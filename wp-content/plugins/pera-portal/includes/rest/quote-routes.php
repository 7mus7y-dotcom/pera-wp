<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_quote_user_can_create()
{
    return function_exists('pera_portal_current_user_can_create_quotes') ? pera_portal_current_user_can_create_quotes() : (function_exists('pera_portal_current_user_can_access') && pera_portal_current_user_can_access());
}

function pera_portal_rest_create_quote(WP_REST_Request $request)
{
    if (!pera_portal_quote_user_can_create()) {
        return new WP_Error('pera_portal_quote_forbidden', __('Portal access required.', 'pera-portal'), ['status' => is_user_logged_in() ? 403 : 401]);
    }

    $unit_id = absint($request->get_param('unit_id'));
    if ($unit_id <= 0) {
        return new WP_Error('pera_portal_quote_unit_required', __('A unit is required.', 'pera-portal'), ['status' => 400]);
    }

    $snapshot_result = pera_portal_quote_build_snapshot($unit_id, $request->get_params());
    if (is_wp_error($snapshot_result)) {
        return $snapshot_result;
    }

    $token = pera_portal_quote_generate_token();
    if (is_wp_error($token)) {
        return $token;
    }

    $reference = pera_portal_quote_generate_reference();
    $snapshot = $snapshot_result['snapshot'];
    $snapshot['reference'] = $reference;

    $quote_post = pera_portal_quote_save([
        'token' => $token,
        'reference' => $reference,
        'status' => 'active',
        'issued_by_user_id' => get_current_user_id(),
        'issued_by_name' => $snapshot['issued_by'],
        'created_gmt' => gmdate('Y-m-d H:i:s'),
        'issued_gmt' => $snapshot['issued_gmt'],
        'expires_gmt' => $snapshot['expires_gmt'],
        'source_building_id' => $snapshot_result['source']['building_id'],
        'source_floor_id' => $snapshot_result['source']['floor_id'],
        'source_unit_id' => $snapshot_result['source']['unit_id'],
        'crm_client_id' => $snapshot['crm_client_id'],
        'crm_deal_id' => $snapshot['crm_deal_id'],
        'client_name' => $snapshot['client_name'],
        'client_email' => $snapshot['client_email'],
        'client_phone' => $snapshot['client_phone'],
        'crm_note' => $snapshot['crm_note'],
        'source_context' => $snapshot['source_context'],
        'source_channel' => $snapshot['source_channel'],
        'payload' => $snapshot,
        'floor_plan_mode' => 'svg_markup',
        'floor_plan_svg' => $snapshot_result['floor_svg'],
        'apartment_plan_attachment_id' => $snapshot_result['apartment_plan_attachment_id'],
    ]);

    if (is_wp_error($quote_post)) {
        return $quote_post;
    }

    return rest_ensure_response([
        'quote_id' => (int) $quote_post->ID,
        'quote_reference' => $reference,
        'status' => 'active',
        'token' => $token,
        'public_url' => pera_portal_quote_get_public_url($token),
        'warning' => $snapshot_result['apartment_plan_attachment_id'] > 0 ? '' : __('Apartment plan was missing and was not included in this quote.', 'pera-portal'),
    ]);
}

function pera_portal_rest_revoke_quote(WP_REST_Request $request)
{
    if (!pera_portal_quote_user_can_create()) {
        return new WP_Error('pera_portal_quote_forbidden', __('Portal access required.', 'pera-portal'), ['status' => is_user_logged_in() ? 403 : 401]);
    }

    $quote_id = absint($request->get_param('quote_id'));
    $quote = $quote_id > 0 ? get_post($quote_id) : null;
    if (!($quote instanceof WP_Post) || $quote->post_type !== 'pera_quote') {
        return new WP_Error('pera_portal_quote_not_found', __('Quote not found.', 'pera-portal'), ['status' => 404]);
    }

    update_post_meta($quote_id, '_pera_quote_status', 'revoked');
    update_post_meta($quote_id, '_pera_quote_revoked_gmt', gmdate('Y-m-d H:i:s'));

    return rest_ensure_response(['quote_id' => $quote_id, 'status' => 'revoked']);
}

function pera_portal_register_quote_rest_routes()
{
    register_rest_route(PERA_PORTAL_REST_NAMESPACE, '/quotes', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'pera_portal_rest_create_quote',
        'permission_callback' => 'pera_portal_quote_user_can_create',
        'args' => [
            'unit_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
            'quoted_price' => ['required' => true, 'type' => 'number'],
            'currency' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'expires_at' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'consultant_note' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            'client_name' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'client_email' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            'client_phone' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'crm_client_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'crm_deal_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'crm_note' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            'source_context' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'source_channel' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    register_rest_route(PERA_PORTAL_REST_NAMESPACE, '/quotes/(?P<quote_id>[0-9]+)/revoke', [
        'methods' => WP_REST_Server::EDITABLE,
        'callback' => 'pera_portal_rest_revoke_quote',
        'permission_callback' => 'pera_portal_quote_user_can_create',
    ]);
}

add_action('rest_api_init', 'pera_portal_register_quote_rest_routes');
