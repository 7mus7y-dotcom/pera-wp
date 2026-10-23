<?php
require_once __DIR__.'/recipient-preferences-fixture.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    $service=recipient_preferences_service(); $store=withdrawal_store();
    $seed=withdrawal_seed(22001,'preferences-sql','preferences');
    $wpdb->update(peracrm_table('peracrm_subscribers'),['preferences_json'=>'["custom","news"]','subscription_state'=>'subscribed','source'=>'synthetic_consent','source_ref'=>'fixture','evidence_ref'=>'fixture-evidence','policy_version'=>'fixture-v1','recorded_at'=>'2020-01-01 00:00:00','confirmed_at'=>'2020-01-02 00:00:00'],['id'=>22001]);
    $wpdb->update(peracrm_table('peracrm_email_suppressions'),['hard_bounce'=>1,'complaint'=>1,'administrative'=>1],['normalized_email'=>$seed['binding']['mailbox']]);
    $original=peracrm_campaigns_repository_identity($seed['binding']['mailbox']);
    $expired=withdrawal_seed(22002,'preferences-expired','preferences',90000);
    $wrong=withdrawal_seed(22003,'preferences-wrong','unsubscribe');
    $revoked=withdrawal_seed(22004,'preferences-revoked','preferences'); $store->revokeToken(hash('sha256',$revoked['token']));
    $changed=withdrawal_seed(22005,'preferences-epoch','preferences'); $store->changeIdentity(22005,1,$changed['binding']['mailbox'],true);
    $deleted=withdrawal_seed(22006,'preferences-deleted','preferences');
    wp_set_current_user((int)get_option('campaign_fixture_manager'));
    withdrawal_expect(!is_wp_error(peracrm_campaigns_delete_subscriber($deleted['binding']['mailbox'])),'delete preference identity');
    // Recreate the same mailbox with a different subscriber: old bearer must not access it.
    peracrm_test_persistence_subscriber(22007,$deleted['binding']['mailbox']);
    $before=withdrawal_snapshot();
    foreach (['GET','HEAD'] as $method) {
        $response=$service->request($method,$seed['token'],'',[],'','','');
        withdrawal_expect($response[0]===200 && array_keys($response[2])===['language','topics'],'read projection only '.$method);
    }
    foreach ([$expired,$wrong,$revoked,$changed,$deleted,['token'=>'malformed']] as $bad) {
        withdrawal_expect($service->request('GET',$bad['token'],'',[],'','','')[0]===400 && recipient_preferences_post($bad)[0]===400,'invalid purpose expiry revoked epoch replacement denied');
    }
    foreach ([new PeraCRM_CampaignRecipientPreferences($store,withdrawal_keys(),'wrong-deployment'),
        new PeraCRM_CampaignRecipientPreferences($store,['k1'=>str_repeat('wrong',10)],'fixture-withdrawal'),
        new PeraCRM_CampaignRecipientPreferences(new PeraCRM_CampaignRecipientPersistence('fixture-withdrawal',get_current_blog_id()+1),withdrawal_keys(),'fixture-withdrawal')] as $wrongService) {
        withdrawal_expect($wrongService->request('GET',$seed['token'],'',[],'','','')[0]===400,'wrong deployment signing key or blog denied');
    }
    $body=['token'=>$seed['token'],'proof'=>$service->proof($seed['token'],time()),'language'=>'en','topics'=>[]];
    foreach (['','null','http://evil.test'] as $origin) {
        withdrawal_expect($service->request('POST',$seed['token'],'application/x-www-form-urlencoded',$body,$origin,'http://fixture.test','cross-site')[0]===400,'cross-site rejected');
    }
    withdrawal_expect(withdrawal_snapshot()===$before,'GET HEAD and rejected edits SQL unchanged including mutex rows');
    foreach (['subscriber','event','commit'] as $fault) {
        $mutating=false;
        $filter=static function ($sql) use ($fault,&$mutating) {
            $update=str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_subscribers');
            if ($update) { $mutating=true; }
            return (($fault==='subscriber' && $update)
                || ($fault==='event' && str_starts_with($sql,'INSERT') && str_contains($sql,'peracrm_subscription_events'))
                || ($fault==='commit' && $sql==='COMMIT' && $mutating)) ? 'INVALID_PREFERENCE_FIXTURE_SQL' : $sql;
        };
        add_filter('query',$filter); $response=recipient_preferences_post($seed); remove_filter('query',$filter);
        withdrawal_expect($response[0]===400 && withdrawal_snapshot()===$before,'edit rollback '.$fault);
    }
    $wpdb->query('START TRANSACTION'); withdrawal_expect(recipient_preferences_post($seed)[0]===400,'nested transaction denied'); $wpdb->query('ROLLBACK');
    withdrawal_expect(recipient_preferences_post($seed)[0]===200,'valid preferences commit');
    $current=peracrm_campaigns_repository_identity($seed['binding']['mailbox']);
    foreach (['subscription_state','withdrawn_at','unsubscribe','hard_bounce','complaint','administrative','source','source_ref','evidence_ref','policy_version','recorded_at','confirmed_at','client_id'] as $field) {
        withdrawal_expect($current[$field]===$original[$field],'valid edit preserves permission/evidence '.$field);
    }
    withdrawal_expect($current['preferences_json']==='["custom","news"]','language-only edit preserves unrendered topics');
    $after=withdrawal_snapshot(); withdrawal_expect(recipient_preferences_post($seed)[0]===200 && withdrawal_snapshot()===$after,'duplicate edits no events or version bump');
    $withdraw=withdrawal_seed(22001,'preferences-sql','unsubscribe');
    withdrawal_expect(withdrawal_service()->request('POST',true,$withdraw['token'],'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0]===200,'existing withdrawal after edit');
    $row=peracrm_campaigns_repository_identity($seed['binding']['mailbox']);
    withdrawal_expect(recipient_preferences_post($seed,['language'=>'en','topics'=>[]])[0]===200,'withdrawn profile remains editable');
    $edited=peracrm_campaigns_repository_identity($seed['binding']['mailbox']);
    foreach (['subscription_state','withdrawn_at','unsubscribe','hard_bounce','complaint','administrative','source','source_ref','evidence_ref','policy_version','recorded_at','confirmed_at','client_id'] as $field) {
        withdrawal_expect(($row[$field]??null)===($edited[$field]??null),'edit preserves '.$field);
    }
    withdrawal_expect($edited['preferences_json']==='["custom"]','clearing rendered topics preserves custom keys');
    withdrawal_expect($edited['subscription_state']==='unsubscribed' && (int)$edited['unsubscribe']===1,'edits never reactivate');
    $independent=withdrawal_seed(22002,'preferences-expired','unsubscribe',90000);
    withdrawal_expect(!$service->withdrawalAvailable($expired['token']) && $service->withdrawalAvailable($independent['token']),'expired preference cannot mint withdrawal; independent authority works');
    $http=['target'=>home_url('/'),'expired'=>$expired['token'],'independent'=>$independent['token'],'revoked'=>$revoked['token'],'wrong'=>$wrong['token']];
    foreach (['desktop','mobile'] as $i=>$name) { $http[$name]=withdrawal_seed(22100+$i,'preferences-http-'.$name,'preferences')['token']; }
    file_put_contents('/var/www/html/recipient-preferences-fixture.json',wp_json_encode($http));
});
