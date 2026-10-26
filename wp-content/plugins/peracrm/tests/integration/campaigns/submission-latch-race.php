<?php
require_once __DIR__.'/sending-budget-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    budget_actor(); $now=1800000000; $ready=true;
    $key=hash('sha256','latch-campaign-'.$mode); $owner=hash('sha256','latch-owner-'.$mode);
    if ($mode==='prepare') {
        $pins=budget_pins(); update_option('submission_latch_race',$pins,false);
        $svc=budget_service($pins,$now,$ready); $svc->configure(10,100,$now,$now+3600);
        foreach (['winner','loser'] as $who) { $svc->reserve(hash('sha256','latch-campaign-'.$who),hash('sha256','latch-owner-'.$who)); }
        return;
    }
    $pins=get_option('submission_latch_race'); $svc=budget_service($pins,$now,$ready);
    if ($mode==='verify') {
        $winner=json_decode(file_get_contents('/tmp/peracrm-latch-winner'),true);
        $loser=json_decode(file_get_contents('/tmp/peracrm-latch-loser'),true);
        sns_expect($winner['connection']!==$loser['connection'],'latch independent processes');
        sns_expect($winner['calls']===($scenario==='crash-before'?0:1) && $loser['calls']===0,'zero denied transport calls');
        sns_expect($loser['result']==='budget_pending' && $svc->health()['submission_pending'],'committed latch blocks concurrent campaigns after lock release');
        $now+=172800; $svc->configure(10,100,$now,$now+3600);
        budget_denied(fn()=>$svc->reserve(budget_key(),budget_key()),'budget_pending');
        $attempt=hash('sha256','latch-campaign-winner'); $own=hash('sha256','latch-owner-winner');
        // No time/lease/quota refresh or process restart clears the latch.
        $svc->settleSubmission($attempt,$own,'submission_unknown',budget_key());
        sns_expect($svc->health()['breaker']==='operator' && !$svc->health()['submission_pending'],'durable unknown recovery evidence');
        budget_denied(fn()=>$svc->beginSubmission(hash('sha256','latch-campaign-loser'),hash('sha256','latch-owner-loser')),'budget_breaker');
        return;
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $calls=0; $result='';
    try {
        $svc->beginSubmission($key,$owner);
        if ($scenario!=='crash-before') { $calls++; } // injected synthetic transport, no HTTP
        if ($scenario==='signal-outage') {
            $filter=static function ($sql) {
                return str_starts_with($sql,'INSERT INTO') && str_contains($sql,'budget_events')?'INVALID_LATCH_SIGNAL':$sql;
            };
            add_filter('query',$filter);
            try { $svc->settleSubmission($key,$owner,'provider_rejected',budget_key()); }
            finally { remove_filter('query',$filter); }
        }
        $result='started';
    } catch (PeraCRM_Sending_Budget_Error $e) { $result=$e->getMessage(); }
    file_put_contents('/tmp/peracrm-latch-'.$mode,json_encode(['connection'=>$connection,'calls'=>$calls,'result'=>$result]));
    if ($mode==='winner') {
        file_put_contents('/tmp/peracrm-latch-held','committed');
        if ($scenario==='concurrent') { usleep(1500000); } else { exit(79); }
    }
});
