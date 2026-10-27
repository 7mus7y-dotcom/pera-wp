<?php
define('ABSPATH',__DIR__);
function get_current_blog_id() { return 2; }
function peracrm_get_target_blog_id() { return 2; }
function is_multisite() { return true; }
function home_url($path='') { return $GLOBALS['deployment_home']??'https://example.org/crm/'; }
require __DIR__.'/deployment-fixture.php';
function deployment_expect(bool $ok,string $label): void
{
    if (!$ok) { throw new RuntimeException('FAIL deployment: '.$label); }
    echo 'PASS deployment: '.$label."\n";
}
function deployment_reject(Closure $fn,string $label): void
{
    $denied=false;
    try { $fn(); } catch (Throwable $e) { $denied=true; }
    deployment_expect($denied,$label);
}
$now=time(); $m=campaign_deployment_manifest(); $v=campaign_deployment_material($now);
$clock=static function () use (&$now) { return $now; };
$make=static fn($manifest,$material,$runtime=null)=>new PeraCRM_Campaign_Deployment($manifest,$material,$clock,$runtime??campaign_deployment_runtime($manifest));
deployment_expect(peracrm_campaign_deployment_service()===null,'no implicit factory or ambient credentials');
$d=$make($m,$v);
deployment_expect($d->checks()===['deployment_scope'=>'validated','provider_identity'=>'configured',
    'token_signing_keys'=>'configured','output_encryption_keys'=>'configured'],'local configuration has explicit provenance');
deployment_expect($d->preparation() instanceof PeraCRM_Campaign_Worker_Preparation && $d->credentials()->account()===$m['account_id'],
    'adapters reuse preparation and credential contracts without transport');
foreach (['blog_id'=>3,'origin'=>'http://example.org','instance_id'=>'different-instance-01'] as $key=>$value) {
    $runtime=array_replace(campaign_deployment_runtime($m)(),[$key=>$value]);
    $bad=$make($m,$v,static fn()=>$runtime);
    deployment_expect(count(array_unique($bad->checks()))===1 && $bad->checks()['deployment_scope']==='unavailable','clone/runtime mismatch '.$key);
    deployment_reject(fn()=>$bad->preparation(),'mismatch cannot prepare '.$key);
    deployment_reject(fn()=>$bad->fingerprint(),'mismatch cannot bind evidence '.$key);
}
foreach (['provider'=>'other','account_id'=>12,'region'=>'unknown','configuration_version'=>0,'blog_id'=>0,
    'origin'=>'https://example.org/path','instance_id'=>'short','identity_version'=>0,'signing_key_id'=>'e1','encryption_key_id'=>'k1'] as $key=>$value) {
    deployment_reject(fn()=>$make(array_replace($m,[$key=>$value]),$v),'invalid manifest '.$key);
}
deployment_reject(fn()=>$make($m+['enabled'=>true],$v),'no activation/configuration extra fields');
deployment_reject(fn()=>$make($m,$v+['path'=>'secret-file']),'no secret file path input');
$recursive=[]; $recursive['self']=&$recursive;
deployment_reject(fn()=>$make($m,['signing'=>$recursive]),'recursive/oversized material is bounded');
foreach (['identity'=>'provider_identity','signing'=>'token_signing_keys','encryption'=>'output_encryption_keys'] as $kind=>$name) {
    $bad=$v; unset($bad[$kind]);
    deployment_expect($make($m,$bad)->checks()[$name]==='unavailable','missing '.$kind);
    foreach (['not_before'=>$now+1,'expires_at'=>$now+20] as $field=>$value) {
        $bad=$v; $bad[$kind][$field]=$value;
        deployment_expect($make($m,$bad)->checks()[$name]==='unavailable','future/expired '.$kind.' '.$field);
    }
}
foreach (['account_id'=>'999999999999','version'=>2,'access_key'=>'bad','secret_key'=>'bad','session_token'=>"bad\n"] as $key=>$value) {
    $bad=$v; $bad['identity'][$key]=$value;
    deployment_expect($make($m,$bad)->checks()['provider_identity']==='unavailable','identity denied '.$key);
}
foreach (['signing','encryption'] as $kind) {
    $id=$kind==='signing'?'k1':'e1';
    foreach (['active'=>'missing','keys'=>[$id=>'short']] as $key=>$value) {
        $bad=$v; $bad[$kind][$key]=$value;
        deployment_reject(fn()=>$make($m,$bad)->preparation(),'invalid ring '.$kind.' '.$key);
    }
}
$bad=$v; $bad['encryption']['keys']['e1']=$bad['signing']['keys']['k1'];
deployment_expect($make($m,$bad)->checks()['token_signing_keys']==='unavailable'
    && $make($m,$bad)->checks()['output_encryption_keys']==='unavailable','signing/encryption key separation');
$bad=$v; $bad['signing']['keys']['k2']=$bad['signing']['keys']['k1'];
deployment_reject(fn()=>$make($m,$bad)->preparation(),'duplicate key bytes under new ID denied');
$bad=$v; $bad['signing']['keys']['k1']=$bad['identity']['secret_key'];
deployment_reject(fn()=>$make($m,$bad)->preparation(),'provider credential cannot become token key');
$reference=str_repeat('s',32); $copied=$v; $copied['signing']['keys']['k1']=&$reference;
$deployment='immutable-deployment'; $manifest=$m; $manifest['deployment']=&$deployment;
$copiedService=$make($manifest,$copied); $reference='bad'; $deployment='mutated';
deployment_expect($copiedService->checks()['token_signing_keys']==='configured'
    && $copiedService->binding()['deployment']==='immutable-deployment','defensive copies remove references');
deployment_expect($d->fingerprint()!==$make(array_replace($m,['identity_version'=>2]),$v)->fingerprint(),'principal revision invalidates evidence binding');
deployment_expect($d->fingerprint()!==$make(array_replace($m,['signing_key_id'=>'k2']),$v)->fingerprint(),'active key ID invalidates evidence binding');
$binding=['blog_id'=>2,'subscriber_id'=>3,'mailbox'=>'recipient@example.org','token_epoch'=>1,
    'attempt_key'=>hash('sha256','attempt'),'snapshot_hash'=>hash('sha256','snapshot')];
$tokens=new PeraCRM_CampaignRecipientTokens($v['signing']['keys'],'k1',$m['deployment'],static fn()=>true);
$token=$tokens->issue('unsubscribe',$binding,$now,$now+600);
$expired=$v; $expired['signing']['expires_at']=$now+30; $expired['encryption']['expires_at']=$now+30;
$retained=$make($m,$expired); $p=$retained->preparation(); $now+=31;
deployment_reject(fn()=>$retained->preparation(),'expired ring cannot start new preparation');
deployment_reject(fn()=>$p->prepare([],[],[]),'already constructed preparation rechecks expiry before SQL');
deployment_expect($retained->verifyToken($token,'unsubscribe',$binding,static fn()=>true),'retained keys verify an unexpired historical bearer');
deployment_expect(!$retained->verifyToken($token,'preferences',$binding,static fn()=>true)
    && !$retained->verifyToken($token,'unsubscribe',$binding,static fn()=>false),'purpose and durable registry remain mandatory');
$rotated=$v; $rotated['signing']['active']='k2'; $rotated['signing']['keys']['k2']=str_repeat('t',32);
$rotatedManifest=array_replace($m,['signing_key_id'=>'k2']);
deployment_expect($make($rotatedManifest,$rotated)->verifyToken($token,'unsubscribe',$binding,static fn()=>true),'key rotation retains old verification bytes');
unset($rotated['signing']['keys']['k1']);
deployment_expect(!$make($rotatedManifest,$rotated)->verifyToken($token,'unsubscribe',$binding,static fn()=>true),'missing historical key fails closed');
deployment_expect(!str_contains(print_r($d,true),str_repeat('s',32)) && !str_contains(print_r($d,true),'synthetic-session'),'redacted diagnostics');
deployment_reject(fn()=>serialize($d),'serialization prohibited');
// Actual constant-only factory; home_url may include a target-blog subdirectory.
define('PERACRM_CAMPAIGN_DEPLOYMENT',$m); define('PERACRM_CAMPAIGN_MATERIAL',$v);
deployment_expect(peracrm_campaign_deployment_service()->checks()['deployment_scope']==='unavailable','missing independent host instance fails closed');
define('PERACRM_CAMPAIGN_INSTANCE_ID',$m['instance_id']);
deployment_expect(peracrm_campaign_deployment_service()->checks()['provider_identity']==='configured','explicit production source validates locally without network');
foreach (['http://example.org/','https://example.org:443/','https://user@example.org/','https://clone.example.org/'] as $url) {
    $GLOBALS['deployment_home']=$url;
    deployment_expect(peracrm_campaign_deployment_service()->checks()['deployment_scope']==='unavailable','unsafe or cloned configured host denied');
}
echo "PASS campaign deployment adapters\n";
