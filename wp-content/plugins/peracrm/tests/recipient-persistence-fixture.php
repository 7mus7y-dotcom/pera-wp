<?php
/** Synthetic projections shared by standalone regressions and actual SQL tests. */
function peracrm_test_persistence_values(array $content, int $blog): array
{
    require_once dirname(__DIR__).'/inc/campaigns/recipient-persistence.php';
    $binding = ['blog_id'=>$blog,'subscriber_id'=>17001,'mailbox'=>'persistence@example.org','token_epoch'=>1,
        'attempt_key'=>hash('sha256','fixture-persistence-attempt'),'snapshot_hash'=>hash('sha256',wp_json_encode($content))];
    $tokens = new PeraCRM_CampaignRecipientTokens(['k1'=>str_repeat('synthetic-key-',4)],'k1','fixture-persistence',static fn()=>true);
    $output = (new PeraCRM_CampaignRecipientExpansion('https://example.org',$tokens))->expand($content,$binding['snapshot_hash'],$binding,1800000000);
    $fields = $output->fields();
    $data = ['version'=>1,'scope'=>['provider'=>'ses_v2','account_id'=>'123456789012','region'=>'eu-west-1','configuration_set'=>'fixture','configuration_version'=>1],
        'blog_id'=>$blog,'campaign_id'=>31,'job_id'=>41,'revision_id'=>51,'snapshot_id'=>61,'recipient_id'=>71,
        'snapshot_hash'=>$binding['snapshot_hash'],'delivery_key'=>hash('sha256','fixture-persistence-delivery'),'attempt_key'=>$binding['attempt_key'],
        'personalization_version'=>1,'mailbox'=>$binding['mailbox'],'subject'=>'Synthetic subject','sender_name'=>'Fixture',
        'sender_email'=>'sender@example.org','reply_to'=>'reply@example.org','html'=>$fields['html'],'text'=>$fields['text'],'timeout_seconds'=>20];
    return [$binding,$output,new PeraCRM_CampaignSubmissionV1($data)];
}

function peracrm_test_persistence_projection(array $content, callable $expect, int $blog): void
{
    [$binding,$output,$submission] = peracrm_test_persistence_values($content,$blog);
    $metadata = PeraCRM_CampaignRecipientPersistence::attemptMetadata($submission,$binding,$output);
    $json = json_encode($metadata);
    $expect(!str_contains($json,'token=') && !str_contains($json,'Synthetic subject') && !str_contains($json,'<html'), 'attempt metadata excludes bearer/body/subject');
    $expect($metadata['envelope_hash'] === $submission->hash() && $metadata['expanded_output_hash'] === $output->hash(), 'independent envelope and output hashes');
    $reverse = array_reverse($binding,true);
    $expect(PeraCRM_CampaignRecipientPersistence::attemptMetadata($submission,$reverse,$output) === $metadata, 'canonical identity order');
    foreach (['mailbox'=>'other@example.org','blog_id'=>$blog+1,'attempt_key'=>str_repeat('f',64),'snapshot_hash'=>str_repeat('f',64),'subscriber_id'=>17002,'token_epoch'=>2] as $key=>$value) {
        $rejected = false;
        try { PeraCRM_CampaignRecipientPersistence::attemptMetadata($submission,array_replace($binding,[$key=>$value]),$output); }
        catch (Throwable $e) { $rejected = $e->getMessage() === 'recipient_service_unavailable'; }
        $expect($rejected,'projection rejects mismatched '.$key);
    }
    foreach (['html','text'] as $part) {
        $data = $submission->fields(); $data[$part] .= 'changed'; $rejected = false;
        try { PeraCRM_CampaignRecipientPersistence::attemptMetadata(new PeraCRM_CampaignSubmissionV1($data),$binding,$output); }
        catch (Throwable $e) { $rejected = true; }
        $expect($rejected,'expanded '.$part.' must match envelope');
    }
    $store = new PeraCRM_CampaignRecipientPersistence('fixture-persistence',$blog);
    ob_start(); var_dump($store); $debug = ob_get_clean();
    $expect(!str_contains($debug,'example.org') && !str_contains($debug,'fixture-persistence'), 'store debug is redacted');
    foreach ([fn()=>serialize($store),fn()=>$store->__construct('other-deployment',$blog)] as $fn) {
        $rejected = false; try { $fn(); } catch (Throwable $e) { $rejected = true; }
        $expect($rejected,'store serialization and constructor reentry denied');
    }
}
