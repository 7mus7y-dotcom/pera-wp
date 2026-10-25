<?php
require_once __DIR__.'/ses-suppression-fixture.php';
require_once __DIR__.'/prepared-worker-fixture.php';
// Last fixture: permanent deny knowledge must survive and must not be cleared to
// make an earlier shared synthetic worker mailbox eligible for subsequent tests.
peracrm_with_target_blog(static function () {
    global $wpdb;
    $job=worker_fixture_reset(15); $deployment='late-suppress-'.bin2hex(random_bytes(8));
    $preparation=prepared_service($deployment); $worker=worker_fixture();
    $transport=new class implements PeraCRM_Campaign_SES_Transport {
        public int $calls=0;
        public function send(array $request,array $options): array {
            ++$this->calls;
            foreach ($request['EmailTags'] as $tag) { if ($tag['Name']==='peracrm_attempt') { $attempt=$tag['Value']; } }
            return ['complete'=>true,'authenticated'=>true,'status'=>200,'message_id'=>'late-'.$attempt];
        }
    };
    for ($batch=0;$batch<3;$batch++) {
        $claims=$worker->claim($job,10);
        foreach ($claims as $claim) { $worker->submitPrepared($claim,$preparation,new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport)); }
        if (!$claims) { break; }
    }
    sns_expect($transport->calls>0 && $worker->completion($job),'actual prepared job submissions complete before late feedback');
    $v=sns_values(); $v['deployment']=$deployment; $records=prepared_records($deployment);
    $metadata=json_decode($records['submission_attempts'][0]['binding_json'],true);
    foreach (['complete'=>'Bounce','cancel'=>'Complaint'] as $terminal=>$type) {
        if ($terminal==='cancel') { worker_fixture_state($job,'cancel'); }
        $event=ses_event($type); $event['mail']['destination']=[$metadata['mailbox']];
        $event['mail']['messageId']='late-'.$metadata['attempt_key'];
        foreach (['delivery'=>'delivery_key','attempt'=>'attempt_key','snapshot'=>'snapshot_hash','envelope'=>'envelope_hash'] as $tag=>$field) { $event['mail']['tags']['peracrm_'.$tag]=[$metadata[$field]]; }
        $event[lcfirst($type)][$type==='Bounce'?'bouncedRecipients':'complainedRecipients']=[['emailAddress'=>$metadata['mailbox']]];
        $id=suppress_receive($v,$event,$type==='Bounce'?1200:1201);
        $before=worker_fixture_rows($job); $calls=$transport->calls;
        $jobBefore=suppress_rows('peracrm_fixture_worker_jobs');
        suppress_apply($v,$id);
        sns_expect(worker_fixture_rows($job)===$before && suppress_rows('peracrm_fixture_worker_jobs')===$jobBefore
            && $worker->claim($job,1)===[] && $transport->calls===$calls,'late '.$terminal.' feedback cannot regress state or grant retry');
    }
    $identity=peracrm_campaigns_repository_identity($metadata['mailbox']);
    sns_expect((int)$identity['hard_bounce']===1 && (int)$identity['complaint']===1,'completed/cancelled feedback retains both reasons');
});
