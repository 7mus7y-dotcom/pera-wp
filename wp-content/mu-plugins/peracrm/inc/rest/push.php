<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_rest_register_push_routes()
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    register_rest_route('peracrm/v1', '/push/config', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'peracrm_rest_get_push_config',
        'permission_callback' => 'peracrm_rest_can_access_push',
    ]);

    register_rest_route('peracrm/v1', '/push/subscribe', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'peracrm_rest_push_subscribe',
        'permission_callback' => 'peracrm_rest_can_access_push',
    ]);

    register_rest_route('peracrm/v1', '/push/unsubscribe', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'peracrm_rest_push_unsubscribe',
        'permission_callback' => 'peracrm_rest_can_access_push',
    ]);

    register_rest_route('peracrm/v1', '/push/debug', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'peracrm_rest_push_debug',
        'permission_callback' => 'peracrm_rest_can_access_push',
    ]);

    register_rest_route('peracrm/v1', '/push/digest/run', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'peracrm_rest_push_digest_run',
        'permission_callback' => 'peracrm_rest_can_run_digest',
    ]);
}

function peracrm_rest_get_push_config(WP_REST_Request $request)
{
    return new WP_REST_Response(peracrm_push_get_public_config());
}

function peracrm_rest_push_subscribe(WP_REST_Request $request)
{
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return new WP_Error('peracrm_forbidden', 'Authentication required.', ['status' => 401]);
    }

    $payload = $request->get_json_params();
    if (!is_array($payload)) {
        $payload = [];
    }

    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    $saved = function_exists('peracrm_with_target_blog')
        ? peracrm_with_target_blog(static function () use ($user_id, $payload, $user_agent) {
            return peracrm_push_save_subscription($user_id, $payload, $user_agent);
        })
        : peracrm_push_save_subscription($user_id, $payload, $user_agent);
    if (is_wp_error($saved)) {
        return $saved;
    }

    return new WP_REST_Response([
        'ok' => true,
        'subscriptions' => peracrm_push_get_subscriptions($user_id),
    ]);
}

function peracrm_rest_push_unsubscribe(WP_REST_Request $request)
{
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return new WP_Error('peracrm_forbidden', 'Authentication required.', ['status' => 401]);
    }

    $payload = $request->get_json_params();
    if (!is_array($payload)) {
        $payload = [];
    }

    $endpoint = isset($payload['endpoint']) ? (string) $payload['endpoint'] : '';
    $removed = function_exists('peracrm_with_target_blog')
        ? peracrm_with_target_blog(static function () use ($user_id, $endpoint) {
            return peracrm_push_remove_subscription($user_id, $endpoint);
        })
        : peracrm_push_remove_subscription($user_id, $endpoint);

    return new WP_REST_Response([
        'ok' => true,
        'removed' => (bool) $removed,
        'subscriptions' => peracrm_push_get_subscriptions($user_id),
    ]);
}

function peracrm_rest_push_debug(WP_REST_Request $request)
{
    $acting_user_id = get_current_user_id();
    $is_manager = function_exists('peracrm_push_user_can_run_digest')
        ? peracrm_push_user_can_run_digest($acting_user_id)
        : false;

    $requested_user_id = absint($request->get_param('user_id'));
    $target_user_id = $acting_user_id;
    $ignored_user_id = false;
    if ($is_manager && $requested_user_id > 0) {
        $target_user_id = $requested_user_id;
    } elseif (!$is_manager && $requested_user_id > 0 && $requested_user_id !== $acting_user_id) {
        $ignored_user_id = true;
    }

    $debug = function_exists('peracrm_push_get_debug_data')
        ? peracrm_push_get_debug_data($acting_user_id, $target_user_id)
        : [];

    return new WP_REST_Response([
        'ok' => true,
        'acting_user_id' => $acting_user_id,
        'target_user_id' => $target_user_id,
        'can_impersonate' => (bool) $is_manager,
        'ignored_user_id_param' => $ignored_user_id,
        'debug' => is_array($debug) ? $debug : [],
        'self_check' => [
            'has_get_debug_data' => function_exists('peracrm_push_get_debug_data'),
            'has_get_public_config' => function_exists('peracrm_push_get_public_config'),
            'has_run_digest' => function_exists('peracrm_push_run_digest_for_current_window'),
            'is_manager' => (bool) $is_manager,
        ],
    ]);
}

function peracrm_rest_push_digest_run(WP_REST_Request $request)
{
    $force = rest_sanitize_boolean($request->get_param('force'));
    $summary = function_exists('peracrm_push_run_digest_for_current_window')
        ? peracrm_push_run_digest_for_current_window($force)
        : [];

    return new WP_REST_Response([
        'ok' => true,
        'force' => (bool) $force,
        'summary' => is_array($summary) ? $summary : [],
        'cron' => function_exists('peracrm_push_get_cron_health') ? peracrm_push_get_cron_health() : [],
    ]);
}


function peracrm_rest_can_access_push(WP_REST_Request $request)
{
    if (!is_user_logged_in()) {
        return new WP_Error('peracrm_forbidden', 'Authentication required.', ['status' => 401]);
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0 || !function_exists('peracrm_user_can_access_crm') || !peracrm_user_can_access_crm($user_id)) {
        return new WP_Error('peracrm_forbidden', 'CRM access required.', ['status' => 403]);
    }

    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        $nonce = (string) $request->get_param('_wpnonce');
    }

    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('peracrm_invalid_nonce', 'Invalid REST nonce.', ['status' => 403]);
    }

    return true;
}

function peracrm_rest_can_run_digest(WP_REST_Request $request)
{
    $access = peracrm_rest_can_access_push($request);
    if (is_wp_error($access)) {
        return $access;
    }

    if (!function_exists('peracrm_push_user_can_run_digest') || !peracrm_push_user_can_run_digest(get_current_user_id())) {
        return new WP_Error('peracrm_forbidden', 'Digest trigger requires manager/admin access.', ['status' => 403]);
    }

    return true;
}


add_action('rest_api_init', 'peracrm_rest_register_push_routes');
