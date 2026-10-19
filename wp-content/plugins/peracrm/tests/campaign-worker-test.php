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
