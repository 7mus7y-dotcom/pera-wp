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

    $identifiers = peracrm_facebook_leads_extract_payload_identifiers($payload);

    peracrm_facebook_leads_log_debug('Webhook payload received', [
        'entry_count' => isset($payload['entry']) && is_array($payload['entry']) ? count($payload['entry']) : 0,
        'leadgen_id' => $identifiers['leadgen_id'],
        'page_id' => $identifiers['page_id'],
        'form_id' => $identifiers['form_id'],
    ]);

    return new WP_REST_Response([
        'ok' => true,
        'received' => true,
    ], 200);
}

function peracrm_facebook_leads_extract_payload_identifiers(array $payload)
{
    $result = [
        'leadgen_id' => '',
        'page_id' => '',
        'form_id' => '',
    ];

    $entries = $payload['entry'] ?? [];
    if (!is_array($entries)) {
        return $result;
    }

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        if ($result['page_id'] === '' && isset($entry['id'])) {
            $result['page_id'] = sanitize_text_field((string) $entry['id']);
        }

        $changes = $entry['changes'] ?? [];
        if (!is_array($changes)) {
            continue;
        }

        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            $value = $change['value'] ?? [];
            if (!is_array($value)) {
                continue;
            }

            if ($result['leadgen_id'] === '' && isset($value['leadgen_id'])) {
                $result['leadgen_id'] = sanitize_text_field((string) $value['leadgen_id']);
            }

            if ($result['form_id'] === '' && isset($value['form_id'])) {
                $result['form_id'] = sanitize_text_field((string) $value['form_id']);
            }

            if ($result['page_id'] === '' && isset($value['page_id'])) {
                $result['page_id'] = sanitize_text_field((string) $value['page_id']);
            }

            if ($result['leadgen_id'] !== '' && $result['form_id'] !== '' && $result['page_id'] !== '') {
                return $result;
            }
        }
    }

    return $result;
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
