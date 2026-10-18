<?php
/** Real WordPress/MySQL Phase 2 regression; synthetic data only. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
function management_expect($ok, $label) { if (!$ok) { throw new RuntimeException('FAIL Phase 2: ' . $label); } echo 'PASS Phase 2: ' . $label . "\n"; }
function management_target(callable $fn) { return peracrm_with_target_blog($fn); }
function management_count($name) { global $wpdb; return management_target(static function () use ($wpdb, $name) { return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . peracrm_table($name)); }); }
function management_identity($email) { return management_target(static function () use ($email) { return peracrm_campaigns_repository_identity($email); }); }
function management_preview($rows, $synthetic = true, $complete = true) {
    $stream = fopen('php://temp', 'w+');
    fputcsv($stream, ['email','state','source','source_ref','evidence_ref','policy_version','occurred_at','language','topics'], ',', '"', '');
    foreach ($rows as $row) { fputcsv($stream, $row, ',', '"', ''); } rewind($stream);
    try { return peracrm_subscribers_preview($stream, array_flip(['email','state','source','source_ref','evidence_ref','policy_version','occurred_at','language','topics']), $synthetic, $complete); }
    finally { fclose($stream); }
}
function management_row($email, $state = 'subscribed', $evidence = 'consent-1') { return [$email,$state,'archive','source-1',$evidence,'v1','2020-01-02 03:04:05','en','news|properties']; }
function management_throws(callable $fn) { try { $fn(); return false; } catch (Throwable $error) { return true; } }
$manager = management_target(static function () { return (int) get_option('campaign_fixture_manager'); });
$admin = management_target(static function () { return (int) get_option('campaign_fixture_administrator'); });
$employee = management_target(static function () { return (int) get_option('campaign_fixture_employee'); });
wp_set_current_user($manager);
management_expect(peracrm_campaigns_schema_ready(), 'schema 22 ready');
// Rebuild the exact Phase 1 surface and upgrade it through real init.
management_target(static function () {
    global $wpdb;
    foreach (array_keys(peracrm_campaigns_phase2_definitions()) as $name) { $wpdb->query('DROP TABLE ' . peracrm_table($name)); }
    update_option('peracrm_schema_version', 21);
    update_option('peracrm_campaigns_schema_status', ['state' => 'ready', 'version' => 21]);
});
do_action('init');
management_expect(peracrm_campaigns_schema_ready(), 'real Phase 1 to Phase 2 migration');
management_expect(peracrm_subscribers_url() === management_target(static function () { return home_url('/crm/subscribers/'); }), 'staff URLs use target blog');
$clients_before = management_target(static function () { global $wpdb; return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}"); });
$users_before = count_users()['total_users'];
$counts_before = array_map('management_count', ['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events']);
$id = management_preview([
    management_row('phase2-yes@example.test'), management_row('PHASE2-YES@example.test'),
    management_row('phase2-conflict@example.test'), management_row('phase2-conflict@example.test','unsubscribe'),
    management_row('phase2-unknown@example.test','subscribed',''), management_row('bad email@example.test'),
    management_row('phase2-complaint@example.test','complaint'), management_row('phase2-bounce@example.test','hard_bounce'),
]);
management_expect($counts_before === array_map('management_count', ['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events']), 'preview changes no subscriber, consent or suppression tables');
$entries = peracrm_subscribers_batch_rows($id);
$second = json_decode($entries[1]['payload_json'], true);
$conflict = json_decode($entries[3]['payload_json'], true);
management_expect($second['preview']['duplicate'] && $conflict['preview']['conflict'] && $entries[5]['status'] === 'excluded', 'duplicate, conflicting and invalid preview rows');
$result = peracrm_subscribers_apply_batch($id);
management_expect($result['status'] === 'complete', 'bounded batch applies');
management_expect(peracrm_campaigns_is_eligible('phase2-yes@example.test'), 'complete evidence eligible after import');
management_expect(!management_identity('phase2-conflict@example.test'), 'suppression rows precede conflicting opt-in');
management_expect(management_identity('phase2-unknown@example.test')['subscription_state'] === 'unknown', 'missing evidence stays unknown');
management_target(static function () { global $wpdb; $events = peracrm_table('peracrm_subscription_events'); management_expect((int) $wpdb->get_var("SELECT COUNT(*) FROM {$events} WHERE event_type='suppression_imported' AND occurred_at='2020-01-02 03:04:05'") === 3, 'suppression intake retains historical batch audit even without a subscriber'); });
management_expect(management_identity('phase2-yes@example.test')['confirmed_at'] === '2020-01-02 03:04:05', 'historical consent time preserved');
management_expect(peracrm_subscribers_batch_rows($id) === [] && peracrm_subscribers_apply_batch($id) === $result, 'terminal payload cleanup and repeated apply is stable');
$id2 = management_preview([management_row('phase2-yes@example.test'),management_row('phase2-conflict@example.test')]);
$events = management_count('peracrm_subscription_events');
peracrm_subscribers_apply_batch($id2);
management_expect($events === management_count('peracrm_subscription_events'), 'reimport cannot change existing identity or resubscribe suppression');
// Confirmation and readiness are independent gates.
$not_confirmed = management_preview([management_row('phase2-unconfirmed@example.test')], true, false);
management_expect(management_throws(static function () use ($not_confirmed) { peracrm_subscribers_apply_batch($not_confirmed); }) && !management_identity('phase2-unconfirmed@example.test'), 'suppression-source confirmation required');
$blocked_partial = management_preview([management_row('phase2-partial-unknown@example.test','unknown',''),management_row('phase2-partial-optin@example.test')], true, false);
management_expect(management_throws(static function () use ($blocked_partial) { peracrm_subscribers_apply_batch($blocked_partial); }), 'later confirmation failure reports partial application');
management_expect(management_identity('phase2-partial-unknown@example.test')['subscription_state'] === 'unknown' && !management_identity('phase2-partial-optin@example.test'), 'confirmation failure never grants permission');
$blocked_summary = json_decode(peracrm_subscribers_batch($blocked_partial)['summary_json'],true);
management_expect(count(array_filter($blocked_summary, static function ($row) { return $row['status'] === 'done' && (int) $row['total'] === 1; })) === 1, 'partial application summary persists even when a later eligibility guard throws');
peracrm_subscribers_cancel_batch($blocked_partial);
$real = management_preview([management_row('phase2-real@example.test')], false);
management_expect(management_throws(static function () use ($real) { peracrm_subscribers_apply_batch($real); }), 'real-data gate disabled by default, previews remain available');
management_expect(management_throws(static function () { peracrm_subscribers_readiness(['enabled' => true]); }), 'manager cannot open readiness gate');
wp_set_current_user($admin);
management_expect(management_throws(static function () { peracrm_subscribers_readiness(['enabled' => true]); }), 'administrator cannot enable without decisions');
peracrm_subscribers_readiness(['enabled' => true,'consent_mapping' => 'fixture-map','suppression_sources' => 'fixture-inventory','retention' => 'fixture-retention']);
wp_set_current_user($manager);
management_expect(peracrm_subscribers_apply_batch($real)['status'] === 'complete', 'administrator decisions enable real-mode synthetic fixture');
wp_set_current_user($admin);
peracrm_subscribers_readiness(['enabled' => false,'consent_mapping' => 'fixture-map','suppression_sources' => 'fixture-inventory','retention' => 'fixture-retention']);
wp_set_current_user($manager);
// Partial requests, cancellation, expiry and bounded retry failures.
$many = []; for ($i=0;$i<27;$i++) { $many[] = management_row('phase2-many-' . $i . '@example.test'); }
$partial = management_preview($many);
management_expect(peracrm_subscribers_apply_batch($partial)['status'] === 'applying', 'first request commits only 25 rows');
management_expect(peracrm_subscribers_apply_batch($partial)['status'] === 'complete', 'second request resumes remaining rows');
$cancel = management_preview([management_row('phase2-cancel@example.test')]); peracrm_subscribers_cancel_batch($cancel);
management_expect(peracrm_subscribers_batch_rows($cancel) === [] && !management_identity('phase2-cancel@example.test'), 'cancel cleans payload without applying');
$cleanup_retry = management_preview([management_row('phase2-cleanup-retry@example.test')]);
$fail_cleanup = static function ($sql) use ($cleanup_retry) { return str_starts_with($sql,'DELETE FROM') && str_contains($sql,'peracrm_subscriber_import_rows') && str_contains($sql,$cleanup_retry) ? 'DELETE FROM peracrm_fixture_nonexistent' : $sql; };
add_filter('query',$fail_cleanup);
management_expect(management_throws(static function () use ($cleanup_retry) { peracrm_subscribers_cancel_batch($cleanup_retry); }), 'cleanup storage failure is reported');
remove_filter('query',$fail_cleanup);
management_expect(peracrm_subscribers_batch($cleanup_retry)['status'] === 'preview' && count(peracrm_subscribers_batch_rows($cleanup_retry)) === 1, 'failed cleanup rolls back terminal status and remains retryable');
peracrm_subscribers_cancel_batch($cleanup_retry);
management_expect(peracrm_subscribers_batch_rows($cleanup_retry) === [], 'retried cancellation removes payload');
$expiry = management_preview([management_row('phase2-expiry@example.test')]);
management_target(static function () use ($expiry) { global $wpdb; $wpdb->update(peracrm_table('peracrm_subscriber_batches'), ['expires_at' => '2000-01-01 00:00:00'], ['id' => $expiry]); });
peracrm_subscribers_expire_batches();
management_expect(peracrm_subscribers_batch($expiry)['status'] === 'expired' && peracrm_subscribers_batch_rows($expiry) === [], 'timeout deletes payloads and retains durable summary');
$failure = management_preview([management_row('phase2-storage-fail@example.test','complaint'), management_row('phase2-after-fail@example.test')]);
$fail_query = static function ($sql) { return str_contains($sql, 'INSERT INTO') && str_contains($sql, 'peracrm_email_suppression_events') && str_contains($sql, 'phase2-storage-fail') ? 'INSERT INTO peracrm_fixture_nonexistent VALUES (1)' : $sql; };
add_filter('query', $fail_query);
for ($attempt=0;$attempt<3;$attempt++) { peracrm_subscribers_apply_batch($failure); }
remove_filter('query', $fail_query);
management_expect(peracrm_subscribers_batch($failure)['status'] === 'partial_failed' && !management_identity('phase2-after-fail@example.test'), 'three storage failures terminate safely; suppressions fail closed');
management_expect(peracrm_subscribers_batch_rows($failure) === [], 'permanent failure cleans remaining payloads');
// Cross-user reads and writes, employee and anonymous reads, impersonation.
wp_set_current_user($admin);
management_expect(management_throws(static function () use ($id) { peracrm_subscribers_batch($id); }), 'administrator cannot read another owner batch');
wp_set_current_user($employee);
management_expect(management_throws(static function () { peracrm_subscribers_list(); }) && management_throws(static function () { management_preview([]); }), 'employee cannot list or import');
wp_set_current_user(0);
management_expect(management_throws(static function () { peracrm_subscribers_detail(1); }), 'anonymous cannot read subscriber detail');
wp_set_current_user($manager);
management_target(static function () use ($manager,$employee) { update_user_meta($manager, PERACRM_VIEW_AS_USER_META_KEY, $employee); });
management_expect(!peracrm_campaigns_staff_can('peracrm_manage_subscribers', true) && management_throws(static function () { management_preview([]); }), 'impersonated import writes denied');
delete_user_meta($manager, PERACRM_VIEW_AS_USER_META_KEY);
// Token service works without a staff session and stores only digests.
$token = peracrm_subscribers_issue_token('phase2-yes@example.test');
$other = peracrm_subscribers_issue_token('phase2-unknown@example.test');
management_expect(strlen($token) === 64 && strpos(peracrm_subscribers_preferences_url($token), '@') === false && strpos(peracrm_subscribers_preferences_url($token), 'phase2-yes') === false, 'opaque authoritative token URL has no mailbox or CRM ID');
$token_row = management_target(static function () use ($token) { return peracrm_subscribers_resolve_token($token); });
management_expect($token_row['digest'] === hash('sha256',$token) && !in_array($token, $token_row, true), 'stored token is digest-only');
$before = management_count('peracrm_subscription_events');
management_expect(peracrm_subscribers_public_valid($token) && management_count('peracrm_subscription_events') === $before, 'GET validation has no subscriber mutation');
wp_set_current_user(0);
management_expect(!peracrm_subscribers_public_post(str_repeat('0',64), 'withdraw'), 'invalid POST denies uniformly');
management_expect(peracrm_subscribers_public_post($token,'preferences',['news']) && peracrm_campaigns_is_eligible('phase2-yes@example.test'), 'topic change preserves existing consent');
management_expect(peracrm_subscribers_public_post($token,'withdraw'), 'valid public POST withdraws immediately without staff authorization');
$before = management_count('peracrm_subscription_events'); $before_x = management_count('peracrm_email_suppression_events');
management_expect(peracrm_subscribers_public_post($token,'withdraw') && $before === management_count('peracrm_subscription_events') && $before_x === management_count('peracrm_email_suppression_events'), 'repeated opt-out is idempotent without duplicate audit');
management_expect(peracrm_subscribers_public_post($token,'preferences',['properties']) && !peracrm_campaigns_is_eligible('phase2-yes@example.test'), 'topic changes cannot clear global suppression');
management_expect(management_identity('phase2-unknown@example.test')['subscription_state'] === 'unknown', 'token cannot select another mailbox');
management_expect(peracrm_subscribers_public_post($other,'preferences',['news']) && !peracrm_campaigns_is_eligible('phase2-unknown@example.test'), 'topics never grant unknown consent');
wp_set_current_user($manager);
peracrm_subscribers_revoke_token($other);
management_expect(!peracrm_subscribers_public_valid($other) && !peracrm_subscribers_public_post($other,'withdraw'), 'revoked tokens denied');
management_target(static function () use ($token) { global $wpdb; $wpdb->update(peracrm_table('peracrm_subscriber_tokens'), ['purpose' => 'confirmation'], ['digest' => hash('sha256',$token)]); });
management_expect(!peracrm_subscribers_public_valid($token), 'wrong token purpose rejected');
management_target(static function () use ($token) { global $wpdb; $wpdb->update(peracrm_table('peracrm_subscriber_tokens'), ['purpose' => 'preferences','version' => 2], ['digest' => hash('sha256',$token)]); });
management_expect(!peracrm_subscribers_public_valid($token), 'unsupported token version rejected');
management_target(static function () use ($token) { global $wpdb; $wpdb->update(peracrm_table('peracrm_subscriber_tokens'), ['version' => 1,'expires_at' => '2000-01-01 00:00:00'], ['digest' => hash('sha256',$token)]); });
management_expect(!peracrm_subscribers_public_valid($token), 'expired token rejected');
foreach (['=HYPERLINK(1)',' +SUM(1)','@cmd',"\t-2",'-1','+2',"\rvalue"] as $cell) { management_expect(str_starts_with(peracrm_subscribers_csv_cell($cell), "'"), 'formula-like exported cell neutralized'); }
management_expect(peracrm_subscribers_csv_cell('normal') === 'normal', 'ordinary CSV cell preserved');
$stream = fopen('php://temp','w+'); fwrite($stream, str_repeat('a',1048577)); rewind($stream);
management_expect(management_throws(static function () use ($stream) { peracrm_subscribers_parse_csv($stream, array_flip(['email','state','source','source_ref','evidence_ref','policy_version','occurred_at'])); }), 'oversize stream rejected'); fclose($stream);
management_expect(management_throws(static function () { management_preview([management_row('real@example.com')],true); }), 'synthetic flag cannot bypass real intake gate');
$clients_after = management_target(static function () { global $wpdb; return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}"); });
management_expect($clients_before === $clients_after && $users_before === count_users()['total_users'], 'all import and preference operations create no clients or users');
management_expect(get_current_blog_id() === 1 && (!is_multisite() || !ms_is_switched()), 'Phase 2 restores originating blog across success and errors');
management_expect(management_target(static function () { return get_stylesheet() !== 'hello-elementor-child'; }), 'fixtures use bundled default WordPress theme');
echo "PASS Phase 2 real WordPress/MySQL regression complete\n";

wp_set_current_user($manager);
$browser_token = peracrm_subscribers_issue_token('phase2-real@example.test');
file_put_contents('/var/www/html/phase2-fixture.json', wp_json_encode(['target' => management_target(static function () { return home_url('/'); }), 'token' => $browser_token]));
$ambiguous = management_target(static function () {
    $ids = [];
    for ($i=0;$i<2;$i++) { $id = wp_insert_post(['post_type'=>'crm_client','post_status'=>'draft','post_title'=>'Synthetic ambiguous fixture']); update_post_meta($id,'_peracrm_email','phase2-ambiguous@example.test'); $ids[] = $id; }
    return $ids;
});
$amb = management_preview([management_row('phase2-ambiguous@example.test')]);
$amb_row = json_decode(peracrm_subscribers_batch_rows($amb)[0]['payload_json'],true);
management_expect($amb_row['preview']['client_match'] === 'ambiguous_unresolved', 'ambiguous mailbox matches reported and unresolved');
peracrm_subscribers_apply_batch($amb);
management_expect(empty(management_identity('phase2-ambiguous@example.test')['client_id']), 'import never automatically links ambiguous CRM records');
management_expect(is_wp_error(peracrm_campaigns_save_subscriber('phase2-ambiguous@example.test',['client_id'=>(int)$ambiguous[0]])), 'explicit ambiguous client link rejected');
management_expect(management_target(static function () use ($ambiguous) { return get_post_status($ambiguous[0]) === 'draft' && get_post_status($ambiguous[1]) === 'draft'; }), 'ambiguity never republishes, merges or trashes clients');

// A subscriber manager with assigned-client scope must preserve masked links.
$link_fixture = management_target(static function () use ($manager) {
    $restricted = wp_insert_user(['user_login' => 'fixture-subscriber-scoped', 'user_pass' => 'fixture-scoped-only', 'role' => 'subscriber']);
    $user = new WP_User($restricted);
    // Multisite user_register assigns the lead role before fixture staff caps exist.
    $user->set_role('subscriber');
    $user->add_cap('peracrm_manage_subscribers'); $user->add_cap('edit_crm_clients');
    $client = wp_insert_post(['post_type' => 'crm_client', 'post_status' => 'draft', 'post_title' => 'Synthetic hidden client']);
    update_post_meta($client, '_peracrm_email', 'phase2-hidden@example.test');
    $replacement = wp_insert_post(['post_type' => 'crm_client', 'post_status' => 'draft', 'post_title' => 'Synthetic accessible replacement']);
    update_post_meta($replacement, 'assigned_advisor_user_id', $restricted);
    return ['user' => $restricted, 'client' => $client, 'replacement' => $replacement];
});
$hidden_id = peracrm_campaigns_save_subscriber('phase2-hidden@example.test', ['client_id' => (int) $link_fixture['client']]);
management_expect(!is_wp_error($hidden_id), 'authorized explicit link succeeds');
wp_set_current_user($link_fixture['user']);
management_expect(peracrm_subscribers_detail($hidden_id)['client_id'] === null, 'inaccessible client masked on read');
management_expect(!is_wp_error(peracrm_campaigns_save_subscriber('phase2-hidden@example.test', ['client_id' => null, 'preferred_language' => 'fr', 'preferences' => ['news']])), 'masked profile edit accepted');
management_expect((int) management_identity('phase2-hidden@example.test')['client_id'] === (int) $link_fixture['client'], 'language/topics retain inaccessible stored link');
management_expect(is_wp_error(peracrm_campaigns_unlink_subscriber_client('phase2-hidden@example.test')), 'scoped manager cannot explicitly unlink hidden client');
management_expect(is_wp_error(peracrm_campaigns_save_subscriber('phase2-hidden@example.test', ['client_id' => (int) $link_fixture['replacement']])), 'scoped manager cannot replace hidden client');
peracrm_campaigns_withdraw('phase2-hidden@example.test', ['source' => 'fixture']);
wp_set_current_user($manager);
management_expect(peracrm_campaigns_unlink_subscriber_client('phase2-hidden@example.test') === true, 'authorized explicit unlink succeeds');
management_expect(peracrm_campaigns_unlink_subscriber_client('phase2-hidden@example.test') === true, 'authorized repeated unlink idempotent');
management_expect(!peracrm_campaigns_is_eligible('phase2-hidden@example.test') && management_identity('phase2-hidden@example.test')['unsubscribe'], 'unlink preserves suppression and ineligibility');
peracrm_campaigns_save_subscriber('phase2-hidden@example.test', ['client_id' => (int) $link_fixture['client']]);
$browser_fixture = json_decode(file_get_contents('/var/www/html/phase2-fixture.json'), true);
$browser_fixture['hidden_id'] = $hidden_id;
$browser_fixture['replacement'] = $link_fixture['replacement'];
file_put_contents('/var/www/html/phase2-fixture.json', wp_json_encode($browser_fixture));

// Apache fixture owns no host port; these rules only serve synthetic browser checks.
$stale = management_target(static function () {
    $client = wp_insert_post(['post_type' => 'crm_client', 'post_status' => 'draft', 'post_title' => 'Synthetic deleted link']);
    update_post_meta($client, '_peracrm_email', 'phase3-stale@example.test');
    return (int) $client;
});
$stale_id = peracrm_campaigns_save_subscriber('phase3-stale@example.test', ['client_id' => $stale]);
management_expect(!is_wp_error($stale_id), 'create explicit stale-link fixture');
management_target(static function () use ($stale) { wp_delete_post($stale, true); });
wp_set_current_user($link_fixture['user']);
management_expect(empty(peracrm_subscribers_detail($stale_id)['can_unlink_client']) && is_wp_error(peracrm_campaigns_unlink_subscriber_client('phase3-stale@example.test')), 'scoped staff cannot repair absent assignment');
wp_set_current_user($manager);
management_expect(peracrm_subscribers_detail($stale_id)['can_unlink_client'] && peracrm_subscribers_detail($stale_id)['client_id'] === null, 'full-scope staff see repair action without dead link');
management_expect(peracrm_campaigns_unlink_subscriber_client('phase3-stale@example.test') === true && empty(management_identity('phase3-stale@example.test')['client_id']), 'explicit deleted reference repair persists');
management_expect(management_identity('phase3-stale@example.test')['subscription_state'] === 'unknown', 'repair never grants consent');
$browser_stale = management_target(static function () {
    $client = wp_insert_post(['post_type' => 'crm_client', 'post_status' => 'draft', 'post_title' => 'Synthetic browser deleted link']);
    update_post_meta($client, '_peracrm_email', 'phase3-stale@example.test');
    return (int) $client;
});
peracrm_campaigns_save_subscriber('phase3-stale@example.test', ['client_id' => $browser_stale]);
management_target(static function () use ($browser_stale) { wp_delete_post($browser_stale, true); });
$browser_fixture = json_decode(file_get_contents('/var/www/html/phase2-fixture.json'), true);
$browser_fixture['stale_id'] = $stale_id;
file_put_contents('/var/www/html/phase2-fixture.json', wp_json_encode($browser_fixture));
$lookup_queries = [];
$count_lookups = static function ($sql) use (&$lookup_queries) {
    if (str_contains($sql, 'COUNT(DISTINCT p.ID)') || str_contains($sql, '1 AS existing')) { $lookup_queries[] = $sql; }
    return $sql;
};
add_filter('query', $count_lookups);
$lookup_emails = array_map(static function ($n) { return 'phase3-bounded-' . $n . '@example.test'; }, range(0, 999));
$lookup_states = peracrm_subscribers_preview_states($lookup_emails);
remove_filter('query', $count_lookups);
management_expect(count($lookup_states) === 1000 && count($lookup_queries) === 20, 'real maximum-size lookup performs 20 queries');
$lookup_states = peracrm_subscribers_preview_states(['phase2-ambiguous@example.test', 'phase2-yes@example.test', 'phase3-bounded-0@example.test']);
management_expect($lookup_states['phase2-ambiguous@example.test']['client_match'] === 'ambiguous_unresolved', 'real aggregated alias lookup preserves ambiguous relationships');
management_expect($lookup_states['phase2-yes@example.test']['existing'] && $lookup_states['phase2-yes@example.test']['suppressed'], 'real batched lookup preserves subscriber and suppression');
management_expect($lookup_states['phase3-bounded-0@example.test'] === ['existing' => false, 'suppressed' => false, 'client_match' => 'none'], 'real absent lookup has no fabricated CRM data');
$alias_client = management_target(static function () {
    $client = wp_insert_post(['post_type' => 'crm_client', 'post_status' => 'draft', 'post_title' => 'Synthetic alias counts']);
    foreach (['crm_primary_email','primary_email','crm_primary_email_normalized','primary_email_normalized','_peracrm_email'] as $key) {
        add_post_meta($client, $key, ' Phase3-Aliases@Example.test ');
    }
    $trashed = wp_insert_post(['post_type' => 'crm_client', 'post_status' => 'trash', 'post_title' => 'Synthetic excluded match']);
    update_post_meta($trashed, '_peracrm_email', 'phase3-aliases@example.test');
    return $client;
});
$lookup_states = peracrm_subscribers_preview_states(['phase3-aliases@example.test']);
management_expect($lookup_states['phase3-aliases@example.test']['client_match'] === 'available_unlinked', 'five normalized aliases count one distinct client and exclude trash');
peracrm_campaigns_add_suppression('phase3-no-profile@example.test', 'complaint', ['source' => 'fixture']);
$lookup_states = peracrm_subscribers_preview_states(['phase3-no-profile@example.test']);
management_expect($lookup_states['phase3-no-profile@example.test']['suppressed'] && !$lookup_states['phase3-no-profile@example.test']['existing'], 'real suppression without subscriber is preserved');
management_expect(get_current_blog_id() === 1 && (!is_multisite() || !ms_is_switched()), 'new repair and lookup restore original blog');
file_put_contents('/var/www/html/.htaccess', "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]\nRewriteRule ^([_0-9a-zA-Z-]+/)?(.*\\.php)$ $2 [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n");

management_target(static function () use ($employee) { wp_set_password('fixture-employee-only', $employee); });
$_SERVER['HTTP_HOST'] = 'fixture.test';
management_expect(management_target(static function () { return peracrm_subscribers_on_authoritative_domain(); }), 'public endpoint accepts authoritative target host');
$_SERVER['HTTP_HOST'] = 'wrong.example.test';
management_expect(!management_target(static function () { return peracrm_subscribers_on_authoritative_domain(); }), 'public endpoint rejects alternate host before mutation');
$_SERVER['HTTP_HOST'] = 'fixture.test';
if (is_multisite()) { management_expect(!peracrm_subscribers_on_authoritative_domain(), 'public endpoint rejects originating blog even on same network domain'); }
