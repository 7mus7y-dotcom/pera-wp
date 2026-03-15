<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_whatsapp_default_settings()
{
    return [
        'enabled' => 0,
        'phone_number_id' => '',
        'access_token' => '',
        'verify_token' => '',
        'test_mode' => 0,
    ];
}

function peracrm_whatsapp_get_settings()
{
    $saved = get_option('peracrm_whatsapp_settings', []);
    if (!is_array($saved)) {
        $saved = [];
    }

    return wp_parse_args($saved, peracrm_whatsapp_default_settings());
}

function peracrm_whatsapp_is_enabled()
{
    $settings = peracrm_whatsapp_get_settings();

    return !empty($settings['enabled']);
}

function peracrm_whatsapp_mask_secret($value)
{
    $value = (string) $value;
    $len = strlen($value);
    if ($len <= 0) {
        return '';
    }

    if ($len <= 6) {
        return str_repeat('*', $len);
    }

    return substr($value, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($value, -3);
}

function peracrm_whatsapp_save_settings(array $input)
{
    $existing = peracrm_whatsapp_get_settings();

    $settings = [
        'enabled' => !empty($input['enabled']) ? 1 : 0,
        'phone_number_id' => sanitize_text_field((string) ($input['phone_number_id'] ?? '')),
        'verify_token' => sanitize_text_field((string) ($input['verify_token'] ?? '')),
        'test_mode' => !empty($input['test_mode']) ? 1 : 0,
        'access_token' => $existing['access_token'],
    ];

    if (isset($input['access_token'])) {
        $candidate = trim((string) $input['access_token']);
        if ($candidate !== '') {
            $settings['access_token'] = sanitize_text_field($candidate);
        }
    }

    update_option('peracrm_whatsapp_settings', $settings, false);

    return $settings;
}

function peracrm_whatsapp_log($message, array $context = [])
{
    $safe = [];
    foreach ($context as $key => $value) {
        $k = sanitize_key((string) $key);
        if (in_array($k, ['access_token', 'verify_token'], true)) {
            continue;
        }

        if (is_scalar($value) || $value === null) {
            $safe[$k] = $value;
        }
    }

    error_log('[PeraCRM whatsapp] ' . sanitize_text_field($message) . ' ' . wp_json_encode($safe));
}

function peracrm_whatsapp_set_diagnostic($status, $message = '')
{
    $diag = [
        'last_received_at' => peracrm_now_mysql(),
        'last_status' => sanitize_key((string) $status),
        'last_error' => sanitize_text_field((string) $message),
    ];

    update_option('peracrm_whatsapp_last_diag', $diag, false);
}

function peracrm_whatsapp_get_diagnostic()
{
    $saved = get_option('peracrm_whatsapp_last_diag', []);
    if (!is_array($saved)) {
        $saved = [];
    }

    return wp_parse_args($saved, [
        'last_received_at' => '',
        'last_status' => '',
        'last_error' => '',
    ]);
}

function peracrm_whatsapp_normalize_phone($phone_raw)
{
    $phone_raw = (string) $phone_raw;
    $phone_raw = preg_replace('/[^0-9+]/', '', $phone_raw);
    if ($phone_raw === '') {
        return '';
    }

    if (strpos($phone_raw, '+') === 0) {
        $digits = preg_replace('/\D+/', '', $phone_raw);
        return $digits !== '' ? '+' . $digits : '';
    }

    $digits = preg_replace('/\D+/', '', $phone_raw);
    if ($digits === '') {
        return '';
    }

    // Assumption for local Turkish numbers: 05xxxxxxxxx / 5xxxxxxxxx / 90xxxxxxxxxx.
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }
    if (strpos($digits, '90') === 0 && strlen($digits) >= 12) {
        return '+' . $digits;
    }
    if (strpos($digits, '0') === 0 && strlen($digits) === 11) {
        return '+90' . substr($digits, 1);
    }
    if (strlen($digits) === 10) {
        return '+90' . $digits;
    }

    return '+' . $digits;
}

function peracrm_whatsapp_find_client_by_phone($phone_e164)
{
    $phone_e164 = peracrm_whatsapp_normalize_phone($phone_e164);
    if ($phone_e164 === '') {
        return 0;
    }

    $meta_keys = ['_peracrm_phone', 'crm_phone'];

    foreach ($meta_keys as $meta_key) {
        $matches = get_posts([
            'post_type' => 'crm_client',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_query' => [[
                'key' => $meta_key,
                'value' => $phone_e164,
                'compare' => '=',
            ]],
        ]);

        if (!empty($matches)) {
            return (int) $matches[0];
        }
    }

    return 0;
}

function peracrm_whatsapp_fallback_name($phone_e164)
{
    $suffix = substr(preg_replace('/\D+/', '', (string) $phone_e164), -4);
    if ($suffix === '') {
        $suffix = 'lead';
    }

    return 'WhatsApp Lead ' . $suffix;
}

function peracrm_whatsapp_create_client_from_inbound($phone_e164, $contact_name = '')
{
    $phone_e164 = peracrm_whatsapp_normalize_phone($phone_e164);
    if ($phone_e164 === '') {
        return 0;
    }

    $contact_name = sanitize_text_field((string) $contact_name);
    $title = $contact_name !== '' ? $contact_name : peracrm_whatsapp_fallback_name($phone_e164);

    $post_id = wp_insert_post([
        'post_type' => 'crm_client',
        'post_title' => $title,
        'post_status' => 'publish',
    ], true);

    if (is_wp_error($post_id)) {
        return 0;
    }

    $post_id = (int) $post_id;

    update_post_meta($post_id, 'crm_phone', $phone_e164);
    update_post_meta($post_id, '_peracrm_phone', $phone_e164);
    update_post_meta($post_id, 'crm_source', 'whatsapp_inbound');
    update_post_meta($post_id, 'crm_status', 'enquiry');
    if ($contact_name !== '') {
        update_post_meta($post_id, 'crm_first_name', $contact_name);
    }

    if (function_exists('peracrm_log_event')) {
        peracrm_log_event($post_id, 'client_created', [
            'source' => 'whatsapp_inbound',
            'phone' => $phone_e164,
        ]);
    }

    return $post_id;
}

function peracrm_whatsapp_store_message(array $record)
{
    global $wpdb;

    $table = peracrm_whatsapp_messages_table_name();

    $inserted = $wpdb->insert($table, [
        'client_id' => !empty($record['client_id']) ? (int) $record['client_id'] : null,
        'phone_e164' => sanitize_text_field((string) ($record['phone_e164'] ?? '')),
        'whatsapp_contact_name' => sanitize_text_field((string) ($record['whatsapp_contact_name'] ?? '')),
        'direction' => sanitize_key((string) ($record['direction'] ?? 'inbound')),
        'message_type' => sanitize_key((string) ($record['message_type'] ?? 'text')),
        'message_body' => isset($record['message_body']) ? sanitize_textarea_field((string) $record['message_body']) : null,
        'media_url' => isset($record['media_url']) ? esc_url_raw((string) $record['media_url']) : null,
        'whatsapp_message_id' => sanitize_text_field((string) ($record['whatsapp_message_id'] ?? '')),
        'raw_payload_json' => isset($record['raw_payload_json']) ? (string) $record['raw_payload_json'] : '{}',
        'source' => sanitize_key((string) ($record['source'] ?? 'whatsapp')),
        'linked_by' => sanitize_key((string) ($record['linked_by'] ?? 'phone')),
        'created_at' => peracrm_now_mysql(),
    ], [
        '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
    ]);

    if (!$inserted) {
        return 0;
    }

    return (int) $wpdb->insert_id;
}

function peracrm_whatsapp_count_messages()
{
    global $wpdb;
    $table = peracrm_whatsapp_messages_table_name();

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
}

function peracrm_whatsapp_process_inbound_payload(array $payload)
{
    $entries = isset($payload['entry']) && is_array($payload['entry']) ? $payload['entry'] : [];
    $processed = 0;

    foreach ($entries as $entry) {
        $changes = isset($entry['changes']) && is_array($entry['changes']) ? $entry['changes'] : [];
        foreach ($changes as $change) {
            $value = isset($change['value']) && is_array($change['value']) ? $change['value'] : [];
            $messages = isset($value['messages']) && is_array($value['messages']) ? $value['messages'] : [];
            $contacts = isset($value['contacts']) && is_array($value['contacts']) ? $value['contacts'] : [];
            $contact_name = '';
            if (!empty($contacts[0]['profile']['name'])) {
                $contact_name = sanitize_text_field((string) $contacts[0]['profile']['name']);
            }

            foreach ($messages as $message) {
                $from = isset($message['from']) ? (string) $message['from'] : '';
                $phone_e164 = peracrm_whatsapp_normalize_phone($from);
                if ($phone_e164 === '') {
                    continue;
                }

                $message_type = isset($message['type']) ? sanitize_key((string) $message['type']) : 'unknown';
                $message_body = '';
                $media_url = '';

                if ($message_type === 'text' && !empty($message['text']['body'])) {
                    $message_body = (string) $message['text']['body'];
                } elseif ($message_type !== 'text') {
                    $message_body = '[' . $message_type . ']';
                }

                $client_id = peracrm_whatsapp_find_client_by_phone($phone_e164);
                $was_created = false;
                if ($client_id <= 0) {
                    $client_id = peracrm_whatsapp_create_client_from_inbound($phone_e164, $contact_name);
                    $was_created = $client_id > 0;
                }

                $message_id = isset($message['id']) ? sanitize_text_field((string) $message['id']) : '';
                $message_row_id = peracrm_whatsapp_store_message([
                    'client_id' => $client_id,
                    'phone_e164' => $phone_e164,
                    'whatsapp_contact_name' => $contact_name,
                    'direction' => 'inbound',
                    'message_type' => $message_type,
                    'message_body' => $message_body,
                    'media_url' => $media_url,
                    'whatsapp_message_id' => $message_id,
                    'raw_payload_json' => peracrm_json_encode($message),
                    'source' => 'whatsapp',
                    'linked_by' => 'phone',
                ]);

                if ($client_id > 0 && function_exists('peracrm_log_event')) {
                    peracrm_log_event($client_id, 'whatsapp_inbound', [
                        'message_id' => $message_id,
                        'message_type' => $message_type,
                        'message_preview' => mb_substr((string) $message_body, 0, 120),
                        'phone' => $phone_e164,
                        'row_id' => $message_row_id,
                    ]);

                    if ($was_created) {
                        peracrm_log_event($client_id, 'lead_created', [
                            'source' => 'whatsapp_inbound',
                            'phone' => $phone_e164,
                        ]);
                    }
                }

                $processed++;
            }
        }
    }

    return $processed;
}
