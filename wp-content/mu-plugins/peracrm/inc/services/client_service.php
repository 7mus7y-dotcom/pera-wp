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


function peracrm_find_existing_client_id_by_email($email)
{
    $email = sanitize_email($email);
    if ($email === '') {
        return 0;
    }

    $normalized_email = function_exists('peracrm_normalize_email')
        ? peracrm_normalize_email($email)
        : strtolower(trim((string) $email));

    $meta_keys = [
        '_peracrm_email',
        'crm_primary_email',
        'primary_email',
    ];

    foreach ($meta_keys as $meta_key) {
        $existing = get_posts([
            'post_type' => 'crm_client',
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => $meta_key,
                    'value' => [$email, $normalized_email],
                    'compare' => 'IN',
                ],
            ],
        ]);

        if (!empty($existing)) {
            return (int) $existing[0];
        }
    }

    if ($normalized_email !== '') {
        $normalized_keys = [
            'crm_primary_email_normalized',
            'primary_email_normalized',
        ];

        foreach ($normalized_keys as $meta_key) {
            $existing = get_posts([
                'post_type' => 'crm_client',
                'posts_per_page' => 1,
                'post_status' => 'any',
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => $meta_key,
                        'value' => $normalized_email,
                        'compare' => '=',
                    ],
                ],
            ]);

            if (!empty($existing)) {
                return (int) $existing[0];
            }
        }
    }

    return 0;
}

function peracrm_find_all_client_ids_by_email($email)
{
    $email = sanitize_email($email);
    if ($email === '') {
        return [];
    }

    $normalized_email = function_exists('peracrm_normalize_email')
        ? peracrm_normalize_email($email)
        : strtolower(trim((string) $email));

    $meta_query = [
        'relation' => 'OR',
        [
            'key' => 'crm_primary_email',
            'value' => [$email, $normalized_email],
            'compare' => 'IN',
        ],
        [
            'key' => 'primary_email',
            'value' => [$email, $normalized_email],
            'compare' => 'IN',
        ],
    ];

    if ($normalized_email !== '') {
        $meta_query[] = [
            'key' => 'crm_primary_email_normalized',
            'value' => $normalized_email,
            'compare' => '=',
        ];
        $meta_query[] = [
            'key' => 'primary_email_normalized',
            'value' => $normalized_email,
            'compare' => '=',
        ];
    }

    $matches = get_posts([
        'post_type' => 'crm_client',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => $meta_query,
    ]);

    $match_ids = array_values(array_unique(array_map('intval', (array) $matches)));
    usort($match_ids, static function ($left, $right) {
        $left_status = (string) get_post_status((int) $left);
        $right_status = (string) get_post_status((int) $right);

        $left_priority = 2;
        if ($left_status === 'publish') {
            $left_priority = 0;
        } elseif ($left_status !== 'trash') {
            $left_priority = 1;
        }

        $right_priority = 2;
        if ($right_status === 'publish') {
            $right_priority = 0;
        } elseif ($right_status !== 'trash') {
            $right_priority = 1;
        }

        if ($left_priority !== $right_priority) {
            return $left_priority <=> $right_priority;
        }

        return ((int) $right) <=> ((int) $left);
    });

    return $match_ids;
}

function peracrm_client_ingest_debug_log($message, array $context = [])
{
    if (function_exists('peracrm_ingest_debug_log')) {
        peracrm_ingest_debug_log($message, $context);
    }
}

function peracrm_client_get_wp_user_by_email($email)
{
    $email = sanitize_email($email);
    if ($email === '') {
        return null;
    }

    $user = get_user_by('email', $email);
    return $user instanceof WP_User ? $user : null;
}

function peracrm_client_sync_user_link($client_id, $user_id)
{
    $client_id = (int) $client_id;
    $user_id = (int) $user_id;

    if ($client_id <= 0 || $user_id <= 0) {
        return;
    }

    update_user_meta($user_id, 'crm_client_id', $client_id);

    if (function_exists('peracrm_update_client_linked_user_id')) {
        peracrm_update_client_linked_user_id($client_id, $user_id);
    } else {
        update_post_meta($client_id, 'linked_user_id', $user_id);
    }
}

function peracrm_client_ensure_membership_for_user($user_id, $email)
{
    $user_id = (int) $user_id;
    $target_blog_id = (int) peracrm_get_target_blog_id();
    $is_member = ($user_id > 0 && $target_blog_id > 0)
        ? (bool) is_user_member_of_blog($user_id, $target_blog_id)
        : false;

    peracrm_client_ingest_debug_log('existing wp user for enquiry email', [
        'email' => $email,
        'user_id' => $user_id,
        'is_member_of_target_blog' => $is_member ? 1 : 0,
        'target_blog_id' => $target_blog_id,
        'current_blog_id' => (int) get_current_blog_id(),
    ]);

    if ($user_id <= 0 || $target_blog_id <= 0 || !is_multisite()) {
        return;
    }

    if (function_exists('peracrm_membership_ensure_lead_on_target_blog')) {
        $membership = peracrm_membership_ensure_lead_on_target_blog($user_id, 'enquiry_ingest');
        if (empty($membership['ok']) && !empty($membership['membership_error'])) {
            peracrm_client_ingest_debug_log('target blog membership failed during ingest', [
                'email' => $email,
                'user_id' => $user_id,
                'target_blog_id' => $target_blog_id,
                'membership_error' => (string) $membership['membership_error'],
            ]);
        }
        return;
    }

    if (!$is_member) {
        $added = add_user_to_blog($target_blog_id, $user_id, 'lead');
        if (is_wp_error($added)) {
            peracrm_client_ingest_debug_log('target blog membership failed during ingest', [
                'email' => $email,
                'user_id' => $user_id,
                'target_blog_id' => $target_blog_id,
                'membership_error' => $added->get_error_message(),
            ]);
            return;
        }
    }

    peracrm_with_target_blog(static function () use ($user_id) {
        $target_user = new WP_User($user_id);
        $target_roles = array_map('strval', (array) $target_user->roles);
        if (!in_array('lead', $target_roles, true)) {
            $target_user->set_role('lead');
        }
    });
}

function peracrm_client_ensure_published($client_id, array $context = [])
{
    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        return false;
    }

    $client = get_post($client_id);
    if (!$client instanceof WP_Post || $client->post_type !== 'crm_client') {
        return false;
    }

    $old_status = (string) get_post_status($client_id);
    if ($old_status === 'publish') {
        return true;
    }

    $target_blog_id = (int) peracrm_get_target_blog_id();
    $email = isset($context['email']) ? sanitize_email((string) $context['email']) : '';

    peracrm_client_ingest_debug_log('existing client found but not published; reviving', [
        'client_id' => $client_id,
        'email' => $email,
        'old_status' => $old_status,
        'target_blog_id' => $target_blog_id,
        'current_blog_id' => (int) get_current_blog_id(),
    ]);

    return (bool) peracrm_with_target_blog(static function () use ($client_id, $old_status, $email, $target_blog_id) {
        if ($old_status === 'trash' && function_exists('wp_untrash_post')) {
            wp_untrash_post($client_id);
        }

        wp_update_post([
            'ID' => $client_id,
            'post_status' => 'publish',
        ]);

        $new_status = (string) get_post_status($client_id);

        peracrm_client_ingest_debug_log('client revive status result', [
            'client_id' => $client_id,
            'email' => $email,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'target_blog_id' => $target_blog_id,
            'current_blog_id' => (int) get_current_blog_id(),
        ]);

        return $new_status === 'publish';
    });
}

function peracrm_find_or_create_client_by_email($email, array $data = [])
{
    $email = sanitize_email($email);
    if ($email === '') {
        return 0;
    }

    return (int) peracrm_with_target_blog(static function () use ($email, $data) {
        $wp_user = peracrm_client_get_wp_user_by_email($email);
        if ($wp_user instanceof WP_User) {
            peracrm_client_ensure_membership_for_user((int) $wp_user->ID, $email);
        }

        $matching_client_ids = peracrm_find_all_client_ids_by_email($email);
        if (empty($matching_client_ids)) {
            $existing_id = 0;
            if (function_exists('peracrm_find_client_by_email')) {
                $existing_id = (int) peracrm_find_client_by_email($email);
                if ($existing_id > 0) {
                    $matching_client_ids = [$existing_id];
                }
            }

            if ($existing_id <= 0) {
                $existing_id = (int) peracrm_find_existing_client_id_by_email($email);
                if ($existing_id > 0) {
                    $matching_client_ids = [$existing_id];
                }
            }
        }

        if (!empty($matching_client_ids)) {
            $canonical_client_id = (int) $matching_client_ids[0];
            $trashed_count = 0;

            peracrm_client_ensure_published($canonical_client_id, [
                'email' => $email,
                'reason' => 'ingest_existing',
            ]);

            if (count($matching_client_ids) > 1) {
                foreach (array_slice($matching_client_ids, 1) as $duplicate_client_id) {
                    $duplicate_client_id = (int) $duplicate_client_id;
                    if ($duplicate_client_id <= 0 || $duplicate_client_id === $canonical_client_id) {
                        continue;
                    }

                    update_post_meta($duplicate_client_id, 'peracrm_dedupe_canonical_id', $canonical_client_id);

                    if (get_post_status($duplicate_client_id) !== 'trash') {
                        wp_trash_post($duplicate_client_id);
                        $trashed_count++;
                    }
                }
            }

            if (defined('PERACRM_DEBUG_INGEST') && PERACRM_DEBUG_INGEST) {
                peracrm_client_ingest_debug_log('client dedupe summary', [
                    'email' => $email,
                    'matches_found' => count($matching_client_ids),
                    'canonical_client_id' => $canonical_client_id,
                    'trashed_count' => $trashed_count,
                ]);
            }

            $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
            peracrm_sync_client_contact_meta($canonical_client_id, $email, $phone);

            if ($wp_user instanceof WP_User) {
                peracrm_client_sync_user_link($canonical_client_id, (int) $wp_user->ID);
            }

            return $canonical_client_id;
        }

        if ($wp_user instanceof WP_User) {
            peracrm_client_ingest_debug_log('recreating client for existing user/email', [
                'email' => $email,
                'user_id' => (int) $wp_user->ID,
                'target_blog_id' => (int) peracrm_get_target_blog_id(),
            ]);
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

        if ($wp_user instanceof WP_User) {
            peracrm_client_sync_user_link($post_id, (int) $wp_user->ID);
        }

        peracrm_log_event($post_id, 'client_created', [
            'email' => $email,
            'source' => $source,
        ]);

        return $post_id;
    });
}
