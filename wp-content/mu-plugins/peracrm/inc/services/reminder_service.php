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

    $assigned_advisor_id = isset($reminder['advisor_user_id']) ? (int) $reminder['advisor_user_id'] : 0;
    $can_manage = current_user_can('manage_options') || current_user_can('peracrm_manage_all_reminders');
    if (!$can_manage && $assigned_advisor_id !== $actor_user_id) {
        return new WP_Error('unauthorized', 'Unauthorized reminder update attempt.');
    }

    $client_id = isset($reminder['client_id']) ? absint($reminder['client_id']) : 0;
    $client = $client_id > 0 ? get_post($client_id) : null;
    if (!$client || $client->post_type !== 'crm_client') {
        return new WP_Error('invalid_client', 'Reminder client is invalid.');
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
