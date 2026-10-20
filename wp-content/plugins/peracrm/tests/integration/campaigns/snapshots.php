<?php
/** Disposable real WP/MySQL service coverage. No Phase 3C UI acceptance is claimed. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
function snapshot_expect($ok, $label) { if (!$ok) { throw new RuntimeException('FAIL snapshots: ' . $label); } echo 'PASS snapshots: ' . $label . "\n"; }
function snapshot_throws(callable $fn) { try { $fn(); return false; } catch (Throwable $error) { return true; } }
$origin = get_current_blog_id();
$manager = peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_manager'); });
$admin = peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_administrator'); });
$employee = peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_employee'); });
wp_set_current_user($admin);
// Exercise the deployed v23 upgrade and repeat without destructive rollback.
peracrm_with_target_blog(static function () {
    global $wpdb;
    $wpdb->query('DROP TABLE ' . peracrm_table('peracrm_campaign_content_snapshots'));
    update_option('peracrm_schema_version', 23);
    update_option('peracrm_campaigns_schema_status', ['state' => 'ready', 'version' => 23]);
});
snapshot_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema(), 'v23 upgrade and repeated readiness');
peracrm_with_target_blog(static function () { peracrm_campaigns_assert_schema(); });
$configuration = ['senders' => [['id' => 'snapshot', 'name' => 'Pera', 'email' => 'news@example.org', 'reply_to' => 'reply@example.org', 'approved' => true]], 'footer' => 'Snapshot approved legal identity and address', 'footer_approved' => true];
$previous_configuration = peracrm_campaign_configuration(true);
$configured = peracrm_campaign_configuration_save($previous_configuration['revision'], $configuration);
snapshot_expect(!is_wp_error($configured), 'approved synthetic configuration');
wp_set_current_user($manager);
$article = peracrm_with_target_blog(static function () { return wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Phase3B Snapshot article', 'post_content' => 'Original snapshot story']); });
$document = ['internal_name' => 'Snapshot fixture', 'subject' => 'Snapshot fixture subject', 'sender_id' => 'snapshot', 'language' => 'en', 'main_text' => 'Authored content', 'blocks' => [['type' => 'article', 'id' => $article]]];
$draft = peracrm_campaign_draft_save(0, 0, $document);
snapshot_expect(!is_wp_error($draft), 'saved snapshot source draft');
$document = $draft['document']; $id = $draft['id'];
$nonce = wp_create_nonce('peracrm_campaign_snapshot_' . $id . '_1');
// Change configuration after its first SELECT has returned, before the next
// query (normally content cache priming). A second configuration read would see
// this new revision and mix it with the first read's metadata.
$configuration_reads = 0; $configuration_changed = false;
$concurrent_configuration = $configuration;
$concurrent_configuration['senders'][0]['name'] = 'Concurrent sender';
$concurrent_configuration['senders'][0]['email'] = 'concurrent@example.org';
$concurrent_configuration['senders'][0]['reply_to'] = 'concurrent-reply@example.org';
$concurrent_configuration['footer'] = 'Concurrent footer and address';
$race = static function ($sql) use (&$configuration_reads, &$configuration_changed, $concurrent_configuration, $configured) {
    global $wpdb;
    $configuration_select = 'SELECT * FROM ' . peracrm_table('peracrm_campaign_configuration') . ' WHERE id=1';
    if ($configuration_reads > 0 && !$configuration_changed) {
        $configuration_changed = true; // Guard the nested fixture UPDATE query.
        snapshot_expect($wpdb->update(peracrm_table('peracrm_campaign_configuration'), ['revision' => $configured['revision'] + 1, 'document_json' => wp_json_encode($concurrent_configuration)], ['id' => 1]) === 1, 'fixture configuration changes after first read');
    }
    if ($sql === $configuration_select) { $configuration_reads++; }
    return $sql;
};
add_filter('query', $race);
try { $racing_review = peracrm_campaign_snapshot_review($id, 1, $document); }
finally { remove_filter('query', $race); }
$racing_content = $racing_review['content'];
$racing_binding = peracrm_campaign_audience_unpack($racing_review['review_proof']);
snapshot_expect($configuration_changed && $configuration_reads === 1, 'review reads configuration once during concurrent change');
snapshot_expect($racing_content['configuration_revision'] === $configured['revision'] && $racing_content['configuration'] === $configured['document']
    && $racing_content['sender'] === $configured['document']['senders'][0] && $racing_content['configuration_approved_by'] === $admin
    && $racing_content['model']['sender'] === 'Pera <news@example.org> · Reply-to: reply@example.org' && $racing_content['model']['footer'] === $configuration['footer']
    && str_contains($racing_content['output']['html'], $configuration['footer']) && str_contains($racing_content['output']['text'], 'Pera <news@example.org>')
    && !str_contains($racing_content['output']['text'], 'Concurrent')
    && $racing_binding['content_hash'] === hash('sha256', peracrm_campaign_snapshot_json($racing_content)), 'review output and signed proof describe the same configuration revision');
snapshot_expect(is_wp_error(peracrm_campaign_snapshot_capture($id, 1, $document, $racing_review['review_proof'], $nonce))
    && peracrm_with_target_blog(static function () use ($id) { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . peracrm_table('peracrm_campaign_content_snapshots') . ' WHERE campaign_id=%d', $id)); }) === 0, 'changed configuration rejects stale capture without a partial artifact');
wp_set_current_user($admin);
$configured = peracrm_campaign_configuration_save($configured['revision'] + 1, $configuration);
snapshot_expect(!is_wp_error($configured), 'restore configuration through authorized save after race');
wp_set_current_user($manager);
$review = peracrm_campaign_snapshot_review($id, 1, $document);
$translated = peracrm_campaign_draft_save(0, 0, array_replace($document, ['language' => 'de']));
snapshot_expect(!is_wp_error($translated) && snapshot_throws(static function () use ($translated) { peracrm_campaign_snapshot_review($translated['id'], 1, $translated['document']); }), 'missing required translation cannot be reviewed');
snapshot_expect(snapshot_throws(static function () use ($id, $document) { peracrm_campaign_snapshot_review($id, 1, array_replace($document, ['subject' => 'Unsaved'])); }), 'unsaved document rejected');
snapshot_expect(is_wp_error(peracrm_campaign_snapshot_capture($id, 1, $document, $review['review_proof'], 'bad')), 'nonce required');
$count = static function () use ($id) { return peracrm_with_target_blog(static function () use ($id) { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . peracrm_table('peracrm_campaign_content_snapshots') . ' WHERE campaign_id=%d', $id)); }); };
$before = peracrm_with_target_blog(static function () { global $wpdb; $out = []; foreach (['peracrm_subscribers', 'peracrm_subscription_events', 'peracrm_email_suppressions', 'peracrm_email_suppression_events', 'peracrm_subscriber_tokens'] as $name) { $out[$name] = $wpdb->get_results('SELECT * FROM ' . peracrm_table($name), ARRAY_A); } return wp_json_encode($out); });
$fail = static function ($sql) { return str_starts_with($sql, 'INSERT INTO') && str_contains($sql, 'peracrm_campaign_content_snapshots') ? 'INSERT INTO peracrm_fixture_missing VALUES (1)' : $sql; };
add_filter('query', $fail);
$failed = peracrm_campaign_snapshot_capture($id, 1, $document, $review['review_proof'], $nonce);
remove_filter('query', $fail);
snapshot_expect(is_wp_error($failed) && $count() === 0, 'insert failure rolls back and remains unpublished');
$approval=peracrm_campaign_audience_unpack($review['review_proof']);
foreach (['renderer_version'=>'old-renderer','slots'=>[]] as $key=>$value) {
    $stale_content=$review['content']; $stale_content[$key]=$value;
    $stale_approval=array_replace($approval,['content_hash'=>hash('sha256',peracrm_campaign_snapshot_json($stale_content))]);
    snapshot_expect(is_wp_error(peracrm_campaign_snapshot_capture($id,1,$document,peracrm_campaign_audience_pack($stale_approval),$nonce)) && $count()===0,'stale '.$key.' approval rejected without writes');
}
$saved = peracrm_campaign_snapshot_capture($id, 1, $document, $review['review_proof'], $nonce);
snapshot_expect(!is_wp_error($saved) && $count() === 1, 'capture succeeds after failure');
$content=$saved['payload']['content'];
// Unwired token/expansion services run against an actual immutable SQL capture in
// both topologies. Only injected test secrets and synthetic mailbox fixtures.
require_once WP_PLUGIN_DIR.'/peracrm/tests/recipient-services-fixture.php';
peracrm_test_recipient_services($content, 'snapshot_expect', (int)$saved['target_blog_id']);
snapshot_expect(peracrm_campaign_snapshot_get($id, (int)$saved['id']) === $saved,
    'recipient expansion leaves SQL capture, approval hash and historical bytes unchanged');
snapshot_expect($saved['payload']['schema_version']===2 && $content['renderer_version']==='peracrm_email_v2'
    && $content['slots']===peracrm_campaign_deliverable_slots(), 'v2 renderer and typed slots captured');
peracrm_campaign_deliverable_validate($content['output'], $content['slots']);
snapshot_expect(str_contains($content['output']['html'],'<a href="') && str_contains($content['output']['text'],$content['model']['blocks'][0]['url']), 'captured article HTML and text preserve the same working URL');
snapshot_expect(!preg_match('/<a\\b|href=/i',peracrm_campaign_staff_preview_html($content['output']['html'])), 'staff display projection disables navigation');
// Add a historical review-only row directly, without changing its bytes/hash.
$legacy_content=['schema_version'=>1, 'output'=>['html'=>'<p>REVIEWED CONTENT</p>', 'text'=>'Historical review-only bytes']];
$legacy_payload=['schema_version'=>1, 'state'=>'unpublished_content', 'content'=>$legacy_content];
$legacy=peracrm_with_target_blog(static function () use ($saved,$legacy_payload,$legacy_content) {
    global $wpdb;
    $row=$saved; unset($row['id'],$row['payload']);
    $row['binding_hash']=hash('sha256','historical-fixture');
    $row['content_hash']=hash('sha256',peracrm_campaign_snapshot_json($legacy_content));
    $row['payload_json']=peracrm_campaign_snapshot_json($legacy_payload);
    snapshot_expect($wpdb->insert(peracrm_table('peracrm_campaign_content_snapshots'),$row)===1,'historical fixture inserted unchanged');
    return ['id'=>(int)$wpdb->insert_id]+$row;
});
$legacy_read=peracrm_campaign_snapshot_get($id,$legacy['id']);
snapshot_expect($legacy_read['payload_json']===$legacy['payload_json'] && $legacy_read['content_hash']===$legacy['content_hash'] && !isset($legacy_read['payload']['content']['renderer_version']), 'historical read keeps bytes/hash and review-only format');

$again = peracrm_campaign_snapshot_capture($id, 1, $document, $review['review_proof'], $nonce);
snapshot_expect($again === $saved && $count() === 2, 'duplicate request returns exact stored artifact');
peracrm_with_target_blog(static function () use ($saved) {
    global $wpdb; $row = $saved; unset($row['id'], $row['payload']);
    $errors = $wpdb->suppress_errors(true);
    snapshot_expect($wpdb->insert(peracrm_table('peracrm_campaign_content_snapshots'), $row) === false, 'database unique capture constraint');
    $wpdb->suppress_errors($errors);
});
peracrm_with_target_blog(static function () use ($article) { wp_update_post(['ID' => $article, 'post_status' => 'private', 'post_content' => 'Changed story']); });
snapshot_expect(snapshot_throws(static function () use ($id, $document) { peracrm_campaign_snapshot_review($id, 1, $document); }), 'fresh review rejects changed source visibility');
$stored = peracrm_campaign_snapshot_get($id, (int)$saved['id']);
snapshot_expect($stored === $saved && str_contains($stored['payload']['content']['output']['text'], 'Original snapshot story'), 'source changes cannot rewrite stored HTML/text');
snapshot_expect(peracrm_campaign_snapshot_get($id + 1000000, (int)$saved['id']) === null, 'read requires matching campaign');
wp_set_current_user($employee);
snapshot_expect(snapshot_throws(static function () use ($id, $saved) { peracrm_campaign_snapshot_get($id, (int)$saved['id']); }) && is_wp_error(peracrm_campaign_snapshot_capture($id, 1, $document, $review['review_proof'], $nonce)), 'employee read and capture denied');
wp_set_current_user($manager);
update_user_meta($manager, PERACRM_VIEW_AS_USER_META_KEY, $employee);
snapshot_expect(snapshot_throws(static function () use ($id, $saved) { peracrm_campaign_snapshot_get($id, (int)$saved['id']); }), 'impersonated snapshot read denied');
delete_user_meta($manager, PERACRM_VIEW_AS_USER_META_KEY);
$next = peracrm_campaign_draft_save($id, 1, array_replace($document, ['subject' => 'New authored revision']));
snapshot_expect(!is_wp_error($next) && peracrm_campaign_snapshot_get($id, (int)$saved['id']) === $saved && is_wp_error(peracrm_campaign_snapshot_capture($id, 1, $document, $review['review_proof'], $nonce)), 'later draft preserves artifact and stale capture fails');
wp_set_current_user($admin);
$configuration['footer'] = 'Changed configuration';
$changed = peracrm_campaign_configuration_save($configured['revision'], $configuration);
snapshot_expect(!is_wp_error($changed) && peracrm_campaign_snapshot_get($id, (int)$saved['id']) === $saved, 'configuration changes cannot rewrite stored output');
// Restore configuration for existing downstream browser fixtures.
snapshot_expect(!is_wp_error(peracrm_campaign_configuration_save($changed['revision'], $previous_configuration['document'])), 'restore synthetic configuration');
$after = peracrm_with_target_blog(static function () { global $wpdb; $out = []; foreach (['peracrm_subscribers', 'peracrm_subscription_events', 'peracrm_email_suppressions', 'peracrm_email_suppression_events', 'peracrm_subscriber_tokens'] as $name) { $out[$name] = $wpdb->get_results('SELECT * FROM ' . peracrm_table($name), ARRAY_A); } return wp_json_encode($out); });
snapshot_expect($before === $after && peracrm_campaign_draft_get($id)['status'] === 'draft' && get_current_blog_id() === $origin, 'no subscriber/token mutations, publication or target-context leak');
