<?php
require_once __DIR__.'/prepared-worker-fixture.php';
$w=worker_fixture();
$seed=bin2hex(random_bytes(8));
foreach (['accepted','unknown','interrupted','withdraw','cancel','pause','schedule','stale','insert-failure','historical','membership','authority','transient'] as $scenario) {
    $job=worker_fixture_reset(1); $claim=$w->claim($job,1)[0];
    $deployment='prepared-'.$seed.'-'.$scenario;
    $p=prepared_service($deployment); $transport=new PeraCRM_Prepared_Mock($deployment);
    $mapper=new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport);
    $filter=null; $restore=null;
    try {
        if (in_array($scenario,['withdraw','cancel','pause','schedule','stale','interrupted'],true)) {
            $filter=prepared_interrupt(static function () use ($scenario,$job,$claim,&$restore) {
                if ($scenario==='interrupted') { throw new RuntimeException('synthetic interruption after preparation COMMIT'); }
                if ($scenario==='stale') { worker_fixture_expire($job); return; }
                if ($scenario==='withdraw') {
                    wp_set_current_user(worker_fixture_target(static fn()=>(int)get_option('campaign_fixture_manager')));
                    $restore=worker_fixture_target(static function () use ($claim) {
                        global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_subscribers').' WHERE id=%d',$claim['subscriber_id']),ARRAY_A);
                    });
                    $result=peracrm_campaigns_record_consent($claim['mailbox'],'unsubscribed',
                        ['source'=>'synthetic_fixture','source_ref'=>'prepared-withdraw','occurred_at'=>'2026-01-01 00:00:00']);
                    prepared_expect(!is_wp_error($result),'withdrawal service commits during preparation interval'); return;
                }
                worker_fixture_state($job,$scenario,$scenario==='schedule'?gmdate('Y-m-d\TH:i:s\Z',time()+3600):null);
            });
        }
        if ($scenario==='insert-failure') {
            $filter=static fn($sql)=>str_starts_with($sql,'INSERT INTO') && str_contains($sql,'submission_outputs')?'INVALID_PREPARED_OUTPUT_SQL':$sql;
            add_filter('query',$filter);
        }
        if ($scenario==='membership') {
            worker_fixture_target(static function () use ($claim) {
                global $wpdb; $wpdb->update(peracrm_table('peracrm_fixture_worker_deliveries'),['subscriber_id'=>99999999],['id'=>$claim['id']]);
            });
        }
        if ($scenario==='historical') {
            $restore=worker_fixture_target(static function () use ($job,$claim) {
                global $wpdb; $restore=[];
                $snapshotTable=peracrm_table('peracrm_campaign_content_snapshots');
                $snapshot=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$snapshotTable.' WHERE id=%d',$claim['snapshot_id']),ARRAY_A);
                $payload=json_decode($snapshot['payload_json'],true); $payload['schema_version']=1;
                $payload['content']['schema_version']=1; $payload['content']['purpose']='review_only';
                $hash=hash('sha256',peracrm_campaign_snapshot_json($payload['content']));
                foreach (['peracrm_campaign_content_snapshots','peracrm_campaign_frozen_revisions','peracrm_campaign_freeze_attempts',
                    'peracrm_fixture_worker_jobs','peracrm_fixture_worker_deliveries'] as $name) {
                    $table=peracrm_table($name);
                    $where=match($name) {
                        'peracrm_campaign_content_snapshots'=>['id'=>$claim['snapshot_id']],
                        'peracrm_campaign_frozen_revisions'=>['id'=>$claim['frozen_revision_id']],
                        'peracrm_campaign_freeze_attempts'=>['snapshot_id'=>$claim['snapshot_id']],
                        'peracrm_fixture_worker_jobs'=>['id'=>$job],
                        default=>['id'=>$claim['id']],
                    };
                    $row=$wpdb->get_row('SELECT * FROM '.$table.' WHERE '.array_key_first($where).'='.(int)current($where),ARRAY_A);
                    $restore[]=[$table,$row];
                    $changes=['content_hash'=>$hash];
                    if ($name==='peracrm_campaign_content_snapshots') { $changes['payload_json']=wp_json_encode($payload); }
                    $wpdb->update($table,$changes,$where);
                }
                return $restore;
            });
        }
        if ($scenario==='authority') { $w=new PeraCRM_Fixture_Worker(worker_fixture_authority()); worker_fixture_target(static fn()=>update_option('campaign_worker_fixture_authority',bin2hex(random_bytes(32)),false)); }
        $transport->mode=in_array($scenario,['unknown','transient'],true)?$scenario:'accepted';
        if (in_array($scenario,['interrupted','stale','insert-failure','historical','authority'],true)) {
            prepared_reject(fn()=>$w->submitPrepared($claim,$p,$mapper));
        } else { $result=$w->submitPrepared($claim,$p,$mapper); }
        if ($filter) { remove_filter('query',$filter); $filter=null; }
        $records=prepared_records($deployment);
        if (in_array($scenario,['accepted','unknown'],true)) {
            prepared_expect($transport->calls===1 && $result['state']===$scenario,'integrated SES outcome '.$scenario);
            prepared_historical_recovery($deployment,$claim);
            prepared_reject(fn()=>$w->submitPrepared($claim,$p,$mapper));
            prepared_expect($w->claim($job)===[] && $transport->calls===1,'accepted/unknown never resubmitted');
        } elseif ($scenario==='transient') {
            prepared_expect($result['state']==='retry' && $transport->calls===1,'proven non-acceptance preserves retry');
            worker_fixture_target(static function () use ($job) { global $wpdb; $wpdb->query('UPDATE '.peracrm_table('peracrm_fixture_worker_deliveries')." SET next_attempt_at='2000-01-01' WHERE job_id=".$job); });
            $next=$w->claim($job,1)[0]; $transport->mode='accepted';
            prepared_expect($next['attempt_key']!==$claim['attempt_key'] && $w->submitPrepared($next,$p,$mapper)['state']==='accepted' && $transport->calls===2,'new attempt only after proven rejection');
        } elseif ($scenario==='interrupted') {
            prepared_expect($transport->calls===0 && count($records['submission_outputs'])===1,'interruption leaves committed output and zero calls');
            $result=$w->submitPrepared($claim,prepared_service($deployment,true),$mapper);
            prepared_expect($result['state']==='accepted' && $transport->calls===1 && prepared_records($deployment)===$records,'exact recovery without issuance inputs or ciphertext replacement');
        } else {
            prepared_expect($transport->calls===0,'zero transport when '.$scenario.' denies authorization');
            if ($scenario==='cancel') {
                prepared_historical_recovery($deployment,$claim);
                prepared_reject(fn()=>$w->submitPrepared($claim,$p,$mapper));
                prepared_expect($transport->calls===0,'cancelled historical recovery cannot submit');
            }
            if (in_array($scenario,['insert-failure','historical','membership','authority'],true)) {
                prepared_expect(count($records['recipient_tokens'])===0 && count($records['submission_attempts'])===0
                    && count($records['submission_outputs'])===0,'no partial preparation after '.$scenario);
            }
            if ($scenario==='stale') {
                $w->recover($job); $next=$w->claim($job,1)[0];
                prepared_expect($next['attempt_key']===$claim['attempt_key'] && $next['owner_token']!==$claim['owner_token'],'recovery refunds unsubmitted reservation and fences old owner');
                prepared_expect($w->submitPrepared($next,prepared_service($deployment,true),$mapper)['state']==='accepted'
                    && prepared_records($deployment)===$records,'new lease recovers exact same attempt bytes');
            }
        }
    } finally {
        if ($filter) { remove_filter('query',$filter); }
        if ($restore) { worker_fixture_target(static function () use ($restore,$scenario) {
            global $wpdb;
            if ($scenario==='historical') { foreach ($restore as [$table,$row]) { $wpdb->update($table,$row,['id'=>$row['id']]); } }
            else {
                $wpdb->update(peracrm_table('peracrm_subscribers'),$restore,['id'=>$restore['id']]);
                $wpdb->delete(peracrm_table('peracrm_email_suppressions'),['normalized_email'=>$restore['normalized_email']]);
            }
        }); }
        $w=worker_fixture();
    }
}
echo "PASS integrated prepared worker SQL and mocked SES boundaries\n";
