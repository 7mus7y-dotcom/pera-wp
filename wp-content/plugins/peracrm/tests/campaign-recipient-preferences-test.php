<?php
define('ABSPATH',__DIR__);
require_once dirname(__DIR__).'/inc/campaigns/recipient-preferences.php';
function pref_expect($ok,$label) { if (!$ok) { throw new RuntimeException($label); } }
$service=new PeraCRM_CampaignRecipientPreferences(new PeraCRM_CampaignRecipientPersistence('synthetic',1),['k1'=>str_repeat('test',16)],'synthetic');
pref_expect(peracrm_campaign_recipient_preferences_service()===null,'production blocked');
pref_expect(peracrm_campaign_recipient_preferences_profile(['language'=>'EN-us','topics'=>['properties','news']])===['language'=>'en-us','topics'=>['news','properties']],'canonical profile');
foreach ([['language'=>'x','topics'=>[]],['language'=>'en','topics'=>['consent']],['language'=>'en','topics'=>['news','news']],['language'=>[],'topics'=>[]],['language'=>'en','topics'=>[],'subscription_state'=>'subscribed']] as $profile) {
    try { peracrm_campaign_recipient_preferences_profile($profile); throw new RuntimeException('invalid profile allowed'); }
    catch (InvalidArgumentException $e) {}
}
pref_expect(peracrm_campaign_recipient_preferences_merge(['custom','news'],[])===['custom'],'clearing rendered topics preserves custom');
pref_expect(peracrm_campaign_recipient_preferences_merge(['custom','news'],['news'])===['custom','news'],'language-only edit preserves custom');
pref_expect(peracrm_campaign_recipient_preferences_merge(['properties','custom'],['news'])===['custom','news'],'replace only rendered topics');
try { peracrm_campaign_recipient_preferences_merge(array_map(fn($i)=>'custom'.$i,range(1,32)),['news']); throw new RuntimeException('overflow accepted'); } catch (InvalidArgumentException $e) {}
pref_expect(peracrm_campaign_recipient_preferences_same_origin('null','https://example.test','same-origin'),'no-referrer same-origin navigation supported');
foreach (['same-site','cross-site','none'] as $site) { pref_expect(!peracrm_campaign_recipient_preferences_same_origin('null','https://example.test',$site),'explicit foreign metadata denied'); }
pref_expect(peracrm_campaign_recipient_preferences_same_origin('null','https://example.test',''),'no-referrer requires cookie proof when metadata absent');
$token='secret'; $body=['token'=>$token,'language'=>'en','topics'=>['news'],'proof'=>$service->proof($token,time(),str_repeat('a',48))];
foreach ([['https://evil.test','same-origin'],['null','cross-site'],['',''],['https://example.test','same-site'],['https://example.test','cross-site']] as [$origin,$site]) {
 pref_expect($service->request('POST',$token,'application/x-www-form-urlencoded',$body,$origin,'https://example.test',$site,str_repeat('a',48))[0]===400,'cross-site rejected');
}
foreach (['', $service->proof($token,time(),str_repeat('b',48)), $service->proof('different',time(),str_repeat('a',48)),$service->proof($token,time()-901,str_repeat('a',48)),$service->proof($token,time()+901,str_repeat('a',48))] as $proof) {
 $body['proof']=$proof;
 pref_expect($service->request('POST',$token,'application/x-www-form-urlencoded',$body,'https://example.test','https://example.test','same-origin',str_repeat('a',48))[0]===400,'invalid proof denied before storage');
}
pref_expect($service->request('DELETE',$token,'',[],'','','')[0]===405,'method denied');
pref_expect($service->request('GET',str_repeat('a',257),'',[],'','','')[0]===400,'bounded input');
ob_start(); var_dump($service);$debug=ob_get_clean();pref_expect(!str_contains($debug,str_repeat('test',16)),'debug redacted');
try { serialize($service);throw new RuntimeException('serialization allowed'); } catch (LogicException $e) {}
echo "PASS recipient preference profile, CSRF, proof, parser and disabled factory\n";
