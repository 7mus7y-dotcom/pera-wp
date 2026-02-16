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
            'bedrooms' => '',
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
        'bedrooms' => get_post_meta($client_id, '_peracrm_bedrooms', true),
        'phone' => get_post_meta($client_id, '_peracrm_phone', true),
        'email' => get_post_meta($client_id, '_peracrm_email', true),
    ];
}

function peracrm_phone_canonical_from_components($country_raw, $national_raw)
{
    $country_digits = preg_replace('/\D+/', '', sanitize_text_field((string) $country_raw));
    $national_digits = preg_replace('/\D+/', '', sanitize_text_field((string) $national_raw));
    $national_digits = ltrim((string) $national_digits, '0');

    if ($country_digits === '' || $national_digits === '') {
        return '';
    }

    return '+' . $country_digits . $national_digits;
}

function peracrm_phone_canonical_from_source(array $source, $country_key = 'phone_country', $national_key = 'phone_national', $legacy_key = 'phone')
{
    $country_raw = isset($source[$country_key]) ? wp_unslash((string) $source[$country_key]) : '';
    $national_raw = isset($source[$national_key]) ? wp_unslash((string) $source[$national_key]) : '';

    $canonical = peracrm_phone_canonical_from_components($country_raw, $national_raw);
    if ($canonical !== '') {
        return $canonical;
    }

    $legacy_raw = isset($source[$legacy_key]) ? sanitize_text_field(wp_unslash((string) $source[$legacy_key])) : '';
    if ($legacy_raw === '') {
        return '';
    }

    return preg_replace('/[^0-9+]/', '', $legacy_raw);
}

function peracrm_phone_digits_only($phone)
{
    return preg_replace('/\D+/', '', (string) $phone);
}

function peracrm_whatsapp_url_from_phone($phone)
{
    $digits = peracrm_phone_digits_only($phone);
    if ($digits === '') {
        return '';
    }

    return 'https://wa.me/' . $digits;
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

    $phone = '';
    if (is_array($data)) {
        $phone = peracrm_phone_canonical_from_source($data, 'phone_country', 'phone_national', 'phone');
    }

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

    $bedrooms = null;
    if (array_key_exists('bedrooms', $data)) {
        $bedrooms = peracrm_client_profile_sanitize_integer_field($data['bedrooms'], 0);
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

    if (null !== $bedrooms) {
        update_post_meta($client_id, '_peracrm_bedrooms', (int) $bedrooms);
    } else {
        delete_post_meta($client_id, '_peracrm_bedrooms');
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
    return peracrm_client_profile_sanitize_integer_field($value, 0);
}

function peracrm_client_profile_sanitize_integer_field($value, $min = 0)
{
    if ($value === '' || $value === null) {
        return null;
    }

    $value = (int) $value;
    $minimum = (int) $min;
    if ($value < $minimum) {
        $value = $minimum;
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

function peracrm_render_assigned_advisor_box($client_id, array $args = [])
{
    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        echo '<p class="peracrm-empty">' . esc_html('Invalid client.') . '</p>';
        return;
    }

    if (!function_exists('peracrm_client_get_assigned_advisor_id')) {
        echo '<p class="peracrm-empty">' . esc_html('Unavailable (missing helper).') . '</p>';
        return;
    }

    $advisor_id = (int) peracrm_client_get_assigned_advisor_id($client_id);

    $advisor_name = 'Unassigned';
    $advisor_exists = true;
    $advisor_is_eligible = true;
    if ($advisor_id > 0) {
        $advisor_user = get_userdata($advisor_id);
        if ($advisor_user instanceof WP_User) {
            $advisor_name = $advisor_user->display_name;
        } else {
            $advisor_exists = false;
            $advisor_name = sprintf('User #%d', $advisor_id);
        }

        if (function_exists('peracrm_user_is_staff')) {
            $advisor_is_eligible = peracrm_user_is_staff($advisor_id);
        }
    }

    $can_reassign = current_user_can('edit_post', $client_id)
        && (current_user_can('manage_options') || current_user_can('peracrm_manage_assignments'));

    $redirect_url = isset($args['redirect']) ? (string) $args['redirect'] : '';

    echo '<div class="peracrm-metabox">';
    if ($advisor_id > 0 && !$advisor_is_eligible) {
        $advisor_name .= ' (not eligible)';
    }
    echo '<p><strong>Current advisor:</strong> ' . esc_html($advisor_name) . '</p>';
    if ($advisor_id > 0 && !$advisor_exists) {
        echo '<p class="peracrm-empty">' . esc_html('Warning: assigned advisor account no longer exists.') . '</p>';
    }

    if (!$can_reassign) {
        echo '<p>' . esc_html('You do not have permission to reassign advisors.') . '</p>';
        echo '</div>';
        return;
    }

    $advisor_options = function_exists('peracrm_get_staff_users')
        ? peracrm_get_staff_users()
        : [];

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="peracrm-form">';
    echo '<input type="hidden" name="action" value="peracrm_reassign_client_advisor" />';
    echo '<input type="hidden" name="peracrm_reassign_client_advisor_nonce" value="' . esc_attr(wp_create_nonce('peracrm_reassign_client_advisor')) . '" />';
    echo '<input type="hidden" name="peracrm_client_id" value="' . esc_attr($client_id) . '" />';
    if ($redirect_url !== '') {
        echo '<input type="hidden" name="peracrm_redirect" value="' . esc_url($redirect_url) . '" />';
    }
    echo '<p><label for="peracrm-assigned-advisor-' . esc_attr((string) $client_id) . '">Advisor</label></p>';
    echo '<p><select name="peracrm_assigned_advisor" id="peracrm-assigned-advisor-' . esc_attr((string) $client_id) . '" class="widefat">';
    printf(
        '<option value="0"%s>%s</option>',
        selected($advisor_id, 0, false),
        esc_html('Unassigned')
    );
    if (empty($advisor_options)) {
        echo '<option value="" disabled>' . esc_html('No employees found') . '</option>';
    }
    foreach ($advisor_options as $advisor) {
        if (!$advisor instanceof WP_User) {
            continue;
        }

        printf(
            '<option value="%1$d"%2$s>%3$s</option>',
            (int) $advisor->ID,
            selected($advisor_id, (int) $advisor->ID, false),
            esc_html($advisor->display_name)
        );
    }
    echo '</select></p>';
    echo '<p><button type="submit" class="button">' . esc_html('Reassign') . '</button></p>';
    echo '</form>';
    echo '</div>';
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
