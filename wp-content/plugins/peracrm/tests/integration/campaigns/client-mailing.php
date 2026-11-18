<?php
/** Disposable real WordPress/MySQL mailing permission coverage. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) { throw new RuntimeException('Disposable fixture required.'); }
function mailing_expect($value, $label) { if (!$value) { WP_CLI::error('Client mailing: '.$label); } WP_CLI::log('PASS client mailing: '.$label); }
$mailing_original_user = get_current_user_id();
peracrm_with_target_blog(static function () {
    global $wpdb;
    $admin = get_user_by('login', 'fixture-admin'); wp_set_current_user($admin->ID);
    peracrm_subscribers_readiness(['enabled'=>true,'consent_mapping'=>'mailing-fixture','suppression_sources'=>'mailing-fixture','retention'=>'mailing-fixture']);
    $clients = [];
    foreach (['new','blocked','unknown','stale','browser'] as $key) {
        $id = wp_insert_post(['post_type'=>'crm_client','post_status'=>'private','post_title'=>'Synthetic mailing '.$key]);
        mailing_expect(is_int($id) && $id > 0, 'synthetic client created');
        update_post_meta($id, '_peracrm_email', 'client-mailing-'.$key.'@example.test');
        peracrm_party_upsert_status($id, ['disposition'=>'none']);
        $clients[$key] = $id;
    }
    $table = peracrm_table('peracrm_subscribers');
    $before = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    mailing_expect(peracrm_client_mailing_status($clients['new'])['status']==='missing' && (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}")===$before, 'status reads do not create subscribers');
    mailing_expect(is_wp_error(peracrm_client_mailing_confirm($clients['new'],'client-mailing-new@example.test',false,'fixture reply')), 'unchecked confirmation denied');
    $fail_event = static function ($sql) { return str_starts_with($sql,'INSERT INTO') && str_contains($sql,'peracrm_subscription_events') && str_contains($sql,'client-mailing-new@example.test') ? 'INVALID_MAILING_EVENT_SQL' : $sql; };
    add_filter('query',$fail_event);
    mailing_expect(is_wp_error(peracrm_client_mailing_confirm($clients['new'],'client-mailing-new@example.test',true,'fixture reply')), 'storage failure reported');
    remove_filter('query',$fail_event);
    mailing_expect(peracrm_client_mailing_status($clients['new'])['status']==='missing', 'storage failure rolled back subscriber and permission');
    mailing_expect(peracrm_client_mailing_confirm($clients['new'],'client-mailing-new@example.test',true,'fixture reply')==='saved', 'client permission saved');
    $saved = peracrm_campaigns_repository_identity('client-mailing-new@example.test');
    mailing_expect($saved['subscription_state']==='subscribed' && $saved['evidence_ref']==='fixture reply' && !$saved['client_id'], 'confirmation evidence and independent subscriber record');
    mailing_expect(peracrm_client_mailing_confirm($clients['new'],'client-mailing-new@example.test',true,'repeat')==='already_subscribed'
        && $saved===peracrm_campaigns_repository_identity('client-mailing-new@example.test'), 'repeat preserves existing profile and permission');
    $unknown = peracrm_campaigns_save_subscriber('client-mailing-unknown@example.test',['preferred_language'=>'de','preferences'=>['news']]);
    mailing_expect(is_int($unknown) && peracrm_client_mailing_status($clients['unknown'])['status']==='unconfirmed', 'existing unknown needs explicit confirmation');
    mailing_expect(peracrm_client_mailing_confirm($clients['unknown'],'client-mailing-unknown@example.test',true,'fixture reply')==='saved', 'existing unknown can be explicitly confirmed');
    $confirmed = peracrm_campaigns_repository_identity('client-mailing-unknown@example.test');
    mailing_expect((int)$confirmed['id']===$unknown && $confirmed['preferred_language']==='de' && json_decode($confirmed['preferences_json'],true)===['news'], 'confirmation preserves identity, explicit language and topics');
    peracrm_campaigns_add_suppression('client-mailing-blocked@example.test','complaint',['source'=>'fixture']);
    mailing_expect(peracrm_client_mailing_status($clients['blocked'])['status']==='suppressed'
        && is_wp_error(peracrm_client_mailing_confirm($clients['blocked'],'client-mailing-blocked@example.test',true,'fixture reply')), 'suppression without subscriber cannot be enrolled');
    peracrm_campaigns_withdraw('client-mailing-new@example.test',['source'=>'fixture']);
    mailing_expect(is_wp_error(peracrm_client_mailing_confirm($clients['new'],'client-mailing-new@example.test',true,'later reply')), 'withdrawal cannot be reversed');
    update_post_meta($clients['stale'],'_peracrm_email','client-mailing-changed@example.test');
    mailing_expect(is_wp_error(peracrm_client_mailing_confirm($clients['stale'],'client-mailing-stale@example.test',true,'fixture reply'))
        && !peracrm_campaigns_repository_identity('client-mailing-stale@example.test') && !peracrm_campaigns_repository_identity('client-mailing-changed@example.test'), 'stale form cannot subscribe old or changed email');
    $before = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $audit = peracrm_client_mailing_audit();
    mailing_expect($audit['read_only'] && $audit['client_records']===array_sum($audit['counts']) && (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}")===$before && !str_contains(wp_json_encode($audit),'@'), 'coverage report contains counts only and never enrols clients');
    file_put_contents('/var/www/html/client-mailing-fixture.json', wp_json_encode(['target'=>home_url('/'),'client'=>$clients['browser'],'blocked'=>$clients['blocked']]));
});
wp_set_current_user($mailing_original_user);
mailing_expect(get_current_blog_id()===1 && (!is_multisite() || !ms_is_switched()), 'original blog restored');
