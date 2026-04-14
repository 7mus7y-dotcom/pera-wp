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

function peracrm_log_event_result($client_id, $event_type, array $payload = [])
{
    $activity_id = 0;
    $ok = (bool) peracrm_log_event($client_id, $event_type, $payload);

    if (!$ok) {
        return [
            'ok' => false,
            'activity_id' => 0,
        ];
    }

    if (!isset($payload['submission_id']) || !function_exists('peracrm_activity_table_exists') || !peracrm_activity_table_exists()) {
        return [
            'ok' => true,
            'activity_id' => 0,
        ];
    }

    global $wpdb;

    $client_id = (int) $client_id;
    $event_type = sanitize_key($event_type);
    $submission_id = sanitize_text_field((string) $payload['submission_id']);

    if ($client_id > 0 && $event_type !== '' && $submission_id !== '') {
        $table = peracrm_table('crm_activity');
        $needle = $wpdb->esc_like('"submission_id":"' . $submission_id . '"');
        $query = $wpdb->prepare(
            "SELECT id
             FROM {$table}
             WHERE client_id = %d
               AND event_type = %s
               AND event_payload LIKE %s
             ORDER BY id DESC
             LIMIT 1",
            $client_id,
            $event_type,
            '%' . $needle . '%'
        );

        $activity_id = (int) $wpdb->get_var($query);
    }

    return [
        'ok' => true,
        'activity_id' => $activity_id,
    ];
}
