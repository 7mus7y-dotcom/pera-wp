<?php
require_once __DIR__.'/ses-matching-fixture.php';
require_once __DIR__.'/prepared-worker-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/submission-response.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    if ($mode==='prepare') {
        $v=sns_values(); $v['deployment']='accept-race-'.bin2hex(random_bytes(8));
        if ($scenario==='retry-fence') {
            $job=worker_fixture_reset(1); $preparation=prepared_service($v['deployment']); $worker=worker_fixture();
            $claim=$worker->claim($job,1)[0]; $transport=new PeraCRM_Prepared_Mock($v['deployment']); $transport->mode='transient';
            $worker->submitPrepared($claim,$preparation,new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport));
            $store=new PeraCRM_CampaignRecipientPersistence($v['deployment'],get_current_blog_id()); $expected=$store->attempt($claim['attempt_key']);
            $binding=['blog_id'=>get_current_blog_id(),'subscriber_id'=>(int)$claim['subscriber_id'],'mailbox'=>$claim['mailbox'],
                'token_epoch'=>$expected['token_epoch'],'attempt_key'=>$claim['attempt_key'],'snapshot_hash'=>$claim['content_hash']];
            [$submission]=$store->recoverOutput($binding,$expected,new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('e',32)],'e1'));
            $v['submission']=$submission->fields(); $v['message']='race-accepted'; $v['job']=$job;
            $wpdb->update(peracrm_table('peracrm_fixture_worker_deliveries'),['next_attempt_at'=>'2000-01-01'],['id'=>$claim['id']]);
        } else {
            $seed=ses_match_seed($v['deployment'],800); $v['submission']=$seed['submission']->fields();
            $v['message']=$seed['event']['mail']['messageId'];
            ses_match_receive($v,$v['deployment'],$seed['event'],800);
        }
        $v['snapshot']=ses_match_snapshot(); update_option('acceptance_race',$v,false); return;
    }
    $v=get_option('acceptance_race');
    if ($mode==='verify') {
        $a=json_decode(file_get_contents('/tmp/peracrm-accept-winner'),true);
        $b=json_decode(file_get_contents('/tmp/peracrm-accept-loser'),true);
        sns_expect($a['connection']!==$b['connection'] && $a['result']==='accepted','independent response/event connections');
        sns_expect($b['result']===($scenario==='conflict'?'message_conflict':'accepted') && $b['duration']>0.5,'serialized acceptance race '.$scenario);
        sns_expect(ses_match_snapshot()===$v['snapshot'],'race preserves worker permissions and immutable attempt bytes');
        $records=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_provider_messages').' WHERE deployment=%s',$v['deployment']),ARRAY_A);
        if ($scenario==='retry-fence') {
            $row=worker_fixture_rows($v['job'])[0];
            sns_expect($row['state']==='unknown' && $row['reason']==='acceptance_evidence' && (int)$row['attempt_count']===1,'concurrent retry fenced before claim or transport');
        }
        sns_expect(count($records)===1 && $records[0]['message_id']===$v['message'],'single immutable winning association');
        return;
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $start=microtime(true);
    if ($mode==='winner') {
        add_filter('query',static function ($sql) {
            if ($sql==='COMMIT') { file_put_contents('/tmp/peracrm-accept-held','held'); usleep(1500000); }
            return $sql;
        });
    }
    $event=($scenario==='event-response' && $mode==='winner') || ($scenario==='response-event' && $mode==='loser');
    if ($scenario==='retry-fence' && $mode==='loser') {
        $result=worker_fixture()->claim($v['job'],1)===[]?'accepted':'unexpected';
    } elseif ($event) {
        $result=(new PeraCRM_SES_Matcher(sns_verifier($v),$v['deployment'],get_current_blog_id(),ses_event_scope()))->match(ses_event_id(800));
        $result=array_values($result)===['matched']?'accepted':'unexpected';
    } else {
        $message=$v['message'].($scenario==='conflict' && $mode==='loser'?'-conflict':'');
        $result=PeraCRM_Campaign_Submission_Response::record($v['deployment'],new PeraCRM_CampaignSubmissionV1($v['submission']),$message);
    }
    file_put_contents('/tmp/peracrm-accept-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$start,'result'=>$result]));
});
