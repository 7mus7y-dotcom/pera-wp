<?php
require_once __DIR__.'/ses-suppression-fixture.php';
require_once __DIR__.'/prepared-worker-fixture.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    $v=sns_values(); $v['deployment']='suppress-'.bin2hex(random_bytes(8));
    $apps=peracrm_table('peracrm_campaign_ses_applications'); $refs=peracrm_table('peracrm_campaign_ses_application_receipts');
    $wpdb->query('DROP TABLE '.$apps); $wpdb->query('DROP TABLE '.$refs);
    update_option('peracrm_schema_version',34); update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>34]);
    $before=ses_match_snapshot();
    sns_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema(),'v34 additive/idempotent suppression migration');
    sns_expect(ses_match_snapshot()===$before,'migration preserves permissions and existing evidence');
    // The actual schema assertion requires full logical-event uniqueness.
    $wpdb->query("ALTER TABLE {$apps} DROP PRIMARY KEY"); $denied=false;
    try { peracrm_campaigns_assert_schema(); } catch (Throwable $e) { $denied=true; }
    sns_expect($denied,'application uniqueness required'); peracrm_campaigns_install_schema();
    $seed=ses_match_seed($v['deployment'],1000); $event=suppress_event($seed,'Bounce');
    $id=suppress_receive($v,$event,1000);
    $wpdb->update(peracrm_table('peracrm_email_suppressions'),['administrative'=>1,'unsubscribe'=>1],['normalized_email'=>$seed['binding']['mailbox']]);
    suppress_apply($v,$id);
    $audit=suppress_rows('peracrm_email_suppression_events'); $last=end($audit);
    sns_expect($last['source']==='ses_authenticated' && (int)$last['actor_user_id']===0
        && preg_match('/\A[a-f0-9]{64}:[a-f0-9]{64}\z/',$last['source_ref'])===1,'server-controlled system actor and exact scope/event evidence');
    $first=suppress_atomic(); suppress_apply($v,$id); sns_expect(suppress_atomic()===$first,'same receipt replay is a complete no-op');
    $duplicate=suppress_receive($v,$event,1001); $ledger=suppress_rows('peracrm_campaign_ses_applications');
    $audit=suppress_rows('peracrm_email_suppression_events'); suppress_apply($v,$duplicate);
    sns_expect(suppress_rows('peracrm_campaign_ses_applications')===$ledger && suppress_rows('peracrm_email_suppression_events')===$audit,'logical duplicate keeps first provenance and one suppression audit');
    $complaint=suppress_receive($v,suppress_event($seed,'Complaint'),1002); suppress_apply($v,$complaint);
    $identity=peracrm_campaigns_repository_identity($seed['binding']['mailbox']);
    sns_expect((int)$identity['hard_bounce']===1 && (int)$identity['complaint']===1 && (int)$identity['administrative']===1 && (int)$identity['unsubscribe']===1,'distinct valid reasons retained cumulatively');
    foreach ([['Bounce','Transient'],['Bounce','Undetermined'],['Complaint','not-spam'],['Complaint','future-type'],['Delivery',''],['DeliveryDelay',''],['Send','']] as $n=>[$type,$subtype]) {
        $event=suppress_event($seed,$type,$subtype);
        if (in_array($type,['Bounce','Complaint'],true)) { $event[lcfirst($type)]['feedbackId']='unsupported-'.$n; }
        $unsupported=suppress_receive($v,$event,1010+$n); $before=suppress_atomic();
        suppress_apply($v,$unsupported,'unsupported_feedback'); sns_expect(suppress_atomic()===$before,'unsupported feedback neither clears nor adds denial '.$type.$subtype);
    }
    // Absent subtype retains the documented Complaint meaning under exact binding.
    $event=suppress_event($seed,'Complaint'); unset($event['complaint']['complaintFeedbackType']); $event['complaint']['feedbackId']='unspecified';
    suppress_apply($v,suppress_receive($v,$event,1020));
    // Changed mailbox and deleted/reused subscriber ID cannot retarget history.
    foreach (['changed','deleted','reused'] as $n=>$mode) {
        $seed=ses_match_seed($v['deployment'],1030+$n); $id=suppress_receive($v,suppress_event($seed,'Bounce'),1030+$n);
        $mailbox=$seed['binding']['mailbox']; $subscriber=$seed['binding']['subscriber_id']; $replacement=$mode.'-replacement@example.test';
        if ($mode==='changed') { $wpdb->update(peracrm_table('peracrm_subscribers'),['email'=>$replacement,'normalized_email'=>$replacement],['id'=>$subscriber]); }
        else {
            $wpdb->delete(peracrm_table('peracrm_subscribers'),['id'=>$subscriber]);
            if ($mode==='deleted') { $wpdb->delete(peracrm_table('peracrm_email_suppressions'),['normalized_email'=>$mailbox]); }
        }
        peracrm_campaigns_repository_replace_identity($subscriber,$replacement,0);
        if ($mode==='reused') { peracrm_test_persistence_subscriber($subscriber,$replacement); }
        suppress_apply($v,$id);
        $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email=%s',$mailbox),ARRAY_A);
        sns_expect((int)$old['hard_bounce']===1 && !peracrm_campaigns_repository_identity($mailbox),'historical mailbox suppressed without subscriber recreation '.$mode);
        $new=peracrm_campaigns_repository_identity($replacement);
        sns_expect(!$new || empty($new['hard_bounce']),'replacement address untouched '.$mode);
    }
    foreach (['unmatched','missing','tags','message','later-conflict','destinations'] as $n=>$mode) {
        $seed=ses_match_seed($v['deployment'],1050+$n,$mode!=='missing'); $event=suppress_event($seed,'Bounce');
        if ($mode==='tags') { $event['mail']['tags']['peracrm_snapshot']=[str_repeat('f',64)]; }
        if ($mode==='destinations') { $event['mail']['destination'][]='other@example.test'; }
        if ($mode==='message') { PeraCRM_Campaign_Submission_Response::record($v['deployment'],$seed['submission'],'different-message'); }
        $id=suppress_receive($v,$event,1050+$n,$mode!=='unmatched');
        if ($mode==='later-conflict') { PeraCRM_Campaign_Submission_Response::record($v['deployment'],$seed['submission'],'later-contradiction'); }
        $expected=['unmatched'=>'matching_pending','missing'=>'missing_binding','tags'=>'binding_conflict','message'=>'message_conflict','later-conflict'=>'message_conflict','destinations'=>'wrong_recipient'][$mode];
        $before=suppress_atomic(); suppress_apply($v,$id,$expected); sns_expect(suppress_atomic()===$before,'quarantine preserved '.$mode);
    }
    $seed=ses_match_seed($v['deployment'],1070); $id=suppress_receive($v,suppress_event($seed,'Bounce'),1070);
    $before=suppress_atomic();
    sns_reject(fn()=>(new PeraCRM_SES_Suppression(sns_verifier($v),$v['deployment'].'other',get_current_blog_id(),ses_event_scope()))->apply($id),'sns_storage');
    $scope=ses_event_scope(); $scope['configuration_version']=2;
    sns_reject(fn()=>(new PeraCRM_SES_Suppression(sns_verifier($v),$v['deployment'],get_current_blog_id(),$scope))->apply($id),'sns_storage');
    $unnormalized=ses_event_id(1071); (new PeraCRM_SNS_Receipts(sns_verifier($v),$v['deployment'],get_current_blog_id()))->receive(ses_event_body($v,suppress_event($seed,'Bounce'),$unnormalized),$v['now']);
    sns_reject(fn()=>suppress_service($v)->apply($unnormalized),'sns_storage');
    if (is_multisite()) { $service=suppress_service($v); switch_to_blog(1); try { sns_reject(fn()=>$service->apply($id),'sns_storage'); } finally { restore_current_blog(); } }
    // Fail every write boundary, current read and COMMIT; replace the actual SQL
    // connection at each boundary to exercise replay-on-autocommit protections.
    foreach (['mailbox','update','audit','application','reference','select','commit','capture'] as $n=>$point) {
        foreach ([false,true] as $reconnect) {
            if ($point==='capture' && !$reconnect) { continue; }
            $seed=ses_match_seed($v['deployment'],1100+$n*2+(int)$reconnect); $id=suppress_receive($v,suppress_event($seed,'Bounce'),1100+$n*2+(int)$reconnect);
            $before=suppress_atomic(); $fired=false;
            $filter=static function ($sql) use ($point,$reconnect,&$fired,$wpdb) {
                $target=match ($point) {
                    'mailbox'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'email_suppressions '),
                    'update'=>str_starts_with($sql,'UPDATE ') && str_contains($sql,'email_suppressions '),
                    'audit'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'email_suppression_events '),
                    'application'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'ses_applications '),
                    'reference'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'ses_application_receipts '),
                    'select'=>str_starts_with($sql,'SELECT * FROM') && str_contains($sql,'submission_attempts') && str_ends_with($sql,'FOR UPDATE'),
                    'commit'=>$sql==='COMMIT', 'capture'=>$sql==='SELECT CONNECTION_ID()',
                };
                if (!$fired && $target) { $fired=true; if ($reconnect) { $wpdb->close(); $wpdb->db_connect(); } else { return 'INVALID_SUPPRESSION_FIXTURE_SQL'; } }
                return $sql;
            };
            add_filter('query',$filter); try { sns_reject(fn()=>suppress_service($v)->apply($id),'sns_storage'); } finally { remove_filter('query',$filter); }
            sns_expect($fired && suppress_atomic()===$before,'atomic rollback/session replacement '.$point.' '.(int)$reconnect);
            suppress_apply($v,$id);
        }
    }
    foreach ([false,true] as $failedProbe) {
        $wpdb->query('START TRANSACTION');
        $filter=static fn($sql)=>$sql==='RELEASE SAVEPOINT peracrm_suppress_top_level_probe'?'INVALID_SUPPRESS_PROBE_SQL':$sql;
        if ($failedProbe) { add_filter('query',$filter); }
        try { sns_reject(fn()=>suppress_service($v)->apply($id),'sns_storage'); } finally { if ($failedProbe) { remove_filter('query',$filter); } }
        sns_expect($wpdb->query('SAVEPOINT suppression_caller')!==false && $wpdb->query('RELEASE SAVEPOINT suppression_caller')!==false,'caller transaction preserved'); $wpdb->query('ROLLBACK');
    }
});
