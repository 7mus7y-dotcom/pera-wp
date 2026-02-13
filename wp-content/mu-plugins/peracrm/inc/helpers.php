<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_now_mysql()
{
    return current_time('mysql');
}

function peracrm_table($suffix)
{
    global $wpdb;

    return $wpdb->prefix . $suffix;
}

function peracrm_json_encode($data)
{
    $encoded = wp_json_encode($data);
    if (false === $encoded || null === $encoded) {
        return '{}';
    }

    return $encoded;
}

function peracrm_json_decode($json)
{
    if (!is_string($json) || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function peracrm_pipeline_assigned_meta_keys()
{
    if (function_exists('peracrm_admin_work_queue_assigned_meta_keys')) {
        return peracrm_admin_work_queue_assigned_meta_keys();
    }

    return ['assigned_advisor_user_id', 'crm_assigned_advisor'];
}

function peracrm_client_get_assigned_advisor_id($client_id)
{
    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        return 0;
    }

    $assigned_id = (int) get_post_meta($client_id, 'assigned_advisor_user_id', true);
    $crm_id = (int) get_post_meta($client_id, 'crm_assigned_advisor', true);

    if (function_exists('peracrm_user_is_valid_advisor')) {
        if (peracrm_user_is_valid_advisor($assigned_id)) {
            return $assigned_id;
        }

        if (peracrm_user_is_valid_advisor($crm_id)) {
            return $crm_id;
        }
    }

    return 0;
}


function peracrm_client_get_profile($client_id)
{
    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        return [
            'status' => '',
            'client_type' => '',
            'preferred_contact' => '',
            'budget_min_usd' => '',
            'budget_max_usd' => '',
            'phone' => '',
            'email' => '',
        ];
    }

    return [
        'status' => get_post_meta($client_id, '_peracrm_status', true),
        'client_type' => get_post_meta($client_id, '_peracrm_client_type', true),
        'preferred_contact' => get_post_meta($client_id, '_peracrm_preferred_contact', true),
        'budget_min_usd' => get_post_meta($client_id, '_peracrm_budget_min_usd', true),
        'budget_max_usd' => get_post_meta($client_id, '_peracrm_budget_max_usd', true),
        'phone' => get_post_meta($client_id, '_peracrm_phone', true),
        'email' => get_post_meta($client_id, '_peracrm_email', true),
    ];
}

function peracrm_client_update_profile($client_id, $data)
{
    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        return false;
    }

    $allowed_status = function_exists('peracrm_status_options')
        ? array_keys((array) peracrm_status_options())
        : ['enquiry', 'active', 'dormant', 'closed'];
    $allowed_types = function_exists('peracrm_client_type_options')
        ? array_keys((array) peracrm_client_type_options())
        : ['citizenship', 'investor', 'lifestyle', 'seller', 'landlord'];
    $allowed_contact = ['phone', 'whatsapp', 'email'];

    $status = isset($data['status']) ? sanitize_key($data['status']) : '';
    if (!in_array($status, $allowed_status, true)) {
        $status = '';
    }

    $client_type = isset($data['client_type']) ? sanitize_key($data['client_type']) : '';
    if (!in_array($client_type, $allowed_types, true)) {
        $client_type = '';
    }

    $preferred_contact = isset($data['preferred_contact']) ? sanitize_key($data['preferred_contact']) : '';
    if (!in_array($preferred_contact, $allowed_contact, true)) {
        $preferred_contact = '';
    }

    $phone = isset($data['phone']) ? preg_replace('/[^0-9+]/', '', $data['phone']) : '';
    if (strlen($phone) > 30) {
        $phone = substr($phone, 0, 30);
    }

    $email = isset($data['email']) ? sanitize_email($data['email']) : '';
    if ($email !== '' && !is_email($email)) {
        $email = '';
    }
    if (strlen($email) > 254) {
        $email = substr($email, 0, 254);
    }

    $min = null;
    if (array_key_exists('budget_min_usd', $data)) {
        $min = peracrm_client_profile_sanitize_budget($data['budget_min_usd']);
    }

    $max = null;
    if (array_key_exists('budget_max_usd', $data)) {
        $max = peracrm_client_profile_sanitize_budget($data['budget_max_usd']);
    }

    if (null === $min && null !== $max) {
        $min = 0;
    }

    if (null !== $min && null !== $max && $max < $min) {
        $swap = $min;
        $min = $max;
        $max = $swap;
    }

    $fields = [
        '_peracrm_status' => $status,
        '_peracrm_client_type' => $client_type,
        '_peracrm_preferred_contact' => $preferred_contact,
        '_peracrm_phone' => $phone,
        '_peracrm_email' => $email,
    ];

    foreach ($fields as $meta_key => $value) {
        if ($value === '' || null === $value) {
            delete_post_meta($client_id, $meta_key);
        } else {
            update_post_meta($client_id, $meta_key, $value);
        }
    }

    if (null !== $min) {
        update_post_meta($client_id, '_peracrm_budget_min_usd', (int) $min);
    } else {
        delete_post_meta($client_id, '_peracrm_budget_min_usd');
    }

    if (null !== $max) {
        update_post_meta($client_id, '_peracrm_budget_max_usd', (int) $max);
    } else {
        delete_post_meta($client_id, '_peracrm_budget_max_usd');
    }

    return true;
}

function peracrm_client_type_options()
{
    return [
        'citizenship' => 'Citizenship',
        'investor' => 'Investor',
        'lifestyle' => 'Lifestyle',
        'seller' => 'Seller',
        'landlord' => 'Landlord',
    ];
}

function peracrm_status_options()
{
    return [
        'enquiry' => 'Enquiry',
        'active' => 'Active',
        'dormant' => 'Dormant',
        'closed' => 'Closed',
    ];
}

function peracrm_party_get_derived_type($party_id)
{
    $party_id = (int) $party_id;
    if ($party_id <= 0) {
        return 'lead';
    }

    if (!function_exists('peracrm_party_batch_get_closed_won_client_ids')) {
        return 'lead';
    }

    $client_ids = peracrm_party_batch_get_closed_won_client_ids([$party_id]);

    return in_array($party_id, array_map('intval', (array) $client_ids), true) ? 'client' : 'lead';
}

function peracrm_client_profile_sanitize_budget($value)
{
    if ($value === '' || $value === null) {
        return null;
    }

    $value = (int) $value;
    if ($value < 0) {
        $value = 0;
    }

    return $value;
}

function peracrm_user_is_valid_advisor($user_id)
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    if (function_exists('peracrm_user_is_staff') && peracrm_user_is_staff($user_id)) {
        return true;
    }

    return user_can($user, 'manage_options');
}

function peracrm_get_staff_users()
{
    $load_users = static function () {
        $users = get_users([
            'role__in' => ['employee', 'manager', 'administrator'],
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        if (!is_array($users) || empty($users)) {
            return [];
        }

        return array_values(array_filter($users, static function ($user) {
            if (!$user instanceof WP_User) {
                return false;
            }

            return function_exists('peracrm_user_is_staff') && peracrm_user_is_staff((int) $user->ID);
        }));
    };

    if (function_exists('peracrm_with_target_blog')) {
        return (array) peracrm_with_target_blog($load_users);
    }

    if (is_multisite() && function_exists('switch_to_blog') && function_exists('restore_current_blog')) {
        $target_blog_id = function_exists('peracrm_get_target_blog_id') ? (int) peracrm_get_target_blog_id() : 0;
        $current_blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        if ($target_blog_id > 0 && $target_blog_id !== $current_blog_id) {
            switch_to_blog($target_blog_id);
            try {
                return (array) $load_users();
            } finally {
                restore_current_blog();
            }
        }
    }

    return (array) $load_users();
}


function peracrm_user_is_staff($user_id)
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $user = get_userdata($user_id);
    if (!$user instanceof WP_User) {
        return false;
    }

    $roles = (array) $user->roles;
    return in_array('employee', $roles, true)
        || in_array('manager', $roles, true)
        || in_array('administrator', $roles, true);
}

/**
 * @deprecated Use peracrm_user_is_staff() for eligibility checks.
 */
function peracrm_user_is_employee_advisor($user_id)
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $user = get_userdata($user_id);
    if (!$user instanceof WP_User) {
        return false;
    }

    return in_array('employee', (array) $user->roles, true);
}

function peracrm_get_target_blog_id()
{
    if (!is_multisite()) {
        return 0;
    }

    if (function_exists('peracrm_membership_get_target_blog_id')) {
        return (int) peracrm_membership_get_target_blog_id();
    }

    if (defined('PERACRM_TARGET_BLOG_ID')) {
        return max(0, (int) PERACRM_TARGET_BLOG_ID);
    }

    return 0;
}

function peracrm_switch_to_target_blog_if_needed()
{
    $target_blog_id = peracrm_get_target_blog_id();
    if (!is_multisite() || $target_blog_id <= 0 || get_current_blog_id() === $target_blog_id) {
        return false;
    }

    switch_to_blog($target_blog_id);
    return true;
}

function peracrm_with_target_blog(callable $callback)
{
    $switched = peracrm_switch_to_target_blog_if_needed();
    try {
        return $callback();
    } finally {
        if ($switched) {
            restore_current_blog();
        }
    }
}

/**
 * @deprecated Use peracrm_get_staff_users().
 */
function peracrm_get_advisor_users()
{
    return function_exists('peracrm_get_staff_users') ? peracrm_get_staff_users() : [];
}

function peracrm_user_can_access_crm($user_id = 0)
{
    if ($user_id > 0) {
        $user = get_userdata((int) $user_id);
        if (!$user instanceof WP_User) {
            return false;
        }

        return user_can($user, 'manage_options')
            || user_can($user, 'edit_crm_clients')
            || user_can($user, 'edit_crm_leads')
            || user_can($user, 'edit_crm_deals');
    }

    return current_user_can('manage_options')
        || current_user_can('edit_crm_clients')
        || current_user_can('edit_crm_leads')
        || current_user_can('edit_crm_deals');
}
