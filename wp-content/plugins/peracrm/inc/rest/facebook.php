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
    $raw = file_get_contents('php://input');
    error_log('PeraCRM FB WEBHOOK RAW: ' . $raw);

    $leadgen_id = '';
    $notifications_count = 0;
    $scheduled = 0;
    $failed = 0;
    $last_error = '';

    try {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $raw_body = (string) $request->get_body();
            $decoded = json_decode($raw_body, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        error_log('PeraCRM Facebook webhook request received: ' . wp_json_encode([
            'object' => isset($payload['object']) ? sanitize_key((string) $payload['object']) : '',
            'entry_count' => isset($payload['entry']) && is_array($payload['entry']) ? count($payload['entry']) : 0,
        ]));

        $notifications = [];
        if (function_exists('peracrm_facebook_leads_extract_lead_notifications')) {
            $notifications = peracrm_facebook_leads_extract_lead_notifications($payload);
        } elseif (isset($payload['entry'][0]['changes'][0]['value']['leadgen_id'])) {
            $fallback_leadgen_id = sanitize_text_field((string) $payload['entry'][0]['changes'][0]['value']['leadgen_id']);
            if ($fallback_leadgen_id !== '') {
                $notifications[] = ['leadgen_id' => $fallback_leadgen_id];
            }
        }

        if (!empty($notifications[0]['leadgen_id'])) {
            $leadgen_id = sanitize_text_field((string) $notifications[0]['leadgen_id']);
            set_transient('peracrm_facebook_last_leadgen_id', $leadgen_id, DAY_IN_SECONDS);
            error_log('PeraCRM Facebook leadgen_id extracted: ' . $leadgen_id);
        }

        $notifications_count = is_array($notifications) ? count($notifications) : 0;
        set_transient('peracrm_facebook_last_webhook_payload', $payload, DAY_IN_SECONDS);

        if (!empty($notifications)) {
            foreach ($notifications as $notification) {
                if (!is_array($notification)) {
                    continue;
                }

                $scheduled_result = peracrm_rest_facebook_schedule_notification_processing($notification);
                if (!empty($scheduled_result['ok'])) {
                    $scheduled++;
                    continue;
                }

                $failed++;
                $last_error = sanitize_text_field((string) ($scheduled_result['error_message'] ?? 'schedule_failed'));
                error_log('PeraCRM Facebook lead notification scheduling failed: ' . wp_json_encode([
                    'leadgen_id' => sanitize_text_field((string) ($notification['leadgen_id'] ?? '')),
                    'error_code' => sanitize_key((string) ($scheduled_result['error_code'] ?? 'unknown')),
                    'error_message' => $last_error,
                ]));
            }
        }
    } catch (Throwable $e) {
        error_log('PeraCRM Facebook webhook exception: ' . $e->getMessage());
        $last_error = sanitize_text_field($e->getMessage());
    }

    $response_data = [
        'ok' => true,
        'received' => true,
        'leadgen_id' => $leadgen_id,
        'notifications' => $notifications_count,
        'retrieved' => 0,
        'ingested' => 0,
        'scheduled' => $scheduled,
        'failed' => $failed,
        'last_error' => $last_error,
        'processing' => 'deferred',
        'handled_by' => 'peracrm_rest_facebook_receive_webhook',
    ];
    error_log('PeraCRM Facebook webhook response returned: ' . wp_json_encode($response_data));

    return new WP_REST_Response($response_data, 200);
}

function peracrm_rest_facebook_schedule_notification_processing(array $notification)
{
    $leadgen_id = sanitize_text_field((string) ($notification['leadgen_id'] ?? ''));
    if ($leadgen_id === '') {
        return [
            'ok' => false,
            'error_code' => 'missing_leadgen_id',
            'error_message' => 'Leadgen ID missing from notification.',
        ];
    }

    $event_args = [
        $notification,
    ];

    $next_scheduled = wp_next_scheduled('peracrm_process_facebook_lead_notification', $event_args);
    if ($next_scheduled) {
        return [
            'ok' => true,
            'already_scheduled' => true,
            'leadgen_id' => $leadgen_id,
        ];
    }

    $scheduled = wp_schedule_single_event(time(), 'peracrm_process_facebook_lead_notification', $event_args);
    if (!$scheduled) {
        return [
            'ok' => false,
            'error_code' => 'wp_schedule_single_event_failed',
            'error_message' => 'Unable to schedule async Facebook lead processing.',
        ];
    }

    error_log('PeraCRM Facebook lead notification scheduled for async processing: ' . wp_json_encode([
        'leadgen_id' => $leadgen_id,
    ]));

    return [
        'ok' => true,
        'leadgen_id' => $leadgen_id,
    ];
}

function peracrm_process_facebook_lead_notification($notification)
{
    $leadgen_id = sanitize_text_field((string) ($notification['leadgen_id'] ?? ''));
    if ($leadgen_id === '') {
        error_log('PeraCRM Facebook async processing skipped: missing leadgen_id');
        return;
    }

    if (!function_exists('peracrm_facebook_leads_graph_get_lead') || !function_exists('peracrm_facebook_leads_ingest_graph_lead')) {
        error_log('PeraCRM Facebook async processing skipped: integration helpers unavailable');
        return;
    }

    $graph_result = peracrm_facebook_leads_graph_get_lead($leadgen_id);
    if (empty($graph_result['ok'])) {
        error_log('PeraCRM Facebook Graph lead lookup failed (async): ' . wp_json_encode([
            'leadgen_id' => $leadgen_id,
            'error_code' => sanitize_key((string) ($graph_result['error_code'] ?? 'unknown')),
            'error_message' => sanitize_text_field((string) ($graph_result['error_message'] ?? 'graph_lookup_failed')),
            'http_status' => (int) ($graph_result['http_status'] ?? 0),
        ]));
        return;
    }

    $ingest_result = peracrm_facebook_leads_ingest_graph_lead($notification, $graph_result);
    error_log('PeraCRM Facebook lead async ingest completed: ' . wp_json_encode([
        'leadgen_id' => $leadgen_id,
        'status' => sanitize_key((string) ($ingest_result['status'] ?? 'unknown')),
        'client_id' => (int) ($ingest_result['client_id'] ?? 0),
    ]));
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
add_action('peracrm_process_facebook_lead_notification', 'peracrm_process_facebook_lead_notification', 10, 1);
