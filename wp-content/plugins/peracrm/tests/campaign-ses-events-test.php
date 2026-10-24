<?php
define('ABSPATH',__DIR__);
require __DIR__.'/ses-events-fixture.php';
$normalizer=new PeraCRM_SES_Event_Normalizer(ses_event_scope());
foreach (['Send','Delivery','Bounce','Complaint','DeliveryDelay'] as $type) {
    $event=ses_event($type); $facts=$normalizer->normalize(ses_event_json($event));
    sns_expect(count($facts)===2,'recipient-specific '.$type);
    $event['newField']=['harmless'=>true]; $event['mail']['tags']['new-tag']=['ignored'];
    $event['mail']['destination']=array_reverse($event['mail']['destination']);
    if ($type==='Delivery') { $event['delivery']['recipients']=array_reverse($event['delivery']['recipients']); }
    sns_expect($normalizer->normalize(ses_event_json($event))===$facts,'additive fields and order stable '.$type);
    unset($event['eventType']); $event['notificationType']=$type;
    sns_expect($normalizer->normalize(ses_event_json($event))===$facts,'legacy type alias '.$type);
}
foreach (['Transient','Undetermined','Permanent'] as $bounce) {
    $e=ses_event('Bounce'); $e['bounce']['bounceType']=$bounce;
    $facts=$normalizer->normalize(ses_event_json($e));
    sns_expect(json_decode($facts[0]['json'],true)['bounce_type']===$bounce,'bounce distinction '.$bounce);
}
$e=ses_event('Complaint'); $e['complaint']['complaintFeedbackType']='not-spam';
$fact=json_decode($normalizer->normalize(ses_event_json($e))[0]['json'],true);
sns_expect($fact['subtype']==='not-spam' && $fact['recipient_is_candidate']===true,'not-spam and candidate identity retained');
sns_expect($fact['destinations']===['one@example.test','two@example.test'],'full send destination set retained for future matching');
$e['complaint']['complaintFeedbackType']=null;
ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'invalid_event');
$e=ses_event(); $e['notificationType']='Send'; $e['eventType']=null;
ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'invalid_event');
$e=ses_event('Delivery'); $fact=json_decode($normalizer->normalize(ses_event_json($e))[0]['json'],true);
sns_expect($fact['type']==='Delivery' && !isset($fact['inbox_placement'],$fact['retry']),'delivery fact grants no placement/retry assertion');
$e=ses_event(); $e['notificationType']='Bounce';
ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'invalid_event');
$e=ses_event(); $e['mail']['sendingAccountId']='999999999999';
ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'wrong_scope');
$e=ses_event(); $e['mail']['tags']['ses:configuration-set']=['other'];
ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'wrong_scope');
foreach (['peracrm_attempt','peracrm_delivery','peracrm_snapshot','peracrm_envelope'] as $tag) {
    $e=ses_event(); $e['mail']['tags'][$tag]=[str_repeat('a',64),str_repeat('b',64)];
    ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'missing_tags');
    $e['mail']['tags'][$tag]=['bad'];
    ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'invalid_tags');
}
$e=ses_event('Bounce'); $e['bounce']['bouncedRecipients'][0]['emailAddress']='other@example.test';
ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'wrong_recipient');
foreach (['Open','Click','Subscription','Reject','Rendering Failure'] as $type) {
    $e=ses_event(); $e['eventType']=$type;
    ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'unsupported_event');
}
foreach (['null','[]','{',str_repeat('x',262145),str_repeat('{"x":',20).'null'.str_repeat('}',20)] as $bytes) {
    ses_event_reject(fn()=>$normalizer->normalize($bytes),'invalid_event');
}
foreach (['2026-02-30T12:00:00Z','2026-10-24T25:00:00Z','2026-10-24T12:00:00+01:00',123] as $time) {
    $e=ses_event(); $e['mail']['timestamp']=$time;
    ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'invalid_event');
}
foreach ([[],array_fill(0,51,'one@example.test'),['one@example.test','ONE@example.test'],[' one@example.test']] as $recipients) {
    $e=ses_event(); $e['mail']['destination']=$recipients;
    ses_event_reject(fn()=>$normalizer->normalize(ses_event_json($e)),'invalid_event');
}
$e=ses_event('Bounce'); $before=$normalizer->normalize(ses_event_json($e)); $e['bounce']['bounceType']='Transient';
$after=$normalizer->normalize(ses_event_json($e));
sns_expect($before[0]['key']===$after[0]['key'] && $before[0]['json']!==$after[0]['json'],'changed feedback identity is detectable conflict');
$e=ses_event(); $e['mail']['timestamp']='2026-10-24T12:00:00Z'; $before=$normalizer->normalize(ses_event_json($e));
$e['mail']['timestamp']='2026-10-24T12:00:00.000000Z';
sns_expect($before===$normalizer->normalize(ses_event_json($e)),'timestamp precision canonicalized');
echo "PASS bounded SES normalization; no HTTP or application authority\n";
