<?php
require_once __DIR__.'/withdrawal-fixture.php';
$mode = $args[0] ?? ''; $scenario = $args[1] ?? '';
if (!in_array($scenario,['withdraw-withdraw','revoke-withdraw','withdraw-revoke','identity-withdraw','withdraw-identity','delete-withdraw','withdraw-delete'],true)) { throw new RuntimeException('Unknown withdrawal race.'); }
wp_set_current_user(peracrm_with_target_blog(static fn() => (int)get_option('campaign_fixture_manager')));
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    if ($mode === 'prepare') {
        $index = array_search($scenario,['withdraw-withdraw','revoke-withdraw','withdraw-revoke','identity-withdraw','withdraw-identity','delete-withdraw','withdraw-delete'],true);
        update_option('withdrawal_race_seed',withdrawal_seed(21200+$index,'withdrawal-race-'.$scenario),false); return;
    }
    $seed = get_option('withdrawal_race_seed'); $binding = $seed['binding'];
    [$first,$second] = explode('-',$scenario);
    if ($mode === 'verify') {
        $winner = json_decode(file_get_contents('/tmp/peracrm-withdrawal-winner'),true);
        $loser = json_decode(file_get_contents('/tmp/peracrm-withdrawal-loser'),true);
        $expected = $first === 'withdraw' || $second !== 'withdraw';
        withdrawal_expect($winner['connection'] !== $loser['connection'] && $loser['duration'] > 0.5
            && $winner['ok'] === true && $loser['ok'] === $expected,'independent blocking connections '.$scenario);
        $suppression = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email=%s',$binding['mailbox']),ARRAY_A);
        withdrawal_expect((int)($suppression['unsubscribe'] ?? 0) === ($first === 'withdraw' ? 1 : 0),'serialized suppression outcome '.$scenario);
        $row = peracrm_campaigns_repository_identity($binding['mailbox']);
        if ($first === 'withdraw' && $row) { withdrawal_expect($row['subscription_state'] === 'unsubscribed','identity operation cannot undo withdrawal'); }
        return;
    }
    $operation = $mode === 'winner' ? $first : $second;
    if (!in_array($mode,['winner','loser'],true)) { throw new RuntimeException('Unknown mode.'); }
    $connection = (int)$wpdb->get_var('SELECT CONNECTION_ID()'); $started = microtime(true);
    if ($mode === 'winner') {
        $armed = false;
        add_filter('query',static function ($sql) use (&$armed,$operation) {
            if ((str_starts_with($sql,'INSERT INTO') && str_contains($sql,'peracrm_email_suppressions'))
                || ($operation === 'revoke' && str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_campaign_recipient_tokens'))) { $armed = true; }
            if ($armed && $sql === 'COMMIT') { file_put_contents('/tmp/peracrm-withdrawal-held','locked'); usleep(1500000); $armed = false; }
            return $sql;
        });
    }
    $store = withdrawal_store();
    if ($operation === 'withdraw') {
        $ok = withdrawal_service()->request('POST',true,$seed['token'],'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0] === 200;
    } elseif ($operation === 'revoke') { $store->revokeToken(hash('sha256',$seed['token'])); $ok = true; }
    elseif ($operation === 'identity') { $ok = $store->changeIdentity($binding['subscriber_id'],1,$binding['mailbox'],true) === 2; }
    else { $ok = !is_wp_error(peracrm_campaigns_delete_subscriber($binding['mailbox'])); }
    file_put_contents('/tmp/peracrm-withdrawal-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$started,'ok'=>$ok]));
});
