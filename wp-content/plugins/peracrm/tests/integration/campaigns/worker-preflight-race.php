<?php
require_once __DIR__.'/prepared-worker-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
worker_fixture_target(static function () use ($mode,$scenario) {
    global $wpdb;
    if ($mode==='prepare') {
        $job=worker_fixture_reset(3); $deployment='integrated-race-'.bin2hex(random_bytes(8));
        prepared_service($deployment); $claims=worker_fixture()->claim($job,2);
        update_option('integrated_worker_race',compact('job','deployment','claims'),false); return;
    }
    extract(get_option('integrated_worker_race'));
    $preparation=prepared_service($deployment); $controls=prepared_controls(); $w=worker_fixture();
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()');
    $transport=new PeraCRM_Prepared_Mock($deployment);
    $pins=prepared_pins($deployment);
    $budget=new PeraCRM_Sending_Budget($pins,static fn()=>prepared_clock(),static fn()=>true);
    if ($mode==='verify') {
        $a=json_decode(file_get_contents('/tmp/peracrm-integrated-winner'),true);
        $b=json_decode(file_get_contents('/tmp/peracrm-integrated-loser'),true);
        prepared_expect($a['connection']!==$b['connection'] && $b['calls']===0,'independent worker denied zero transport '.$scenario);
        prepared_expect($a['calls']===($scenario==='crash-before'?0:1),'winner transport count '.$scenario);
        if ($scenario==='concurrent') { prepared_expect(!$budget->health()['submission_pending'],'success releases latch'); }
        else {
            prepared_expect($budget->health()['submission_pending'],'crash/outage retains durable latch');
            wp_set_current_user((int)get_option('campaign_fixture_administrator'));
            worker_fixture_expire($job); $records=prepared_records($deployment);
            $w->recoverPrepared($claims[0],$preparation,$controls);
            prepared_expect(!$budget->health()['submission_pending'] && $budget->health()['breaker']==='operator'
                && prepared_records($deployment)===$records,'conservative crash recovery no output changes');
        }
        return;
    }
    if ($mode==='winner') {
        if ($scenario==='crash-before') {
            $begun=false;
            add_filter('query',static function ($sql) use (&$begun,$connection) {
                if (str_contains($sql,'submission_started')) { $begun=true; }
                if ($begun && $sql==='START TRANSACTION') {
                    file_put_contents('/tmp/peracrm-integrated-winner',json_encode(['connection'=>$connection,'calls'=>0]));
                    file_put_contents('/tmp/peracrm-integrated-held','begun'); exit(79);
                }
                return $sql;
            });
        }
        $transport->during=static function () use ($scenario,$connection,$transport) {
            file_put_contents('/tmp/peracrm-integrated-winner',json_encode(['connection'=>$connection,'calls'=>$transport->calls]));
            file_put_contents('/tmp/peracrm-integrated-held','transport');
            if ($scenario==='crash-after') { exit(79); }
            if ($scenario==='signal-outage') {
                add_filter('query',static fn($sql)=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'budget_events')?'INVALID_INTEGRATED_SIGNAL':$sql);
                return;
            }
            $until=microtime(true)+15;
            while (!file_exists('/tmp/peracrm-integrated-loser') && microtime(true)<$until) { usleep(20000); }
            prepared_expect(file_exists('/tmp/peracrm-integrated-loser'),'bounded race barrier');
        };
    }
    $failed=false;
    try { $w->submitPrepared($claims[$mode==='winner'?0:1],$preparation,
        new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport,static fn()=>0),$controls); }
    catch (Throwable $error) { $failed=true; }
    file_put_contents('/tmp/peracrm-integrated-'.$mode,json_encode(['connection'=>$connection,'calls'=>$transport->calls]));
    if ($mode==='winner' && $scenario==='signal-outage') { prepared_expect($failed,'signal outage propagates'); exit(79); }
    if ($mode==='loser') { prepared_expect($failed && $transport->calls===0,'durable account exclusion at transport'); }
});
