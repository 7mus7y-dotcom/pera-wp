<?php
if (!defined('ABSPATH')) { exit; }

/** Opaque authenticated quarantine only. No inner event or subscriber authority. */
function peracrm_campaign_event_definitions(): array
{
    return ['peracrm_campaign_sns_receipts'=>"scope_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        message_id VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        payload_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        canonical_payload LONGTEXT NOT NULL,
        certificate_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        authentication_version VARCHAR(16) NOT NULL,
        quarantine_reason VARCHAR(32) NOT NULL,
        received_at DATETIME NOT NULL,
        PRIMARY KEY  (scope_hash, message_id)"];
}
