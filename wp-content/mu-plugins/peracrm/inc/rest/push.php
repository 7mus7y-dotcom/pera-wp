<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_rest_register_push_routes()
{
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


function peracrm_rest_can_access_push(WP_REST_Request $request)
{
    if (!is_user_logged_in()) {
        return new WP_Error('peracrm_forbidden', 'Authentication required.', ['status' => 401]);
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
