<?php
require_once __DIR__.'/ses-suppression-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/event-processing.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    if ($mode==='prepare') {
        $v=sns_values(); $v['deployment']='processing-race-'.bin2hex(random_bytes(8));
        $seed=ses_match_seed($v['deployment'],1980); $v['mailbox']=$seed['binding']['mailbox'];
        $id=ses_event_id(1980); (new PeraCRM_SNS_Receipts(sns_verifier($v),$v['deployment'],get_current_blog_id()))->receive(ses_event_body($v,suppress_event($seed,'Bounce'),$id),$v['now']);
        update_option('event_processing_race',$v,false); return;
    }
    $v=get_option('event_processing_race');
    $service=new PeraCRM_SES_Processing(sns_verifier($v),$v['deployment'],get_current_blog_id(),ses_event_scope());
    if ($mode==='verify') {
        $winner=$scenario==='duplicate'?json_decode(file_get_contents('/tmp/peracrm-processing-winner'),true):null; $loser=json_decode(file_get_contents('/tmp/peracrm-processing-loser'),true);
        if ($scenario==='duplicate') {
            sns_expect($winner['connection']!==$loser['connection'] && $winner['result']['completed']===1 && $loser['result']==='overlap','independent duplicate workers excluded');
        } else { sns_expect($loser['result']['completed']===1,'killed pipeline resumes durable pending progress'); }
        $row=peracrm_campaigns_repository_identity($v['mailbox']); sns_expect((int)$row['hard_bounce']===1,'race durable suppression');
        sns_expect((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_email_suppression_events').' WHERE normalized_email=%s',$v['mailbox']))===1,'race one logical audit');
        sns_expect($service->redrive(1)['processed']===0,'race completion is durable'); return;
    }
    if ($mode==='winner') {
        $armed=false;
        add_filter('query',static function ($sql) use (&$armed,$scenario) {
            if (str_starts_with($sql,'INSERT INTO') && str_contains($sql,'ses_normalizations ')) { $armed=true; }
            if ($armed && $sql==='COMMIT') {
                $armed=false; file_put_contents('/tmp/peracrm-processing-held','held');
                if ($scenario==='interrupted') { exit(77); }
                usleep(1500000);
            }
            return $sql;
        });
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()');
    try { $result=$service->redrive(1); } catch (PeraCRM_SNS_Error $e) { $result='overlap'; }
    file_put_contents('/tmp/peracrm-processing-'.$mode,json_encode(['connection'=>$connection,'result'=>$result]));
});
