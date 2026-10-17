<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Match CRM/import case/trim rules without repairing an invalid address. */
function peracrm_campaigns_normalize_email($email)
{
    if (!is_string($email)) {
        throw new InvalidArgumentException('An email address is required.');
    }
    $email = strtolower(trim($email));
    if (strlen($email) > 254 || !is_email($email) || preg_match('/[^\x21-\x7e]/', $email)) {
        throw new InvalidArgumentException('Invalid email address.');
    }
    return $email;
}

function peracrm_campaigns_module_enabled()
{
    return (!defined('PERACRM_CAMPAIGNS_ENABLED') || PERACRM_CAMPAIGNS_ENABLED)
        && peracrm_campaigns_schema_ready();
}

/** Dedicated target-blog authority. Never use effective/impersonated identity. */
function peracrm_campaigns_staff_can($capability, $mutation = false)
{
    $allowed = ['peracrm_manage_campaigns', 'peracrm_manage_subscribers', 'peracrm_view_campaign_reports'];
    if (!in_array($capability, $allowed, true) || !peracrm_campaigns_module_enabled()) {
        return false;
    }
    return peracrm_with_target_blog(static function () use ($capability, $mutation) {
        $actor = peracrm_get_actor_user_id();
        return $actor > 0 && user_can($actor, $capability)
            && (!$mutation || !peracrm_is_impersonating_crm_user());
    });
}

/** @internal Staff writes always authorize before touching mailbox storage. */
function peracrm_campaigns_staff_mutate($address, callable $callback)
{
    try {
        return peracrm_with_target_blog(static function () use ($address, $callback) {
            if (!peracrm_campaigns_staff_can('peracrm_manage_subscribers', true)) {
                return new WP_Error('campaign_forbidden', 'Subscriber mutation is not permitted.');
            }
            $email = peracrm_campaigns_normalize_email($address);
            $actor = peracrm_get_actor_user_id();
            return peracrm_campaigns_repository_mutate($email, static function ($subscriber, $suppression) use ($callback, $email, $actor) {
                return $callback($email, $subscriber, $suppression, $actor);
            });
        });
    } catch (InvalidArgumentException $error) {
        return new WP_Error('campaign_invalid', $error->getMessage());
    } catch (Throwable $error) {
        return new WP_Error('campaign_storage_failed', 'Subscriber storage is unavailable; no change was accepted.');
    }
}

/** Shared write/read rules: the string "0" is populated evidence, never missing. */
function peracrm_campaigns_evidence_reference_limits()
{
    return ['source' => 64, 'source_ref' => 191, 'evidence_ref' => 191, 'policy_version' => 64];
}

function peracrm_campaigns_valid_evidence_reference($value, $limit, $required = false)
{
    return is_string($value) && strlen($value) <= $limit
        && sanitize_text_field($value) === $value && (!$required || trim($value) !== '');
}

function peracrm_campaigns_has_required_evidence($row)
{
    foreach (peracrm_campaigns_evidence_reference_limits() as $key => $limit) {
        if (!peracrm_campaigns_valid_evidence_reference($row[$key] ?? null, $limit, true)) {
            return false;
        }
    }
    return true;
}

/** @internal Bounded references only; no enquiry payloads, IPs or arbitrary audit actor. */
function peracrm_campaigns_evidence($input, $required = false)
{
    if (!is_array($input) || array_diff(array_keys($input), ['source', 'source_ref', 'evidence_ref', 'policy_version', 'occurred_at'])) {
        throw new InvalidArgumentException('Invalid evidence fields.');
    }
    $output = [];
    foreach (peracrm_campaigns_evidence_reference_limits() as $key => $limit) {
        $value = $input[$key] ?? '';
        if (!peracrm_campaigns_valid_evidence_reference($value, $limit, $required || $key === 'source')) {
            throw new InvalidArgumentException('Invalid evidence reference.');
        }
        $output[$key] = trim($value);
    }
    $time = $input['occurred_at'] ?? gmdate('Y-m-d H:i:s');
    $date = is_string($time) ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $time, new DateTimeZone('UTC')) : false;
    if (!$date || $date->format('Y-m-d H:i:s') !== $time || $time > gmdate('Y-m-d H:i:s') || ($required && !isset($input['occurred_at']))) {
        throw new InvalidArgumentException('An explicit valid UTC evidence time is required.');
    }
    $output['occurred_at'] = $time;
    return $output;
}

/**
 * Staff profile create/update. Identity is immutable: use the new address to
 * create/evaluate a separate unknown identity. Consent/suppression fields fail.
 * No auto-linking, lead ingestion, post publication, merging or user creation.
 */
function peracrm_campaigns_save_subscriber($address, $profile = [])
{
    return peracrm_campaigns_staff_mutate($address, static function ($email, $subscriber, $suppression, $actor) use ($address, $profile) {
        global $wpdb;
        if (!is_array($profile) || array_diff(array_keys($profile), ['client_id', 'preferred_language', 'preferences'])) {
            throw new InvalidArgumentException('Only explicit profile fields may be updated.');
        }
        $data = [];
        if (array_key_exists('client_id', $profile)) {
            $client_id = $profile['client_id'];
            if ($client_id !== null) {
                if (!is_int($client_id) || $client_id <= 0 || !peracrm_user_can_access_client($actor, $client_id)) {
                    throw new InvalidArgumentException('Client link is not authorized.');
                }
                // Include the legacy _peracrm_email alias missing from the
                // existing multi-match helper; perform a read-only union.
                $matches = peracrm_find_all_client_ids_by_email($email);
                $legacy = get_posts([
                    'post_type' => 'crm_client', 'post_status' => 'any', 'posts_per_page' => -1,
                    'fields' => 'ids', 'no_found_rows' => true,
                    'meta_query' => [['key' => '_peracrm_email', 'value' => $email, 'compare' => '=']],
                ]);
                $matches = array_values(array_unique(array_map('intval', array_merge($matches, $legacy))));
                if (count($matches) !== 1 || $matches[0] !== $client_id || get_post_status($client_id) === 'trash') {
                    throw new InvalidArgumentException('Client email match is absent or ambiguous; leave unresolved.');
                }
            }
            $data['client_id'] = $client_id;
        }
        if (array_key_exists('preferred_language', $profile)) {
            $language = $profile['preferred_language'];
            if (!is_string($language) || strlen($language) > 35 || ($language !== '' && !preg_match('/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/D', $language))) {
                throw new InvalidArgumentException('Invalid explicit language tag.');
            }
            $data['preferred_language'] = strtolower($language);
        }
        if (array_key_exists('preferences', $profile)) {
            $topics = $profile['preferences'];
            if (!is_array($topics) || count($topics) > 32 || array_values($topics) !== $topics) {
                throw new InvalidArgumentException('Preferences must be an explicit list.');
            }
            foreach ($topics as $topic) {
                if (!is_string($topic) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $topic)) {
                    throw new InvalidArgumentException('Invalid topic key.');
                }
            }
            $topics = array_values(array_unique($topics));
            sort($topics);
            $data['preferences_json'] = wp_json_encode($topics);
        }
        $now = gmdate('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        $table = peracrm_table('peracrm_subscribers');
        if (!$subscriber) {
            $data += ['email' => trim($address), 'normalized_email' => $email, 'client_id' => null,
                'subscription_state' => 'unknown', 'preferences_json' => '[]', 'created_at' => $now, 'version' => 1];
            peracrm_campaigns_db_require($wpdb->insert($table, $data));
            $id = (int) $wpdb->insert_id;
            $event_subscriber = ['id' => $id];
        } else {
            $data['version'] = (int) $subscriber['version'] + 1;
            peracrm_campaigns_db_require($wpdb->update($table, $data, ['id' => $subscriber['id']]));
            $id = (int) $subscriber['id'];
            $event_subscriber = $subscriber;
        }
        peracrm_campaigns_repository_event($email, $event_subscriber, $subscriber ? 'profile_updated' : 'created',
            $subscriber['subscription_state'] ?? 'unknown', [], $actor,
            ['before' => $subscriber ? array_intersect_key($subscriber, array_flip(['client_id', 'preferred_language', 'preferences_json'])) : [],
             'after' => array_intersect_key($data, array_flip(['client_id', 'preferred_language', 'preferences_json']))]);
        return $id;
    });
}

/** Staff-only evidence recording; public verification/resubscription is deferred. */
function peracrm_campaigns_record_consent($address, $state, $evidence)
{
    return peracrm_campaigns_staff_mutate($address, static function ($email, $subscriber, $suppression, $actor) use ($state, $evidence) {
        global $wpdb;
        if (!$subscriber || !in_array($state, ['unknown', 'pending', 'subscribed', 'unsubscribed'], true)) {
            throw new InvalidArgumentException('Existing subscriber and valid consent state required.');
        }
        $evidence = peracrm_campaigns_evidence($evidence, $state === 'subscribed');
        if ($state === 'subscribed' && (peracrm_campaigns_has_suppression($suppression) || $subscriber['subscription_state'] === 'unsubscribed')) {
            throw new InvalidArgumentException('Suppressed/withdrawn permission requires a later verified resubscription flow.');
        }
        // Never replay older evidence as a new permission action.
        if (!empty($subscriber['recorded_at']) && $evidence['occurred_at'] < $subscriber['recorded_at']) {
            throw new InvalidArgumentException('Consent evidence predates the current permission record.');
        }
        $data = $evidence;
        unset($data['occurred_at']);
        $data += ['subscription_state' => $state, 'recorded_at' => $evidence['occurred_at'],
            'confirmed_at' => $state === 'subscribed' ? $evidence['occurred_at'] : null,
            'withdrawn_at' => $state === 'unsubscribed' ? $evidence['occurred_at'] : ($subscriber['withdrawn_at'] ?? null),
            'updated_at' => gmdate('Y-m-d H:i:s'), 'version' => (int) $subscriber['version'] + 1];
        if ($state === 'unsubscribed') {
            peracrm_campaigns_repository_suppress($email, 'unsubscribe', $evidence, $actor);
        }
        peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_subscribers'), $data, ['id' => $subscriber['id']]));
        peracrm_campaigns_repository_event($email, $subscriber, 'consent_changed', $state, $evidence, $actor);
        return true;
    });
}

/** Suppress even when no subscriber exists; reasons are cumulative, never cleared. */
function peracrm_campaigns_add_suppression($address, $reason, $evidence)
{
    return peracrm_campaigns_staff_mutate($address, static function ($email, $subscriber, $suppression, $actor) use ($reason, $evidence) {
        if (!in_array($reason, ['unsubscribe', 'hard_bounce', 'complaint', 'administrative'], true)) {
            throw new InvalidArgumentException('Invalid suppression reason.');
        }
        $evidence = peracrm_campaigns_evidence($evidence);
        peracrm_campaigns_repository_suppress($email, $reason, $evidence, $actor);
        return true;
    });
}

/** Withdrawal without an identity still leaves a durable unsubscribe tombstone. */
function peracrm_campaigns_withdraw($address, $evidence)
{
    return peracrm_campaigns_staff_mutate($address, static function ($email, $subscriber, $suppression, $actor) use ($evidence) {
        global $wpdb;
        $evidence = peracrm_campaigns_evidence($evidence);
        peracrm_campaigns_repository_suppress($email, 'unsubscribe', $evidence, $actor);
        if ($subscriber) {
            peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_subscribers'), [
                'subscription_state' => 'unsubscribed', 'withdrawn_at' => $evidence['occurred_at'],
                'updated_at' => gmdate('Y-m-d H:i:s'), 'version' => (int) $subscriber['version'] + 1,
            ], ['id' => $subscriber['id']]));
            peracrm_campaigns_repository_event($email, $subscriber, 'withdrawn', 'unsubscribed', $evidence, $actor);
        }
        return true;
    });
}

/** Delete only current profile. Never cascade into consent/suppression history. */
function peracrm_campaigns_delete_subscriber($address)
{
    return peracrm_campaigns_staff_mutate($address, static function ($email, $subscriber, $suppression, $actor) {
        global $wpdb;
        if ($subscriber) {
            peracrm_campaigns_repository_event($email, $subscriber, 'deleted', $subscriber['subscription_state'], [], $actor);
            peracrm_campaigns_db_require($wpdb->delete(peracrm_table('peracrm_subscribers'), ['id' => $subscriber['id']]));
        }
        return true;
    });
}

function peracrm_campaigns_has_suppression($row)
{
    foreach (['unsubscribe', 'hard_bounce', 'complaint', 'administrative'] as $reason) {
        if (!empty($row[$reason])) {
            return true;
        }
    }
    return false;
}

/**
 * Internal read, no current-user prerequisite. This is permission eligibility,
 * never authorization to send: Phase 1 registers no delivery or queue paths.
 * Supported positive filters require explicit data; unknown filters deny.
 */
function peracrm_campaigns_is_eligible($address, $filters = [])
{
    if (!peracrm_campaigns_module_enabled()) {
        return false;
    }
    try {
        $email = peracrm_campaigns_normalize_email($address);
        return peracrm_with_target_blog(static function () use ($email, $filters) {
            $row = peracrm_campaigns_repository_identity($email);
            if (!$row || empty($row['suppression_mailbox']) || peracrm_campaigns_has_suppression($row)
                || $row['subscription_state'] !== 'subscribed' || empty($row['confirmed_at'])
                || empty($row['recorded_at']) || !peracrm_campaigns_has_required_evidence($row)
                || !empty($row['withdrawn_at'])) {
                return false;
            }
            if (!empty($row['client_id'])) {
                if (get_post_type((int) $row['client_id']) !== 'crm_client' || get_post_status((int) $row['client_id']) === 'trash') {
                    return false; // Explicit unlink permits independent evaluation.
                }
                global $wpdb;
                $party_table = peracrm_table('peracrm_party');
                $disposition = $wpdb->get_var($wpdb->prepare(
                    "SELECT disposition FROM {$party_table} WHERE party_id = %d", (int) $row['client_id']
                ));
                peracrm_campaigns_db_require(true);
                if ($disposition !== 'none') {
                    return false;
                }
            }
            if (!is_array($filters) || array_diff(array_keys($filters), ['language', 'topic'])) {
                return false;
            }
            foreach ($filters as $key => $value) {
                if (!is_string($value) || $value === '') {
                    return false;
                }
                if ($key === 'language' && ($row['preferred_language'] === '' || $row['preferred_language'] !== strtolower($value))) {
                    return false;
                }
                if ($key === 'topic') {
                    $topics = json_decode($row['preferences_json'], true);
                    if (!is_array($topics) || !in_array($value, $topics, true)) {
                        return false;
                    }
                }
            }
            return true;
        });
    } catch (Throwable $error) {
        return false;
    }
}
