<?php
require_once __DIR__.'/prepared-worker-fixture.php';
require_once __DIR__.'/ses-matching-fixture.php';
function acceptance_worker_event(array $v,string $deployment,array $request,string $type,int $id): void
{
    worker_fixture_target(static function () use ($v,$deployment,$request,$type,$id) {
        $event=ses_event($type); $mailbox=$request['Destination']['ToAddresses'][0];
        $event['mail']['destination']=[$mailbox]; $event['mail']['messageId']='worker-accepted';
        foreach ($request['EmailTags'] as $tag) { $event['mail']['tags'][$tag['Name']]=[$tag['Value']]; }
        $detail=lcfirst($type);
        if ($type==='Delivery') { $event[$detail]['recipients']=[$mailbox]; }
        if ($type==='Bounce') { $event[$detail]['bouncedRecipients']=[['emailAddress'=>$mailbox]]; $event[$detail]['bounceType']='Transient'; }
        if ($type==='DeliveryDelay') { $event[$detail]['delayedRecipients']=[['emailAddress'=>$mailbox]]; }
        $receipt=ses_match_receive($v,$deployment,$event,$id);
        $result=(new PeraCRM_SES_Matcher(sns_verifier($v),$deployment,get_current_blog_id(),ses_event_scope()))->match($receipt);
        sns_expect(array_values($result)===['matched'],'worker authenticated '.$type.' acceptance');
    });
}
$v=sns_values();
foreach (['early-event','late-response','late-event','retry-event','cancelled','Delivery','Bounce','DeliveryDelay'] as $n=>$scenario) {
    $job=worker_fixture_reset(1); $deployment='worker-accept-'.bin2hex(random_bytes(8));
    $preparation=prepared_service($deployment); $worker=worker_fixture(); $claim=$worker->claim($job,1)[0];
    $transport=new class($scenario,$job,$v,$deployment,$n) implements PeraCRM_Campaign_SES_Transport {
        public int $calls=0; public array $request=[];
        public function __construct(private string $scenario,private int $job,private array $v,private string $deployment,private int $n) {}
        public function send(array $request,array $options): array {
            ++$this->calls; $this->request=$request;
            if ($this->scenario==='early-event') { acceptance_worker_event($this->v,$this->deployment,$request,'Send',900+$this->n); }
            if (in_array($this->scenario,['late-response','cancelled'],true)) { worker_fixture_expire($this->job); }
            if ($this->scenario==='cancelled') { worker_fixture_state($this->job,'cancel'); }
            if (in_array($this->scenario,['early-event','retry-event'],true)) {
                return ['complete'=>true,'authenticated'=>true,'status'=>429,'error_code'=>'TooManyRequestsException'];
            }
            if ($this->scenario==='late-event') { throw new RuntimeException('synthetic timeout'); }
            return ['complete'=>true,'authenticated'=>true,'status'=>200,'message_id'=>'worker-accepted'];
        }
    };
    $mapper=new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport);
    if (in_array($scenario,['late-response','cancelled'],true)) {
        prepared_reject(fn()=>$worker->submitPrepared($claim,$preparation,$mapper));
        $worker->recover($job);
    } else { $worker->submitPrepared($claim,$preparation,$mapper); }
    if (in_array($scenario,['late-event','retry-event','cancelled','Delivery','Bounce','DeliveryDelay'],true)) {
        $before=worker_fixture_rows($job);
        acceptance_worker_event($v,$deployment,$transport->request,in_array($scenario,['Delivery','Bounce','DeliveryDelay'],true)?$scenario:'Send',920+$n);
        prepared_expect(worker_fixture_rows($job)===$before,'event preserves submission state '.$scenario);
    }
    if ($scenario==='retry-event') {
        worker_fixture_target(static function () use ($claim) { global $wpdb; $wpdb->update(peracrm_table('peracrm_fixture_worker_deliveries'),['next_attempt_at'=>'2000-01-01'],['id'=>$claim['id']]); });
    }
    prepared_expect($worker->claim($job,1)===[] && $transport->calls===1,'accepted/unknown/cancelled retry fenced '.$scenario);
    prepared_reject(fn()=>$worker->submitPrepared($claim,$preparation,$mapper));
    prepared_expect($transport->calls===1,'stale claim never resubmits '.$scenario);
    if ($scenario==='late-response') {
        $row=worker_fixture_rows($job)[0];
        prepared_expect($row['state']==='unknown','late acceptance does not resolve unknown automatically');
        $provider=new class implements PeraCRM_Campaign_Provider {
            public function submit(array $s): array { throw new RuntimeException('no transport'); }
            public function lookup(string $d,string $a): ?array { return null; }
            public function verify(array $e,string $d,string $a): bool { return ($e['proof']??null)==='synthetic-verified'; }
        };
        $e=['kind'=>'accepted','message_id'=>'worker-accepted','delivery_key'=>$row['delivery_key'],'attempt_key'=>$row['attempt_key'],'proof'=>'synthetic-verified'];
        prepared_reject(fn()=>$worker->reconcile($job,(int)$row['id'],(int)$row['version'],'bad',$e,$provider));
        wp_set_current_user(worker_fixture_target(static fn()=>(int)get_option('campaign_fixture_manager')));
        $campaign=worker_fixture_target(static function () use ($job) { global $wpdb; return (int)$wpdb->get_var('SELECT campaign_id FROM '.peracrm_table('peracrm_fixture_worker_jobs').' WHERE id='.(int)$job); });
        $nonce=wp_create_nonce('peracrm_campaign_queue_'.$campaign);
        $bad=$e; $bad['kind']='transient';
        prepared_reject(fn()=>$worker->reconcile($job,(int)$row['id'],(int)$row['version'],$nonce,$bad,$provider));
        $resolved=$worker->reconcile($job,(int)$row['id'],(int)$row['version'],$nonce,$e,$provider);
        prepared_expect($resolved['state']==='accepted','only authorized durable positive reconciliation resolves unknown');
        wp_set_current_user(0);
    }
}
