<?php
/** Review regressions run against real SQL and exact published synthetic membership. */
worker_fixture_environment();
function worker_fixture_due_retries(int $job): void
{
    worker_fixture_target(static function () use ($job) {
        global $wpdb;
        peracrm_campaigns_db_require($wpdb->query($wpdb->prepare('UPDATE '.peracrm_table('peracrm_fixture_worker_deliveries')." SET next_attempt_at='2000-01-01' WHERE job_id=%d AND state='retry'",$job)));
    });
}
function worker_fixture_three_attempts(PeraCRM_Fixture_Worker $worker, int $size): array
{
    $job=worker_fixture_reset($size); $provider=new PeraCRM_Fake_Campaign_Provider(); $provider->mode='transient';
    for ($round=1;$round<=3;$round++) {
        $results=$worker->run($job,$provider,$size);
        worker_expect(count($results)===$size && count(array_filter($results,static function ($r) use ($round) { return $r['state']==='retry' && (int)$r['attempt_count']===$round; }))===$size,'lease regression setup: proven transient round '.$round.' size '.$size);
        worker_fixture_due_retries($job);
    }
    return [$job,$provider];
}

// All synthetic clock changes belong to this fixture's SQL session and are restored even on failure.
global $wpdb;
try {
    foreach (['pause','schedule'] as $operation) {
        [$lease_job,$lease_provider]=worker_fixture_three_attempts($w,1);
        $fourth=$w->claim($lease_job,1)[0]; $before=$lease_provider->calls;
        worker_expect((int)$fourth['attempt_count']===4,'fourth claim reserves final attempt before '.$operation);
        worker_fixture_state($lease_job,$operation,$operation==='schedule' ? gmdate('Y-m-d\TH:i:s\Z',time()+120) : null);
        $released=$w->submit($fourth,$lease_provider);
        worker_expect($released['state']==='held' && (int)$released['attempt_count']===3 && $released['owner_token']==='' && $released['lease_until']===null && $lease_provider->calls===$before,'fourth-claim '.$operation.' refunds unused attempt without submission');
        worker_expect(worker_reject(static function () use ($w,$fourth,$lease_provider) { $w->submit($fourth,$lease_provider); }),'released fourth-claim '.$operation.' owner is stale');
        if ($operation==='pause') { worker_fixture_state($lease_job,'resume'); }
        else {
            worker_expect($w->claim($lease_job)===[],'future schedule remains a dispatch fence');
            worker_fixture_advance(121); // The stored UTC schedule becomes due; no intent/state bypass.
        }
        $lease_provider->mode='accepted';
        $accepted=$w->run($lease_job,$lease_provider,1)[0];
        worker_expect($accepted['state']==='accepted' && (int)$accepted['attempt_count']===4 && $lease_provider->calls===$before+1 && $w->completion($lease_job),'fourth-claim '.$operation.' recipient remains processable after resume/due time');
        peracrm_campaigns_db_require($wpdb->query('SET timestamp = 0'));
    }

    $lease_job=worker_fixture_reset(15); $slow=new PeraCRM_Fake_Campaign_Provider(); $slow->elapsed_seconds=7;
    $slow->on_submit=static function ($submission) use ($lease_job,$slow) {
        $rows=worker_fixture_rows($lease_job);
        $untouched=array_filter($rows,static function ($r) { return $r['state']==='held' && (int)$r['attempt_count']===0 && $r['owner_token']==='' && $r['lease_until']===null; });
        worker_expect(count($untouched)===15-$slow->calls && count(array_filter($rows,static function ($r) { return $r['state']==='inflight'; }))===1 && !array_filter($rows,static function ($r) { return $r['state']==='claimed'; }),'slow submission '.$slow->calls.' has no leased/attempted waiting recipients');
    };
    $started=(int)$wpdb->get_var('SELECT UNIX_TIMESTAMP()');
    $results=$w->run($lease_job,$slow,10);
    $elapsed=(int)$wpdb->get_var('SELECT UNIX_TIMESTAMP()')-$started;
    worker_expect($elapsed>=70 && count($results)===10 && $slow->calls===10 && count(array_filter($results,static function ($r) { return $r['state']==='accepted' && (int)$r['attempt_count']===1; }))===10,'ten seven-second simulated calls exceed a cumulative lease yet all ten are accepted once');
    worker_expect($slow->timeouts===array_fill(0,10,20) && !$w->completion($lease_job) && count(array_filter(worker_fixture_rows($lease_job),static function ($r) { return $r['state']==='held' && (int)$r['attempt_count']===0; }))===5,'provider timeout is 20 seconds; invocation remains bounded at ten with five untouched rows');
    peracrm_campaigns_db_require($wpdb->query('SET timestamp = 0'));

    $lease_job=worker_fixture_reset(3); $overrun=new PeraCRM_Fake_Campaign_Provider(); $overrun->elapsed_seconds=61;
    $stale_inflight=null;
    $overrun->on_submit=static function () use ($lease_job,&$stale_inflight) { $stale_inflight=worker_fixture_rows($lease_job)[0]; };
    worker_expect(worker_reject(static function () use ($w,$lease_job,$overrun) { $w->run($lease_job,$overrun,3); }),'provider violating timeout and exceeding lease cannot persist its late result');
    $rows=worker_fixture_rows($lease_job);
    worker_expect($rows[0]['state']==='inflight' && (int)$rows[0]['attempt_count']===1 && $overrun->calls===1 && count(array_filter($rows,static function ($r) { return $r['state']==='held' && (int)$r['attempt_count']===0 && $r['lease_until']===null; }))===2,'overrun abort leaves later recipients unclaimed with full attempt budgets');
    $recovery=$w->recover($lease_job);
    worker_expect(count($recovery)===1 && $recovery[0]['state']==='unknown' && (int)$recovery[0]['attempt_count']===1 && !$w->completion($lease_job),'expired in-flight overrun recovers only to unknown');
    worker_expect(worker_reject(static function () use ($w,$stale_inflight) { $w->result($stale_inflight,['kind'=>'accepted','message_id'=>'late-overrun']); }) && worker_fixture_rows($lease_job)[0]['state']==='unknown','late owner cannot overwrite recovered overrun');
    $remaining=new PeraCRM_Fake_Campaign_Provider(); $done=$w->run($lease_job,$remaining,3);
    worker_expect(count($done)===2 && $remaining->calls===2 && worker_fixture_rows($lease_job)[0]['state']==='unknown' && $w->claim($lease_job)===[] && !$w->completion($lease_job),'next invocation processes untouched rows without retrying unknown or accepted recipients');
    peracrm_campaigns_db_require($wpdb->query('SET timestamp = 0'));

    [$lease_job,$lease_provider]=worker_fixture_three_attempts($w,3);
    $untouched=$w->claim($lease_job,3); $before=$lease_provider->calls;
    worker_fixture_expire($lease_job); $refunded=$w->recover($lease_job,3);
    worker_expect(count($refunded)===3 && count(array_filter($refunded,static function ($r) { return $r['state']==='held' && (int)$r['attempt_count']===3 && $r['owner_token']==='' && $r['lease_until']===null; }))===3,'recovery refunds all untouched fourth claims instead of exhausting attempts');
    foreach ($untouched as $claim) {
        worker_expect(worker_reject(static function () use ($w,$claim,$lease_provider) { $w->submit($claim,$lease_provider); }),'recovered untouched claim rejects stale submission');
    }
    worker_expect($lease_provider->calls===$before,'untouched recovery and stale claims make no provider calls');
    $lease_provider->mode='accepted'; $accepted=$w->run($lease_job,$lease_provider,3);
    worker_expect(count($accepted)===3 && count(array_filter($accepted,static function ($r) { return $r['state']==='accepted' && (int)$r['attempt_count']===4; }))===3 && $w->completion($lease_job),'recovered untouched fourth claims remain processable exactly once');
} finally {
    peracrm_campaigns_db_require($wpdb->query('SET timestamp = 0'));
}
echo "PASS worker: fourth-claim and sequential lease acceptance regressions (real SQL, simulated database elapsed time)\n";
