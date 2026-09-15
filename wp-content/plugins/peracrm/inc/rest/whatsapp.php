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
    register_rest_route('peracrm/v1', '/whatsapp/clients/(?P<client_id>\d+)/messages', [
        ['methods' => WP_REST_Server::READABLE, 'callback' => 'peracrm_rest_whatsapp_client_messages', 'permission_callback' => 'peracrm_rest_whatsapp_client_permission'],
        ['methods' => WP_REST_Server::CREATABLE, 'callback' => 'peracrm_rest_whatsapp_send_message', 'permission_callback' => 'peracrm_rest_whatsapp_client_permission'],
    ]);
    register_rest_route('peracrm/v1', '/whatsapp/associate', [
        'methods' => WP_REST_Server::EDITABLE,
        'callback' => 'peracrm_rest_whatsapp_associate_sender',
        'permission_callback' => static function () { return current_user_can('manage_options'); },
    ]);
}

function peracrm_rest_whatsapp_client_permission(WP_REST_Request $request)
{
    return is_user_logged_in() && peracrm_whatsapp_user_can_access_client((int) $request['client_id']);
}

function peracrm_whatsapp_verify_meta_signature($raw, $signature, $secret)
{
    $signature = trim((string) $signature);
    $secret = (string) $secret;
    if ($secret === '' || !preg_match('/^sha256=([a-f0-9]{64})$/i', $signature, $matches)) return false;
    return hash_equals(hash_hmac('sha256', (string) $raw, $secret), strtolower($matches[1]));
}

function peracrm_rest_whatsapp_verify_webhook(WP_REST_Request $request)
{
    $settings = peracrm_whatsapp_get_settings();
    if (empty($settings['enabled']) || empty($settings['test_mode']) || empty($settings['verify_token'])) {
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
    if (empty($settings['enabled']) || empty($settings['test_mode']) || empty($settings['phone_number_id'])) {
        return new WP_REST_Response(['ok' => false, 'message' => 'disabled'], 403);
    }

    $secret = (string) ($settings['app_secret'] ?? '');
    $raw = (string) $request->get_body();
    $signature = trim((string) $request->get_header('X-Hub-Signature-256'));
    if (!peracrm_whatsapp_verify_meta_signature($raw, $signature, $secret)) {
        peracrm_whatsapp_set_diagnostic('signature_failed', 'invalid or missing webhook signature');
        return new WP_REST_Response(['ok' => false], 401);
    }
    if ($raw === '' || strlen($raw) > 1048576) return new WP_REST_Response(['ok' => false], 400);
    $payload = json_decode($raw, true);
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

function peracrm_rest_whatsapp_client_messages(WP_REST_Request $request)
{
    $result = peracrm_with_target_blog(static function () use ($request) {
        return peracrm_whatsapp_get_messages(['client_id' => (int) $request['client_id'], 'per_page' => 100]);
    });
    return new WP_REST_Response(['messages' => $result['rows'], 'test_mode' => true], 200);
}

function peracrm_rest_whatsapp_send_message(WP_REST_Request $request)
{
    $result = peracrm_with_target_blog(static function () use ($request) {
        return peracrm_whatsapp_send_client_text((int) $request['client_id'], (string) $request->get_param('message'));
    });
    return is_wp_error($result) ? $result : new WP_REST_Response($result, 201);
}

function peracrm_rest_whatsapp_associate_sender(WP_REST_Request $request)
{
    global $wpdb;
    $client_id = absint($request->get_param('client_id'));
    $wa_id = preg_replace('/\D+/', '', (string) $request->get_param('wa_id'));
    if ($client_id <= 0 || get_post_type($client_id) !== 'crm_client' || $wa_id === '') return new WP_Error('invalid_association', 'Valid client and sender are required.', ['status' => 400]);
    $updated = peracrm_with_target_blog(static function () use ($wpdb, $client_id, $wa_id) {
        $table = peracrm_whatsapp_messages_table_name();
        return $wpdb->query($wpdb->prepare("UPDATE {$table} SET client_id = %d, linked_by = 'admin' WHERE sender_wa_id = %s AND client_id IS NULL", $client_id, $wa_id));
    });
    return new WP_REST_Response(['associated' => max(0, (int) $updated)], 200);
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
