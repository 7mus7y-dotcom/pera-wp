<?php
require_once __DIR__.'/sending-budget-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    budget_actor(); $now=1800000000; $ready=true;
    if ($mode==='prepare') {
        $pins=budget_pins(); update_option('sending_budget_race',$pins,false);
        budget_service($pins,$now,$ready)->configure($scenario==='daily'?100:1,$scenario==='daily'?1:2,$now,$now+3600); return;
    }
    $pins=get_option('sending_budget_race');
    if ($scenario==='crash' && $mode!=='winner') { $now+=172800; }
    $service=budget_service($pins,$now,$ready);
    $key=hash('sha256','different-campaign-attempt-'.$mode); $owner=hash('sha256','owner-'.$mode);
    if ($mode==='verify') {
        $winner=json_decode(file_get_contents('/tmp/peracrm-budget-winner'),true);
        $loser=json_decode(file_get_contents('/tmp/peracrm-budget-loser'),true);
        sns_expect($winner['connection']!==$loser['connection'],'budget uses independent connections');
        if (in_array($scenario,['overlap','daily'],true)) {
            sns_expect($winner['result']==='reserved' && $loser['result']==='budget_overlap','concurrent campaigns cannot over-reserve account');
        } else { sns_expect($loser['result']==='budget_exhausted','crashed worker reservation never ages free'); }
        budget_denied(fn()=>$service->reserve(budget_key(),budget_key()),'budget_exhausted');
        sns_expect(!peracrm_campaign_queue_dispatch_allowed(),'race preserves production block'); return;
    }
    if ($mode==='winner' && in_array($scenario,['overlap','daily'],true)) {
        $filter=static function ($sql) {
            if (str_starts_with($sql,'INSERT INTO') && str_contains($sql,'budget_reservations')) {
                file_put_contents('/tmp/peracrm-budget-held','held'); usleep(1500000);
            } return $sql;
        }; add_filter('query',$filter);
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()');
    try { $service->reserve($key,$owner); $result='reserved'; } catch (PeraCRM_Sending_Budget_Error $e) { $result=$e->getMessage(); }
    file_put_contents('/tmp/peracrm-budget-'.$mode,json_encode(['connection'=>$connection,'result'=>$result]));
    if ($mode==='winner' && $scenario==='crash') {
        // Refresh quota before process death so the later probe tests pending usage.
        $now+=172800; $service->configure(1,2,$now,$now+3600);
        file_put_contents('/tmp/peracrm-budget-held','held'); exit(78);
    }
});
