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
            disposition VARCHAR(32) NOT NULL DEFAULT 'none',
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
            closed_reason VARCHAR(32) NOT NULL DEFAULT 'none',
            deal_value DECIMAL(18,2) NULL,
            currency CHAR(3) NOT NULL DEFAULT 'USD',
            commission_type VARCHAR(12) NOT NULL DEFAULT 'percent',
            commission_rate DECIMAL(7,4) NULL,
            commission_amount DECIMAL(18,2) NULL,
            commission_currency CHAR(3) NOT NULL DEFAULT 'USD',
            commission_status VARCHAR(16) NOT NULL DEFAULT 'expected',
            commission_due_date DATE NULL,
            commission_paid_at DATETIME NULL,
            commission_notes TEXT NULL,
            expected_close_date DATE NULL,
            closed_at DATETIME NULL,
            owner_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY party_id (party_id),
            KEY stage (stage),
            KEY closed_reason (closed_reason),
            KEY owner_user_id (owner_user_id),
            KEY party_stage (party_id, stage),
            KEY commission_status (commission_status),
            KEY commission_due_date (commission_due_date),
            KEY owner_commission_status (owner_user_id, commission_status)
        ) {$charset_collate};";

        dbDelta($sql_notes);
        dbDelta($sql_reminders);
        dbDelta($sql_activity);
        dbDelta($sql_client_property);
        dbDelta($sql_party);
        dbDelta($sql_deals);

        if (function_exists('peracrm_push_log_create_table')) {
            peracrm_push_log_create_table();
        }

        $closed_reason_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$deals_table} LIKE %s", 'closed_reason'));
        if (!$closed_reason_exists) {
            $wpdb->query("ALTER TABLE {$deals_table} ADD COLUMN closed_reason VARCHAR(32) NOT NULL DEFAULT 'none' AFTER stage");
        }

        if ($installed_version < 2 && $target_version >= 2) {
            peracrm_migrate_legacy_status_to_party_table();
        }

        $v4_done = (int) get_option('peracrm_migration_v4_done', 0);
        if ($target_version >= 4 && ($installed_version < 4 || $v4_done !== 1)) {
            peracrm_migrate_stage_taxonomy_v4();
            update_option('peracrm_migration_v4_done', 1);
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

    if ($status !== 'closed') {
        $base['lead_pipeline_stage'] = function_exists('peracrm_map_legacy_lead_stage')
            ? peracrm_map_legacy_lead_stage($status)
            : 'new_enquiry';
    }

    if ($status === 'dormant') {
        $base['engagement_state'] = 'dormant';
    }

    if ($status === 'closed') {
        $base['engagement_state'] = 'closed';
    }

    if ($status === 'junk') {
        $base['engagement_state'] = 'closed';
        $base['disposition'] = 'junk_lead';
    }

    return $base;
}


function peracrm_migrate_legacy_party_closed_reason_to_deal($party_id, $legacy_closed_reason, $deals_table)
{
    global $wpdb;

    $party_id = (int) $party_id;
    $legacy_closed_reason = sanitize_key((string) $legacy_closed_reason);
    if ($party_id <= 0 || !in_array($legacy_closed_reason, ['lost_price', 'lost_finance', 'lost_competitor'], true)) {
        return;
    }

    $deal = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, stage, closed_reason FROM {$deals_table} WHERE party_id = %d ORDER BY updated_at DESC, id DESC LIMIT 1",
            $party_id
        ),
        ARRAY_A
    );

    if (is_array($deal) && !empty($deal['id'])) {
        $deal_stage = sanitize_key((string) ($deal['stage'] ?? ''));
        $is_closed_stage = in_array($deal_stage, ['lost', 'completed', 'closed_won', 'closed_lost'], true)
            || (function_exists('peracrm_deal_is_closed_stage') && peracrm_deal_is_closed_stage($deal_stage));

        $existing_reason = sanitize_key((string) ($deal['closed_reason'] ?? 'none'));
        if ($is_closed_stage && ($existing_reason === '' || $existing_reason === 'none')) {
            $wpdb->update(
                $deals_table,
                [
                    'closed_reason' => $legacy_closed_reason,
                    'updated_at' => peracrm_now_mysql(),
                ],
                ['id' => (int) $deal['id']],
                ['%s', '%s'],
                ['%d']
            );
        }

        return;
    }

    if (function_exists('update_post_meta')) {
        $existing_meta = (string) get_post_meta($party_id, '_peracrm_migrated_closed_reason', true);
        if ($existing_meta === '' || $existing_meta === 'none') {
            update_post_meta($party_id, '_peracrm_migrated_closed_reason', $legacy_closed_reason);
        }
    }
}

function peracrm_migrate_stage_taxonomy_v4()
{
    peracrm_with_target_blog(static function () {
        global $wpdb;

        $party_table = peracrm_table('peracrm_party');
        $deals_table = peracrm_table('peracrm_deals');

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $party_table)) !== $party_table) {
            return;
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $deals_table)) !== $deals_table) {
            return;
        }

    $legacy_party_stages = ['enquiry', 'active', 'active_search', 'deal_created', 'dormant', 'closed'];
    $legacy_deal_stages = ['qualified', 'reserved', 'closed_won', 'closed_lost'];

    $party_where = "lead_pipeline_stage IN ('" . implode("','", array_map('esc_sql', $legacy_party_stages)) . "')"
        . " OR disposition = 'junk'"
        . " OR disposition IN ('lost_price','lost_finance','lost_competitor')";

    $party_rows = $wpdb->get_results(
        "SELECT party_id, lead_pipeline_stage, engagement_state, disposition FROM {$party_table} WHERE {$party_where}",
        ARRAY_A
    );

    foreach ((array) $party_rows as $row) {
        $party_id = (int) ($row['party_id'] ?? 0);
        if ($party_id <= 0) {
            continue;
        }

        $legacy_stage_raw = (string) ($row['lead_pipeline_stage'] ?? '');
        $legacy_stage = sanitize_key($legacy_stage_raw);

        if ($legacy_stage === '' || $legacy_stage === 'closed') {
            $new_stage = 'qualified';
        } else {
            $mapped_stage = peracrm_map_legacy_lead_stage($legacy_stage);
            $stage_options = function_exists('peracrm_party_stage_options') ? peracrm_party_stage_options() : [];
            $new_stage = isset($stage_options[$mapped_stage]) ? $mapped_stage : 'qualified';
        }

        $legacy_disposition = sanitize_key((string) ($row['disposition'] ?? 'none'));
        if ($legacy_disposition === 'junk') {
            $new_disposition = 'junk_lead';
        } elseif (in_array($legacy_disposition, ['junk_lead', 'duplicate', 'none'], true)) {
            $new_disposition = $legacy_disposition;
        } elseif (in_array($legacy_disposition, ['lost_price', 'lost_finance', 'lost_competitor'], true)) {
            $new_disposition = 'none';
            peracrm_migrate_legacy_party_closed_reason_to_deal($party_id, $legacy_disposition, $deals_table);
        } else {
            $new_disposition = 'none';
        }

        $engagement = sanitize_key((string) ($row['engagement_state'] ?? 'engaged'));
        if ($legacy_stage === 'closed') {
            $engagement = 'closed';
        }
        if ($new_disposition !== 'none' && $engagement !== 'closed') {
            $engagement = 'closed';
        }

        $current_stage = sanitize_key((string) ($row['lead_pipeline_stage'] ?? ''));
        $current_engagement = sanitize_key((string) ($row['engagement_state'] ?? 'engaged'));
        $current_disposition = sanitize_key((string) ($row['disposition'] ?? 'none'));

        if ($current_stage !== $new_stage || $current_engagement !== $engagement || $current_disposition !== $new_disposition) {
            $wpdb->update(
                $party_table,
                [
                    'lead_pipeline_stage' => $new_stage,
                    'engagement_state' => $engagement,
                    'disposition' => $new_disposition,
                    'updated_at' => peracrm_now_mysql(),
                ],
                ['party_id' => $party_id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
        }
    }

    $deal_where = "stage IN ('" . implode("','", array_map('esc_sql', $legacy_deal_stages)) . "')";
    $deal_rows = $wpdb->get_results("SELECT id, stage FROM {$deals_table} WHERE {$deal_where}", ARRAY_A);
    foreach ((array) $deal_rows as $row) {
        $deal_id = (int) ($row['id'] ?? 0);
        if ($deal_id <= 0) {
            continue;
        }

        $mapped_stage = peracrm_map_legacy_deal_stage($row['stage'] ?? 'reservation_taken');
        $current_stage = sanitize_key((string) ($row['stage'] ?? ''));

        if ($current_stage !== $mapped_stage) {
            $wpdb->update(
                $deals_table,
                [
                    'stage' => $mapped_stage,
                    'updated_at' => peracrm_now_mysql(),
                ],
                ['id' => $deal_id],
                ['%s', '%s'],
                ['%d']
            );
        }
    }
    });
}


