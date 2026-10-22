<?php
/** Pure request parser regressions. SQL and HTTP acceptance live in disposable fixtures. */
define('ABSPATH', __DIR__);
require_once dirname(__DIR__).'/inc/campaigns/recipient-withdrawal.php';
function withdrawal_expect($ok, $label) { if (!$ok) { throw new RuntimeException($label); } }
$store = new PeraCRM_CampaignRecipientPersistence('synthetic',1);
$service = new PeraCRM_CampaignRecipientWithdrawal($store,['k1'=>str_repeat('test',16)],'synthetic');
withdrawal_expect(peracrm_campaign_recipient_withdrawal_service() === null,'production activation blocked');
foreach (['GET','HEAD','PUT','DELETE','OPTIONS'] as $method) {
    withdrawal_expect($service->request($method,true,'secret','',[])[0] === 405,'machine method denied');
}
foreach (['','List-Unsubscribe=One-Click&x=1','List_Unsubscribe=One-Click','List-Unsubscribe=One-Click&List-Unsubscribe=One-Click'] as $body) {
    withdrawal_expect($service->request('POST',true,'secret','application/x-www-form-urlencoded',$body)[0] === 400,'strict machine body');
}
withdrawal_expect($service->request('POST',false,'secret','application/x-www-form-urlencoded',[])[0] === 400,'human confirmation required');
withdrawal_expect($service->request('POST',true,'secret','application/json','List-Unsubscribe=One-Click')[0] === 400,'content type denied');
withdrawal_expect($service->request('GET',false,str_repeat('x',257),'',[])[0] === 400,'oversized token denied');
ob_start(); var_dump($service); $debug = ob_get_clean();
withdrawal_expect(!str_contains($debug,str_repeat('test',16)),'debug redacted');
try { serialize($service); throw new RuntimeException('serialization allowed'); }
catch (LogicException $e) { withdrawal_expect($e->getPrevious() === null,'unchained error'); }
echo "PASS withdrawal request parser and disabled factory\n";
