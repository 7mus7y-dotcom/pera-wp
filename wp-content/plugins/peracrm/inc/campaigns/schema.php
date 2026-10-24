<?php

if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__.'/recipient-schema.php';
require_once __DIR__.'/event-schema.php';

/** Additive only. InnoDB is required for atomic evidence/current-state writes. */
function peracrm_campaigns_schema_definitions()
{
    global $wpdb;
    $mailbox = 'VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin';
    return peracrm_campaign_event_definitions() + peracrm_campaign_recipient_definitions() + peracrm_campaign_queue_definitions() + peracrm_campaigns_freeze_definitions() + peracrm_campaigns_snapshot_definitions() + peracrm_campaigns_draft_definitions() + peracrm_campaigns_phase2_definitions() + [
        'peracrm_subscribers' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(254) NOT NULL,
            normalized_email {$mailbox} NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            subscription_state VARCHAR(16) NOT NULL DEFAULT 'unknown',
            source VARCHAR(64) NOT NULL DEFAULT '',
            source_ref VARCHAR(191) NOT NULL DEFAULT '',
            evidence_ref VARCHAR(191) NOT NULL DEFAULT '',
            policy_version VARCHAR(64) NOT NULL DEFAULT '',
            recorded_at DATETIME NULL,
            confirmed_at DATETIME NULL,
            withdrawn_at DATETIME NULL,
            preferred_language VARCHAR(35) NOT NULL DEFAULT '',
            preferences_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            version BIGINT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY normalized_email (normalized_email),
            KEY client_id (client_id),
            KEY subscription_state (subscription_state)",
        'peracrm_subscription_events' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id BIGINT UNSIGNED NULL,
            normalized_email {$mailbox} NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            previous_state VARCHAR(16) NOT NULL DEFAULT '',
            subscription_state VARCHAR(16) NOT NULL DEFAULT '',
            source VARCHAR(64) NOT NULL,
            source_ref VARCHAR(191) NOT NULL DEFAULT '',
            evidence_ref VARCHAR(191) NOT NULL DEFAULT '',
            policy_version VARCHAR(64) NOT NULL DEFAULT '',
            actor_user_id BIGINT UNSIGNED NOT NULL,
            occurred_at DATETIME NOT NULL,
            recorded_at DATETIME NOT NULL,
            details_json LONGTEXT NOT NULL,
            PRIMARY KEY  (id),
            KEY mailbox_recorded (normalized_email, recorded_at),
            KEY subscriber_id (subscriber_id)",
        // One durable mailbox row serializes mutations even without a subscriber.
        'peracrm_email_suppressions' => "normalized_email {$mailbox} NOT NULL,
            unsubscribe TINYINT UNSIGNED NOT NULL DEFAULT 0,
            hard_bounce TINYINT UNSIGNED NOT NULL DEFAULT 0,
            complaint TINYINT UNSIGNED NOT NULL DEFAULT 0,
            administrative TINYINT UNSIGNED NOT NULL DEFAULT 0,
            scope VARCHAR(32) NOT NULL DEFAULT 'marketing_email',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            version BIGINT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY  (normalized_email)",
        'peracrm_email_suppression_events' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            normalized_email {$mailbox} NOT NULL,
            reason VARCHAR(24) NOT NULL,
            source VARCHAR(64) NOT NULL,
            source_ref VARCHAR(191) NOT NULL DEFAULT '',
            actor_user_id BIGINT UNSIGNED NOT NULL,
            occurred_at DATETIME NOT NULL,
            recorded_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY mailbox_recorded (normalized_email, recorded_at)",
    ];
}

/** Additive installer. Failure is contained by the normal upgrade entry point. */
function peracrm_campaigns_install_schema()
{
    global $wpdb;
    $collate = $wpdb->get_charset_collate();
    foreach (peracrm_campaigns_schema_definitions() as $name => $definition) {
        $table = peracrm_table($name);
        dbDelta("CREATE TABLE {$table} (\n            {$definition}\n        ) ENGINE=InnoDB {$collate};");
        if ($wpdb->last_error) {
            throw new RuntimeException('PeraCRM campaign schema could not be installed.');
        }
    }
    peracrm_campaigns_assert_schema();
}

/** Read-only verification, also used for sites already marked version 21. */
function peracrm_campaigns_assert_schema()
{
    global $wpdb;
    foreach (peracrm_campaigns_schema_definitions() as $name => $definition) {
        $table = peracrm_table($name);
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        preg_match_all('/^\s*([a-z_]+) (?:BIGINT|VARCHAR|DATETIME|LONGTEXT|TINYINT)/m', $definition, $matches);
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        $unique_mailbox = false;
        $keys = [];
        foreach ((array) $indexes as $index) {
            if ((int) $index['Non_unique'] === 0) {
                $keys[$index['Key_name']][] = $index;
            }
        }
        foreach ($keys as $key) {
            if (count($key) === 1 && $key[0]['Column_name'] === 'normalized_email' && empty($key[0]['Sub_part'])) {
                $unique_mailbox = true;
            }
        }
        $engine = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        $expected_keys = [
            'peracrm_campaign_ses_events' => ['scope_hash','event_key'],
            'peracrm_campaign_ses_normalizations' => ['scope_hash','receipt_id'],
            'peracrm_campaign_sns_receipts' => ['scope_hash','message_id'],
            'peracrm_campaign_recipient_identities' => ['deployment','blog_id','subscriber_id'],
            'peracrm_campaign_recipient_tokens' => ['deployment','blog_id','digest'],
            'peracrm_campaign_submission_outputs' => ['deployment','blog_id','attempt_key'],
            'peracrm_campaign_submission_attempts' => ['deployment','blog_id','attempt_key'],
            'peracrm_campaign_worker_events' => ['delivery_id', 'version'],
            'peracrm_campaign_queue_jobs' => ['frozen_revision_id'],
            'peracrm_campaign_deliveries' => ['frozen_revision_id', 'mailbox'],
            'peracrm_campaign_queue_events' => ['job_id', 'version'],
            'peracrm_campaign_freeze_attempts' => ['campaign_id', 'draft_revision'],
            'peracrm_campaign_recipient_evaluations' => ['attempt_id', 'subscriber_id'],
            'peracrm_campaign_frozen_recipients' => ['attempt_id', 'mailbox'],
            'peracrm_campaign_frozen_revisions' => ['campaign_id', 'draft_revision'],
            'peracrm_campaign_content_snapshots' => ['campaign_id', 'draft_revision', 'binding_hash'],
            'peracrm_campaign_drafts' => ['id'], 'peracrm_campaign_configuration' => ['id'],
            'peracrm_campaign_draft_revisions' => ['campaign_id', 'revision'],
            'peracrm_subscribers' => ['normalized_email'], 'peracrm_email_suppressions' => ['normalized_email'],
            'peracrm_subscriber_batches' => ['id'], 'peracrm_subscriber_tokens' => ['digest'],
            'peracrm_subscriber_import_rows' => ['batch_id', 'line_number'],
        ];
        $needs_unique = isset($expected_keys[$name]);
        if ($needs_unique) {
            $unique_mailbox = false;
            foreach ($keys as $key) {
                if (array_column($key, 'Column_name') === $expected_keys[$name] && !array_filter(array_column($key, 'Sub_part'))) { $unique_mailbox = true; }
            }
        }
        if ($name === 'peracrm_campaign_deliveries') {
            foreach (['health_lease'=>['state','lease_until','job_id'], 'health_retry'=>['state','next_attempt_at'], 'health_result'=>['state','updated_at']] as $index_name=>$required) {
                $parts=array_values(array_filter((array)$indexes,static function ($index) use ($index_name) { return $index['Key_name']===$index_name; }));
                usort($parts,static function ($a,$b) { return ($a['Seq_in_index'] ?? 0)<=>($b['Seq_in_index'] ?? 0); });
                if (array_column($parts,'Column_name')!==$required || array_filter(array_column($parts,'Sub_part'))) { throw new RuntimeException('PeraCRM health index assertion failed.'); }
            }
        }
        if ($name === 'peracrm_campaign_deliveries') {
            foreach ([['delivery_key'], ['frozen_revision_id', 'frozen_recipient_id']] as $required) {
                $found = false;
                foreach ($keys as $key) { if (array_column($key, 'Column_name') === $required && !array_filter(array_column($key, 'Sub_part'))) { $found = true; } }
                if (!$found) { throw new RuntimeException('PeraCRM delivery uniqueness assertion failed.'); }
            }
        }
        if ($name === 'peracrm_campaign_frozen_revisions') {
            $unique_attempt = false;
            foreach ($keys as $key) {
                if (array_column($key, 'Column_name') === ['attempt_id'] && !array_filter(array_column($key, 'Sub_part'))) { $unique_attempt = true; }
            }
            if (!$unique_attempt) { throw new RuntimeException('PeraCRM campaign publication uniqueness assertion failed.'); }
        }
        if (array_diff($matches[1], (array) $columns) || ($needs_unique && !$unique_mailbox)
            || strtolower($engine['Engine'] ?? '') !== 'innodb') {
            throw new RuntimeException('PeraCRM campaign schema assertions failed.');
        }
    }
}
