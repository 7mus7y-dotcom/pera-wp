<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/ses-events-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/tests/recipient-persistence-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/event-matching.php';
function ses_match_seed(string $deployment,int $number,bool $persist=true): array
{
    $content=get_option('atomic_preparation_content'); $blog=get_current_blog_id();
    [$binding,,$original]=peracrm_test_persistence_values($content,$blog);
    $binding['subscriber_id']=26000+$number; $binding['mailbox']='matching-'.$number.'@example.test';
    $binding['attempt_key']=hash('sha256','matching-'.$deployment.'-'.$number);
    $tokens=new PeraCRM_CampaignRecipientTokens(['k1'=>str_repeat('synthetic-match-',3)],'k1',$deployment,static fn()=>true);
    $output=(new PeraCRM_CampaignRecipientExpansion('https://example.test',$tokens))->expand($content,$binding['snapshot_hash'],$binding,1800000000);
    $fields=$original->fields();
    foreach (['blog_id','mailbox','attempt_key','snapshot_hash'] as $key) { $fields[$key]=$binding[$key]; }
    $fields['delivery_key']=hash('sha256','delivery-'.$deployment.'-'.$number);
    foreach (['html','text'] as $key) { $fields[$key]=$output->fields()[$key]; }
    $submission=new PeraCRM_CampaignSubmissionV1($fields);
    $metadata=PeraCRM_CampaignRecipientPersistence::attemptMetadata($submission,$binding,$output);
    if ($persist) { ses_match_persist($deployment,$binding,$submission,$output); }
    $event=ses_event(); $event['mail']['destination']=[$binding['mailbox']];
    $event['mail']['messageId']='matching-message-'.$number;
    foreach (['delivery'=>'delivery_key','attempt'=>'attempt_key','snapshot'=>'snapshot_hash','envelope'=>'envelope_hash'] as $tag=>$field) {
        $event['mail']['tags']['peracrm_'.$tag]=[$metadata[$field]];
    }
    return ['binding'=>$binding,'submission'=>$submission,'output'=>$output,'metadata'=>$metadata,'event'=>$event];
}
function ses_match_persist(string $deployment,array $binding,PeraCRM_CampaignSubmissionV1 $submission,PeraCRM_CampaignRecipientOutput $output): void
{
    peracrm_test_persistence_subscriber($binding['subscriber_id'],$binding['mailbox']);
    $store=new PeraCRM_CampaignRecipientPersistence($deployment,get_current_blog_id());
    $store->ensureIdentity($binding['subscriber_id'],$binding['mailbox']);
    $store->bindAttempt($submission,$binding,$output);
}
function ses_match_receive(array $v,string $deployment,array $event,int $number): string
{
    $id=ses_event_id($number); $verifier=sns_verifier($v); $blog=get_current_blog_id();
    (new PeraCRM_SNS_Receipts($verifier,$deployment,$blog))->receive(ses_event_body($v,$event,$id),$v['now']);
    sns_expect((new PeraCRM_SES_Quarantine($verifier,$deployment,$blog,ses_event_scope()))->normalize($id)==='matching_pending','authenticated normalization committed');
    return $id;
}
function ses_match_snapshot(): string
{
    global $wpdb; $out=[];
    foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events',
        'peracrm_campaign_recipient_tokens','peracrm_campaign_recipient_identities','peracrm_campaign_submission_attempts',
        'peracrm_campaign_submission_outputs','peracrm_campaign_deliveries','peracrm_campaign_queue_jobs','peracrm_campaign_worker_events'] as $table) {
        $out[$table]=$wpdb->get_results('SELECT * FROM '.peracrm_table($table).' ORDER BY 1,2,3',ARRAY_A);
    }
    return hash('sha256',wp_json_encode($out));
}
function ses_match_check(callable $operation,string $expected): void
{
    $before=ses_match_snapshot(); $result=$operation();
    sns_expect(count($result)===1 && array_values($result)===[$expected],'correlation '.$expected);
    sns_expect($before===ses_match_snapshot(),'permissions attempts suppression and worker states unchanged');
}
