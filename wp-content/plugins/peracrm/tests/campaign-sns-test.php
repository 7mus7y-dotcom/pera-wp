<?php
define('ABSPATH',__DIR__);
require __DIR__.'/sns-fixture.php';
$v=sns_values(); $calls=0;
$verifier=new PeraCRM_SNS_Verifier($v['scope'],static function ($url) use ($v,&$calls) { ++$calls; return $v['pem']; });
$body=sns_body($v,['Subject'=>"A\nB"]);
$r=$verifier->verify($body,$v['now']); sns_expect($calls===1 && $r['hash']===hash('sha256',$r['canonical']),'signed escaped fields and SHA256');
sns_expect(str_ends_with($r['canonical'],"Type\nNotification\n"),'canonical trailing newline');
// Independent canonical oracle: signing must not reuse the implementation under test.
$independent=json_decode($body,true);
$expected="Message\n{\"eventType\":\"Send\",\"mail\":{\"messageId\":\"synthetic\"}}\n"
    ."MessageId\n12345678-1234-1234-1234-123456789abc\nSubject\nA\nB\nTimestamp\n"
    .gmdate('Y-m-d\TH:i:s\Z',$v['now'])."\nTopicArn\narn:aws:sns:eu-west-1:123456789012:fixture\nType\nNotification\n";
openssl_sign($expected,$independentSignature,$v['key'],OPENSSL_ALGO_SHA256);
$independent['Signature']=base64_encode($independentSignature);
sns_expect($verifier->verify(json_encode($independent),$v['now'])['canonical']===$expected,'independent AWS canonical field order oracle');

$e=json_decode($body,true); $e['Message']='forged';
sns_reject(fn()=>$verifier->verify(json_encode($e),$v['now']),'sns_authentication');
foreach (['http://sns.eu-west-1.amazonaws.com/SimpleNotificationService-'.str_repeat('a',32).'.pem',
    'https://sns.eu-west-1.amazonaws.com.evil.test/x.pem','https://127.0.0.1/x.pem',
    'https://sns.eu-west-1.amazonaws.com:443/SimpleNotificationService-'.str_repeat('a',32).'.pem',
    'https://user@sns.eu-west-1.amazonaws.com/x.pem','https://sns.eu-west-1.amazonaws.com/../x.pem',
    'https://sns.eu-west-1.amazonaws.com/%2e%2e/x.pem',
    'https://sns.eu-west-1.amazonaws.com/SimpleNotificationService-'.str_repeat('a',32).'.pem?x=1',
    'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-'.str_repeat('a',32).'.pem'] as $url) {
    $before=$calls; sns_reject(fn()=>$verifier->verify(sns_body($v,['SigningCertURL'=>$url]),$v['now']),'sns_invalid');
    sns_expect($calls===$before,'URL attack rejected before retrieval');
}
foreach ([['TopicArn'=>'arn:aws:sns:eu-west-1:999999999999:fixture'],['Type'=>'SubscriptionConfirmation'],['SignatureVersion'=>'1'],['Timestamp'=>'2026-02-30T00:00:00Z'],['MessageId'=>'invalid']] as $change) {
    $before=$calls; sns_reject(fn()=>$verifier->verify(sns_body($v,$change),$v['now']),'sns_invalid'); sns_expect($calls===$before,'invalid envelope before retrieval');
}
foreach (['null','[]','{','{"Message":{}}',str_repeat('x',262145)] as $invalid) { sns_reject(fn()=>$verifier->verify($invalid,$v['now']),'sns_invalid'); }
$broken=new PeraCRM_SNS_Verifier($v['scope'],static function () { throw new RuntimeException('secret'); });
sns_reject(fn()=>$broken->verify($body,$v['now']),'sns_dependency');
sns_reject(fn()=>$verifier->verify($body,$v['now']+86400*3),'sns_dependency');
// Exercise HTTP policy without any real HTTP implementation or network.
function wp_safe_remote_get($url,$options) { $GLOBALS['sns_http_options']=$options; return ['code'=>$GLOBALS['sns_http_code']??200,'body'=>$GLOBALS['sns_http_body']??'fixture']; }
function is_wp_error($response) { return false; }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
peracrm_sns_certificate_fetch($verifier,json_decode($body,true)['SigningCertURL']);
$o=$GLOBALS['sns_http_options']; sns_expect($o['redirection']===0 && $o['sslverify']===true && $o['reject_unsafe_urls']===true && $o['limit_response_size']===16385 && $o['timeout']===5,'bounded HTTPS policy');
$GLOBALS['sns_http_code']=302; sns_reject(fn()=>peracrm_sns_certificate_fetch($verifier,json_decode($body,true)['SigningCertURL']),'sns_dependency');
$GLOBALS['sns_http_code']=200; $GLOBALS['sns_http_body']=str_repeat('x',16385); sns_reject(fn()=>peracrm_sns_certificate_fetch($verifier,json_decode($body,true)['SigningCertURL']),'sns_dependency');
sns_reject(fn()=>peracrm_sns_certificate_fetch($verifier,'http://127.0.0.1/'),'sns_invalid');
echo "PASS SNS verification; real network unavailable by construction\n";
