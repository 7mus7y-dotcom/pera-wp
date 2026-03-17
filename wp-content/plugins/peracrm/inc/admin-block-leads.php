<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_user_is_lead(): bool
{
    $user = wp_get_current_user();

    if (!$user || !$user->exists()) {
        return false;
    }

    return in_array('lead', (array) $user->roles, true);
}

function peracrm_block_wp_admin_for_leads(): void
{
    if (!is_user_logged_in()) {
        return;
    }

    if (!peracrm_user_is_lead()) {
        return;
    }

    if (!is_network_admin() && !is_admin()) {
        return;
    }

    if (wp_doing_ajax()) {
        return;
    }

    if (defined('DOING_CRON') && DOING_CRON) {
        return;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    if (function_exists('wp_is_json_request') && wp_is_json_request()) {
        return;
    }

    wp_safe_redirect(home_url('/crm/'));
    exit;
}
add_action('admin_init', 'peracrm_block_wp_admin_for_leads', 0);

add_filter('show_admin_bar', function ($show) {
    if (peracrm_user_is_lead()) {
        return false;
    }

    return $show;
});
