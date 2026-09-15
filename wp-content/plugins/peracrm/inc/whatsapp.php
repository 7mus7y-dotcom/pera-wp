<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_whatsapp_default_settings()
{
    return [
        'enabled' => 0,
        'phone_number_id' => '',
        'waba_id' => '',
        'access_token' => '',
        'verify_token' => '',
        'app_secret' => '',
        'graph_api_version' => 'v22.0',
        'test_mode' => 0,
    ];
}

function peracrm_whatsapp_get_settings()
{
    $saved = get_option('peracrm_whatsapp_settings', []);
    if (!is_array($saved)) {
        $saved = [];
    }

    $settings = wp_parse_args($saved, peracrm_whatsapp_default_settings());
    $overrides = [
        'enabled' => 'PERACRM_WHATSAPP_ENABLED',
        'test_mode' => 'PERACRM_WHATSAPP_TEST_MODE',
        'phone_number_id' => 'PERACRM_WHATSAPP_PHONE_NUMBER_ID',
        'waba_id' => 'PERACRM_WHATSAPP_WABA_ID',
        'access_token' => 'PERACRM_WHATSAPP_ACCESS_TOKEN',
        'verify_token' => 'PERACRM_WHATSAPP_VERIFY_TOKEN',
        'app_secret' => 'PERACRM_WHATSAPP_APP_SECRET',
        'graph_api_version' => 'PERACRM_WHATSAPP_GRAPH_API_VERSION',
    ];
    foreach ($overrides as $key => $constant) {
        if (defined($constant)) {
            $settings[$key] = constant($constant);
        }
    }
    return $settings;
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
        'waba_id' => sanitize_text_field((string) ($input['waba_id'] ?? '')),
        'verify_token' => $existing['verify_token'],
        'graph_api_version' => sanitize_text_field((string) ($input['graph_api_version'] ?? ($existing['graph_api_version'] ?? 'v22.0'))),
        'test_mode' => !empty($input['test_mode']) ? 1 : 0,
        'access_token' => $existing['access_token'],
        'app_secret' => $existing['app_secret'],
    ];

    if (isset($input['access_token'])) {
        $candidate = trim((string) $input['access_token']);
        if ($candidate !== '') {
            $settings['access_token'] = sanitize_text_field($candidate);
        }
    }

    if (isset($input['verify_token'])) {
        $candidate = trim((string) $input['verify_token']);
        if ($candidate !== '') {
            $settings['verify_token'] = sanitize_text_field($candidate);
        }
    }

    if (isset($input['app_secret'])) {
        $candidate = trim((string) $input['app_secret']);
        if ($candidate !== '') {
            $settings['app_secret'] = sanitize_text_field($candidate);
        }
    }

    update_option('peracrm_whatsapp_settings', $settings, false);

    return $settings;
}

function peracrm_whatsapp_message_lock_option_name($message_id)
{
    $message_id = sanitize_text_field((string) $message_id);
    if ($message_id === '') {
        return '';
    }

    return 'peracrm_wa_msg_lock_' . md5($message_id);
}

function peracrm_whatsapp_message_lock_ttl()
{
    return 120;
}

function peracrm_whatsapp_message_lock_value()
{
    return [
        'created_at' => time(),
        'request_marker' => sanitize_text_field((string) wp_generate_uuid4()),
    ];
}

function peracrm_whatsapp_extract_message_lock_created_at($lock_value)
{
    if (is_numeric($lock_value)) {
        return (int) $lock_value;
    }

    if (!is_array($lock_value)) {
        return 0;
    }

    $created_at = $lock_value['created_at'] ?? 0;
    if (!is_numeric($created_at)) {
        return 0;
    }

    return (int) $created_at;
}

function peracrm_whatsapp_is_message_lock_stale($lock_value)
{
    $created_at = peracrm_whatsapp_extract_message_lock_created_at($lock_value);
    if ($created_at <= 0) {
        return true;
    }

    $ttl = max(1, (int) peracrm_whatsapp_message_lock_ttl());
    return (time() - $created_at) > $ttl;
}

function peracrm_whatsapp_acquire_message_lock($message_id)
{
    $lock_name = peracrm_whatsapp_message_lock_option_name($message_id);
    if ($lock_name === '') {
        return false;
    }

    $lock_value = peracrm_whatsapp_message_lock_value();
    if (add_option($lock_name, $lock_value, '', false)) {
        return true;
    }

    $existing_lock_value = get_option($lock_name, null);
    if (!peracrm_whatsapp_is_message_lock_stale($existing_lock_value)) {
        return false;
    }

    delete_option($lock_name);
    return (bool) add_option($lock_name, $lock_value, '', false);
}

function peracrm_whatsapp_release_message_lock($message_id)
{
    $lock_name = peracrm_whatsapp_message_lock_option_name($message_id);
    if ($lock_name === '') {
        return;
    }

    delete_option($lock_name);
}

function peracrm_whatsapp_log($message, array $context = [])
{
    $safe = [];
    foreach ($context as $key => $value) {
        $k = sanitize_key((string) $key);
        if (in_array($k, ['access_token', 'verify_token', 'app_secret', 'message', 'message_body', 'raw_payload'], true)) {
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

function peracrm_whatsapp_phone_match_candidates($phone_raw)
{
    $normalized = peracrm_whatsapp_normalize_phone($phone_raw);
    if ($normalized === '') {
        return [];
    }

    $candidates = [$normalized];
    $digits = preg_replace('/\D+/', '', $normalized);
    if ($digits !== '') {
        $candidates[] = $digits;

        if (strpos($digits, '90') === 0 && strlen($digits) === 12) {
            $candidates[] = '0' . substr($digits, 2);
            $candidates[] = substr($digits, 2);
        }
    }

    $candidates = array_values(array_unique(array_filter(array_map('strval', $candidates))));
    return $candidates;
}

function peracrm_whatsapp_find_client_by_phone($phone_e164)
{
    $candidates = peracrm_whatsapp_phone_match_candidates($phone_e164);
    if (empty($candidates)) {
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
                'value' => $candidates,
                'compare' => 'IN',
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

    $default_advisor_user_id = 0;
    if (function_exists('peracrm_ingest_default_admin_user_id')) {
        $default_advisor_user_id = (int) peracrm_ingest_default_admin_user_id();
    } else {
        $admin_ids = get_users([
            'role' => 'administrator',
            'fields' => 'ids',
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
        $default_advisor_user_id = !empty($admin_ids) ? (int) $admin_ids[0] : 0;
    }

    if (function_exists('peracrm_get_default_assignee_user_id')) {
        $default_advisor_user_id = (int) peracrm_get_default_assignee_user_id($default_advisor_user_id);
    }

    if ($default_advisor_user_id > 0) {
        if (function_exists('peracrm_ingest_enforce_advisor_assignment')) {
            peracrm_ingest_enforce_advisor_assignment($post_id, $default_advisor_user_id);
        } else {
            update_post_meta($post_id, 'assigned_advisor_user_id', $default_advisor_user_id);
            update_post_meta($post_id, 'crm_assigned_advisor', $default_advisor_user_id);
        }
    }

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

function peracrm_whatsapp_find_message_row_id_by_message_id($whatsapp_message_id)
{
    global $wpdb;

    $whatsapp_message_id = sanitize_text_field((string) $whatsapp_message_id);
    if ($whatsapp_message_id === '') {
        return 0;
    }

    $table = peracrm_whatsapp_messages_table_name();

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE whatsapp_message_id = %s ORDER BY id DESC LIMIT 1",
        $whatsapp_message_id
    ));
}

function peracrm_whatsapp_store_message(array $record)
{
    $result = peracrm_whatsapp_store_message_result($record);

    return (int) ($result['row_id'] ?? 0);
}

function peracrm_whatsapp_store_message_result(array $record)
{
    global $wpdb;

    $table = peracrm_whatsapp_messages_table_name();
    $whatsapp_message_id = sanitize_text_field((string) ($record['whatsapp_message_id'] ?? ''));

    if ($whatsapp_message_id !== '') {
        $existing_id = peracrm_whatsapp_find_message_row_id_by_message_id($whatsapp_message_id);
        if ($existing_id > 0) {
            return [
                'row_id' => (int) $existing_id,
                'inserted' => false,
            ];
        }
    }

    $inserted = $wpdb->insert($table, [
        'client_id' => !empty($record['client_id']) ? (int) $record['client_id'] : null,
        'phone_e164' => sanitize_text_field((string) ($record['phone_e164'] ?? '')),
        'sender_wa_id' => sanitize_text_field((string) ($record['sender_wa_id'] ?? '')),
        'recipient_phone_number_id' => sanitize_text_field((string) ($record['recipient_phone_number_id'] ?? '')),
        'whatsapp_contact_name' => sanitize_text_field((string) ($record['whatsapp_contact_name'] ?? '')),
        'direction' => sanitize_key((string) ($record['direction'] ?? 'inbound')),
        'message_type' => sanitize_key((string) ($record['message_type'] ?? 'text')),
        'message_body' => isset($record['message_body']) ? sanitize_textarea_field((string) $record['message_body']) : null,
        'media_url' => isset($record['media_url']) ? esc_url_raw((string) ($record['media_url'] ?? '')) : null,
        'whatsapp_message_id' => $whatsapp_message_id,
        'message_status' => sanitize_key((string) ($record['message_status'] ?? 'received')),
        'meta_timestamp' => !empty($record['meta_timestamp']) ? sanitize_text_field((string) $record['meta_timestamp']) : null,
        'raw_payload_json' => isset($record['raw_payload_json']) ? (string) $record['raw_payload_json'] : '{}',
        'source' => sanitize_key((string) ($record['source'] ?? 'whatsapp')),
        'linked_by' => sanitize_key((string) ($record['linked_by'] ?? 'phone')),
        'created_at' => peracrm_now_mysql(),
    ], [
        '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
    ]);

    if (!$inserted && $whatsapp_message_id !== '') {
        $existing_id = peracrm_whatsapp_find_message_row_id_by_message_id($whatsapp_message_id);
        if ($existing_id > 0) {
            return [
                'row_id' => (int) $existing_id,
                'inserted' => false,
            ];
        }
    }

    if (!$inserted) {
        return [
            'row_id' => 0,
            'inserted' => false,
        ];
    }

    return [
        'row_id' => (int) $wpdb->insert_id,
        'inserted' => true,
    ];
}

function peracrm_whatsapp_count_messages()
{
    global $wpdb;
    $table = peracrm_whatsapp_messages_table_name();

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
}

function peracrm_whatsapp_get_messages(array $args = [])
{
    global $wpdb;

    $per_page = isset($args['per_page']) ? max(1, (int) $args['per_page']) : 20;
    $paged = isset($args['paged']) ? max(1, (int) $args['paged']) : 1;
    $table = peracrm_whatsapp_messages_table_name();
    $client_id = isset($args['client_id']) ? absint($args['client_id']) : 0;
    $where = $client_id > 0 ? $wpdb->prepare(' WHERE client_id = %d', $client_id) : '';
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}{$where}");
    $total_pages = max(1, (int) ceil($total / $per_page));
    $paged = min($paged, $total_pages);
    $offset = ($paged - 1) * $per_page;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, client_id, phone_e164, sender_wa_id, recipient_phone_number_id, whatsapp_contact_name, direction, message_type, message_body, whatsapp_message_id, message_status, meta_timestamp, created_at FROM {$table}{$where} ORDER BY COALESCE(meta_timestamp, created_at) ASC, id ASC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ),
        ARRAY_A
    );

    return [
        'rows' => is_array($rows) ? $rows : [],
        'pagination' => [
            'total' => $total,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
            'paged' => $paged,
        ],
    ];
}

function peracrm_whatsapp_delete_messages_by_ids(array $ids)
{
    global $wpdb;

    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    if (empty($ids)) {
        return ['deleted' => 0];
    }

    $table = peracrm_whatsapp_messages_table_name();
    $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
    $query = $wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids);
    $deleted = $wpdb->query($query);

    return [
        'deleted' => max(0, (int) $deleted),
    ];
}

function peracrm_whatsapp_user_can_access_client($client_id, $user_id = 0)
{
    $client_id = absint($client_id);
    $user_id = $user_id > 0 ? absint($user_id) : get_current_user_id();
    if ($client_id <= 0 || $user_id <= 0 || get_post_type($client_id) !== 'crm_client') {
        return false;
    }
    if (user_can($user_id, 'manage_options') || user_can($user_id, 'peracrm_manage_all_reminders')) {
        return true;
    }
    if (!user_can($user_id, 'edit_crm_clients')) {
        return false;
    }
    return function_exists('peracrm_client_get_assigned_advisor_id')
        && (int) peracrm_client_get_assigned_advisor_id($client_id) === $user_id;
}

function peracrm_whatsapp_client_phone($client_id)
{
    $phone = (string) get_post_meta((int) $client_id, '_peracrm_phone', true);
    if ($phone === '') {
        $phone = (string) get_post_meta((int) $client_id, 'crm_phone', true);
    }
    return peracrm_whatsapp_normalize_phone($phone);
}

function peracrm_whatsapp_configuration_errors()
{
    $settings = peracrm_whatsapp_get_settings();
    $missing = [];
    foreach (['phone_number_id', 'access_token', 'verify_token', 'app_secret'] as $key) {
        if (trim((string) ($settings[$key] ?? '')) === '') {
            $missing[] = $key;
        }
    }
    if (empty($settings['enabled'])) $missing[] = 'enabled';
    if (empty($settings['test_mode'])) $missing[] = 'test_mode';
    return $missing;
}

function peracrm_whatsapp_meta_datetime($timestamp)
{
    $timestamp = filter_var($timestamp, FILTER_VALIDATE_INT);
    return $timestamp && $timestamp > 0 ? gmdate('Y-m-d H:i:s', $timestamp) : null;
}

function peracrm_whatsapp_process_inbound_payload(array $payload)
{
    $settings = peracrm_whatsapp_get_settings();
    $processed = 0;
    foreach ((array) ($payload['entry'] ?? []) as $entry) {
        if (!empty($settings['waba_id']) && !hash_equals((string) $settings['waba_id'], (string) ($entry['id'] ?? ''))) continue;
        foreach ((array) ($entry['changes'] ?? []) as $change) {
            if (($change['field'] ?? '') !== 'messages') continue;
            $value = is_array($change['value'] ?? null) ? $change['value'] : [];
            $recipient_id = sanitize_text_field((string) ($value['metadata']['phone_number_id'] ?? ''));
            if ($recipient_id === '' || !hash_equals((string) $settings['phone_number_id'], $recipient_id)) continue;
            $contacts = (array) ($value['contacts'] ?? []);
            $names = [];
            foreach ($contacts as $contact) {
                if (!empty($contact['wa_id'])) $names[(string) $contact['wa_id']] = sanitize_text_field((string) ($contact['profile']['name'] ?? ''));
            }
            foreach ((array) ($value['messages'] ?? []) as $message) {
                if (!is_array($message) || ($message['type'] ?? '') !== 'text') continue;
                $wamid = sanitize_text_field((string) ($message['id'] ?? ''));
                $wa_id = preg_replace('/\D+/', '', (string) ($message['from'] ?? ''));
                $body = isset($message['text']['body']) ? trim((string) $message['text']['body']) : '';
                if ($wamid === '' || $wa_id === '' || $body === '') continue;
                $phone = peracrm_whatsapp_normalize_phone('+' . $wa_id);
                $client_id = peracrm_whatsapp_find_client_by_phone($phone); // Unknown senders remain safely unlinked.
                $write = peracrm_whatsapp_store_message_result([
                    'client_id' => $client_id, 'phone_e164' => $phone, 'sender_wa_id' => $wa_id,
                    'recipient_phone_number_id' => $recipient_id, 'whatsapp_contact_name' => $names[$wa_id] ?? '',
                    'direction' => 'inbound', 'message_type' => 'text', 'message_body' => $body,
                    'whatsapp_message_id' => $wamid, 'message_status' => 'received',
                    'meta_timestamp' => peracrm_whatsapp_meta_datetime($message['timestamp'] ?? 0),
                    'raw_payload_json' => '{}', 'source' => 'whatsapp', 'linked_by' => $client_id ? 'phone' : 'unlinked',
                ]);
                if (empty($write['inserted'])) continue;
                $processed++;
                if ($client_id && function_exists('peracrm_log_event')) {
                    peracrm_log_event($client_id, 'whatsapp_inbound', ['message_id' => $wamid, 'row_id' => (int) $write['row_id']]);
                }
            }
            foreach ((array) ($value['statuses'] ?? []) as $status) {
                $wamid = sanitize_text_field((string) ($status['id'] ?? ''));
                $state = sanitize_key((string) ($status['status'] ?? ''));
                if ($wamid !== '' && in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
                    global $wpdb;
                    $wpdb->update(peracrm_whatsapp_messages_table_name(), ['message_status' => $state], ['whatsapp_message_id' => $wamid], ['%s'], ['%s']);
                }
            }
        }
    }
    return $processed;
}

function peracrm_whatsapp_send_client_text($client_id, $body)
{
    $client_id = absint($client_id);
    $body = trim(sanitize_textarea_field((string) $body));
    if (!peracrm_whatsapp_user_can_access_client($client_id)) return new WP_Error('forbidden', 'Client access denied.', ['status' => 403]);
    if ($body === '' || mb_strlen($body) > 4096) return new WP_Error('invalid_message', 'A message of 1–4096 characters is required.', ['status' => 400]);
    $missing = peracrm_whatsapp_configuration_errors();
    if ($missing) return new WP_Error('not_configured', 'WhatsApp TEST mode is not fully configured.', ['status' => 503]);
    $settings = peracrm_whatsapp_get_settings();
    $phone = peracrm_whatsapp_client_phone($client_id);
    if ($phone === '') return new WP_Error('invalid_recipient', 'The selected client has no valid WhatsApp phone number.', ['status' => 400]);
    $recipient = preg_replace('/\D+/', '', $phone);
    $version = preg_match('/^v\d+\.\d+$/', (string) $settings['graph_api_version']) ? $settings['graph_api_version'] : 'v22.0';
    $response = wp_remote_post('https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode((string) $settings['phone_number_id']) . '/messages', [
        'timeout' => 15,
        'headers' => ['Authorization' => 'Bearer ' . $settings['access_token'], 'Content-Type' => 'application/json'],
        'body' => wp_json_encode(['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $recipient, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $body]]),
    ]);
    if (is_wp_error($response)) return new WP_Error('meta_unavailable', 'Meta could not be reached.', ['status' => 502]);
    $code = (int) wp_remote_retrieve_response_code($response);
    $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
    $wamid = sanitize_text_field((string) ($decoded['messages'][0]['id'] ?? ''));
    if ($code < 200 || $code >= 300 || $wamid === '') return new WP_Error('meta_rejected', 'Meta rejected the message request.', ['status' => 502]);
    $write = peracrm_whatsapp_store_message_result([
        'client_id' => $client_id, 'phone_e164' => $phone, 'recipient_phone_number_id' => $settings['phone_number_id'],
        'direction' => 'outbound', 'message_type' => 'text', 'message_body' => $body,
        'whatsapp_message_id' => $wamid, 'message_status' => 'sent', 'meta_timestamp' => current_time('mysql', true),
        'raw_payload_json' => '{}', 'source' => 'whatsapp', 'linked_by' => 'client',
    ]);
    if (empty($write['row_id'])) return new WP_Error('persistence_failed', 'Meta may already have accepted this message, but CRM persistence failed. Delivery state is unknown; do not retry automatically. Verify the recipient and Meta history first.', ['status' => 500]);
    if (function_exists('peracrm_log_event')) peracrm_log_event($client_id, 'whatsapp_outbound', ['message_id' => $wamid, 'row_id' => (int) $write['row_id']]);
    return ['wamid' => $wamid, 'row_id' => (int) $write['row_id']];
}

function peracrm_whatsapp_render_client_conversation($client_id)
{
    if (!peracrm_whatsapp_user_can_access_client($client_id)) return;
    $settings = peracrm_whatsapp_get_settings();
    $phone = peracrm_whatsapp_client_phone($client_id);
    echo '<section class="crm-section crm-whatsapp" data-peracrm-whatsapp-conversation data-client-id="' . esc_attr((string) $client_id) . '">';
    echo '<header class="crm-section__header"><div class="crm-section__heading-group"><h3 class="crm-section__title">' . esc_html__('WhatsApp conversation', 'peracrm') . '</h3>';
    echo '<p class="crm-section__description"><strong class="crm-whatsapp__test">' . esc_html__('META TEST MODE', 'peracrm') . '</strong> ' . esc_html($phone !== '' ? $phone : __('Client phone missing', 'peracrm')) . '</p></div></header>';
    echo '<div class="crm-section__body"><div class="crm-whatsapp__messages" data-wa-messages aria-live="polite"></div><p data-wa-feedback></p>';
    echo '<form class="crm-whatsapp__composer" data-wa-composer><label class="screen-reader-text" for="peracrm-wa-message">' . esc_html__('WhatsApp message', 'peracrm') . '</label><textarea id="peracrm-wa-message" name="message" rows="3" maxlength="4096" required></textarea><button class="btn btn--green" type="submit">' . esc_html__('Send WhatsApp text', 'peracrm') . '</button><button class="btn btn--ghost" type="button" data-wa-refresh>' . esc_html__('Refresh', 'peracrm') . '</button></form></div></section>';
}
