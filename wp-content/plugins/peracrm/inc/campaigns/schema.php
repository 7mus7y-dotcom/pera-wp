<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Additive only. InnoDB is required for atomic evidence/current-state writes. */
function peracrm_campaigns_install_schema()
{
    global $wpdb;
    $collate = $wpdb->get_charset_collate();
    $mailbox = 'VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin';
    $definitions = [
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
    foreach ($definitions as $name => $definition) {
        $table = peracrm_table($name);
        dbDelta("CREATE TABLE {$table} (\n            {$definition}\n        ) ENGINE=InnoDB {$collate};");
        // dbDelta does not throw on SQL failure. Do not advance the schema marker
        // until every required column, unique mailbox key and engine is present.
        if ($wpdb->last_error) {
            throw new RuntimeException('PeraCRM campaign schema could not be installed.');
        }
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
        $needs_unique = in_array($name, ['peracrm_subscribers', 'peracrm_email_suppressions'], true);
        if (array_diff($matches[1], (array) $columns) || ($needs_unique && !$unique_mailbox)
            || strtolower($engine['Engine'] ?? '') !== 'innodb') {
            throw new RuntimeException('PeraCRM campaign schema assertions failed.');
        }
    }
}
