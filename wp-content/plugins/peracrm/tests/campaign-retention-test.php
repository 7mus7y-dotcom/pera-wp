<?php
define('ABSPATH',__DIR__);
require_once dirname(__DIR__).'/inc/campaigns/retention.php';
function retention_expect(bool $ok,string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
}
$row=['payload_hash'=>hash('sha256','authenticated-body'),'canonical_payload'=>'authenticated-body','authentication_version'=>'sns-v2-sha256'];
retention_expect(peracrm_sns_receipt_integrity($row),'live body hash checked');
retention_expect(!peracrm_sns_receipt_integrity(array_replace($row,['canonical_payload'=>'tampered'])),'tampering rejected');
$retired=array_replace($row,['canonical_payload'=>'','authentication_version'=>'sns-v2-retired']);
retention_expect(peracrm_sns_receipt_integrity($retired),'retired body keeps original authenticated digest');
foreach ([['canonical_payload'=>'rehydrated'],['payload_hash'=>''],['payload_hash'=>str_repeat('x',64)],['authentication_version'=>'unknown']] as $bad) {
    retention_expect(!peracrm_sns_receipt_integrity(array_replace($retired,$bad)),'malformed tombstone rejected');
}
foreach ([[],['scope'=>str_repeat('a',64),'policy'=>str_repeat('b',64),'blog'=>0],
    ['scope'=>str_repeat('a',64),'policy'=>str_repeat('b',64),'blog'=>1,'extra'=>true]] as $bad) {
    $rejected=false;
    try { new PeraCRM_Campaign_Retention($bad); } catch (InvalidArgumentException $e) { $rejected=true; }
    retention_expect($rejected,'invalid retention scope rejected');
}
echo "PASS retention digest tombstones and scope contracts (SQL acceptance separate)\n";
