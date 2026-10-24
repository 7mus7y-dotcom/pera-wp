<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/ses-events-fixture.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    $v=sns_values(); $blog=get_current_blog_id(); $deployment='ses-normalization-'.bin2hex(random_bytes(8));
    $verifier=sns_verifier($v);
    $intake=new PeraCRM_SNS_Receipts($verifier,$deployment,$blog);
    $store=new PeraCRM_SES_Quarantine($verifier,$deployment,$blog,ses_event_scope());
    $events=peracrm_table('peracrm_campaign_ses_events');
    $links=peracrm_table('peracrm_campaign_ses_normalizations');
    $receipts=peracrm_table('peracrm_campaign_sns_receipts');
    $scope=hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$deployment,'blog'=>$blog]+$v['scope'],JSON_THROW_ON_ERROR));
    // Real v31 additive migration, retaining existing receipts/other plugin data.
    $wpdb->query('DROP TABLE '.$events); $wpdb->query('DROP TABLE '.$links);
    update_option('peracrm_schema_version',31);
    update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>31]);
    sns_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema(),'v31 normalization migration idempotent');
    $snapshot=static function () use ($wpdb) {
        $out=[];
        foreach (['peracrm_subscribers','peracrm_email_suppressions','peracrm_email_suppression_events',
            'peracrm_subscription_events','peracrm_campaign_deliveries','peracrm_campaign_queue_jobs',
            'peracrm_campaign_submission_attempts','peracrm_campaign_submission_outputs','peracrm_campaign_worker_events'] as $table) {
            $out[$table]=$wpdb->get_results('SELECT * FROM '.peracrm_table($table),ARRAY_A);
        }
        return $out;
    };
    $before=$snapshot();
    $rows=static fn($table)=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE scope_hash=%s ORDER BY 1,2",$scope),ARRAY_A);
    $receive=static function (array $event,int $number,array $extra=[]) use ($v,$intake) {
        $id=ses_event_id($number);
        $intake->receive(sns_body($v,$extra+['MessageId'=>$id,'Message'=>ses_event_json($event)]),$v['now']);
        return $id;
    };
    sns_reject(fn()=>$store->normalize(ses_event_id(999)),'sns_storage');
    $body=ses_event_body($v,ses_event(),ses_event_id(1));
    $forged=json_decode($body,true); $forged['Message']='forged';
    sns_reject(fn()=>$intake->receive(json_encode($forged),$v['now']),'sns_authentication');
    sns_expect(!$rows($events) && !$rows($links),'no raw or unauthenticated normalization path');
    $id=$receive(ses_event(),1,['Subject'=>"A\nMessageId\nsynthetic"]);
    sns_expect($store->normalize($id)==='matching_pending' && count($rows($events))===2,'durable authenticated recipient facts');
    $original=[$rows($events),$rows($links),$rows($receipts)];
    $intake->receive(sns_body($v,['MessageId'=>$id,'Message'=>ses_event_json(ses_event()),'Subject'=>"A\nMessageId\nsynthetic"]),$v['now']);
    sns_expect($store->normalize($id)==='matching_pending' && [$rows($events),$rows($links),$rows($receipts)]===$original,'same SNS receipt immutable replay');
    $e=ses_event(); $e['extra']=['new'=>true]; $e['mail']['destination']=array_reverse($e['mail']['destination']);
    sns_expect($store->normalize($receive($e,2))==='matching_pending' && count($rows($events))===2 && count($rows($links))===2,'logical dedupe across SNS IDs with durable references');
    $e=ses_event('Bounce'); $bounce=$receive($e,3);
    sns_expect($store->normalize($bounce)==='matching_pending','bounce facts remain quarantined');
    $originalFacts=$rows($events); $e['bounce']['bounceType']='Transient';
    sns_expect($store->normalize($receive($e,4))==='event_conflict' && $rows($events)===$originalFacts,'feedback conflict preserves original entire batch');
    foreach (['Delivery','Complaint','DeliveryDelay'] as $number=>$type) {
        $e=ses_event($type);
        if ($type==='Complaint') { $e['complaint']['complaintFeedbackType']='not-spam'; }
        sns_expect($store->normalize($receive($e,10+$number))==='matching_pending','out-of-order '.$type.' fact only');
    }
    foreach (['wrong_scope','wrong_recipient','unsupported_event','invalid_tags','invalid_event'] as $number=>$reason) {
        $e=ses_event('Delivery');
        if ($reason==='wrong_scope') { $e['mail']['sendingAccountId']='999999999999'; }
        if ($reason==='wrong_recipient') { $e['delivery']['recipients']=['stranger@example.test']; }
        if ($reason==='unsupported_event') { $e['eventType']='Subscription'; }
        if ($reason==='invalid_tags') { $e['mail']['tags']['peracrm_attempt']=['bad']; }
        if ($reason==='invalid_event') { $e['mail']['timestamp']='2026-02-30T00:00:00Z'; }
        $count=count($rows($events));
        sns_expect($store->normalize($receive($e,20+$number))===$reason && count($rows($events))===$count,'quarantine '.$reason);
    }
    foreach ([-90000,600] as $number=>$offset) {
        $timestamp=gmdate('Y-m-d\TH:i:s\Z',$v['now']+$offset);
        $count=count($rows($events));
        sns_expect($store->normalize($receive(ses_event(),30+$number,['Timestamp'=>$timestamp]))===($offset<0?'stale_timestamp':'future_timestamp')
            && count($rows($events))===$count,'freshness quarantine never automatically redriven');
    }
    // Fail at every stage, including real replacement sessions before writes and
    // COMMIT. Existing durable receipt remains; neither fact nor link can escape.
    foreach (['event_insert','link_insert','select','commit','reconnect_event','reconnect_link','reconnect_commit','reconnect_capture'] as $number=>$failure) {
        $e=ses_event(); $e['mail']['messageId']='failure-'.$failure;
        $id=$receive($e,40+$number); $originalFacts=$rows($events); $originalLinks=$rows($links); $fired=false;
        $filter=static function ($sql) use ($failure,&$fired,$wpdb) {
            $eventWrite=str_starts_with($sql,'INSERT INTO') && str_contains($sql,'campaign_ses_events');
            $linkWrite=str_starts_with($sql,'INSERT INTO') && str_contains($sql,'campaign_ses_normalizations');
            $capture=$sql==='SELECT CONNECTION_ID()';
            if (!$fired && (($failure==='reconnect_event' && $eventWrite) || ($failure==='reconnect_link' && $linkWrite)
                || ($failure==='reconnect_commit' && $sql==='COMMIT') || ($failure==='reconnect_capture' && $capture))) {
                $fired=true; $wpdb->close(); $wpdb->db_connect();
            }
            if (($failure==='event_insert' && $eventWrite) || ($failure==='link_insert' && $linkWrite)
                || ($failure==='select' && str_starts_with($sql,'SELECT * FROM') && str_contains($sql,'sns_receipts'))
                || ($failure==='commit' && $sql==='COMMIT')) { return 'INVALID_SES_NORMALIZATION_FIXTURE_SQL'; }
            return $sql;
        };
        add_filter('query',$filter);
        try { sns_reject(fn()=>$store->normalize($id),'sns_storage'); }
        finally { remove_filter('query',$filter); }
        sns_expect($rows($events)===$originalFacts && $rows($links)===$originalLinks,'atomic rollback and SQL-session fence '.$failure);
        sns_expect($store->normalize($id)==='matching_pending','receipt remains retryable after '.$failure);
    }
    $wpdb->query('START TRANSACTION');
    sns_reject(fn()=>$store->normalize(ses_event_id(1)),'sns_storage');
    sns_expect($wpdb->query('SAVEPOINT ses_caller_owned')!==false && $wpdb->query('RELEASE SAVEPOINT ses_caller_owned')!==false,'caller transaction preserved');
    $wpdb->query('ROLLBACK');
    $other=new PeraCRM_SES_Quarantine($verifier,$deployment.'-other',$blog,ses_event_scope());
    sns_reject(fn()=>$other->normalize(ses_event_id(1)),'sns_storage');
    $changed=ses_event_scope(); $changed['configuration_version']=2;
    $other=new PeraCRM_SES_Quarantine($verifier,$deployment,$blog,$changed);
    sns_reject(fn()=>$other->normalize(ses_event_id(1)),'sns_storage');
    if (is_multisite()) {
        switch_to_blog(1);
        try { sns_reject(fn()=>$store->normalize(ses_event_id(1)),'sns_storage'); }
        finally { restore_current_blog(); }
    }
    sns_expect($snapshot()===$before,'normalization never mutates consent suppression attempts outputs or any worker/job state');
});
