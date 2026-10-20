<?php
if (!defined('ABSPATH')) { exit; }

/** Additive metadata only. No bearer, key material, body or transport activation. */
function peracrm_campaign_recipient_definitions(): array
{
    $scope = 'deployment VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        blog_id BIGINT UNSIGNED NOT NULL';
    $hash = 'VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    return [
        'peracrm_campaign_recipient_identities' => "{$scope},
            subscriber_id BIGINT UNSIGNED NOT NULL,
            mailbox VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            token_epoch BIGINT UNSIGNED NOT NULL,
            active TINYINT UNSIGNED NOT NULL,
            PRIMARY KEY  (deployment, blog_id, subscriber_id)",
        'peracrm_campaign_recipient_tokens' => "{$scope},
            digest {$hash} NOT NULL,
            subscriber_id BIGINT UNSIGNED NOT NULL,
            binding_json LONGTEXT NOT NULL,
            binding_hash {$hash} NOT NULL,
            revoked TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (deployment, blog_id, digest),
            KEY identity_tokens (deployment, blog_id, subscriber_id)",
        'peracrm_campaign_submission_attempts' => "{$scope},
            attempt_key {$hash} NOT NULL,
            delivery_key {$hash} NOT NULL,
            binding_json LONGTEXT NOT NULL,
            binding_hash {$hash} NOT NULL,
            PRIMARY KEY  (deployment, blog_id, attempt_key),
            KEY delivery_attempts (deployment, blog_id, delivery_key)",
    ];
}
