<?php
/** Synthetic behavior tests; real SQL assertions live in integration/campaigns/snapshots.php. */
require __DIR__ . '/campaign-previews-test.php';
require $root . '/inc/campaigns/snapshots.php';
function wp_verify_nonce($nonce, $action) { return $nonce === 'fixture:' . $action; }
function clean_post_cache($id) {}
class Snapshot_Test_DB extends Campaign_Memory_DB
{
    public $reads = 0;
    public $configuration_reads = 0;
    public $configuration_after_read = null;
    public function get_row($sql, $format) {
        $this->reads++;
        $this->last_error = '';
        if (str_contains($sql, 'peracrm_campaign_drafts')) { return $GLOBALS['snapshot_draft']; }
        if (str_contains($sql, 'peracrm_campaign_configuration')) {
            $this->configuration_reads++;
            $row = $GLOBALS['snapshot_configuration'];
            if ($this->configuration_after_read !== null) {
                $GLOBALS['snapshot_configuration'] = $this->configuration_after_read;
                $this->configuration_after_read = null;
            }
            return $row;
        }
        if (str_contains($sql, 'peracrm_campaign_content_snapshots')) {
            foreach ($this->tables[$this->prefix . 'peracrm_campaign_content_snapshots'] ?? [] as $row) { return $row; }
            return null;
        }
        return parent::get_row($sql, $format);
    }
}
$GLOBALS['wpdb'] = new Snapshot_Test_DB();
$GLOBALS['campaign_actor'] = 1; $GLOBALS['campaign_impersonating'] = false;
$configuration = ['senders' => [['id' => 'news', 'name' => 'Pera', 'email' => 'news@example.org', 'reply_to' => 'reply@example.org', 'approved' => true]], 'footer' => 'Pera legal identity and postal address', 'footer_approved' => true];
$document = peracrm_campaign_draft_document(['internal_name' => 'Snapshot', 'subject' => 'Reviewed subject', 'language' => 'en', 'sender_id' => 'news', 'main_text' => 'Reviewed content', 'blocks' => [['type' => 'cta', 'label' => 'Read', 'url' => 'https://example.org/story']]]);
$GLOBALS['snapshot_draft'] = ['id' => 10, 'revision' => 2, 'status' => 'draft', 'document' => $document, 'document_json' => wp_json_encode($document)];
$GLOBALS['snapshot_configuration'] = ['id' => 1, 'revision' => 3, 'document_json' => wp_json_encode($configuration), 'approved_by' => 1];
function snapshot_rejects(callable $fn) { try { $fn(); return false; } catch (Throwable $e) { return true; } }
foreach ([0, 3] as $actor) {
    $GLOBALS['campaign_actor'] = $actor; $before = $GLOBALS['wpdb']->reads;
    campaign_expect(snapshot_rejects(static function () use ($document) { peracrm_campaign_snapshot_review(10, 2, $document); })
        && snapshot_rejects(static function () { peracrm_campaign_snapshot_get(10, 1); })
        && is_wp_error(peracrm_campaign_snapshot_capture(10, 2, $document, '', '')) && $GLOBALS['wpdb']->reads === $before, 'snapshot unauthorized reads/writes rejected before storage');
}
$GLOBALS['campaign_actor'] = 1; $GLOBALS['campaign_impersonating'] = true;
campaign_expect(snapshot_rejects(static function () use ($document) { peracrm_campaign_snapshot_review(10, 2, $document); }) && is_wp_error(peracrm_campaign_snapshot_capture(10, 2, $document, '', '')), 'snapshot impersonated review and capture denied');
$GLOBALS['campaign_impersonating'] = false;
$original_configuration = $GLOBALS['snapshot_configuration'];
$changed_configuration = $configuration;
$changed_configuration['senders'][0]['name'] = 'Changed sender';
$changed_configuration['senders'][0]['email'] = 'changed@example.org';
$changed_configuration['senders'][0]['reply_to'] = 'changed-reply@example.org';
$changed_configuration['footer'] = 'Changed concurrent footer';
$GLOBALS['wpdb']->configuration_after_read = array_replace($original_configuration, ['revision' => 4, 'document_json' => wp_json_encode($changed_configuration), 'approved_by' => 2]);
$reads = $GLOBALS['wpdb']->configuration_reads;
$racing_review = peracrm_campaign_snapshot_review(10, 2, $document);
$racing_content = $racing_review['content'];
$racing_binding = peracrm_campaign_audience_unpack($racing_review['review_proof']);
campaign_expect($GLOBALS['wpdb']->configuration_reads === $reads + 1 && $GLOBALS['snapshot_configuration']['revision'] === 4, 'review reads configuration once despite concurrent revision change');
campaign_expect($racing_content['configuration_revision'] === 3 && $racing_content['configuration'] === $configuration && $racing_content['configuration_approved_by'] === 1
    && $racing_content['sender'] === $configuration['senders'][0] && $racing_content['model']['sender'] === 'Pera <news@example.org> · Reply-to: reply@example.org'
    && $racing_content['model']['footer'] === $configuration['footer'] && str_contains($racing_content['output']['html'], $configuration['footer'])
    && str_contains($racing_content['output']['text'], 'Pera <news@example.org>') && !str_contains($racing_content['output']['text'], 'Changed')
    && $racing_binding['content_hash'] === hash('sha256', peracrm_campaign_snapshot_json($racing_content)), 'review metadata, sender/footer output and signed proof bind one configuration revision');
campaign_expect(is_wp_error(peracrm_campaign_snapshot_capture(10, 2, $document, $racing_review['review_proof'], 'fixture:peracrm_campaign_snapshot_10_2'))
    && campaign_count('peracrm_campaign_content_snapshots') === 0, 'concurrent configuration change still rejects stale capture without writing');
$GLOBALS['snapshot_configuration'] = $original_configuration;
campaign_expect(snapshot_rejects(static function () use ($document) { peracrm_campaign_snapshot_review(10, 1, $document); }) && snapshot_rejects(static function () use ($document) { peracrm_campaign_snapshot_review(10, 2, array_replace($document, ['subject' => 'Unsaved'])); }), 'stale revision and unsaved document review rejected');
$review = peracrm_campaign_snapshot_review(10, 2, $document);
$nonce = 'fixture:peracrm_campaign_snapshot_10_2';
campaign_expect(is_wp_error(peracrm_campaign_snapshot_capture(10, 2, $document, $review['review_proof'], 'bad')), 'capture requires nonce');
$binding = peracrm_campaign_audience_unpack($review['review_proof']);
foreach (['actor' => 2, 'blog' => 1, 'revision' => 1, 'expires' => time() - 1] as $key => $value) {
    $bad = array_replace($binding, [$key => $value]);
    campaign_expect(is_wp_error(peracrm_campaign_snapshot_capture(10, 2, $document, peracrm_campaign_audience_pack($bad), $nonce)), 'review proof rejects changed ' . $key);
}
$GLOBALS['wpdb']->fail_insert = 'peracrm_campaign_content_snapshots';
campaign_expect(is_wp_error(peracrm_campaign_snapshot_capture(10, 2, $document, $review['review_proof'], $nonce)) && campaign_count('peracrm_campaign_content_snapshots') === 0, 'failed capture leaves no partial snapshot');
$GLOBALS['wpdb']->fail_insert = '';
$saved = peracrm_campaign_snapshot_capture(10, 2, $document, $review['review_proof'], $nonce);
campaign_expect(!is_wp_error($saved) && $saved['payload']['state'] === 'unpublished_content' && campaign_count('peracrm_campaign_content_snapshots') === 1 && $saved['target_blog_id'] === 2 && $saved['actor_user_id'] === 1, 'capture stores unpublished actor/blog-bound immutable model and output');
$replay = peracrm_campaign_snapshot_capture(10, 2, $document, $review['review_proof'], $nonce);
campaign_expect($replay === $saved && campaign_count('peracrm_campaign_content_snapshots') === 1, 'duplicate capture returns same artifact');
$GLOBALS['snapshot_configuration']['revision']++;
$configuration['footer'] = 'Changed footer';
$GLOBALS['snapshot_configuration']['document_json'] = wp_json_encode($configuration);
campaign_expect(peracrm_campaign_snapshot_get(10, $saved['id']) === $saved && str_contains($saved['payload']['content']['output']['text'], 'Pera legal identity'), 'stored reads remain byte-stable after configuration changes');
$new = peracrm_campaign_snapshot_review(10, 2, $document);
campaign_expect($new['content']['configuration_revision'] !== $saved['payload']['content']['configuration_revision'], 'fresh review binds new configuration revision');
$GLOBALS['wpdb']->tables = [];
campaign_expect(is_wp_error(peracrm_campaign_snapshot_capture(10, 2, $document, $review['review_proof'], $nonce)), 'changed configuration invalidates uncaptured review');
$configuration['footer_approved'] = false;
$GLOBALS['snapshot_configuration']['document_json'] = wp_json_encode($configuration);
campaign_expect(snapshot_rejects(static function () use ($document) { peracrm_campaign_snapshot_review(10, 2, $document); }), 'incomplete editorial configuration blocks review');
$corrupt = $saved; $corrupt['payload_json'] = str_replace('Reviewed subject', 'tampered subject', $corrupt['payload_json']);
campaign_expect(snapshot_rejects(static function () use ($corrupt) { peracrm_with_target_blog(static function () use ($corrupt) { peracrm_campaign_snapshot_decode($corrupt); }); }), 'stored hash corruption fails closed');
campaign_expect(str_contains($saved['payload']['content']['output']['text'], '{{recipient_unsubscribe_url}}') && $GLOBALS['snapshot_draft']['status'] === 'draft' && campaign_count('peracrm_subscribers') === 0 && campaign_count('peracrm_subscriber_tokens') === 0, 'capture preserves placeholders and creates no frozen status, subscribers or public tokens');
echo "PASS snapshot foundations (synthetic; not SQL/concurrency acceptance)\n";
