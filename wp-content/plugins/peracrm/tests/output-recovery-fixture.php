<?php
/** Synthetic stable token-bearing bytes for independent-process fixtures. */
require_once dirname(__DIR__).'/inc/campaigns/recipient-persistence.php';
function output_fixture(int $blog, string $scenario = 'restart'): array
{
    $binding = ['blog_id'=>$blog,'subscriber_id'=>19001,'mailbox'=>'output@example.org','token_epoch'=>1,
        'attempt_key'=>hash('sha256','output-'.$scenario),'snapshot_hash'=>hash('sha256','output-snapshot')];
    $html = "<p>Exact café &amp; 東京</p>\r\n<a href=\"https://example.org/email-unsubscribe/?v=1&amp;token=synthetic%2Bbearer\">Withdraw</a>\n";
    $text = "Exact café 東京\r\nhttps://example.org/email-unsubscribe/?v=1&token=synthetic%2Bbearer\n\n";
    $output = new PeraCRM_CampaignRecipientOutput($html,$text,$binding['snapshot_hash'],hash('sha256',peracrm_campaign_recipient_binding($binding)));
    $submission = new PeraCRM_CampaignSubmissionV1(['version'=>1,'scope'=>['provider'=>'ses_v2','account_id'=>'123456789012','region'=>'eu-west-1','configuration_set'=>'fixture','configuration_version'=>1],
        'blog_id'=>$blog,'campaign_id'=>31,'job_id'=>41,'revision_id'=>51,'snapshot_id'=>61,'recipient_id'=>71,
        'snapshot_hash'=>$binding['snapshot_hash'],'delivery_key'=>hash('sha256','output-delivery'),'attempt_key'=>$binding['attempt_key'],
        'personalization_version'=>1,'mailbox'=>$binding['mailbox'],'subject'=>'Exact café','sender_name'=>'Fixture',
        'sender_email'=>'sender@example.org','reply_to'=>'reply@example.org','html'=>$html,'text'=>$text,'timeout_seconds'=>20]);
    return [$binding,$output,$submission,PeraCRM_CampaignRecipientPersistence::attemptMetadata($submission,$binding,$output)];
}
function output_cipher(bool $rotated = false): PeraCRM_CampaignOutputCipher
{
    return new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('a',32),'e2'=>str_repeat('b',32)],$rotated?'e2':'e1');
}
