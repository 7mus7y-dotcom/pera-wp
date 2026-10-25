<?php
require_once __DIR__.'/ses-matching-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/submission-response.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    $v=sns_values(); $deployment='accept-'.bin2hex(random_bytes(8)); $blog=get_current_blog_id();
    $matcher=new PeraCRM_SES_Matcher(sns_verifier($v),$deployment,$blog,ses_event_scope());
    $table=peracrm_table('peracrm_campaign_acceptance_evidence');
    $messages=peracrm_table('peracrm_campaign_provider_messages');
    $rows=static fn()=> $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE deployment=%s ORDER BY 1,2,3,4,5",$deployment),ARRAY_A);
    $old=$wpdb->get_results("SELECT * FROM {$messages} ORDER BY 1,2",ARRAY_A);
    $wpdb->query('DROP TABLE '.$table);
    update_option('peracrm_schema_version',33); update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>33]);
    sns_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema(),'v33 acceptance migration idempotent');
    sns_expect($old===$wpdb->get_results("SELECT * FROM {$messages} ORDER BY 1,2",ARRAY_A),'migration preserves historical message provenance');
    $wpdb->query('ALTER TABLE '.peracrm_table('peracrm_campaign_ses_matches').' DROP INDEX binding_decision');
    $failed=false; try { peracrm_campaigns_assert_schema(); } catch (RuntimeException $e) { $failed=true; }
    sns_expect($failed,'schema rejects missing conflict lookup index'); peracrm_campaigns_install_schema();
    foreach (['event-first','response-first'] as $n=>$order) {
        $seed=ses_match_seed($deployment,500+$n); $message=$seed['event']['mail']['messageId'];
        $id=ses_match_receive($v,$deployment,$seed['event'],700+$n);
        $response=fn($m)=>PeraCRM_Campaign_Submission_Response::record($deployment,$seed['submission'],$m);
        if ($order==='event-first') { ses_match_check(fn()=>$matcher->match($id),'matched'); }
        sns_expect($response($message)==='accepted','response '.$order);
        if ($order==='response-first') { ses_match_check(fn()=>$matcher->match($id),'matched'); }
        $before=$rows();
        sns_expect($response($message)==='accepted','duplicate response accepted');
        ses_match_check(fn()=>$matcher->match($id),'matched');
        sns_expect($rows()===$before,'arrival order and duplicate preserve evidence');
        sns_expect(count(array_filter($before,fn($r)=>$r['attempt_key']===$seed['metadata']['attempt_key']))===2,'separate durable event/response evidence');
        $association=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$messages} WHERE deployment=%s AND attempt_key=%s",$deployment,$seed['metadata']['attempt_key']),ARRAY_A);
        sns_expect($response('conflicting-'.$n)==='message_conflict','conflicting response durably quarantined');
        sns_expect($association===$wpdb->get_row($wpdb->prepare("SELECT * FROM {$messages} WHERE deployment=%s AND attempt_key=%s",$deployment,$seed['metadata']['attempt_key']),ARRAY_A),'conflict preserves immutable association');
        $wpdb->query('START TRANSACTION');
        sns_expect(PeraCRM_Campaign_Acceptance::fenced($deployment,$seed['metadata']['delivery_key']),'acceptance/conflict fences retry');
        sns_expect(!PeraCRM_Campaign_Acceptance::fenced($deployment.'other',$seed['metadata']['delivery_key']),'deployment isolation');
        $wpdb->query('ROLLBACK');
    }
    $seed=ses_match_seed($deployment,510);
    $wrong=$seed['submission']->fields(); $wrong['subject']='different immutable envelope';
    sns_expect(PeraCRM_Campaign_Submission_Response::record($deployment,new PeraCRM_CampaignSubmissionV1($wrong),'wrong-binding')==='binding_conflict','response binding conflict durably quarantined');
    $second=ses_match_seed($deployment,511);
    sns_expect(PeraCRM_Campaign_Submission_Response::record($deployment,$second['submission'],'shared-message')==='accepted','first response message binding');
    $third=ses_match_seed($deployment,512);
    sns_expect(PeraCRM_Campaign_Submission_Response::record($deployment,$third['submission'],'shared-message')==='message_conflict','response cannot alias another attempt');
    foreach (['message','evidence','select','commit','reconnect-message','reconnect-evidence','reconnect-commit','reconnect-capture'] as $n=>$failure) {
        $seed=ses_match_seed($deployment,520+$n); $before=$rows(); $fired=false;
        $filter=static function ($sql) use ($failure,&$fired,$wpdb) {
            $message=str_starts_with($sql,'INSERT INTO') && str_contains($sql,'campaign_provider_messages');
            $evidence=str_starts_with($sql,'INSERT INTO') && str_contains($sql,'campaign_acceptance_evidence');
            $target=($message && str_ends_with($failure,'message')) || ($evidence && str_ends_with($failure,'evidence'))
                || ($sql==='COMMIT' && str_ends_with($failure,'commit')) || ($sql==='SELECT CONNECTION_ID()' && $failure==='reconnect-capture');
            if (!$fired && $target) {
                $fired=true;
                if (str_starts_with($failure,'reconnect-')) { $wpdb->close(); $wpdb->db_connect(); }
                else { return 'INVALID_ACCEPTANCE_FIXTURE_SQL'; }
            }
            if ($failure==='select' && str_starts_with($sql,'SELECT * FROM') && str_contains($sql,'submission_attempts')) { return 'INVALID_ACCEPTANCE_FIXTURE_SQL'; }
            return $sql;
        };
        add_filter('query',$filter); $denied=false;
        try { PeraCRM_Campaign_Submission_Response::record($deployment,$seed['submission'],'rollback-'.$n); }
        catch (RuntimeException $e) { $denied=$e->getMessage()==='response_storage' && $e->getPrevious()===null; }
        finally { remove_filter('query',$filter); }
        sns_expect($denied && $before===$rows(),'response atomic rollback/reconnection '.$failure);
        sns_expect(!$wpdb->get_var($wpdb->prepare("SELECT message_id FROM {$messages} WHERE deployment=%s AND attempt_key=%s",$deployment,$seed['metadata']['attempt_key'])),'no partial association');
        sns_expect(PeraCRM_Campaign_Submission_Response::record($deployment,$seed['submission'],'rollback-'.$n)==='accepted','response recovery after rollback');
    }
    foreach ([false,true] as $failedProbe) {
        $wpdb->query('START TRANSACTION');
        $filter=static fn($sql)=>$sql==='RELEASE SAVEPOINT peracrm_response_top_level_probe'?'INVALID_RESPONSE_PROBE_SQL':$sql;
        if ($failedProbe) { add_filter('query',$filter); }
        $denied=false;
        try { PeraCRM_Campaign_Submission_Response::record($deployment,$seed['submission'],'caller'); }
        catch (RuntimeException $e) { $denied=true; }
        finally { if ($failedProbe) { remove_filter('query',$filter); } }
        sns_expect($denied && $wpdb->query('SAVEPOINT response_caller')!==false && $wpdb->query('RELEASE SAVEPOINT response_caller')!==false,'response preserves caller transaction');
        $wpdb->query('ROLLBACK');
    }
});
