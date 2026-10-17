<?php
if (!defined('ABSPATH')) { exit; }

/** Internal staff-only issuance. No UI, public route, or message delivery mints tokens. */
function peracrm_subscribers_issue_token($address, $expires_at = null)
{
    peracrm_subscribers_require_staff(true);
    $email = peracrm_campaigns_normalize_email($address);
    if ($expires_at !== null && (!is_string($expires_at) || !preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $expires_at) || $expires_at <= gmdate('Y-m-d H:i:s'))) { throw new InvalidArgumentException('Invalid token expiry.'); }
    return peracrm_with_target_blog(static function () use ($email, $expires_at) {
        global $wpdb;
        if (!peracrm_campaigns_repository_identity($email)) { throw new InvalidArgumentException('Existing mailbox required.'); }
        $token = bin2hex(random_bytes(32));
        peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_subscriber_tokens'), [
            'digest' => hash('sha256', $token), 'normalized_email' => $email, 'purpose' => 'preferences', 'version' => 1,
            'created_at' => gmdate('Y-m-d H:i:s'), 'expires_at' => $expires_at,
        ]));
        return $token;
    });
}

function peracrm_subscribers_revoke_token($token)
{
    peracrm_subscribers_require_staff(true);
    return peracrm_with_target_blog(static function () use ($token) {
        global $wpdb;
        return peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_subscriber_tokens'), ['revoked_at' => gmdate('Y-m-d H:i:s')], ['digest' => hash('sha256', $token)]));
    });
}

function peracrm_subscribers_preferences_url($token = '')
{
    return peracrm_with_target_blog(static function () use ($token) {
        $url = home_url('/email-preferences/');
        return $token === '' ? $url : add_query_arg('token', $token, $url);
    });
}

/** @internal Read only. Fixed purpose/version; no caller-chosen mailbox or evidence. */
function peracrm_subscribers_resolve_token($token, $lock = false)
{
    global $wpdb;
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) { return null; }
    $table = peracrm_table('peracrm_subscriber_tokens');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE digest=%s" . ($lock ? ' FOR UPDATE' : ''), hash('sha256', $token)), ARRAY_A);
    peracrm_campaigns_db_require(true);
    if (!$row || $row['purpose'] !== 'preferences' || (int) $row['version'] !== 1 || !empty($row['revoked_at'])
        || (!empty($row['expires_at']) && $row['expires_at'] <= gmdate('Y-m-d H:i:s'))) { return null; }
    return $row;
}

/** No subscriber personal data is returned by a public read. */
function peracrm_subscribers_public_valid($token)
{
    if (!peracrm_campaigns_module_enabled()) { return false; }
    try { return peracrm_with_target_blog(static function () use ($token) { return (bool) peracrm_subscribers_resolve_token($token); }); }
    catch (Throwable $error) { return false; }
}

/**
 * Narrow public authority. The bearer selects only withdraw/preferences. Mailbox,
 * actor(0), reason, source, time and audit origin are server controlled.
 */
function peracrm_subscribers_public_post($token, $action, $topics = [])
{
    if (!peracrm_campaigns_module_enabled() || !in_array($action, ['withdraw', 'preferences'], true)) { return false; }
    try {
        return peracrm_with_target_blog(static function () use ($token, $action, $topics) {
            global $wpdb;
            $authorization = peracrm_subscribers_resolve_token($token);
            if (!$authorization) { return false; }
            if ($action === 'preferences') {
                if (!is_array($topics) || array_values($topics) !== $topics || count($topics) > 32) { return false; }
                foreach ($topics as $topic) { if (!is_string($topic) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $topic)) { return false; } }
                $topics = array_values(array_unique($topics)); sort($topics);
            }
            $email = $authorization['normalized_email'];
            return peracrm_campaigns_repository_mutate($email, static function ($subscriber, $suppression) use ($email, $token, $action, $topics) {
                global $wpdb;
                // Recheck after mailbox lock: revocation and expiry can race a request.
                if (!peracrm_subscribers_resolve_token($token, true)) { throw new RuntimeException('Token unavailable.'); }
                $evidence = ['source' => 'public_preferences', 'source_ref' => 'token-v1', 'evidence_ref' => '', 'policy_version' => '', 'occurred_at' => gmdate('Y-m-d H:i:s')];
                if ($action === 'withdraw') {
                    if (empty($suppression['unsubscribe'])) { peracrm_campaigns_repository_suppress($email, 'unsubscribe', $evidence, 0); }
                    if ($subscriber && $subscriber['subscription_state'] !== 'unsubscribed') {
                        peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_subscribers'), [
                            'subscription_state' => 'unsubscribed', 'withdrawn_at' => $evidence['occurred_at'],
                            'updated_at' => $evidence['occurred_at'], 'version' => (int) $subscriber['version'] + 1,
                        ], ['id' => $subscriber['id']]));
                        peracrm_campaigns_repository_event($email, $subscriber, 'withdrawn', 'unsubscribed', $evidence, 0);
                    }
                } elseif ($subscriber && $subscriber['preferences_json'] !== wp_json_encode($topics)) {
                    peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_subscribers'), [
                        'preferences_json' => wp_json_encode($topics), 'updated_at' => $evidence['occurred_at'], 'version' => (int) $subscriber['version'] + 1,
                    ], ['id' => $subscriber['id']]));
                    peracrm_campaigns_repository_event($email, $subscriber, 'topics_updated', $subscriber['subscription_state'], $evidence, 0, ['topics' => $topics]);
                }
                return true;
            });
        });
    } catch (Throwable $error) { return false; }
}

/** Bounded fixed-bucket transient counters; no stored raw IPs or bearer values. */
function peracrm_subscribers_public_rate_allowed($key, $limit)
{
    $key = 'pera_pref_' . substr(hash_hmac('sha256', $key . '|' . floor(time() / 3600), wp_salt('nonce')), 0, 40);
    $count = (int) get_transient($key);
    if ($count >= $limit) { return false; }
    set_transient($key, $count + 1, 3600);
    return true;
}
