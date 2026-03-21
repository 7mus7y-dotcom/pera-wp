<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_log_event($client_id, $event_type, array $payload = [])
{
    if (!isset($payload['actor_user_id']) || (int) $payload['actor_user_id'] <= 0) {
        $payload['actor_user_id'] = function_exists('peracrm_get_actor_user_id') ? peracrm_get_actor_user_id() : get_current_user_id();
    }

    $payload['ts'] = peracrm_now_mysql();

    if (function_exists('peracrm_activity_log')) {
        return peracrm_activity_log($client_id, $event_type, $payload);
    }

    return peracrm_activity_insert($client_id, $event_type, $payload);
}
