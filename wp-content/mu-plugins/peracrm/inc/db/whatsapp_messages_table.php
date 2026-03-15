<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_whatsapp_messages_table_name()
{
    return peracrm_table('peracrm_whatsapp_messages');
}

function peracrm_whatsapp_messages_create_table()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = peracrm_whatsapp_messages_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id BIGINT UNSIGNED NULL,
        phone_e164 VARCHAR(32) NOT NULL,
        whatsapp_contact_name VARCHAR(191) NULL,
        direction VARCHAR(16) NOT NULL DEFAULT 'inbound',
        message_type VARCHAR(32) NOT NULL DEFAULT 'text',
        message_body LONGTEXT NULL,
        media_url TEXT NULL,
        whatsapp_message_id VARCHAR(191) NULL,
        raw_payload_json LONGTEXT NULL,
        source VARCHAR(32) NOT NULL DEFAULT 'whatsapp',
        linked_by VARCHAR(32) NOT NULL DEFAULT 'phone',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY client_id (client_id),
        KEY phone_e164 (phone_e164),
        KEY whatsapp_message_id (whatsapp_message_id),
        KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta($sql);
}
