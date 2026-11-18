<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once __DIR__.'/ses-suppression-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/retention.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    $wpdb->query('SET timestamp=0');
    $v=sns_values(); $v['deployment']='retention-'.bin2hex(random_bytes(8));
    $processing=new PeraCRM_SES_Processing(sns_verifier($v),$v['deployment'],get_current_blog_id(),ses_event_scope());
    $binding=$processing->retentionBinding(); $retention=new PeraCRM_Campaign_Retention($binding);
    $r=peracrm_table('peracrm_campaign_sns_receipts'); $p=peracrm_table('peracrm_campaign_ses_progress');
    $b=peracrm_table('peracrm_subscriber_batches'); $ids=[]; $bodies=[];
    foreach (['eligible','fresh','waiting','conflict','recent_activity','corrupt'] as $i=>$kind) {
        $seed=ses_match_seed($v['deployment'],6500+$i);
        $id=ses_event_id(6500+$i); $body=ses_event_body($v,suppress_event($seed,'Bounce'),$id);
        (new PeraCRM_SNS_Receipts(sns_verifier($v),$v['deployment'],get_current_blog_id()))->receive($body,$v['now']);
        $processing->receipt($id); $ids[$kind]=$id; $bodies[$kind]=$body;
        if ($kind!=='fresh') {
            $wpdb->query($wpdb->prepare("UPDATE {$r} SET received_at=UTC_TIMESTAMP()-INTERVAL 91 DAY WHERE scope_hash=%s AND message_id=%s",$binding['scope'],$id));
            $wpdb->query($wpdb->prepare("UPDATE {$p} SET last_attempt_at=UTC_TIMESTAMP()-INTERVAL 91 DAY,last_redrive_at=NULL WHERE scope_hash=%s AND receipt_id=%s",$binding['scope'],$id));
        }
        if (in_array($kind,['waiting','conflict'],true)) {
            $wpdb->update($p,['state'=>$kind],['scope_hash'=>$binding['scope'],'receipt_id'=>$id]);
        }
        if ($kind==='recent_activity') { $wpdb->update($p,['last_redrive_at'=>gmdate('Y-m-d H:i:s')],['scope_hash'=>$binding['scope'],'receipt_id'=>$id]); }
        if ($kind==='corrupt') { $wpdb->update($r,['canonical_payload'=>'corrupted'],['scope_hash'=>$binding['scope'],'message_id'=>$id]); }
    }
    $batch='retention-'.bin2hex(random_bytes(12));
    $wpdb->insert($b,['id'=>$batch,'owner_id'=>get_current_user_id(),'status'=>'complete','synthetic'=>1,'suppression_complete'=>1,
        'total'=>1,'summary_json'=>'[{"status":"done","total":1}]','created_at'=>'2020-01-01 00:00:00','expires_at'=>'2020-01-02 00:00:00']);
    $all=static function () use ($r,$b,$wpdb) { return wp_json_encode([$wpdb->get_results("SELECT * FROM {$r} ORDER BY scope_hash,message_id",ARRAY_A),$wpdb->get_results("SELECT * FROM {$b} ORDER BY id",ARRAY_A)]); };
    $before=$all();
    $rejected=false; try { $retention->run(); } catch (RuntimeException $e) { $rejected=$e->getMessage()==='retention_integrity'; }
    sns_expect($rejected && $all()===$before,'retention corrupt diagnostics fail closed with zero writes');
    $wpdb->update($p,['state'=>'conflict'],['scope_hash'=>$binding['scope'],'receipt_id'=>$ids['corrupt']]);
    $before=$all(); $writes=[];
    $filter=static function ($sql) use (&$writes) { if (!str_starts_with(ltrim($sql),'SELECT') && !str_starts_with($sql,'SHOW')) { $writes[]=$sql; } return $sql; };
    add_filter('query',$filter); $preview=$retention->run(); remove_filter('query',$filter);
    sns_expect($preview['dry_run'] && $preview['sns_bodies']===1 && !$writes && $all()===$before,'retention preview SELECT-only; unresolved and recent work held');
    $fences=[];
    foreach (['peracrm_email_suppressions','peracrm_email_suppression_events','peracrm_subscription_events',
        'peracrm_campaign_deliveries','peracrm_campaign_queue_jobs','peracrm_campaign_recipient_tokens',
        'peracrm_campaign_submission_attempts','peracrm_campaign_submission_outputs',
        'peracrm_campaign_ses_events','peracrm_campaign_ses_applications','peracrm_campaign_acceptance_evidence'] as $table) { $fences[$table]=suppress_rows($table); }
    $bad=false;
    $filter=static function ($sql) use (&$bad) { if (str_starts_with($sql,'UPDATE ') && str_contains($sql,'subscriber_batches')) { $bad=true; return 'INVALID_RETENTION_FIXTURE_SQL'; } return $sql; };
    add_filter('query',$filter); $failed=false;
    try { $retention->run(true); } catch (RuntimeException $e) { $failed=true; }
    remove_filter('query',$filter);
    sns_expect($bad && $failed && $all()===$before,'retention summary failure rolls back body retirement');
    $wpdb->query('START TRANSACTION'); $failed=false;
    try { $retention->run(true); } catch (RuntimeException $e) { $failed=true; }
    sns_expect($failed && $wpdb->query('SAVEPOINT retention_outer')!==false && $wpdb->query('RELEASE SAVEPOINT retention_outer')!==false,'retention never commits caller transaction');
    $wpdb->query('ROLLBACK');
    $result=$retention->run(true);
    sns_expect(!$result['dry_run'] && $result['sns_bodies']===1,'bounded retention retires only eligible body');
    $retired=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$r} WHERE scope_hash=%s AND message_id=%s",$binding['scope'],$ids['eligible']),ARRAY_A);
    sns_expect($retired['canonical_payload']==='' && $retired['authentication_version']==='sns-v2-retired','body removed and digest tombstone retained');
    sns_expect($wpdb->get_var($wpdb->prepare("SELECT summary_json FROM {$b} WHERE id=%s",$batch))==='{}','expired standalone count summary cleared');
    foreach ($fences as $table=>$rows) { sns_expect(suppress_rows($table)===$rows,'retention preserves protected '.$table); }
    $before=$all(); $store=new PeraCRM_SNS_Receipts(sns_verifier($v),$v['deployment'],get_current_blog_id());
    sns_expect($store->receive($bodies['eligible'],$v['now']+90000)==='quarantined' && $all()===$before,'authentic duplicate never rehydrates retired body');
    sns_reject(fn()=>$store->receive(sns_body($v,['MessageId'=>$ids['eligible'],'Message'=>'different signed body']),$v['now']),'sns_conflict');
    $normalizer=new PeraCRM_SES_Quarantine(sns_verifier($v),$v['deployment'],get_current_blog_id(),ses_event_scope());
    sns_expect($normalizer->normalize($ids['eligible'])==='matching_pending','retired body reuses immutable normalization');
    sns_expect(array_values(suppress_service($v)->apply($ids['eligible']))===['applied'],'historical suppression remains verifiable after retirement');
    sns_expect($retention->run(true)['sns_bodies']===0 && !$retention->run(true)['import_count_summaries'],'retention idempotent');
    wp_set_current_user(0); $denied=false;
    try { $retention->run(); } catch (InvalidArgumentException $e) { $denied=true; }
    sns_expect($denied,'anonymous retention denied');
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    sns_expect(!peracrm_campaign_queue_dispatch_allowed(),'retention never enables dispatch');
});
echo "PASS disposable retention retirement, replay, rollback and protected evidence\n";
