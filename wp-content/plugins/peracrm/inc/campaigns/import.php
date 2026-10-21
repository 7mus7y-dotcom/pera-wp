<?php
if (!defined('ABSPATH')) { exit; }

const PERACRM_SUBSCRIBER_CSV_BYTES = 1048576;
const PERACRM_SUBSCRIBER_CSV_ROWS = 1000;

/** Pure bounded parsing. Mapping indexes are explicit; no guessed headings or sales writes. */
function peracrm_subscribers_parse_csv($stream, $mapping)
{
    $fields = ['email', 'state', 'source', 'source_ref', 'evidence_ref', 'policy_version', 'occurred_at', 'language', 'topics'];
    if (!is_resource($stream) || !is_array($mapping) || array_diff(array_keys($mapping), $fields)) { throw new InvalidArgumentException('Invalid CSV mapping.'); }
    foreach (['email', 'state', 'source', 'source_ref', 'evidence_ref', 'policy_version', 'occurred_at'] as $field) {
        if (!isset($mapping[$field]) || !ctype_digit((string) $mapping[$field])) { throw new InvalidArgumentException('Explicit mapping required for ' . $field); }
    }
    $indexes = [];
    foreach ($mapping as $key => $index) {
        if ($index === '') { unset($mapping[$key]); continue; }
        if (!ctype_digit((string) $index) || (int) $index > 63 || in_array((int) $index, $indexes, true)) { throw new InvalidArgumentException('Map each field to a distinct column from 0 to 63.'); }
        $mapping[$key] = (int) $index;
        $indexes[] = (int) $index;
    }
    $stat = fstat($stream);
    if (($stat['size'] ?? 0) > PERACRM_SUBSCRIBER_CSV_BYTES) { throw new InvalidArgumentException('CSV file exceeds 1 MiB.'); }
    $header = fgetcsv($stream, PERACRM_SUBSCRIBER_CSV_BYTES + 1, ',', '"', '');
    if (ftell($stream) > PERACRM_SUBSCRIBER_CSV_BYTES || !$header || count($header) > 64 || max($indexes) >= count($header)) { throw new InvalidArgumentException('CSV header does not match mapping.'); }
    $rows = [];
    while (($cells = fgetcsv($stream, PERACRM_SUBSCRIBER_CSV_BYTES + 1, ',', '"', '')) !== false) {
        if (ftell($stream) > PERACRM_SUBSCRIBER_CSV_BYTES || count($rows) >= PERACRM_SUBSCRIBER_CSV_ROWS || count($cells) > 64) { throw new InvalidArgumentException('CSV limit: 1 MiB, 1000 rows, 64 columns.'); }
        $row = [];
        foreach ($mapping as $key => $index) { $row[$key] = trim((string) ($cells[$index] ?? '')); }
        $rows[] = $row;
    }
    return $rows;
}

function peracrm_subscribers_validate_import_row($row)
{
    $row['email'] = peracrm_campaigns_normalize_email($row['email'] ?? '');
    if (!in_array($row['state'] ?? '', ['unknown', 'subscribed', 'unsubscribe', 'complaint', 'hard_bounce'], true)) { throw new InvalidArgumentException('Invalid state.'); }
    $evidence = array_intersect_key($row, array_flip(['source', 'source_ref', 'evidence_ref', 'policy_version', 'occurred_at']));
    // Even missing-consent rows require an explicit historical source and date.
    if (empty($evidence['occurred_at'])) { throw new InvalidArgumentException('Historical UTC date required.'); }
    $row['evidence'] = peracrm_campaigns_evidence($evidence);
    $row['missing_evidence'] = !peracrm_campaigns_has_required_evidence($row['evidence']);
    $language = $row['language'] ?? '';
    if ($language !== '' && !preg_match('/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/D', $language)) { throw new InvalidArgumentException('Invalid language.'); }
    if (strlen($language) > 35) { throw new InvalidArgumentException('Invalid language.'); }
    $row['language'] = strtolower($language);
    $row['topics'] = ($row['topics'] ?? '') === '' ? [] : explode('|', $row['topics']);
    if (count($row['topics']) > 32) { throw new InvalidArgumentException('Too many topics.'); }
    foreach ($row['topics'] as $topic) { if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $topic)) { throw new InvalidArgumentException('Invalid topic.'); } }
    $row['topics'] = array_values(array_unique($row['topics']));
    sort($row['topics']);
    return $row;
}

/** Read-only bounded preview lookup; never loads client IDs or chooses a link. */
function peracrm_subscribers_preview_states(array $emails)
{
    peracrm_subscribers_require_staff(true);
    if (count($emails) > PERACRM_SUBSCRIBER_CSV_ROWS) { throw new InvalidArgumentException('Too many preview identities.'); }
    $emails = array_values(array_unique(array_map('peracrm_campaigns_normalize_email', $emails)));
    return peracrm_with_target_blog(static function () use ($emails) {
        global $wpdb;
        $states = [];
        $s = peracrm_table('peracrm_subscribers');
        $x = peracrm_table('peracrm_email_suppressions');
        // Match WP_Query post_status=any, without materializing matching posts.
        $statuses = array_values(get_post_stati(['exclude_from_search' => false]));
        foreach (array_chunk($emails, 100) as $chunk) {
            foreach ($chunk as $email) { $states[$email] = ['existing' => false, 'suppressed' => false, 'client_match' => 'none']; }
            $in = implode(',', array_fill(0, count($chunk), '%s'));
            // Union keeps suppressions for mailboxes without a subscriber.
            $rows = $wpdb->get_results($wpdb->prepare("SELECT s.normalized_email, 1 AS existing, x.unsubscribe, x.hard_bounce, x.complaint, x.administrative
                FROM {$s} s LEFT JOIN {$x} x ON x.normalized_email=s.normalized_email WHERE s.normalized_email IN ({$in})
                UNION ALL SELECT x.normalized_email, 0 AS existing, x.unsubscribe, x.hard_bounce, x.complaint, x.administrative
                FROM {$x} x WHERE x.normalized_email IN ({$in})", ...array_merge($chunk, $chunk)), ARRAY_A);
            peracrm_campaigns_db_require(true);
            foreach ($rows as $row) {
                $email = $row['normalized_email'];
                $states[$email]['existing'] = $states[$email]['existing'] || (bool) $row['existing'];
                $states[$email]['suppressed'] = $states[$email]['suppressed'] || peracrm_campaigns_has_suppression($row);
            }
            if (!$statuses) { continue; }
            $status_in = implode(',', array_fill(0, count($statuses), '%s'));
            $matches = $wpdb->get_results($wpdb->prepare("SELECT LOWER(TRIM(m.meta_value)) AS normalized_email, COUNT(DISTINCT p.ID) AS matches
                FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID=m.post_id
                WHERE p.post_type='crm_client' AND p.post_status IN ({$status_in})
                AND m.meta_key IN ('crm_primary_email','primary_email','crm_primary_email_normalized','primary_email_normalized','_peracrm_email')
                AND LOWER(TRIM(m.meta_value)) IN ({$in}) GROUP BY LOWER(TRIM(m.meta_value))", ...array_merge($statuses, $chunk)), ARRAY_A);
            peracrm_campaigns_db_require(true);
            foreach ($matches as $row) {
                $states[$row['normalized_email']]['client_match'] = (int) $row['matches'] > 1 ? 'ambiguous_unresolved' : 'available_unlinked';
            }
        }
        return $states;
    });
}

/** Preview only: writes temporary bookkeeping, never mailbox, consent, client or user data. */
function peracrm_subscribers_preview($stream, $mapping, $synthetic = false, $suppression_complete = false)
{
    peracrm_subscribers_require_staff(true);
    $input = peracrm_subscribers_parse_csv($stream, $mapping);
    return peracrm_with_target_blog(static function () use ($input, $synthetic, $suppression_complete) {
        global $wpdb;
        $batches = peracrm_table('peracrm_subscriber_batches');
        $table = peracrm_table('peracrm_subscriber_import_rows');
        $active = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$batches} WHERE owner_id=%d AND status IN ('preview','applying') AND expires_at>%s", peracrm_get_actor_user_id(), gmdate('Y-m-d H:i:s')));
        peracrm_campaigns_db_require(true);
        if ($active >= 10) { throw new RuntimeException('Cancel an active batch before creating another.'); }
        $id = bin2hex(random_bytes(32));
        $seen = [];
        $rows = [];
        $suppressed = [];
        foreach ($input as $number => $raw) {
            try {
                $row = peracrm_subscribers_validate_import_row($raw);
                if ($synthetic && !preg_match('/@(?:[a-z0-9-]+\.)*example\.test$/D', $row['email'])) { throw new InvalidArgumentException('Synthetic batches require example.test mailboxes.'); }
                $duplicate = isset($seen[$row['email']]);
                $conflict = $duplicate && $seen[$row['email']] !== $row['state'];
                $seen[$row['email']] = $row['state'];
                $row['preview'] = [
                    'duplicate' => $duplicate, 'conflict' => $conflict,
                    'missing_evidence' => $row['missing_evidence'],
                ];
                if (in_array($row['state'], ['unsubscribe', 'complaint', 'hard_bounce'], true)) { $suppressed[$row['email']] = true; }
                $rows[] = ['number' => $number + 2, 'row' => $row, 'status' => 'pending', 'result' => ''];
            } catch (InvalidArgumentException $error) {
                $rows[] = ['number' => $number + 2, 'row' => [], 'status' => 'excluded', 'result' => 'invalid_row'];
            }
        }
        // Synthetic designation is all-or-nothing, not a way to skip validation of real addresses.
        if ($synthetic) {
            foreach ($input as $raw) {
                if (!preg_match('/@(?:[a-z0-9-]+\.)*example\.test$/Di', trim($raw['email'] ?? ''))) { throw new InvalidArgumentException('Synthetic batch contains a non-synthetic address.'); }
            }
        }
        $emails = [];
        foreach ($rows as $entry) { if ($entry['row']) { $emails[] = $entry['row']['email']; } }
        $states = peracrm_subscribers_preview_states($emails);
        foreach ($rows as &$entry) {
            if ($entry['row']) { $entry['row']['preview'] += $states[$entry['row']['email']]; }
        }
        unset($entry);
        peracrm_campaigns_db_require($wpdb->query('START TRANSACTION'));
        try {
            peracrm_campaigns_db_require($wpdb->insert($batches, ['id' => $id, 'owner_id' => peracrm_get_actor_user_id(), 'status' => 'preview',
                'synthetic' => (int) $synthetic, 'suppression_complete' => (int) $suppression_complete,
                'total' => count($rows), 'summary_json' => '{}', 'created_at' => gmdate('Y-m-d H:i:s'), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400)]));
            foreach ($rows as $entry) {
                $row = $entry['row'];
                if ($row) { $row['preview']['batch_suppressed'] = isset($suppressed[$row['email']]); }
                peracrm_campaigns_db_require($wpdb->insert($table, ['batch_id' => $id, 'line_number' => $entry['number'],
                    'priority' => in_array($row['state'] ?? '', ['unsubscribe', 'complaint', 'hard_bounce'], true) ? 0 : 1,
                    'payload_json' => wp_json_encode($row), 'status' => $entry['status'], 'result' => $entry['result']]));
            }
            peracrm_campaigns_db_require($wpdb->query('COMMIT'));
        } catch (Throwable $error) { $wpdb->query('ROLLBACK'); throw $error; }
        return $id;
    });
}

function peracrm_subscribers_batch($id)
{
    peracrm_subscribers_require_staff();
    return peracrm_with_target_blog(static function () use ($id) {
        global $wpdb;
        $table = peracrm_table('peracrm_subscriber_batches');
        $batch = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%s AND owner_id=%d", $id, peracrm_get_actor_user_id()), ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$batch) { throw new RuntimeException('Batch unavailable.'); }
        return $batch;
    });
}

function peracrm_subscribers_batch_rows($id, $page = 1)
{
    $batch = peracrm_subscribers_batch($id);
    if ($batch['expires_at'] <= gmdate('Y-m-d H:i:s')) { return []; }
    return peracrm_with_target_blog(static function () use ($id, $page) {
        global $wpdb;
        $table = peracrm_table('peracrm_subscriber_import_rows');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE batch_id=%s ORDER BY line_number LIMIT 50 OFFSET %d", $id, (max(1, min(100, (int) $page)) - 1) * 50), ARRAY_A);
        peracrm_campaigns_db_require(true);
        return $rows;
    });
}

/** Nonblocking native lock serializes apply/cancel/expiry, survives per-row transactions. */
function peracrm_subscribers_batch_lock($id, callable $callback)
{
    global $wpdb;
    $key = 'pera-import-' . substr(hash('sha256', $wpdb->prefix . $id), 0, 40);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $key)) !== 1) { throw new RuntimeException('Batch is busy; retry.'); }
    try { return $callback(); }
    finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key)); }
}

/** @internal Runs INSIDE the Phase 1 mailbox transaction. Row completion commits with consent. */
function peracrm_subscribers_apply_row($entry)
{
    $row = json_decode($entry['payload_json'], true);
    return peracrm_campaigns_staff_mutate($row['email'], static function ($email, $subscriber, $suppression, $actor) use ($entry, $row) {
        global $wpdb;
        $rows = peracrm_table('peracrm_subscriber_import_rows');
        $current = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$rows} WHERE id=%d FOR UPDATE", $entry['id']), ARRAY_A);
        if (($current['status'] ?? '') !== 'pending') { return true; }
        $evidence = $row['evidence'];
        $result = 'unchanged';
        if (in_array($row['state'], ['unsubscribe', 'complaint', 'hard_bounce'], true)) {
            if (empty($suppression[$row['state']])) { peracrm_campaigns_repository_suppress($email, $row['state'], $evidence, $actor); }
            if ($row['state'] === 'unsubscribe' && $subscriber && $subscriber['subscription_state'] !== 'unsubscribed') {
                peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_subscribers'), ['subscription_state' => 'unsubscribed', 'withdrawn_at' => $evidence['occurred_at'], 'updated_at' => gmdate('Y-m-d H:i:s'), 'version' => (int) $subscriber['version'] + 1], ['id' => $subscriber['id']]));
                peracrm_campaigns_repository_event($email, $subscriber, 'withdrawn', 'unsubscribed', $evidence, $actor);
            }
            peracrm_campaigns_repository_event($email, $subscriber, 'suppression_imported', $subscriber['subscription_state'] ?? '', $evidence, $actor, ['batch' => $entry['batch_id'], 'row' => (int) $entry['line_number'], 'reason' => $row['state']]);
            $result = 'suppression_recorded';
        } elseif (peracrm_campaigns_has_suppression($suppression) || ($subscriber['subscription_state'] ?? '') === 'unsubscribed' || !empty($subscriber['withdrawn_at'])) {
            $result = 'excluded_suppressed';
        } elseif ($subscriber) {
            // Imports never promote existing identities or overwrite their evidence/profile.
            $result = 'existing_unchanged';
        } else {
            $consented = $row['state'] === 'subscribed' && peracrm_campaigns_has_required_evidence($evidence);
            $now = gmdate('Y-m-d H:i:s');
            $data = array_diff_key($evidence, ['occurred_at' => true]) + ['email' => $email, 'normalized_email' => $email,
                'subscription_state' => $consented ? 'subscribed' : 'unknown', 'recorded_at' => $evidence['occurred_at'],
                'confirmed_at' => $consented ? $evidence['occurred_at'] : null, 'preferred_language' => $row['language'],
                'preferences_json' => wp_json_encode($row['topics']), 'created_at' => $now, 'updated_at' => $now, 'version' => 1];
            peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_subscribers'), $data));
            $subscriber_id = (int) $wpdb->insert_id;
            peracrm_campaigns_repository_replace_identity($subscriber_id, $email, true);
            peracrm_campaigns_repository_event($email, ['id' => $subscriber_id], 'imported', $data['subscription_state'], $evidence, $actor, ['batch' => $entry['batch_id'], 'row' => (int) $entry['line_number']]);
            $result = $consented ? 'imported_subscribed' : 'imported_unknown';
        }
        peracrm_campaigns_db_require($wpdb->update($rows, ['status' => 'done', 'result' => $result, 'attempts' => (int) $entry['attempts'] + 1], ['id' => $entry['id']]));
        return true;
    });
}

/** Up to 25 rows/request. Earlier committed rows survive failure; failed rows retry at most 3 times. */
function peracrm_subscribers_apply_batch($id)
{
    peracrm_subscribers_require_staff(true);
    return peracrm_with_target_blog(static function () use ($id) {
        return peracrm_subscribers_batch_lock($id, static function () use ($id) {
            global $wpdb;
            $batch = peracrm_subscribers_batch($id);
            if (in_array($batch['status'], ['complete', 'partial_failed'], true)) { return ['status' => $batch['status'], 'counts' => json_decode($batch['summary_json'], true)]; }
            if (!in_array($batch['status'], ['preview', 'applying'], true) || $batch['expires_at'] <= gmdate('Y-m-d H:i:s')) { throw new RuntimeException('Batch expired or closed.'); }
            if (!peracrm_subscribers_intake_allowed((bool) $batch['synthetic'])) { throw new RuntimeException('Real intake is disabled pending administrator readiness decisions.'); }
            $table = peracrm_table('peracrm_subscriber_import_rows');
            $batches = peracrm_table('peracrm_subscriber_batches');
            peracrm_campaigns_db_require($wpdb->update($batches, ['status' => 'applying'], ['id' => $id]));
            $entries = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE batch_id=%s AND status='pending' ORDER BY priority, line_number LIMIT 25", $id), ARRAY_A);
            peracrm_campaigns_db_require(true);
            try {
            foreach ($entries as $entry) {
                if (!peracrm_subscribers_intake_allowed((bool) $batch['synthetic'])) { throw new RuntimeException('Intake gate was closed.'); }
                if ((int) $entry['priority'] === 1) {
                    $failed_suppression = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE batch_id=%s AND priority=0 AND status IN ('pending','failed')", $id));
                    peracrm_campaigns_db_require(true);
                    if ($failed_suppression) {
                        $failed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE batch_id=%s AND priority=0 AND status='failed'", $id));
                        if ($failed) { return peracrm_subscribers_summarize_batch($id, 'partial_failed'); }
                        break;
                    }
                    $row = json_decode($entry['payload_json'], true);
                    if ($row['state'] === 'subscribed' && empty($batch['suppression_complete'])) { throw new RuntimeException('Confirm suppression-source intake is complete before opted-in imports.'); }
                }
                $result = peracrm_subscribers_apply_row($entry);
                if (is_wp_error($result)) {
                    $attempts = (int) $entry['attempts'] + 1;
                    peracrm_campaigns_db_require($wpdb->update($table, ['attempts' => $attempts, 'status' => $attempts >= 3 ? 'failed' : 'pending', 'result' => 'storage_failed'], ['id' => $entry['id']]));
                    break; // A failing row never silently advances to opted-in rows.
                }
            }
            } catch (Throwable $error) {
                peracrm_subscribers_summarize_batch($id);
                throw $error;
            }
            $failed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE batch_id=%s AND priority=0 AND status='failed'", $id));
            return peracrm_subscribers_summarize_batch($id, $failed ? 'partial_failed' : '');
        });
    });
}

/** Durable counts contain no mailbox data. Terminal payloads are deleted immediately. */
function peracrm_subscribers_summarize_batch($id, $terminal = '')
{
    global $wpdb;
    $rows = peracrm_table('peracrm_subscriber_import_rows');
    $batches = peracrm_table('peracrm_subscriber_batches');
    peracrm_campaigns_db_require($wpdb->query('START TRANSACTION'));
    try {
    $counts = $wpdb->get_results($wpdb->prepare("SELECT status, result, COUNT(*) AS total FROM {$rows} WHERE batch_id=%s GROUP BY status,result", $id), ARRAY_A);
    peracrm_campaigns_db_require(true);
    $pending = 0; $failed = 0;
    foreach ($counts as $count) { if ($count['status'] === 'pending') { $pending += (int) $count['total']; } if ($count['status'] === 'failed') { $failed += (int) $count['total']; } }
    $status = $terminal ?: ($pending ? 'applying' : ($failed ? 'partial_failed' : 'complete'));
    $summary = wp_json_encode($counts);
    peracrm_campaigns_db_require($wpdb->update($batches, ['status' => $status, 'summary_json' => $summary], ['id' => $id]));
    if (!in_array($status, ['preview', 'applying'], true)) { peracrm_campaigns_db_require($wpdb->delete($rows, ['batch_id' => $id])); }
    peracrm_campaigns_db_require($wpdb->query('COMMIT'));
    return ['status' => $status, 'counts' => $counts];
    } catch (Throwable $error) { $wpdb->query('ROLLBACK'); throw $error; }
}

function peracrm_subscribers_cancel_batch($id)
{
    peracrm_subscribers_require_staff(true);
    return peracrm_with_target_blog(static function () use ($id) {
        return peracrm_subscribers_batch_lock($id, static function () use ($id) {
            $batch = peracrm_subscribers_batch($id);
            if (!in_array($batch['status'], ['preview', 'applying'], true)) { return; }
            peracrm_subscribers_summarize_batch($id, 'cancelled');
        });
    });
}

/** Cleanup is maintenance only; never applies rows or changes subscriptions. */
function peracrm_subscribers_expire_batches()
{
    peracrm_with_target_blog(static function () {
        global $wpdb;
        $table = peracrm_table('peracrm_subscriber_batches');
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE status IN ('preview','applying') AND expires_at<=%s LIMIT 50", gmdate('Y-m-d H:i:s')));
        foreach ((array) $ids as $id) {
            try { peracrm_subscribers_batch_lock($id, static function () use ($id, $table, $wpdb) {
                $batch = $wpdb->get_row($wpdb->prepare("SELECT status, expires_at FROM {$table} WHERE id=%s", $id), ARRAY_A);
                peracrm_campaigns_db_require(true);
                if ($batch && in_array($batch['status'], ['preview','applying'], true) && $batch['expires_at'] <= gmdate('Y-m-d H:i:s')) { peracrm_subscribers_summarize_batch($id, 'expired'); }
            }); }
            catch (Throwable $error) { /* Busy batches are retried by the next maintenance pass. */ }
        }
    });
}
add_action('peracrm_subscriber_cleanup', 'peracrm_subscribers_expire_batches');
add_action('init', static function () {
    if (!peracrm_campaigns_module_enabled()) { return; }
    peracrm_with_target_blog(static function () {
        if (!wp_next_scheduled('peracrm_subscriber_cleanup')) { wp_schedule_event(time() + 3600, 'hourly', 'peracrm_subscriber_cleanup'); }
    });
}, 20);
