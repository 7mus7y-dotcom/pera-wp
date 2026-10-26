<?php
require_once __DIR__.'/sending-budget-fixture.php';
peracrm_with_target_blog(static function () {
    global $wpdb;
    budget_actor(); $unchanged=suppress_atomic();
    // Additive upgrade preserves recipient, acceptance, suppression and worker history.
    foreach (['guards','accounts','reservations','events'] as $name) { $wpdb->query('DROP TABLE '.peracrm_table('peracrm_campaign_budget_'.$name)); }
    update_option('peracrm_schema_version',37); update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>37]);
    sns_expect(peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema() && suppress_atomic()===$unchanged,'v38 additive idempotent migration');
    $pins=budget_pins(); $now=1800000000; $ready=true; $service=budget_service($pins,$now,$ready);
    budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_quota');
    foreach ([['1',2,$now,$now+3600],[1,0,$now,$now+3600],[1,2,$now+1,$now+3600]] as $quota) {
        budget_denied(fn()=>$service->configure(...$quota),'budget_quota');
    }
    $service->configure(1,2,$now,$now+3600); $owner=budget_key(); $attempt=budget_key();
    $service->reserve($attempt,$owner);
    $now+=100; budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_exhausted');
    budget_denied(fn()=>$service->finish($attempt,budget_key()),'budget_attempt');
    // A stalled pending permit never ages into free capacity. Completion starts
    // the conservative accounting window, and a repeated finish never shifts it.
    $service->finish($attempt,$owner); $service->finish($attempt,$owner);
    budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_exhausted');
    $now+=2; $second=budget_key(); $service->reserve($second,$owner); $service->finish($second,$owner);
    $now+=2; budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_exhausted');
    $now=1800000000+100+86400; $service->configure(1,2,$now,$now+3600);
    budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_exhausted'); // exact daily boundary inclusive
    $now++; $third=budget_key(); $service->reserve($third,$owner); $service->finish($third,$owner);
    // Missing/stale config, backwards clock, wrong deployment and target all deny.
    $now+=3600; budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_quota');
    $now=1; budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_clock');
    $now=1800200000; $service->configure(5,10,$now,$now+3600);
    budget_denied(fn()=>$service->reserve($attempt,$owner),'budget_storage'); // old attempt never reusable
    $wrong=$pins; $wrong['deployment']='other-installation';
    budget_denied(fn()=>budget_service($wrong,$now,$ready)->reserve(budget_key(),budget_key()),'budget_scope');
    if (is_multisite()) { switch_to_blog(1); try { budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_scope'); } finally { restore_current_blog(); } }
    $ready=false; $before=budget_snapshot();
    budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_readiness');
    sns_expect(budget_snapshot()===$before,'preflight denial has no charge'); $ready=true;
    $service->signal('provider_rate_limit');
    sns_expect($service->health()['cooldown_active'],'active throttle health');
    budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_breaker');
    $now+=60; budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_breaker');
    $now++; sns_expect(!$service->health()['cooldown_active'],'elapsed cooldown is inspectable'); $a=budget_key(); $service->reserve($a,$owner); $service->finish($a,$owner);
    $service->signal('provider_rejected'); $version=$service->health()['breaker_version'];
    $now+=100; budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_breaker');
    $service->signal('provider_rate_limit');
    sns_expect($service->health()['breaker']==='operator','throttle cannot downgrade operator hold');
    budget_denied(fn()=>$service->recover($version),'budget_breaker');
    $version=$service->health()['breaker_version']; $ready=false;
    budget_denied(fn()=>$service->recover($version),'budget_readiness'); $ready=true;
    wp_set_current_user(0); budget_denied(fn()=>$service->recover($version),'budget_authority'); budget_actor();
    $now+=3600; budget_denied(fn()=>$service->recover($version),'budget_quota');
    $service->configure(5,10,$now,$now+3600);
    $reservations=suppress_rows('peracrm_campaign_budget_reservations');
    $service->recover($version);
    $audit=$wpdb->get_row('SELECT * FROM '.peracrm_table('peracrm_campaign_budget_events')." WHERE event_code='operator_recovery' ORDER BY id DESC LIMIT 1",ARRAY_A);
    sns_expect((int)$audit['actor_user_id']===peracrm_get_actor_user_id(),'recovery audit records real actor');
    sns_expect($service->health()['breaker']==='closed' && suppress_rows('peracrm_campaign_budget_reservations')===$reservations,'authorized recovery never releases usage');
    // A breaker commits before a later recipient write fails; neither recipient
    // rollback nor stale owner can erase it. No real transport is constructed.
    $a=budget_key(); $service->reserve($a,$owner); $service->signal('provider_rejected');
    $wpdb->query('START TRANSACTION'); $wpdb->query('ROLLBACK');
    budget_denied(fn()=>$service->finish($a,budget_key()),'budget_attempt');
    sns_expect(budget_service($pins,$now,$ready)->health()['breaker']==='operator','breaker survives failed recipient result and stale owner');
    // Every mutation is rollback/reconnect fenced, including the independent signal audit.
    foreach (['reserve','finish','signal','recover','configure'] as $operation) {
        foreach (['write','commit'] as $point) {
            foreach (['error','reconnect','rollback'] as $fault) {
                $v=budget_pins(); $clock=1800000000; $ok=true; $svc=budget_service($v,$clock,$ok); $svc->configure(10,100,$clock,$clock+3600);
                $key=budget_key(); $own=budget_key();
                if ($operation==='finish') { $svc->reserve($key,$own); }
                if ($operation==='recover') { $svc->signal('provider_rejected'); }
                $old=budget_snapshot(); $fired=false;
                $filter=static function ($sql) use ($wpdb,$point,$fault,&$fired) {
                    $target=$point==='commit'?$sql==='COMMIT':((str_starts_with($sql,'INSERT INTO') || str_starts_with($sql,'UPDATE ')) && str_contains($sql,'campaign_budget_') && !str_contains($sql,' (scope_hash,guard_token)'));
                    if (!$fired && $target) { $fired=true; if ($fault==='reconnect') { $wpdb->close(); $wpdb->db_connect(); } elseif ($fault==='rollback') { $wpdb->query('ROLLBACK'); } else { return 'INVALID_BUDGET_FIXTURE_SQL'; } }
                    return $sql;
                };
                add_filter('query',$filter);
                try {
                    budget_denied(function () use ($operation,$svc,$key,$own,$clock) {
                        match($operation) {
                            'reserve'=>$svc->reserve($key,$own), 'finish'=>$svc->finish($key,$own),
                            'signal'=>$svc->signal('provider_rejected'), 'recover'=>$svc->recover(1),
                            'configure'=>$svc->configure(3,30,$clock,$clock+3000),
                        };
                    },'budget_storage');
                } finally { remove_filter('query',$filter); }
                sns_expect($fired && budget_snapshot()===$old,'budget atomic rollback/reconnect '.$operation.' '.$point.' '.$fault);
            }
        }
    }
    // Audit failure must roll back the breaker/configuration/recovery mutation.
    $old=budget_snapshot(); $fired=false;
    $filter=static function ($sql) use (&$fired) {
        if (!$fired && str_starts_with($sql,'INSERT INTO') && str_contains($sql,'budget_events')) { $fired=true; return 'INVALID_BUDGET_AUDIT'; } return $sql;
    };
    add_filter('query',$filter); try { budget_denied(fn()=>$service->signal('provider_rejected'),'budget_storage'); } finally { remove_filter('query',$filter); }
    sns_expect($fired && budget_snapshot()===$old,'signal audit failure rolls back');
    $wpdb->query('START TRANSACTION');
    try { budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_storage'); sns_expect($wpdb->query('SAVEPOINT caller_budget')!==false && $wpdb->query('RELEASE SAVEPOINT caller_budget')!==false,'ambient transaction untouched'); }
    finally { $wpdb->query('ROLLBACK'); }
    $old=budget_snapshot(); $health=$service->health();
    sns_expect(budget_snapshot()===$old && !str_contains(json_encode($health),$pins['account_id']) && !str_contains(json_encode($health),'@'),'health read-only privacy-safe projection');
    sns_expect(suppress_atomic()===$unchanged,'budget never changes recipient/acceptance/suppression state');
    sns_expect(peracrm_sending_budget_service()===null && !peracrm_campaign_queue_dispatch_allowed(),'all production sending remains disabled');
});
echo "PASS durable sending budget SQL, rolling boundaries, owners, breakers, recovery and faults\n";
