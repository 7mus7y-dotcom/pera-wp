<?php
require_once __DIR__.'/prepared-worker-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/assessment.php';

worker_fixture_target(static function () {
    global $wpdb;
    $actor=get_current_user_id();
    $admin=(int)get_option('campaign_fixture_administrator');
    wp_set_current_user($admin);
    $deployment='readiness-'.bin2hex(random_bytes(8));
    prepared_setup($deployment);
    $binding=prepared_scope()+['deployment'=>$deployment,'blog_id'=>get_current_blog_id()];
    $budget=new PeraCRM_Sending_Budget(prepared_pins($deployment),static fn()=>prepared_clock(),static fn()=>true);
    $service=new PeraCRM_Campaign_Readiness($binding,$budget,static fn()=>prepared_clock());
    $before=[];
    $query=static function ($sql) use (&$before) { $before[]=$sql; return $sql; };
    add_filter('query',$query);
    try { $report=$service->assess(); }
    finally { remove_filter('query',$query); }
    foreach ($before as $sql) {
        prepared_expect(!preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|START|COMMIT|ALTER|CREATE|DROP|SET)\b/i',$sql)
            && !str_contains($sql,'FOR UPDATE') && !str_contains($sql,'GET_LOCK'),'readiness is read-only');
    }
    prepared_expect(!$report['production_ready'] && !$report['dispatch_enabled']
        && $report['checks']['suppression_sync']['state']==='validated'
        && $report['checks']['budget_breaker_latch']['state']==='validated','local checks never assert production readiness');
    foreach (PeraCRM_Campaign_Readiness::EXTERNAL as $name=>$source) {
        prepared_expect($report['checks'][$name]['state']==='unverified','missing evidence '.$name);
    }
    $fingerprint=$report['configuration_fingerprint'];
    prepared_expect(is_string($fingerprint),'configuration is bound');
    $nonce=wp_create_nonce('peracrm_readiness_'.$fingerprint);
    $digest=hash('sha256','redacted fixture evidence');
    prepared_reject(fn()=>$service->record($fingerprint,'dns_dkim_dmarc','provider_observation',$digest,'bad-nonce'));
    prepared_reject(fn()=>$service->record($fingerprint,'dns_dkim_dmarc','provider_observation','https://secret.example/token',$nonce));
    $service->record($fingerprint,'dns_dkim_dmarc','repository_mock',$digest,$nonce);
    prepared_expect($service->assess()['checks']['dns_dkim_dmarc']['state']==='insufficient_evidence','mocks never satisfy external evidence');
    $service->record($fingerprint,'dns_dkim_dmarc','operator_attestation',$digest,$nonce);
    prepared_expect($service->assess()['checks']['dns_dkim_dmarc']['state']==='insufficient_evidence','attestation never masquerades as provider observation');
    foreach (PeraCRM_Campaign_Readiness::EXTERNAL as $name=>$source) { $service->record($fingerprint,$name,$source,$digest,$nonce); }
    $report=$service->assess();
    prepared_expect($report['checks']['dns_dkim_dmarc']['state']==='recorded'
        && $report['checks']['dns_dkim_dmarc']['actor']===$admin
        && !$report['production_ready'],'scoped dated actor audit is diagnostic only');
    $stored=get_option('peracrm_readiness_v1_'.$fingerprint);
    prepared_expect(count($stored)===9 && $stored[0]['provenance']==='repository_mock'
        && $stored[1]['provenance']==='operator_attestation','append-only audit preserves provenance');
    $later=new PeraCRM_Campaign_Readiness($binding,$budget,static fn()=>prepared_clock()+30*86400);
    prepared_expect($later->assess()['checks']['restore_verification']['state']==='stale','old evidence expires');
    $earlier=new PeraCRM_Campaign_Readiness($binding,$budget,static fn()=>1);
    prepared_expect($earlier->assess()['checks']['restore_verification']['state']==='unverified','future evidence cannot verify');
    foreach (['deployment'=>'other-deployment','configuration_version'=>2,'account_id'=>'999999999999','region'=>'eu-west-2','configuration_set'=>'other'] as $key=>$value) {
        $changed=new PeraCRM_Campaign_Readiness(array_replace($binding,[$key=>$value]),$budget,static fn()=>prepared_clock());
        prepared_expect($changed->assess()['checks']['dns_dkim_dmarc']['state']==='unverified','changed scope invalidates evidence '.$key);
    }
    $wrongBlog=new PeraCRM_Campaign_Readiness(array_replace($binding,['blog_id'=>get_current_blog_id()+100]));
    prepared_reject(fn()=>$wrongBlog->assess());
    // A real configuration revision change invalidates all prior evidence/nonces.
    $configuration=peracrm_table('peracrm_campaign_configuration');
    $wpdb->query("UPDATE {$configuration} SET revision=revision+1 WHERE id=1");
    prepared_expect($service->assess()['checks']['dns_dkim_dmarc']['state']==='unverified','CRM configuration change invalidates evidence');
    prepared_reject(fn()=>$service->record($fingerprint,'retention','operator_attestation',$digest,$nonce));
    $wpdb->query("UPDATE {$configuration} SET revision=revision-1 WHERE id=1"); // disposable fixture only
    $scope=hash('sha256',json_encode(['policy'=>'ses-account-deny-v1']+prepared_pins($deployment)));
    $sync=peracrm_table('peracrm_campaign_ses_sync');
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$sync} WHERE scope_hash=%s",$scope),ARRAY_A);
    foreach (['running','failed'] as $state) {
        $wpdb->update($sync,['state'=>$state],['scope_hash'=>$scope]);
        prepared_expect($service->assess()['checks']['suppression_sync']['state']==='sync_storage','same worker sync denial '.$state);
        prepared_reject(fn()=>PeraCRM_SES_Suppression_Sync::requireComplete(prepared_pins($deployment),prepared_clock()));
    }
    $wpdb->update($sync,['state'=>'completed','last_complete_at'=>gmdate('Y-m-d H:i:s',prepared_clock()-86400)],['scope_hash'=>$scope]);
    prepared_expect($service->assess()['checks']['suppression_sync']['state']!=='validated','stale sync blocks');
    $wpdb->update($sync,$row,['scope_hash'=>$scope]);
    $attempt=hash('sha256','readiness-attempt'); $owner=hash('sha256','readiness-owner');
    $budget->reserve($attempt,$owner); $budget->beginSubmission($attempt,$owner);
    prepared_expect($service->assess()['checks']['budget_breaker_latch']['state']==='budget_pending','pending latch is same reserve blocker');
    prepared_reject(fn()=>$budget->requireDiagnosticCapacity());
    $serialized=wp_json_encode($service->assess());
    foreach (['123456789012','redacted fixture evidence',$digest,'https://','mailbox','SQLSTATE'] as $private) {
        prepared_expect(!str_contains($serialized,$private),'privacy-safe diagnostics');
    }
    foreach (['campaign_fixture_manager','campaign_fixture_employee'] as $option) {
        wp_set_current_user((int)get_option($option));
        prepared_reject(fn()=>$service->record($fingerprint,'retention','operator_attestation',$digest,$nonce));
    }
    wp_set_current_user(0); prepared_reject(fn()=>$service->assess());
    wp_set_current_user($admin);
    update_user_meta($admin,PERACRM_VIEW_AS_USER_META_KEY,(int)get_option('campaign_fixture_employee'));
    prepared_reject(fn()=>$service->assess());
    prepared_reject(fn()=>$service->record($fingerprint,'retention','operator_attestation',$digest,$nonce));
    delete_user_meta($admin,PERACRM_VIEW_AS_USER_META_KEY);
    prepared_expect(!peracrm_campaign_queue_dispatch_allowed() && peracrm_sending_budget_service()===null
        && peracrm_ses_suppression_sync_service()===null,'all production gates preserved');
    wp_set_current_user($actor);
});
echo "PASS consolidated read-only readiness, evidence, scope, privacy and authorization\n";
