<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_notification_log_table_name()
{
    return peracrm_table('peracrm_notification_log');
}

function peracrm_notification_log_create_table()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = peracrm_notification_log_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        event_type VARCHAR(64) NOT NULL DEFAULT '',
        source VARCHAR(64) NOT NULL DEFAULT '',
        source_event_id VARCHAR(191) NULL,
        fingerprint VARCHAR(191) NULL,
        event_key CHAR(64) NOT NULL DEFAULT '',
        client_id BIGINT UNSIGNED NULL,
        enquiry_id VARCHAR(191) NULL,
        channel VARCHAR(32) NOT NULL DEFAULT '',
        recipient VARCHAR(191) NOT NULL DEFAULT '',
        status VARCHAR(32) NOT NULL DEFAULT '',
        provider_code VARCHAR(64) NULL,
        http_status SMALLINT NULL,
        response_excerpt TEXT NULL,
        error_message TEXT NULL,
        meta_json LONGTEXT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_event_claim (event_key, channel),
        KEY event_type_created (event_type, created_at),
        KEY source_created (source, created_at),
        KEY client_created (client_id, created_at),
        KEY status_created (status, created_at)
    ) {$charset_collate};";

    dbDelta($sql);
}

function peracrm_notification_log_insert(array $row)
{
    global $wpdb;

    peracrm_notification_log_ensure_table();

    $table = peracrm_notification_log_table_name();

    $meta_json = null;
    if (array_key_exists('meta', $row) && $row['meta'] !== null) {
        $meta_json = wp_json_encode($row['meta']);
    }

    $data = [
        'created_at' => peracrm_now_mysql(),
        'event_type' => sanitize_key((string) ($row['event_type'] ?? '')),
        'source' => sanitize_key((string) ($row['source'] ?? '')),
        'source_event_id' => peracrm_notification_log_nullable_text($row['source_event_id'] ?? null, 191),
        'fingerprint' => peracrm_notification_log_nullable_text($row['fingerprint'] ?? null, 191),
        'event_key' => sanitize_text_field((string) ($row['event_key'] ?? '')),
        'client_id' => peracrm_notification_log_nullable_int($row['client_id'] ?? null),
        'enquiry_id' => peracrm_notification_log_nullable_text($row['enquiry_id'] ?? null, 191),
        'channel' => sanitize_key((string) ($row['channel'] ?? '')),
        'recipient' => sanitize_text_field((string) ($row['recipient'] ?? '')),
        'status' => sanitize_key((string) ($row['status'] ?? '')),
        'provider_code' => peracrm_notification_log_nullable_text($row['provider_code'] ?? null, 64),
        'http_status' => peracrm_notification_log_nullable_int($row['http_status'] ?? null),
        'response_excerpt' => peracrm_notification_log_nullable_text($row['response_excerpt'] ?? null, 1000),
        'error_message' => peracrm_notification_log_nullable_text($row['error_message'] ?? null, 1000),
        'meta_json' => $meta_json,
    ];

    $inserted = $wpdb->insert($table, $data);

    if ($inserted === false) {
        return [
            'ok' => false,
            'id' => 0,
            'error' => (string) $wpdb->last_error,
        ];
    }

    return [
        'ok' => true,
        'id' => (int) $wpdb->insert_id,
        'error' => '',
    ];
}

function peracrm_notification_log_ensure_table()
{
    static $ensured = [];
    $blog_id = (int) get_current_blog_id();
    if (!empty($ensured[$blog_id])) {
        return true;
    }

    global $wpdb;
    $table = peracrm_notification_log_table_name();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    if (!$exists) {
        peracrm_notification_log_create_table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    if ($exists) {
        $ensured[$blog_id] = true;
    }

    return $exists;
}

function peracrm_notification_log_claim_event(array $event)
{
    $claim = peracrm_notification_log_insert([
        'event_type' => (string) ($event['event_type'] ?? ''),
        'source' => (string) ($event['source'] ?? ''),
        'source_event_id' => $event['source_event_id'] ?? null,
        'fingerprint' => $event['fingerprint'] ?? null,
        'event_key' => (string) ($event['event_key'] ?? ''),
        'client_id' => (int) ($event['client_id'] ?? 0),
        'enquiry_id' => $event['enquiry_id'] ?? null,
        'channel' => 'event_claim',
        'recipient' => '',
        'status' => 'claimed',
        'meta' => $event,
    ]);

    if (!empty($claim['ok'])) {
        return [
            'claimed' => true,
            'claim_id' => (int) $claim['id'],
            'reason' => '',
        ];
    }

    $error = (string) ($claim['error'] ?? '');
    $duplicate = stripos($error, 'Duplicate entry') !== false;

    if ($duplicate) {
        peracrm_notification_log_insert([
            'event_type' => (string) ($event['event_type'] ?? ''),
            'source' => (string) ($event['source'] ?? ''),
            'source_event_id' => $event['source_event_id'] ?? null,
            'fingerprint' => $event['fingerprint'] ?? null,
            'event_key' => (string) ($event['event_key'] ?? ''),
            'client_id' => (int) ($event['client_id'] ?? 0),
            'enquiry_id' => $event['enquiry_id'] ?? null,
            'channel' => 'event_duplicate',
            'recipient' => '',
            'status' => 'skipped_duplicate',
            'error_message' => 'Duplicate event claim suppressed',
            'meta' => $event,
        ]);

        return [
            'claimed' => false,
            'claim_id' => 0,
            'reason' => 'duplicate',
        ];
    }

    peracrm_notification_log_insert([
        'event_type' => (string) ($event['event_type'] ?? ''),
        'source' => (string) ($event['source'] ?? ''),
        'source_event_id' => $event['source_event_id'] ?? null,
        'fingerprint' => $event['fingerprint'] ?? null,
        'event_key' => (string) ($event['event_key'] ?? ''),
        'client_id' => (int) ($event['client_id'] ?? 0),
        'enquiry_id' => $event['enquiry_id'] ?? null,
        'channel' => 'event_claim_error',
        'recipient' => '',
        'status' => 'failed',
        'error_message' => $error,
        'meta' => $event,
    ]);

    return [
        'claimed' => false,
        'claim_id' => 0,
        'reason' => 'claim_error',
    ];
}

function peracrm_notification_log_nullable_text($value, $max_len = 191)
{
    if ($value === null) {
        return null;
    }

    $text = sanitize_text_field((string) $value);
    if ($text === '') {
        return null;
    }

    if (strlen($text) > (int) $max_len) {
        $text = substr($text, 0, (int) $max_len);
    }

    return $text;
}

function peracrm_notification_log_nullable_int($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return (int) $value;
}

function peracrm_notification_log_recent($limit = 20)
{
    global $wpdb;

    $limit = max(1, (int) $limit);
    if (!peracrm_notification_log_ensure_table()) {
        return [];
    }

    $table = peracrm_notification_log_table_name();
    $query = $wpdb->prepare(
        "SELECT id, created_at, event_type, source, source_event_id, client_id, channel, recipient, status, provider_code, http_status, error_message
         FROM {$table}
         ORDER BY id DESC
         LIMIT %d",
        $limit
    );

    $rows = $wpdb->get_results($query, ARRAY_A);
    return is_array($rows) ? $rows : [];
}
