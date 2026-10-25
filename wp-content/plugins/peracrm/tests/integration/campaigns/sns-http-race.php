<?php
require_once __DIR__.'/ses-suppression-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/sns-http.php';
$mode=$args[0]??'';
peracrm_with_target_blog(static function () use ($mode) {
    global $wpdb;
    if ($mode==='prepare') {
        $v=sns_values(); $v['deployment']='http-race-'.bin2hex(random_bytes(8)); $scope=ses_event_scope();
        $seed=ses_match_seed($v['deployment'],1800); $v['mailbox']=$seed['binding']['mailbox'];
        $v['body']=ses_event_body($v,suppress_event($seed,'Bounce'),ses_event_id(1800));
        $v['config']=['enabled'=>true,'blog'=>get_current_blog_id(),'deployment'=>$v['deployment'],'account'=>$v['scope']['account'],
            'region'=>$v['scope']['region'],'topic'=>$v['scope']['topic'],'configuration_set'=>$scope['configuration_set'],'configuration_version'=>$scope['configuration_version']];
        update_option('sns_http_race',$v,false); return;
    }
    $v=get_option('sns_http_race');
    if ($mode==='verify') {
        $a=json_decode(file_get_contents('/tmp/peracrm-http-winner'),true); $b=json_decode(file_get_contents('/tmp/peracrm-http-loser'),true);
        sns_expect($a['status']===202 && $b['status']===202 && $a['connection']!==$b['connection'] && $b['duration']>0.5,'concurrent HTTP pipelines block and safely resume');
        $row=peracrm_campaigns_repository_identity($v['mailbox']); sns_expect((int)$row['hard_bounce']===1,'concurrent HTTP durable suppression');
        $audit=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_email_suppression_events').' WHERE normalized_email=%s',$v['mailbox']));
        sns_expect($audit===1,'concurrent HTTP one logical audit'); return;
    }
    if ($mode==='winner') {
        $armed=false;
        add_filter('query',static function ($sql) use (&$armed) {
            if (str_starts_with($sql,'INSERT INTO') && str_contains($sql,'campaign_sns_receipts')) { $armed=true; }
            if ($armed && $sql==='COMMIT') { $armed=false; file_put_contents('/tmp/peracrm-http-held','held'); usleep(1500000); }
            return $sql;
        });
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $start=microtime(true);
    $http=new PeraCRM_SNS_HTTP($v['config'],static fn()=>$v['pem']);
    $status=$http->handle('POST','text/plain',null,$v['body'],$v['now']);
    file_put_contents('/tmp/peracrm-http-'.$mode,json_encode(['connection'=>$connection,'status'=>$status,'duration'=>microtime(true)-$start]));
});
