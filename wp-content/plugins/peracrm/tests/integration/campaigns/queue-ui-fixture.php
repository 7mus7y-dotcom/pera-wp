<?php
/** CLI-only synthetic seeds. No worker/provider execution or browser outcome mutation. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('Disposable CLI only.'); }
peracrm_with_target_blog(static function () {
    global $wpdb;
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    $zone=date_default_timezone_get(); date_default_timezone_set('Pacific/Honolulu');
    $explicit=peracrm_campaign_queue_ui_schedule('2099-06-01T12:34');
    if (peracrm_campaign_queue_schedule_utc($explicit)!=='2099-06-01 09:34:00') { throw new RuntimeException('Istanbul UTC conversion depends on defaults.'); }
    foreach (['2099-02-30T12:00','2020-01-01T12:00','2099-06-01T12:34Z','2099-06-01T12:34:00'] as $invalid) {
        $denied=false; try { peracrm_campaign_queue_ui_schedule($invalid); } catch (InvalidArgumentException $e) { $denied=true; }
        if (!$denied) { throw new RuntimeException('Invalid schedule accepted.'); }
    }
    date_default_timezone_set($zone);
    $previous=peracrm_campaign_configuration(true);
    $config=$previous['document'];
    $config['senders']=[['id'=>'queue-ui','name'=>'Synthetic queue UI','email'=>'queue-ui@example.test','reply_to'=>'reply@example.test','approved'=>true]];
    $config['footer']='Synthetic queue UI footer'; $config['footer_approved']=true;
    if (is_wp_error(peracrm_campaign_configuration_save($previous['revision'],$config))) { throw new RuntimeException('Queue UI config failed.'); }
    $seeds=[];
    foreach (['desktop','mobile','outcomes'] as $label) {
        $doc=['internal_name'=>'Queue UI '.$label.' '.str_repeat('LongCampaignLabel',8),'subject'=>'Synthetic queue UI','sender_id'=>'queue-ui','language'=>'en','main_text'=>'Synthetic queue UI content','audience'=>['version'=>1,'topic'=>'queue']];
        $draft=peracrm_campaign_draft_save(0,0,$doc);
        if (is_wp_error($draft)) { throw new RuntimeException('Queue UI seed draft failed.'); }
        $id=(int)$draft['id'];
        $review=peracrm_campaign_snapshot_review($id,1,$draft['document']);
        $capture=peracrm_campaign_snapshot_capture($id,1,$draft['document'],$review['review_proof'],wp_create_nonce('peracrm_campaign_snapshot_'.$id.'_1'));
        $nonce=wp_create_nonce('peracrm_campaign_freeze_'.$id);
        $attempt=peracrm_campaign_freeze_start($id,1,(int)$capture['id'],$capture['content_hash'],$draft['document'],$nonce);
        for ($i=0;$i<100 && !is_wp_error($attempt) && !(int)$attempt['complete'];$i++) { $attempt=peracrm_campaign_freeze_continue($id,(int)$attempt['id'],(int)$attempt['version'],$nonce); }
        if (is_wp_error($attempt) || !(int)$attempt['complete']) { throw new RuntimeException('Queue UI seed freeze failed.'); }
        $attempt=peracrm_campaign_freeze_publish($id,(int)$attempt['id'],(int)$attempt['version'],$nonce);
        if (is_wp_error($attempt)) { throw new RuntimeException('Queue UI seed publication failed.'); }
        $revision=peracrm_campaign_frozen_get($id,1);
        $seeds[$label]=['campaign'=>$id,'revision'=>(int)$revision['id']];
        if ($label==='outcomes') {
            $qnonce=wp_create_nonce('peracrm_campaign_queue_'.$id);
            $job=peracrm_campaign_queue_prepare($id,(int)$revision['id'],$qnonce);
            for ($i=0;$i<10 && !is_wp_error($job) && !(int)$job['complete'];$i++) { $job=peracrm_campaign_queue_materialize($id,(int)$job['id'],(int)$job['version'],$qnonce); }
            if (is_wp_error($job) || !(int)$job['complete']) { throw new RuntimeException('Queue UI outcome seed preparation failed.'); }
            // Synthetic visualization rows only, not a fake provider acceptance or worker invocation.
            $rows=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.peracrm_table('peracrm_campaign_deliveries').' WHERE job_id=%d ORDER BY id LIMIT 5',$job['id']));
            foreach (['unknown','failed','retry','claimed','inflight'] as $i=>$state) {
                $wpdb->update(peracrm_table('peracrm_campaign_deliveries'),['state'=>$state,'lease_until'=>$state==='claimed' ? '2099-01-01 00:00:00' : ($state==='inflight' ? '2020-01-01 00:00:00' : null)],['id'=>$rows[$i]]);
            }
            $seeds[$label]['job']=(int)$job['id'];
        }
    }
    $current=peracrm_campaign_configuration(true);
    if (is_wp_error(peracrm_campaign_configuration_save($current['revision'],$previous['document']))) { throw new RuntimeException('Queue UI config restoration failed.'); }
    $actor=get_current_user_id();
    wp_set_current_user((int)get_option('campaign_fixture_employee'));
    $denied=false; try { peracrm_campaign_queue_ui_summary($seeds['outcomes']['campaign'],$seeds['outcomes']['job']); } catch (Throwable $e) { $denied=true; }
    if (!$denied) { throw new RuntimeException('Employee aggregate read accepted.'); }
    wp_set_current_user($actor);
    update_option('peracrm_queue_ui_seeds',$seeds,false);
    file_put_contents('/var/www/html/queue-ui-fixture.json',wp_json_encode($seeds));
    echo "PASS queue UI CLI-only published seeds and synthetic unresolved states; no worker execution\n";
});
