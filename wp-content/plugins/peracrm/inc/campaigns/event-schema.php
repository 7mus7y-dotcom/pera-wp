<?php
if (!defined('ABSPATH')) { exit; }

/** Authenticated receipts and normalized quarantine only. No application authority. */
function peracrm_campaign_event_definitions(): array
{
    $hash='VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    return [
        'peracrm_campaign_ses_events'=>"scope_hash {$hash} NOT NULL,
        event_key {$hash} NOT NULL,
        fact_json LONGTEXT NOT NULL,
        fact_hash {$hash} NOT NULL,
        recorded_at DATETIME NOT NULL,
        PRIMARY KEY  (scope_hash, event_key)",
        'peracrm_campaign_ses_normalizations'=>"scope_hash {$hash} NOT NULL,
        receipt_id VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        payload_hash {$hash} NOT NULL,
        policy_hash {$hash} NOT NULL,
        normalization_version VARCHAR(16) NOT NULL,
        quarantine_reason VARCHAR(32) NOT NULL,
        event_keys_json LONGTEXT NOT NULL,
        recorded_at DATETIME NOT NULL,
        PRIMARY KEY  (scope_hash, receipt_id)",
        'peracrm_campaign_sns_receipts'=>"scope_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        message_id VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        payload_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        canonical_payload LONGTEXT NOT NULL,
        certificate_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        authentication_version VARCHAR(16) NOT NULL,
        quarantine_reason VARCHAR(32) NOT NULL,
        received_at DATETIME NOT NULL,
        PRIMARY KEY  (scope_hash, message_id)"];
}
