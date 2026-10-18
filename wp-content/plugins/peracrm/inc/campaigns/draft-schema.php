<?php
if (!defined('ABSPATH')) { exit; }

/** Phase 3A only: no audience, snapshot, recipient or delivery storage. */
function peracrm_campaigns_draft_definitions()
{
    return [
        'peracrm_campaign_drafts' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            internal_name VARCHAR(160) NOT NULL,
            subject VARCHAR(200) NOT NULL,
            language VARCHAR(35) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'draft',
            document_json LONGTEXT NOT NULL,
            revision BIGINT UNSIGNED NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            updated_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY updated_id (updated_at, id)",
        'peracrm_campaign_draft_revisions' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            revision BIGINT UNSIGNED NOT NULL,
            document_json LONGTEXT NOT NULL,
            actor_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY campaign_revision (campaign_id, revision)",
        'peracrm_campaign_configuration' => "id BIGINT UNSIGNED NOT NULL,
            revision BIGINT UNSIGNED NOT NULL,
            document_json LONGTEXT NOT NULL,
            approved_by BIGINT UNSIGNED NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id)",
    ];
}
