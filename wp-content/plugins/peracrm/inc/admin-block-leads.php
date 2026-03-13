<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_user_is_wp_admin_blocked_role(): bool
{
    if (!is_user_logged_in()) {
        return false;
    }

    // Distinction:
    // - WP role `lead` is a membership/account role.
    // - CRM business lead/client is deal-derived and must not be used as admin-access signal.
    // Block non-admin CRM/member account roles from wp-admin.
    if (current_user_can('manage_options')) {
        return false;
    }

    if (function_exists('peracrm_user_is_membership_lead') && peracrm_user_is_membership_lead()) {
        return true;
    }

    $user = wp_get_current_user();
    if (!$user || !$user->exists()) {
        return false;
    }

    $roles = (array) $user->roles;
    return in_array('manager', $roles, true) || in_array('employee', $roles, true);
}

function peracrm_block_wp_admin_for_non_admin_roles(): void
{
    if (!is_user_logged_in()) {
        return;
    }

    if (!peracrm_user_is_wp_admin_blocked_role()) {
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

    global $pagenow;
    if ($pagenow === 'admin-post.php') {
        return;
    }

    if (function_exists('wp_is_json_request') && wp_is_json_request()) {
        return;
    }

    wp_safe_redirect(home_url('/crm/'));
    exit;
}
add_action('admin_init', 'peracrm_block_wp_admin_for_non_admin_roles', 0);

add_filter('show_admin_bar', function ($show) {
    if (peracrm_user_is_wp_admin_blocked_role()) {
        return false;
    }

    return $show;
});
