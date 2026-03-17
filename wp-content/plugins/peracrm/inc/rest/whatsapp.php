<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_rest_register_whatsapp_routes()
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    register_rest_route('peracrm/v1', '/whatsapp/webhook', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'peracrm_rest_whatsapp_verify_webhook',
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'peracrm_rest_whatsapp_receive_webhook',
            'permission_callback' => '__return_true',
        ],
    ]);
}

function peracrm_rest_whatsapp_verify_webhook(WP_REST_Request $request)
{
    $settings = peracrm_whatsapp_get_settings();
    if (empty($settings['enabled'])) {
        return new WP_REST_Response(['ok' => false, 'message' => 'disabled'], 403);
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
        peracrm_whatsapp_set_diagnostic('verify_failed', 'missing challenge parameters');
        return new WP_REST_Response(['ok' => false], 400);
    }

    if (!hash_equals((string) $settings['verify_token'], $verify_token)) {
        peracrm_whatsapp_set_diagnostic('verify_failed', 'invalid verify token');
        return new WP_REST_Response(['ok' => false], 403);
    }

    peracrm_whatsapp_set_diagnostic('verify_ok', 'webhook verified');

    return new WP_REST_Response([
        'challenge' => $challenge,
    ], 200);
}

function peracrm_rest_whatsapp_receive_webhook(WP_REST_Request $request)
{
    $settings = peracrm_whatsapp_get_settings();
    if (empty($settings['enabled'])) {
        return new WP_REST_Response(['ok' => false, 'message' => 'disabled'], 403);
    }

    $payload = $request->get_json_params();
    if (!is_array($payload)) {
        peracrm_whatsapp_set_diagnostic('ingest_failed', 'invalid json payload');
        return new WP_REST_Response(['ok' => false], 400);
    }

    if ((string) ($payload['object'] ?? '') !== 'whatsapp_business_account') {
        peracrm_whatsapp_set_diagnostic('ingest_failed', 'unexpected object');
        return new WP_REST_Response(['ok' => true, 'ignored' => true], 202);
    }

    try {
        $processed = (int) peracrm_with_target_blog(static function () use ($payload) {
            return peracrm_whatsapp_process_inbound_payload($payload);
        });
        peracrm_whatsapp_set_diagnostic('ingest_ok', 'processed: ' . $processed);
        peracrm_whatsapp_log('Inbound webhook processed', ['processed' => $processed]);

        return new WP_REST_Response(['ok' => true, 'processed' => $processed], 200);
    } catch (Throwable $t) {
        peracrm_whatsapp_set_diagnostic('ingest_failed', $t->getMessage());
        peracrm_whatsapp_log('Webhook failure', ['error' => $t->getMessage()]);

        return new WP_REST_Response(['ok' => false], 500);
    }
}


function peracrm_rest_whatsapp_serve_verify_challenge($served, $result, $request, $server)
{
    if ($served) {
        return $served;
    }

    if (!$request instanceof WP_REST_Request) {
        return $served;
    }

    if ($request->get_method() !== 'GET' || $request->get_route() !== '/peracrm/v1/whatsapp/webhook') {
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

add_filter('rest_pre_serve_request', 'peracrm_rest_whatsapp_serve_verify_challenge', 10, 4);

add_action('rest_api_init', 'peracrm_rest_register_whatsapp_routes');
