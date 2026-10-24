<?php
require_once __DIR__.'/sns-fixture.php';
require_once __DIR__.'/../inc/campaigns/event-normalization.php';
function ses_event_scope(): array
{
    return ['provider'=>'ses_v2','account_id'=>'123456789012','region'=>'eu-west-1','configuration_set'=>'fixture','configuration_version'=>1];
}
function ses_event(string $type='Send'): array
{
    $mail=['messageId'=>'synthetic-ses-message','sendingAccountId'=>'123456789012',
        'timestamp'=>'2026-10-24T12:00:00.000Z','destination'=>['one@example.test','two@example.test'],
        'tags'=>['ses:configuration-set'=>['fixture'],'peracrm_delivery'=>[str_repeat('a',64)],
            'peracrm_attempt'=>[str_repeat('b',64)],'peracrm_snapshot'=>[str_repeat('c',64)],'peracrm_envelope'=>[str_repeat('d',64)]]];
    $detail=['timestamp'=>'2026-10-24T12:00:01Z'];
    if ($type==='Send') { $detail=(object)[]; }
    if ($type==='Delivery') { $detail['recipients']=$mail['destination']; }
    if ($type==='Bounce') { $detail+=['bounceType'=>'Permanent','bounceSubType'=>'General','feedbackId'=>'feedback-1',
        'bouncedRecipients'=>[['emailAddress'=>'one@example.test'],['emailAddress'=>'two@example.test']]]; }
    if ($type==='Complaint') { $detail+=['feedbackId'=>'feedback-1','complaintFeedbackType'=>'abuse',
        'complainedRecipients'=>[['emailAddress'=>'one@example.test'],['emailAddress'=>'two@example.test']]]; }
    if ($type==='DeliveryDelay') { $detail+=['delayType'=>'MailboxFull',
        'delayedRecipients'=>[['emailAddress'=>'one@example.test'],['emailAddress'=>'two@example.test']]]; }
    return ['eventType'=>$type,'mail'=>$mail,lcfirst($type)=>$detail];
}
function ses_event_json(array $event): string { return json_encode($event,JSON_THROW_ON_ERROR); }
function ses_event_body(array $v, array $event, string $id): string
{
    return sns_body($v,['MessageId'=>$id,'Message'=>ses_event_json($event)]);
}
function ses_event_id(int $number): string { return sprintf('%08x-1234-1234-1234-123456789abc',$number); }
function ses_event_reject(callable $fn,string $reason): void
{
    try { $fn(); }
    catch (PeraCRM_SES_Event_Error $error) { sns_expect($error->getMessage()===$reason && $error->getPrevious()===null,$reason); return; }
    throw new RuntimeException('FAIL expected SES '.$reason);
}
