<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_reminders_update_status_authorized($reminder_id, $status, $actor_user_id, array $args = [])
{
    $reminder_id = absint($reminder_id);
    $actor_user_id = absint($actor_user_id);
    $status = peracrm_reminders_sanitize_status(sanitize_key($status));

    if ($reminder_id <= 0 || $actor_user_id <= 0) {
        return new WP_Error('invalid_args', 'Invalid reminder update arguments.');
    }

    if ($status === '') {
        return new WP_Error('invalid_status', 'Invalid reminder status.');
    }

    $reminder = peracrm_reminders_get($reminder_id);
    if (!$reminder) {
        return new WP_Error('invalid_reminder', 'Reminder not found.');
    }

    $client_id = isset($reminder['client_id']) ? absint($reminder['client_id']) : 0;
    if (!peracrm_user_can_complete_reminder($actor_user_id, $reminder_id, $client_id)) {
        return new WP_Error('unauthorized', 'Unauthorized reminder update attempt.');
    }

    $client = $client_id > 0 ? get_post($client_id) : null;
    if (!$client || $client->post_type !== 'crm_client') {
        return new WP_Error('invalid_client', 'Reminder client is invalid.');
    }

    if (!empty($args['enforce_client_scope'])) {
        if (user_can($actor_user_id, 'manage_options') || user_can($actor_user_id, 'peracrm_manage_all_reminders')) {
            $args['enforce_client_scope'] = false;
        }
    }

    if (!empty($args['enforce_client_scope'])) {
        $allowed_ids = apply_filters('peracrm_allowed_client_ids_for_user', null, $actor_user_id);
        if (is_array($allowed_ids) && !in_array($client_id, array_map('absint', $allowed_ids), true)) {
            return new WP_Error('out_of_scope', 'Reminder client is outside the allowed scope.');
        }
    }

    $updated = peracrm_reminder_update_status($reminder_id, $status, $actor_user_id);
    if (!$updated) {
        return new WP_Error('update_failed', 'Unable to update reminder status.');
    }

    return true;
}

function peracrm_user_can_complete_reminder($user_id, $reminder_id, $client_id = 0)
{
    $user_id = absint($user_id);
    $reminder_id = absint($reminder_id);
    $client_id = absint($client_id);

    if ($user_id <= 0 || $reminder_id <= 0) {
        return false;
    }

    if (user_can($user_id, 'manage_options') || user_can($user_id, 'peracrm_manage_all_reminders')) {
        return true;
    }

    $reminder = peracrm_reminders_get($reminder_id);
    if (!$reminder) {
        return false;
    }

    if ($client_id > 0 && isset($reminder['client_id']) && absint($reminder['client_id']) !== $client_id) {
        return false;
    }

    $assigned_advisor_id = isset($reminder['advisor_user_id']) ? (int) $reminder['advisor_user_id'] : 0;
    return $assigned_advisor_id === $user_id;
}
