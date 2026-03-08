<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_quote_find_by_token($token)
{
    $token = sanitize_text_field((string) $token);
    if ($token === '') {
        return null;
    }

    $query = new WP_Query([
        'post_type' => 'pera_quote',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => '_pera_quote_token',
                'value' => $token,
                'compare' => '=',
            ],
        ],
    ]);

    if (empty($query->posts[0])) {
        return null;
    }

    return get_post((int) $query->posts[0]);
}

function pera_portal_quote_get_business_status($quote_id)
{
    $status = sanitize_key((string) get_post_meta($quote_id, '_pera_quote_status', true));

    if ($status === 'revoked') {
        return 'revoked';
    }

    $expires_gmt = (string) get_post_meta($quote_id, '_pera_quote_expires_gmt', true);
    if ($expires_gmt !== '') {
        $expires_ts = strtotime($expires_gmt . ' GMT');
        if ($expires_ts !== false && $expires_ts < time()) {
            return 'expired';
        }
    }

    return $status === 'active' ? 'active' : 'active';
}

function pera_portal_quote_get_public_url($token)
{
    return home_url('/portal/quote/' . rawurlencode((string) $token) . '/');
}

function pera_portal_quote_save(array $record)
{
    $reference = isset($record['reference']) ? sanitize_text_field((string) $record['reference']) : pera_portal_quote_generate_reference();

    $post_id = wp_insert_post([
        'post_type' => 'pera_quote',
        'post_status' => 'publish',
        'post_title' => $reference,
    ], true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $meta = [
        '_pera_quote_token' => sanitize_text_field((string) ($record['token'] ?? '')),
        '_pera_quote_reference' => $reference,
        '_pera_quote_status' => sanitize_key((string) ($record['status'] ?? 'active')),
        '_pera_quote_created_gmt' => sanitize_text_field((string) ($record['created_gmt'] ?? gmdate('Y-m-d H:i:s'))),
        '_pera_quote_issued_gmt' => sanitize_text_field((string) ($record['issued_gmt'] ?? gmdate('Y-m-d H:i:s'))),
        '_pera_quote_expires_gmt' => sanitize_text_field((string) ($record['expires_gmt'] ?? '')),
        '_pera_quote_revoked_gmt' => sanitize_text_field((string) ($record['revoked_gmt'] ?? '')),
        '_pera_quote_issued_by_user_id' => absint($record['issued_by_user_id'] ?? 0),
        '_pera_quote_issued_by_name' => sanitize_text_field((string) ($record['issued_by_name'] ?? '')),
        '_pera_quote_source_building_id' => absint($record['source_building_id'] ?? 0),
        '_pera_quote_source_floor_id' => absint($record['source_floor_id'] ?? 0),
        '_pera_quote_source_unit_id' => absint($record['source_unit_id'] ?? 0),
        '_pera_quote_crm_client_id' => sanitize_text_field((string) ($record['crm_client_id'] ?? '')),
        '_pera_quote_crm_deal_id' => sanitize_text_field((string) ($record['crm_deal_id'] ?? '')),
        '_pera_quote_client_name' => sanitize_text_field((string) ($record['client_name'] ?? '')),
        '_pera_quote_client_email' => sanitize_email((string) ($record['client_email'] ?? '')),
        '_pera_quote_client_phone' => sanitize_text_field((string) ($record['client_phone'] ?? '')),
        '_pera_quote_crm_note' => sanitize_textarea_field((string) ($record['crm_note'] ?? '')),
        '_pera_quote_source_context' => sanitize_text_field((string) ($record['source_context'] ?? 'portal')),
        '_pera_quote_source_channel' => sanitize_text_field((string) ($record['source_channel'] ?? '')),
        '_pera_quote_payload_version' => 1,
        '_pera_quote_payload_v1' => wp_json_encode($record['payload'] ?? []),
        '_pera_quote_floor_plan_mode' => sanitize_key((string) ($record['floor_plan_mode'] ?? 'svg_markup')),
        '_pera_quote_floor_plan_svg' => (string) ($record['floor_plan_svg'] ?? ''),
        '_pera_quote_floor_plan_attachment_id' => absint($record['floor_plan_attachment_id'] ?? 0),
        '_pera_quote_apartment_plan_attachment_id' => absint($record['apartment_plan_attachment_id'] ?? 0),
    ];

    foreach ($meta as $key => $value) {
        update_post_meta($post_id, $key, $value);
    }

    return get_post($post_id);
}
