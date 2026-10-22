<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/recipient-withdrawal.php';

add_action('init', static function () {
    add_rewrite_rule('^email-unsubscribe/?$', 'index.php?peracrm_recipient_withdrawal=visible', 'top');
    add_rewrite_rule('^email-unsubscribe/one-click/?$', 'index.php?peracrm_recipient_withdrawal=machine', 'top');
});
add_filter('query_vars', static function ($vars) { $vars[] = 'peracrm_recipient_withdrawal'; return $vars; });
add_action('template_redirect', static function () {
    $route = (string)get_query_var('peracrm_recipient_withdrawal');
    if (!in_array($route, ['visible','machine'], true)) { return; }
    peracrm_campaign_recipient_withdrawal_respond(peracrm_campaign_recipient_withdrawal_service(), $route === 'machine');
    exit;
}, 0);
