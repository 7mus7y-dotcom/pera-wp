<?php
require_once __DIR__.'/prepared-worker-fixture.php';
worker_fixture_target(static function () {
    global $wpdb;
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    $binding=prepared_scope()+['deployment'=>'readiness-ui-sql','blog_id'=>get_current_blog_id()];
    $service=new PeraCRM_Campaign_Readiness($binding);
    $fingerprint=$service->assess()['configuration_fingerprint'];
    $input=['fingerprint'=>$fingerprint,'prerequisite'=>'retention','provenance'=>'operator_attestation',
        'digest'=>hash('sha256','redacted UI fixture'),'nonce'=>wp_create_nonce('peracrm_readiness_'.$fingerprint),'confirmed'=>'1'];
    $old=get_option('peracrm_readiness_v1_'.$fingerprint,[]);
    foreach (['digest'=>['https://secret.invalid/token',str_repeat('x',65),['array'],'<script>bad</script>','Bearer bad',"message\nbody"],
        'fingerprint'=>[str_repeat('0',64)],'prerequisite'=>['unknown'],'provenance'=>['host_observation'],'confirmed'=>['0'],'nonce'=>['bad']] as $key=>$badValues) {
        foreach ($badValues as $bad) { prepared_reject(fn()=>peracrm_campaign_readiness_ui_save($service,array_replace($input,[$key=>$bad]))); }
    }
    foreach (['actor','verified_at','scope','notes','url'] as $extra) { prepared_reject(fn()=>peracrm_campaign_readiness_ui_save($service,$input+[$extra=>'forbidden'])); }
    prepared_expect(get_option('peracrm_readiness_v1_'.$fingerprint,[])===$old,'rejected form input leaves audit unchanged');
    peracrm_campaign_readiness_ui_save($service,$input);
    peracrm_campaign_readiness_ui_save($service,array_replace($input,['digest'=>hash('sha256','second artifact')]));
    $records=get_option('peracrm_readiness_v1_'.$fingerprint);
    prepared_expect(count($records)===2 && $records[0]['actor']===get_current_user_id() && $records[0]['verified_at']<=time(),'real actor and server date; immutable audit append');
    $history=$service->auditPage();
    prepared_expect($history['groups'][0]['records']===$records,'audit service returns exact bounded contract');
    foreach (['campaign_fixture_manager','campaign_fixture_employee'] as $option) {
        wp_set_current_user((int)get_option($option));
        prepared_reject('peracrm_campaign_readiness_ui_require');
        prepared_reject(fn()=>peracrm_campaign_readiness_ui_save($service,$input));
        prepared_reject(fn()=>$service->auditPage());
        prepared_reject(fn()=>$service->administratorScope());
    }
    wp_set_current_user(0); prepared_reject('peracrm_campaign_readiness_ui_require');
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    update_user_meta(get_current_user_id(),PERACRM_VIEW_AS_USER_META_KEY,(int)get_option('campaign_fixture_employee'));
    prepared_reject('peracrm_campaign_readiness_ui_require');
    prepared_reject(fn()=>peracrm_campaign_readiness_ui_save($service,$input));
    delete_user_meta(get_current_user_id(),PERACRM_VIEW_AS_USER_META_KEY);
    $wrong=new PeraCRM_Campaign_Readiness(array_replace($binding,['blog_id'=>get_current_blog_id()+100]));
    prepared_reject(fn()=>peracrm_campaign_readiness_ui_save($wrong,$input));
    prepared_reject(fn()=>$wrong->auditPage());
    $configuration=peracrm_table('peracrm_campaign_configuration');
    $wpdb->query("UPDATE {$configuration} SET revision=revision+1 WHERE id=1");
    prepared_reject(fn()=>peracrm_campaign_readiness_ui_save($service,$input));
    prepared_expect($service->assess()['checks']['retention']['state']==='unverified' && get_option('peracrm_readiness_v1_'.$fingerprint)===$records,'configuration invalidates applicability and retains audit');
    // Real SQL failure, not a fake success return.
    $next=$service->assess()['configuration_fingerprint'];
    $fail=static function ($sql) { return str_starts_with($sql,'INSERT INTO') && str_contains($sql,'peracrm_readiness_v1_')?'INVALID_READINESS_FIXTURE_SQL':$sql; };
    add_filter('query',$fail);
    try { prepared_reject(fn()=>peracrm_campaign_readiness_ui_save($service,array_replace($input,['fingerprint'=>$next,'nonce'=>wp_create_nonce('peracrm_readiness_'.$next)]))); }
    finally { remove_filter('query',$fail); }
    prepared_expect(get_option('peracrm_readiness_v1_'.$next,[])===[],'failed storage creates no audit success');
    prepared_expect(!peracrm_campaign_queue_dispatch_allowed() && peracrm_ses_suppression_sync_service()===null,'UI cannot activate production gates');
});
echo "PASS administrator readiness form: permissions, impersonation, validation, stale configuration, wrong scope, audit and storage failure\n";
