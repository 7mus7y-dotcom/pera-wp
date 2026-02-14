<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_push_log_table_name()
{
    return peracrm_table('crm_push_log');
}

function peracrm_push_log_table_version()
{
    return 2;
}

function peracrm_push_log_columns()
{
    static $columns = null;

    if (is_array($columns)) {
        return $columns;
    }

    global $wpdb;

    $table = peracrm_push_log_table_name();
    $columns = [];
    $results = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
    if (!is_array($results)) {
        return [];
    }

    foreach ($results as $row) {
        $field = isset($row['Field']) ? (string) $row['Field'] : '';
        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    return $columns;
}

function peracrm_push_log_create_table()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = peracrm_push_log_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        advisor_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        window_end DATETIME NULL,
        event_key VARCHAR(100) NOT NULL DEFAULT '',
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        endpoint_hash CHAR(64) NOT NULL DEFAULT '',
        endpoint TEXT NULL,
        payload_type VARCHAR(40) NOT NULL DEFAULT '',
        payload_json LONGTEXT NULL,
        window_key VARCHAR(40) NOT NULL DEFAULT '',
        status_code SMALLINT NOT NULL DEFAULT 0,
        ok TINYINT(1) NOT NULL DEFAULT 0,
        response_body TEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_event_key (event_key),
        KEY advisor_window (advisor_user_id, window_end),
        KEY user_window (user_id, window_key),
        KEY payload_type_created (payload_type, created_at),
        KEY endpoint_hash (endpoint_hash)
    ) {$charset_collate};";

    dbDelta($sql);
    update_option('peracrm_push_log_table_version', peracrm_push_log_table_version());
}
