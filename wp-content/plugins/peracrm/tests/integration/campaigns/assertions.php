<?php
/** Actual WordPress, dbDelta and MySQL; run only in disposable Compose volumes. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) {
    throw new RuntimeException('Disposable fixture required.');
}
function fixture_expect($condition, $message) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . "\n";
}
function fixture_context() {
    fixture_expect(get_current_blog_id() === 1 && (!is_multisite() || !ms_is_switched()), 'originating blog restored');
}
function fixture_mailbox($email) {
    return peracrm_with_target_blog(static function () use ($email) { return peracrm_campaigns_repository_identity($email); });
}
$phase = $args[0] ?? '';
global $wpdb;
if ($phase === 'concurrency') {
    $row = fixture_mailbox('concurrent+mailbox@example.test');
    fixture_expect($row && $row['unsubscribe'] && $row['hard_bounce'] && $row['complaint'] && $row['administrative'], 'concurrent mutations preserve every reason');
    peracrm_with_target_blog(static function () use ($wpdb, $row) {
        $subscribers = peracrm_table('peracrm_subscribers');
        $events = peracrm_table('peracrm_email_suppression_events');
        fixture_expect((int) $wpdb->get_var("SELECT COUNT(*) FROM {$subscribers} WHERE normalized_email = 'concurrent+mailbox@example.test'") === 1, 'concurrent normalized creation creates one subscriber');
        fixture_expect((int) $wpdb->get_var("SELECT COUNT(*) FROM {$events} WHERE normalized_email = 'concurrent+mailbox@example.test'") === 4, 'all concurrent suppression audit writes persist');
        fixture_expect((int) $row['version'] === 4, 'serialized profile mutations retain monotonic version');
    });
    $durations = array_map('floatval', array_map('file_get_contents', glob('/tmp/peracrm-campaign-duration-*')));
    fixture_expect(count($durations) === 4 && max($durations) > 0.5, 'independent WP connections encountered actual lock contention');
    fixture_expect(!peracrm_campaigns_is_eligible('concurrent+mailbox@example.test'), 'concurrent mailbox remains suppressed');
    fixture_context();
    return;
}
fixture_expect($phase === 'migrations', 'known fixture phase');
fixture_expect(str_starts_with(wp_get_theme()->get_stylesheet(), 'twentytwenty'), 'bundled default WordPress theme: ' . wp_get_theme()->get_stylesheet());
fixture_expect(peracrm_campaigns_schema_ready(), 'fresh activation verifies schema readiness');
fixture_expect(isset(get_option('rewrite_rules', [])['^crm/campaigns/?$']), 'fresh activation persists campaign routes through the shared registrar');
peracrm_with_target_blog(static function () use ($wpdb) {
    peracrm_campaigns_assert_schema();
    fixture_expect((int) get_option('peracrm_schema_version') === PERACRM_SCHEMA_VERSION, 'fresh migration marker matches current schema');
    fixture_expect((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . peracrm_table('peracrm_subscribers')) === 0, 'fresh migration performs no imports');
    // Reconstruct the previous schema in disposable volumes only. No plugin
    // down migration or production data path is introduced by this test.
    foreach (array_keys(peracrm_campaigns_schema_definitions()) as $name) {
        $wpdb->query('DROP TABLE ' . peracrm_table($name));
    }
    peracrm_upgrade_schema_to(20, 0);
    update_option('peracrm_schema_version', 20);
    delete_option('peracrm_campaigns_schema_status');
    foreach (['administrator', 'manager'] as $role) {
        foreach (['peracrm_manage_campaigns', 'peracrm_manage_subscribers', 'peracrm_view_campaign_reports'] as $cap) {
            get_role($role)->remove_cap($cap);
        }
    }
});
do_action('init');
fixture_expect(peracrm_campaigns_schema_ready(), 'actual init upgrades schema 20');
fixture_context();
peracrm_with_target_blog(static function () {
    peracrm_campaigns_assert_schema();
    foreach (['administrator', 'manager'] as $role) {
        foreach (['peracrm_manage_campaigns', 'peracrm_manage_subscribers', 'peracrm_view_campaign_reports'] as $cap) {
            fixture_expect(get_role($role)->has_cap($cap) && !get_role('employee')->has_cap($cap), 'target role capabilities ' . $role . '/' . $cap);
        }
    }
    foreach (['manager', 'employee', 'administrator'] as $role) {
        $id = wp_insert_user(['user_login' => 'campaign-fixture-' . $role, 'user_pass' => wp_generate_password(), 'role' => $role]);
        fixture_expect(!is_wp_error($id), 'fixture user created for ' . $role);
        update_option('campaign_fixture_' . $role, $id);
    }
});
$manager = peracrm_with_target_blog(static function () { return (int) get_option('campaign_fixture_manager'); });
wp_set_current_user($manager);
fixture_expect(peracrm_campaigns_staff_can('peracrm_manage_subscribers', true), 'real manager capability is resolved on target blog');
if (is_multisite()) {
    fixture_expect(!user_can($manager, 'peracrm_manage_subscribers'), 'manager has no campaign grant on originating blog');
    $origin = $wpdb->prefix . 'peracrm_subscribers';
    fixture_expect($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($origin))) === null, 'no campaign tables on originating blog');
}
$id = peracrm_campaigns_save_subscriber(' Unique.Mail+News@Example.test ');
fixture_expect(!is_wp_error($id) && $id === peracrm_campaigns_save_subscriber('unique.mail+news@example.test'), 'real normalization and duplicate service writes');
fixture_expect(!is_wp_error(peracrm_campaigns_save_subscriber('uniquemail@example.test')), 'dots and plus-tags remain distinct');
peracrm_with_target_blog(static function () use ($wpdb) {
    $table = peracrm_table('peracrm_subscribers');
    $row = $wpdb->get_row("SELECT * FROM {$table} WHERE normalized_email = 'unique.mail+news@example.test'", ARRAY_A);
    unset($row['id']);
    $previous = $wpdb->suppress_errors(true);
    fixture_expect($wpdb->insert($table, $row) === false, 'real DB unique subscriber mailbox key rejects raw duplicate');
    $suppression_table = peracrm_table('peracrm_email_suppressions');
    $suppression = $wpdb->get_row("SELECT * FROM {$suppression_table} WHERE normalized_email = 'unique.mail+news@example.test'", ARRAY_A);
    fixture_expect($wpdb->insert($suppression_table, $suppression) === false, 'real DB suppression primary key rejects raw duplicate');
    $wpdb->suppress_errors($previous);
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    peracrm_upgrade_schema_to(PERACRM_SCHEMA_VERSION, PERACRM_SCHEMA_VERSION);
    fixture_expect((int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") === $count, 'repeated real dbDelta preserves subscriber records');
});
do_action('init');
fixture_expect(peracrm_campaigns_schema_ready(), 'repeated init stays ready');
foreach (['source', 'source_ref', 'evidence_ref', 'policy_version'] as $field) {
    $address = 'zero-' . $field . '@example.test';
    $evidence = ['source' => 'fixture', 'source_ref' => 'fixture-1', 'evidence_ref' => 'consent-1',
        'policy_version' => 'v1', 'occurred_at' => '2026-01-01 00:00:00'];
    $evidence[$field] = '0';
    fixture_expect(!is_wp_error(peracrm_campaigns_save_subscriber($address)), 'synthetic zero-evidence identity created');
    fixture_expect(peracrm_campaigns_record_consent($address, 'subscribed', $evidence) === true
        && peracrm_campaigns_is_eligible($address), 'real DB string zero accepted and eligible for ' . $field);
    foreach (['missing', 'null', 'empty'] as $case) {
        $invalid = $evidence;
        if ($case === 'missing') { unset($invalid[$field]); } else { $invalid[$field] = $case === 'null' ? null : ''; }
        fixture_expect(is_wp_error(peracrm_campaigns_record_consent($address, 'subscribed', $invalid)), 'real service rejects ' . $case . ' ' . $field);
    }
}
// Fail a real assertion without a fake wpdb, then exercise recovery from init.
peracrm_with_target_blog(static function () use ($wpdb) {
    $wpdb->query('ALTER TABLE ' . peracrm_table('peracrm_subscribers') . ' DROP INDEX normalized_email');
    delete_option('peracrm_campaigns_schema_status');
});
$continued = false;
add_action('init', static function () use (&$continued) { $continued = true; }, 6);
do_action('init');
fixture_expect($continued && !peracrm_campaigns_schema_ready(), 'real init assertion failure is contained');
fixture_expect(is_wp_error(peracrm_campaigns_save_subscriber('denied@example.test')), 'schema failure denies campaign write');
fixture_context();
$admin = peracrm_with_target_blog(static function () { return (int) get_option('campaign_fixture_administrator'); });
wp_set_current_user($admin);
// Read-only verification of already-v21 correctly refuses a missing key. Repair
// the synthetic broken key manually, then retry verification as an administrator.
peracrm_with_target_blog(static function () use ($wpdb) {
    $wpdb->query('ALTER TABLE ' . peracrm_table('peracrm_subscribers') . ' ADD UNIQUE KEY normalized_email (normalized_email)');
});
fixture_expect(peracrm_campaigns_retry_schema() === true, 'real administrator retry recovers after repair');
$employee = peracrm_with_target_blog(static function () { return (int) get_option('campaign_fixture_employee'); });
wp_set_current_user($employee);
fixture_expect(is_wp_error(peracrm_campaigns_save_subscriber('denied@example.test')) && is_wp_error(peracrm_campaigns_retry_schema()), 'real employee campaign/retry denial');
wp_set_current_user(0);
fixture_expect(is_wp_error(peracrm_campaigns_save_subscriber('denied@example.test')), 'real anonymous denial');
fixture_context();
