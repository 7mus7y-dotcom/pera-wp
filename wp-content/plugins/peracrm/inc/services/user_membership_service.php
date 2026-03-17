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

function peracrm_membership_ensure_lead_role_for_blog($target_blog_id, &$role_created = null)
{
    $target_blog_id = (int) $target_blog_id;
    if (!is_multisite() || $target_blog_id <= 0) {
        $role_created = false;
        return false;
    }

    $role_created = false;
    $current_blog_id = (int) get_current_blog_id();
    $switched = false;

    if ($current_blog_id !== $target_blog_id) {
        switch_to_blog($target_blog_id);
        $switched = true;
    }

    $role_exists = false;
    try {
        $roles = wp_roles();
        if (method_exists($roles, 'for_site')) {
            $roles->for_site($target_blog_id);
        }

        $role_exists = $roles->is_role('lead');
        if (!$role_exists) {
            add_role(
                'lead',
                __('Lead', 'peracrm'),
                [
                    'read' => true,
                ]
            );
            $role_created = true;
            $role_exists = $roles->is_role('lead');
        }
    } finally {
        if ($switched) {
            restore_current_blog();
        }
    }

    if (!$role_exists) {
        $role_created = false;
    }

    return $role_exists;
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

    $role_created = false;
    if (!peracrm_membership_ensure_lead_role_for_blog($target_blog_id, $role_created)) {
        peracrm_membership_debug_log('role_skipped_target_blog_not_found', [
            'current_blog_id' => $current_blog_id,
            'target_blog_id' => $target_blog_id,
            'role' => 'lead',
        ]);
        return;
    }

    peracrm_membership_debug_log($role_created ? 'role_created' : 'role_exists', [
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

    $role_created = false;
    if (!peracrm_membership_ensure_lead_role_for_blog($target_blog_id, $role_created)) {
        peracrm_membership_debug_log('membership_skipped_role_ensure_failed', [
            'user_id' => $user_id,
            'current_blog_id' => $current_blog_id,
            'target_blog_id' => $target_blog_id,
            'source' => $source,
            'role' => 'lead',
        ]);
        return;
    }

    $is_existing_member = is_user_member_of_blog($user_id, $target_blog_id);
    if (!$is_existing_member) {
        add_user_to_blog($target_blog_id, $user_id, 'lead');
    }

    $switched = false;
    if ($current_blog_id !== $target_blog_id) {
        switch_to_blog($target_blog_id);
        $switched = true;
    }

    try {
        $target_user = new WP_User($user_id);
        $target_user_roles = array_map('strval', (array) $target_user->roles);
        $previous_role = (string) reset($target_user_roles);
        if ($previous_role === '') {
            $previous_role = 'none';
        }

        if (in_array('lead', $target_user_roles, true)) {
            peracrm_membership_debug_log('role_already_lead', [
                'user_id' => $user_id,
                'target_blog_id' => $target_blog_id,
                'source' => $source,
                'role_created' => $role_created,
            ]);
            return;
        }

        $target_user->set_role('lead');

        peracrm_membership_debug_log(
            $is_existing_member ? 'role_set_existing_member' : 'role_set_new_member',
            [
                'user_id' => $user_id,
                'target_blog_id' => $target_blog_id,
                'previous_role' => $previous_role,
                'new_role' => 'lead',
                'source' => $source,
                'role_created' => $role_created,
            ]
        );
    } finally {
        if ($switched) {
            restore_current_blog();
        }
    }
}

function peracrm_membership_ensure_lead_on_target_blog($user_id, $source = '')
{
    $user_id = (int) $user_id;
    $source = sanitize_key((string) $source);
    $current_blog_id = (int) get_current_blog_id();
    $target_blog_id = (int) peracrm_membership_get_target_blog_id();

    $result = [
        'ok' => false,
        'user_id' => $user_id,
        'source' => $source,
        'current_blog_id' => $current_blog_id,
        'target_blog_id' => $target_blog_id,
        'is_member' => false,
        'role_assigned' => false,
        'membership_error' => '',
    ];

    if (!is_multisite() || $user_id <= 0 || $target_blog_id <= 0) {
        return $result;
    }

    $user = get_user_by('id', $user_id);
    if (!$user instanceof WP_User) {
        return $result;
    }

    if (peracrm_membership_user_is_staff($user)) {
        $result['ok'] = true;
        $result['is_member'] = (bool) is_user_member_of_blog($user_id, $target_blog_id);
        return $result;
    }

    $role_created = false;
    if (!peracrm_membership_ensure_lead_role_for_blog($target_blog_id, $role_created)) {
        $result['membership_error'] = 'lead_role_missing';
        return $result;
    }

    $result['is_member'] = (bool) is_user_member_of_blog($user_id, $target_blog_id);
    if (!$result['is_member']) {
        $added = add_user_to_blog($target_blog_id, $user_id, 'lead');
        if (is_wp_error($added)) {
            $result['membership_error'] = $added->get_error_message();
            peracrm_membership_debug_log('membership_add_user_failed', $result + ['role_created' => $role_created]);
            return $result;
        }

        $result['is_member'] = (bool) is_user_member_of_blog($user_id, $target_blog_id);
    }

    $switched = false;
    if ($current_blog_id !== $target_blog_id) {
        switch_to_blog($target_blog_id);
        $switched = true;
    }

    try {
        $target_user = new WP_User($user_id);
        $target_roles = array_map('strval', (array) $target_user->roles);
        if (!in_array('lead', $target_roles, true)) {
            $target_user->set_role('lead');
        }

        $target_roles = array_map('strval', (array) $target_user->roles);
        $result['role_assigned'] = in_array('lead', $target_roles, true);
    } finally {
        if ($switched) {
            restore_current_blog();
        }
    }

    $result['ok'] = $result['is_member'] && $result['role_assigned'];

    return $result;
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
