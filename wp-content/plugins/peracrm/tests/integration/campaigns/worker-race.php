<?php
require __DIR__.'/worker-fixture.php';
$mode=$args[0] ?? ''; $scenario=$args[1] ?? '';
if (!in_array($scenario,['claim-claim','lease-stale','crash-before','crash-after','claim-pause','claim-cancel','authorize-pause','authorize-unsubscribe'],true)) { throw new RuntimeException('Invalid worker race.'); }
if ($mode==='prepare') {
    $job=worker_fixture_reset($scenario==='claim-claim' ? 15 : 1);
    $claims=[];
    if (in_array($scenario,['lease-stale','crash-before','crash-after','authorize-pause','authorize-unsubscribe'],true)) { $claims=worker_fixture()->claim($job,1); }
    worker_fixture_target(static function () use ($job,$claims) { update_option('campaign_worker_race',compact('job','claims'),false); });
    return;
}
extract(worker_fixture_target(static function () { return get_option('campaign_worker_race'); }));
$w=worker_fixture();
if ($mode==='verify') {
    $rows=worker_fixture_rows($job);
    $winner=json_decode(file_get_contents('/tmp/peracrm-worker-winner'),true);
    $loser=json_decode(file_get_contents('/tmp/peracrm-worker-loser'),true);
    if ($scenario==='crash-after') {
        $stale_inflight=$rows[0];
        worker_fixture_expire($job); $unknown=$w->recover($job)[0];
        if ($unknown['state']!=='unknown' || $w->claim($job)!==[] || $w->completion($job)) { throw new RuntimeException('Accepted crash was blindly retried.'); }
        $rejected=false;
        try { $w->result($stale_inflight,['kind'=>'accepted','message_id'=>'late-stale-result']); } catch (UnexpectedValueException $e) { $rejected=true; }
        if (!$rejected || worker_fixture_rows($job)[0]['state']!=='unknown') { throw new RuntimeException('Expired owner overwrote recovered unknown.'); }
        $p=new PeraCRM_Fake_Campaign_Provider(); $e=$p->lookup($unknown['delivery_key'],$unknown['attempt_key']);
        if (!$e || $e['kind']!=='accepted') { throw new RuntimeException('Fake acceptance not durable.'); }
        $manager=worker_fixture_target(static function () { return (int)get_option('campaign_fixture_manager'); }); wp_set_current_user($manager);
        $campaign=worker_fixture_target(static function () use ($job) { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT campaign_id FROM '.peracrm_table('peracrm_fixture_worker_jobs').' WHERE id=%d',$job)); });
        $resolved=$w->reconcile($job,(int)$unknown['id'],(int)$unknown['version'],wp_create_nonce('peracrm_campaign_queue_'.$campaign),$e,$p);
        if ($resolved['state']!=='accepted' || !$w->completion($job) || $p->calls!==0) { throw new RuntimeException('Crash reconciliation failed.'); }
    } elseif ($scenario==='crash-before') {
        worker_fixture_expire($job); $held=$w->recover($job)[0];
        if ($held['state']!=='held' || count($w->claim($job))!==1) { throw new RuntimeException('Pre-submit crash did not recover.'); }
    } else {
        if ($winner['connection']===$loser['connection'] || $loser['duration']<0.5) { throw new RuntimeException('Independent blocking connections required: '.wp_json_encode([$scenario,$winner,$loser])); }
        if ($scenario==='claim-claim' && (count($winner['ids'])!==10 || count($loser['ids'])!==0 || count(array_filter($rows,static function ($r) { return $r['state']==='claimed'; }))!==10)) { throw new RuntimeException('Claim/concurrency limit failed.'); }
        if ($scenario==='lease-stale' && ($rows[0]['state']!=='claimed' || $rows[0]['owner_token']===$claims[0]['owner_token'] || $loser['result']!=='stale')) { throw new RuntimeException('Stale-owner fence failed.'); }
        if (in_array($scenario,['claim-pause','claim-cancel'],true) && $loser['ids']!==[]) { throw new RuntimeException('Lifecycle before claim did not fence.'); }
        if (in_array($scenario,['authorize-pause','authorize-unsubscribe'],true) && $rows[0]['state']!=='accepted') { throw new RuntimeException('Submission authorization boundary failed.'); }
    }
    echo 'PASS worker race: '.$scenario.' separate connections; lease/authority/request boundary preserved'."\n"; return;
}
global $wpdb; $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $started=microtime(true); $result='ok'; $ids=[];
if ($mode==='winner') {
    if ($scenario==='crash-before') { file_put_contents('/tmp/peracrm-worker-held','claimed'); exit(72); }
    if ($scenario==='crash-after') { $p=new PeraCRM_Fake_Campaign_Provider(); $p->mode='crash_after_accept'; $w->submit($claims[0],$p); }
    $held=false;
    add_filter('query',static function ($sql) use (&$held,$scenario) {
        $is_job=str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_fixture_worker_deliveries') && str_contains($sql,'`state`');
        $is_authorize=str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_fixture_worker_deliveries') && str_contains($sql,"'inflight'");
        $is_pause=str_starts_with($sql,'UPDATE') && (str_contains($sql,'peracrm_fixture_worker_jobs') || str_contains($sql,'peracrm_campaign_queue_jobs'));
        $match=in_array($scenario,['authorize-pause','authorize-unsubscribe'],true) ? $is_authorize : (in_array($scenario,['claim-pause','claim-cancel'],true) ? $is_pause : $is_job);
        if (!$held && $match) { $held=true; file_put_contents('/tmp/peracrm-worker-held','locked'); usleep(1500000); }
        return $sql;
    });
    if ($scenario==='lease-stale') { worker_fixture_expire($job); $w->recover($job); $ids=array_column($w->claim($job),'id'); }
    elseif (in_array($scenario,['claim-pause','claim-cancel'],true)) { worker_fixture_state($job,$scenario==='claim-pause' ? 'pause' : 'cancel'); }
    elseif (in_array($scenario,['authorize-pause','authorize-unsubscribe'],true)) { $w->submit($claims[0],new PeraCRM_Fake_Campaign_Provider()); }
    else { $ids=array_column($w->claim($job),'id'); }
} else {
    if (in_array($scenario,['crash-before','crash-after'],true)) { /* verification performs post-crash recovery */ }
    elseif ($scenario==='lease-stale') {
        try { $w->submit($claims[0],new PeraCRM_Fake_Campaign_Provider()); } catch (UnexpectedValueException $e) { $result='stale'; }
    } elseif ($scenario==='authorize-pause') { worker_fixture_state($job,'pause'); }
    elseif ($scenario==='authorize-unsubscribe') {
        $manager=worker_fixture_target(static function () { return (int)get_option('campaign_fixture_manager'); }); wp_set_current_user($manager);
        $r=peracrm_campaigns_record_consent($claims[0]['mailbox'],'unsubscribed',['source'=>'synthetic_fixture','source_ref'=>'worker-race','evidence_ref'=>'synthetic','policy_version'=>'v1','occurred_at'=>gmdate('Y-m-d H:i:s')]);
        if (is_wp_error($r)) { throw new RuntimeException('Withdrawal failed.'); }
    } else { $ids=array_column($w->claim($job),'id'); }
}
file_put_contents('/tmp/peracrm-worker-'.$mode,wp_json_encode(['connection'=>$connection,'duration'=>microtime(true)-$started,'ids'=>$ids,'result'=>$result]));
