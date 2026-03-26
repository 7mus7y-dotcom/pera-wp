<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_facebook_leads_external_meta_key()
{
    return '_peracrm_external_facebook_lead_id';
}

function peracrm_facebook_leads_claims_table()
{
    return peracrm_table('peracrm_facebook_lead_claims');
}

function peracrm_facebook_leads_ensure_claims_table()
{
    if (function_exists('peracrm_create_facebook_lead_claims_table')) {
        peracrm_create_facebook_lead_claims_table();
    }
}

function peracrm_facebook_leads_claim_stale_timeout_seconds()
{
    $timeout = (int) apply_filters('peracrm_facebook_leads_claim_stale_timeout_seconds', 300);
    return $timeout > 0 ? $timeout : 300;
}

function peracrm_facebook_leads_find_client_id_by_external_lead_id($facebook_lead_id)
{
    $facebook_lead_id = sanitize_text_field((string) $facebook_lead_id);
    if ($facebook_lead_id === '') {
        return 0;
    }

    $finder = static function () use ($facebook_lead_id) {
        global $wpdb;

        peracrm_facebook_leads_ensure_claims_table();
        $claims_table = peracrm_facebook_leads_claims_table();
        $claimed_client_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM {$claims_table} WHERE facebook_lead_id = %s LIMIT 1",
            $facebook_lead_id
        ));
        if ($claimed_client_id > 0) {
            return $claimed_client_id;
        }

        $found = get_posts([
            'post_type' => 'crm_client',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => peracrm_facebook_leads_external_meta_key(),
                    'value' => $facebook_lead_id,
                    'compare' => '=',
                ],
            ],
            'no_found_rows' => true,
        ]);

        return !empty($found) ? (int) $found[0] : 0;
    };

    if (function_exists('peracrm_with_target_blog')) {
        return (int) peracrm_with_target_blog($finder);
    }

    return (int) $finder();
}

function peracrm_facebook_leads_save_external_lead_id($client_id, $facebook_lead_id)
{
    $client_id = (int) $client_id;
    $facebook_lead_id = sanitize_text_field((string) $facebook_lead_id);

    if ($client_id <= 0 || $facebook_lead_id === '') {
        return;
    }

    $writer = static function () use ($client_id, $facebook_lead_id) {
        update_post_meta($client_id, peracrm_facebook_leads_external_meta_key(), $facebook_lead_id);
    };

    if (function_exists('peracrm_with_target_blog')) {
        peracrm_with_target_blog($writer);
        return;
    }

    $writer();
}

function peracrm_facebook_leads_acquire_external_lead_id_claim($facebook_lead_id)
{
    $facebook_lead_id = sanitize_text_field((string) $facebook_lead_id);
    if ($facebook_lead_id === '') {
        return [
            'ok' => false,
            'acquired' => false,
            'existing_client_id' => 0,
        ];
    }

    $claimer = static function () use ($facebook_lead_id) {
        global $wpdb;

        peracrm_facebook_leads_ensure_claims_table();
        $table = peracrm_facebook_leads_claims_table();
        $now = peracrm_now_mysql();
        $stale_timeout = peracrm_facebook_leads_claim_stale_timeout_seconds();

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (facebook_lead_id, client_id, claimed_at, updated_at)
             VALUES (%s, NULL, %s, %s)",
            $facebook_lead_id,
            $now,
            $now
        ));

        if ((int) $inserted > 0) {
            return [
                'ok' => true,
                'acquired' => true,
                'existing_client_id' => 0,
            ];
        }

        $existing = (array) $wpdb->get_row($wpdb->prepare(
            "SELECT client_id, UNIX_TIMESTAMP(COALESCE(updated_at, claimed_at)) AS claim_ts
             FROM {$table}
             WHERE facebook_lead_id = %s
             LIMIT 1",
            $facebook_lead_id
        ), ARRAY_A);
        $existing_client_id = isset($existing['client_id']) ? (int) $existing['client_id'] : 0;

        if ($existing_client_id <= 0) {
            $reclaimed = $wpdb->query($wpdb->prepare(
                "UPDATE {$table}
                 SET claimed_at = %s, updated_at = %s
                 WHERE facebook_lead_id = %s
                   AND (client_id IS NULL OR client_id = 0)
                   AND COALESCE(updated_at, claimed_at) <= DATE_SUB(%s, INTERVAL %d SECOND)",
                $now,
                $now,
                $facebook_lead_id,
                $now,
                $stale_timeout
            ));

            if ((int) $reclaimed > 0) {
                peracrm_facebook_leads_log_info('Reclaimed stale unbound Facebook lead claim', [
                    'facebook_lead_id' => $facebook_lead_id,
                    'stale_timeout_seconds' => $stale_timeout,
                ]);
                return [
                    'ok' => true,
                    'acquired' => true,
                    'existing_client_id' => 0,
                ];
            }

            $claim_ts = isset($existing['claim_ts']) ? (int) $existing['claim_ts'] : 0;
            if ($claim_ts > 0) {
                $claim_age_seconds = max(0, time() - $claim_ts);
                peracrm_facebook_leads_log_debug('Facebook lead claim exists but is not stale enough to reclaim', [
                    'facebook_lead_id' => $facebook_lead_id,
                    'claim_age_seconds' => $claim_age_seconds,
                    'stale_timeout_seconds' => $stale_timeout,
                ]);
            }
        }

        return [
            'ok' => true,
            'acquired' => false,
            'existing_client_id' => $existing_client_id,
        ];
    };

    if (function_exists('peracrm_with_target_blog')) {
        return (array) peracrm_with_target_blog($claimer);
    }

    return (array) $claimer();
}

function peracrm_facebook_leads_release_external_lead_id_claim($facebook_lead_id)
{
    $facebook_lead_id = sanitize_text_field((string) $facebook_lead_id);
    if ($facebook_lead_id === '') {
        return false;
    }

    $releaser = static function () use ($facebook_lead_id) {
        global $wpdb;
        peracrm_facebook_leads_ensure_claims_table();
        $table = peracrm_facebook_leads_claims_table();
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE facebook_lead_id = %s AND (client_id IS NULL OR client_id = 0)",
            $facebook_lead_id
        ));

        return (int) $deleted > 0;
    };

    if (function_exists('peracrm_with_target_blog')) {
        return (bool) peracrm_with_target_blog($releaser);
    }

    return (bool) $releaser();
}

function peracrm_facebook_leads_bind_external_lead_id_claim($client_id, $facebook_lead_id)
{
    $client_id = (int) $client_id;
    $facebook_lead_id = sanitize_text_field((string) $facebook_lead_id);
    if ($client_id <= 0 || $facebook_lead_id === '') {
        return [
            'ok' => false,
            'existing_client_id' => 0,
        ];
    }

    $claimer = static function () use ($client_id, $facebook_lead_id) {
        global $wpdb;
        peracrm_facebook_leads_ensure_claims_table();
        $table = peracrm_facebook_leads_claims_table();

        $current_client_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM {$table} WHERE facebook_lead_id = %s LIMIT 1",
            $facebook_lead_id
        ));

        if ($current_client_id > 0 && $current_client_id !== $client_id) {
            return [
                'ok' => false,
                'existing_client_id' => $current_client_id,
            ];
        }

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET client_id = %d, updated_at = %s
             WHERE facebook_lead_id = %s AND (client_id IS NULL OR client_id = 0 OR client_id = %d)",
            $client_id,
            peracrm_now_mysql(),
            $facebook_lead_id,
            $client_id
        ));

        if ($updated !== false) {
            peracrm_facebook_leads_save_external_lead_id($client_id, $facebook_lead_id);
            return [
                'ok' => true,
                'existing_client_id' => $client_id,
            ];
        }

        return [
            'ok' => false,
            'existing_client_id' => $current_client_id,
        ];
    };

    if (function_exists('peracrm_with_target_blog')) {
        return (array) peracrm_with_target_blog($claimer);
    }

    return (array) $claimer();
}

function peracrm_facebook_leads_claim_external_lead_id($client_id, $facebook_lead_id)
{
    return peracrm_facebook_leads_bind_external_lead_id_claim($client_id, $facebook_lead_id);
}

function peracrm_facebook_leads_ingest_graph_lead(array $notification, array $graph_result)
{
    $lead = isset($graph_result['lead']) && is_array($graph_result['lead']) ? $graph_result['lead'] : [];
    $mapped = peracrm_facebook_leads_map_graph_lead_to_enquiry($lead, $notification);
    $payload = isset($mapped['payload']) && is_array($mapped['payload']) ? $mapped['payload'] : [];
    $source_meta = isset($mapped['source_meta']) && is_array($mapped['source_meta']) ? $mapped['source_meta'] : [];

    $facebook_lead_id = sanitize_text_field((string) ($source_meta['facebook_lead_id'] ?? ''));
    if ($facebook_lead_id === '') {
        peracrm_facebook_leads_log_warning('Skipping lead ingest: missing Facebook lead id', [
            'page_id' => (string) ($notification['page_id'] ?? ''),
            'form_id' => (string) ($notification['form_id'] ?? ''),
            'graph_http_status' => (int) ($graph_result['http_status'] ?? 0),
        ]);

        return [
            'ok' => true,
            'status' => 'skipped_missing_external_id',
            'client_id' => 0,
        ];
    }

    $claim = peracrm_facebook_leads_acquire_external_lead_id_claim($facebook_lead_id);
    if (empty($claim['ok']) || empty($claim['acquired'])) {
        $existing_client_id = (int) ($claim['existing_client_id'] ?? 0);
        peracrm_facebook_leads_log_info('Skipping duplicate Facebook lead replay', [
            'facebook_lead_id' => $facebook_lead_id,
            'client_id' => $existing_client_id,
            'facebook_form_id' => (string) ($source_meta['facebook_form_id'] ?? ''),
            'facebook_ad_id' => (string) ($source_meta['facebook_ad_id'] ?? ''),
        ]);

        return [
            'ok' => true,
            'status' => 'duplicate_external_id',
            'client_id' => $existing_client_id,
        ];
    }

    $email = sanitize_email((string) ($payload['email'] ?? ''));
    if ($email !== '' && !is_email($email)) {
        $email = '';
    }
    $phone = isset($payload['phone']) ? sanitize_text_field((string) $payload['phone']) : '';
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (!is_string($phone)) {
        $phone = '';
    }

    if ($email === '' && $phone === '') {
        peracrm_facebook_leads_log_warning('Skipping lead ingest: mapping incomplete (email/phone missing)', [
            'facebook_lead_id' => $facebook_lead_id,
            'has_phone' => 0,
            'has_name' => (!empty($payload['name']) || !empty($payload['first_name']) || !empty($payload['last_name'])) ? 1 : 0,
        ]);

        peracrm_facebook_leads_release_external_lead_id_claim($facebook_lead_id);

        return [
            'ok' => true,
            'status' => 'skipped_incomplete_mapping',
            'client_id' => 0,
        ];
    }

    $payload['email'] = $email;
    $payload['phone'] = $phone;
    $payload['raw_fields'] = isset($payload['raw_fields']) && is_array($payload['raw_fields']) ? $payload['raw_fields'] : [];

    $context = [
        'handler' => 'facebook_lead_ads',
        'form_id' => (string) ($source_meta['facebook_form_id'] ?? ''),
        'form_name' => (string) ($source_meta['facebook_form_name'] ?? ''),
        'current_blog_id' => (int) get_current_blog_id(),
        'target_blog_id' => function_exists('peracrm_get_target_blog_id') ? (int) peracrm_get_target_blog_id() : 0,
        'site_url' => esc_url_raw((string) home_url('/')),
        'referrer' => '',
    ];

    $client_id = function_exists('peracrm_ingest_enquiry')
        ? (int) peracrm_ingest_enquiry($payload, $context)
        : 0;

    if ($client_id <= 0) {
        peracrm_facebook_leads_log_warning('Facebook lead ingest did not create/update a client', [
            'facebook_lead_id' => $facebook_lead_id,
            'facebook_form_id' => (string) ($source_meta['facebook_form_id'] ?? ''),
            'facebook_ad_id' => (string) ($source_meta['facebook_ad_id'] ?? ''),
        ]);

        peracrm_facebook_leads_release_external_lead_id_claim($facebook_lead_id);

        return [
            'ok' => true,
            'status' => 'ingest_no_client',
            'client_id' => 0,
        ];
    }

    $bound = peracrm_facebook_leads_bind_external_lead_id_claim($client_id, $facebook_lead_id);
    if (empty($bound['ok'])) {
        $existing_claim_client_id = (int) ($bound['existing_client_id'] ?? 0);
        if ($existing_claim_client_id <= 0) {
            peracrm_facebook_leads_release_external_lead_id_claim($facebook_lead_id);
        }
        peracrm_facebook_leads_log_warning('Facebook lead ingested but external lead id claim failed', [
            'facebook_lead_id' => $facebook_lead_id,
            'client_id' => $client_id,
            'existing_client_id' => $existing_claim_client_id,
            'facebook_form_id' => (string) ($source_meta['facebook_form_id'] ?? ''),
            'facebook_ad_id' => (string) ($source_meta['facebook_ad_id'] ?? ''),
        ]);

        return [
            'ok' => true,
            'status' => 'duplicate_external_id_race',
            'client_id' => $existing_claim_client_id > 0 ? $existing_claim_client_id : $client_id,
        ];
    }

    peracrm_facebook_leads_log_info('Facebook lead ingested into CRM', [
        'facebook_lead_id' => $facebook_lead_id,
        'client_id' => $client_id,
        'facebook_form_id' => (string) ($source_meta['facebook_form_id'] ?? ''),
        'facebook_ad_id' => (string) ($source_meta['facebook_ad_id'] ?? ''),
    ]);

    return [
        'ok' => true,
        'status' => 'ingested',
        'client_id' => $client_id,
    ];
}
