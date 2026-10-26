<?php
require_once __DIR__.'/sending-budget-fixture.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    budget_actor(); $unchanged=suppress_atomic();
    // Additive migration from v38 retains all existing budget/recipient evidence.
    $old=budget_snapshot();
    foreach (['accounts'=>['pending_attempt','pending_owner'],'reservations'=>['outcome_code','evidence_hash']] as $table=>$columns) {
        foreach ($columns as $column) { sns_expect($wpdb->query('ALTER TABLE '.peracrm_table('peracrm_campaign_budget_'.$table).' DROP COLUMN '.$column)!==false,'v38 schema fixture'); }
    }
    update_option('peracrm_schema_version',38);
    update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>38]);
    sns_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema() && budget_snapshot()===$old,'v39 additive idempotent migration');
    $pins=budget_pins(); $now=1800000000; $ready=true; $s=budget_service($pins,$now,$ready);
    $s->configure(10,100,$now,$now+3600); $a=budget_key(); $b=budget_key(); $o=budget_key();
    $s->reserve($a,$o); $s->reserve($b,$o); $calls=0;
    $transport=static function () use (&$calls,$wpdb) {
        $calls++;
        // Probe proves no database transaction spans the injected network boundary.
        $quiet=$wpdb->suppress_errors(true); $wpdb->query('SAVEPOINT latch_transport_probe');
        sns_expect($wpdb->query('RELEASE SAVEPOINT latch_transport_probe')===false,'transport outside transaction');
        $wpdb->suppress_errors($quiet);
        return hash('sha256','synthetic accepted outcome');
    };
    $old=budget_snapshot(); $ready=false;
    budget_denied(function () use ($s,$a,$o,$transport) { $s->beginSubmission($a,$o); $transport(); },'budget_readiness');
    sns_expect($calls===0 && budget_snapshot()===$old,'zero calls and unchanged accounting on denied begin'); $ready=true;
    $s->signal('provider_rate_limit');
    budget_denied(function () use ($s,$a,$o,$transport) { $s->beginSubmission($a,$o); $transport(); },'budget_breaker');
    sns_expect($calls===0,'breaker racing reservation prevents transport'); $now+=61;
    $s->beginSubmission($a,$o); $e=$transport();
    budget_denied(fn()=>$s->beginSubmission($a,$o),'budget_pending');
    budget_denied(fn()=>$s->beginSubmission($b,$o),'budget_pending');
    budget_denied(fn()=>$s->reserve(budget_key(),$o),'budget_pending');
    budget_denied(fn()=>$s->finish($a,$o),'budget_pending');
    budget_denied(fn()=>$s->settleSubmission($a,budget_key(),'provider_accepted',$e),'budget_attempt');
    // Inconsistent pre-existing evidence cannot be overwritten by settlement.
    $reservations=peracrm_table('peracrm_campaign_budget_reservations');
    foreach (['evidence_hash'=>$e,'completed_at'=>$now] as $column=>$value) {
        $wpdb->query($wpdb->prepare('UPDATE '.$reservations.' SET '.$column.'=%s WHERE attempt_hash=%s',$value,$a));
        $inconsistent=budget_snapshot();
        budget_denied(fn()=>$s->settleSubmission($a,$o,'provider_accepted',$e),'budget_attempt');
        sns_expect(budget_snapshot()===$inconsistent,'partial evidence retained for investigation');
        $wpdb->query($wpdb->prepare('UPDATE '.$reservations.' SET '.$column.'=%s WHERE attempt_hash=%s',$column==='completed_at'?0:'',$a));
    }
    foreach ([0,$now+1,1] as $partialCompletion) {
        $wpdb->query($wpdb->prepare('UPDATE '.$reservations.' SET outcome_code=%s,evidence_hash=%s,completed_at=%d WHERE attempt_hash=%s',
            'provider_accepted',$e,$partialCompletion,$a));
        $inconsistent=budget_snapshot();
        budget_denied(fn()=>$s->settleSubmission($a,$o,'provider_accepted',$e),'budget_attempt');
        sns_expect(budget_snapshot()===$inconsistent,'matching evidence without valid completion is not settlement');
    }
    $wpdb->query($wpdb->prepare("UPDATE ".$reservations." SET outcome_code='',evidence_hash='',completed_at=0 WHERE attempt_hash=%s",$a));
    $s->settleSubmission($a,$o,'provider_accepted',$e); $settled=budget_snapshot();
    $s->settleSubmission($a,$o,'provider_accepted',$e);
    sns_expect(budget_snapshot()===$settled,'exact durable evidence replay idempotent');
    budget_denied(fn()=>$s->settleSubmission($a,$o,'submission_unknown',$e),'budget_attempt');
    budget_denied(fn()=>$s->settleSubmission($a,$o,'provider_accepted',budget_key()),'budget_attempt');
    budget_denied(fn()=>$s->beginSubmission($a,$o),'budget_attempt');
    $s->beginSubmission($b,$o);
    $s->settleSubmission($a,$o,'provider_accepted',$e);
    sns_expect($s->health()['submission_pending'],'old replay cannot clear a newer latch');
    $s->settleSubmission($b,$o,'submission_unknown',budget_key());
    sns_expect(!$s->health()['submission_pending'] && $s->health()['breaker']==='operator','lost evidence durable unknown creates operator hold');
    budget_denied(fn()=>$s->beginSubmission($b,$o),'budget_breaker');
    $s->recover($s->health()['breaker_version']);
    budget_denied(fn()=>$s->beginSubmission($b,$o),'budget_attempt');
    // Preflight denial and proven rejection after begin still retain capacity.
    foreach (['preflight_denied','provider_rate_limit','provider_rejected'] as $code) {
        $p=budget_pins(); $svc=budget_service($p,$now,$ready); $svc->configure(10,100,$now,$now+3600);
        $key=budget_key(); $svc->reserve($key,$o); $svc->beginSubmission($key,$o);
        $svc->settleSubmission($key,$o,$code,budget_key());
        sns_expect($svc->health()['usage']['daily_used']===1 && !$svc->health()['submission_pending'],'no refund '.$code);
        if ($code==='provider_rate_limit') { sns_expect($svc->health()['cooldown_active'],'settlement durably throttles'); }
        if ($code==='provider_rejected') { sns_expect($svc->health()['breaker']==='operator','settlement durably rejects'); }
    }
    foreach (['error','rollback','reconnect'] as $fault) {
        for ($index=1;$index<=3;$index++) {
            $p=budget_pins(); $svc=budget_service($p,$now,$ready); $svc->configure(10,100,$now,$now+3600);
            $key=budget_key(); $svc->reserve($key,$o); $before=budget_snapshot(); $n=0; $fired=false;
            $filter=static function ($sql) use ($wpdb,$fault,$index,&$n,&$fired) {
                $target=$sql==='COMMIT' || ((str_starts_with($sql,'INSERT INTO') || str_starts_with($sql,'UPDATE '))
                    && str_contains($sql,'campaign_budget_') && !str_contains($sql,' (scope_hash,guard_token)'));
                if ($target && ++$n===$index) {
                    $fired=true;
                    if ($fault==='rollback') { $wpdb->query('ROLLBACK'); }
                    elseif ($fault==='reconnect') { $wpdb->close(); $wpdb->db_connect(); }
                    else { return 'INVALID_LATCH_BEGIN'; }
                }
                return $sql;
            };
            add_filter('query',$filter);
            try { budget_denied(function () use ($svc,$key,$o,$transport) { $svc->beginSubmission($key,$o); $transport(); },'budget_storage'); }
            finally { remove_filter('query',$filter); }
            sns_expect($fired && budget_snapshot()===$before && $calls===1,'begin failure denies transport '.$fault.' '.$index);
        }
    }
    $p=budget_pins(); $svc=budget_service($p,$now,$ready); $svc->configure(1,1,$now,$now+3600);
    $key=budget_key(); $svc->reserve($key,$o);
    budget_denied(fn()=>$svc->beginSubmission($key,budget_key()),'budget_attempt');
    budget_denied(fn()=>$svc->reserve(budget_key(),$o),'budget_exhausted');
    $now+=3600;
    budget_denied(function () use ($svc,$key,$o,$transport) { $svc->beginSubmission($key,$o); $transport(); },'budget_quota');
    sns_expect($calls===1,'stale quota zero transport');
    // Rollback/reconnection/commit failures at EVERY mutation, including the
    // signal audit and the latch clear. After failures a fresh service must deny.
    foreach (['provider_rejected','provider_rate_limit','provider_accepted','submission_unknown'] as $code) {
        foreach (['error','rollback','reconnect'] as $fault) {
            for ($index=1;$index<=($code==='provider_accepted'?4:6);$index++) {
                $p=budget_pins(); $svc=budget_service($p,$now,$ready); $svc->configure(10,100,$now,$now+3600);
                $key=budget_key(); $own=budget_key(); $svc->reserve($key,$own); $svc->beginSubmission($key,$own);
                $old=budget_snapshot(); $n=0; $fired=false;
                $filter=static function ($sql) use ($wpdb,$fault,$index,&$n,&$fired) {
                    $target=$sql==='COMMIT' || ((str_starts_with($sql,'INSERT INTO') || str_starts_with($sql,'UPDATE '))
                        && str_contains($sql,'campaign_budget_') && !str_contains($sql,' (scope_hash,guard_token)'));
                    if ($target && ++$n===$index) {
                        $fired=true;
                        if ($fault==='rollback') { $wpdb->query('ROLLBACK'); }
                        elseif ($fault==='reconnect') { $wpdb->close(); $wpdb->db_connect(); }
                        else { return 'INVALID_LATCH_SQL'; }
                    }
                    return $sql;
                };
                add_filter('query',$filter);
                try { budget_denied(fn()=>$svc->settleSubmission($key,$own,$code,budget_key()),'budget_storage'); }
                finally { remove_filter('query',$filter); }
                sns_expect($fired && budget_snapshot()===$old,'atomic latch settlement '.$code.' '.$fault.' '.$index);
                $fresh=budget_service($p,$now,$ready);
                budget_denied(fn()=>$fresh->reserve(budget_key(),$own),'budget_pending');
                $fresh->signal('provider_rejected');
                budget_denied(fn()=>$fresh->recover($fresh->health()['breaker_version']),'budget_pending');
                // Durable recovery evidence must be committed before latch clears.
                $fresh->settleSubmission($key,$own,'submission_unknown',budget_key());
                sns_expect(!$fresh->health()['submission_pending'] && $fresh->health()['breaker']==='operator','durable unknown recovery');
            }
        }
    }
    // A later recipient-result rollback cannot erase independently settled signal.
    $p=budget_pins(); $svc=budget_service($p,$now,$ready); $svc->configure(10,100,$now,$now+3600);
    $key=budget_key(); $svc->reserve($key,$o); $svc->beginSubmission($key,$o);
    $svc->settleSubmission($key,$o,'provider_rejected',budget_key());
    $wpdb->query('START TRANSACTION'); $wpdb->query('ROLLBACK');
    sns_expect(budget_service($p,$now,$ready)->health()['breaker']==='operator','result rollback preserves independent signal');
    sns_expect(suppress_atomic()===$unchanged,'latch never mutates recipient/event correlation or output bytes');
    sns_expect(peracrm_sending_budget_service()===null && !peracrm_campaign_queue_dispatch_allowed(),'production remains blocked');
});
echo "PASS durable submission latch SQL, evidence, signal outages and recovery\n";
