<?php
/** Focused service rollback/epoch regressions; actual locking is tested in SQL. */
require __DIR__.'/campaign-foundations-test.php';
$mailbox = 'lifecycle@example.test';
$id = peracrm_campaigns_save_subscriber($mailbox);
campaign_expect(is_int($id), 'lifecycle subscriber created');
$table = 'wp_2_peracrm_campaign_recipient_identities';
foreach (['current','retained'] as $index=>$deployment) {
    $wpdb->tables[$table][$index] = ['deployment'=>$deployment,'blog_id'=>2,'subscriber_id'=>$id,
        'mailbox'=>$mailbox,'token_epoch'=>7,'active'=>1];
}
$before = $wpdb->tables[$table];
peracrm_campaigns_save_subscriber($mailbox, ['preferences'=>['news'],'preferred_language'=>'tr']);
peracrm_campaigns_record_consent($mailbox,'pending',['source'=>'fixture']);
campaign_expect($wpdb->tables[$table] === $before, 'profile topics and consent retain withdrawal epochs');
$wpdb->fail_insert = 'peracrm_subscription_events';
campaign_expect(is_wp_error(peracrm_campaigns_delete_subscriber($mailbox))
    && campaign_identity($mailbox) && $wpdb->tables[$table] === $before, 'failed audit rolls back subscriber and identity');
$wpdb->fail_insert = '';
campaign_expect(peracrm_campaigns_delete_subscriber($mailbox) === true, 'lifecycle deletion succeeds');
foreach ($wpdb->tables[$table] as $row) {
    campaign_expect($row['token_epoch'] === 8 && $row['active'] === 0, 'deletion revokes every retained deployment');
}
peracrm_with_target_blog(static function () use ($id,$mailbox) {
    peracrm_campaigns_repository_mutate($mailbox, static function () use ($id,$mailbox) {
        peracrm_campaigns_repository_replace_identity($id,$mailbox,true);
    });
});
foreach ($wpdb->tables[$table] as $row) {
    campaign_expect($row['token_epoch'] === 9 && $row['active'] === 1, 'reused ID advances epoch instead of restoring access');
}
peracrm_with_target_blog(static function () {
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    $result = peracrm_campaigns_save_subscriber('nested-lifecycle@example.test');
    if (!is_wp_error($result)) { throw new RuntimeException('Nested mutation accepted.'); }
    $wpdb->query('ROLLBACK');
});
campaign_expect(!campaign_identity('nested-lifecycle@example.test'), 'nested service does not commit caller transaction');
