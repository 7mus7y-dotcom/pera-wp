<?php
require_once __DIR__.'/ses-suppression-fixture.php';
require_once __DIR__.'/withdrawal-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    $scenarios=['duplicate','logical','bounce-complaint','complaint-bounce','bounce-withdraw','withdraw-bounce','complaint-withdraw','withdraw-complaint','triple','bounce-delete','delete-bounce','bounce-identity','identity-bounce'];
    if (!in_array($scenario,$scenarios,true)) { throw new RuntimeException('Unknown suppression race'); }
    if ($mode==='prepare') {
        $v=sns_values(); $v['deployment']='suppress-race-'.bin2hex(random_bytes(8));
        $number=1300+array_search($scenario,$scenarios,true); $seed=ses_match_seed($v['deployment'],$number);
        $v['seed']=withdrawal_seed($seed['binding']['subscriber_id'],'matching-'.$number);
        $wpdb->update(peracrm_table('peracrm_subscribers'),['subscription_state'=>'subscribed','evidence_ref'=>'preserve-consent-evidence'],['id'=>$seed['binding']['subscriber_id']]);
        $v['bounce']=suppress_receive($v,suppress_event($seed,'Bounce'),1300);
        $v['complaint']=suppress_receive($v,suppress_event($seed,'Complaint'),1301);
        $v['logical']=suppress_receive($v,suppress_event($seed,'Bounce'),1302);
        $v['protected']=[];
        foreach (['peracrm_campaign_recipient_tokens','peracrm_campaign_recipient_identities','peracrm_campaign_submission_attempts',
            'peracrm_campaign_submission_outputs','peracrm_campaign_deliveries','peracrm_campaign_queue_jobs','peracrm_campaign_worker_events'] as $table) { $v['protected'][$table]=suppress_rows($table); }
        update_option('ses_suppression_race',$v,false); return;
    }
    $v=get_option('ses_suppression_race');
    if ($mode==='verify') {
        $a=json_decode(file_get_contents('/tmp/peracrm-suppress-winner'),true);
        $b=json_decode(file_get_contents('/tmp/peracrm-suppress-loser'),true);
        sns_expect($a['connection']!==$b['connection'] && $a['ok'] && $b['ok'] && $b['duration']>0.5,'independent blocking suppression connections '.$scenario);
        if ($scenario==='triple') {
            $c=json_decode(file_get_contents('/tmp/peracrm-suppress-third'),true);
            sns_expect($c['ok'] && $c['duration']>0.5 && !in_array($c['connection'],[$a['connection'],$b['connection']],true),'three independent competing mailbox owners');
        }
        $historical=$v['seed']['binding']['mailbox'];
        $identityRace=str_contains($scenario,'delete') || str_contains($scenario,'identity');
        $row=peracrm_campaigns_repository_identity($historical);
        if ($identityRace) {
            $suppression=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email=%s',$historical),ARRAY_A);
            sns_expect(!$row && (int)$suppression['hard_bounce']===1,'concurrent deletion/mailbox change retains historical denial without subscriber recreation');
            $replacement=peracrm_campaigns_repository_identity('replacement-'.$v['seed']['binding']['subscriber_id'].'@example.test');
            sns_expect(!$replacement || empty($replacement['hard_bounce']),'concurrent identity replacement does not receive historical denial');
            foreach ($v['protected'] as $table=>$rows) {
                if ($table!=='peracrm_campaign_recipient_identities') { sns_expect(suppress_rows($table)===$rows,'identity race preserves tokens attempts outputs and retry state '.$table); }
            }
            return;
        }
        $withdraw=str_contains($scenario,'withdraw') || $scenario==='triple';
        $complaint=str_contains($scenario,'complaint') || $scenario==='triple';
        $bounce=!in_array($scenario,['complaint-withdraw','withdraw-complaint'],true);
        sns_expect((int)$row['hard_bounce']===(int)$bounce && (int)$row['complaint']===(int)$complaint && (int)$row['unsubscribe']===(int)$withdraw,'all distinct valid reasons survive '.$scenario);
        sns_expect($row['evidence_ref']==='preserve-consent-evidence' && $row['subscription_state']===($withdraw?'unsubscribed':'subscribed'),'no consent grant/evidence replacement '.$scenario);
        foreach ($v['protected'] as $table=>$rows) { sns_expect(suppress_rows($table)===$rows,'no retry/identity/output change '.$table); }
        $audit=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppression_events').' WHERE normalized_email=%s',$row['normalized_email']),ARRAY_A);
        sns_expect(count($audit)===(int)$bounce+(int)$complaint+(int)$withdraw,'one audit per logical suppression/withdrawal');
        $scope=hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$v['deployment'],'blog'=>get_current_blog_id()]+$v['scope'],JSON_THROW_ON_ERROR));
        $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_ses_applications').' WHERE scope_hash=%s',$scope));
        sns_expect($count===(int)$bounce+(int)$complaint,'one application per logical event'); return;
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $start=microtime(true);
    if ($mode==='winner') {
        $armed=false; $held=false;
        add_filter('query',static function ($sql) use (&$armed,&$held) {
            if (str_starts_with($sql,'INSERT INTO') && str_contains($sql,'peracrm_email_suppressions')) { $armed=true; }
            // Token withdrawal first commits a read-only locator transaction.
            // Only the actual authoritative mailbox owner may release the barrier.
            if ($armed && !$held && $sql==='COMMIT') {
                $held=true; file_put_contents('/tmp/peracrm-suppress-held','held'); usleep(1500000);
            }
            return $sql;
        });
    }
    if (in_array($scenario,['duplicate','logical'],true)) { $operation='bounce'; }
    elseif ($scenario==='triple') { $operation=['winner'=>'bounce','loser'=>'complaint','third'=>'withdraw'][$mode]; }
    else { $parts=explode('-',$scenario); $operation=$parts[$mode==='winner'?0:1]; }
    if ($operation==='delete') {
        wp_set_current_user((int)get_option('campaign_fixture_manager'));
        $ok=!is_wp_error(peracrm_campaigns_delete_subscriber($v['seed']['binding']['mailbox']));
    } elseif ($operation==='identity') {
        // Synthetic mailbox editor participates in the authoritative old-mailbox
        // mutex and advances all retained identities before committing.
        $ok=peracrm_campaigns_repository_mutate($v['seed']['binding']['mailbox'],static function ($subscriber) {
            global $wpdb;
            $replacement='replacement-'.$subscriber['id'].'@example.test';
            peracrm_campaigns_repository_replace_identity((int)$subscriber['id'],$replacement,true);
            return $wpdb->update(peracrm_table('peracrm_subscribers'),['email'=>$replacement,'normalized_email'=>$replacement],['id'=>$subscriber['id']])===1;
        });
    } elseif ($operation==='withdraw') { $ok=withdrawal_store()->withdrawToken($v['seed']['token'],withdrawal_keys(),'fixture-withdrawal'); }
    else {
        $id=$v[$scenario==='logical' && $mode==='loser'?'logical':$operation];
        $ok=array_values(suppress_service($v)->apply($id))===['applied'];
    }
    file_put_contents('/tmp/peracrm-suppress-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$start,'ok'=>$ok]));
});
