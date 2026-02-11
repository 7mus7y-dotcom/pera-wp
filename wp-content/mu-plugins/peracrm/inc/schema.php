<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_maybe_upgrade_schema()
{
    peracrm_with_target_blog(static function () {
        $installed = (int) get_option('peracrm_schema_version', 0);
        if ($installed >= PERACRM_SCHEMA_VERSION) {
            return;
        }

        peracrm_upgrade_schema_to(PERACRM_SCHEMA_VERSION, $installed);
        update_option('peracrm_schema_version', PERACRM_SCHEMA_VERSION);
    });
}

function peracrm_upgrade_schema_to($target_version, $installed_version = 0)
{
    $target_version = (int) $target_version;
    $installed_version = (int) $installed_version;

    if ($target_version < 1) {
        return;
    }

    peracrm_with_target_blog(static function () use ($target_version, $installed_version) {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $notes_table = peracrm_table('crm_notes');
        $reminders_table = peracrm_table('crm_reminders');
        $activity_table = peracrm_table('crm_activity');
        $client_property_table = peracrm_table('crm_client_property');
        $party_table = peracrm_table('peracrm_party');
        $deals_table = peracrm_table('peracrm_deals');

        $sql_notes = "CREATE TABLE {$notes_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            advisor_user_id BIGINT UNSIGNED NOT NULL,
            note_body LONGTEXT NOT NULL,
            visibility VARCHAR(20) NOT NULL DEFAULT 'internal',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY client_created (client_id, created_at),
            KEY advisor_created (advisor_user_id, created_at)
        ) {$charset_collate};";

        $sql_reminders = "CREATE TABLE {$reminders_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            advisor_user_id BIGINT UNSIGNED NOT NULL,
            due_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            note TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY advisor_due_status (advisor_user_id, status, due_at),
            KEY client_status_due (client_id, status, due_at)
        ) {$charset_collate};";

        $sql_activity = "CREATE TABLE {$activity_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY client_created (client_id, created_at),
            KEY type_created (event_type, created_at)
        ) {$charset_collate};";

        $sql_client_property = "CREATE TABLE {$client_property_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            property_id BIGINT UNSIGNED NOT NULL,
            relation_type VARCHAR(30) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_client_property_type (client_id, property_id, relation_type),
            KEY client_rel (client_id, relation_type, created_at),
            KEY property_rel (property_id, relation_type, created_at)
        ) {$charset_collate};";

        $sql_party = "CREATE TABLE {$party_table} (
            party_id BIGINT UNSIGNED NOT NULL,
            lead_pipeline_stage VARCHAR(32) NOT NULL DEFAULT 'new_enquiry',
            engagement_state VARCHAR(16) NOT NULL DEFAULT 'engaged',
            disposition VARCHAR(16) NOT NULL DEFAULT 'none',
            lead_stage_updated_at DATETIME NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (party_id),
            KEY lead_pipeline_stage (lead_pipeline_stage),
            KEY engagement_state (engagement_state),
            KEY disposition (disposition)
        ) {$charset_collate};";

        $sql_deals = "CREATE TABLE {$deals_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            party_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            primary_property_id BIGINT UNSIGNED NULL,
            stage VARCHAR(24) NOT NULL,
            deal_value DECIMAL(18,2) NULL,
            currency CHAR(3) NOT NULL DEFAULT 'USD',
            expected_close_date DATE NULL,
            closed_at DATETIME NULL,
            owner_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY party_id (party_id),
            KEY stage (stage),
            KEY owner_user_id (owner_user_id),
            KEY party_stage (party_id, stage)
        ) {$charset_collate};";

        dbDelta($sql_notes);
        dbDelta($sql_reminders);
        dbDelta($sql_activity);
        dbDelta($sql_client_property);
        dbDelta($sql_party);
        dbDelta($sql_deals);

        if ($installed_version < 2 && $target_version >= 2) {
            peracrm_migrate_legacy_status_to_party_table();
        }
    });
}

function peracrm_migrate_legacy_status_to_party_table()
{
    if (!function_exists('get_posts') || !function_exists('peracrm_party_upsert_status')) {
        return;
    }

    $ids = get_posts([
        'post_type' => 'crm_client',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    if (empty($ids)) {
        return;
    }

    foreach ($ids as $client_id) {
        $client_id = (int) $client_id;
        if ($client_id <= 0) {
            continue;
        }

        $legacy_status = get_post_meta($client_id, '_peracrm_status', true);
        if ($legacy_status === '') {
            $legacy_status = get_post_meta($client_id, 'crm_status', true);
        }

        $mapped = peracrm_map_legacy_status_to_party_fields($legacy_status);
        peracrm_party_upsert_status($client_id, $mapped);
    }
}

function peracrm_map_legacy_status_to_party_fields($legacy_status)
{
    $status = strtolower(trim((string) $legacy_status));

    $base = [
        'lead_pipeline_stage' => 'new_enquiry',
        'engagement_state' => 'engaged',
        'disposition' => 'none',
        'lead_stage_updated_at' => peracrm_now_mysql(),
    ];

    if (in_array($status, ['enquiry', 'new_enquiry'], true)) {
        return $base;
    }

    if ($status === 'active') {
        $base['lead_pipeline_stage'] = 'qualified';
        return $base;
    }

    if ($status === 'dormant') {
        $base['lead_pipeline_stage'] = 'qualified';
        $base['engagement_state'] = 'dormant';
        return $base;
    }

    if ($status === 'closed') {
        $base['lead_pipeline_stage'] = 'qualified';
        $base['engagement_state'] = 'dormant';
        return $base;
    }

    return $base;
}
