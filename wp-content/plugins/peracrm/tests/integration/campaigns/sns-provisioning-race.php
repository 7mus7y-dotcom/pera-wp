<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/sns-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    wp_set_current_user(get_user_by('login','fixture-admin')->ID);
    $confirmation='provision-exact-sns-subscription';
    if ($mode==='prepare') {
        $v=sns_values();
        $v['config']=['enabled'=>true,'blog'=>get_current_blog_id(),'deployment'=>'sns-provision-race-'.bin2hex(random_bytes(8))]
            +$v['scope']+['endpoint'=>'https://fixture.example.test/peracrm-ses-sns/'];
        $v['storage_key']=base64_encode(random_bytes(32));
        update_option('sns_provision_race_fixture',$v,false);
    } else { $v=get_option('sns_provision_race_fixture'); }
    $api=static function ($action,$parameters) use ($v,$scenario) {
        if ($action==='ConfirmSubscription') {
            file_put_contents('/tmp/peracrm-sns-provision-calls','call',FILE_APPEND);
            if ($scenario==='confirm' && !is_file('/tmp/peracrm-sns-provision-held')) {
                file_put_contents('/tmp/peracrm-sns-provision-held','held'); usleep(1500000);
            }
            return ['SubscriptionArn'=>$v['scope']['topic'].':12345678-1234-1234-1234-123456789abc'];
        }
        return ['TopicArn'=>$v['scope']['topic'],'Owner'=>$v['scope']['account'],'Endpoint'=>$v['config']['endpoint'],
            'Protocol'=>'https','ConfirmationWasAuthenticated'=>'true'];
    };
    $service=new PeraCRM_SNS_Provisioning($v['config'],static fn()=>$v['pem'],$api,base64_decode($v['storage_key']));
    $body=sns_body($v,['Type'=>'SubscriptionConfirmation','Message'=>'Synthetic confirmation','Token'=>'synthetic-private-token',
        'SubscribeURL'=>'https://untrusted.example.test/never-fetch']);
    if ($mode==='prepare') {
        $service->request($confirmation,$v['now']);
        if ($scenario==='confirm') { $service->receive($body,$v['now']); }
        return;
    }
    if ($mode==='verify') {
        $a=json_decode(file_get_contents('/tmp/peracrm-sns-provision-winner'),true);
        $b=json_decode(file_get_contents('/tmp/peracrm-sns-provision-loser'),true);
        sns_expect($a['connection']!==$b['connection'] && $b['result']==='sns_storage','control concurrent independent process fails closed');
        if ($scenario==='confirm') {
            sns_expect($a['result']==='confirmed' && file_get_contents('/tmp/peracrm-sns-provision-calls')==='call'
                && $service->confirm($confirmation,$v['now'])==='confirmed','control concurrent confirmation one remote mutation');
        } else {
            sns_expect($a['result']==='received' && $service->receive($body,$v['now'])==='duplicate','control concurrent duplicate durable recovery');
        }
        return;
    }
    if ($mode==='winner' && $scenario==='receive') {
        add_filter('query',static function ($sql) {
            if (str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_sns_provision_v1_')) {
                file_put_contents('/tmp/peracrm-sns-provision-held','held'); usleep(1500000);
            }
            return $sql;
        });
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()');
    try { $result=$scenario==='confirm'?$service->confirm($confirmation,$v['now']):$service->receive($body,$v['now']); }
    catch (PeraCRM_SNS_Error $e) { $result=$e->getMessage(); }
    file_put_contents('/tmp/peracrm-sns-provision-'.$mode,json_encode(['connection'=>$connection,'result'=>$result]));
});
