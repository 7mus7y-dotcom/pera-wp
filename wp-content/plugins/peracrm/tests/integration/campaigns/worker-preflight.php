<?php
require_once __DIR__.'/prepared-worker-fixture.php';
function integrated_budget(string $deployment,?Closure $ready=null): PeraCRM_Sending_Budget
{
    return new PeraCRM_Sending_Budget(prepared_pins($deployment),static fn()=>prepared_clock(),$ready??static fn()=>true);
}
function integrated_sync_scope(string $deployment): string
{
    return hash('sha256',json_encode(['policy'=>'ses-account-deny-v1']+prepared_pins($deployment)));
}
worker_fixture_target(static function () {
    global $wpdb;
    foreach (['accepted','unknown','transient','rejected','missing','stale','future','running','failed','cursor','pages',
        'wrong-account','wrong-region','wrong-deployment','wrong-blog','budget','breaker','sync-after-prep',
        'sync-after-begin','breaker-after-begin','stale-after-begin','pause-after-begin','pause-final-attempt-after-begin','cancel-after-begin','quota-lock-wait','sync-lock-wait',
        'signal-failure','result-failure','settle-failure'] as $scenario) {
        $job=worker_fixture_reset(1); $w=worker_fixture();
        if ($scenario==='pause-final-attempt-after-begin') {
            $wpdb->query('UPDATE '.peracrm_table('peracrm_fixture_worker_deliveries').' SET attempt_count=3 WHERE job_id='.(int)$job);
        }
        $claim=$w->claim($job,1)[0];
        $deployment='integrated-'.bin2hex(random_bytes(8)); $p=prepared_service($deployment);
        $scope=integrated_sync_scope($deployment); $table=peracrm_table('peracrm_campaign_ses_sync');
        $budget=integrated_budget($deployment); $controls=prepared_controls();
        $transport=new PeraCRM_Prepared_Mock($deployment); $filter=null;
        if (in_array($scenario,['unknown','transient','rejected'],true)) { $transport->mode=$scenario; }
        if ($scenario==='missing') { $wpdb->delete($table,['scope_hash'=>$scope]); }
        if ($scenario==='stale') { $wpdb->update($table,['last_complete_at'=>gmdate('Y-m-d H:i:s',prepared_clock()-86400)],['scope_hash'=>$scope]); }
        if ($scenario==='future') { $wpdb->update($table,['last_complete_at'=>gmdate('Y-m-d H:i:s',prepared_clock()+1)],['scope_hash'=>$scope]); }
        if (in_array($scenario,['running','failed'],true)) { $wpdb->update($table,['state'=>$scenario],['scope_hash'=>$scope]); }
        if ($scenario==='cursor') { $wpdb->update($table,['cursor_value'=>'unfinished'],['scope_hash'=>$scope]); }
        if ($scenario==='pages') { $wpdb->update($table,['pages'=>2],['scope_hash'=>$scope]); }
        if (str_starts_with($scenario,'wrong-')) {
            $pins=prepared_pins($deployment); $key=substr($scenario,6);
            $key=['account'=>'account_id','blog'=>'blog_id'][$key]??$key;
            $pins[$key]=match($key) { 'account_id'=>'999999999999','blog_id'=>get_current_blog_id()+1,
                'region'=>'us-east-1',default=>'other-deployment' };
            $wrongScope=hash('sha256',json_encode(['policy'=>'ses-account-deny-v1']+$pins));
            $wpdb->update($table,['scope_hash'=>$wrongScope],['scope_hash'=>$scope]);
        }
        if ($scenario==='budget') {
            $actor=get_current_user_id(); wp_set_current_user((int)get_option('campaign_fixture_administrator'));
            $now=prepared_clock(); $budget->configure(1,1,$now,$now+3600); wp_set_current_user($actor);
            $budget->reserve(bin2hex(random_bytes(32)),bin2hex(random_bytes(32)));
        }
        if ($scenario==='breaker') { $budget->signal('provider_rejected'); }
        if (str_ends_with($scenario,'lock-wait')) {
            if ($scenario==='quota-lock-wait') {
                $actor=get_current_user_id(); wp_set_current_user((int)get_option('campaign_fixture_administrator'));
                $now=prepared_clock(); $budget->configure(100,10000,$now,$now+5); wp_set_current_user($actor);
            } else {
                $stamp=gmdate('Y-m-d H:i:s',prepared_clock()-86399);
                $wpdb->update($table,['started_at'=>$stamp,'updated_at'=>$stamp,'last_complete_at'=>$stamp],['scope_hash'=>$scope]);
            }
            $begun=false;
            $filter=static function ($sql) use (&$filter,&$begun,$scenario) {
                if (str_contains($sql,'submission_started')) { $begun=true; }
                if ($begun && str_starts_with($sql,'SELECT ') && str_contains($sql,'recipient_identities') && str_contains($sql,'FOR UPDATE')) {
                    remove_filter('query',$filter); worker_fixture_advance($scenario==='quota-lock-wait'?6:2);
                }
                return $sql;
            };
            add_filter('query',$filter);
        }
        if ($scenario==='sync-after-prep') {
            $filter=prepared_interrupt(static function () use ($wpdb,$table,$scope) {
                $wpdb->update($table,['state'=>'failed','error_code'=>'sync_storage'],['scope_hash'=>$scope]);
            });
        }
        if (str_ends_with($scenario,'after-begin')) {
            $begun=false;
            $filter=static function ($sql) use (&$filter,&$begun,$scenario,$wpdb,$table,$scope,$budget,$job) {
                if (str_contains($sql,'submission_started')) { $begun=true; }
                if ($begun && $sql==='START TRANSACTION') {
                    remove_filter('query',$filter);
                    if ($scenario==='sync-after-begin') { $wpdb->update($table,['state'=>'running'],['scope_hash'=>$scope]); }
                    elseif ($scenario==='breaker-after-begin') { $budget->signal('provider_rejected'); }
                    elseif ($scenario==='stale-after-begin') { worker_fixture_expire($job); }
                    else { worker_fixture_state($job,str_starts_with($scenario,'pause-')?'pause':'cancel'); }
                }
                return $sql;
            };
            add_filter('query',$filter);
        }
        if (in_array($scenario,['signal-failure','result-failure','settle-failure'],true)) {
            if ($scenario==='signal-failure') { $transport->mode='rejected'; }
            $transport->during=static function () use ($scenario,&$filter) {
                $filter=static function ($sql) use ($scenario) {
                    $fail=match($scenario) {
                        'signal-failure'=>str_starts_with($sql,'UPDATE ') && str_contains($sql,'budget_accounts') && str_contains($sql,'breaker='),
                        'result-failure'=>str_starts_with($sql,'UPDATE ') && str_contains($sql,'fixture_worker_deliveries'),
                        default=>str_starts_with($sql,'UPDATE ') && str_contains($sql,'budget_reservations') && str_contains($sql,'completed_at='),
                    };
                    return $fail?'INVALID_INTEGRATED_SQL':$sql;
                };
                add_filter('query',$filter);
            };
        }
        $denied=false; $result=null; $quiet=$wpdb->suppress_errors(true);
        try { $result=$w->submitPrepared($claim,$p,new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport,static fn()=>0),$controls); }
        catch (Throwable $e) { $denied=true; }
        finally { if ($filter) { remove_filter('query',$filter); } $wpdb->suppress_errors($quiet); }
        $sent=in_array($scenario,['accepted','unknown','transient','rejected','signal-failure','result-failure','settle-failure'],true);
        prepared_expect($transport->calls===($sent?1:0),'preflight exact transport count '.$scenario);
        if (!$sent) {
            $lifecycle=in_array($scenario,['pause-after-begin','pause-final-attempt-after-begin','cancel-after-begin'],true);
            prepared_expect($lifecycle?!$denied:$denied,'submission denied '.$scenario);
            if ($lifecycle) {
                prepared_expect(!$budget->health()['submission_pending'] && $result['attempt_count']===$claim['attempt_count'],
                    'post-reservation lifecycle denial keeps charged attempt and releases only with evidence');
                if ($scenario==='pause-after-begin') {
                    worker_fixture_state($job,'resume'); $next=$w->claim($job,1)[0];
                    prepared_expect($next['attempt_key']!==$claim['attempt_key'],'resume uses a fresh charged attempt');
                    $w->submitPrepared($next,$p,new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport,static fn()=>0),$controls);
                    prepared_expect($transport->calls===1,'resume submits once after prior zero-call denial');
                }
                if ($scenario==='pause-final-attempt-after-begin') {
                    worker_fixture_state($job,'resume');
                    prepared_expect($result['state']==='failed' && $result['reason']==='attempt_limit'
                        && $w->claim($job,1)===[] && $w->completion($job),'charged final attempt pause is terminal and job completes');
                }
            } elseif (str_ends_with($scenario,'after-begin') || str_ends_with($scenario,'lock-wait')) {
                prepared_expect($budget->health()['submission_pending'],'post-begin failure keeps durable latch');
                $stamp=gmdate('Y-m-d H:i:s',prepared_clock());
                $wpdb->update($table,['state'=>'completed','error_code'=>'','updated_at'=>$stamp,'last_complete_at'=>$stamp],['scope_hash'=>$scope]);
                $actor=get_current_user_id(); wp_set_current_user((int)get_option('campaign_fixture_administrator'));
                if ($scenario==='quota-lock-wait') { $now=prepared_clock(); $budget->configure(100,10000,$now,$now+3600); }
                worker_fixture_expire($job); $w->recoverPrepared($claim,$p,$controls); wp_set_current_user($actor);
                prepared_expect(!$budget->health()['submission_pending'] && $budget->health()['breaker']==='operator'
                    && worker_fixture_rows($job)[0]['state']==='unknown' && $transport->calls===0,'zero-call conservative preflight recovery');
            }
            continue;
        }
        $fault=str_ends_with($scenario,'failure');
        prepared_expect($budget->health()['submission_pending']===$fault,'durable latch after '.$scenario);
        if (!$fault) {
            $state=['accepted'=>'accepted','unknown'=>'unknown','transient'=>'retry','rejected'=>'failed'][$scenario];
            prepared_expect($result['state']===$state && $budget->health()['usage']['daily_used']===1,'outcome and no refund '.$scenario);
            if ($scenario==='transient') { prepared_expect($budget->health()['cooldown_active'],'throttle persisted'); }
            if (in_array($scenario,['unknown','rejected'],true)) { prepared_expect($budget->health()['breaker']==='operator','operator hold persisted'); }
        } else {
            prepared_expect($denied,'failure propagates '.$scenario);
            $records=prepared_records($deployment); $before=worker_fixture_rows($job);
            prepared_reject(fn()=>$w->submitPrepared($claim,$p,new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport,static fn()=>0),$controls));
            prepared_expect($transport->calls===1,'no resend after failed persistence');
            $actor=get_current_user_id(); wp_set_current_user((int)get_option('campaign_fixture_administrator'));
            $notReady=new PeraCRM_Campaign_Worker_Preflight(integrated_budget($deployment,static fn()=>false),static fn()=>prepared_clock());
            prepared_reject(fn()=>$w->recoverPrepared($claim,$p,$notReady));
            prepared_expect(worker_fixture_rows($job)===$before && $budget->health()['submission_pending'],'recovery readiness cannot be bypassed');
            worker_fixture_expire($job);
            $recovered=$w->recoverPrepared($claim,$p,$controls);
            prepared_expect(in_array($recovered['state'],['unknown','accepted'],true) && !$budget->health()['submission_pending']
                && $budget->health()['breaker']==='operator','safe recovery keeps recipient fences '.$scenario);
            wp_set_current_user($actor);
            prepared_expect(prepared_records($deployment)===$records && $transport->calls===1,'recovery preserves immutable bytes and zero resends');
        }
    }
    prepared_expect(!peracrm_campaign_queue_dispatch_allowed() && peracrm_sending_budget_service()===null
        && peracrm_ses_suppression_sync_service()===null,'production gates stay disabled');
});
echo "PASS integrated worker budgets, sync, immutable evidence, failed results and safe recovery\n";
