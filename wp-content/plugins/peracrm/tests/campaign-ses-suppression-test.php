<?php
require __DIR__.'/campaign-ses-matching-test.php';
require __DIR__.'/../inc/campaigns/event-suppression.php';
foreach (['Permanent'=>'hard_bounce','Transient'=>null,'Undetermined'=>null] as $bounce=>$reason) {
    $event=ses_event('Bounce'); $event['bounce']['bounceType']=$bounce;
    foreach ((new PeraCRM_SES_Event_Normalizer(ses_event_scope()))->normalize(ses_event_json($event)) as $item) {
        campaign_expect(PeraCRM_SES_Suppression::reason(json_decode($item['json'],true))===$reason,'suppression policy '.$bounce);
    }
}
foreach (['abuse','auth-failure','fraud','other','virus','unspecified','not-spam','future-type'] as $subtype) {
    $event=ses_event('Complaint');
    if ($subtype==='unspecified') { unset($event['complaint']['complaintFeedbackType']); }
    else { $event['complaint']['complaintFeedbackType']=$subtype; }
    $fact=json_decode((new PeraCRM_SES_Event_Normalizer(ses_event_scope()))->normalize(ses_event_json($event))[0]['json'],true);
    campaign_expect($fact['recipient_is_candidate'] && PeraCRM_SES_Suppression::reason($fact)===(in_array($subtype,['not-spam','future-type'],true)?null:'complaint'),'complaint policy '.$subtype);
}
foreach (['Send','Delivery','DeliveryDelay'] as $type) {
    $fact=json_decode((new PeraCRM_SES_Event_Normalizer(ses_event_scope()))->normalize(ses_event_json(ses_event($type)))[0]['json'],true);
    campaign_expect(PeraCRM_SES_Suppression::reason($fact)===null,'no permanent denial or retry from '.$type);
}
$service=new PeraCRM_SES_Suppression(sns_verifier(sns_values()),'fixture',2,ses_event_scope());
foreach ([fn()=>serialize($service),fn()=>$service->__construct(sns_verifier(sns_values()),'other',2,ses_event_scope()),fn()=>$service->apply('{}')] as $operation) {
    $denied=false; try { $operation(); } catch (Throwable $e) { $denied=true; }
    campaign_expect($denied,'no serialization, reentry or raw JSON authority');
}
echo "PASS SES suppression policy\n";
