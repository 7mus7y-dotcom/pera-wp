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
// Authenticated evidence committed between preparation and final authorization.
$job=worker_fixture_reset(1); $deployment='final-fence-'.bin2hex(random_bytes(8));
$preparation=prepared_service($deployment); $worker=worker_fixture(); $claim=$worker->claim($job,1)[0];
$transport=new PeraCRM_Prepared_Mock($deployment);
$filter=prepared_interrupt(static function () use ($v,$deployment) {
    $record=prepared_records($deployment)['submission_attempts'][0];
    $metadata=json_decode($record['binding_json'],true);
    $request=['Destination'=>['ToAddresses'=>[$metadata['mailbox']]],'EmailTags'=>[]];
    foreach (['delivery'=>'delivery_key','attempt'=>'attempt_key','snapshot'=>'snapshot_hash','envelope'=>'envelope_hash'] as $tag=>$key) {
        $request['EmailTags'][]=['Name'=>'peracrm_'.$tag,'Value'=>$metadata[$key]];
    }
    acceptance_worker_event($v,$deployment,$request,'Send',899);
});
try { $result=$worker->submitPrepared($claim,$preparation,new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport)); }
finally { remove_filter('query',$filter); }
prepared_expect($result['state']==='unknown' && $transport->calls===0 && $worker->claim($job,1)===[],'evidence before final authorization makes zero transport calls');
// Reconnection before either write must not replay an autocommitted worker mutation.
foreach (['update','audit','commit','capture'] as $failure) {
    $job=worker_fixture_reset(1); $worker=worker_fixture(); $before=worker_fixture_rows($job); $fired=false;
    $filter=static function ($sql) use ($failure,&$fired) {
        global $wpdb;
        $target=($failure==='update' && str_starts_with($sql,'UPDATE ') && str_contains($sql,'fixture_worker_deliveries'))
            || ($failure==='audit' && str_starts_with($sql,'INSERT INTO ') && str_contains($sql,'fixture_worker_events'))
            || ($failure==='commit' && $sql==='COMMIT') || ($failure==='capture' && $sql==='SELECT CONNECTION_ID()');
        if (!$fired && $target) { $fired=true; $wpdb->close(); $wpdb->db_connect(); }
        return $sql;
    };
    add_filter('query',$filter);
    try { prepared_reject(fn()=>$worker->claim($job,1)); } finally { remove_filter('query',$filter); }
    prepared_expect($fired && worker_fixture_rows($job)===$before,'worker reconnect rollback '.$failure);
}
foreach (['early-event','late-response','late-event','retry-event','cancelled','Delivery','Bounce','DeliveryDelay','retry-siblings'] as $n=>$scenario) {
    $job=worker_fixture_reset($scenario==='retry-siblings'?3:1); $deployment='worker-accept-'.bin2hex(random_bytes(8));
    $preparation=prepared_service($deployment); $worker=worker_fixture(); $claim=$worker->claim($job,1)[0];
    $transport=new class($scenario,$job,$v,$deployment,$n) implements PeraCRM_Campaign_SES_Transport {
        public int $calls=0; public array $request=[];
        public function __construct(private string $scenario,private int $job,private array $v,private string $deployment,private int $n) {}
        public function send(array $request,array $options): array {
            ++$this->calls; $this->request=$request;
            if ($this->scenario==='early-event') { acceptance_worker_event($this->v,$this->deployment,$request,'Send',900+$this->n); }
            if (in_array($this->scenario,['late-response','cancelled'],true)) { worker_fixture_expire($this->job); }
            if ($this->scenario==='cancelled') { worker_fixture_state($this->job,'cancel'); }
            if (in_array($this->scenario,['early-event','retry-event','retry-siblings'],true)) {
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
    if (in_array($scenario,['late-event','retry-event','retry-siblings','cancelled','Delivery','Bounce','DeliveryDelay'],true)) {
        $before=worker_fixture_rows($job);
        acceptance_worker_event($v,$deployment,$transport->request,in_array($scenario,['Delivery','Bounce','DeliveryDelay'],true)?$scenario:'Send',920+$n);
        prepared_expect(worker_fixture_rows($job)===$before,'event preserves submission state '.$scenario);
    }
    if (in_array($scenario,['retry-event','retry-siblings'],true)) {
        worker_fixture_target(static function () use ($claim) { global $wpdb; $wpdb->update(peracrm_table('peracrm_fixture_worker_deliveries'),['next_attempt_at'=>'2000-01-01'],['id'=>$claim['id']]); });
    }
    prepared_expect($worker->claim($job,1)===[] && $transport->calls===1,'accepted/unknown/cancelled retry fenced '.$scenario);
    prepared_reject(fn()=>$worker->submitPrepared($claim,$preparation,$mapper));
    prepared_expect($transport->calls===1,'stale claim never resubmits '.$scenario);
    if ($scenario==='retry-siblings') {
        $next=$worker->claim($job,1);
        prepared_expect(count($next)===1 && $next[0]['id']!==$claim['id'] && $transport->calls===1,'fenced lowest ID cannot starve later deliveries');
    }
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
