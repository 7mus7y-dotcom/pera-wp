<?php
require_once __DIR__.'/ses-matching-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/event-suppression.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/submission-response.php';
function suppress_service(array $v): PeraCRM_SES_Suppression
{
    return new PeraCRM_SES_Suppression(sns_verifier($v),$v['deployment'],get_current_blog_id(),ses_event_scope());
}
function suppress_event(array $seed,string $type,string $subtype=''): array
{
    $event=ses_event($type); $event['mail']=$seed['event']['mail']; $mailbox=$seed['binding']['mailbox'];
    $field=['Bounce'=>'bouncedRecipients','Complaint'=>'complainedRecipients','Delivery'=>'recipients','DeliveryDelay'=>'delayedRecipients'][$type]??null;
    if ($field) { $event[lcfirst($type)][$field]=$type==='Delivery'?[$mailbox]:[['emailAddress'=>$mailbox]]; }
    if ($type==='Bounce' && $subtype!=='') { $event['bounce']['bounceType']=$subtype; }
    if ($type==='Complaint' && $subtype!=='') { $event['complaint']['complaintFeedbackType']=$subtype; }
    return $event;
}
function suppress_receive(array $v,array $event,int $number,bool $match=true): string
{
    $id=ses_match_receive($v,$v['deployment'],$event,$number);
    if ($match) { (new PeraCRM_SES_Matcher(sns_verifier($v),$v['deployment'],get_current_blog_id(),ses_event_scope()))->match($id); }
    return $id;
}
function suppress_rows(string $table): array
{
    global $wpdb;
    return $wpdb->get_results('SELECT * FROM '.peracrm_table($table).' ORDER BY 1,2,3',ARRAY_A);
}
function suppress_state(): string
{
    $out=[];
    foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_campaign_recipient_tokens',
        'peracrm_campaign_recipient_identities','peracrm_campaign_submission_attempts','peracrm_campaign_submission_outputs',
        'peracrm_campaign_deliveries','peracrm_campaign_queue_jobs','peracrm_campaign_worker_events',
        'peracrm_campaign_sns_receipts','peracrm_campaign_ses_normalizations','peracrm_campaign_ses_events',
        'peracrm_campaign_ses_matches','peracrm_campaign_provider_messages','peracrm_campaign_acceptance_evidence'] as $table) {
        $out[$table]=suppress_rows($table);
    }
    return hash('sha256',wp_json_encode($out));
}
function suppress_atomic(): string
{
    $out=[suppress_state()];
    foreach (['peracrm_email_suppressions','peracrm_email_suppression_events',
        'peracrm_campaign_ses_applications','peracrm_campaign_ses_application_receipts'] as $table) { $out[$table]=suppress_rows($table); }
    return hash('sha256',wp_json_encode($out));
}
function suppress_apply(array $v,string $id,string $expected='applied'): void
{
    $before=suppress_state();
    sns_expect(array_values(suppress_service($v)->apply($id))===[$expected],'suppression result '.$expected);
    sns_expect(suppress_state()===$before,'no consent grants, clearing, subscriber creation, retry/job/identity or provenance changes');
}
