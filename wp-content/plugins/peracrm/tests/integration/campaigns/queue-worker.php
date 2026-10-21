<?php
require __DIR__.'/worker-fixture.php';
function worker_expect($ok, string $label): void { if (!$ok) { throw new RuntimeException('FAIL worker: '.$label); } echo 'PASS worker: '.$label."\n"; }
function worker_reject(callable $fn): bool { try { $fn(); return false; } catch (Throwable $e) { return true; } }
/** Content recovery must not renew leases or reset accepted/unknown worker rows. */
function worker_output_recovery_preserves_fences(int $job): void
{
    require_once WP_PLUGIN_DIR.'/peracrm/tests/output-recovery-fixture.php';
    $before = worker_fixture_rows($job);
    worker_fixture_target(static function () {
        $blog=get_current_blog_id(); [$binding,$output,$submission,$metadata]=output_fixture($blog);
        $store=new PeraCRM_CampaignRecipientPersistence('fixture-output-restart',$blog);
        [$restored]=$store->recoverOutput($binding,$metadata,output_cipher(true));
        worker_expect($restored->hash()===$submission->hash(),'historical output recovery under independent worker fixture');
    });
    worker_expect(worker_fixture_rows($job)===$before,'content recovery preserves worker state, owner, version, attempt and lease bytes');
}
function worker_protected(): string
{
    return worker_fixture_target(static function () {
        global $wpdb; $out=[];
        foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events','peracrm_subscriber_tokens','peracrm_campaign_frozen_revisions','peracrm_campaign_frozen_recipients','peracrm_campaign_content_snapshots','peracrm_campaign_queue_jobs','peracrm_campaign_deliveries'] as $name) {
            $out[$name]=$wpdb->get_results('SELECT * FROM '.peracrm_table($name).' ORDER BY 1',ARRAY_A);
        }
        return hash('sha256',wp_json_encode($out));
    });
}
worker_fixture_target(static function () { update_option('campaign_worker_fixture_authority',bin2hex(random_bytes(32)),false); });
// Prepare exact-sized sources through the real services, never truncate published membership.
$setup_manager=worker_fixture_target(static function () { return (int)get_option('campaign_fixture_manager'); });
wp_set_current_user($setup_manager);
$original=worker_fixture_target(static function () { global $wpdb; return $wpdb->get_row('SELECT * FROM '.peracrm_table('peracrm_campaign_queue_jobs')." WHERE state='queued' AND complete=1 ORDER BY id DESC LIMIT 1",ARRAY_A); });
$template=peracrm_campaign_draft_get((int)$original['campaign_id'])['document'];
foreach ([1,3,15] as $size) {
    $topic='workerfixture'.$size;
    for ($i=0;$i<$size;$i++) {
        $address=$topic.'-'.$i.'@example.test';
        $saved=peracrm_campaigns_save_subscriber($address,['preferred_language'=>'en','preferences'=>[$topic]]);
        $consent=peracrm_campaigns_record_consent($address,'subscribed',['source'=>'synthetic_fixture','source_ref'=>'worker','evidence_ref'=>'synthetic','policy_version'=>'v1','occurred_at'=>'2020-01-01 00:00:00']);
        if (is_wp_error($saved) || is_wp_error($consent)) { throw new RuntimeException('Worker source subscriber failed.'); }
    }
    $doc=$template; $doc['audience']=['version'=>1,'topic'=>$topic];
    $d=peracrm_campaign_draft_save(0,0,$doc);
    $review=peracrm_campaign_snapshot_review($d['id'],1,$d['document']);
    $s=peracrm_campaign_snapshot_capture($d['id'],1,$d['document'],$review['review_proof'],wp_create_nonce('peracrm_campaign_snapshot_'.$d['id'].'_1'));
    $fn=wp_create_nonce('peracrm_campaign_freeze_'.$d['id']);
    $a=peracrm_campaign_freeze_start($d['id'],1,(int)$s['id'],$s['content_hash'],$d['document'],$fn);
    while (!(int)$a['complete']) { $a=peracrm_campaign_freeze_continue($d['id'],(int)$a['id'],(int)$a['version'],$fn); if (is_wp_error($a)) { throw new RuntimeException('Worker source freeze failed.'); } }
    $a=peracrm_campaign_freeze_publish($d['id'],(int)$a['id'],(int)$a['version'],$fn);
    $r=peracrm_campaign_frozen_get($d['id'],1); $nonce=wp_create_nonce('peracrm_campaign_queue_'.$d['id']);
    $j=peracrm_campaign_queue_prepare($d['id'],(int)$r['id'],$nonce);
    while (!(int)$j['complete']) { $j=peracrm_campaign_queue_materialize($d['id'],(int)$j['id'],(int)$j['version'],$nonce); if (is_wp_error($j)) { throw new RuntimeException('Worker source preparation failed.'); } }
    worker_expect((int)$j['expected_count']===$size,'exact published synthetic source '.$size);
}
// Reconstruct the exact previous column layout in disposable volumes; existing prepared rows survive.
$administrator=worker_fixture_target(static function () { return (int)get_option('campaign_fixture_administrator'); });
wp_set_current_user($administrator);
$before_migration=worker_fixture_target(static function () { global $wpdb; return (int)$wpdb->get_var('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_deliveries')); });
worker_fixture_target(static function () {
    global $wpdb; $table=peracrm_table('peracrm_campaign_deliveries');
    $wpdb->query('ALTER TABLE '.$table.' DROP INDEX worker_due, DROP INDEX worker_lease, DROP INDEX health_lease, DROP INDEX health_retry, DROP INDEX health_result');
    foreach (['owner_token','lease_until','attempt_count','attempt_key','next_attempt_at','message_id','reason'] as $column) { $wpdb->query('ALTER TABLE '.$table.' DROP COLUMN '.$column); }
    $wpdb->query('ALTER TABLE '.peracrm_table('peracrm_campaign_queue_jobs').' DROP COLUMN submission_complete');
    $wpdb->query('DROP TABLE '.peracrm_table('peracrm_campaign_worker_events'));
    update_option('peracrm_schema_version',26); update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>26]);
});
worker_expect(!peracrm_campaigns_schema_ready() && peracrm_maybe_upgrade_schema() && peracrm_campaigns_schema_ready(),'additive v26 to current readiness migration');
worker_expect($before_migration===worker_fixture_target(static function () { global $wpdb; return (int)$wpdb->get_var('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_deliveries')); }),'migration preserves prepared delivery rows');
worker_fixture_target(static function () { global $wpdb; $wpdb->query('ALTER TABLE '.peracrm_table('peracrm_campaign_worker_events').' DROP INDEX delivery_version'); });
worker_expect(worker_reject(static function () { worker_fixture_target(static function () { peracrm_campaigns_assert_schema(); }); }),'worker audit uniqueness required by readiness verification');
worker_fixture_target(static function () { peracrm_campaigns_install_schema(); });
$origin=get_current_blog_id();
$protected=worker_protected();
$cron=worker_fixture_target(static function () { return get_option('cron'); });
$mail=0; $http=0;
add_filter('pre_wp_mail',static function ($r) use (&$mail) { $mail++; return $r; },99);
add_filter('pre_http_request',static function ($r) use (&$http) { $http++; return $r; },99);
wp_set_current_user(0);
worker_expect(worker_reject(static function () { new PeraCRM_Fixture_Worker(''); }) && is_wp_error(peracrm_campaign_worker_run()),'explicit authority and unconditional production denial');
$w=worker_fixture(); $p=new PeraCRM_Fake_Campaign_Provider();
$job=worker_fixture_reset(3);
worker_expect(worker_reject(static function () use ($w,$job) { $w->claim($job,11); }) && worker_reject(static function () use ($w,$job) { $w->recover($job,0); }),'bounded batch and recovery');
worker_expect(worker_reject(static function () use ($w,$job,$p) { $w->run($job,$p,0); }) && worker_reject(static function () use ($w,$job,$p) { $w->run($job,$p,11); }) && $p->calls===0,'sequential invocation rejects invalid bounds before claiming or submitting');
$claims=$w->claim($job,2);
worker_expect(count($claims)===2 && strlen($claims[0]['owner_token'])===64 && $claims[0]['attempt_key']!=='' && $claims[0]['delivery_key']!=='' && $claims[0]['lease_until']!==null,'durable owner lease attempt and deterministic keys before submission');
worker_expect(!$w->completion($job),'active leases and unclaimed work prevent completion');
$r=$w->submit($claims[0],$p);
worker_output_recovery_preserves_fences($job);
worker_expect($r['state']==='accepted' && $r['message_id']!=='' && worker_reject(static function () use ($w,$claims,$p) { $w->submit($claims[0],$p); }),'accepted result fenced and never resent');
$same=$p->submit(['mailbox'=>$r['mailbox'],'delivery_key'=>$r['delivery_key'],'attempt_key'=>$r['attempt_key']]);
worker_expect($same['message_id']===$r['message_id'],'fake same-key/attempt idempotency retains stable correlation');
worker_fixture_expire($job); $recovered=$w->recover($job);
worker_expect(count($recovered)===1 && $recovered[0]['state']==='held' && worker_reject(static function () use ($w,$claims,$p) { $w->submit($claims[1],$p); }),'crash before submission recovers with stale-owner denial');
$w->run($job,$p,10);
worker_expect($w->completion($job) && count($w->claim($job))===0 && count(array_filter(worker_fixture_rows($job),static function ($r) { return $r['state']==='accepted'; }))===3,'exact accepted counts and submission completion only');
worker_fixture_target(static function () use ($job) { global $wpdb; $wpdb->query($wpdb->prepare('DELETE FROM '.peracrm_table('peracrm_fixture_worker_deliveries').' WHERE job_id=%d ORDER BY id LIMIT 1',$job)); });
worker_expect(!$w->completion($job),'missing persisted membership prevents false submission completion');

$job=worker_fixture_reset(1); $p->mode='timeout_after_accept';
$r=$w->run($job,$p,1)[0];
worker_output_recovery_preserves_fences($job);
worker_expect($r['state']==='unknown' && !$w->completion($job) && $w->claim($job)===[],'ambiguous timeout held for reconciliation');
worker_fixture_state($job,'pause'); worker_fixture_state($job,'resume');
worker_expect(worker_fixture_rows($job)[0]['state']==='unknown' && $w->claim($job)===[],'ordinary pause/resume cannot reset unknown');
$e=$p->lookup($r['delivery_key'],$r['attempt_key']);
worker_expect($e && $e['kind']==='accepted','fake lookup explicitly models accepted evidence');
$campaign=worker_fixture_target(static function () use ($job) { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT campaign_id FROM '.peracrm_table('peracrm_fixture_worker_jobs').' WHERE id=%d',$job)); });
$manager=worker_fixture_target(static function () { return (int)get_option('campaign_fixture_manager'); });
$employee=worker_fixture_target(static function () { return (int)get_option('campaign_fixture_employee'); });
foreach ([0,$employee] as $actor) {
    wp_set_current_user($actor);
    worker_expect(worker_reject(static function () use ($w,$job,$r,$e,$p,$campaign) { $w->reconcile($job,(int)$r['id'],(int)$r['version'],wp_create_nonce('peracrm_campaign_queue_'.$campaign),$e,$p); }),'reconciliation requires staff authority');
}
wp_set_current_user($manager); update_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY,$employee);
worker_expect(worker_reject(static function () use ($w,$job,$r,$e,$p,$campaign) { $w->reconcile($job,(int)$r['id'],(int)$r['version'],wp_create_nonce('peracrm_campaign_queue_'.$campaign),$e,$p); }),'impersonated reconciliation denied');
delete_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY);
$nonce=wp_create_nonce('peracrm_campaign_queue_'.$campaign);
worker_fixture_target(static function () use ($manager) { (new WP_User($manager))->add_cap('peracrm_manage_subscribers',false); });
worker_expect(worker_reject(static function () use ($w,$job,$r,$e,$p,$nonce) { $w->reconcile($job,(int)$r['id'],(int)$r['version'],$nonce,$e,$p); }),'campaign-only actor cannot reconcile or read recipient details');
worker_fixture_target(static function () use ($manager) { (new WP_User($manager))->remove_cap('peracrm_manage_subscribers'); });
$bad=$e; $bad['kind']='permanent';
worker_expect(worker_reject(static function () use ($w,$job,$r,$bad,$p,$nonce) { $w->reconcile($job,(int)$r['id'],(int)$r['version'],$nonce,$bad,$p); }),'unverified evidence denied');
$resolved=$w->reconcile($job,(int)$r['id'],(int)$r['version'],$nonce,$e,$p);
worker_expect($resolved['state']==='accepted' && $w->reconcile($job,(int)$r['id'],(int)$r['version'],$nonce,$e,$p)==$resolved && worker_reject(static function () use ($w,$job,$r,$e,$p,$nonce) { $w->reconcile($job,(int)$r['id'],(int)$r['version']-1,$nonce,$e,$p); }),'verified idempotent reconciliation rejects stale version');
$audit=worker_fixture_target(static function () use ($resolved) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_fixture_worker_events').' WHERE delivery_id=%d AND version=%d',$resolved['id'],$resolved['version']),ARRAY_A); });
worker_expect((int)$audit['actor_user_id']===$manager && $audit['evidence_hash']!=='' && $w->completion($job),'real actor reconciliation audit and resolved completion');
wp_set_current_user(0);

$job=worker_fixture_reset(1); $p->mode='transient';
for ($i=1;$i<=4;$i++) {
    $r=$w->run($job,$p,1)[0];
    worker_expect((int)$r['attempt_count']===$i && $r['state']===($i===4 ? 'failed' : 'retry'),'bounded transient attempt '.$i);
    if ($i<4) {
        worker_expect(strtotime($r['next_attempt_at'].' UTC')-strtotime($r['updated_at'].' UTC')>=120 && !$w->completion($job) && $w->claim($job)===[],'retry-after respected and future retry unresolved');
        worker_fixture_target(static function () use ($job) { global $wpdb; $wpdb->query($wpdb->prepare('UPDATE '.peracrm_table('peracrm_fixture_worker_deliveries')." SET next_attempt_at='2000-01-01' WHERE job_id=%d",$job)); });
    }
}
worker_expect($w->completion($job) && $w->claim($job)===[],'attempt limit terminal without further submission');
$job=worker_fixture_reset(1); $p->mode='permanent';
worker_expect($w->run($job,$p,1)[0]['state']==='failed' && $w->claim($job)===[],'permanent non-acceptance never retried');
foreach (['pause','cancel'] as $op) {
    $job=worker_fixture_reset(1); worker_fixture_state($job,$op);
    worker_expect($w->claim($job)===[],$op.' before claim');
    $job=worker_fixture_reset(1); $claim=$w->claim($job,1)[0]; worker_fixture_state($job,$op); $before=$p->calls;
    $r=$w->submit($claim,$p);
    worker_expect($p->calls===$before && $r['state']===($op==='cancel' ? 'excluded' : 'held'),$op.' before final submission');
}

// Model consent and suppression changes after freeze/claim without persisting synthetic mutations.
foreach (['unsubscribe','suppression','evidence','address'] as $change) {
    $job=worker_fixture_reset(1); $claim=$w->claim($job,1)[0];
    $old=worker_fixture_target(static function () use ($claim,$change) {
        global $wpdb; $table=peracrm_table($change!=='suppression' ? 'peracrm_subscribers' : 'peracrm_email_suppressions');
        $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$table.' WHERE normalized_email=%s',$claim['mailbox']),ARRAY_A);
        $updates=['unsubscribe'=>['subscription_state'=>'unsubscribed'],'suppression'=>['complaint'=>1],'evidence'=>['evidence_ref'=>''],'address'=>['normalized_email'=>'changed-worker@example.test','email'=>'changed-worker@example.test']];
        $wpdb->update($table,$updates[$change],['normalized_email'=>$claim['mailbox']]);
        return [$table,$old];
    });
    $before=$p->calls; $r=$w->submit($claim,$p);
    $reason=['unsubscribe'=>'consent','suppression'=>'suppression','evidence'=>'consent','address'=>'mailbox_binding'][$change];
    worker_expect($r['state']==='excluded' && $p->calls===$before && $r['reason']===$reason,$change.' between freeze claim and submission terminally excluded; no address substitution');
    worker_fixture_target(static function () use ($old,$claim,$change) { global $wpdb; $wpdb->update($old[0],$old[1],['normalized_email'=>$change==='address' ? 'changed-worker@example.test' : $claim['mailbox']]); });
}
$job=worker_fixture_reset(1);
worker_fixture_target(static function () use ($job) { global $wpdb; $wpdb->update(peracrm_table('peracrm_fixture_worker_jobs'),['complete'=>0],['id'=>$job]); });
worker_expect($w->claim($job)===[] && !$w->completion($job),'incomplete preparation prevents submission completion');
require __DIR__.'/worker-lease-acceptance.php';
worker_expect(worker_protected()===$protected && $mail===0 && $http===0 && $cron===worker_fixture_target(static function () { return get_option('cron'); }),'unchanged frozen history subscribers evidence tokens ordinary queue cron and zero mail/network');
worker_expect(get_current_blog_id()===$origin,'target context restored independently of anonymous worker actor');
if (is_multisite() && $origin!==peracrm_get_target_blog_id()) {
    global $wpdb; worker_expect($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'peracrm_fixture_worker_jobs'))===null,'no fixture ledger on source blog');
}
echo "PASS worker: real SQL fixture service suite\n";
