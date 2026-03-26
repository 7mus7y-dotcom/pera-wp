<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_rest_register_facebook_leads_routes()
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    register_rest_route('peracrm/v1', '/facebook-leads/webhook', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'peracrm_rest_facebook_leads_verify_webhook',
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'peracrm_rest_facebook_leads_receive_webhook',
            'permission_callback' => '__return_true',
        ],
    ]);
}

function peracrm_rest_facebook_leads_verify_webhook(WP_REST_Request $request)
{
    if (!peracrm_facebook_leads_is_enabled()) {
        return new WP_Error('peracrm_facebook_leads_disabled', 'Facebook leads integration is disabled.', ['status' => 403]);
    }

    $mode = (string) $request->get_param('hub_mode');
    if ($mode === '') {
        $mode = (string) $request->get_param('hub.mode');
    }

    $verify_token = (string) $request->get_param('hub_verify_token');
    if ($verify_token === '') {
        $verify_token = (string) $request->get_param('hub.verify_token');
    }

    $challenge = (string) $request->get_param('hub_challenge');
    if ($challenge === '') {
        $challenge = (string) $request->get_param('hub.challenge');
    }

    if ($mode !== 'subscribe' || $verify_token === '' || $challenge === '') {
        peracrm_facebook_leads_log_warning('Webhook verify failed: missing or invalid challenge params', [
            'mode' => $mode,
        ]);

        return new WP_Error('peracrm_facebook_leads_bad_verify_request', 'Invalid webhook verification payload.', ['status' => 400]);
    }

    $expected_token = peracrm_facebook_leads_get_verify_token();
    if ($expected_token === '' || !hash_equals($expected_token, $verify_token)) {
        peracrm_facebook_leads_log_warning('Webhook verify failed: token mismatch', [
            'mode' => $mode,
            'verify_token' => $verify_token,
        ]);

        return new WP_Error('peracrm_facebook_leads_invalid_verify_token', 'Invalid verify token.', ['status' => 403]);
    }

    peracrm_facebook_leads_log_debug('Webhook verify succeeded', [
        'mode' => $mode,
    ]);

    return new WP_REST_Response([
        'challenge' => $challenge,
    ], 200);
}

function peracrm_rest_facebook_leads_receive_webhook(WP_REST_Request $request)
{
    if (!peracrm_facebook_leads_is_enabled()) {
        return new WP_REST_Response(['ok' => false, 'message' => 'disabled'], 403);
    }

    $payload = $request->get_json_params();
    if (!is_array($payload)) {
        peracrm_facebook_leads_log_warning('Webhook payload rejected: invalid JSON', []);

        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_json'], 400);
    }

    if (($payload['object'] ?? '') !== 'page') {
        peracrm_facebook_leads_log_debug('Webhook payload ignored: unexpected object', [
            'object' => isset($payload['object']) ? (string) $payload['object'] : '',
        ]);

        return new WP_REST_Response(['ok' => true, 'ignored' => true], 202);
    }

    $signature = peracrm_facebook_leads_extract_signature_stub($request, $payload);
    if (!empty($signature['present']) && empty($signature['verified'])) {
        peracrm_facebook_leads_log_debug('Webhook signature header present but verification is not enforced in this slice', [
            'signature_stub' => $signature,
        ]);
    }

    $notifications = peracrm_facebook_leads_extract_lead_notifications($payload);
    if (empty($notifications)) {
        peracrm_facebook_leads_log_info('Webhook payload acknowledged but no actionable leadgen notification found', [
            'entry_count' => isset($payload['entry']) && is_array($payload['entry']) ? count($payload['entry']) : 0,
        ]);

        return new WP_REST_Response([
            'ok' => true,
            'ignored' => true,
            'reason' => 'no_actionable_leadgen_id',
        ], 202);
    }

    $retrieved = 0;
    $failed = 0;

    foreach ($notifications as $notification) {
        $leadgen_id = (string) ($notification['leadgen_id'] ?? '');
        if ($leadgen_id === '') {
            continue;
        }

        $result = peracrm_facebook_leads_graph_get_lead($leadgen_id);
        if (!empty($result['ok'])) {
            $retrieved++;
            $lead = is_array($result['lead'] ?? null) ? $result['lead'] : [];
            peracrm_facebook_leads_log_info('Leadgen notification retrieved from Graph', [
                'leadgen_id' => $leadgen_id,
                'page_id' => (string) ($notification['page_id'] ?? ''),
                'form_id' => (string) ($notification['form_id'] ?? ''),
                'event_time' => (string) ($notification['event_time'] ?? ''),
                'created_time' => (string) ($lead['created_time'] ?? ''),
                'field_count' => isset($lead['field_data']) && is_array($lead['field_data']) ? count($lead['field_data']) : 0,
                'graph_http_status' => (int) ($result['http_status'] ?? 0),
                'graph_raw' => isset($result['raw']) && is_array($result['raw']) ? $result['raw'] : [],
            ]);
            continue;
        }

        $failed++;
        peracrm_facebook_leads_log_warning('Leadgen notification Graph retrieval failed', [
            'leadgen_id' => $leadgen_id,
            'page_id' => (string) ($notification['page_id'] ?? ''),
            'form_id' => (string) ($notification['form_id'] ?? ''),
            'event_time' => (string) ($notification['event_time'] ?? ''),
            'graph_http_status' => (int) ($result['http_status'] ?? 0),
            'graph_error_code' => (string) ($result['error_code'] ?? ''),
            'graph_error_message' => (string) ($result['error_message'] ?? ''),
            'graph_raw' => isset($result['raw']) && is_array($result['raw']) ? $result['raw'] : [],
        ]);
    }

    return new WP_REST_Response([
        'ok' => true,
        'received' => true,
        'notifications' => count($notifications),
        'retrieved' => $retrieved,
        'failed' => $failed,
    ], 200);
}

function peracrm_facebook_leads_extract_lead_notifications(array $payload)
{
    $notifications = [];

    $entries = $payload['entry'] ?? [];
    if (!is_array($entries)) {
        return $notifications;
    }

    foreach ($entries as $entry_index => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $entry_page_id = isset($entry['id']) ? sanitize_text_field((string) $entry['id']) : '';
        $entry_time = isset($entry['time']) ? (int) $entry['time'] : 0;

        $changes = $entry['changes'] ?? [];
        if (!is_array($changes)) {
            continue;
        }

        foreach ($changes as $change_index => $change) {
            if (!is_array($change)) {
                continue;
            }

            $field = isset($change['field']) ? sanitize_key((string) $change['field']) : '';
            if ($field !== '' && $field !== 'leadgen') {
                continue;
            }

            $value = $change['value'] ?? [];
            if (!is_array($value)) {
                continue;
            }

            $leadgen_id = isset($value['leadgen_id']) ? sanitize_text_field((string) $value['leadgen_id']) : '';
            if ($leadgen_id === '') {
                continue;
            }

            $notifications[] = [
                'leadgen_id' => $leadgen_id,
                'page_id' => isset($value['page_id']) ? sanitize_text_field((string) $value['page_id']) : $entry_page_id,
                'form_id' => isset($value['form_id']) ? sanitize_text_field((string) $value['form_id']) : '',
                'event_time' => isset($value['created_time']) ? sanitize_text_field((string) $value['created_time']) : ($entry_time > 0 ? (string) $entry_time : ''),
                'entry_index' => (int) $entry_index,
                'change_index' => (int) $change_index,
                'change_field' => $field,
                'change_value_keys' => array_values(array_filter(array_map('sanitize_key', array_keys($value)))),
            ];
        }
    }

    return $notifications;
}

function peracrm_facebook_leads_extract_signature_stub(WP_REST_Request $request, array $payload)
{
    $signature = (string) $request->get_header('X-Hub-Signature-256');
    if ($signature === '') {
        return [
            'present' => false,
            'verified' => false,
            'reason' => 'missing_header',
        ];
    }

    return [
        'present' => true,
        'verified' => false,
        'reason' => 'todo_app_secret_hmac_verification',
        'header_excerpt' => peracrm_facebook_leads_mask_secret($signature),
        // TODO: Verify sha256 HMAC against raw request body using app secret in next hardening slice.
        'payload_entries' => isset($payload['entry']) && is_array($payload['entry']) ? count($payload['entry']) : 0,
    ];
}

function peracrm_rest_facebook_leads_serve_verify_challenge($served, $result, $request, $server)
{
    if ($served) {
        return $served;
    }

    if (!$request instanceof WP_REST_Request) {
        return $served;
    }

    if ($request->get_method() !== 'GET' || $request->get_route() !== '/peracrm/v1/facebook-leads/webhook') {
        return $served;
    }

    if (!$result instanceof WP_HTTP_Response || (int) $result->get_status() !== 200) {
        return $served;
    }

    $data = $result->get_data();
    if (!is_array($data) || !isset($data['challenge'])) {
        return $served;
    }

    $challenge = (string) $data['challenge'];
    if ($challenge === '') {
        return $served;
    }

    header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
    echo $challenge;

    return true;
}

add_filter('rest_pre_serve_request', 'peracrm_rest_facebook_leads_serve_verify_challenge', 10, 4);
add_action('rest_api_init', 'peracrm_rest_register_facebook_leads_routes');
