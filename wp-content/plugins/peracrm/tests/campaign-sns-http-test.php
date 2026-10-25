<?php
define('ABSPATH',__DIR__);
function add_action(...$args) {}
function get_current_blog_id() { return 2; }
function is_multisite() { return true; }
function peracrm_get_target_blog_id() { return 2; }
require __DIR__.'/../inc/campaigns/sns-http.php';
require __DIR__.'/sns-fixture.php';
$v=sns_values();
$config=['enabled'=>true,'blog'=>2,'deployment'=>'fixture','account'=>$v['scope']['account'],'region'=>$v['scope']['region'],
    'topic'=>$v['scope']['topic'],'configuration_set'=>'fixture','configuration_version'=>1];
$calls=0; $cert=static function () use ($v,&$calls) { ++$calls; return $v['pem']; };
$http=new PeraCRM_SNS_HTTP($config,$cert);
sns_expect(peracrm_sns_http_service()===null,'HTTP disabled without deployment constant');
foreach (array_keys($config) as $key) {
    $bad=$config; unset($bad[$key]); $denied=false;
    try { new PeraCRM_SNS_HTTP($bad,$cert); } catch (Throwable $e) { $denied=true; }
    sns_expect($denied,'missing HTTP config '.$key);
}
foreach (['enabled'=>1,'blog'=>1,'deployment'=>'bad deployment','account'=>'999999999999','region'=>'us-east-1','configuration_version'=>'1'] as $key=>$value) {
    $bad=$config; $bad[$key]=$value; $denied=false;
    try { new PeraCRM_SNS_HTTP($bad,$cert); } catch (Throwable $e) { $denied=true; }
    sns_expect($denied,'inconsistent HTTP config '.$key);
}
foreach (['text/plain','application/json','text/xml','text/plain; charset=UTF-8','application/json; charset="UTF-8"'] as $type) {
    sns_expect(PeraCRM_SNS_HTTP::preflight('POST',$type,null)===0,'documented SNS content type '.$type);
}
foreach ([['GET','text/plain',null,405],['HEAD','text/plain',null,405],['PUT','text/plain',null,405],['POST','application/xml',null,415],
    ['POST','application/x-www-form-urlencoded',null,415],['POST','text/plain','262145',413],['POST','text/plain','-1',413],['POST','text/plain','99999999999999999999',413]] as [$method,$type,$length,$status]) {
    sns_expect($http->handle($method,$type,$length,'{}',$v['now'])===$status,'cheap preflight rejection');
}
foreach (['{','null','[]',str_repeat('[',20).str_repeat(']',20)] as $body) {
    sns_expect($http->handle('POST','text/plain',null,$body,$v['now'])===400,'malformed bounded envelope');
}
sns_expect($http->handle('POST','text/plain',null,str_repeat('x',262145),$v['now'])===413,'bounded stream without content length');
sns_expect($calls===0,'preflight and malformed input never retrieve cert');
$body=json_decode(sns_body($v),true); $body['Message']='forged';
sns_expect($http->handle('POST','text/plain',null,json_encode($body),$v['now'])===403,'forged payload rejected');
$http=new PeraCRM_SNS_HTTP($config,static function () { throw new RuntimeException('sensitive'); });
sns_expect($http->handle('POST','text/plain',null,sns_body($v),$v['now'])===503,'temporary authentication dependency retries');
foreach (['SubscriptionConfirmation','UnsubscribeConfirmation'] as $type) {
    sns_expect($http->handle('POST','text/plain',null,sns_body($v,['Type'=>$type]),$v['now'])===400,'no automatic subscription action');
}
sns_expect(!str_contains(print_r($http,true),'certificate'),'redacted HTTP diagnostics');
