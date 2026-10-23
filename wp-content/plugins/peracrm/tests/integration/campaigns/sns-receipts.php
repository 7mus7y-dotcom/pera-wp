<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/sns-fixture.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    $blog=get_current_blog_id(); $v=sns_values(); $body=sns_body($v);
    // Simulate upgrade from v30 without receipt table; no existing data is deleted.
    $table=peracrm_table('peracrm_campaign_sns_receipts');
    $wpdb->query('DROP TABLE '.$table); update_option('peracrm_schema_version',30);
    update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>30]);
    sns_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema(),'v30 additive migration idempotent');
    $store=new PeraCRM_SNS_Receipts(sns_verifier($v),'fixture-sns',$blog);
    $snapshot=static function () use ($wpdb) {
        $out=[];
        foreach (['peracrm_subscribers','peracrm_email_suppressions','peracrm_campaign_deliveries','peracrm_campaign_submission_attempts'] as $name) {
            $out[$name]=$wpdb->get_results('SELECT * FROM '.peracrm_table($name),ARRAY_A);
        }
        return $out;
    };
    $before=$snapshot();
    $count=static fn()=>(int)$wpdb->get_var('SELECT COUNT(*) FROM '.$table);
    sns_expect($store->receive($body,$v['now'])==='quarantined' && $count()===1,'authenticated receipt durable before return');
    $original=$wpdb->get_results('SELECT * FROM '.$table,ARRAY_A);
    sns_expect($store->receive($body,$v['now']+90000)==='quarantined' && $wpdb->get_results('SELECT * FROM '.$table,ARRAY_A)===$original,'old duplicate retains original tombstone');
    sns_reject(fn()=>$store->receive(sns_body($v,['Message'=>'conflict']),$v['now']),'sns_conflict');
    sns_expect($wpdb->get_results('SELECT * FROM '.$table,ARRAY_A)===$original,'conflict never overwrites original');
    $bad=json_decode($body,true); $bad['Message']='forged';
    sns_reject(fn()=>$store->receive(json_encode($bad),$v['now']),'sns_authentication');
    sns_reject(fn()=>$store->receive(sns_body($v,['TopicArn'=>'arn:aws:sns:eu-west-1:999999999999:fixture']),$v['now']),'sns_invalid');
    foreach (['insert','commit'] as $failure) {
        $filter=static function ($sql) use ($failure) {
            return (($failure==='insert' && str_starts_with($sql,'INSERT INTO') && str_contains($sql,'sns_receipts')) || ($failure==='commit' && $sql==='COMMIT'))?'INVALID_SNS_FIXTURE_SQL':$sql;
        };
        add_filter('query',$filter);
        try { sns_reject(fn()=>$store->receive(sns_body($v,['MessageId'=>'22222222-1234-1234-1234-123456789abc']),$v['now']),'sns_storage'); }
        finally { remove_filter('query',$filter); }
        sns_expect($count()===1,'rollback before ack '.$failure);
    }
    $wpdb->query('START TRANSACTION');
    sns_reject(fn()=>$store->receive($body,$v['now']),'sns_storage');
    sns_expect($wpdb->query('SAVEPOINT caller_still_owned')!==false && $wpdb->query('RELEASE SAVEPOINT caller_still_owned')!==false,'caller transaction preserved');
    $wpdb->query('ROLLBACK');
    foreach ([-90000,600] as $offset) {
        $changed=$v; $changed['now']+=$offset;
        $store->receive(sns_body($changed,['MessageId'=>($offset<0?'33333333':'44444444').'-1234-1234-1234-123456789abc']),$v['now']);
    }
    $reasons=$wpdb->get_col('SELECT quarantine_reason FROM '.$table.' ORDER BY message_id');
    sns_expect($reasons===['application_pending','stale_timestamp','future_timestamp'],'out of order and early timestamps quarantined');
    sns_expect($snapshot()===$before,'quarantine creates no subscriber and grants no state or suppression authority');
    // Separate deployment shares provider ID without crossing local scope.
    $other=new PeraCRM_SNS_Receipts(sns_verifier($v),'fixture-sns-other',$blog);
    $other->receive($body,$v['now']); sns_expect($count()===4,'deployment receipt scope isolated');
    if (is_multisite()) {
        switch_to_blog(1);
        try { sns_reject(fn()=>$store->receive($body,$v['now']),'sns_storage'); }
        finally { restore_current_blog(); }
    }
});
