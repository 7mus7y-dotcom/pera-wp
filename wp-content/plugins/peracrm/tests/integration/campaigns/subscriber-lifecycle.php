<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/recipient-persistence.php';
require_once WP_PLUGIN_DIR.'/peracrm/tests/integration/campaigns/recipient-persistence.php';
$manager = peracrm_with_target_blog(static fn() => (int)get_option('campaign_fixture_manager'));
wp_set_current_user($manager);
peracrm_with_target_blog(static function () {
    global $wpdb;
    $blog = get_current_blog_id();
    $mailbox = 'lifecycle-sql@example.test';
    $id = peracrm_campaigns_save_subscriber($mailbox);
    persistence_expect(is_int($id),'lifecycle staff create');
    $store = new PeraCRM_CampaignRecipientPersistence('fixture-lifecycle',$blog);
    $retained = new PeraCRM_CampaignRecipientPersistence('fixture-lifecycle-retained',$blog);
    persistence_expect($store->ensureIdentity($id,$mailbox) === 1 && $retained->ensureIdentity($id,$mailbox) === 1,'current and retained deployment identities');
    $binding = ['blog_id'=>$blog,'subscriber_id'=>$id,'mailbox'=>$mailbox,'token_epoch'=>1,
        'attempt_key'=>hash('sha256','lifecycle-attempt'),'snapshot_hash'=>hash('sha256','lifecycle-snapshot')];
    $digest = hash('sha256','lifecycle-digest');
    $store->registerToken($digest,'unsubscribe','k1',1800000000,0,$binding);
    $identities = peracrm_table('peracrm_campaign_recipient_identities');
    $subscribers = peracrm_table('peracrm_subscribers');
    $events = peracrm_table('peracrm_subscription_events');
    $capture = static function () use ($wpdb) {
        return [
            $wpdb->get_results('SELECT * FROM '.peracrm_table('peracrm_campaign_submission_attempts').' ORDER BY deployment,attempt_key',ARRAY_A),
            $wpdb->get_results('SELECT * FROM '.peracrm_table('peracrm_campaign_content_snapshots').' ORDER BY id',ARRAY_A),
        ];
    };
    $history = $capture();
    persistence_expect(!is_wp_error(peracrm_campaigns_save_subscriber($mailbox,['preferred_language'=>'tr','preferences'=>['news']]))
        && peracrm_campaigns_record_consent($mailbox,'pending',['source'=>'lifecycle']) === true
        && peracrm_campaigns_unlink_subscriber_client($mailbox) === true
        && $store->locate($digest) !== null,'profile topics language consent and unlink preserve withdrawal');
    persistence_expect(is_wp_error(peracrm_campaigns_save_subscriber($mailbox,['normalized_email'=>'changed@example.test']))
        && $store->locate($digest) !== null,'mailbox edit is unsupported and cannot revoke or mutate consent');
    // Existing import skips a current profile without replacing its identity.
    $stream = fopen('php://temp','w+');
    fwrite($stream,"email,state,source,source_ref,evidence_ref,policy_version,occurred_at\n{$mailbox},subscribed,archive,src,consent,v1,2020-01-01 00:00:00\n");
    rewind($stream);
    $batch = peracrm_subscribers_preview($stream,array_flip(['email','state','source','source_ref','evidence_ref','policy_version','occurred_at']),true,true);
    fclose($stream);
    peracrm_subscribers_apply_batch($batch);
    persistence_expect($store->locate($digest) !== null,'import of existing identity does not rotate withdrawal epoch');
    peracrm_campaigns_add_suppression($mailbox,'complaint',['source'=>'lifecycle']);
    peracrm_campaigns_withdraw($mailbox,['source'=>'lifecycle']);
    persistence_expect($store->locate($digest) !== null,'cumulative suppression and withdrawal preserve withdrawal link');
    $read = static fn() => $wpdb->get_row($wpdb->prepare("SELECT * FROM {$subscribers} WHERE id=%d",$id),ARRAY_A);
    $preserved = $read();
    $eventCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$events}");
    foreach (['identity','delete','commit'] as $failure) {
        $filter = static function ($sql) use ($failure) {
            $matches = ($failure === 'identity' && str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_campaign_recipient_identities'))
                || ($failure === 'delete' && str_starts_with($sql,'DELETE') && str_contains($sql,'peracrm_subscribers'))
                || ($failure === 'commit' && $sql === 'COMMIT');
            return $matches ? 'INVALID_LIFECYCLE_FIXTURE_SQL' : $sql;
        };
        $quiet = $wpdb->suppress_errors(true);
        add_filter('query',$filter);
        $result = peracrm_campaigns_delete_subscriber($mailbox);
        remove_filter('query',$filter);
        $wpdb->suppress_errors($quiet);
        persistence_expect(is_wp_error($result) && $read() === $preserved && $store->locate($digest) !== null
            && (int)$wpdb->get_var("SELECT COUNT(*) FROM {$events}") === $eventCount,'atomic rollback on '.$failure.' failure');
    }
    // Reject top-level API nesting while leaving caller work and ownership intact.
    $wpdb->query('START TRANSACTION');
    $wpdb->update($subscribers,['preferred_language'=>'fr'],['id'=>$id]);
    persistence_expect(is_wp_error(peracrm_campaigns_delete_subscriber($mailbox)) && $read()['preferred_language'] === 'fr',
        'nested service preserves outer uncommitted work');
    $wpdb->query('ROLLBACK');
    persistence_expect($read() === $preserved && $store->locate($digest) !== null,'caller rollback remains effective');
    $wpdb->query('SET autocommit=0');
    persistence_expect(is_wp_error(peracrm_campaigns_delete_subscriber($mailbox)),'autocommit-disabled caller rejected');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit=1');
    // A participant never commits: caller can roll back identity and profile together.
    $wpdb->query('START TRANSACTION');
    peracrm_campaigns_repository_lock_mailbox($mailbox);
    peracrm_campaigns_repository_replace_identity($id,$mailbox,false);
    $wpdb->delete($subscribers,['id'=>$id]);
    $wpdb->query('ROLLBACK');
    persistence_expect($read() === $preserved && $store->locate($digest) !== null,'participant rollback restores subscriber and epoch together');
    $store->changeIdentity($id,1,$mailbox,true);
    persistence_reject(fn() => $store->registerToken(hash('sha256','stale-epoch'),'unsubscribe','k1',1800000000,0,$binding));
    persistence_reject(fn() => $store->changeIdentity($id,1,$mailbox,true));
    $binding['token_epoch'] = 2;
    $currentDigest = hash('sha256','lifecycle-current');
    $store->registerToken($currentDigest,'unsubscribe','k1',1800000000,0,$binding);
    persistence_expect(peracrm_campaigns_delete_subscriber($mailbox) === true && !$read()
        && $store->locate($currentDigest) === null,'committed deletion revokes old tokens');
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$identities} WHERE subscriber_id=%d AND blog_id=%d ORDER BY deployment",$id,$blog),ARRAY_A);
    persistence_expect(count($rows) === 2 && (int)$rows[0]['active'] === 0 && (int)$rows[1]['active'] === 0,
        'deletion retains inactive tombstones across deployments');
    persistence_reject(fn() => $store->ensureIdentity($id,$mailbox));
    persistence_reject(fn() => $store->changeIdentity($id,3,$mailbox,true));
    persistence_reject(fn() => $store->registerToken(hash('sha256','deleted-token'),'unsubscribe','k1',1800000000,0,$binding));
    $newId = peracrm_campaigns_save_subscriber($mailbox);
    persistence_expect(is_int($newId) && $newId !== $id && $store->ensureIdentity($newId,$mailbox) === 1
        && $store->locate($digest) === null && $store->locate($currentDigest) === null,'returning to old mailbox creates separate identity without old access');
    $newRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$subscribers} WHERE id=%d",$newId),ARRAY_A);
    $suppression = peracrm_campaigns_repository_identity($mailbox);
    persistence_expect($newRow['subscription_state'] === 'unknown' && empty($newRow['confirmed_at'])
        && (int)$suppression['unsubscribe'] === 1 && (int)$suppression['complaint'] === 1,
        'recreation does not copy consent or clear cumulative suppression');
    // Simulate an allocator restoring/reusing a deleted ID through the same internal
    // create participant. Retained epochs must advance, never reset to one.
    peracrm_campaigns_delete_subscriber($mailbox);
    peracrm_campaigns_repository_mutate($mailbox,static function () use ($id,$mailbox) {
        peracrm_test_persistence_subscriber($id,$mailbox);
        peracrm_campaigns_repository_replace_identity($id,$mailbox,true);
    });
    persistence_expect($store->ensureIdentity($id,$mailbox) === 4 && $retained->ensureIdentity($id,$mailbox) === 3
        && $store->locate($digest) === null && $store->locate($currentDigest) === null,'reused subscriber ID cannot restore an earlier epoch');
    persistence_expect($capture() === $history,'lifecycle retains frozen captures and historical attempt bytes');
    persistence_expect((int)$wpdb->get_var("SELECT COUNT(*) FROM {$events}") > $eventCount,'consent and deletion audit history retained');
    echo "PASS subscriber lifecycle SQL: target blog ".$blog."\n";
});
