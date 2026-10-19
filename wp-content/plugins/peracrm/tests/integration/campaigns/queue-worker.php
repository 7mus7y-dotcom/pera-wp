<?php
require __DIR__.'/worker-fixture.php';
function worker_expect($ok, string $label): void { if (!$ok) { throw new RuntimeException('FAIL worker: '.$label); } echo 'PASS worker: '.$label."\n"; }
function worker_reject(callable $fn): bool { try { $fn(); return false; } catch (Throwable $e) { return true; } }
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
$claims=$w->claim($job,2);
worker_expect(count($claims)===2 && strlen($claims[0]['owner_token'])===64 && $claims[0]['attempt_key']!=='' && $claims[0]['delivery_key']!=='' && $claims[0]['lease_until']!==null,'durable owner lease attempt and deterministic keys before submission');
worker_expect(!$w->completion($job),'active leases and unclaimed work prevent completion');
$r=$w->submit($claims[0],$p);
worker_expect($r['state']==='accepted' && $r['message_id']!=='' && worker_reject(static function () use ($w,$claims,$p) { $w->submit($claims[0],$p); }),'accepted result fenced and never resent');
worker_fixture_expire($job); $recovered=$w->recover($job);
worker_expect(count($recovered)===1 && $recovered[0]['state']==='held' && worker_reject(static function () use ($w,$claims,$p) { $w->submit($claims[1],$p); }),'crash before submission recovers with stale-owner denial');
$w->run($job,$p,10);
worker_expect($w->completion($job) && count($w->claim($job))===0 && count(array_filter(worker_fixture_rows($job),static function ($r) { return $r['state']==='accepted'; }))===3,'exact accepted counts and submission completion only');

$job=worker_fixture_reset(1); $p->mode='timeout_after_accept';
$r=$w->run($job,$p,1)[0];
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
foreach (['unsubscribe','suppression'] as $change) {
    $job=worker_fixture_reset(1); $claim=$w->claim($job,1)[0];
    $old=worker_fixture_target(static function () use ($claim,$change) {
        global $wpdb; $table=peracrm_table($change==='unsubscribe' ? 'peracrm_subscribers' : 'peracrm_email_suppressions');
        $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$table.' WHERE normalized_email=%s',$claim['mailbox']),ARRAY_A);
        $wpdb->update($table,$change==='unsubscribe' ? ['subscription_state'=>'unsubscribed'] : ['complaint'=>1],['normalized_email'=>$claim['mailbox']]);
        return [$table,$old];
    });
    $before=$p->calls; $r=$w->submit($claim,$p);
    worker_expect($r['state']==='excluded' && $p->calls===$before && $r['reason']===($change==='unsubscribe' ? 'consent' : 'suppression'),$change.' between freeze claim and submission terminally excluded');
    worker_fixture_target(static function () use ($old,$claim) { global $wpdb; $wpdb->update($old[0],$old[1],['normalized_email'=>$claim['mailbox']]); });
}
$job=worker_fixture_reset(1);
worker_fixture_target(static function () use ($job) { global $wpdb; $wpdb->update(peracrm_table('peracrm_fixture_worker_jobs'),['complete'=>0],['id'=>$job]); });
worker_expect($w->claim($job)===[] && !$w->completion($job),'incomplete preparation prevents submission completion');
worker_expect(worker_protected()===$protected && $mail===0 && $http===0 && $cron===worker_fixture_target(static function () { return get_option('cron'); }),'unchanged frozen history subscribers evidence tokens ordinary queue cron and zero mail/network');
worker_expect(get_current_blog_id()===$origin,'target context restored independently of anonymous worker actor');
if (is_multisite() && $origin!==peracrm_get_target_blog_id()) {
    global $wpdb; worker_expect($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'peracrm_fixture_worker_jobs'))===null,'no fixture ledger on source blog');
}
echo "PASS worker: real SQL fixture service suite\n";
