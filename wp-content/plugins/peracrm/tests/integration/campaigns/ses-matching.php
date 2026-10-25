<?php
require_once __DIR__.'/ses-matching-fixture.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    $v=sns_values(); $deployment='match-'.bin2hex(random_bytes(8)); $blog=get_current_blog_id();
    $store=new PeraCRM_SES_Matcher(sns_verifier($v),$deployment,$blog,ses_event_scope());
    $messages=peracrm_table('peracrm_campaign_provider_messages'); $matches=peracrm_table('peracrm_campaign_ses_matches');
    $rows=static fn($table)=>$wpdb->get_results("SELECT * FROM {$table} ORDER BY 1,2,3",ARRAY_A);
    $wpdb->query('DROP TABLE '.$messages); $wpdb->query('DROP TABLE '.$matches);
    update_option('peracrm_schema_version',32); update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>32]);
    sns_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema(),'v32 correlation migration idempotent');
    sns_reject(fn()=>$store->match(ses_event_id(9999)),'sns_storage');
    $seed=ses_match_seed($deployment,1); $id=ses_match_receive($v,$deployment,$seed['event'],301);
    ses_match_check(fn()=>$store->match($id),'matched');
    sns_expect(count($rows($messages))===1 && $rows($messages)[0]['binding_hash']===hash('sha256',json_encode($seed['metadata'],JSON_THROW_ON_ERROR)), 'early event associates immutable attempt without response or worker mutation');
    sns_expect($seed['metadata']['version']===2 && $seed['metadata']['personalization_version']===2,'V2 events retain durable immutable matching');
    $original=[$rows($messages),$rows($matches)];
    ses_match_check(fn()=>$store->match($id),'matched');
    $duplicate=ses_match_receive($v,$deployment,$seed['event'],302);
    ses_match_check(fn()=>$store->match($duplicate),'matched');
    sns_expect([$rows($messages),$rows($matches)]===$original,'identical and logical replay preserve first provenance');
    $conflict=$seed['event']; $conflict['mail']['messageId']='alternative-message';
    $conflictId=ses_match_receive($v,$deployment,$conflict,303);
    ses_match_check(fn()=>$store->match($conflictId),'message_conflict');
    sns_expect($rows($messages)===$original[0],'conflicting message cannot replace association');
    $second=ses_match_seed($deployment,2); $second['event']['mail']['messageId']=$seed['event']['mail']['messageId'];
    $secondId=ses_match_receive($v,$deployment,$second['event'],304);
    ses_match_check(fn()=>$store->match($secondId),'message_conflict');
    sns_expect($rows($messages)===$original[0],'same provider message cannot bind different attempt');
    $config='fixture-other'; $alternate=ses_event_scope(); $alternate['configuration_set']=&$config;
    $alternateSeed=ses_match_seed($deployment,40,true,$alternate);
    $alternateSeed['event']['mail']['messageId']=$seed['event']['mail']['messageId'];
    $alternateId=ses_match_receive($v,$deployment,$alternateSeed['event'],307,$alternate);
    $alternateStore=new PeraCRM_SES_Matcher(sns_verifier($v),$deployment,$blog,$alternate);
    $config='caller-mutated';
    ses_match_check(fn()=>$alternateStore->match($alternateId),'message_conflict');
    sns_expect($rows($messages)===$original[0],'message uniqueness spans config sets and configuration copy is immutable');
    $unnormalized=ses_event_id(308);
    (new PeraCRM_SNS_Receipts(sns_verifier($v),$deployment,$blog))->receive(ses_event_body($v,$seed['event'],$unnormalized),$v['now']);
    sns_reject(fn()=>$store->match($unnormalized),'sns_storage');
    sns_reject(fn()=>$store->match(ses_event_json($seed['event'])),'sns_invalid');
    $late=ses_match_seed($deployment,3,false); $lateId=ses_match_receive($v,$deployment,$late['event'],305);
    ses_match_check(fn()=>$store->match($lateId),'missing_binding'); $original=$rows($matches);
    ses_match_check(fn()=>$store->match($lateId),'missing_binding');
    sns_expect($rows($matches)===$original,'missing replay is idempotent without explicit redrive');
    ses_match_persist($deployment,$late['binding'],$late['submission'],$late['output']);
    ses_match_check(fn()=>$store->reprocess($lateId),'matched');
    $missing=ses_match_seed($deployment,4,false); $missingId=ses_match_receive($v,$deployment,$missing['event'],306);
    for ($i=0;$i<8;$i++) { ses_match_check(fn()=>$store->reprocess($missingId),'missing_binding'); }
    $scope=hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$deployment,'blog'=>$blog]+$v['scope'],JSON_THROW_ON_ERROR));
    $keys=$wpdb->get_col($wpdb->prepare("SELECT sequence FROM {$matches} WHERE scope_hash=%s AND decision='missing_binding' AND receipt_id=%s ORDER BY sequence",$scope,$missingId));
    sns_expect(array_map('intval',$keys)===[1,2,3,4,5],'bounded redrive cannot create unbounded history');
    foreach (['delivery','snapshot','envelope','recipient','destinations'] as $number=>$wrong) {
        $event=$seed['event']; $event['mail']['messageId']='wrong-'.$wrong;
        if ($wrong==='recipient') { $event['mail']['destination']=['other@example.test']; }
        elseif ($wrong==='destinations') { $event['mail']['destination'][]='other@example.test'; $event['eventType']='Delivery'; unset($event['send']); $event['delivery']=['timestamp'=>'2026-10-24T12:00:01Z','recipients'=>[$seed['binding']['mailbox']]]; }
        else { $event['mail']['tags']['peracrm_'.$wrong]=[str_repeat('f',64)]; }
        $wrongId=ses_match_receive($v,$deployment,$event,310+$number);
        ses_match_check(fn()=>$store->match($wrongId),in_array($wrong,['recipient','destinations'],true)?'wrong_recipient':'binding_conflict');
    }
    $legacy=ses_match_seed($deployment,91,true,[],1);
    $legacyId=ses_match_receive($v,$deployment,$legacy['event'],391);
    ses_match_check(fn()=>$store->match($legacyId),'matched');
    sns_expect($legacy['metadata']['version']===1,'historical V1 event matching remains supported');
    $other=new PeraCRM_SES_Matcher(sns_verifier($v),$deployment.'other',$blog,ses_event_scope());
    sns_reject(fn()=>$other->match($id),'sns_storage');
    $changed=ses_event_scope(); $changed['configuration_version']=2;
    $other=new PeraCRM_SES_Matcher(sns_verifier($v),$deployment,$blog,$changed);
    sns_reject(fn()=>$other->match($id),'sns_storage');
    if (is_multisite()) { switch_to_blog(1); try { sns_reject(fn()=>$store->match($id),'sns_storage'); } finally { restore_current_blog(); } }
    // Prove both unique indexes are asserted, not merely present on fresh installs.
    $wpdb->query("ALTER TABLE {$messages} DROP INDEX immutable_attempt");
    $failed=false; try { peracrm_campaigns_assert_schema(); } catch (Throwable $e) { $failed=true; }
    sns_expect($failed,'schema rejects missing attempt uniqueness'); peracrm_campaigns_install_schema();
    foreach (['message_insert','decision_insert','select','commit','reconnect_message','reconnect_decision','reconnect_commit','reconnect_capture'] as $number=>$failure) {
        $seed=ses_match_seed($deployment,20+$number); $failureId=ses_match_receive($v,$deployment,$seed['event'],330+$number);
        $original=[$rows($messages),$rows($matches)]; $before=ses_match_snapshot(); $fired=false;
        $filter=static function ($sql) use ($failure,&$fired,$wpdb) {
            $message=str_starts_with($sql,'INSERT INTO') && str_contains($sql,'campaign_provider_messages');
            $decision=str_starts_with($sql,'INSERT INTO') && str_contains($sql,'campaign_ses_matches');
            if (!$fired && (($failure==='reconnect_message' && $message) || ($failure==='reconnect_decision' && $decision)
                || ($failure==='reconnect_commit' && $sql==='COMMIT') || ($failure==='reconnect_capture' && $sql==='SELECT CONNECTION_ID()'))) {
                $fired=true; $wpdb->close(); $wpdb->db_connect();
            }
            if (($failure==='message_insert' && $message) || ($failure==='decision_insert' && $decision)
                || ($failure==='select' && str_starts_with($sql,'SELECT * FROM') && str_contains($sql,'submission_attempts'))
                || ($failure==='commit' && $sql==='COMMIT')) { return 'INVALID_MATCH_FIXTURE_SQL'; }
            return $sql;
        };
        add_filter('query',$filter); try { sns_reject(fn()=>$store->match($failureId),'sns_storage'); } finally { remove_filter('query',$filter); }
        sns_expect([$rows($messages),$rows($matches)]===$original && $before===ses_match_snapshot(),'atomic association/decision rollback and session fence '.$failure);
        ses_match_check(fn()=>$store->match($failureId),'matched');
    }
    foreach ([false,true] as $failedProbe) {
        $wpdb->query('START TRANSACTION');
        $filter=static fn($sql)=>$sql==='RELEASE SAVEPOINT peracrm_match_top_level_probe'?'INVALID_MATCH_PROBE_SQL':$sql;
        if ($failedProbe) { add_filter('query',$filter); }
        try { sns_reject(fn()=>$store->match($id),'sns_storage'); } finally { if ($failedProbe) { remove_filter('query',$filter); } }
        sns_expect($wpdb->query('SAVEPOINT matching_caller')!==false && $wpdb->query('RELEASE SAVEPOINT matching_caller')!==false,'caller transaction preserved including failed probe');
        $wpdb->query('ROLLBACK');
    }
});
