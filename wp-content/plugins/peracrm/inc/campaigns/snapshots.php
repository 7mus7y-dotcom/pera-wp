<?php
if (!defined('ABSPATH')) { exit; }

/** @internal Canonical bytes for review/content binding. Encoding failure must fail closed. */
function peracrm_campaign_snapshot_json($value)
{
    $json = wp_json_encode($value);
    if (!is_string($json) || strlen($json) > 2097152) { throw new RuntimeException('Content capture is unavailable.'); }
    return $json;
}

/** @internal Runs only in target context after authorization. Not a frozen revision. */
function peracrm_campaign_snapshot_candidate($draft)
{
    $doc = peracrm_campaign_draft_document($draft['document']);
    // Do not reuse a request's already-primed post/meta cache when reviewing again.
    foreach ($doc['blocks'] as $block) { if (isset($block['id'])) { clean_post_cache($block['id']); } }
    $configuration = peracrm_campaign_configuration();
    $model = peracrm_campaign_render_model($doc, $configuration['document']);
    if ($model['notices']) { throw new InvalidArgumentException('Complete the required draft and approved sender/footer configuration before capturing content.'); }
    $warnings = [];
    foreach ($model['blocks'] as $index => $block) {
        if (isset($block['notice'])) { throw new InvalidArgumentException('Selected content or its current translation/public destination is unavailable.'); }
        if (in_array($block['type'], ['article', 'property'], true)) {
            if ($block['title'] === '' || $block['excerpt'] === '' || $block['url'] === '' || $block['language'] !== $doc['language']) {
                throw new InvalidArgumentException('Selected content requires a title, summary, public destination and explicit matching language.');
            }
            if ($block['image'] === '' || $block['alt'] === '') { $warnings[] = 'Block ' . ($index + 1) . ': optional image or translated image description unavailable.'; }
            if ($block['type'] === 'property') {
                if (empty($block['property']['units']) || isset($block['property']['notice'])) { throw new InvalidArgumentException('Selected property requires canonical v2 units, size and USD price data.'); }
                foreach ($block['property']['units'] as $unit) {
                    foreach (['v2_beds', 'v2_gross_size_min', 'v2_price_usd_min'] as $field) {
                        if (!isset($unit[$field]) || ($field !== 'v2_beds' && $unit[$field] <= 0)) { throw new InvalidArgumentException('Each property unit requires canonical beds, positive minimum size and USD price.'); }
                    }
                    foreach (['v2_gross_size', 'v2_price_usd'] as $field) {
                        if (isset($unit[$field . '_max']) && $unit[$field . '_max'] < $unit[$field . '_min']) { throw new InvalidArgumentException('Property maximum values cannot be below their minimum values.'); }
                    }
                }
            }
        }
    }
    $sender = null;
    foreach ($configuration['document']['senders'] as $identity) { if ($identity['id'] === $doc['sender_id']) { $sender = $identity; } }
    $content = [
        'schema_version' => 1, 'template_version' => $model['template_version'],
        'document_hash' => hash('sha256', peracrm_campaign_snapshot_json($doc)),
        'configuration_revision' => $configuration['revision'], 'configuration' => $configuration['document'],
        'configuration_approved_by' => $configuration['approved_by'], 'sender' => $sender,
        'model' => $model, 'output' => peracrm_campaign_render($model),
        'rules' => peracrm_campaign_audience_rules($doc['audience'] ?? []),
        'validation_version' => 1, 'warnings' => $warnings,
    ];
    $content['rules_hash'] = hash('sha256', peracrm_campaign_snapshot_json($content['rules']));
    return $content;
}

/** Prepare a short-lived, actor/blog/revision/content-bound review proof. No writes or tokens for recipients. */
function peracrm_campaign_snapshot_review($id, $revision, $document)
{
    peracrm_campaign_drafts_require(true);
    if (!is_int($id) || $id < 1 || !is_int($revision) || $revision < 1) { throw new InvalidArgumentException('A saved draft revision is required.'); }
    return peracrm_with_target_blog(static function () use ($id, $revision, $document) {
        $draft = peracrm_campaign_draft_get($id);
        if (!$draft || (int)$draft['revision'] !== $revision
            || peracrm_campaign_draft_document($document) !== peracrm_campaign_draft_document($draft['document'])) {
            throw new UnexpectedValueException('Save the current document before reviewing it.');
        }
        $content = peracrm_campaign_snapshot_candidate($draft);
        $proof = ['purpose' => 'content_review_v1', 'version' => 1, 'blog' => get_current_blog_id(), 'actor' => peracrm_get_actor_user_id(),
            'campaign' => $id, 'revision' => $revision, 'content_hash' => hash('sha256', peracrm_campaign_snapshot_json($content)), 'expires' => time() + 900];
        return ['content' => $content, 'review_proof' => peracrm_campaign_audience_pack($proof),
            'notice' => 'Unpublished content review only. Editorial configuration approval does not verify DNS or provider readiness. Image URLs do not make the underlying image files immutable. No recipient membership or sending authorization.'];
    });
}

/**
 * Internal staff mutation, not registered as an HTTP/CLI command. Future UI must
 * submit its current document, proof and this action's nonce. Draft/config row
 * locks serialize capture with saves. Source resolution is a bounded evaluation
 * interval, not a single-time database snapshot; stored bytes are immutable.
 */
function peracrm_campaign_snapshot_capture($id, $revision, $document, $proof, $nonce)
{
    return peracrm_campaign_draft_transaction(static function () use ($id, $revision, $document, $proof, $nonce) {
        global $wpdb;
        if (!is_int($id) || $id < 1 || !is_int($revision) || $revision < 1
            || !is_string($nonce) || !wp_verify_nonce($nonce, 'peracrm_campaign_snapshot_' . $id . '_' . $revision)) {
            throw new InvalidArgumentException('Content capture request is invalid.');
        }
        $binding = peracrm_campaign_audience_unpack($proof);
        foreach (['purpose' => 'content_review_v1', 'version' => 1, 'blog' => get_current_blog_id(), 'actor' => peracrm_get_actor_user_id(), 'campaign' => $id, 'revision' => $revision] as $key => $value) {
            if (($binding[$key] ?? null) !== $value) { throw new UnexpectedValueException(); }
        }
        if (!is_int($binding['expires'] ?? null) || $binding['expires'] < time() || $binding['expires'] > time() + 900) { throw new UnexpectedValueException(); }
        $draft = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . peracrm_table('peracrm_campaign_drafts') . ' WHERE id=%d FOR UPDATE', $id), ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$draft || (int)$draft['revision'] !== $revision || $draft['status'] !== 'draft') { throw new UnexpectedValueException(); }
        $draft['document'] = json_decode($draft['document_json'], true);
        if (peracrm_campaign_draft_document($document) !== peracrm_campaign_draft_document($draft['document'])) { throw new UnexpectedValueException(); }
        // Approval/configuration saves acquire this same singleton lock.
        $configuration = $wpdb->get_row('SELECT * FROM ' . peracrm_table('peracrm_campaign_configuration') . ' WHERE id=1 FOR UPDATE', ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$configuration) { throw new InvalidArgumentException('Approved configuration is required.'); }
        $table = peracrm_table('peracrm_campaign_content_snapshots');
        $binding_hash = hash('sha256', peracrm_campaign_snapshot_json(['actor' => $binding['actor'], 'blog' => $binding['blog'], 'content_hash' => $binding['content_hash'] ?? '']));
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE campaign_id=%d AND draft_revision=%d AND binding_hash=%s", $id, $revision, $binding_hash), ARRAY_A);
        peracrm_campaigns_db_require(true);
        // Lost-response replay returns the original artifact, never new source output.
        if ($existing) { return peracrm_campaign_snapshot_decode($existing); }
        $started = gmdate('Y-m-d H:i:s');
        $content = peracrm_campaign_snapshot_candidate($draft);
        $content_hash = hash('sha256', peracrm_campaign_snapshot_json($content));
        if (!is_string($binding['content_hash'] ?? null) || !hash_equals($binding['content_hash'], $content_hash)
            || $content['configuration_revision'] !== (int)$configuration['revision']) { throw new UnexpectedValueException(); }
        $now = gmdate('Y-m-d H:i:s');
        $payload = ['schema_version' => 1, 'state' => 'unpublished_content', 'content' => $content,
            'evaluation_started_at' => $started, 'evaluation_completed_at' => $now,
            'image_limitation' => 'Storing an image URL does not make the underlying image file immutable.'];
        peracrm_campaigns_db_require($wpdb->insert($table, ['campaign_id' => $id, 'draft_revision' => $revision,
            'target_blog_id' => get_current_blog_id(), 'binding_hash' => $binding_hash, 'content_hash' => $content_hash,
            'actor_user_id' => peracrm_get_actor_user_id(), 'captured_at' => $now, 'payload_json' => peracrm_campaign_snapshot_json($payload)]));
        $snapshot_id = (int)$wpdb->insert_id;
        // No campaign status, subscriber, token, recipient or history mutation.
        $stored = peracrm_campaign_snapshot_get($id, $snapshot_id);
        if (!$stored) { throw new RuntimeException('Content capture is unavailable.'); }
        return $stored;
    });
}

/** @internal Integrity check, never re-render from current sources/configuration. */
function peracrm_campaign_snapshot_decode($row)
{
    $payload = json_decode($row['payload_json'], true);
    if ((int)$row['target_blog_id'] !== get_current_blog_id() || !is_array($payload)
        || ($payload['state'] ?? '') !== 'unpublished_content' || !isset($payload['content'])
        || !hash_equals($row['content_hash'], hash('sha256', peracrm_campaign_snapshot_json($payload['content'])))) {
        throw new RuntimeException('Stored content is unavailable.');
    }
    $row['payload'] = $payload;
    return $row;
}

/** Authorized immutable artifact read. This is not a published/frozen revision API. */
function peracrm_campaign_snapshot_get($campaign_id, $snapshot_id)
{
    peracrm_campaign_drafts_require(true);
    return peracrm_with_target_blog(static function () use ($campaign_id, $snapshot_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . peracrm_table('peracrm_campaign_content_snapshots') . ' WHERE campaign_id=%d AND id=%d', $campaign_id, $snapshot_id), ARRAY_A);
        peracrm_campaigns_db_require(true);
        return $row ? peracrm_campaign_snapshot_decode($row) : null;
    });
}
