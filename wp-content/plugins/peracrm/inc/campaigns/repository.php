<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @internal Persistence primitives; no request routes call these directly. */
function peracrm_campaigns_db_require($result)
{
    global $wpdb;
    if ($result === false || $wpdb->last_error !== '') {
        throw new RuntimeException('Campaign persistence failed.');
    }
    return $result;
}

/** @internal Caller must already be in target-blog context; serializes one mailbox. */
function peracrm_campaigns_repository_mutate($email, callable $callback)
{
    global $wpdb;
    // START TRANSACTION would implicitly commit an outer caller's operation.
    peracrm_campaigns_repository_require_top_level();
    peracrm_campaigns_db_require($wpdb->query('START TRANSACTION'));
    try {
        [$subscriber, $suppression] = peracrm_campaigns_repository_lock_mailbox($email);
        $result = $callback($subscriber, $suppression);
        peracrm_campaigns_db_require($wpdb->query('COMMIT'));
        return $result;
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        throw $error;
    }
}

/** Reject nesting without ending or rolling back any caller-owned transaction. */
function peracrm_campaigns_repository_require_top_level()
{
    global $wpdb;
    $previous = $wpdb->suppress_errors(true);
    try {
        if ((int) peracrm_campaigns_db_require($wpdb->get_var('SELECT @@autocommit')) !== 1) {
            throw new RuntimeException('Caller owns transaction.');
        }
        peracrm_campaigns_db_require($wpdb->query('SAVEPOINT peracrm_subscriber_top_level_probe'));
        if ($wpdb->query('RELEASE SAVEPOINT peracrm_subscriber_top_level_probe') !== false) {
            throw new RuntimeException('Caller owns transaction.');
        }
    } finally {
        $wpdb->suppress_errors($previous);
    }
}

/** @internal Transaction participant. Lock order: mailbox -> subscriber -> identity. */
function peracrm_campaigns_repository_lock_mailbox($email)
{
    global $wpdb;
    $table = peracrm_table('peracrm_email_suppressions');
    $now = gmdate('Y-m-d H:i:s');
    peracrm_campaigns_db_require($wpdb->query($wpdb->prepare(
        "INSERT INTO {$table} (normalized_email, created_at, updated_at) VALUES (%s, %s, %s)
         ON DUPLICATE KEY UPDATE normalized_email = VALUES(normalized_email)", $email, $now, $now
    )));
    $suppression = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE normalized_email = %s FOR UPDATE", $email), ARRAY_A);
    peracrm_campaigns_db_require($suppression ?: false);
    $table = peracrm_table('peracrm_subscribers');
    $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE normalized_email = %s FOR UPDATE", $email), ARRAY_A);
    peracrm_campaigns_db_require(true);
    return [$subscriber, $suppression];
}

/**
 * @internal Caller holds mailbox/subscriber locks in its transaction.
 * Advance every deployment's retained identity on deletion or ID replacement.
 * No deployment provisioning, token issuance, history deletion or independent commit.
 */
function peracrm_campaigns_repository_replace_identity($subscriber, $mailbox, $active)
{
    global $wpdb;
    $table = peracrm_table('peracrm_campaign_recipient_identities');
    $blog = get_current_blog_id();
    // Use a current locking read, including when an outer owner established a
    // repeatable-read snapshot before acquiring the mailbox mutex. A plain read
    // could omit a deployment identity committed while that owner was waiting.
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE blog_id=%d AND subscriber_id=%d ORDER BY deployment FOR UPDATE",
        $blog, $subscriber
    ), ARRAY_A);
    peracrm_campaigns_db_require($rows);
    foreach ($rows as $row) {
        $epoch = (int) $row['token_epoch'];
        if ($epoch < 1 || $epoch >= PHP_INT_MAX) { throw new RuntimeException('Identity epoch exhausted.'); }
        $changed = peracrm_campaigns_db_require($wpdb->update($table,
            ['mailbox' => $mailbox, 'active' => (int) $active, 'token_epoch' => $epoch + 1],
            ['deployment' => $row['deployment'], 'blog_id' => $blog, 'subscriber_id' => $subscriber, 'token_epoch' => $epoch]
        ));
        if ($changed !== 1) { throw new RuntimeException('Identity changed concurrently.'); }
    }
}

/** @internal Append only: never update/delete audit rows, including on unlink/delete. */
function peracrm_campaigns_repository_event($email, $subscriber, $type, $state, $evidence, $actor, $details = [])
{
    global $wpdb;
    $now = gmdate('Y-m-d H:i:s');
    peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_subscription_events'), [
        'subscriber_id' => $subscriber['id'] ?? null,
        'normalized_email' => $email,
        'event_type' => $type,
        'previous_state' => $subscriber['subscription_state'] ?? '',
        'subscription_state' => $state,
        'source' => $evidence['source'] ?? 'staff',
        'source_ref' => $evidence['source_ref'] ?? '',
        'evidence_ref' => $evidence['evidence_ref'] ?? '',
        'policy_version' => $evidence['policy_version'] ?? '',
        'actor_user_id' => $actor,
        'occurred_at' => $evidence['occurred_at'] ?? $now,
        'recorded_at' => $now,
        'details_json' => wp_json_encode($details),
    ]));
}

/** @internal There is deliberately no suppression-clear operation in Phase 1. */
function peracrm_campaigns_repository_suppress($email, $reason, $evidence, $actor)
{
    global $wpdb;
    // $reason is selected from the service's fixed allowlist, never request SQL.
    $table = peracrm_table('peracrm_email_suppressions');
    $now = gmdate('Y-m-d H:i:s');
    peracrm_campaigns_db_require($wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET {$reason} = 1, version = version + 1, updated_at = %s WHERE normalized_email = %s", $now, $email
    )));
    peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_email_suppression_events'), [
        'normalized_email' => $email,
        'reason' => $reason,
        'source' => $evidence['source'],
        'source_ref' => $evidence['source_ref'],
        'actor_user_id' => $actor,
        'occurred_at' => $evidence['occurred_at'],
        'recorded_at' => $now,
    ]));
}

/** Internal eligibility read. One statement sees permission and suppression together. */
function peracrm_campaigns_repository_identity($email)
{
    global $wpdb;
    $subscribers = peracrm_table('peracrm_subscribers');
    $suppressions = peracrm_table('peracrm_email_suppressions');
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, x.unsubscribe, x.hard_bounce, x.complaint, x.administrative,
         x.normalized_email AS suppression_mailbox FROM {$subscribers} s
         LEFT JOIN {$suppressions} x ON x.normalized_email = s.normalized_email
         WHERE s.normalized_email = %s", $email
    ), ARRAY_A);
    peracrm_campaigns_db_require(true);
    return $row;
}
