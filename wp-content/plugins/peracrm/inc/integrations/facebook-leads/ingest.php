<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_facebook_leads_external_meta_key()
{
    return '_peracrm_external_facebook_lead_id';
}

function peracrm_facebook_leads_find_client_id_by_external_lead_id($facebook_lead_id)
{
    $facebook_lead_id = sanitize_text_field((string) $facebook_lead_id);
    if ($facebook_lead_id === '') {
        return 0;
    }

    $finder = static function () use ($facebook_lead_id) {
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

function peracrm_facebook_leads_claim_external_lead_id($client_id, $facebook_lead_id)
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
        $existing_client_id = peracrm_facebook_leads_find_client_id_by_external_lead_id($facebook_lead_id);
        if ($existing_client_id > 0 && $existing_client_id !== $client_id) {
            return [
                'ok' => false,
                'existing_client_id' => $existing_client_id,
            ];
        }

        if ($existing_client_id === $client_id) {
            return [
                'ok' => true,
                'existing_client_id' => $client_id,
            ];
        }

        $saved = add_post_meta($client_id, peracrm_facebook_leads_external_meta_key(), $facebook_lead_id, true);
        if ($saved) {
            return [
                'ok' => true,
                'existing_client_id' => $client_id,
            ];
        }

        update_post_meta($client_id, peracrm_facebook_leads_external_meta_key(), $facebook_lead_id);

        $current_client_id = peracrm_facebook_leads_find_client_id_by_external_lead_id($facebook_lead_id);
        return [
            'ok' => $current_client_id === $client_id,
            'existing_client_id' => $current_client_id,
        ];
    };

    if (function_exists('peracrm_with_target_blog')) {
        return (array) peracrm_with_target_blog($claimer);
    }

    return (array) $claimer();
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

    $existing_client_id = peracrm_facebook_leads_find_client_id_by_external_lead_id($facebook_lead_id);
    if ($existing_client_id > 0) {
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
    if (!is_email($email)) {
        peracrm_facebook_leads_log_warning('Skipping lead ingest: mapping incomplete (email missing/invalid)', [
            'facebook_lead_id' => $facebook_lead_id,
            'has_phone' => !empty($payload['phone']) ? 1 : 0,
            'has_name' => (!empty($payload['name']) || !empty($payload['first_name']) || !empty($payload['last_name'])) ? 1 : 0,
        ]);

        return [
            'ok' => true,
            'status' => 'skipped_incomplete_mapping',
            'client_id' => 0,
        ];
    }

    $payload['email'] = $email;
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

        return [
            'ok' => true,
            'status' => 'ingest_no_client',
            'client_id' => 0,
        ];
    }

    $claim = peracrm_facebook_leads_claim_external_lead_id($client_id, $facebook_lead_id);
    if (empty($claim['ok'])) {
        $existing_claim_client_id = (int) ($claim['existing_client_id'] ?? 0);
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
