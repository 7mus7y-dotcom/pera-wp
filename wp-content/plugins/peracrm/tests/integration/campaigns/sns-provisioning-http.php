<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/sns-fixture.php';
function provision_http(string $body,string $failure='',string $method='POST',string $type='text/plain'): int
{
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>'Content-Type: '.$type."\r\nX-Fixture-Failure: ".$failure."\r\n",
        'content'=>$body,'ignore_errors'=>true,'timeout'=>20,'follow_location'=>0]]);
    $result=file_get_contents(set_url_scheme(home_url('/peracrm-ses-sns/'),'http'),false,$context);
    $headers=$http_response_header??[]; preg_match('/\AHTTP\/\S+ ([0-9]{3})/',$headers[0]??'',$status);
    sns_expect(isset($status[1]) && $result===($method==='HEAD'?'':($status[1]==='202'?'Accepted.':'Request unavailable.')),'control HTTP generic response');
    sns_expect(in_array('Cache-Control: no-store, private, max-age=0',$headers,true),'control HTTP privacy');
    if ($status[1]==='503') { sns_expect(in_array('Retry-After: 60',$headers,true),'control HTTP retry header'); }
    return (int)$status[1];
}
peracrm_with_target_blog(static function () {
    $v=sns_values(); $confirmation='provision-exact-sns-subscription';
    wp_set_current_user(get_user_by('login','fixture-admin')->ID);
    $config=['enabled'=>true,'blog'=>get_current_blog_id(),'deployment'=>'sns-provision-http-'.bin2hex(random_bytes(8))]
        +$v['scope']+['endpoint'=>set_url_scheme(home_url('/peracrm-ses-sns/'),'https')];
    $key=hash_hkdf('sha256',wp_salt('auth'),32,'peracrm-sns-provision-v1',json_encode($config,JSON_THROW_ON_ERROR));
    $service=new PeraCRM_SNS_Provisioning($config,static fn()=>$v['pem'],static function () { throw new RuntimeException('No public API'); },$key);
    $body=sns_body($v,['Type'=>'SubscriptionConfirmation','Message'=>'Synthetic confirmation','Token'=>'synthetic-private-token',
        'SubscribeURL'=>'https://untrusted.example.test/never-fetch']);
    update_option('sns_provision_http_fixture',['config'=>$config,'pem'=>$v['pem']],false);
    try {
        sns_expect(provision_http($body)===409,'control HTTP needs armed administrator');
        $service->request($confirmation,$v['now']);
        sns_expect(provision_http('','', 'HEAD')===405 && provision_http('{}','','POST','application/xml')===415,'control HTTP cheap preflight');
        sns_expect(provision_http(str_repeat('x',262145))===413,'control HTTP bounded stream');
        sns_expect(provision_http(sns_body($v))===400,'control HTTP rejects notifications');
        $forged=json_decode($body,true); $forged['Token'].='x';
        sns_expect(provision_http(json_encode($forged))===403,'control HTTP invalid signature');
        sns_expect(provision_http($body,'certificate')===503 && provision_http($body,'storage')===503,'control HTTP dependency/storage retry');
        sns_expect($service->status($confirmation)[0]['state']==='armed','control HTTP failures leave pending authority');
        sns_expect(provision_http($body)===202 && provision_http($body)===202,'control HTTP durable valid and duplicate');
        sns_expect($service->status($confirmation)[0]['state']==='received','control HTTP cannot confirm or call API');
        $changed=sns_body($v,['Type'=>'SubscriptionConfirmation','Message'=>'Changed','Token'=>'synthetic-private-token','SubscribeURL'=>'https://untrusted.example.test/never-fetch']);
        sns_expect(provision_http($changed)===409,'control HTTP signed conflict');
    } finally { delete_option('sns_provision_http_fixture'); }
    sns_expect(provision_http('{}')===404,'control HTTP teardown leaves endpoint disabled');
});
