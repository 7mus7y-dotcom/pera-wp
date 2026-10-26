<?php
require __DIR__.'/campaign-queue-test.php';
require $root.'/inc/campaigns/worker.php';
campaign_expect(is_wp_error(peracrm_campaign_worker_run(1)) && !class_exists('PeraCRM_Fake_Campaign_Provider'),'production entry denies and fake is not loaded');
foreach ([[],['kind'=>'timeout'],['kind'=>'accepted'],['kind'=>'accepted','message_id'=>''],['kind'=>'accepted','message_id'=>str_repeat('x',192)]] as $bad) {
    campaign_expect(peracrm_campaign_worker_result($bad)===['kind'=>'unknown'],'malformed acceptance is ambiguous');
}
foreach (['accepted','permanent','transient','unknown'] as $kind) {
    campaign_expect(peracrm_campaign_worker_result(['kind'=>$kind,'message_id'=>'fake-correlation'])['kind']===$kind,'provider result '.$kind);
}
for ($i=1;$i<=20;$i++) {
    $delay=peracrm_campaign_worker_delay($i,'fixture');
    campaign_expect($delay>=30 && $delay<=4500 && $delay===peracrm_campaign_worker_delay($i,'fixture'),'bounded deterministic exponential jitter');
}
campaign_expect(peracrm_campaign_worker_delay(1,'fixture',120)===120 && peracrm_campaign_worker_delay(1,'fixture',999999)===86400,'bounded retry-after');
$source=file_get_contents($root.'/inc/campaigns/worker.php');
campaign_expect(!preg_match('/\b(?:wp_mail|wp_remote_\w+|curl_exec|wp_schedule_\w+|add_action|peracrm_subscribers_issue_token)\s*\(/',$source),'no delivery network scheduler hooks or preference-token issuance');
$reflection=new ReflectionClass('PeraCRM_Campaign_Worker');
campaign_expect($reflection->isAbstract() && $reflection->getMethod('claim')->isFinal() && $reflection->getMethod('submit')->isFinal(),'worker requires explicit dependency authority with fenced final entrypoints');
echo "PASS worker standalone contracts\n";
require_once $root.'/inc/campaigns/worker-content.php';
campaign_expect($reflection->getMethod('submitPrepared')->isFinal(),'integrated submission retains final worker entrypoint');
$scope=['provider'=>'ses_v2','account_id'=>'123456789012','region'=>'eu-west-1','configuration_set'=>'fixture','configuration_version'=>1];
$preparation=new PeraCRM_Campaign_Worker_Preparation('fixture',$scope,'https://example.org',
    ['k1'=>str_repeat('s',32)],'k1',new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('e',32)],'e1'),static fn()=>1800000000);
campaign_expect(!str_contains(print_r($preparation,true),str_repeat('s',32)),'worker dependency debug output redacts injected secrets');
$denied=false;
try { serialize($preparation); } catch (LogicException $e) { $denied=true; }
campaign_expect($denied,'worker dependency serialization disabled');
$reordered=new PeraCRM_Campaign_Worker_Preparation('fixture',array_reverse($scope,true),'https://example.org',[],
    'missing',new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('e',32)],'e1'),static fn()=>1800000000);
campaign_expect($reordered->scope()===$preparation->scope(),'scope order is canonical across recovery dependencies');
$authorized=0; $denied=false;
try { PeraCRM_Campaign_Worker_Content::resolve([],[],$scope,static function () use (&$authorized) {
    ++$authorized; throw new RuntimeException('sensitive authority detail');
}); } catch (RuntimeException $e) { $denied=$e->getMessage()==='frozen_content_unavailable' && $e->getPrevious()===null; }
campaign_expect($authorized===1 && $denied,'resolver denies before storage reads and strips authority diagnostics');
campaign_expect(is_wp_error(peracrm_campaign_worker_run(1)),'integrated dependencies do not open production dispatch');
