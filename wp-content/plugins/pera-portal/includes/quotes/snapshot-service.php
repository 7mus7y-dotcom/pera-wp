<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_quote_get_field($field, $post_id)
{
    return function_exists('get_field') ? get_field($field, $post_id) : get_post_meta($post_id, $field, true);
}

function pera_portal_quote_resolve_floor_svg_markup($floor_id)
{
    $file = pera_portal_quote_get_field('floor_svg', $floor_id);
    $svg_path = '';

    if (is_array($file) && !empty($file['ID'])) {
        $svg_path = (string) get_attached_file((int) $file['ID']);
    } elseif (is_numeric($file)) {
        $svg_path = (string) get_attached_file((int) $file);
    }

    if ($svg_path === '' || !file_exists($svg_path)) {
        return new WP_Error('pera_portal_quote_floor_svg_missing', __('Floor SVG is required to create a quote.', 'pera-portal'));
    }

    $svg_markup = (string) file_get_contents($svg_path);
    $sanitized = pera_portal_quote_sanitize_svg_markup($svg_markup);

    if ($sanitized === '') {
        return new WP_Error('pera_portal_quote_floor_svg_invalid', __('Floor SVG could not be sanitized for quote snapshot.', 'pera-portal'));
    }

    return $sanitized;
}

function pera_portal_quote_get_floor_unit_codes($floor_id)
{
    $floor_id = absint($floor_id);
    if ($floor_id <= 0) {
        return [];
    }

    $query = new WP_Query([
        'post_type' => 'pera_unit',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => 'floor',
                'value' => (string) $floor_id,
                'compare' => '=',
            ],
        ],
    ]);

    $codes = [];

    foreach ($query->posts as $unit_id) {
        $raw_code = pera_portal_quote_get_field('unit_code', $unit_id);
        $code = sanitize_text_field((string) $raw_code);
        if ($code === '') {
            continue;
        }

        $codes[$code] = $code;
    }

    return array_values($codes);
}

function pera_portal_quote_build_snapshot($unit_id, array $request)
{
    $unit = get_post($unit_id);
    if (!($unit instanceof WP_Post) || $unit->post_type !== 'pera_unit') {
        return new WP_Error('pera_portal_quote_invalid_unit', __('Invalid unit selected.', 'pera-portal'), ['status' => 400]);
    }

    $floor_id = absint(pera_portal_quote_get_field('floor', $unit_id));
    $floor = $floor_id > 0 ? get_post($floor_id) : null;

    if (!($floor instanceof WP_Post) || $floor->post_type !== 'pera_floor') {
        return new WP_Error('pera_portal_quote_invalid_floor', __('Selected unit is missing a floor relation.', 'pera-portal'), ['status' => 400]);
    }

    $building_id = absint(pera_portal_quote_get_field('building', $floor_id));
    $building = $building_id > 0 ? get_post($building_id) : null;

    if (!($building instanceof WP_Post) || $building->post_type !== 'pera_building') {
        return new WP_Error('pera_portal_quote_invalid_building', __('Selected unit floor is missing building relation.', 'pera-portal'), ['status' => 400]);
    }

    $now_gmt = gmdate('Y-m-d H:i:s');
    $expires_gmt = get_gmt_from_date((string) ($request['expires_at'] ?? ''));

    if ($expires_gmt === '' || strtotime($expires_gmt . ' GMT') <= time()) {
        return new WP_Error('pera_portal_quote_invalid_expiry', __('Expiry date/time must be in the future.', 'pera-portal'), ['status' => 400]);
    }

    $price = is_numeric($request['quoted_price'] ?? null) ? (float) $request['quoted_price'] : null;
    if ($price === null || $price <= 0) {
        return new WP_Error('pera_portal_quote_invalid_price', __('Quoted price must be greater than zero.', 'pera-portal'), ['status' => 400]);
    }

    $currency = sanitize_text_field((string) ($request['currency'] ?? 'GBP'));
    if ($currency === '') {
        $currency = 'GBP';
    }

    $floor_svg = pera_portal_quote_resolve_floor_svg_markup($floor_id);
    if (is_wp_error($floor_svg)) {
        return $floor_svg;
    }

    $floor_unit_codes = pera_portal_quote_get_floor_unit_codes($floor_id);

    $plan = pera_portal_quote_get_field('unit_detail_plan', $unit_id);
    $source_attachment_id = is_array($plan) && !empty($plan['ID']) ? (int) $plan['ID'] : (is_numeric($plan) ? (int) $plan : 0);
    $copied_attachment_id = pera_portal_quote_copy_attachment($source_attachment_id, 'quote-apartment-plan');

    $snapshot = [
        'payload_version' => 1,
        'reference' => '',
        'building_title' => wp_strip_all_tags((string) get_the_title($building_id), true),
        'floor_label' => sanitize_text_field((string) pera_portal_quote_get_field('floor_number', $floor_id)),
        'unit_code' => sanitize_text_field((string) pera_portal_quote_get_field('unit_code', $unit_id)),
        'unit_type' => sanitize_text_field((string) pera_portal_quote_get_field('unit_type', $unit_id)),
        'floor_unit_codes' => $floor_unit_codes,
        'net_size' => pera_portal_quote_get_field('net_size', $unit_id),
        'gross_size' => pera_portal_quote_get_field('gross_size', $unit_id),
        'price' => $price,
        'currency' => $currency,
        'unit_status' => sanitize_key((string) pera_portal_quote_get_field('status', $unit_id)),
        'consultant_note' => sanitize_textarea_field((string) ($request['consultant_note'] ?? '')),
        'issued_gmt' => $now_gmt,
        'expires_gmt' => $expires_gmt,
        'issued_by' => sanitize_text_field(wp_get_current_user()->display_name),
        'client_name' => sanitize_text_field((string) ($request['client_name'] ?? '')),
        'client_email' => sanitize_email((string) ($request['client_email'] ?? '')),
        'client_phone' => sanitize_text_field((string) ($request['client_phone'] ?? '')),
        'crm_client_id' => sanitize_text_field((string) ($request['crm_client_id'] ?? '')),
        'crm_deal_id' => sanitize_text_field((string) ($request['crm_deal_id'] ?? '')),
        'crm_note' => sanitize_textarea_field((string) ($request['crm_note'] ?? '')),
        'source_context' => sanitize_text_field((string) ($request['source_context'] ?? 'portal')),
        'source_channel' => sanitize_text_field((string) ($request['source_channel'] ?? '')),
        'disclaimer' => __('This quote is a frozen snapshot and does not guarantee ongoing availability.', 'pera-portal'),
    ];

    return [
        'snapshot' => $snapshot,
        'source' => [
            'building_id' => $building_id,
            'floor_id' => $floor_id,
            'unit_id' => $unit_id,
        ],
        'floor_svg' => $floor_svg,
        'apartment_plan_attachment_id' => $copied_attachment_id,
    ];
}
