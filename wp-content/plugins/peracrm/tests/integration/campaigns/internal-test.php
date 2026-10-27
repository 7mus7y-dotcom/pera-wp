<?php
require_once __DIR__.'/internal-test-fixture.php';
worker_fixture_target(static function () {
    global $wpdb;
    $admin=(int)get_option('campaign_fixture_administrator');
    $startBlog=get_current_blog_id();
    foreach (['accepted','unknown','rejected','throttled','stale','stale-sync','breaker','configuration','unauthorized','cap','crash-before','crash-after','journal-failure','withdraw'] as $scenario) {
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
        elseif ($scenario==='stale-sync') {
            $scope=hash('sha256',json_encode(['policy'=>'ses-account-deny-v1']+prepared_pins($deployment)));
            $wpdb->update(peracrm_table('peracrm_campaign_ses_sync'),['last_complete_at'=>gmdate('Y-m-d H:i:s',prepared_clock()-86400)],['scope_hash'=>$scope]);
            prepared_reject(fn()=>$s->execute($id));
            prepared_expect($transport->calls===0 && worker_fixture_rows($job)[0]['state']==='held','stale sync stops coordinator before claiming');
        } elseif ($scenario==='breaker') {
            $budget=new PeraCRM_Sending_Budget(prepared_pins($deployment),static fn()=>prepared_clock(),static fn()=>true);
            $budget->signal('provider_rejected'); prepared_reject(fn()=>$s->execute($id));
            prepared_expect($transport->calls===0 && worker_fixture_rows($job)[0]['state']==='held','breaker stops coordinator before claiming');
        }
        elseif ($scenario==='configuration') {
            $table=peracrm_table('peracrm_campaign_configuration');
            $wpdb->query("UPDATE {$table} SET revision=revision+1 WHERE id=1");
            prepared_reject(fn()=>$s->execute($id));
            $wpdb->query("UPDATE {$table} SET revision=revision-1 WHERE id=1");
        } elseif ($scenario==='withdraw') {
            // Restore exact disposable rows after proving withdrawal; normal
            // production consent intentionally cannot clear this durable denial.
            $mailbox='internal-test-1@example.test';
            $subscriber=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_subscribers').' WHERE normalized_email=%s',$mailbox),ARRAY_A);
            $suppression=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email=%s',$mailbox),ARRAY_A);
            try {
                $withdrawn=peracrm_campaigns_record_consent($mailbox,'unsubscribed',[
                    'source'=>'synthetic_fixture','source_ref'=>'internal-withdraw','occurred_at'=>'2026-01-01 00:00:00']);
                prepared_expect(!is_wp_error($withdrawn),'withdrawal committed');
                prepared_expect(is_wp_error(peracrm_campaigns_record_consent($mailbox,'subscribed',[
                    'source'=>'synthetic_fixture','source_ref'=>'internal','evidence_ref'=>'synthetic','policy_version'=>'v1','occurred_at'=>'2020-01-01 00:00:00'])),
                    'ordinary consent cannot clear withdrawn fixture denial');
                $out=$s->execute($id);
                prepared_expect($out['results'][0]['state']==='excluded' && $transport->calls===0,'live consent never bypassed');
            } finally {
                peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_subscribers'),$subscriber,['id'=>$subscriber['id']]));
                if ($suppression) { peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_email_suppressions'),$suppression,['normalized_email'=>$mailbox])); }
                else { peracrm_campaigns_db_require($wpdb->delete(peracrm_table('peracrm_email_suppressions'),['normalized_email'=>$mailbox])); }
            }
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
            $snapshot=worker_fixture()->internalTestSnapshot($job);
            prepared_expect((int)$snapshot['job']['submission_complete']===(in_array($scenario,['accepted','rejected'],true)?1:0),
                'shared completion preserves ambiguous/retry holds');
            if ($scenario==='accepted') {
                $records=prepared_records($deployment);
                prepared_expect(count($records['recipient_tokens'])===4 && count($records['submission_attempts'])===2
                    && count($records['submission_outputs'])===2 && $transport->calls===2,'both recipients retain complete immutable prepared records');
            }
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
