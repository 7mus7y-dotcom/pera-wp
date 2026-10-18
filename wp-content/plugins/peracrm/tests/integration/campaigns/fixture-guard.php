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
    if (str_starts_with($query, 'CREATE TABLE ') && preg_match('/peracrm_(?:subscribers|subscription_events|email_suppressions|email_suppression_events) /', $query)
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
