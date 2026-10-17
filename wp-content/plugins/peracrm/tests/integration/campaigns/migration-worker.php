<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) {
    throw new RuntimeException('Disposable fixture required.');
}
function migration_expect($condition, $message) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . "\n";
}
$mode = $args[0] ?? '';
if ($mode === 'prepare') {
    peracrm_with_target_blog(static function () {
        global $wpdb;
        foreach (array_keys(peracrm_campaigns_schema_definitions()) as $name) {
            $wpdb->query('DROP TABLE ' . peracrm_table($name));
        }
        update_option('peracrm_schema_version', 20);
        delete_option('peracrm_campaigns_schema_status');
    });
    return;
}
if ($mode === 'race-owner') {
    migration_expect(peracrm_campaigns_schema_ready(), 'one installer publishes ready through actual init');
    file_put_contents('/tmp/peracrm-migration-owner-finished', 'ready');
} elseif ($mode === 'race-loser') {
    peracrm_with_target_blog(static function () {
        $slot = json_decode(file_get_contents('/tmp/peracrm-migration-owner-ready'), true);
        migration_expect(!peracrm_campaigns_schema_publish($slot, 'peracrm_campaigns_schema_status', ['state' => 'failed'])
            && !peracrm_campaigns_schema_release_slot($slot), 'competing connection cannot publish or release even with the owner token');
        migration_expect(peracrm_campaigns_schema_option('peracrm_campaigns_schema_status')['state'] === 'checking', 'loser leaves live owner state unchanged');
        file_put_contents('/tmp/peracrm-migration-loser-returned', 'ordinary WP init returned while owner was blocked');
        peracrm_fixture_wait_for('/tmp/peracrm-migration-owner-finished');
        migration_expect(!peracrm_campaigns_schema_publish($slot, 'peracrm_campaigns_schema_status', ['state' => 'failed'])
            && !peracrm_campaigns_schema_publish($slot, 'peracrm_schema_version', 20), 'late losing request cannot overwrite successful readiness or marker');
        migration_expect(peracrm_campaigns_schema_ready(), 'late losing request leaves campaigns ready');
    });
} elseif ($mode === 'stale-owner') {
    peracrm_with_target_blog(static function () {
        global $wpdb;
        $slot = peracrm_campaigns_schema_acquire_slot();
        migration_expect((bool) $slot, 'stale fixture initially owns a real native slot');
        migration_expect(peracrm_campaigns_schema_publish($slot, 'peracrm_campaigns_schema_status', [
            'state' => 'checking', 'version' => 21, 'checked_at' => '2020-01-01 00:00:00', 'attempt_token' => $slot['token'],
        ]), 'interrupted attempt records checking');
        // Simulate loss of the original DB connection's advisory lock. The PHP
        // attempt resumes later; its old token must never affect its successor.
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $slot['name']));
        file_put_contents('/tmp/peracrm-migration-stale-released', 'old lock is gone');
        peracrm_fixture_wait_for('/tmp/peracrm-migration-successor-ready');
        $new = peracrm_campaigns_schema_option('peracrm_campaigns_schema_status');
        migration_expect($new['state'] === 'ready' && $new['attempt_token'] !== $slot['token'], 'administrator successor has published a newer successful result');
        migration_expect(!peracrm_campaigns_schema_publish($slot, 'peracrm_campaigns_schema_status', ['state' => 'failed'])
            && !peracrm_campaigns_schema_publish($slot, 'peracrm_schema_version', 20)
            && !peracrm_campaigns_schema_release_slot($slot), 'stale genuine owner cannot overwrite or release its live successor');
        migration_expect($new === peracrm_campaigns_schema_option('peracrm_campaigns_schema_status') && peracrm_campaigns_schema_ready(), 'new successful readiness survives stale completion');
        file_put_contents('/tmp/peracrm-migration-stale-done', 'all stale writes rejected');
    });
} elseif ($mode === 'successor') {
    $admin = peracrm_with_target_blog(static function () { return (int) get_option('campaign_fixture_administrator'); });
    wp_set_current_user($admin);
    migration_expect(peracrm_campaigns_retry_schema() === true && peracrm_campaigns_schema_ready(), 'controlled administrator retry recovers interrupted attempt');
} elseif ($mode === 'verify') {
    $installers = file('/tmp/peracrm-migration-installers', FILE_IGNORE_NEW_LINES);
    migration_expect(count($installers) === 4 && count(array_unique($installers)) === 1
        && str_starts_with($installers[0], 'race-owner:'), 'exactly one real connection ran all four campaign table installers');
    migration_expect(is_file('/tmp/peracrm-migration-loser-returned'), 'competing ordinary request stayed available before owner finished');
    migration_expect(peracrm_campaigns_schema_ready(), 'migration race and stale completion preserve final readiness');
    peracrm_with_target_blog(static function () {
        $slot = peracrm_campaigns_schema_acquire_slot();
        migration_expect((bool) $slot && peracrm_campaigns_schema_release_slot($slot), 'completed owners released the migration slot');
    });
} else {
    throw new RuntimeException('Unknown fixture mode.');
}
migration_expect(get_current_blog_id() === 1 && (!is_multisite() || !ms_is_switched()), 'concurrent migration restores originating blog');
