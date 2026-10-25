<?php
/** Synthetic pure value and mapper contracts; no HTTP client or network. */
require __DIR__.'/campaign-snapshots-test.php';
require __DIR__.'/recipient-persistence-fixture.php';
require_once dirname(__DIR__).'/inc/campaigns/providers/ses.php';
[$binding,$output,$submission]=peracrm_test_persistence_values($saved['payload']['content'],2,2);
$fields=$submission->fields(); $url=$fields['unsubscribe_url'];
$metadata=PeraCRM_CampaignRecipientPersistence::attemptMetadata($submission,$binding,$output);
campaign_expect($fields['version']===2 && $fields['personalization_version']===2,'explicit v2 envelope');
campaign_expect(!str_contains(json_encode($metadata),$url) && !str_contains(json_encode($metadata),'pc1.'),'attempt metadata excludes header bearer');
$reject=static function (callable $fn): void {
    try { $fn(); } catch (Throwable $e) {
        campaign_expect(in_array($e->getMessage(),['invalid_submission','recipient_service_unavailable','submission_serialization_disabled'],true)
            && $e->getPrevious()===null,'fixed unchained diagnostic'); return;
    }
    throw new RuntimeException('Expected header envelope rejection');
};
foreach (["\r\nBcc: victim@example.org",'http://example.org/email-unsubscribe/?v=1&token=x',
    str_replace('.unsubscribe.','.preferences.',$url),$url.'&x=1',str_replace('example.org','user@example.org',$url),
    str_replace('example.org','example.org:443',$url),str_replace('/?v=1','/?v=2',$url)] as $bad) {
    $reject(fn()=>new PeraCRM_CampaignSubmissionV1(array_replace($fields,['unsubscribe_url'=>$bad])));
}
foreach ([['version'=>3],['personalization_version'=>1],['unsubscribe_url'=>null],['extra'=>'value']] as $change) {
    $reject(fn()=>new PeraCRM_CampaignSubmissionV1(array_replace($fields,$change)));
}
$missing=$fields; unset($missing['unsubscribe_url']);
$reject(fn()=>new PeraCRM_CampaignSubmissionV1($missing));
$changed=$fields; $changed['unsubscribe_url']=str_replace('example.org','other.example.org',$url);
$changed=new PeraCRM_CampaignSubmissionV1($changed);
campaign_expect($changed->hash()!==$submission->hash(),'header URL changes immutable correlation hash');
$reject(fn()=>PeraCRM_CampaignRecipientPersistence::attemptMetadata($changed,$binding,$output));
$copy=$fields; $external=$url; $copy['unsubscribe_url']=&$external;
$immutable=new PeraCRM_CampaignSubmissionV1($copy); $external='changed';
campaign_expect($immutable->hash()===$submission->hash(),'caller-held URL reference cannot mutate envelope');
ob_start(); var_dump($submission,$output); $debug=ob_get_clean();
campaign_expect(!str_contains($debug,'pc1.') && !str_contains($debug,'example.org'),'header bearer debug redaction');
$reject(fn()=>serialize($submission));
$before=$submission->hash();
$reject(fn()=>$submission->__construct($changed->fields()));
campaign_expect($submission->hash()===$before,'constructor reentry cannot replace immutable header correlation');
$transport=new class implements PeraCRM_Campaign_SES_Transport {
    public $calls=[];
    public function send(array $request,array $options): array {
        $this->calls[]=[$request,$options];
        return ['complete'=>true,'authenticated'=>true,'status'=>200,'message_id'=>'fixture-v2'];
    }
};
$result=(new PeraCRM_Campaign_SES_Mapper($fields['scope'],$transport))->submit($submission);
[$request,$options]=$transport->calls[0];
campaign_expect($result['kind']==='accepted' && count($transport->calls)===1,'one mocked v2 submission');
campaign_expect($request['Content']['Simple']['Headers']===[
    ['Name'=>'List-Unsubscribe','Value'=>'<'.$url.'>'],
    ['Name'=>'List-Unsubscribe-Post','Value'=>'List-Unsubscribe=One-Click'],
],'exact RFC 8058 headers from envelope');
campaign_expect(!isset($request['ListManagementOptions']) && !isset($request['Content']['Template'])
    && $request['Content']['Simple']['Body']['Html']['Data']===$fields['html']
    && $request['Content']['Simple']['Body']['Text']['Data']===$fields['text'],'no managed unsubscribe or body rewrite');
campaign_expect($request['EmailTags'][3]['Value']===$submission->hash(),'correlation binds header URL');
[$legacyBinding,$legacyOutput,$legacy]=peracrm_test_persistence_values($saved['payload']['content'],2);
campaign_expect($legacy->fields()['version']===1 && !isset($legacyOutput->fields()['unsubscribe_url']),'historical v1 shape preserved');
$reject(fn()=>new PeraCRM_CampaignSubmissionV1($legacy->fields()+['unsubscribe_url'=>$url]));
echo "PASS versioned unsubscribe envelope, exact headers, binding and redaction\n";
