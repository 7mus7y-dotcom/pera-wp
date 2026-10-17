<?php
if (!defined('ABSPATH')) { exit; }

/** Phase 2 additive target-blog tables. Raw tokens and uploaded files are never retained. */
function peracrm_campaigns_phase2_definitions()
{
    return [
        'peracrm_subscriber_batches' => "id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            owner_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(24) NOT NULL,
            synthetic TINYINT UNSIGNED NOT NULL DEFAULT 0,
            suppression_complete TINYINT UNSIGNED NOT NULL DEFAULT 0,
            total BIGINT UNSIGNED NOT NULL,
            summary_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY owner_status (owner_id, status),
            KEY expires_at (expires_at)",
        'peracrm_subscriber_import_rows' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            row_number BIGINT UNSIGNED NOT NULL,
            priority TINYINT UNSIGNED NOT NULL,
            payload_json LONGTEXT NOT NULL,
            status VARCHAR(24) NOT NULL,
            result VARCHAR(64) NOT NULL DEFAULT '',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY batch_row (batch_id, row_number),
            KEY batch_status (batch_id, status, priority)",
        'peracrm_subscriber_tokens' => "digest VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            normalized_email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            purpose VARCHAR(32) NOT NULL,
            version BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            revoked_at DATETIME NULL,
            PRIMARY KEY  (digest),
            KEY mailbox (normalized_email)",
    ];
}
