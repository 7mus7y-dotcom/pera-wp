<?php
// Loaded only by the disposable Compose fixture, never by production PeraCRM.
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) {
    exit('Disposable fixture guard missing.');
}
add_filter('pre_wp_mail', '__return_false');
add_filter('pre_http_request', static function () {
    return new WP_Error('fixture_network_disabled', 'External requests are disabled in this fixture.');
});
