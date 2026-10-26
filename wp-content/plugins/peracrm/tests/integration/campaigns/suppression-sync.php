<?php
require_once __DIR__.'/suppression-sync-fixture.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    $before=suppress_state();
    foreach (['peracrm_campaign_ses_sync','peracrm_campaign_ses_sync_facts','peracrm_campaign_ses_sync_pages'] as $table) { $wpdb->query('DROP TABLE '.peracrm_table($table)); }
    update_option('peracrm_schema_version',36); update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>36]);
    sns_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema() && suppress_state()===$before,'v37 additive idempotent migration preserves history');
    $pins=sync_pins(); $box='Sync-Mixed-'.bin2hex(random_bytes(5)).'@Example.test'; $local=strtolower($box);
    $wpdb->insert(peracrm_table('peracrm_email_suppressions'),['normalized_email'=>$local,'administrative'=>1,'unsubscribe'=>1,'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
    $wpdb->insert(peracrm_table('peracrm_email_suppression_events'),['normalized_email'=>$local,'reason'=>'administrative','source'=>'historical_fixture','source_ref'=>'keep-original','actor_user_id'=>0,'occurred_at'=>gmdate('Y-m-d H:i:s'),'recorded_at'=>gmdate('Y-m-d H:i:s')]);
    $historical=suppress_rows('peracrm_email_suppression_events');
    $calls=[];
    $read=static function ($cursor) use (&$calls,$box) { $calls[]=$cursor; return sync_page($box,$cursor===null?'case+cursor/=':null); };
    $service=sync_service($pins,$read); $first=$service->run(1);
    sns_expect($first['pages']===1 && $first['last_complete_at']===null && $first['state']==='running','first page commits without false complete');
    $audit=suppress_rows('peracrm_email_suppression_events');
    $done=sync_service($pins,$read)->run(1);
    sns_expect($calls===[null,'case+cursor/='] && $done['pages']===2 && $done['state']==='completed' && !$done['stale'],'new object resumes durable exact cursor');
    sns_expect(suppress_rows('peracrm_email_suppression_events')===$audit && suppress_state()===$before,'duplicate page idempotent and no subscriber/profile/consent mutation');
    $fact=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_ses_sync_facts').' WHERE normalized_email=%s',$local),ARRAY_A);
    sns_expect($fact['provider_mailbox']===$box && $fact['normalized_email']===$local,'provider spelling separate from existing normalization');
    $denied=sync_denials();
    $empty=sync_service($pins,static fn()=>['SuppressedDestinationSummaries'=>[]])->run(1);
    // The empty scan is a new completed run and can cross a second boundary.
    $complete=$empty['last_complete_at'];
    sns_expect((int)$wpdb->get_var($wpdb->prepare('SELECT hard_bounce FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email=%s',$local))===1,'remote removal never clears historical denial');
    sns_expect(suppress_rows('peracrm_email_suppression_events')===$audit,'empty scan adds no audit');
    // Failure on the second page retains first-page denies, exact resume and old complete.
    $fail=true;
    $reader=static function ($cursor) use (&$fail,$box) {
        if ($cursor!==null && $fail) { throw new PeraCRM_SES_Sync_Error('sync_read_failed'); }
        return sync_page($box,$cursor===null?'resume-token':null,'COMPLAINT');
    };
    $service=sync_service($pins,$reader); sync_reject_sql(fn()=>$service->run(2),'sync_read_failed');
    sns_expect($service->health()['state']==='failed' && $service->health()['last_complete_at']===$complete && $service->health()['pages']===1,'failed later page cannot advance complete');
    $fail=false; sns_expect($service->run(1)['state']==='completed','failed scan resumes');
    foreach (['sync_invalid_cursor','sync_stale_cursor','sync_cursor_cycle','sync_unsupported_reason'] as $code) {
        $v=sync_pins(); $svc=sync_service($v,static fn()=>sync_page($box,'again'));
        $svc->run(1); $old=sync_denials();
        if ($code==='sync_stale_cursor') { $wpdb->query('UPDATE '.peracrm_table('peracrm_campaign_ses_sync')." SET started_at='2000-01-01 00:00:00' WHERE state='running'"); }
        $read=match($code) {
            'sync_invalid_cursor'=>static function () { throw new PeraCRM_SES_Sync_Error('sync_invalid_cursor'); },
            'sync_unsupported_reason'=>static fn()=>sync_page($box,null,'FUTURE'),
            default=>static fn()=>sync_page($box,'again'),
        };
        $svc=sync_service($v,$read); sync_reject_sql(fn()=>$svc->run(1),$code);
        sns_expect(sync_denials()===$old && $svc->health()['last_complete_at']===null,'cursor/reason failure leaves page uncommitted '.$code);
        sns_expect(sync_service($v,static fn()=>['SuppressedDestinationSummaries'=>[]])->run(1,true)['state']==='completed','explicit restart safely abandons cursor '.$code);
    }
    // Preserve all historical reasons and the original provenance rows.
    $flags=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email=%s',$local),ARRAY_A);
    sns_expect((int)$flags['hard_bounce']===1 && (int)$flags['complaint']===1 && (int)$flags['administrative']===1 && (int)$flags['unsubscribe']===1,'cumulative distinct reasons preserve all historical flags');
    sns_expect(array_slice(suppress_rows('peracrm_email_suppression_events'),0,count($historical))===$historical,'historical provenance bytes retained');
    foreach (['mailbox','update','audit','fact','page','checkpoint','commit','capture'] as $point) {
        foreach ([false,true] as $reconnect) {
            $v=sync_pins(); $svc=sync_service($v,static fn()=>sync_page('fault-'.bin2hex(random_bytes(5)).'@example.test'));
            $old=sync_denials(); $fired=false; $commits=0; $captures=0;
            $filter=static function ($sql) use ($wpdb,$point,$reconnect,&$fired,&$commits,&$captures) {
                if ($sql==='COMMIT') { $commits++; }
                if ($sql==='SELECT CONNECTION_ID()') { $captures++; }
                $target=match($point) {
                    'mailbox'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'email_suppressions '),
                    'update'=>str_starts_with($sql,'UPDATE ') && str_contains($sql,'email_suppressions '),
                    'audit'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'email_suppression_events '),
                    'fact'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'ses_sync_facts '),
                    'page'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'ses_sync_pages '),
                    'checkpoint'=>str_starts_with($sql,'UPDATE ') && str_contains($sql,'cursor_value='),
                    'commit'=>$sql==='COMMIT' && $commits===2,
                    'capture'=>$sql==='SELECT CONNECTION_ID()' && $captures===2,
                };
                if (!$fired && $target) { $fired=true; if ($reconnect) { $wpdb->close(); $wpdb->db_connect(); } else { return 'INVALID_SYNC_FIXTURE_SQL'; } }
                return $sql;
            };
            add_filter('query',$filter); try { sync_reject_sql(fn()=>$svc->run(1),'sync_storage'); } finally { remove_filter('query',$filter); }
            sns_expect($fired && sync_denials()===$old && $svc->health()['last_complete_at']===null,'atomic rollback/reconnection '.$point.' '.(int)$reconnect);
        }
    }
    // Post-terminal diagnostics cannot downgrade durable completion; failure to
    // initialize a successor cannot invalidate the previous completed scan.
    foreach (['post-commit','health','initialize-update','initialize-commit'] as $point) {
        $v=sync_pins(); $svc=sync_service($v,static fn()=>sync_page($box));
        $svc->run(1); $old=$svc->health(); $fired=false; $commits=0;
        $filter=static function ($sql) use ($point,&$fired,&$commits) {
            if ($sql==='COMMIT') { $commits++; }
            $target=match($point) {
                'post-commit'=>$commits===2 && $sql==='SELECT CONNECTION_ID()',
                'health'=>$commits===2 && str_starts_with($sql,'SELECT * FROM') && str_contains($sql,'ses_sync ') && !str_ends_with($sql,'FOR UPDATE'),
                'initialize-update'=>str_starts_with($sql,'UPDATE ') && str_contains($sql,' SET run_id='),
                'initialize-commit'=>$sql==='COMMIT' && $commits===1,
            };
            if (!$fired && $target) { $fired=true; return 'INVALID_SYNC_DIAGNOSTIC_SQL'; }
            return $sql;
        };
        add_filter('query',$filter); try { sync_reject_sql(fn()=>$svc->run(1),'sync_storage'); } finally { remove_filter('query',$filter); }
        sns_expect($fired && $svc->health()['state']==='completed' && $svc->health()['error_code']===null,'completed checkpoint survives '.$point);
        if (str_starts_with($point,'initialize')) { sns_expect($svc->health()===$old,'failed successor initialization preserves prior completion'); }
        sns_expect($svc->run(1)['state']==='completed','next scan starts cleanly after '.$point);
    }
    $v=sync_pins(); $read=static fn()=>sync_page($box); $wrong=$v; $wrong['account_id']='999999999999';
    sync_reject_sql(fn()=>new PeraCRM_SES_Suppression_Sync($v,new PeraCRM_Sync_Fixture_Reader($wrong,$read)),'sync_scope');
    $svc=sync_service($v,$read);
    if (is_multisite()) { switch_to_blog(1); try { sync_reject_sql(fn()=>$svc->run(1),'sync_scope'); } finally { restore_current_blog(); } }
    $wpdb->query('START TRANSACTION');
    try { sync_reject_sql(fn()=>$svc->run(1),'sync_storage'); sns_expect($wpdb->query('SAVEPOINT sync_caller')!==false && $wpdb->query('RELEASE SAVEPOINT sync_caller')!==false,'outer transaction preserved'); }
    finally { $wpdb->query('ROLLBACK'); }
    $old=sync_denials(); $health=$svc->health(); sns_expect(sync_denials()===$old && !str_contains(json_encode($health),'@'),'health read-only and mailbox-free');
    sns_expect(peracrm_ses_suppression_sync_service()===null && !peracrm_campaign_queue_dispatch_allowed(),'production sync and sending remain disabled');
});
echo "PASS suppression sync SQL fixtures\n";
