<?php
// Loaded only by the disposable Compose fixture, never by production PeraCRM.
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) {
    exit('Disposable fixture guard missing.');
}
add_filter('pre_wp_mail', '__return_false');
add_filter('pre_http_request', static function () {
    return new WP_Error('fixture_network_disabled', 'External requests are disabled in this fixture.');
});

function peracrm_fixture_wait_for($path)
{
    $deadline = microtime(true) + 30;
    while (!is_file($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Fixture barrier timed out: ' . basename($path));
        }
        usleep(50000);
    }
}

// Fixture-only barriers inside real init/SQL: no production hooks are added.
add_filter('query', static function ($query) {
    global $wpdb;
    $mode = getenv('PERACRM_MIGRATION_WORKER_MODE');
    if ($mode === 'race-loser' && str_contains($query, 'GET_LOCK(')) {
        file_put_contents('/tmp/peracrm-migration-loser-paused', 'before atomic acquisition');
        peracrm_fixture_wait_for('/tmp/peracrm-migration-owner-ready');
    }
    if (str_starts_with($query, 'CREATE TABLE ') && preg_match('/peracrm_(?:subscribers|subscription_events|email_suppressions|email_suppression_events|campaign_drafts|campaign_draft_revisions|campaign_configuration) /', $query)
        && in_array($mode, ['race-owner', 'race-loser'], true)) {
        file_put_contents('/tmp/peracrm-migration-installers', $mode . ':' . $wpdb->get_var('SELECT CONNECTION_ID()') . "\n", FILE_APPEND | LOCK_EX);
        if (str_contains($query, 'peracrm_subscribers ')) {
            file_put_contents('/tmp/peracrm-migration-owner-ready', wp_json_encode([
                'name' => peracrm_campaigns_schema_lock_name(),
                'token' => peracrm_campaigns_schema_option('peracrm_campaigns_schema_owner'),
            ]));
            peracrm_fixture_wait_for('/tmp/peracrm-migration-owner-release');
        }
    }
    if ($mode === 'successor' && str_contains($query, 'RELEASE_LOCK(') && str_contains($query, 'EXISTS(')) {
        file_put_contents('/tmp/peracrm-migration-successor-ready', 'ready but still holding native slot');
        peracrm_fixture_wait_for('/tmp/peracrm-migration-stale-done');
    }
    return $query;
});

add_filter('query', static function ($query) {
    if (getenv('PERACRM_PHASE2_IMPORT_PAUSE') === '1' && str_contains($query, 'INSERT INTO') && str_contains($query, 'peracrm_subscribers') && str_contains($query, 'race-phase2-public@example.test')) {
        file_put_contents('/tmp/peracrm-phase2-import-held','inside mailbox transaction');
        usleep(1500000);
    }
    return $query;
});

// Synthetic public CPT only in this disposable fixture. Production ownership stays outside PeraCRM.
add_action('init',static function () {
    if (get_option('campaign_fixture_property_enabled')) { register_post_type('property',['public'=>true,'label'=>'Fixture properties','supports'=>['title','editor','excerpt','thumbnail']]); }
},20);
add_filter('post_link',static function ($url,$post) { return str_starts_with($post->post_title,'Phase3B') ? 'https://example.org/stories/'.$post->ID : $url; },20,2);
add_filter('post_type_link',static function ($url,$post) { return str_starts_with($post->post_title,'Phase3B') ? 'https://example.org/properties/'.$post->ID : $url; },20,2);

// Phase 3C browser-only fault and evidence controls, confined to disposable fixtures.
add_filter('query', static function ($sql) {
    if (($_COOKIE['freeze_fixture_failure'] ?? '')==='1' && str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_campaign_freeze_attempts') && str_contains($sql,'`last_id`') && str_contains($sql,"'materializing'")) {
        return 'UPDATE peracrm_fixture_missing SET id=1';
    }
    return $sql;
});
add_action('wp_ajax_peracrm_freeze_fixture',static function () {
    if (!current_user_can('manage_options')) { wp_send_json_error([],403); }
    $op=$_POST['operation'] ?? '';
    if ($op==='impersonate') { update_user_meta(get_current_user_id(),PERACRM_VIEW_AS_USER_META_KEY,(int)peracrm_with_target_blog(static function () { return get_option('campaign_fixture_employee'); })); wp_send_json_success(); }
    if ($op==='restore') { delete_user_meta(get_current_user_id(),PERACRM_VIEW_AS_USER_META_KEY); wp_send_json_success(); }
    if ($op==='subscriber_access') {
        peracrm_with_target_blog(static function () { $u=new WP_User(get_current_user_id()); $u->add_cap('peracrm_manage_subscribers',($_POST['enabled'] ?? '')==='1'); });
        wp_send_json_success();
    }
    if ($op==='evidence') {
        $hash=peracrm_with_target_blog(static function () { global $wpdb; $out=[]; foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events','peracrm_subscriber_tokens'] as $name) { $out[$name]=$wpdb->get_results('SELECT * FROM '.peracrm_table($name).' ORDER BY 1',ARRAY_A); } return hash('sha256',wp_json_encode($out)); });
        wp_send_json_success(['protected'=>$hash]);
    }
    wp_send_json_error([],422);
});

add_filter('map_meta_cap',static function ($caps,$cap) {
    if ($cap==='peracrm_manage_subscribers' && ($_COOKIE['freeze_fixture_no_subscribers'] ?? '')==='1') { return ['do_not_allow']; }
    return $caps;
},99,2);
