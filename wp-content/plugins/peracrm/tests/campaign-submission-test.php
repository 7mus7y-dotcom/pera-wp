<?php
/** Standalone synthetic contracts. No WordPress DB, network or provider account. */
define('ABSPATH', __DIR__.'/');
$root = dirname(__DIR__);
require $root.'/inc/campaigns/providers/ses.php';
function submission_expect($ok, $label) {
    if (!$ok) { throw new RuntimeException('FAIL '.$label); }
}
function submission_reject(callable $fn) {
    try { $fn(); } catch (Throwable $e) {
        submission_expect(in_array($e->getMessage(), ['invalid_submission','frozen_content_unavailable','submission_serialization_disabled'], true)
            && $e->getPrevious() === null, 'fixed unchained diagnostic'); return;
    }
    throw new RuntimeException('FAIL expected rejection');
}
$scope=['provider'=>'ses_v2','account_id'=>'123456789012','region'=>'eu-west-1','configuration_set'=>'fixture','configuration_version'=>1];
$content=['schema'=>'delivery_content_v1','renderer_version'=>'fixture-only',
    'subject'=>'İstanbul news','sender_name'=>'Pera "CRM"','sender_email'=>'news@example.test',
    'reply_to'=>'reply@example.test','html'=>'<p>Stored HTML &amp; content</p>','text'=>'Stored text & content'];
$binding=['blog_id'=>2,'campaign_id'=>3,'job_id'=>4,'revision_id'=>5,'snapshot_id'=>6,'recipient_id'=>7,
    'snapshot_hash'=>hash('sha256',json_encode($content, JSON_THROW_ON_ERROR)), 'mailbox'=>'frozen@example.test',
    'delivery_key'=>str_repeat('a',64),'attempt_key'=>str_repeat('b',64)];
$common=array_intersect_key($binding,array_flip(['blog_id','campaign_id','snapshot_id','snapshot_hash']));
$rows=['job'=>$common+['id'=>4,'complete'=>true,'state'=>'queued','revision_id'=>5,'preparation_id'=>8],
    'revision'=>$common+['id'=>5,'published'=>true,'preparation_id'=>8],
    'preparation'=>$common+['id'=>8,'complete'=>true],
    'recipient'=>['id'=>7,'preparation_id'=>8,'mailbox'=>'frozen@example.test','subscriber_id'=>9],
    'snapshot'=>$common+['id'=>6,'purpose'=>'deliverable','content'=>$content], 'delivery'=>$binding+['state'=>'inflight','subscriber_id'=>9]];
class SubmissionFixtureResolver extends PeraCRM_Campaign_Frozen_Content_Resolver {
    public $rows; public $allowed=true; public $reads=0;
    protected function authorize(array $binding): void { if (!$this->allowed) { throw new RuntimeException('secret bearer@example.test'); } }
    protected function load(array $binding): array { $this->reads++; return $this->rows; }
    protected function supportsRenderer(string $version): bool { return $version === 'fixture-only'; }
}
$resolver=new SubmissionFixtureResolver(); $resolver->rows=$rows;
$envelope=$resolver->resolve($binding,$scope);
// Scheduled jobs retain their state when due; authorization owns the due-time gate.
$resolver->rows['job']['state']='scheduled';
submission_expect($resolver->resolve($binding,$scope)->hash()===$envelope->hash(), 'authorized scheduled job resolves identical frozen content');
$resolver->allowed=false; $reads=$resolver->reads;
submission_reject(fn()=>$resolver->resolve($binding,$scope));
submission_expect($resolver->reads===$reads, 'scheduled job cannot bypass worker authorization or due-time gate');
$resolver->allowed=true;
foreach (['paused','cancelled','preparing','failed','completed','unknown','',null,1] as $state) {
    $resolver->rows['job']['state']=$state;
    submission_reject(fn()=>$resolver->resolve($binding,$scope));
}
$resolver->rows=$rows;
$data=$envelope->fields(); $before=$envelope->hash();
$data['mailbox']='changed@example.test'; $data['scope']['account_id']='000000000000';
submission_expect($envelope->hash()===$before && $envelope->fields()['mailbox']===$binding['mailbox'], 'immutable copy and stable hash');
submission_expect($resolver->rows===$rows,'no source mutation');
submission_reject(fn()=>serialize($envelope));
ob_start(); var_dump($envelope); $debug=ob_get_clean();
submission_expect(!str_contains($debug,'example.test') && !str_contains($debug,'Stored'), 'debug redaction');
$resolver->allowed=false; $reads=$resolver->reads;
submission_reject(fn()=>$resolver->resolve($binding,$scope));
submission_expect($resolver->reads===$reads,'authority before reads'); $resolver->allowed=true;
foreach (['blog_id'=>9,'campaign_id'=>9,'job_id'=>9,'revision_id'=>9,'snapshot_id'=>9,'recipient_id'=>9,
    'snapshot_hash'=>str_repeat('c',64),'mailbox'=>'other@example.test','delivery_key'=>str_repeat('c',64),'attempt_key'=>str_repeat('c',64)] as $key=>$value) {
    submission_reject(fn()=>$resolver->resolve(array_replace($binding,[$key=>$value]),$scope));
}
foreach ([['job','complete',false],['job','state','cancelled'],['job','revision_id',9],['job','preparation_id',9],
    ['revision','published',false],['revision','preparation_id',9],['revision','snapshot_hash',str_repeat('c',64)],
    ['preparation','complete',false],['preparation','snapshot_id',9],['recipient','preparation_id',9],['recipient','mailbox','new@example.test'],
    ['recipient','subscriber_id',10],['delivery','state','claimed'],
    ['snapshot','purpose','unpublished_content'],['snapshot','content',['schema_version'=>1]],
    ['snapshot','content',array_replace($content,['renderer_version'=>'unreviewed'])],
    ['snapshot','content',array_replace($content,['text'=>'tampered'])]] as [$row,$key,$value]) {
    $resolver->rows=$rows; $resolver->rows[$row][$key]=$value;
    submission_reject(fn()=>$resolver->resolve($binding,$scope));
}
$resolver->rows=$rows;
class SubmissionDefaultDenyResolver extends PeraCRM_Campaign_Frozen_Content_Resolver {
    public $rows;
    protected function authorize(array $binding): void {}
    protected function load(array $binding): array { return $this->rows; }
}
$deniedRenderer=new SubmissionDefaultDenyResolver(); $deniedRenderer->rows=$rows;
submission_reject(fn()=>$deniedRenderer->resolve($binding,$scope));
// Even correctly hashed v1 review bytes are never promoted to deliverable input.
$review=['schema_version'=>1,'output'=>['html'=>'review','text'=>'review']];
$resolver->rows['snapshot']['content']=$review;
$reviewBinding=$binding; $reviewBinding['snapshot_hash']=hash('sha256',json_encode($review));
foreach (['job','revision','snapshot','delivery','preparation'] as $key) { $resolver->rows[$key]['snapshot_hash']=$reviewBinding['snapshot_hash']; }
submission_reject(fn()=>$resolver->resolve($reviewBinding,$scope)); $resolver->rows=$rows;
$valid=$envelope->fields();
$referenced=$valid; $external='fixture'; $referenced['scope']['configuration_set']=&$external;
$immutableReference=new PeraCRM_CampaignSubmissionV1($referenced); $referenceHash=$immutableReference->hash(); $external='changed';
submission_expect($immutableReference->hash()===$referenceHash,'caller-held nested reference cannot mutate envelope');
$reordered=$valid; $reordered['scope']=array_reverse($scope,true);
submission_expect((new PeraCRM_CampaignSubmissionV1(array_reverse($reordered,true)))->hash()===$before,'canonical envelope and scope order');
foreach (['version'=>2,'blog_id'=>'2','attempt_key'=>'bad','timeout_seconds'=>21,'personalization_version'=>2,
    'mailbox'=>['a@example.test','b@example.test'],'sender_email'=>"x@example.test\r\nBcc: victim@example.test",
    'reply_to'=>'Display <reply@example.test>','subject'=>"bad\r\nheader",'sender_name'=>"bad\0header",
    'html'=>'{{unknown}}','text'=>"bad\xff",'scope'=>array_replace($scope,['region'=>'attacker.example'])] as $key=>$value) {
    submission_reject(fn()=>new PeraCRM_CampaignSubmissionV1(array_replace($valid,[$key=>$value])));
}
submission_reject(fn()=>new PeraCRM_CampaignSubmissionV1($valid+['cc'=>'victim@example.test']));
submission_reject(fn()=>new PeraCRM_CampaignSubmissionV1(array_replace($valid,['html'=>str_repeat('x',1048577)])));
submission_reject(fn()=>new PeraCRM_CampaignSubmissionV1(array_replace($valid,['html'=>str_repeat('x',1048576),'text'=>str_repeat('x',1048576)])));
class SubmissionFixtureTransport implements PeraCRM_Campaign_SES_Transport {
    public $calls=0; public $request; public $options; public $response; public $throws=false;
    public function send(array $request,array $options): array {
        $this->calls++; $this->request=$request; $this->options=$options;
        if ($this->throws) { throw new RuntimeException('credential secret token@example.test'); }
        return $this->response;
    }
}
$transport=new SubmissionFixtureTransport(); $mapper=new PeraCRM_Campaign_SES_Mapper($scope,$transport);
$complete=['complete'=>true,'authenticated'=>true];
$transport->response=$complete+['status'=>200,'message_id'=>'ses-id_123'];
submission_expect($mapper->submit($envelope)===['kind'=>'accepted','message_id'=>'ses-id_123','code'=>'provider_accepted'],'acceptance only');
$request=$transport->request;
submission_expect($request['Destination']===['ToAddresses'=>['frozen@example.test']], 'exact single frozen recipient');
submission_expect($request['Content']['Simple']===['Subject'=>['Data'=>$content['subject'],'Charset'=>'UTF-8'],
    'Body'=>['Html'=>['Data'=>$content['html'],'Charset'=>'UTF-8'],'Text'=>['Data'=>$content['text'],'Charset'=>'UTF-8']]], 'stored UTF8 content mapping');
submission_expect($request['FromEmailAddress']==='"Pera \\"CRM\\"" <news@example.test>' && $request['ReplyToAddresses']===['reply@example.test'], 'captured sender and reply-to');
submission_expect($request['ConfigurationSetName']==='fixture' && array_column($request['EmailTags'],'Value','Name')===
    ['peracrm_delivery'=>$binding['delivery_key'],'peracrm_attempt'=>$binding['attempt_key'],'peracrm_snapshot'=>$binding['snapshot_hash'],'peracrm_envelope'=>$before], 'fixed correlation tags');
submission_expect($transport->options['retries']===0 && $transport->options['http']===['connect_timeout'=>3,'timeout'=>20,'verify'=>true,'allow_redirects'=>false]
    && $transport->options['endpoint']==='https://email.eu-west-1.amazonaws.com' && $transport->options['account_id']===$scope['account_id'], 'explicit fixed transport options');
$unicodeName=str_repeat('İ',80);
$mapper->submit(new PeraCRM_CampaignSubmissionV1(array_replace($valid,['sender_name'=>$unicodeName])));
$words=explode(' ',explode(' <',$transport->request['FromEmailAddress'])[0]); $decoded='';
foreach ($words as $word) {
    submission_expect(strlen($word)<=75 && preg_match('/\A=\?UTF-8\?B\?(.+)\?=\z/',$word,$match), 'bounded encoded sender words');
    $decoded.=base64_decode($match[1]);
}
submission_expect($decoded===$unicodeName,'UTF8 sender preserved');
foreach ([['status'=>429,'error_code'=>'TooManyRequestsException'],['status'=>400,'error_code'=>'LimitExceededException'],
    ['status'=>400,'error_code'=>'ThrottlingException']] as $response) {
    $transport->response=$complete+$response+['body'=>'secret bearer@example.test','retry_after'=>999999]; $calls=$transport->calls;
    submission_expect($mapper->submit($envelope)===['kind'=>'transient','code'=>'provider_rate_limit','retry_after'=>60,'readiness_blocked'=>true]
        && $transport->calls===$calls+1, 'known nonacceptance no submission retry and redacted backoff');
}
foreach (['BadRequestException'=>400,'MessageRejected'=>400,'MailFromDomainNotVerifiedException'=>400,'AccessDeniedException'=>403,'AccountSuspendedException'=>400,'SendingPausedException'=>400,'NotFoundException'=>404] as $code=>$status) {
    $transport->response=$complete+['status'=>$status,'error_code'=>$code,'body'=>'secret'];
    submission_expect($mapper->submit($envelope)===['kind'=>'permanent','code'=>'provider_rejected','readiness_blocked'=>true], 'documented rejection '.$code);
}
foreach ([[],['status'=>200],['status'=>200,'message_id'=>''],['status'=>200,'message_id'=>str_repeat('a',192)],
    ['status'=>200,'message_id'=>"id\r\nsecret"],['status'=>500,'error_code'=>'MessageRejected'],
    ['status'=>429],['status'=>400,'error_code'=>'unrecognized'],['status'=>'200','message_id'=>'id'],
    ['status'=>400,'error_code'=>'MessageRejected','message_id'=>'id']] as $response) {
    $transport->response=$complete+$response+['body'=>'secret bearer@example.test']; $calls=$transport->calls;
    submission_expect($mapper->submit($envelope)===['kind'=>'unknown','code'=>'ambiguous_submission'] && $transport->calls===$calls+1, 'ambiguous hold and redaction');
}
foreach (['complete','authenticated'] as $flag) {
    $transport->response=array_replace($complete,[$flag=>false])+['status'=>429,'error_code'=>'TooManyRequestsException'];
    submission_expect($mapper->submit($envelope)['kind']==='unknown','proxy/truncated rejection held');
}
// The common SES throttling error proves non-acceptance only with its exact trusted pair.
foreach ([
    ['status'=>400,'error_code'=>'ThrottlingException','complete'=>false],
    ['status'=>400,'error_code'=>'ThrottlingException','authenticated'=>false],
    ['status'=>400,'error_code'=>'ThrottlingException','complete'=>1],
    ['status'=>400,'error_code'=>'ThrottlingException','authenticated'=>1],
    ['status'=>429,'error_code'=>'ThrottlingException'],
    ['status'=>500,'error_code'=>'ThrottlingException'],
    ['status'=>'400','error_code'=>'ThrottlingException'],
    ['status'=>400,'error_code'=>'ThrottlingException','message_id'=>'conflicting-id'],
    ['status'=>400,'error_code'=>'ThrottlingExceptionOther'],
] as $response) {
    $transport->response=$response+$complete+['body'=>'secret bearer@example.test']; $calls=$transport->calls;
    submission_expect($mapper->submit($envelope)===['kind'=>'unknown','code'=>'ambiguous_submission']
        && $transport->calls===$calls+1, 'untrusted or mismatched throttling remains unknown without submission retry');
}
$transport->throws=true; $calls=$transport->calls;
submission_expect($mapper->submit($envelope)===['kind'=>'unknown','code'=>'ambiguous_submission'] && $transport->calls===$calls+1,'exception redaction without retry');
$calls=$transport->calls;
foreach (['account_id'=>'000000000000','region'=>'us-east-1','configuration_set'=>'other','configuration_version'=>2] as $key=>$value) {
    $mismatch=new PeraCRM_CampaignSubmissionV1(array_replace($valid,['scope'=>array_replace($scope,[$key=>$value])]));
    submission_expect($mapper->submit($mismatch)===['kind'=>'permanent','code'=>'scope_mismatch','readiness_blocked'=>true] && $transport->calls===$calls,'scope mismatch before transport');
}
// New code is unreferenced by bootstrap/worker; production gate remains unconditional.
$queue=file_get_contents($root.'/inc/campaigns/queue.php');
submission_expect(preg_match('/function peracrm_campaign_queue_dispatch_allowed\(\)\s*\{\s*return false;/',$queue), 'unconditional production block');
submission_expect(!str_contains(file_get_contents($root.'/inc/bootstrap.php'),'/providers/ses.php'), 'no production factory or transport wiring');
echo "PASS submission envelope, frozen bindings, SES mapping, outcomes, redaction and disabled production contracts\n";
