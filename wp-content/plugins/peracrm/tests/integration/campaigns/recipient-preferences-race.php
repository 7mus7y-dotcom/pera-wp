<?php
require_once __DIR__.'/recipient-preferences-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
$scenarios=['edit-edit','revoke-edit','edit-revoke','identity-edit','edit-identity','delete-edit','edit-delete','withdraw-edit','edit-withdraw'];
if (!in_array($scenario,$scenarios,true)) { throw new RuntimeException('Unknown preference race.'); }
wp_set_current_user(peracrm_with_target_blog(static fn()=>(int)get_option('campaign_fixture_manager')));
peracrm_with_target_blog(static function () use ($mode,$scenario,$scenarios) {
    global $wpdb;
    if ($mode==='prepare') {
        $id=22200+array_search($scenario,$scenarios,true); $name='preferences-race-'.$scenario;
        $seed=withdrawal_seed($id,$name,'preferences');
        $seed['withdrawal']=withdrawal_seed($id,$name,'unsubscribe')['token'];
        update_option('preferences_race_seed',$seed,false); return;
    }
    $seed=get_option('preferences_race_seed'); $binding=$seed['binding']; [$first,$second]=explode('-',$scenario);
    if ($mode==='verify') {
        $winner=json_decode(file_get_contents('/tmp/peracrm-preferences-winner'),true);
        $loser=json_decode(file_get_contents('/tmp/peracrm-preferences-loser'),true);
        $denied=in_array($first,['revoke','identity','delete'],true);
        withdrawal_expect($winner['connection']!==$loser['connection'] && $loser['duration']>0.5 && $winner['ok']===true
            && $loser['ok']===!$denied,'blocking edit identity race '.$scenario);
        $row=peracrm_campaigns_repository_identity($binding['mailbox']);
        if ($row) {
            withdrawal_expect($row['preferred_language']===($denied?'':'tr'),'serialized preference outcome '.$scenario);
            if (str_contains($scenario,'withdraw')) { withdrawal_expect($row['subscription_state']==='unsubscribed' && (int)$row['unsubscribe']===1,'withdrawal wins regardless of edit order'); }
        } else { withdrawal_expect(str_contains($scenario,'delete'),'only deletion removes subscriber'); }
        return;
    }
    if (!in_array($mode,['winner','loser'],true)) { throw new RuntimeException('Unknown mode.'); }
    $operation=$mode==='winner'?$first:$second;
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $started=microtime(true);
    if ($mode==='winner') {
        $armed=false;
        add_filter('query',static function ($sql) use (&$armed) {
            if ((str_starts_with($sql,'INSERT INTO') && str_contains($sql,'peracrm_email_suppressions'))
                || (str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_campaign_recipient_tokens'))) { $armed=true; }
            if ($armed && $sql==='COMMIT') { file_put_contents('/tmp/peracrm-preferences-held','locked'); usleep(1500000); $armed=false; }
            return $sql;
        });
    }
    $store=withdrawal_store();
    if ($operation==='edit') { $ok=recipient_preferences_post($seed)[0]===200; }
    elseif ($operation==='withdraw') { $ok=withdrawal_service()->request('POST',true,$seed['withdrawal'],'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0]===200; }
    elseif ($operation==='revoke') { $store->revokeToken(hash('sha256',$seed['token'])); $ok=true; }
    elseif ($operation==='identity') { $ok=$store->changeIdentity($binding['subscriber_id'],1,$binding['mailbox'],true)===2; }
    else { $ok=!is_wp_error(peracrm_campaigns_delete_subscriber($binding['mailbox'])); }
    file_put_contents('/tmp/peracrm-preferences-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$started,'ok'=>$ok]));
});
