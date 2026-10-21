<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/recipient-persistence.php';
$mode = $args[0] ?? ''; $scenario = $args[1] ?? '';
if (!in_array($scenario,['delete-register','register-delete','edit-register','register-edit','delete-create','scope-delete'],true)) {
    throw new RuntimeException('Unknown lifecycle race.');
}
$manager = peracrm_with_target_blog(static fn() => (int)get_option('campaign_fixture_manager'));
wp_set_current_user($manager);
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    $mailbox = 'lifecycle-'.$scenario.'@example.test';
    $blog = get_current_blog_id();
    $store = new PeraCRM_CampaignRecipientPersistence('fixture-lifecycle-race',$blog);
    $digest = hash('sha256',$scenario);
    if ($mode === 'prepare') {
        $id = peracrm_campaigns_save_subscriber($mailbox);
        if (!is_int($id) || $store->ensureIdentity($id,$mailbox) !== 1) { throw new RuntimeException('Lifecycle prepare failed.'); }
        update_option('fixture_lifecycle_race_id',$id,false);
        return;
    }
    $id = (int)get_option('fixture_lifecycle_race_id');
    $binding = ['blog_id'=>$blog,'subscriber_id'=>$id,'mailbox'=>$mailbox,'token_epoch'=>1,
        'attempt_key'=>hash('sha256','race-lifecycle-attempt'),'snapshot_hash'=>hash('sha256','race-lifecycle-snapshot')];
    if ($mode === 'verify') {
        $winner = json_decode(file_get_contents('/tmp/peracrm-lifecycle-winner'),true);
        $loser = json_decode(file_get_contents('/tmp/peracrm-lifecycle-loser'),true);
        if ($winner['connection'] === $loser['connection'] || $loser['duration'] < 0.5 || $winner['result'] !== 'ok'
            || $loser['result'] !== ($scenario === 'delete-register' ? 'rejected' : 'ok')) {
            throw new RuntimeException('Lifecycle independent connection blocking/results failed: '.$scenario);
        }
        $current = peracrm_campaigns_repository_identity($mailbox);
        $active = $store->locate($digest);
        if (str_contains($scenario,'edit')) {
            if (!$active || (int)$current['id'] !== $id || $current['preferred_language'] !== 'tr') {
                throw new RuntimeException('Profile edit unnecessarily revoked token.');
            }
        } elseif ($active !== null || ($scenario !== 'delete-create' && $current)) {
            throw new RuntimeException('Deletion left old access active.');
        }
        if ($scenario === 'delete-create' && (!$current || (int)$current['id'] === $id
            || $store->ensureIdentity((int)$current['id'],$mailbox) !== 1)) {
            throw new RuntimeException('Recreation inherited deleted identity.');
        }
        if ($scenario === 'scope-delete') {
            $activeScope = $wpdb->get_var($wpdb->prepare('SELECT active FROM '.peracrm_table('peracrm_campaign_recipient_identities')
                .' WHERE deployment=%s AND blog_id=%d AND subscriber_id=%d','fixture-lifecycle-new-scope',$blog,$id));
            if ($activeScope === null || (int)$activeScope !== 0) { throw new RuntimeException('Outer snapshot omitted newly committed deployment identity.'); }
        }
        echo "PASS subscriber lifecycle race: ".$scenario."; independent blocking SQL connections\n";
        return;
    }
    if (!in_array($mode,['winner','loser'],true)) { throw new RuntimeException('Unknown lifecycle mode.'); }
    $connection = (int)$wpdb->get_var('SELECT CONNECTION_ID()'); $started = microtime(true);
    if ($mode === 'winner') {
        add_filter('query',static function ($sql) {
            if ($sql === 'COMMIT') {
                file_put_contents('/tmp/peracrm-lifecycle-held','locked');
                usleep(1500000);
            }
            return $sql;
        });
    }
    [$first,$second] = explode('-',$scenario);
    $operation = $mode === 'winner' ? $first : $second;
    $result = 'ok';
    try {
        if ($operation === 'register') { $store->registerToken($digest,'unsubscribe','k1',1800000000,0,$binding); }
        elseif ($operation === 'scope') {
            (new PeraCRM_CampaignRecipientPersistence('fixture-lifecycle-new-scope',$blog))->ensureIdentity($id,$mailbox);
        }
        elseif ($scenario === 'scope-delete') {
            $wpdb->query('START TRANSACTION');
            // Establish an old snapshot while the new scope is still uncommitted.
            $wpdb->get_var('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_recipient_identities'));
            peracrm_campaigns_repository_lock_mailbox($mailbox);
            peracrm_campaigns_repository_replace_identity($id,$mailbox,false);
            peracrm_campaigns_db_require($wpdb->delete(peracrm_table('peracrm_subscribers'),['id'=>$id]));
            peracrm_campaigns_db_require($wpdb->query('COMMIT'));
        }
        else {
            $value = match ($operation) {
                'delete' => peracrm_campaigns_delete_subscriber($mailbox),
                'edit' => peracrm_campaigns_save_subscriber($mailbox,['preferred_language'=>'tr']),
                'create' => peracrm_campaigns_save_subscriber($mailbox),
            };
            if (is_wp_error($value)) { throw new RuntimeException('Lifecycle mutation failed.'); }
        }
    } catch (Throwable $error) {
        if ($operation !== 'register' || $error->getMessage() !== 'recipient_persistence_unavailable') { throw $error; }
        $result = 'rejected';
    }
    file_put_contents('/tmp/peracrm-lifecycle-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$started,'result'=>$result]));
});
