<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/ses-events-fixture.php';
require_once __DIR__.'/withdrawal-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    if ($mode==='prepare') {
        $v=sns_values(); $v['deployment']='ses-race-'.bin2hex(random_bytes(8));
        $v['seed']=withdrawal_seed(24000+array_search($scenario,['duplicate','logical','conflict','withdraw'],true),'ses-race-'.$scenario);
        peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_subscribers'),
            ['evidence_ref'=>'fixture-evidence','subscription_state'=>'subscribed'],['id'=>$v['seed']['binding']['subscriber_id']]));
        $v['event']=ses_event('Bounce');
        $v['event']['mail']['destination']=[$v['seed']['binding']['mailbox']];
        $v['event']['bounce']['bouncedRecipients']=[['emailAddress'=>$v['seed']['binding']['mailbox']]];
        $store=new PeraCRM_SNS_Receipts(sns_verifier($v),$v['deployment'],get_current_blog_id());
        $store->receive(ses_event_body($v,$v['event'],ses_event_id(100)),$v['now']);
        if (in_array($scenario,['logical','conflict'],true)) {
            $event=$v['event'];
            if ($scenario==='conflict') { $event['bounce']['bounceType']='Transient'; }
            $store->receive(ses_event_body($v,$event,ses_event_id(101)),$v['now']);
        }
        update_option('ses_normalization_race',$v,false); return;
    }
    $v=get_option('ses_normalization_race');
    $scope=hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$v['deployment'],'blog'=>get_current_blog_id()]+$v['scope'],JSON_THROW_ON_ERROR));
    if ($mode==='verify') {
        $winner=json_decode(file_get_contents('/tmp/peracrm-ses-winner'),true);
        $loser=json_decode(file_get_contents('/tmp/peracrm-ses-loser'),true);
        sns_expect($winner['connection']!==$loser['connection'] && $winner['result']==='matching_pending','independent SQL normalization connections');
        sns_expect($loser['result']===($scenario==='conflict'?'event_conflict':($scenario==='withdraw'?'withdrawn':'matching_pending')),'concurrent '.$scenario.' outcome');
        if ($scenario!=='withdraw') { sns_expect($loser['duration']>0.5,'concurrent normalization blocks on immutable identity'); }
        $facts=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_ses_events').' WHERE scope_hash=%s',$scope),ARRAY_A);
        sns_expect(count($facts)===1 && json_decode($facts[0]['fact_json'],true)['bounce_type']==='Permanent','one original logical fact retained');
        if ($scenario==='withdraw') {
            $subscriber=peracrm_campaigns_repository_identity($v['seed']['binding']['mailbox']);
            sns_expect($subscriber['subscription_state']==='unsubscribed' && (int)$subscriber['unsubscribe']===1
                && $subscriber['evidence_ref']==='fixture-evidence','concurrent normalization preserves withdrawal and consent evidence');
        }
        return;
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $start=microtime(true);
    if ($mode==='winner') {
        add_filter('query',static function ($sql) {
            if ($sql==='COMMIT') { file_put_contents('/tmp/peracrm-ses-held','held'); usleep(1500000); }
            return $sql;
        });
    }
    if ($mode==='loser' && $scenario==='withdraw') {
        $result=withdrawal_store()->withdrawToken($v['seed']['token'],withdrawal_keys(),'fixture-withdrawal')?'withdrawn':'denied';
    } else {
        $store=new PeraCRM_SES_Quarantine(sns_verifier($v),$v['deployment'],get_current_blog_id(),ses_event_scope());
        $result=$store->normalize(ses_event_id($mode==='loser' && $scenario!=='duplicate'?101:100));
    }
    file_put_contents('/tmp/peracrm-ses-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$start,'result'=>$result]));
});
