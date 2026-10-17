<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) {
    throw new RuntimeException('Disposable fixture required.');
}
$actor = peracrm_with_target_blog(static function () { return (int) get_option('campaign_fixture_manager'); });
wp_set_current_user($actor);
$mode = $args[0] ?? '';
if ($mode === 'hold') {
    peracrm_with_target_blog(static function () {
        peracrm_campaigns_repository_mutate('concurrent+mailbox@example.test', static function () {
            file_put_contents('/tmp/peracrm-campaign-holder-ready', 'locked');
            sleep(8); // Other real wpdb connections must block on this mailbox.
            return true;
        });
    });
    return;
}
$start = microtime(true);
$id = peracrm_campaigns_save_subscriber(' CONCURRENT+MAILBOX@EXAMPLE.TEST ');
if (is_wp_error($id)) { throw new RuntimeException($id->get_error_code()); }
$result = peracrm_campaigns_add_suppression('concurrent+mailbox@example.test', $mode, [
    'source' => 'fixture', 'source_ref' => $mode, 'occurred_at' => '2026-01-01 00:00:00',
]);
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_code()); }
file_put_contents('/tmp/peracrm-campaign-duration-' . $mode, (string) (microtime(true) - $start));
