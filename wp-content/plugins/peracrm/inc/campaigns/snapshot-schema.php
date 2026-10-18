<?php
if (!defined('ABSPATH')) { exit; }

/** Unpublished content captures only. No recipient, publication or dispatch storage. */
function peracrm_campaigns_snapshot_definitions()
{
    return ['peracrm_campaign_content_snapshots' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        draft_revision BIGINT UNSIGNED NOT NULL,
        target_blog_id BIGINT UNSIGNED NOT NULL,
        binding_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        content_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        actor_user_id BIGINT UNSIGNED NOT NULL,
        captured_at DATETIME NOT NULL,
        payload_json LONGTEXT NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY campaign_capture (campaign_id, draft_revision, binding_hash)"];
}
