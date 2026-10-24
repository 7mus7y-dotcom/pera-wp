<?php
require __DIR__.'/campaign-recipient-persistence-test.php';
require __DIR__.'/ses-events-fixture.php';
require __DIR__.'/../inc/campaigns/event-matching.php';
[$binding,$output,$submission]=peracrm_test_persistence_values($saved['payload']['content'],2);
$metadata=PeraCRM_CampaignRecipientPersistence::attemptMetadata($submission,$binding,$output);
$event=ses_event(); $event['mail']['destination']=[$binding['mailbox']];
foreach (['delivery'=>'delivery_key','attempt'=>'attempt_key','snapshot'=>'snapshot_hash','envelope'=>'envelope_hash'] as $tag=>$field) {
    $event['mail']['tags']['peracrm_'.$tag]=[$metadata[$field]];
}
$fact=json_decode((new PeraCRM_SES_Event_Normalizer(ses_event_scope()))->normalize(ses_event_json($event))[0]['json'],true);
campaign_expect(PeraCRM_SES_Matcher::bindingDecision($fact,$metadata,ses_event_scope(),2)==='matched','exact immutable scope tags hashes recipient');
foreach (['account_id','region','configuration_set','configuration_version'] as $key) {
    $wrong=$metadata; $wrong['scope'][$key]='wrong';
    campaign_expect(PeraCRM_SES_Matcher::bindingDecision($fact,$wrong,ses_event_scope(),2)==='wrong_scope','wrong immutable '.$key);
}
foreach (['delivery_key','attempt_key','snapshot_hash','envelope_hash'] as $key) {
    $wrong=$metadata; $wrong[$key]=str_repeat('0',64);
    campaign_expect(PeraCRM_SES_Matcher::bindingDecision($fact,$wrong,ses_event_scope(),2)==='binding_conflict','wrong immutable '.$key);
}
foreach (['recipient','destinations'] as $key) {
    $wrong=$fact; $wrong[$key]=$key==='recipient'?'other@example.test':[$binding['mailbox'],'other@example.test'];
    campaign_expect(PeraCRM_SES_Matcher::bindingDecision($wrong,$metadata,ses_event_scope(),2)==='wrong_recipient','single recipient envelope required '.$key);
}
campaign_expect(PeraCRM_SES_Matcher::bindingDecision($fact,$metadata,ses_event_scope(),1)==='binding_conflict','wrong immutable blog');
$source=file_get_contents(__DIR__.'/../inc/campaigns/event-matching.php');
campaign_expect(!str_contains($source,'->submit(') && !str_contains($source,'->update(') && !str_contains($source,'wp_schedule'), 'correlation has no send/state/cron mutation surface');
echo "PASS SES immutable matching projections\n";
