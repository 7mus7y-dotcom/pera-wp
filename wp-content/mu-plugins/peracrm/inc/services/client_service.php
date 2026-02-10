<?php

if (!defined('ABSPATH')) {
    exit;
}


function peracrm_sync_client_contact_meta($client_id, $email, $phone = '')
{
    $client_id = (int) $client_id;
    $email = sanitize_email($email);
    $phone = sanitize_text_field((string) $phone);

    if ($client_id <= 0 || $email === '') {
        return;
    }

    if ($phone !== '') {
        $current_phone = (string) get_post_meta($client_id, '_peracrm_phone', true);
        if ($current_phone !== $phone) {
            update_post_meta($client_id, '_peracrm_phone', $phone);
        }

        $legacy_phone = (string) get_post_meta($client_id, 'crm_phone', true);
        if ($legacy_phone !== $phone) {
            update_post_meta($client_id, 'crm_phone', $phone);
        }
    }

    $normalized_email = function_exists('peracrm_normalize_email')
        ? peracrm_normalize_email($email)
        : strtolower(trim((string) $email));

    update_post_meta($client_id, '_peracrm_email', $email);
    update_post_meta($client_id, 'crm_primary_email', $email);
    update_post_meta($client_id, 'primary_email', $email);

    if ($normalized_email !== '') {
        update_post_meta($client_id, 'crm_primary_email_normalized', $normalized_email);
        update_post_meta($client_id, 'primary_email_normalized', $normalized_email);
    }
}

function peracrm_find_or_create_client_by_email($email, array $data = [])
{
    $email = sanitize_email($email);
    if ($email === '') {
        return 0;
    }

    if (function_exists('peracrm_find_client_by_email')) {
        $existing_id = (int) peracrm_find_client_by_email($email);
        if ($existing_id > 0) {
            $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
            peracrm_sync_client_contact_meta($existing_id, $email, $phone);

            return $existing_id;
        }
    } else {
        $existing = get_posts([
            'post_type' => 'crm_client',
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'crm_primary_email',
                    'value' => $email,
                    'compare' => '=',
                ],
            ],
        ]);

        if (!empty($existing)) {
            $existing_id = (int) $existing[0];
            $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
            peracrm_sync_client_contact_meta($existing_id, $email, $phone);

            return $existing_id;
        }
    }

    $first_name = isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '';
    $last_name = isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '';
    $full_name = trim($first_name . ' ' . $last_name);
    if ($full_name === '') {
        $full_name = $email;
    }

    $post_id = wp_insert_post([
        'post_type' => 'crm_client',
        'post_title' => $full_name,
        'post_status' => 'publish',
    ], true);

    if (is_wp_error($post_id)) {
        return 0;
    }

    $post_id = (int) $post_id;

    $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
    $source = isset($data['source']) ? sanitize_key($data['source']) : 'manual';
    $status = isset($data['status']) ? sanitize_key($data['status']) : 'enquiry';
    $assigned_advisor = isset($data['assigned_advisor']) ? (int) $data['assigned_advisor'] : 0;

    $normalized_email = function_exists('peracrm_normalize_email')
        ? peracrm_normalize_email($email)
        : strtolower(trim((string) $email));

    update_post_meta($post_id, 'crm_primary_email', $email);
    update_post_meta($post_id, 'primary_email', $email);

    if ($normalized_email !== '') {
        update_post_meta($post_id, 'crm_primary_email_normalized', $normalized_email);
        update_post_meta($post_id, 'primary_email_normalized', $normalized_email);
    }

    update_post_meta($post_id, 'crm_first_name', $first_name);
    update_post_meta($post_id, 'crm_last_name', $last_name);
    update_post_meta($post_id, 'crm_phone', $phone);
    update_post_meta($post_id, 'crm_source', $source);
    update_post_meta($post_id, 'crm_status', $status);
    if ($assigned_advisor > 0) {
        update_post_meta($post_id, 'crm_assigned_advisor', $assigned_advisor);
    }

    peracrm_sync_client_contact_meta($post_id, $email, $phone);

    peracrm_log_event($post_id, 'client_created', [
        'email' => $email,
        'source' => $source,
    ]);

    return $post_id;
}
