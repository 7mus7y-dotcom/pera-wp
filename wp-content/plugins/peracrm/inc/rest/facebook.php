<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_rest_register_facebook_routes()
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    register_rest_route('peracrm/v1', '/facebook/webhook', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'peracrm_rest_facebook_verify_webhook',
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'peracrm_rest_facebook_receive_webhook',
            'permission_callback' => '__return_true',
        ],
    ]);
}

function peracrm_rest_facebook_verify_webhook(WP_REST_Request $request)
{
    $mode = isset($_GET['hub_mode']) ? sanitize_text_field(wp_unslash((string) $_GET['hub_mode'])) : '';
    $verify_token = isset($_GET['hub_verify_token']) ? sanitize_text_field(wp_unslash((string) $_GET['hub_verify_token'])) : '';
    $challenge = isset($_GET['hub_challenge']) ? wp_unslash((string) $_GET['hub_challenge']) : '';

    if ($verify_token !== 'peracrm_fb_verify') {
        return new WP_REST_Response(['ok' => false], 403);
    }

    error_log('PeraCRM Facebook webhook verification request: ' . wp_json_encode([
        'hub_mode' => $mode,
        'challenge_length' => strlen($challenge),
    ]));

    return new WP_REST_Response([
        'challenge' => $challenge,
    ], 200);
}

function peracrm_rest_facebook_receive_webhook(WP_REST_Request $request)
{
    $payload = $request->get_json_params();
    if (!is_array($payload)) {
        $raw_body = (string) $request->get_body();
        $decoded = json_decode($raw_body, true);
        $payload = is_array($decoded) ? $decoded : [];
    }

    error_log('PeraCRM Facebook lead webhook payload: ' . wp_json_encode($payload));

    $leadgen_id = '';
    if (isset($payload['entry'][0]['changes'][0]['value']['leadgen_id'])) {
        $leadgen_id = sanitize_text_field((string) $payload['entry'][0]['changes'][0]['value']['leadgen_id']);
    }

    if ($leadgen_id !== '') {
        set_transient('peracrm_facebook_last_leadgen_id', $leadgen_id, DAY_IN_SECONDS);
        error_log('PeraCRM Facebook leadgen_id received: ' . $leadgen_id);
    }

    return new WP_REST_Response([
        'ok' => true,
        'received' => true,
        'leadgen_id' => $leadgen_id,
    ], 200);
}

function peracrm_rest_facebook_serve_verify_challenge($served, $result, $request, $server)
{
    if ($served) {
        return $served;
    }

    if (!$request instanceof WP_REST_Request) {
        return $served;
    }

    if ($request->get_method() !== 'GET' || $request->get_route() !== '/peracrm/v1/facebook/webhook') {
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
    header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
    echo $challenge;

    return true;
}

add_filter('rest_pre_serve_request', 'peracrm_rest_facebook_serve_verify_challenge', 10, 4);
add_action('rest_api_init', 'peracrm_rest_register_facebook_routes');
