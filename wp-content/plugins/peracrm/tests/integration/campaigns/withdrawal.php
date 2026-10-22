<?php
require_once __DIR__.'/withdrawal-fixture.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    $service = withdrawal_service(); $store = withdrawal_store();
    $seed = withdrawal_seed(21001,'withdrawal-sql'); $token = $seed['token'];
    $pref = withdrawal_seed(21002,'withdrawal-pref','preferences');
    $expired = withdrawal_seed(21003,'withdrawal-expired','preferences',90000);
    $revoked = withdrawal_seed(21004,'withdrawal-revoked');
    $store->revokeToken(hash('sha256',$revoked['token']));
    $old = withdrawal_seed(21005,'withdrawal-deleted');
    $store->changeIdentity(21005,1,$old['binding']['mailbox'],false);
    peracrm_campaigns_repository_mutate($seed['binding']['mailbox'],static function () use ($wpdb,$seed) {
        peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_email_suppressions'),
            ['hard_bounce'=>1,'complaint'=>1,'administrative'=>1],['normalized_email'=>$seed['binding']['mailbox']]));
    });
    $before = withdrawal_snapshot();
    foreach (['GET','HEAD'] as $method) {
        withdrawal_expect($service->request($method,false,$token,'',[]) === [200,'confirm'],'scanner '.$method.' offers confirmation');
        withdrawal_expect($service->request($method,true,$token,'',[])[0] === 405,'machine '.$method.' rejected');
    }
    foreach (['',str_repeat('a',257),$pref['token'],$expired['token'],$revoked['token'],$old['token'],substr($token,0,-1).'z'] as $bad) {
        withdrawal_expect($service->request('POST',true,$bad,'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0] === 400,'invalid purpose format expiry revocation identity denied');
    }
    foreach (['','List-Unsubscribe=One-Click&List-Unsubscribe=One-Click','List_Unsubscribe=One-Click','List-Unsubscribe=one-click'] as $body) {
        withdrawal_expect($service->request('POST',true,$token,'application/x-www-form-urlencoded',$body)[0] === 400,'machine body strict');
    }
    withdrawal_expect($service->request('POST',false,$token,'application/x-www-form-urlencoded',[])[0] === 400,'human explicit confirmation required');
    withdrawal_expect($service->request('POST',true,$token,'application/json','List-Unsubscribe=One-Click')[0] === 400,'unsupported content type denied');
    withdrawal_expect(withdrawal_snapshot() === $before,'scanner and invalid requests change no SQL rows');
    $wrong = new PeraCRM_CampaignRecipientWithdrawal($store,withdrawal_keys(),'fixture-other');
    withdrawal_expect($wrong->request('GET',false,$token,'',[])[0] === 400
        && $wrong->request('POST',true,$token,'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0] === 400,'wrong deployment denied');
    $wrong = new PeraCRM_CampaignRecipientWithdrawal(new PeraCRM_CampaignRecipientPersistence('fixture-withdrawal',get_current_blog_id()+1),withdrawal_keys(),'fixture-withdrawal');
    withdrawal_expect($wrong->request('POST',true,$token,'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0] === 400,'wrong blog denied');
    $wrong = new PeraCRM_CampaignRecipientWithdrawal($store,['k1'=>str_repeat('wrong',10)],'fixture-withdrawal');
    withdrawal_expect($wrong->request('POST',true,$token,'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0] === 400,'wrong signing key denied');
    foreach (['suppression','subscriber','event','commit'] as $fault) {
        $mutating = false;
        $filter = static function ($sql) use ($fault, &$mutating) {
            if (str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_email_suppressions')) { $mutating = true; }
            $match = ($fault === 'suppression' && str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_email_suppressions'))
                || ($fault === 'subscriber' && str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_subscribers'))
                || ($fault === 'event' && str_starts_with($sql,'INSERT') && str_contains($sql,'peracrm_subscription_events'))
                || ($fault === 'commit' && $sql === 'COMMIT' && $mutating);
            return $match ? 'INVALID_WITHDRAWAL_FIXTURE_SQL' : $sql;
        };
        add_filter('query',$filter);
        $response = $service->request('POST',true,$token,'application/x-www-form-urlencoded','List-Unsubscribe=One-Click');
        remove_filter('query',$filter);
        withdrawal_expect($response[0] === 400 && withdrawal_snapshot() === $before,'atomic rollback '.$fault);
    }
    $wpdb->query('START TRANSACTION');
    withdrawal_expect($service->request('POST',true,$token,'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0] === 400,'caller transaction denied');
    $wpdb->query('ROLLBACK');
    withdrawal_expect($service->request('POST',true,$token,'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0] === 200,'machine POST commits');
    $after = withdrawal_snapshot();
    withdrawal_expect($service->request('POST',false,$token,'application/x-www-form-urlencoded',['confirm'=>'withdraw','topics'=>['news'],'subscription_state'=>'subscribed'])[0] === 200
        && withdrawal_snapshot() === $after,'repeated valid human withdrawal idempotent and no consent or topics authority');
    $row = peracrm_campaigns_repository_identity($seed['binding']['mailbox']);
    withdrawal_expect($row['subscription_state'] === 'unsubscribed' && (int)$row['unsubscribe'] === 1 && $row['preferences_json'] === '[]'
        && (int)$row['hard_bounce'] === 1 && (int)$row['complaint'] === 1 && (int)$row['administrative'] === 1,'global state and cumulative suppressions persisted together');
    // Same identity and issue time as the expired preference link. This proves
    // independent withdrawal remains valid, not that preference-page navigation exists.
    $independent = withdrawal_seed(21003,'withdrawal-expired','unsubscribe',90000);
    withdrawal_expect($service->request('POST',true,$independent['token'],'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0] === 200,
        'independent withdrawal remains usable after preference expiry');
    $http = ['target'=>home_url('/')];
    foreach (['desktop','mobile','machine','revoked'] as $i=>$name) {
        $entry = withdrawal_seed(21100+$i,'withdrawal-http-'.$name);
        $http[$name] = $entry['token'];
        if ($name === 'revoked') { $store->revokeToken(hash('sha256',$entry['token'])); }
    }
    $http['preferences'] = $pref['token'];
    file_put_contents('/var/www/html/withdrawal-fixture.json',wp_json_encode($http));
});
