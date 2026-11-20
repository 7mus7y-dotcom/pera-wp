<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
function review_expect($ok,string $label): void { if (!$ok) { throw new RuntimeException('FAIL delivery review: '.$label); } echo 'PASS delivery review: '.$label."\n"; }
$admin=peracrm_with_target_blog(static fn()=>(int)get_option('campaign_fixture_administrator'));
$employee=peracrm_with_target_blog(static fn()=>(int)get_option('campaign_fixture_employee'));
wp_set_current_user($admin);
peracrm_with_target_blog(static function () use ($admin,$employee) {
    global $wpdb;
    $create=static function ($mailbox,$reason=null) {
        $id=peracrm_campaigns_save_subscriber($mailbox,['preferred_language'=>'en']);
        review_expect(is_int($id),'synthetic subscriber created');
        review_expect(peracrm_campaigns_record_consent($mailbox,'subscribed',[
            'source'=>'owner_attested_zoho_migration','source_ref'=>'review-zoho','evidence_ref'=>'review-evidence','policy_version'=>'v1',
            'occurred_at'=>'2026-01-01 00:00:00'])===true,'synthetic evidence recorded');
        if ($reason) { review_expect(!is_wp_error(peracrm_campaigns_add_suppression($mailbox,$reason,['source'=>'synthetic_review'])),'synthetic suppression'); }
        return $id;
    };
    $mail='review-hard@example.test'; $id=$create($mail,'hard_bounce');
    $client=wp_insert_post(['post_type'=>'crm_client','post_status'=>'draft','post_title'=>'Review preserved client','post_author'=>$admin]);
    $wpdb->update(peracrm_table('peracrm_subscribers'),['client_id'=>$client],['id'=>$id]);
    $beforeClient=get_post($client)->to_array();
    $identity=peracrm_campaigns_repository_identity($mail); $version=(int)$identity['version'];
    $historyBefore=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_subscription_events').' WHERE normalized_email=%s',$mail));
    $denials=$wpdb->get_results('SELECT * FROM '.peracrm_table('peracrm_email_suppression_events').' ORDER BY id',ARRAY_A);
    $row=peracrm_delivery_review_list('hard_bounce','review-hard')['rows'][0];
    review_expect($row['cleanup_candidate'] && (int)$row['subscriber_id']===$id && !empty($row['client_id']),'hard bounce flagged and authorized client linked');
    review_expect(peracrm_delivery_review_list('complaint','review-hard')['total']===0,'complaints kept separate');
    review_expect(peracrm_delivery_review_list('hard_bounce','review-%')['total']===0,'search wildcard treated literally');
    review_expect(is_wp_error(peracrm_delivery_review_remove($mail,$id,$version,false)),'unconfirmed removal rejected');
    review_expect(is_wp_error(peracrm_delivery_review_remove($mail,$id,$version+1,true)),'stale profile version rejected');
    wp_set_current_user($employee);
    review_expect(is_wp_error(peracrm_delivery_review_remove($mail,$id,$version,true)),'employee cleanup rejected');
    $denied=false; try { peracrm_delivery_review_list(); } catch (Throwable $e) { $denied=true; }
    review_expect($denied,'employee cannot enumerate suppressed addresses');
    wp_set_current_user($admin);
    review_expect(peracrm_delivery_review_remove($mail,$id,$version,true)===true,'explicit cleanup removes current profile');
    review_expect(peracrm_campaigns_repository_identity($mail)===null,'profile no longer listed');
    $x=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email=%s',$mail),ARRAY_A);
    review_expect((int)$x['hard_bounce']===1 && !peracrm_campaigns_is_eligible($mail),'suppression persists after profile removal');
    review_expect(get_post($client)->to_array()===$beforeClient,'linked CRM client is unchanged');
    review_expect($denials===$wpdb->get_results('SELECT * FROM '.peracrm_table('peracrm_email_suppression_events').' ORDER BY id',ARRAY_A),'suppression audit unchanged');
    review_expect((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_subscription_events').' WHERE normalized_email=%s',$mail))===$historyBefore+1,'consent history preserved with one deletion audit');
    review_expect(peracrm_delivery_review_remove($mail,$id,$version,true)===true,'repeated removal is idempotent');
    $replacement=peracrm_campaigns_save_subscriber($mail);
    review_expect(is_int($replacement) && $replacement!==$id && !peracrm_campaigns_is_eligible($mail),'recreation cannot clear hard-bounce block');
    review_expect(is_wp_error(peracrm_delivery_review_remove($mail,$id,$version,true)),'stale request cannot delete a replacement profile');
    $clean='review-clean@example.test'; $cleanID=$create($clean);
    $cleanRow=peracrm_campaigns_repository_identity($clean);
    review_expect(is_wp_error(peracrm_delivery_review_remove($clean,$cleanID,(int)$cleanRow['version'],true)),'unsuppressed profile cannot be removed through cleanup');
    $temp='review-temporary@example.test'; $tempID=$create($temp);
    $fact=['type'=>'Bounce','bounce_type'=>'Transient','subtype'=>'MailboxFull','recipient'=>$temp,'occurred_at'=>'2026-01-02T00:00:00Z'];
    $json=wp_json_encode($fact); $scope=hash('sha256','review-temporary-fixture');
    $wpdb->insert(peracrm_table('peracrm_campaign_ses_events'),['scope_hash'=>$scope,'event_key'=>hash('sha256',$json),'fact_json'=>$json,'fact_hash'=>hash('sha256',$json),'recorded_at'=>gmdate('Y-m-d H:i:s')]);
    peracrm_campaigns_db_require(true);
    $temporary=peracrm_delivery_review_list('temporary','review-temporary');
    review_expect($temporary['total']===1 && !$temporary['rows'][0]['cleanup_candidate'] && $temporary['rows'][0]['feedback_subtype']==='MailboxFull','temporary feedback shown with subtype and no removal control');
    $ui=$create('review-ui@example.test','hard_bounce');
    $create('review-complaint@example.test','complaint');
    $create('review-unsubscribe@example.test','unsubscribe');
    review_expect(peracrm_delivery_review_list('complaint','review-complaint')['total']===1
        && peracrm_delivery_review_list('unsubscribe','review-unsubscribe')['total']===1,'complaint and unsubscribe categories visible');
    // Minimal synthetic journals exercise SELECT-only paused-job review; no worker or provider invocation.
    $j=peracrm_table('peracrm_campaign_queue_jobs'); $d=peracrm_table('peracrm_campaign_deliveries');
    $stamp=gmdate('Y-m-d H:i:s'); $hash=hash('sha256','review-job');
    $wpdb->insert($j,['frozen_revision_id'=>9000001,'campaign_id'=>9000001,'attempt_id'=>9000001,'snapshot_id'=>9000001,
        'content_hash'=>$hash,'state'=>'paused','version'=>1,'expected_count'=>3,'created_by'=>$admin,'updated_by'=>$admin,'created_at'=>$stamp,'updated_at'=>$stamp]);
    peracrm_campaigns_db_require(true); $job=(int)$wpdb->insert_id;
    foreach (['held','accepted','unknown'] as $index=>$state) {
        $address=$state==='held'?$clean:('review-'.$state.'@example.test');
        $wpdb->insert($d,['job_id'=>$job,'frozen_revision_id'=>9000001,'frozen_recipient_id'=>9000001+$index,'subscriber_id'=>$cleanID,
            'snapshot_id'=>9000001,'content_hash'=>$hash,'mailbox'=>$address,'delivery_key'=>hash('sha256',$address),'state'=>$state,'version'=>1,'created_at'=>$stamp,'updated_at'=>$stamp]);
        peracrm_campaigns_db_require(true);
    }
    $beforeRows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$d} WHERE job_id=%d ORDER BY id",$job),ARRAY_A);
    $remaining=peracrm_delivery_review_unsent($job);
    review_expect($remaining['total']===1 && $remaining['rows'][0]['mailbox']===$clean,'paused Zoho review excludes accepted and unknown outcomes');
    review_expect(count(peracrm_delivery_review_export($job))===2,'review CSV includes header and unsent row');
    review_expect($beforeRows===$wpdb->get_results($wpdb->prepare("SELECT * FROM {$d} WHERE job_id=%d ORDER BY id",$job),ARRAY_A),'review and export do not alter any recipient state');
    $wpdb->update($j,['state'=>'queued'],['id'=>$job]);
    $denied=false; try { peracrm_delivery_review_unsent($job); } catch (Throwable $e) { $denied=true; }
    review_expect($denied,'unpaused job rejected by review service');
    $wpdb->delete($d,['job_id'=>$job]); $wpdb->delete($j,['id'=>$job]);
    wp_delete_post($client,true);
});
review_expect(get_current_blog_id()===1 && (!is_multisite() || !ms_is_switched()),'services restore original target-blog context');
echo "PASS delivery review WordPress/MySQL fixture\n";
