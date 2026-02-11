<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERACRM_TARGET_BLOG_ID')) {
    // Set this to the peraproperty.com blog_id from `wp site list`.
    define('PERACRM_TARGET_BLOG_ID', 0);
}

function peracrm_membership_debug_enabled()
{
    return defined('WP_DEBUG') && WP_DEBUG && defined('PERACRM_DEBUG_MEMBERSHIP') && PERACRM_DEBUG_MEMBERSHIP;
}

function peracrm_membership_debug_log($message, array $context = [])
{
    if (!peracrm_membership_debug_enabled()) {
        return;
    }

    $pairs = [];
    foreach ($context as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value);
        }

        $pairs[] = sanitize_key((string) $key) . '=' . sanitize_text_field((string) $value);
    }

    error_log('[peracrm membership] ' . $message . (empty($pairs) ? '' : ' ' . implode(' ', $pairs)));
}

function peracrm_membership_normalize_url($url)
{
    return untrailingslashit(strtolower((string) $url));
}

function peracrm_membership_is_target_site_url($blog_id)
{
    $blog_id = (int) $blog_id;
    if ($blog_id <= 0) {
        return false;
    }

    $site_url = (string) get_blog_option($blog_id, 'siteurl', '');
    if ($site_url === '') {
        switch_to_blog($blog_id);
        $site_url = (string) home_url('/');
        restore_current_blog();
    }

    $normalized_site_url = peracrm_membership_normalize_url($site_url);

    return in_array($normalized_site_url, [
        peracrm_membership_normalize_url('https://peraproperty.com'),
        peracrm_membership_normalize_url('http://peraproperty.com'),
        peracrm_membership_normalize_url('https://www.peraproperty.com'),
        peracrm_membership_normalize_url('http://www.peraproperty.com'),
    ], true);
}

function peracrm_membership_get_target_blog_id()
{
    if (!is_multisite()) {
        return 0;
    }

    $configured_blog_id = (int) PERACRM_TARGET_BLOG_ID;
    if ($configured_blog_id > 0) {
        $details = get_blog_details($configured_blog_id);
        return $details ? $configured_blog_id : 0;
    }

    $sites = get_sites([
        'number' => 0,
        'fields' => 'ids',
    ]);

    foreach ($sites as $site_id) {
        $site_id = (int) $site_id;
        if ($site_id > 0 && peracrm_membership_is_target_site_url($site_id)) {
            return $site_id;
        }
    }

    return 0;
}

function peracrm_membership_user_is_staff(WP_User $user)
{
    if (user_can($user, 'manage_options') || user_can($user, 'edit_crm_clients')) {
        return true;
    }

    $roles = array_map('strval', (array) $user->roles);
    return in_array('administrator', $roles, true) || in_array('employee', $roles, true);
}

function peracrm_membership_ensure_lead_role()
{
    if (!is_multisite()) {
        return;
    }

    $current_blog_id = (int) get_current_blog_id();
    $target_blog_id = peracrm_membership_get_target_blog_id();
    if ($target_blog_id <= 0) {
        peracrm_membership_debug_log('role_skipped_target_blog_not_found', [
            'current_blog_id' => $current_blog_id,
            'target_blog_id' => $target_blog_id,
            'role' => 'lead',
        ]);
        return;
    }

    $switched = false;
    if ($current_blog_id !== $target_blog_id) {
        switch_to_blog($target_blog_id);
        $switched = true;
    }

    if (!get_role('lead')) {
        add_role(
            'lead',
            __('Lead', 'peracrm'),
            [
                'read' => true,
            ]
        );
    }

    if ($switched) {
        restore_current_blog();
    }

    peracrm_membership_debug_log('role_ensured', [
        'current_blog_id' => $current_blog_id,
        'target_blog_id' => $target_blog_id,
        'role' => 'lead',
    ]);
}

function peracrm_membership_assign_lead_if_needed($user_id, $source)
{
    $user_id = (int) $user_id;
    $current_blog_id = (int) get_current_blog_id();
    $target_blog_id = peracrm_membership_get_target_blog_id();

    if (!is_multisite()) {
        peracrm_membership_debug_log('membership_skipped_not_multisite', [
            'user_id' => $user_id,
            'current_blog_id' => $current_blog_id,
            'target_blog_id' => $target_blog_id,
            'source' => $source,
        ]);
        return;
    }

    if ($target_blog_id <= 0) {
        peracrm_membership_debug_log('membership_skipped_target_blog_not_found', [
            'user_id' => $user_id,
            'current_blog_id' => $current_blog_id,
            'target_blog_id' => $target_blog_id,
            'source' => $source,
        ]);
        return;
    }

    $user = get_user_by('id', $user_id);
    if (!$user instanceof WP_User) {
        peracrm_membership_debug_log('membership_skipped_invalid_user', [
            'user_id' => $user_id,
            'current_blog_id' => $current_blog_id,
            'target_blog_id' => $target_blog_id,
            'source' => $source,
        ]);
        return;
    }

    if (peracrm_membership_user_is_staff($user)) {
        peracrm_membership_debug_log('membership_skipped_staff_user', [
            'user_id' => $user_id,
            'current_blog_id' => $current_blog_id,
            'target_blog_id' => $target_blog_id,
            'role' => 'lead',
            'source' => $source,
            'reason' => 'staff_account',
        ]);
        return;
    }

    if (is_user_member_of_blog($user_id, $target_blog_id)) {
        peracrm_membership_debug_log('membership_skipped_existing_member', [
            'user_id' => $user_id,
            'current_blog_id' => $current_blog_id,
            'target_blog_id' => $target_blog_id,
            'role' => 'lead',
            'source' => $source,
            'reason' => 'already_member',
        ]);
        return;
    }

    add_user_to_blog($target_blog_id, $user_id, 'lead');

    peracrm_membership_debug_log('membership_assigned', [
        'user_id' => $user_id,
        'current_blog_id' => $current_blog_id,
        'target_blog_id' => $target_blog_id,
        'role' => 'lead',
        'source' => $source,
    ]);
}

function peracrm_membership_on_user_register($user_id)
{
    peracrm_membership_assign_lead_if_needed((int) $user_id, 'user_register');
}
add_action('user_register', 'peracrm_membership_on_user_register', 20);

function peracrm_membership_on_wp_login($user_login, $user)
{
    if (!$user instanceof WP_User) {
        return;
    }

    peracrm_membership_assign_lead_if_needed((int) $user->ID, 'wp_login');
}
add_action('wp_login', 'peracrm_membership_on_wp_login', 5, 2);

add_action('init', 'peracrm_membership_ensure_lead_role', 5);
