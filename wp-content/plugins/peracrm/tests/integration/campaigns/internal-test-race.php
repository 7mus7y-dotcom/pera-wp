<?php
require_once __DIR__.'/internal-test-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
worker_fixture_target(static function () use ($mode,$scenario) {
    global $wpdb;
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    if ($mode==='prepare') {
        $job=internal_test_source(); $deployment='internal-race-'.bin2hex(random_bytes(8));
        $s=internal_test_service($deployment,new PeraCRM_Campaign_Internal_Test_Transport());
        $id=internal_test_authorize($s,$job);
        update_option('internal_test_race',compact('job','deployment','id'),false); return;
    }
    extract(get_option('internal_test_race'));
    $transport=new PeraCRM_Campaign_Internal_Test_Transport();
    $s=internal_test_service($deployment,$transport);
    if ($mode==='winner') {
        $begun=false; $staged=false;
        add_filter('query',static function ($sql) use (&$begun,&$staged,$scenario,$transport) {
            if (str_contains($sql,'submission_started')) { $begun=true; }
            if (str_contains($sql,'outcome_staged=1')) { $staged=true; }
            if ($sql==='START TRANSACTION' && ($scenario==='crash-after'?$staged:$begun)) {
                file_put_contents('/tmp/peracrm-internal-held','held');
                if ($scenario!=='concurrent') {
                    file_put_contents('/tmp/peracrm-internal-calls',(string)$transport->calls); exit(79);
                }
                $until=microtime(true)+15;
                while (!file_exists('/tmp/peracrm-internal-loser') && microtime(true)<$until) { usleep(20000); }
                prepared_expect(file_exists('/tmp/peracrm-internal-loser'),'bounded concurrent coordinator barrier');
                $begun=false;
            }
            return $sql;
        });
        $s->execute($id); file_put_contents('/tmp/peracrm-internal-calls',(string)$transport->calls); return;
    }
    if ($mode==='loser') {
        prepared_reject(fn()=>$s->execute($id));
        prepared_expect($transport->calls===0,'independent duplicate/crashed invocation cannot send');
        file_put_contents('/tmp/peracrm-internal-loser','denied'); return;
    }
    if ($mode==='verify') {
        $calls=(int)file_get_contents('/tmp/peracrm-internal-calls');
        prepared_expect($calls===($scenario==='crash-before'?0:1),'actual process boundary count '.$scenario);
        if ($scenario!=='concurrent') {
            $before=prepared_records($deployment); worker_fixture_advance(61);
            $s->recover($id,wp_create_nonce('peracrm_internal_test_recover_'.$id));
            prepared_expect($s->audit()[$id]['state']==='recovered' && worker_fixture_rows($job)[0]['state']==='unknown'
                && prepared_records($deployment)===$before && $transport->calls===0,'process death releases native lock; recovery preserves output and fences unknown');
        }
        prepared_reject(fn()=>$s->execute($id));
        return;
    }
    throw new RuntimeException('internal race mode');
});
