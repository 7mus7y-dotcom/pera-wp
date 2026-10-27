<?php
require_once __DIR__.'/internal-test-fixture.php';
worker_fixture_target(static function () {
    global $wpdb;
    $admin=(int)get_option('campaign_fixture_administrator');
    $startBlog=get_current_blog_id();
    foreach (['accepted','unknown','rejected','throttled','stale','configuration','unauthorized','cap','crash-before','crash-after','journal-failure','withdraw'] as $scenario) {
        $job=internal_test_source($scenario==='cap'?3:($scenario==='accepted'?2:1),$scenario==='unauthorized');
        $deployment='internal-'.bin2hex(random_bytes(8));
        $transport=new PeraCRM_Campaign_Internal_Test_Transport(in_array($scenario,['unknown','rejected','throttled'],true)?$scenario:'accepted');
        $offset=0;
        $s=internal_test_service($deployment,$transport,static function () use (&$offset) { return prepared_clock()+$offset; });
        wp_set_current_user($admin);
        if (in_array($scenario,['unauthorized','cap'],true)) {
            prepared_reject(fn()=>$s->proposal($job));
            prepared_expect(!$s->audit() && $transport->calls===0,'unknown recipients/oversized job denied before authorization');
            continue;
        }
        $binding=$s->proposal($job)['binding'];
        prepared_reject(fn()=>$s->authorize($job,$binding,'bad','synthetic-only'));
        prepared_reject(fn()=>$s->authorize($job,$binding,wp_create_nonce('peracrm_internal_test_'.$binding),'send'));
        foreach (['campaign_fixture_manager','campaign_fixture_employee'] as $role) {
            wp_set_current_user((int)get_option($role)); prepared_reject(fn()=>internal_test_authorize($s,$job));
        }
        wp_set_current_user(0); prepared_reject(fn()=>internal_test_authorize($s,$job));
        wp_set_current_user($admin);
        update_user_meta($admin,PERACRM_VIEW_AS_USER_META_KEY,(int)get_option('campaign_fixture_employee'));
        prepared_reject(fn()=>internal_test_authorize($s,$job)); delete_user_meta($admin,PERACRM_VIEW_AS_USER_META_KEY);
        $id=internal_test_authorize($s,$job);
        prepared_reject(fn()=>internal_test_authorize($s,$job));
        if ($scenario==='stale') { $offset=300; prepared_reject(fn()=>$s->execute($id)); $offset=0; }
        elseif ($scenario==='configuration') {
            $table=peracrm_table('peracrm_campaign_configuration');
            $wpdb->query("UPDATE {$table} SET revision=revision+1 WHERE id=1");
            prepared_reject(fn()=>$s->execute($id));
            $wpdb->query("UPDATE {$table} SET revision=revision-1 WHERE id=1");
        } elseif ($scenario==='withdraw') {
            peracrm_campaigns_record_consent('internal-test-1@example.test','unsubscribed',[
                'source'=>'synthetic_fixture','source_ref'=>'internal-withdraw','occurred_at'=>'2026-01-01 00:00:00']);
            $out=$s->execute($id);
            prepared_expect($out['results'][0]['state']==='excluded' && $transport->calls===0,'live consent never bypassed');
        } elseif (in_array($scenario,['crash-before','crash-after'],true)) {
            $inflight=false; $record=false; $committed=false;
            $filter=static function ($sql) use (&$inflight,&$record,&$committed,$scenario) {
                if (str_contains($sql,'peracrm_fixture_worker_deliveries') && str_starts_with($sql,'UPDATE') && str_contains($sql,'inflight')) { $inflight=true; }
                if (str_contains($sql,'outcome_staged=1')) { $record=true; }
                if ($sql==='COMMIT' && $inflight) { $committed=true; }
                // Throw immediately after a committed inflight boundary, or after
                // staged outcome COMMIT. A new coordinator then recovers it.
                if (($scenario==='crash-before' && $committed && $sql==='SELECT CONNECTION_ID()')
                    || ($scenario==='crash-after' && $record && $sql==='START TRANSACTION')) { throw new RuntimeException('synthetic crash'); }
                return $sql;
            };
            add_filter('query',$filter);
            try { prepared_reject(fn()=>$s->execute($id)); } finally { remove_filter('query',$filter); }
            $before=prepared_records($deployment); $calls=$transport->calls;
            prepared_expect($calls===($scenario==='crash-before'?0:1),'crash boundary mock count');
            worker_fixture_advance(61);
            $s=internal_test_service($deployment,$transport);
            $s->recover($id,wp_create_nonce('peracrm_internal_test_recover_'.$id));
            prepared_expect($s->audit()[$id]['state']==='recovered' && worker_fixture_rows($job)[0]['state']==='unknown'
                && prepared_records($deployment)===$before && $transport->calls===$calls,'crash recovery fences unknown without transport or output regeneration');
            prepared_reject(fn()=>$s->execute($id));
            continue;
        } elseif ($scenario==='journal-failure') {
            $filter=static fn($sql)=>str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_internal_test_v1_')?'INVALID_INTERNAL_TEST_SQL':$sql;
            add_filter('query',$filter); $quiet=$wpdb->suppress_errors(true);
            try { prepared_reject(fn()=>$s->execute($id)); }
            finally { remove_filter('query',$filter); $wpdb->suppress_errors($quiet); }
            prepared_expect($transport->calls===0 && worker_fixture_rows($job)[0]['state']==='held','failed start audit leaves zero claims/calls');
        } else {
            $out=$s->execute($id);
            $state=['accepted'=>'accepted','unknown'=>'unknown','rejected'=>'failed','throttled'=>'retry'][$scenario];
            prepared_expect(count($out['results'])===($scenario==='accepted'?2:1) && $out['results'][0]['state']===$state
                && !$out['inbox_verified'] && !$out['production_ready'],'complete mocked orchestration '.$scenario);
            $calls=$transport->calls; prepared_reject(fn()=>$s->execute($id));
            prepared_expect($transport->calls===$calls,'duplicate execution never resubmits');
        }
        $serialized=wp_json_encode($s->audit());
        prepared_expect(!preg_match('/@|mailbox|owner_token|attempt_key|html|text|credentials/',$serialized),'audit projection excludes private runtime data');
        prepared_expect(!peracrm_campaign_queue_dispatch_allowed() && peracrm_sending_budget_service()===null
            && peracrm_ses_suppression_sync_service()===null && get_current_blog_id()===$startBlog,'production factories/gates and target context preserved');
    }
    // Cap includes canceled/expired authorizations and cannot be reset by a new service.
    $deployment='internal-cap-'.bin2hex(random_bytes(8)); $transport=new PeraCRM_Campaign_Internal_Test_Transport();
    for ($i=0;$i<3;$i++) {
        $job=internal_test_source(); $s=internal_test_service($deployment,$transport);
        $id=internal_test_authorize($s,$job);
        $s->recover($id,wp_create_nonce('peracrm_internal_test_recover_'.$id));
    }
    $job=internal_test_source(); $s=internal_test_service($deployment,$transport);
    prepared_reject(fn()=>internal_test_authorize($s,$job));
    prepared_expect(count($s->audit())===3 && $transport->calls===0,'durable lifetime run cap includes recovered runs');
});
echo "PASS controlled internal-test orchestration\n";
