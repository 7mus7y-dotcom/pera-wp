<?php
/** Standalone behavior harness: synthetic identities and stub DB/WP only. */
define('ABSPATH', __DIR__ . '/fixtures/campaign-wp/');
define('PERACRM_TARGET_BLOG_ID', 2);
define('PERACRM_SCHEMA_VERSION', 30);
define('ARRAY_A', 'ARRAY_A');
require __DIR__ . '/fixtures/campaign-memory-db.php';
$GLOBALS['wpdb'] = new Campaign_Memory_DB();
$GLOBALS['campaign_blog'] = 1;
$GLOBALS['campaign_stack'] = [];
$GLOBALS['campaign_actor'] = 1;
$GLOBALS['campaign_impersonating'] = false;
$GLOBALS['campaign_roles'] = [];
$GLOBALS['campaign_options'] = [];
$GLOBALS['campaign_matches'] = [];
$GLOBALS['campaign_legacy'] = [];
$GLOBALS['campaign_client_status'] = 'publish';
$GLOBALS['campaign_disposition'] = 'none';
$GLOBALS['campaign_mail'] = [];
function sanitize_email($email) { return filter_var($email, FILTER_SANITIZE_EMAIL); }
function esc_url_raw($url) { return $url; }
function wp_mail(...$args) { $GLOBALS['campaign_mail'][] = $args; return true; }
class WP_Error { public $code; public function get_error_code() { return $this->code; } public function __construct($code, $message) { $this->code = $code; } }
class Campaign_Test_Role {
    public $caps = [];
    public function add_cap($cap) { $this->caps[$cap] = true; }
    public function remove_cap($cap) { unset($this->caps[$cap]); }
}
$GLOBALS['campaign_hooks'] = [];
function add_action($hook, $callback, $priority = 10) { $GLOBALS['campaign_hooks'][$hook][$priority][] = $callback; }
function do_action($hook) {
    $priorities = $GLOBALS['campaign_hooks'][$hook] ?? [];
    ksort($priorities);
    foreach ($priorities as $callbacks) { foreach ($callbacks as $callback) { $callback(); } }
}
function delete_option($key) { unset($GLOBALS['campaign_options'][get_current_blog_id()][$key]); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_url($text) { return $text; }
function admin_url($path = '') { return '/wp-admin/' . $path; }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException('request_rejected'); }
function check_admin_referer($action) { throw new RuntimeException('nonce_rejected'); }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="synthetic-nonce">'; }

function is_multisite() { return true; }
function get_current_blog_id() { return $GLOBALS['campaign_blog']; }
function switch_to_blog($id) { $GLOBALS['campaign_stack'][] = get_current_blog_id(); $GLOBALS['campaign_blog'] = $id; $GLOBALS['wpdb']->prefix = 'wp_' . $id . '_'; }
function restore_current_blog() { $GLOBALS['campaign_blog'] = array_pop($GLOBALS['campaign_stack']); $GLOBALS['wpdb']->prefix = 'wp_' . get_current_blog_id() . '_'; }
function peracrm_get_actor_user_id() { return $GLOBALS['campaign_actor']; }
function peracrm_is_impersonating_crm_user() { return $GLOBALS['campaign_impersonating']; }
function user_can($id, $cap) { return !empty($GLOBALS['campaign_roles'][get_current_blog_id()][[1 => 'administrator', 2 => 'manager', 3 => 'employee'][$id] ?? 'anonymous']->caps[$cap]); }
function get_role($slug) { return $GLOBALS['campaign_roles'][get_current_blog_id()][$slug] ?? null; }
function add_role($slug, $label, $caps) { $GLOBALS['campaign_roles'][get_current_blog_id()][$slug] = new Campaign_Test_Role(); }
function get_option($key, $default = false) { return $GLOBALS['campaign_options'][get_current_blog_id()][$key] ?? $default; }
function update_option($key, $value, $autoload = true) { $GLOBALS['campaign_options'][get_current_blog_id()][$key] = $value; }
function wp_generate_uuid4() { static $id = 0; return sprintf('00000000-0000-4000-8000-%012d', ++$id); }
function maybe_serialize($v) { return is_array($v) ? serialize($v) : (string) $v; }
function maybe_unserialize($v) { return is_string($v) && str_starts_with($v, 'a:') ? unserialize($v) : $v; }
function wp_cache_delete($key, $group) {}
function wp_json_encode($v) { return json_encode($v); }
function is_email($v) { return filter_var($v, FILTER_VALIDATE_EMAIL); }
function is_wp_error($v) { return $v instanceof WP_Error; }
function sanitize_text_field($v) { return trim(strip_tags($v)); }
function peracrm_user_can_access_client($actor, $id) { return in_array($actor, [1, 2], true) && $id > 0 && !in_array($id, $GLOBALS['campaign_denied_clients'] ?? [], true); }
function peracrm_find_all_client_ids_by_email($email) { return $GLOBALS['campaign_matches']; }
function get_posts($args) { return $GLOBALS['campaign_legacy']; }
function get_post_status($id) { return $GLOBALS['campaign_client_status']; }
function get_post_type($id) { return $GLOBALS['campaign_client_status'] === 'deleted' ? false : 'crm_client'; }
function dbDelta($sql) {
    $db = $GLOBALS['wpdb'];
    $db->ddl_calls++;
    preg_match('/CREATE TABLE (\w+)/', $sql, $m);
    if ($db->fail_schema && str_contains($m[1], 'peracrm_subscribers')) { throw new RuntimeException('synthetic DDL failure'); }
    $db->ddl[$m[1]] = $sql;
}
$root = dirname(__DIR__);
require $root . '/inc/helpers.php';
require $root . '/inc/roles.php';
require $root . '/inc/schema.php';
require $root . '/inc/campaigns/management-schema.php';
require $root . '/inc/campaigns/draft-schema.php';
require $root . '/inc/campaigns/snapshot-schema.php';
require $root . '/inc/campaigns/freeze-schema.php';
require $root . '/inc/campaigns/queue-schema.php';
require $root . '/inc/campaigns/schema.php';
require $root . '/inc/campaigns/repository.php';
require $root . '/inc/campaigns/service.php';
require $root . '/inc/campaigns/schema-lock.php';
require $root . '/inc/campaigns/readiness.php';
require $root . '/inc/notifications/providers/class-peracrm-email-provider.php';
function campaign_expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    if (get_current_blog_id() !== 1 || $GLOBALS['campaign_stack']) { throw new RuntimeException('Blog context leaked: ' . $message); }
    echo "PASS: {$message}\n";
}
function campaign_identity($email) { return peracrm_with_target_blog(static function () use ($email) { return peracrm_campaigns_repository_identity($email); }); }
function campaign_count($suffix) { return count($GLOBALS['wpdb']->tables['wp_2_' . $suffix] ?? []); }
$wpdb = $GLOBALS['wpdb'];
$GLOBALS['campaign_roles'][2]['administrator'] = new Campaign_Test_Role();
$GLOBALS['campaign_roles'][2]['administrator']->add_cap('manage_options');
// Skip unrelated historical v4 migration work in the fresh-install stub.
$GLOBALS['campaign_options'][2]['peracrm_migration_v4_done'] = 1;
do_action('init');
campaign_expect(get_option('peracrm_schema_version') === false && (int) $GLOBALS['campaign_options'][2]['peracrm_schema_version'] === 30, 'fresh schema marker and tables are target-scoped');
$ddl = $wpdb->ddl;
peracrm_upgrade_schema_to(30, 30);
campaign_expect($ddl === $wpdb->ddl, 'repeated DDL definitions are stable (stub only)');
$GLOBALS['campaign_options'][2]['peracrm_schema_version'] = 20;
do_action('init');
campaign_expect((int) $GLOBALS['campaign_options'][2]['peracrm_schema_version'] === 30, 'previous-schema upgrade wires v21');
// This is the same callback registration executed by normal plugin init.
$GLOBALS['campaign_existing_init_ran'] = 0;
add_action('init', static function () { $GLOBALS['campaign_existing_init_ran']++; }, 6);
foreach (['fail_schema', 'missing_unique'] as $failure) {
    $GLOBALS['campaign_options'][2]['peracrm_schema_version'] = 20;
    $wpdb->$failure = true;
    $before = $GLOBALS['campaign_existing_init_ran'];
    do_action('init');
    campaign_expect($GLOBALS['campaign_existing_init_ran'] === $before + 1
        && (int) $GLOBALS['campaign_options'][2]['peracrm_schema_version'] === 20,
        'actual init contains ' . $failure . ', preserves marker and continues later website/CRM hooks');
    campaign_expect(!peracrm_campaigns_schema_ready() && !peracrm_campaigns_is_eligible('fixture@example.test')
        && is_wp_error(peracrm_campaigns_save_subscriber('fixture@example.test')), 'campaign services denied after migration failure');
    campaign_expect(!$wpdb->suppress_errors, 'SQL error display setting restored after failure');
    $operational_during_failure = (new PeraCRM_Email_Provider())->send(['source_label' => 'Synthetic enquiry'], 'ops@example.test');
    campaign_expect($operational_during_failure['ok'], 'existing operational email provider remains callable during campaign failure (fake mail)');
    $calls = $wpdb->ddl_calls;
    do_action('init');
    campaign_expect($wpdb->ddl_calls === $calls, 'public init does not repeat expensive failed migration');
    ob_start(); peracrm_campaigns_schema_admin_notice(); $notice = ob_get_clean();
    campaign_expect(str_contains($notice, 'schema_verification_failed') && !str_contains($notice, 'synthetic DDL failure'), 'administrator sees sanitized diagnostic and retry form');
    foreach ([0, 2, 3] as $actor) {
        $GLOBALS['campaign_actor'] = $actor;
        campaign_expect(is_wp_error(peracrm_campaigns_retry_schema()), 'nonadministrator retry denied');
        ob_start(); peracrm_campaigns_schema_admin_notice(); $hidden = ob_get_clean();
        campaign_expect($hidden === '', 'diagnostic is administrator-only');
    }
    $GLOBALS['campaign_actor'] = 1;
    $GLOBALS['campaign_impersonating'] = true;
    campaign_expect(is_wp_error(peracrm_campaigns_retry_schema()), 'impersonated administrator retry denied');
    $GLOBALS['campaign_impersonating'] = false;
    $calls = $wpdb->ddl_calls;
    foreach (['GET' => 'request_rejected', 'POST' => 'nonce_rejected'] as $method => $expected_error) {
        $_SERVER['REQUEST_METHOD'] = $method;
        $rejected = false;
        try { peracrm_campaigns_handle_schema_retry(); } catch (RuntimeException $error) { $rejected = $error->getMessage() === $expected_error; }
        campaign_expect($rejected && $wpdb->ddl_calls === $calls, 'retry endpoint requires POST and valid nonce before migration');
    }
    unset($_SERVER['REQUEST_METHOD']);
    $wpdb->$failure = false;
    campaign_expect(peracrm_campaigns_retry_schema() === true && peracrm_campaigns_schema_ready(), 'controlled administrator retry recovers services');
    campaign_expect($GLOBALS['campaign_options'][2]['peracrm_campaigns_schema_status']['retry_actor_user_id'] === 1, 'retry preserves real actor');
}
// A marker from the earlier PR revision without readiness must be verified.
unset($GLOBALS['campaign_options'][2]['peracrm_campaigns_schema_status']);
$wpdb->missing_unique = true;
do_action('init');
campaign_expect(!peracrm_campaigns_schema_ready(), 'existing v21 marker alone cannot enable campaigns');
$wpdb->missing_unique = false;
campaign_expect(peracrm_campaigns_retry_schema() === true, 'existing v21 schema read-only verification recovers');
// Model ownership loss/re-entry. Real connection races are covered in Docker.
$lock_checks = peracrm_with_target_blog(static function () use ($wpdb) {
    $old = peracrm_campaigns_schema_acquire_slot();
    $nested = peracrm_campaigns_schema_acquire_slot();
    peracrm_campaigns_schema_release_slot($old);
    $new = peracrm_campaigns_schema_acquire_slot();
    $current = peracrm_campaigns_schema_option('peracrm_campaigns_schema_status');
    peracrm_campaigns_schema_publish($new, 'peracrm_campaigns_schema_status', array_merge($current, ['attempt_token' => $new['token']]));
    $before = peracrm_campaigns_schema_option('peracrm_campaigns_schema_status');
    $stale_write = peracrm_campaigns_schema_publish($old, 'peracrm_campaigns_schema_status', ['state' => 'failed']);
    $stale_marker = peracrm_campaigns_schema_publish($old, 'peracrm_schema_version', 20);
    $stale_release = peracrm_campaigns_schema_release_slot($old);
    $intact = $before === peracrm_campaigns_schema_option('peracrm_campaigns_schema_status')
        && peracrm_campaigns_schema_owns_slot($new);
    peracrm_campaigns_schema_release_slot($new);
    return [$nested, $stale_write, $stale_marker, $stale_release, $intact];
});
campaign_expect($lock_checks === [null, false, false, false, true], 'non-reentrant ownership and stale-token publication/release fencing');
$GLOBALS['campaign_options'][2]['peracrm_campaigns_schema_status'] = ['state' => 'checking', 'version' => 22, 'checked_at' => gmdate('Y-m-d H:i:s')];
campaign_expect(is_wp_error(peracrm_campaigns_retry_schema()), 'recent interrupted attempt cannot be retried early');
$GLOBALS['campaign_options'][2]['peracrm_campaigns_schema_status']['checked_at'] = '2020-01-01 00:00:00';
$held_name = peracrm_with_target_blog(static function () { return peracrm_campaigns_schema_lock_name(); });
$wpdb->schema_locks[$held_name] = 2;
campaign_expect(is_wp_error(peracrm_campaigns_retry_schema()), 'even an old attempt cannot be taken over while its connection holds the slot');
unset($wpdb->schema_locks[$held_name]);
campaign_expect(peracrm_campaigns_retry_schema() === true && peracrm_campaigns_schema_ready(), 'interrupted old attempt recovers after the native slot is free');
foreach (['peracrm_manage_campaigns', 'peracrm_manage_subscribers', 'peracrm_view_campaign_reports'] as $cap) {
    foreach ([1 => true, 2 => true, 3 => false, 0 => false] as $actor => $expected) {
        $GLOBALS['campaign_actor'] = $actor;
        campaign_expect(peracrm_campaigns_staff_can($cap) === $expected, 'dedicated capability ' . $cap . ' actor ' . $actor);
    }
}
$mail_before_foundations = count($GLOBALS['campaign_mail']);
$evidence = ['source' => 'synthetic', 'source_ref' => 'fixture-1', 'evidence_ref' => 'consent-1', 'policy_version' => 'v1', 'occurred_at' => '2026-01-01 00:00:00'];
foreach ([0, 3] as $actor) {
    $GLOBALS['campaign_actor'] = $actor;
    foreach ([peracrm_campaigns_save_subscriber('denied@example.test'), peracrm_campaigns_record_consent('denied@example.test', 'subscribed', $evidence),
        peracrm_campaigns_add_suppression('denied@example.test', 'complaint', $evidence), peracrm_campaigns_withdraw('denied@example.test', $evidence),
        peracrm_campaigns_delete_subscriber('denied@example.test')] as $result) {
        campaign_expect(is_wp_error($result), 'anonymous/employee mutation denied');
    }
}
$GLOBALS['campaign_actor'] = 1;
$GLOBALS['campaign_impersonating'] = true;
foreach ([peracrm_campaigns_save_subscriber('denied@example.test'), peracrm_campaigns_record_consent('denied@example.test', 'subscribed', $evidence),
    peracrm_campaigns_add_suppression('denied@example.test', 'complaint', $evidence), peracrm_campaigns_withdraw('denied@example.test', $evidence),
    peracrm_campaigns_delete_subscriber('denied@example.test')] as $result) {
    campaign_expect(is_wp_error($result), 'impersonated mutation denied');
}
$GLOBALS['campaign_impersonating'] = false;
campaign_expect(campaign_count('peracrm_subscribers') === 0 && campaign_count('peracrm_email_suppressions') === 0, 'denied operations write nothing');
$id = peracrm_campaigns_save_subscriber('  First.Last+News@Example.test  ');
campaign_expect(is_int($id) && $id > 0, 'newsletter-only independent identity created');
$id2 = peracrm_campaigns_save_subscriber('FIRST.LAST+NEWS@example.test');
campaign_expect($id === $id2 && campaign_count('peracrm_subscribers') === 1, 'case and whitespace duplicates resolve to same mailbox');
campaign_expect(campaign_identity('first.last+news@example.test')['client_id'] === null, 'no automatic client/user link or creation');
peracrm_campaigns_save_subscriber('firstlast@example.test');
campaign_expect(campaign_count('peracrm_subscribers') === 2, 'plus tags and dots remain distinct');
campaign_expect(is_wp_error(peracrm_campaigns_save_subscriber('broken @example.test')), 'invalid address rejected before writing');
campaign_expect(!peracrm_campaigns_is_eligible('first.last+news@example.test'), 'unknown consent denied');
peracrm_campaigns_record_consent('first.last+news@example.test', 'pending', $evidence);
campaign_expect(!peracrm_campaigns_is_eligible('first.last+news@example.test'), 'pending consent denied');
campaign_expect(is_wp_error(peracrm_campaigns_record_consent('first.last+news@example.test', 'subscribed', ['source' => 'enquiry'])), 'missing marketing evidence cannot subscribe');
campaign_expect(peracrm_campaigns_record_consent('first.last+news@example.test', 'subscribed', $evidence) === true, 'documented explicit consent accepted');
$GLOBALS['campaign_actor'] = 0;
campaign_expect(peracrm_campaigns_is_eligible('first.last+news@example.test'), 'internal eligibility read needs no staff user');
$GLOBALS['campaign_actor'] = 2;
campaign_expect(!peracrm_campaigns_is_eligible('first.last+news@example.test', ['language' => 'en']) && !peracrm_campaigns_is_eligible('first.last+news@example.test', ['topic' => 'property']), 'missing positive audience data denied');
peracrm_campaigns_save_subscriber('first.last+news@example.test', ['preferred_language' => 'EN', 'preferences' => ['property']]);
campaign_expect(peracrm_campaigns_is_eligible('first.last+news@example.test', ['language' => 'en', 'topic' => 'property']) && !peracrm_campaigns_is_eligible('first.last+news@example.test', ['budget' => '100']), 'explicit preferences match and unsupported filters fail closed');
$GLOBALS['campaign_matches'] = [5, 6];
campaign_expect(is_wp_error(peracrm_campaigns_save_subscriber('first.last+news@example.test', ['client_id' => 5])), 'ambiguous client match remains unresolved');
$GLOBALS['campaign_matches'] = [5];
$GLOBALS['campaign_legacy'] = [6];
campaign_expect(is_wp_error(peracrm_campaigns_save_subscriber('first.last+news@example.test', ['client_id' => 5])), 'legacy alias ambiguity remains unresolved');
$GLOBALS['campaign_legacy'] = [];
peracrm_campaigns_save_subscriber('first.last+news@example.test', ['client_id' => 5]);
$GLOBALS['campaign_denied_clients'] = [5];
foreach ([null, ''] as $empty) {
    campaign_expect(!is_wp_error(peracrm_campaigns_save_subscriber('first.last+news@example.test', ['client_id' => $empty, 'preferred_language' => 'fr', 'preferences' => ['news']])), 'masked profile edit accepted');
    campaign_expect(campaign_identity('first.last+news@example.test')['client_id'] === 5, 'language/topics preserve inaccessible link');
}
campaign_expect(is_wp_error(peracrm_campaigns_unlink_subscriber_client('first.last+news@example.test')), 'inaccessible stored link cannot be unlinked');
$GLOBALS['campaign_matches'] = [6];
campaign_expect(is_wp_error(peracrm_campaigns_save_subscriber('first.last+news@example.test', ['client_id' => 6])), 'accessible replacement cannot remove inaccessible stored link');
campaign_expect(campaign_identity('first.last+news@example.test')['client_id'] === 5, 'crafted replacement preserves original link');
$GLOBALS['campaign_denied_clients'] = [];
$GLOBALS['campaign_matches'] = [5];
$GLOBALS['campaign_client_status'] = 'deleted';
campaign_expect(!peracrm_campaigns_is_eligible('first.last+news@example.test'), 'deleted linked client is ineligible before explicit repair');
$GLOBALS['campaign_actor'] = 2;
$manager_role = $GLOBALS['campaign_roles'][2]['manager'];
$manager_had_full = !empty($manager_role->caps['peracrm_manage_all_clients']);
$manager_role->remove_cap('peracrm_manage_all_clients');
campaign_expect(is_wp_error(peracrm_campaigns_unlink_subscriber_client('first.last+news@example.test')), 'scoped subscriber manager cannot clear a deleted link');
$GLOBALS['campaign_actor'] = 1;
$GLOBALS['campaign_roles'][2]['administrator']->add_cap('manage_options');
$repair_history = campaign_count('peracrm_subscription_events');
campaign_expect(peracrm_campaigns_unlink_subscriber_client('first.last+news@example.test') === true, 'full-scope administrator can clear deleted client link');
campaign_expect(campaign_identity('first.last+news@example.test')['client_id'] === null && peracrm_campaigns_is_eligible('first.last+news@example.test'), 'explicit repair restores newsletter-only eligibility');
campaign_expect(campaign_count('peracrm_subscription_events') === $repair_history + 1, 'repair writes one profile audit');
campaign_expect(peracrm_campaigns_unlink_subscriber_client('first.last+news@example.test') === true && campaign_count('peracrm_subscription_events') === $repair_history + 1, 'repeated repair is idempotent');
if ($manager_had_full) { $manager_role->add_cap('peracrm_manage_all_clients'); }
$GLOBALS['campaign_client_status'] = 'publish';
peracrm_campaigns_save_subscriber('first.last+news@example.test', ['client_id' => 5]);
$GLOBALS['campaign_disposition'] = 'duplicate';
campaign_expect(!peracrm_campaigns_is_eligible('first.last+news@example.test'), 'duplicate linked sales record excluded');
$GLOBALS['campaign_disposition'] = 'none';
campaign_expect(peracrm_campaigns_is_eligible('first.last+news@example.test'), 'valid linked client evaluated');
foreach (['hard_bounce', 'complaint', 'administrative'] as $reason) {
    campaign_expect(peracrm_campaigns_add_suppression('FIRST.LAST+NEWS@example.test', $reason, $evidence) === true, $reason . ' recorded distinctly');
    campaign_expect(campaign_identity('first.last+news@example.test')['subscription_state'] === 'subscribed' && !peracrm_campaigns_is_eligible('first.last+news@example.test'), 'suppression overrides still-subscribed permission');
}
peracrm_campaigns_withdraw('first.last+news@example.test', $evidence);
$row = campaign_identity('first.last+news@example.test');
campaign_expect($row['unsubscribe'] && $row['hard_bounce'] && $row['complaint'] && $row['administrative'], 'all active reasons preserved');
campaign_expect(!peracrm_campaigns_is_eligible('first.last+news@example.test'), 'suppression wins over permission');
foreach (['subscription_state' => 'subscribed', 'unsubscribe' => 0, 'email' => 'new@example.test'] as $field => $value) {
    campaign_expect(is_wp_error(peracrm_campaigns_save_subscriber('first.last+news@example.test', [$field => $value])), 'ordinary update cannot bypass identity or suppression via ' . $field);
}
campaign_expect(is_wp_error(peracrm_campaigns_record_consent('first.last+news@example.test', 'subscribed', $evidence)), 'explicit consent cannot bypass suppression');
$history = campaign_count('peracrm_subscription_events');
peracrm_campaigns_unlink_subscriber_client('first.last+news@example.test');
$GLOBALS['campaign_client_status'] = 'deleted';
campaign_expect(!peracrm_campaigns_is_eligible('first.last+news@example.test') && campaign_count('peracrm_subscription_events') === $history + 1, 'unlink/deleted client leaves history and suppression intact');
peracrm_campaigns_delete_subscriber('first.last+news@example.test');
$new_id = peracrm_campaigns_save_subscriber('first.last+news@example.test');
campaign_expect($new_id !== $id && !peracrm_campaigns_is_eligible('first.last+news@example.test') && campaign_identity('first.last+news@example.test')['complaint'], 'subscriber deletion/recreation preserves suppression and starts unknown');
peracrm_campaigns_save_subscriber('new-address@example.test');
campaign_expect(!peracrm_campaigns_is_eligible('new-address@example.test'), 'new address requires independent permission');
peracrm_campaigns_withdraw('absent@example.test', $evidence);
peracrm_campaigns_save_subscriber('absent@example.test');
campaign_expect(campaign_identity('absent@example.test')['unsubscribe'] === 1, 'withdrawal before identity creates durable tombstone');
peracrm_campaigns_save_subscriber('suppression-failure@example.test');
$wpdb->fail_insert = 'peracrm_email_suppression_events';
campaign_expect(is_wp_error(peracrm_campaigns_add_suppression('suppression-failure@example.test', 'complaint', $evidence))
    && !campaign_identity('suppression-failure@example.test')['complaint'], 'suppression history failure rolls back active reason');
$wpdb->fail_insert = '';
$count = campaign_count('peracrm_subscribers');
$wpdb->fail_insert = 'peracrm_subscription_events';
campaign_expect(is_wp_error(peracrm_campaigns_save_subscriber('failure@example.test')) && campaign_count('peracrm_subscribers') === $count, 'audit failure rolls back current state and restores context');
$wpdb->fail_insert = '';
campaign_expect(!isset($wpdb->tables['wp_1_peracrm_subscribers']), 'all mailbox storage is target-blog scoped');
$events = $wpdb->tables['wp_2_peracrm_subscription_events'];
campaign_expect(count(array_filter($events, static function ($row) { return $row['actor_user_id'] === 2; })) > 0, 'audit records real manager actor');
foreach (['source', 'source_ref', 'evidence_ref', 'policy_version'] as $field) {
    $address = 'zero-' . $field . '@example.test';
    $zero_evidence = $evidence;
    $zero_evidence[$field] = '0';
    $zero_id = peracrm_campaigns_save_subscriber($address);
    campaign_expect(peracrm_campaigns_record_consent($address, 'subscribed', $zero_evidence) === true
        && peracrm_campaigns_is_eligible($address), 'accepted string zero remains eligible for ' . $field);
    foreach (['missing', 'null', 'empty', 'whitespace', 'integer', 'boolean'] as $case) {
        $invalid = $zero_evidence;
        $bad_value = ['null' => null, 'empty' => '', 'whitespace' => ' ', 'integer' => 0, 'boolean' => false][$case] ?? null;
        if ($case === 'missing') { unset($invalid[$field]); } else { $invalid[$field] = $bad_value; }
        campaign_expect(is_wp_error(peracrm_campaigns_record_consent($address, 'subscribed', $invalid)), 'consent rejects ' . $case . ' ' . $field);
        $stored = &$wpdb->tables['wp_2_peracrm_subscribers'][$zero_id];
        if ($case === 'missing') { unset($stored[$field]); } else { $stored[$field] = $bad_value; }
        campaign_expect(!peracrm_campaigns_is_eligible($address), 'eligibility rejects stored ' . $case . ' ' . $field);
        $stored[$field] = '0';
        unset($stored);
    }
}
campaign_expect(count($GLOBALS['campaign_mail']) === $mail_before_foundations, 'foundation operations never send mail');
$provider = new PeraCRM_Email_Provider();
$operational = $provider->send(['source_label' => 'Synthetic enquiry', 'event_key' => 'fixture'], 'first.last+news@example.test');
campaign_expect($operational['ok'] && count($GLOBALS['campaign_mail']) === $mail_before_foundations + 1, 'operational enquiry provider retains behavior independently of newsletter suppression (fake wp_mail)');
$invalid = $provider->send([], 'invalid');
campaign_expect(!$invalid['ok'] && count($GLOBALS['campaign_mail']) === $mail_before_foundations + 1, 'operational provider still rejects invalid recipient');
echo "Campaign foundations behavior checks passed (stub DB/WP; real integration outstanding).\n";
