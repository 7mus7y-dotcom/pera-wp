<?php
require_once __DIR__.'/ses-suppression-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/event-processing.php';
function processing_service(array $v): PeraCRM_SES_Processing { return new PeraCRM_SES_Processing(sns_verifier($v),$v['deployment'],get_current_blog_id(),ses_event_scope()); }
function processing_scope(array $v): string { return hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$v['deployment'],'blog'=>get_current_blog_id()]+$v['scope'],JSON_THROW_ON_ERROR)); }
function processing_receive(array $v,array $event,int $n): string {
    $id=ses_event_id($n); (new PeraCRM_SNS_Receipts(sns_verifier($v),$v['deployment'],get_current_blog_id()))->receive(ses_event_body($v,$event,$id),$v['now']); return $id;
}
peracrm_with_target_blog(static function () {
    global $wpdb;
    $v=sns_values(); $v['deployment']='processing-'.bin2hex(random_bytes(8)); $service=processing_service($v);
    $p=peracrm_table('peracrm_campaign_ses_progress');
    for ($i=0;$i<3;$i++) { $seed=ses_match_seed($v['deployment'],1900+$i); processing_receive($v,suppress_event($seed,'Bounce'),1900+$i); }
    $fences=[];
    foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_campaign_deliveries','peracrm_campaign_queue_jobs','peracrm_campaign_worker_events','peracrm_campaign_submission_attempts'] as $table) { $fences[$table]=suppress_rows($table); }
    $before=suppress_atomic(); $writes=[];
    $filter=static function ($sql) use (&$writes) { if (!str_starts_with(ltrim($sql),'SELECT') && !str_starts_with($sql,'SHOW')) { $writes[]=$sql; } return $sql; };
    add_filter('query',$filter); $h=$service->health(); remove_filter('query',$filter);
    sns_expect(!$writes && suppress_atomic()===$before && $h['pending_processing']===3 && $h['last_successful_redrive']===null,'health SELECT-only no metadata writes');
    foreach ([0,26] as $limit) { $denied=false; try { $service->redrive($limit); } catch (InvalidArgumentException $e) { $denied=true; } sns_expect($denied,'strict batch bound'); }
    sns_expect($service->redrive(2)['processed']===2 && $service->health()['pending_processing']===1,'batch limit exactly enforced');
    sns_expect($service->redrive(2)['processed']===1 && $service->redrive(2)['processed']===0,'durable terminal progress skipped');
    foreach ($fences as $table=>$rows) { sns_expect(suppress_rows($table)===$rows,'redrive preserves sending/consent/cancellation/unknown fences '.$table); }
    $h=$service->health(); sns_expect($h['last_successful_processing']!==null && $h['last_successful_redrive']!==null && $h['provider_health']==='not_observed' && !$h['delivery_verified'],'local progress without provider/delivery claims');
    // Binding can arrive after normalization and the first explicit pass.
    $seed=ses_match_seed($v['deployment'],1910,false); $id=processing_receive($v,suppress_event($seed,'Bounce'),1910);
    sns_expect($service->redrive(1)['waiting']===1,'missing binding remains retryable');
    ses_match_persist($v['deployment'],$seed['binding'],$seed['submission'],$seed['output']);
    sns_expect($service->redrive(1)['completed']===1 && $service->health()['unmatched_events']===0,'later immutable binding recovers');
    // Permanent mismatch never gains retry authority even after repeated runs.
    $seed=ses_match_seed($v['deployment'],1920); $event=suppress_event($seed,'Bounce'); $event['mail']['tags']['peracrm_snapshot']=[str_repeat('a',64)];
    processing_receive($v,$event,1920); sns_expect($service->redrive(1)['conflict']===1,'permanent binding conflict held');
    $stable=suppress_atomic(); sns_expect($service->redrive(25)['processed']===0 && suppress_atomic()===$stable,'permanent conflict never retried');
    $missing=ses_match_seed($v['deployment'],1921,false); processing_receive($v,suppress_event($missing,'Bounce'),1921);
    for ($pass=0;$pass<5;$pass++) { $service->redrive(1); }
    sns_expect($service->health()['manual_review_receipts']===2 && $service->redrive(1)['processed']===0,'missing bindings exhaust existing five-pass fence');
    $old=$v; $old['now']-=90000; (new PeraCRM_SNS_Receipts(sns_verifier($v),$v['deployment'],get_current_blog_id()))->receive(ses_event_body($old,suppress_event($seed,'Bounce'),ses_event_id(1922)),$v['now']);
    processing_receive($v,['eventType'=>'FutureUnsupported'],1923);
    $service->redrive(25);
    sns_expect($service->health()['quarantined_receipts']===2,'freshness and unsupported quarantine separated from failures');
    // Fail writes/COMMIT and replace actual sessions in normalization/application/progress.
    foreach (['normalization','application','commit','progress'] as $n=>$point) {
        foreach ([false,true] as $reconnect) {
            $number=1930+$n*2+(int)$reconnect;
            $seed=ses_match_seed($v['deployment'],$number); $id=processing_receive($v,suppress_event($seed,'Bounce'),$number);
            $fired=false; $application=false;
            $filter=static function ($sql) use ($point,$reconnect,&$fired,&$application,$wpdb) {
                if (str_starts_with($sql,'INSERT INTO') && str_contains($sql,'ses_applications ')) { $application=true; }
                $target=match ($point) {
                    'normalization'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'ses_normalizations '),
                    'application'=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'ses_applications '),
                    'commit'=>$application && $sql==='COMMIT',
                    'progress'=>str_starts_with($sql,'UPDATE ') && str_contains($sql,"SET state='completed'"),
                };
                if (!$fired && $target) { $fired=true; if ($reconnect) { $wpdb->close(); $wpdb->db_connect(); } else { return 'INVALID_PROCESSING_FIXTURE_SQL'; } }
                return $sql;
            };
            add_filter('query',$filter); $result=$service->redrive(1); remove_filter('query',$filter);
            sns_expect($fired && $result['result']==='blocked','downstream/progress interruption surfaced '.$point.' '.(int)$reconnect);
            sns_expect($service->health()['pending_processing']+$service->health()['failed_processing']===1,'interrupted progress remains actionable');
            sns_expect($service->redrive(1)['completed']===1,'rollback/reconnection resumes '.$point.' '.(int)$reconnect);
            $row=peracrm_campaigns_repository_identity($seed['binding']['mailbox']);
            sns_expect((int)$row['hard_bounce']===1,'resumed suppression persisted');
            sns_expect((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_email_suppression_events').' WHERE normalized_email=%s',$seed['binding']['mailbox']))===1,'resume retains one logical suppression audit');
        }
    }
    // Unrecognized/manual progress states fail closed on HTTP-style replay too.
    $manual=ses_event_id(1900);
    $wpdb->query($wpdb->prepare("UPDATE {$p} SET state='manual_review' WHERE scope_hash=%s AND receipt_id=%s",processing_scope($v),$manual));
    $before=suppress_atomic(); sns_reject(fn()=>$service->receipt($manual),'sns_storage');
    sns_expect(suppress_atomic()===$before && $service->redrive(1)['processed']===0,'manual/unknown progress cannot be automatically released');
    sns_expect($wpdb->get_var($wpdb->prepare("SELECT state FROM {$p} WHERE scope_hash=%s AND receipt_id=%s",processing_scope($v),$manual))==='manual_review','manual hold preserved');
    // A caller transaction must never be implicitly committed by progress writes.
    $wpdb->query('START TRANSACTION'); sns_reject(fn()=>$service->redrive(1),'sns_storage');
    sns_expect($wpdb->query('SAVEPOINT processing_caller')!==false && $wpdb->query('RELEASE SAVEPOINT processing_caller')!==false,'outer transaction retained'); $wpdb->query('ROLLBACK');
    if (is_multisite()) { switch_to_blog(1); try { sns_reject(fn()=>$service->health(),'sns_storage'); sns_reject(fn()=>$service->redrive(1),'sns_storage'); } finally { restore_current_blog(); } }
    $wrong=ses_event_scope(); $wrong['configuration_version']=2;
    $wrongService=new PeraCRM_SES_Processing(sns_verifier($v),$v['deployment'],get_current_blog_id(),$wrong);
    sns_expect($wrongService->health()['policy_conflicts']>0,'read-only health exposes mismatched policy pins');
    $text=json_encode($service->health()); sns_expect(!str_contains($text,'@') && !str_contains($text,'https:') && !str_contains($text,'canonical_payload'),'payload-free inspection');
    sns_expect(!peracrm_campaign_queue_dispatch_allowed(),'production sending remains disabled');
});
