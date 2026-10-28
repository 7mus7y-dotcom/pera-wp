<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/sns-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/sns-provisioning.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    $admin=get_user_by('login','fixture-admin'); wp_set_current_user($admin->ID);
    $v=sns_values(); $confirmation='provision-exact-sns-subscription'; $key=random_bytes(32);
    $config=['enabled'=>true,'blog'=>get_current_blog_id(),'deployment'=>'sns-provision-'.bin2hex(random_bytes(8))]
        +$v['scope']+['endpoint'=>'https://fixture.example.test/peracrm-ses-sns/'];
    $calls=[]; $failure='';
    $api=static function ($action,$parameters) use (&$calls,&$failure,$config) {
        $calls[]=$action;
        if ($failure==='network') { throw new RuntimeException('synthetic sensitive error'); }
        if ($action==='Subscribe') { return ['SubscriptionArn'=>'pending confirmation']; }
        if ($action==='ConfirmSubscription') {
            sns_expect($parameters['AuthenticateOnUnsubscribe']==='true' && $parameters['TopicArn']===$config['topic'],'SQL exact confirmation authority');
            return ['SubscriptionArn'=>$config['topic'].':12345678-1234-1234-1234-123456789abc'];
        }
        return ['TopicArn'=>$config['topic'],'Owner'=>$config['account'],'Endpoint'=>$failure==='scope'?'https://wrong.example.test/':$config['endpoint'],
            'Protocol'=>'https','ConfirmationWasAuthenticated'=>'true'];
    };
    $make=static function ($c=null,$k=null) use (&$config,$v,$api,$key) { return new PeraCRM_SNS_Provisioning($c??$config,static fn()=>$v['pem'],$api,$k??$key); };
    $service=$make();
    $body=sns_body($v,['Type'=>'SubscriptionConfirmation','Message'=>'Synthetic confirmation','Token'=>'synthetic-private-token',
        'SubscribeURL'=>'https://untrusted.example.test/do-not-fetch']);
    sns_reject(fn()=>$service->receive($body,$v['now']),'sns_conflict');
    wp_set_current_user(0); sns_reject(fn()=>$service->request($confirmation,$v['now']),'sns_authority');
    wp_set_current_user($admin->ID); sns_reject(fn()=>$service->request('yes',$v['now']),'sns_authority');
    sns_expect(!$calls && $service->request($confirmation,$v['now'])==='armed','SQL durable arm before AWS');
    sns_expect($service->request($confirmation,$v['now'])==='armed','SQL arm idempotent');
    $option='peracrm_sns_provision_v1_'.hash('sha256',$config['deployment']);
    $read=static fn()=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$option),ARRAY_A);
    $before=$read();
    $forged=json_decode($body,true); $forged['Token'].='x';
    sns_reject(fn()=>$service->receive(json_encode($forged),$v['now']),'sns_authentication');
    foreach (['account','region','topic'] as $scope) {
        $wrong=json_decode($body,true); $wrong['TopicArn']='arn:aws:sns:us-east-1:999999999999:other';
        sns_reject(fn()=>$service->receive(json_encode($wrong),$v['now']),'sns_invalid');
    }
    sns_expect($read()===$before && !$calls,'SQL invalid input leaves no writes/network');
    $failure='network'; sns_reject(fn()=>$service->subscribe($confirmation,$v['now']),'sns_dependency'); $failure='';
    sns_expect($service->subscribe($confirmation,$v['now'])==='requested','SQL explicit Subscribe recovery');
    sns_expect($service->receive($body,$v['now'])==='received','SQL authenticated pending confirmation');
    $stored=$read();
    sns_expect($stored['autoload']==='off' && !str_contains($stored['option_value'],'synthetic-private-token')
        && !str_contains($stored['option_value'],'untrusted.example.test'),'SQL token encrypted and URL not stored');
    sns_expect($service->receive($body,$v['now'])==='duplicate' && $read()===$stored,'SQL immutable duplicate');
    $changed=sns_body($v,['Type'=>'SubscriptionConfirmation','Message'=>'Changed','Token'=>'synthetic-private-token',
        'SubscribeURL'=>'https://untrusted.example.test/do-not-fetch']);
    sns_reject(fn()=>$service->receive($changed,$v['now']),'sns_conflict');
    sns_reject(fn()=>$make(null,random_bytes(32))->confirm($confirmation,$v['now']),'sns_storage');
    $bad=$config; $bad['endpoint']='https://other.example.test/peracrm-ses-sns/';
    sns_reject(fn()=>$make($bad)->status($confirmation),'sns_conflict');
    $count=count($calls);
    $filter=static fn($sql)=>str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_sns_provision_v1_')?'INVALID_SNS_PROVISION_SQL':$sql;
    add_filter('query',$filter);
    try { sns_reject(fn()=>$service->confirm($confirmation,$v['now']),'sns_storage'); }
    finally { remove_filter('query',$filter); }
    sns_expect(count($calls)===$count && $read()===$stored,'SQL persistence failure precedes remote mutation');
    $failure='network'; sns_reject(fn()=>$service->confirm($confirmation,$v['now']),'sns_dependency');
    sns_expect($service->status($confirmation)[0]['state']==='confirming','SQL remote ambiguity durable');
    $failure='scope'; sns_reject(fn()=>$service->confirm($confirmation,$v['now']),'sns_conflict');
    $failure='';
    $writes=0;
    $finalFault=static function ($sql) use (&$writes) {
        if (str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_sns_provision_v1_') && ++$writes===2) { return 'INVALID_SNS_FINAL_SQL'; }
        return $sql;
    };
    add_filter('query',$finalFault);
    try { sns_reject(fn()=>$service->confirm($confirmation,$v['now']),'sns_storage'); }
    finally { remove_filter('query',$finalFault); }
    sns_expect($service->status($confirmation)[0]['state']==='confirming','SQL failed final persistence retains ambiguous authority');
    sns_expect($service->confirm($confirmation,$v['now'])==='confirmed','SQL operator recovery validates actual subscription');
    $count=count($calls); $final=$read();
    sns_expect(!str_contains($final['option_value'],'sealed') && $service->confirm($confirmation,$v['now'])==='confirmed'
        && count($calls)===$count && $service->receive($body,$v['now'])==='duplicate' && $read()===$final,'SQL confirmed replay no network and token purged');
    sns_expect(!str_contains(json_encode($service->status($confirmation)),'digest') && !str_contains(print_r($service,true),'synthetic'),'SQL diagnostics safe');
    // Expiry retains replay tombstones but cannot renew an old confirmation's authority.
    $config['deployment'].='-expiry'; $expired=$make();
    $expired->request($confirmation,$v['now']); $expired->receive($body,$v['now']);
    sns_reject(fn()=>$expired->confirm($confirmation,$v['now']+3601),'sns_conflict');
    sns_expect($expired->request($confirmation,$v['now']+3601)==='armed' && $expired->receive($body,$v['now']+3601)==='duplicate'
        && $expired->status($confirmation)[1]['state']==='armed','SQL expired replay cannot refill new pending slot');
    sns_expect(!peracrm_campaign_queue_dispatch_allowed() && peracrm_sns_http_service()===null && peracrm_sns_provisioning_service()===null,'SQL production gates remain disabled');
});
echo "PASS SNS provisioning integration\n";
