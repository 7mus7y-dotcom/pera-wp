<?php
/** Real SQL only; all addresses, IDs and keys are disposable synthetic fixtures. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/recipient-persistence-fixture.php';
function persistence_expect($ok, string $label): void
{
    if (!$ok) { throw new RuntimeException('FAIL persistence: '.$label); }
    echo 'PASS persistence: '.$label."\n";
}
function persistence_reject(callable $fn): void
{
    try { $fn(); } catch (Throwable $e) {
        persistence_expect($e->getMessage() === 'recipient_persistence_unavailable' && $e->getPrevious() === null,'fixed unchained persistence diagnostic'); return;
    }
    throw new RuntimeException('FAIL persistence: expected rejection');
}
function peracrm_test_persistence_sql(array $content): void
{
    $origin = get_current_blog_id();
    peracrm_with_target_blog(static function () use ($content) {
        global $wpdb;
        $blog = get_current_blog_id();
        // Reconstruct only the newly added tables as absent on a v28 site.
        // Existing subscriber/snapshot/queue tables and their bytes stay intact.
        foreach (array_keys(peracrm_campaign_recipient_definitions()) as $suffix) { $wpdb->query('DROP TABLE '.peracrm_table($suffix)); }
        update_option('peracrm_schema_version',28);
        update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>28]);
        persistence_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema(),'additive v28 migration and repeated readiness');
        peracrm_test_persistence_projection($content,'persistence_expect',$blog);
        [$binding,$output,$submission] = peracrm_test_persistence_values($content,$blog);
        peracrm_test_persistence_subscriber(17001,$binding['mailbox']);
        require_once __DIR__.'/atomic-preparation.php';
        atomic_preparation_sql($content);
        atomic_unsubscribe_headers_sql($content);
        update_option('atomic_preparation_content',$content,false);
        $store = new PeraCRM_CampaignRecipientPersistence('fixture-persistence',$blog);
        persistence_expect($store->ensureIdentity(17001,$binding['mailbox']) === 1 && $store->ensureIdentity(17001,$binding['mailbox']) === 1,'identity creation idempotent');
        persistence_reject(fn()=>$store->ensureIdentity(17001,'conflict@example.org'));
        $tokens = new PeraCRM_CampaignRecipientTokens(['k1'=>str_repeat('synthetic-key-',4)],'k1','fixture-persistence',[$store,'activeBinding']);
        $issued = 1800000000;
        $withdraw = $tokens->issue('unsubscribe',$binding,$issued); $digest = hash('sha256',$withdraw);
        persistence_expect(!$tokens->verify($withdraw,'unsubscribe',$binding,$issued),'unregistered cryptographic token denies');
        $store->registerToken($digest,'unsubscribe','k1',$issued,0,$binding);
        $store->registerToken($digest,'unsubscribe','k1',$issued,0,array_reverse($binding,true));
        persistence_expect($tokens->verify($withdraw,'unsubscribe',$binding,$issued),'durable digest callback verifies');
        $wrongMetadata = $tokens->issue('unsubscribe',$binding,$issued);
        $store->registerToken(hash('sha256',$wrongMetadata),'unsubscribe','k2',$issued,0,$binding);
        persistence_expect(!$tokens->verify($wrongMetadata,'unsubscribe',$binding,$issued),'registry key version must match authenticated token metadata');
        $rotated = new PeraCRM_CampaignRecipientTokens(['k1'=>str_repeat('synthetic-key-',4),'k2'=>str_repeat('new-synthetic-key-',4)],'k2','fixture-persistence',[$store,'activeBinding']);
        persistence_expect($rotated->verify($withdraw,'unsubscribe',$binding,$issued+86400*365*20),'routine rotation preserves withdrawal without registry change');
        $pref = $tokens->issue('preferences',$binding,$issued); $prefDigest = hash('sha256',$pref);
        $store->registerToken($prefDigest,'preferences','k1',$issued,$issued+86400,$binding);
        persistence_expect(!$tokens->verify($pref,'preferences',$binding,$issued+86400) && $tokens->verify($withdraw,'unsubscribe',$binding,$issued+86400),'preference expiry independent of withdrawal');
        persistence_reject(fn()=>$store->registerToken($digest,'preferences','k1',$issued,$issued+86400,$binding));
        persistence_reject(fn()=>$store->registerToken($digest,'unsubscribe','k2',$issued,0,$binding));
        persistence_expect($store->bindAttempt($submission,$binding,$output) === $submission->hash()
            && $store->bindAttempt($submission,array_reverse($binding,true),$output) === $submission->hash(),'exact attempt creation idempotent');
        $before = $store->attempt($binding['attempt_key']);
        $cipher = new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('s',32)],'e1');
        $store->persistOutput($submission,$binding,$output,$cipher);
        [$recoveredSubmission,$recoveredOutput] = $store->recoverOutput($binding,$before,$cipher);
        persistence_expect($recoveredSubmission->fields() === $submission->fields()
            && $recoveredOutput->fields() === $output->fields(),'actual approved SQL capture expansion and random bearer bytes recover exactly');

        foreach (['delivery_key'=>str_repeat('e',64),'snapshot_id'=>62,'revision_id'=>52,'recipient_id'=>72,'subject'=>'Changed synthetic subject'] as $key=>$value) {
            $data = $submission->fields(); $data[$key] = $value;
            persistence_reject(fn()=>$store->bindAttempt(new PeraCRM_CampaignSubmissionV1($data),$binding,$output));
        }
        foreach (['account_id'=>'999999999999','region'=>'eu-west-2','configuration_set'=>'other','configuration_version'=>2] as $key=>$value) {
            $data = $submission->fields(); $data['scope'][$key] = $value;
            persistence_reject(fn()=>$store->bindAttempt(new PeraCRM_CampaignSubmissionV1($data),$binding,$output));
        }
        persistence_expect($store->attempt($binding['attempt_key']) === $before,'conflicts preserve immutable historical attempt');
        // Fail COMMIT after an INSERT to prove the complete new binding rolls back.
        $failCommit = static fn($sql)=>$sql === 'COMMIT' ? 'INVALID_PERSISTENCE_FIXTURE_COMMIT' : $sql;
        $rollbackDigest = hash('sha256','rollback-token');
        add_filter('query',$failCommit);
        persistence_reject(fn()=>$store->registerToken($rollbackDigest,'unsubscribe','k1',$issued,0,$binding));
        remove_filter('query',$failCommit);
        persistence_expect($store->locate($rollbackDigest) === null,'token creation rolls back on commit failure');
        $otherBinding = array_replace($binding,['attempt_key'=>hash('sha256','rollback-attempt')]);
        $otherOutput = new PeraCRM_CampaignRecipientOutput($output->fields()['html'],$output->fields()['text'],$binding['snapshot_hash'],hash('sha256',peracrm_campaign_recipient_binding($otherBinding)));
        $data = $submission->fields(); $data['attempt_key'] = $otherBinding['attempt_key'];
        add_filter('query',$failCommit);
        persistence_reject(fn()=>$store->bindAttempt(new PeraCRM_CampaignSubmissionV1($data),$otherBinding,$otherOutput));
        remove_filter('query',$failCommit);
        persistence_expect($store->attempt($otherBinding['attempt_key']) === null,'attempt creation rolls back on commit failure');
        add_filter('query',$failCommit);
        persistence_reject(fn()=>$store->changeIdentity(17001,1,$binding['mailbox'],true));
        remove_filter('query',$failCommit);
        persistence_expect($tokens->verify($withdraw,'unsubscribe',$binding,$issued),'failed epoch update rolls back revocation');
        add_filter('query',$failCommit);
        persistence_reject(fn()=>$store->revokeToken($digest));
        remove_filter('query',$failCommit);
        persistence_expect($tokens->verify($withdraw,'unsubscribe',$binding,$issued),'failed individual revocation rolls back');
        $store->revokeToken($digest); $store->revokeToken($digest);
        $store->registerToken($digest,'unsubscribe','k1',$issued,0,$binding);
        persistence_expect(!$tokens->verify($withdraw,'unsubscribe',$binding,$issued),'revocation monotonic across identical registration');
        $store->changeIdentity(17001,1,'changed@example.org',false);
        persistence_expect(!$tokens->verify($pref,'preferences',$binding,$issued),'mailbox change revokes prior identity');
        persistence_reject(fn()=>$store->changeIdentity(17001,1,$binding['mailbox'],true));
        $store->changeIdentity(17001,2,$binding['mailbox'],false);
        persistence_reject(fn()=>$store->ensureIdentity(17001,$binding['mailbox']));
        $store->changeIdentity(17001,3,$binding['mailbox'],true);
        persistence_expect(!$tokens->verify($pref,'preferences',$binding,$issued),'returning to old mailbox cannot revive old epoch');
        persistence_expect($store->attempt($binding['attempt_key']) === $before,'revocation preserves historical attempt bytes');
        $clone = new PeraCRM_CampaignRecipientPersistence('fixture-restored',$blog);
        persistence_expect($clone->locate($prefDigest) === null && $clone->attempt($binding['attempt_key']) === null,'new restore deployment isolates copied bindings');
        $clone->ensureIdentity(17001,$binding['mailbox']);
        $clone->registerToken($prefDigest,'preferences','k1',$issued,$issued+86400,$binding);
        $misconfigured = new PeraCRM_CampaignRecipientTokens(['k1'=>str_repeat('synthetic-key-',4)],'k1','fixture-persistence',[$clone,'activeBinding']);
        persistence_expect(!$misconfigured->verify($pref,'preferences',$binding,$issued),'mismatched issuer/store deployment fails even with copied digest and key');
        persistence_expect($clone->bindAttempt($submission,$binding,$output) === $submission->hash(),'same opaque keys isolated across deployment');
        $wrongBlog = new PeraCRM_CampaignRecipientPersistence('fixture-persistence',$blog+1);
        persistence_reject(fn()=>$wrongBlog->attempt($binding['attempt_key']));
        $wpdb->query('START TRANSACTION');
        $wpdb->insert(peracrm_table('peracrm_campaign_recipient_identities'),['deployment'=>'fixture-nested','blog_id'=>$blog,
            'subscriber_id'=>17998,'mailbox'=>'outer@example.org','token_epoch'=>1,'active'=>1]);
        persistence_reject(fn()=>$store->ensureIdentity(17999,'nested@example.org'));
        persistence_expect((int)$wpdb->get_var('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_recipient_identities').' WHERE subscriber_id=17998') === 1,'nested rejection preserves uncommitted outer work');
        $wpdb->query('ROLLBACK');
        persistence_expect((int)$wpdb->get_var('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_recipient_identities').' WHERE subscriber_id IN (17998,17999)') === 0,'nested call neither commits nor inserts');
        $wpdb->query('SET autocommit=0');
        persistence_reject(fn()=>$store->ensureIdentity(17999,'nested@example.org'));
        $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit=1');
        $rows = $wpdb->get_col('SELECT binding_json FROM '.peracrm_table('peracrm_campaign_submission_attempts'));
        persistence_expect(!str_contains(implode('',$rows),'token=') && !str_contains(implode('',$rows),'Synthetic subject'),'SQL metadata contains no raw bearer/body');
        // Real full-length binary unique constraints are independently exercised.
        $table = peracrm_table('peracrm_campaign_submission_attempts'); $quiet = $wpdb->suppress_errors(true);
        persistence_expect($wpdb->query("INSERT INTO {$table} SELECT * FROM {$table} LIMIT 1") === false,'SQL uniqueness rejects duplicate attempt directly');
        $wpdb->suppress_errors($quiet);
        foreach (['recipient_identities','recipient_tokens'] as $suffix) {
            $table = peracrm_table('peracrm_campaign_'.$suffix); $quiet = $wpdb->suppress_errors(true);
            persistence_expect($wpdb->query("INSERT INTO {$table} SELECT * FROM {$table} LIMIT 1") === false,'SQL uniqueness rejects duplicate '.$suffix.' directly');
            $wpdb->suppress_errors($quiet);
        }
    });
    persistence_expect(get_current_blog_id() === $origin,'origin context preserved');
    if (is_multisite()) {
        $target = (int)peracrm_get_target_blog_id();
        $store = new PeraCRM_CampaignRecipientPersistence('fixture-persistence',$target);
        persistence_reject(fn()=>$store->attempt(hash('sha256','fixture-persistence-attempt')));
        global $wpdb;
        persistence_expect($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'peracrm_campaign_submission_attempts')) === null,'no attempt table leaked to originating site');
    }
}
