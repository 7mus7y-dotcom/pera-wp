<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/recipient-persistence.php';
$mode = $args[0] ?? ''; $scenario = $args[1] ?? '';
if (!in_array($scenario,['identity','identical','conflict','token-identical','token-conflict','revoke'],true)) { throw new RuntimeException('Unknown persistence race.'); }
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    $blog = get_current_blog_id();
    $store = new PeraCRM_CampaignRecipientPersistence('fixture-race-'.$scenario,$blog);
    $binding = ['blog_id'=>$blog,'subscriber_id'=>18001,'mailbox'=>'race@example.org','token_epoch'=>1,
        'attempt_key'=>hash('sha256','race-attempt'),'snapshot_hash'=>hash('sha256','race-snapshot')];
    $digest = hash('sha256','race-token');
    $output = new PeraCRM_CampaignRecipientOutput('<p>Synthetic stable body</p>','Synthetic stable body',$binding['snapshot_hash'],hash('sha256',peracrm_campaign_recipient_binding($binding)));
    $fields = ['version'=>1,'scope'=>['provider'=>'ses_v2','account_id'=>'123456789012','region'=>'eu-west-1','configuration_set'=>'fixture','configuration_version'=>1],
        'blog_id'=>$blog,'campaign_id'=>31,'job_id'=>41,'revision_id'=>51,'snapshot_id'=>61,'recipient_id'=>71,
        'snapshot_hash'=>$binding['snapshot_hash'],'delivery_key'=>hash('sha256','race-delivery'),'attempt_key'=>$binding['attempt_key'],
        'personalization_version'=>1,'mailbox'=>$binding['mailbox'],'subject'=>'Synthetic','sender_name'=>'Fixture',
        'sender_email'=>'sender@example.org','reply_to'=>'reply@example.org','html'=>$output->fields()['html'],'text'=>$output->fields()['text'],'timeout_seconds'=>20];
    if ($mode === 'prepare') {
        if ($scenario !== 'identity') { $store->ensureIdentity(18001,$binding['mailbox']); }
        if ($scenario === 'revoke') {
            $store->ensureIdentity(18001,$binding['mailbox']);
            $store->registerToken($digest,'unsubscribe','k1',1800000000,0,$binding);
        }
        return;
    }
    if ($mode === 'verify') {
        $winner = json_decode(file_get_contents('/tmp/peracrm-persistence-winner'),true);
        $loser = json_decode(file_get_contents('/tmp/peracrm-persistence-loser'),true);
        if ($winner['connection'] === $loser['connection'] || $loser['duration'] < 0.5 || $winner['result'] !== 'ok'
            || $loser['result'] !== (in_array($scenario,['conflict','token-conflict'],true) ? 'rejected' : 'ok')) {
            throw new RuntimeException('Persistence independent blocking connections/results failed.');
        }
        $suffix = $scenario === 'identity' ? 'recipient_identities' : (in_array($scenario,['identical','conflict'],true) ? 'submission_attempts' : 'recipient_tokens');
        $count = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_'.$suffix).' WHERE deployment=%s','fixture-race-'.$scenario));
        if ($count !== 1) { throw new RuntimeException('Persistence race uniqueness failed.'); }
        if ($scenario === 'revoke' && $store->locate($digest) !== null) { throw new RuntimeException('Concurrent registration revived revoked token.'); }
        if ($scenario === 'conflict' && $store->attempt($binding['attempt_key'])['scope']['region'] !== 'eu-west-1') { throw new RuntimeException('Conflict changed attempt.'); }
        echo 'PASS persistence race: '.$scenario.'; independent SQL connections, blocking and immutable binding'."\n";
        return;
    }
    $connection = (int)$wpdb->get_var('SELECT CONNECTION_ID()'); $started = microtime(true); $result = 'ok';
    if ($mode === 'winner') {
        $held = false;
        add_filter('query',static function ($sql) use (&$held,$scenario) {
            $match = $scenario === 'revoke' ? str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_campaign_recipient_tokens')
                : str_starts_with($sql,'INSERT INTO') && str_contains($sql,'peracrm_campaign_'.($scenario === 'identity' ? 'recipient_identities' : (str_starts_with($scenario,'token-') ? 'recipient_tokens' : 'submission_attempts')));
            if (!$held && $match) {
                // Pause after the lock is acquired by the preceding SELECT, or
                // (for new identities) before COMMIT after the INSERT below.
                $held = true;
            }
            if ($held && $sql === 'COMMIT') { file_put_contents('/tmp/peracrm-persistence-held','locked'); usleep(1500000); }
            return $sql;
        });
    } elseif ($mode !== 'loser') { throw new RuntimeException('Unknown persistence mode.'); }
    try {
        if ($scenario === 'revoke' && $mode === 'winner') { $store->revokeToken($digest); }
        else {
            $store->ensureIdentity(18001,$binding['mailbox']);
            if ($scenario === 'identity') { /* creation only */ }
            elseif (in_array($scenario,['token-identical','token-conflict','revoke'],true)) {
                $keyId = $scenario === 'token-conflict' && $mode === 'loser' ? 'k2' : 'k1';
                $store->registerToken($digest,'unsubscribe',$keyId,1800000000,0,$binding);
            } else {
                if ($scenario === 'conflict' && $mode === 'loser') { $fields['scope']['region'] = 'eu-west-2'; }
                $store->bindAttempt(new PeraCRM_CampaignSubmissionV1($fields),$binding,$output);
            }
        }
    } catch (Throwable $e) {
        if ($e->getMessage() !== 'recipient_persistence_unavailable') { throw $e; }
        $result = 'rejected';
    }
    file_put_contents('/tmp/peracrm-persistence-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$started,'result'=>$result]));
});
