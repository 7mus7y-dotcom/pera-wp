<?php
require __DIR__.'/campaign-snapshots-test.php';
require __DIR__.'/recipient-persistence-fixture.php';
peracrm_test_persistence_projection($saved['payload']['content'],'campaign_expect',2);
echo "PASS recipient persistence projections and redaction (SQL exercised separately)\n";

[$binding]=peracrm_test_persistence_values($saved['payload']['content'],2);
$registered=[];
$issuer=new PeraCRM_CampaignRecipientTokens(['k1'=>str_repeat('s',32)],'k1','fixture-atomic',static fn()=>false,
    static function ($digest,$purpose,$actual,$protocol) use (&$registered) { $registered[]=[$digest,$purpose,$actual,$protocol]; });
$token=$issuer->issue('preferences',$binding,1800000000);
campaign_expect($registered===[[hash('sha256',$token),'preferences',$binding,
    ['protocol'=>'pc1','deployment'=>'fixture-atomic','key_id'=>'k1','issued'=>1800000000,'expires'=>1800086400]]],
    'issuance registrar receives actual digest and exact authenticated metadata');
$issuer=new PeraCRM_CampaignRecipientTokens(['k1'=>str_repeat('s',32)],'k1','fixture-atomic',static fn()=>false,
    static function () { throw new RuntimeException('fixture-registration-failed'); });
$failed=false;
try { $issuer->issue('unsubscribe',$binding,1800000000); } catch (Throwable $e) { $failed=true; }
campaign_expect($failed,'failed registration cannot return bearer');
