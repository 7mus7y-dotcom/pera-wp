<?php
define('ABSPATH',__DIR__);
function add_action(...$args) {}
require __DIR__.'/sns-fixture.php';
require __DIR__.'/../inc/campaigns/sns-provisioning-runtime.php';
require __DIR__.'/../inc/campaigns/providers/sns-api.php';
function is_wp_error($v) { return false; }
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
$v=sns_values();
$e=['Type'=>'SubscriptionConfirmation','Message'=>'Synthetic confirmation','Token'=>'synthetic-token',
    'SubscribeURL'=>'https://untrusted.example.test/never-fetch'];
$body=sns_body($v,$e); $verifier=sns_verifier($v);
sns_reject(fn()=>$verifier->verify($body,$v['now']),'sns_invalid');
$r=$verifier->verifyConfirmation($body,$v['now']);
sns_expect($r['token']==='synthetic-token','separate confirmation signature v2');
foreach (['Token','SubscribeURL','Message','Timestamp'] as $field) {
    $forged=json_decode($body,true); $forged[$field].='x';
    sns_reject(fn()=>$verifier->verifyConfirmation(json_encode($forged),$v['now']),$field==='Timestamp'?'sns_invalid':'sns_authentication');
}
foreach ([['TopicArn','arn:aws:sns:eu-west-1:999999999999:fixture'],['TopicArn','arn:aws:sns:us-east-1:123456789012:fixture'],
    ['TopicArn',$v['scope']['topic'].'-other'],['SignatureVersion','1'],['Type','UnsubscribeConfirmation']] as [$field,$value]) {
    sns_reject(fn()=>$verifier->verifyConfirmation(sns_body($v,array_replace($e,[$field=>$value])),$v['now']),'sns_invalid');
}
sns_reject(fn()=>$verifier->verifyConfirmation(sns_body($v),$v['now']),'sns_invalid');
sns_expect(peracrm_sns_provisioning_service()===null,'provisioning disabled by default');
$credentials=new PeraCRM_Campaign_SES_Credentials($v['scope']['account'],str_repeat('A',20),str_repeat('b',40),'synthetic-session',time()+300);
$calls=0;
$api=new PeraCRM_SNS_API($v['scope'],$credentials,static function ($url,$options) use (&$calls,$v) {
    ++$calls; parse_str($options['body'],$q);
    sns_expect($url==='https://sns.eu-west-1.amazonaws.com/' && $options['redirection']===0 && $options['sslverify']===true
        && $options['timeout']===10 && isset($options['headers']['Authorization']),'fixed authenticated bounded regional API');
    sns_expect($q['Action']==='ConfirmSubscription' && $q['Token']==='synthetic-token' && $q['TopicArn']===$v['scope']['topic']
        && $q['AuthenticateOnUnsubscribe']==='true','confirmation uses POST body and authenticated unsubscribe');
    return ['status'=>200,'body'=>'<ConfirmSubscriptionResponse xmlns="http://sns.amazonaws.com/doc/2010-03-31/"><ConfirmSubscriptionResult><SubscriptionArn>'.$v['scope']['topic'].':12345678-1234-1234-1234-123456789abc</SubscriptionArn></ConfirmSubscriptionResult></ConfirmSubscriptionResponse>'];
});
$out=$api('ConfirmSubscription',['TopicArn'=>$v['scope']['topic'],'Token'=>'synthetic-token','AuthenticateOnUnsubscribe'=>'true']);
sns_expect(str_starts_with($out['SubscriptionArn'],$v['scope']['topic'].':') && $calls===1,'namespaced SNS XML response parsed');
sns_reject(fn()=>$api('ConfirmSubscription',['TopicArn'=>$v['scope']['topic'],'Token'=>'synthetic-token','AuthenticateOnUnsubscribe'=>'false']),'sns_dependency');
sns_expect($calls===1,'invalid API parameters never execute');
foreach ([['status'=>503,'body'=>'sensitive'],['status'=>200,'body'=>'<!DOCTYPE x [<!ENTITY a SYSTEM "file:///etc/passwd">]><x>&a;</x>'],
    ['status'=>200,'body'=>str_repeat('x',65537)],['status'=>200,'body'=>'invalid']] as $response) {
    $bad=new PeraCRM_SNS_API($v['scope'],$credentials,static fn()=>$response);
    sns_reject(fn()=>$bad('ConfirmSubscription',['TopicArn'=>$v['scope']['topic'],'Token'=>'synthetic-token','AuthenticateOnUnsubscribe'=>'true']),'sns_dependency');
}
sns_expect(!str_contains(print_r($api,true),'synthetic'),'API diagnostics redacted');

$attributeApi=new PeraCRM_SNS_API($v['scope'],$credentials,static fn()=>['status'=>200,'body'=>
    '<GetSubscriptionAttributesResponse xmlns="http://sns.amazonaws.com/doc/2010-03-31/"><GetSubscriptionAttributesResult><Attributes>'
    .'<entry><key>Owner</key><value>123456789012</value></entry><entry><key>ConfirmationWasAuthenticated</key><value>true</value></entry>'
    .'</Attributes></GetSubscriptionAttributesResult></GetSubscriptionAttributesResponse>']);
$attrs=$attributeApi('GetSubscriptionAttributes',['SubscriptionArn'=>$v['scope']['topic'].':12345678-1234-1234-1234-123456789abc']);
sns_expect($attrs===['Owner'=>'123456789012','ConfirmationWasAuthenticated'=>'true'],'actual namespaced attribute map');
sns_reject(fn()=>$api('Subscribe',['TopicArn'=>'arn:aws:sns:eu-west-1:999999999999:fixture','Protocol'=>'https',
    'Endpoint'=>'https://fixture.example.test/','ReturnSubscriptionArn'=>'false']),'sns_dependency');
$config=['enabled'=>true,'blog'=>2,'deployment'=>'fixture']+$v['scope']+['endpoint'=>'https://fixture.example.test/peracrm-ses-sns/'];
foreach (['enabled'=>1,'blog'=>0,'deployment'=>'bad deployment','account'=>'999999999999','region'=>'us-east-1',
    'endpoint'=>'http://fixture.example.test/peracrm-ses-sns/'] as $key=>$value) {
    $bad=$config; $bad[$key]=$value;
    sns_reject(fn()=>new PeraCRM_SNS_Provisioning($bad,static fn()=>$v['pem'],static fn()=>[],str_repeat('k',32)),'sns_configuration');
}
