<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_push_log_table_name()
{
    return peracrm_table('crm_push_log');
}

function peracrm_push_log_create_table()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = peracrm_push_log_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        advisor_user_id BIGINT UNSIGNED NOT NULL,
        window_end DATETIME NOT NULL,
        event_key VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_event_key (event_key),
        KEY advisor_window (advisor_user_id, window_end)
    ) {$charset_collate};";

    dbDelta($sql);
}
