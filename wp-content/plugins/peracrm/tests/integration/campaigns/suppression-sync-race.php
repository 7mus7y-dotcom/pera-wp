<?php
require_once __DIR__.'/suppression-sync-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    if ($mode==='prepare') {
        $pins=sync_pins(); $pins['mailbox']='sync-race-'.bin2hex(random_bytes(6)).'@example.test';
        update_option('suppression_sync_race',$pins,false); return;
    }
    $pins=get_option('suppression_sync_race'); $mailbox=$pins['mailbox']; unset($pins['mailbox']);
    $reader=static function ($cursor) use ($mode,$scenario,$mailbox) {
        if ($mode==='winner' && (($scenario==='interrupted' && $cursor!==null) || ($scenario==='duplicate' && $cursor===null))) {
            file_put_contents('/tmp/peracrm-sync-held','held');
            if ($scenario==='interrupted') { exit(77); }
            usleep(1500000);
        }
        return sync_page($mailbox,$cursor===null?'second-page':null);
    };
    $svc=sync_service($pins,$reader);
    if ($mode==='verify') {
        $loser=json_decode(file_get_contents('/tmp/peracrm-sync-loser'),true);
        if ($scenario==='duplicate') {
            $winner=json_decode(file_get_contents('/tmp/peracrm-sync-winner'),true);
            sns_expect($winner['connection']!==$loser['connection'] && $winner['result']['state']==='completed'
                && $loser['result']==='sync_overlap','independent overlapping sync excluded');
        } else { sns_expect($loser['result']['pages']===2 && $loser['result']['state']==='completed','process death resumes committed page'); }
        sns_expect((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_email_suppression_events').' WHERE normalized_email=%s',$mailbox))===1,'race duplicate pages one provenance audit');
        sns_expect($svc->health()['state']==='completed','durable race completion'); return;
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()');
    try { $result=$svc->run(2); } catch (PeraCRM_SES_Sync_Error $error) { $result=$error->getMessage(); }
    file_put_contents('/tmp/peracrm-sync-'.$mode,json_encode(['connection'=>$connection,'result'=>$result]));
});
