<?php
require_once __DIR__.'/prepared-worker-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
if (!in_array($scenario,['concurrent','withdraw','cancel','interrupted'],true)) { throw new RuntimeException('Invalid prepared race.'); }
if ($mode==='prepare') {
    $job=worker_fixture_reset(1); $claim=worker_fixture()->claim($job,1)[0];
    $deployment='prepared-race-'.bin2hex(random_bytes(8));
    worker_fixture_target(static fn()=>update_option('prepared_worker_race',
        ['job'=>$job,'claim'=>$claim,'deployment'=>$deployment],false)); return;
}
extract(worker_fixture_target(static fn()=>get_option('prepared_worker_race')));
if ($mode==='verify') {
    $a=json_decode(file_get_contents('/tmp/peracrm-prepared-winner'),true);
    $b=json_decode(file_get_contents('/tmp/peracrm-prepared-loser'),true);
    prepared_expect($a['connection']!==$b['connection'],'independent SQL connections');
    $rows=worker_fixture_rows($job); $records=prepared_records($deployment);
    prepared_expect(count($records['recipient_tokens'])===2 && count($records['submission_attempts'])===1
        && count($records['submission_outputs'])===1,'racing workers retain one immutable attempt and output');
    $expected=in_array($scenario,['withdraw','cancel'],true)?0:1;
    prepared_expect($a['calls']+$b['calls']===$expected,'exact bounded mock call count for '.$scenario);
    prepared_expect($rows[0]['state']===($expected?'accepted':'excluded'),'final fenced race outcome '.$scenario);
    if ($scenario==='withdraw') {
        worker_fixture_target(static function () use ($claim) {
            global $wpdb; $original=get_option('prepared_worker_race_subscriber');
            $wpdb->update(peracrm_table('peracrm_subscribers'),$original,['id'=>$claim['subscriber_id']]);
            $wpdb->delete(peracrm_table('peracrm_email_suppressions'),['normalized_email'=>$claim['mailbox']]);
        });
    }
    echo 'PASS integrated worker race '.$scenario."\n"; return;
}
if (!in_array($mode,['winner','loser'],true)) { throw new RuntimeException('Invalid prepared race mode.'); }
global $wpdb; $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $started=microtime(true);
$transport=new PeraCRM_Prepared_Mock($deployment); $mapper=new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport,static fn()=>0);
if ($mode==='winner') {
    $output=false; $committed=false;
    add_filter('query',static function ($sql) use (&$output,&$committed,$scenario,$connection) {
        if (str_starts_with($sql,'INSERT INTO') && str_contains($sql,'submission_outputs')) { $output=true; }
        if ($sql==='COMMIT' && $output && !$committed) {
            $committed=true; file_put_contents('/tmp/peracrm-prepared-held','locked'); usleep(1500000);
        }
        if ($sql==='START TRANSACTION' && $committed) {
            if ($scenario==='interrupted') {
                file_put_contents('/tmp/peracrm-prepared-winner',json_encode(['connection'=>$connection,'calls'=>0]));
                exit(77); // State committed, authorization has never happened.
            }
            if (in_array($scenario,['withdraw','cancel'],true)) {
                $until=microtime(true)+15;
                while (!file_exists('/tmp/peracrm-prepared-loser') && microtime(true)<$until) { usleep(20000); }
                if (!file_exists('/tmp/peracrm-prepared-loser')) { throw new RuntimeException('Race synchronization failed.'); }
            }
        }
        return $sql;
    });
}
try {
    if ($mode==='loser' && $scenario==='withdraw') {
        wp_set_current_user(worker_fixture_target(static fn()=>(int)get_option('campaign_fixture_manager')));
        worker_fixture_target(static function () use ($claim) {
            global $wpdb; update_option('prepared_worker_race_subscriber',$wpdb->get_row($wpdb->prepare(
                'SELECT * FROM '.peracrm_table('peracrm_subscribers').' WHERE id=%d',$claim['subscriber_id']),ARRAY_A),false);
        });
        $result=peracrm_campaigns_record_consent($claim['mailbox'],'unsubscribed',
            ['source'=>'synthetic_fixture','source_ref'=>'prepared-race','occurred_at'=>'2026-01-01 00:00:00']);
        prepared_expect(!is_wp_error($result),'concurrent withdrawal committed');
    } elseif ($mode==='loser' && $scenario==='cancel') { worker_fixture_state($job,'cancel'); }
    else { worker_fixture()->submitPrepared($claim,prepared_service($deployment,$mode==='loser' && $scenario==='interrupted'),$mapper,prepared_controls()); }
} catch (Throwable $error) {
    if ($scenario!=='concurrent') { throw $error; }
}
file_put_contents('/tmp/peracrm-prepared-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$started,'calls'=>$transport->calls]));
