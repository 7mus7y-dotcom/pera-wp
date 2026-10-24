<?php
require_once __DIR__.'/ses-matching-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    if ($mode==='prepare') {
        $v=sns_values(); $v['deployment']='matching-race-'.bin2hex(random_bytes(8));
        $seed=ses_match_seed($v['deployment'],100+array_search($scenario,['duplicate','logical','message','attempt'],true));
        $event=$seed['event'];
        ses_match_receive($v,$v['deployment'],$event,400);
        if ($scenario==='message') { $event['mail']['messageId']='alternative-race-message'; }
        if ($scenario==='attempt') {
            $other=ses_match_seed($v['deployment'],110);
            $message=$event['mail']['messageId']; $event=$other['event']; $event['mail']['messageId']=$message;
        }
        if ($scenario!=='duplicate') { ses_match_receive($v,$v['deployment'],$event,401); }
        $v['snapshot']=ses_match_snapshot(); update_option('ses_matching_race',$v,false); return;
    }
    $v=get_option('ses_matching_race');
    if ($mode==='verify') {
        $winner=json_decode(file_get_contents('/tmp/peracrm-match-winner'),true);
        $loser=json_decode(file_get_contents('/tmp/peracrm-match-loser'),true);
        sns_expect($winner['connection']!==$loser['connection'] && $winner['result']===['matched'],'independent matching SQL connections');
        sns_expect($loser['result']===[in_array($scenario,['message','attempt'],true)?'message_conflict':'matched'],'concurrent matching '.$scenario);
        sns_expect($loser['duration']>0.5,'association/decision creation serialized');
        $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_provider_messages').' WHERE deployment=%s',$v['deployment']),ARRAY_A);
        sns_expect(count($rows)===1,'one immutable association under race');
        sns_expect(ses_match_snapshot()===$v['snapshot'],'race leaves permissions and all worker states unchanged'); return;
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $start=microtime(true);
    if ($mode==='winner') {
        add_filter('query',static function ($sql) {
            if ($sql==='COMMIT') { file_put_contents('/tmp/peracrm-match-held','held'); usleep(1500000); }
            return $sql;
        });
    }
    $store=new PeraCRM_SES_Matcher(sns_verifier($v),$v['deployment'],get_current_blog_id(),ses_event_scope());
    $result=$store->match(ses_event_id($mode==='loser' && $scenario!=='duplicate'?401:400));
    file_put_contents('/tmp/peracrm-match-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$start,'result'=>array_values($result)]));
});
