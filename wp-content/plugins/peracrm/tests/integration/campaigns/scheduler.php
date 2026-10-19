<?php
require_once __DIR__.'/scheduler-fixture.php';
function scheduler_expect($ok,string $label): void { if (!$ok) { throw new RuntimeException('FAIL scheduler: '.$label); } echo 'PASS scheduler: '.$label."\n"; }
function scheduler_protected(): string
{
    return worker_fixture_target(static function () {
        global $wpdb; $out=[];
        foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events','peracrm_subscriber_tokens','peracrm_campaign_frozen_revisions','peracrm_campaign_frozen_recipients','peracrm_campaign_content_snapshots','peracrm_campaign_queue_jobs','peracrm_campaign_deliveries','peracrm_campaign_worker_events'] as $name) { $out[$name]=$wpdb->get_results('SELECT * FROM '.peracrm_table($name).' ORDER BY 1',ARRAY_A); }
        return hash('sha256',wp_json_encode($out));
    });
}
$origin=get_current_blog_id(); $protected=scheduler_protected();
$cron=worker_fixture_target(static function () { return _get_cron_array(); });
$mail=0; $network=0;
$mail_watch=static function ($v) use (&$mail) { $mail++; return $v; };
$network_watch=static function ($v) use (&$network) { $network++; return $v; };
add_filter('pre_wp_mail',$mail_watch); add_filter('pre_http_request',$network_watch);
for ($i=0;$i<3;$i++) { peracrm_on_activation(); do_action('init'); peracrm_campaign_scheduler_registration(); }
scheduler_expect(worker_fixture_target(static function () { return wp_next_scheduled('peracrm_campaign_scheduler')===false; }),'repeated activation/init installs no production dispatch event');
scheduler_expect($cron===worker_fixture_target(static function () { return _get_cron_array(); }),'existing CRM/subscriber cron unchanged');
$production=new PeraCRM_Campaign_Operations();
for ($i=0;$i<2;$i++) { scheduler_expect($production->invoke()['result']==='disabled','direct production entry is disabled'); do_action('peracrm_campaign_scheduler'); }
scheduler_expect($protected===scheduler_protected(),'disabled invocation performs no production claims/submissions');
wp_set_current_user(0); $denied=false;
try { $production->health(); } catch (RuntimeException $e) { $denied=true; }
scheduler_expect($denied,'anonymous health denied');
$manager=worker_fixture_target(static function () { return (int)get_option('campaign_fixture_manager'); });
wp_set_current_user($manager);
$h=$production->health();
scheduler_expect(!$h['dispatch_enabled'] && $h['scheduler_state']==='disabled' && $h['last_invocation']['result']==='disabled' && $h['last_successful_processing']===null && !$h['delivery_verified'],'disabled/invoked is never processing success or delivery');
$due=$production->due(1); scheduler_expect(count($due)<=1,'production due inspection bounded');
update_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY,worker_fixture_target(static function () { return (int)get_option('campaign_fixture_employee'); })); $denied=false;
try { $production->health(); } catch (RuntimeException $e) { $denied=true; }
delete_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY);
scheduler_expect($denied,'impersonated health denied');
wp_set_current_user(0);
worker_fixture_target(static function () { delete_option('peracrm_fixture_scheduler_last'); delete_option('peracrm_fixture_scheduler_last_success'); });
$s=new PeraCRM_Fixture_Scheduler(); $job=worker_fixture_reset(15);
scheduler_expect($s->health()['scheduler_state']==='never_invoked','fixture starts without successful scheduler claim');
$s->readiness=false; $r=$s->invoke();
scheduler_expect($r['result']==='blocked_readiness' && $r['processed']===0 && $s->health()['scheduler_state']==='blocked','readiness failure is distinct from disabled/success');
$s->readiness=true;
// Schedule uses actual lifecycle fence and database clock, including before/at due.
worker_fixture_state($job,'schedule',gmdate('Y-m-d\TH:i:s\Z',time()+120));
try {
    scheduler_expect($s->due()===[] && $s->invoke()['processed']===0 && $s->health()['scheduler_state']==='invoked','future scheduled work is not due; invocation is not success');
    worker_fixture_advance(121);
    scheduler_expect(count($s->due(1))===1,'scheduled work becomes due using stored UTC instant');
    $s->fake=new PeraCRM_Fake_Campaign_Provider(); $s->fake->elapsed_seconds=7;
    $r=$s->invoke(false,['seconds'=>25]);
    scheduler_expect($r['processed']===1 && $r['result']==='time_bound' && $s->fake->calls===1,'elapsed bound reserves transport timeout and stops new claims after one seven-second call');
    $h=$s->health();
    scheduler_expect($h['scheduler_state']==='processed' && $h['last_successful_processing']!==null && $h['last_provider_acceptance']!==null && !$h['delivery_verified'],'processing, acceptance and delivery are separately labelled');
    $s->fake->elapsed_seconds=0; $r=$s->invoke(false,['recipients'=>3]);
    scheduler_expect($r['processed']===3 && $s->fake->calls===4,'recipient bound applies across invocation');
    $r=$s->invoke(); scheduler_expect($r['processed']===10 && $s->fake->calls===14 && $s->invoke()['processed']===1 && $s->invoke()['processed']===0,'default ten-recipient bound and duplicate invocation never resend accepted work');
} finally { global $wpdb; $wpdb->query('SET timestamp = 0'); }
// Crash equivalents retain the worker's lease/version/unknown rules.
$job=worker_fixture_reset(3); $w=worker_fixture(); $p=new PeraCRM_Fake_Campaign_Provider();
$claims=$w->claim($job,2); $p->mode='timeout_after_accept'; $w->submit($claims[0],$p); worker_fixture_expire($job);
$r=$s->invoke(true,['recovery'=>1]);
scheduler_expect($r['recovered']===1 && $r['processed']===0 && worker_fixture_rows($job)[0]['state']==='unknown','bounded maintenance recovers unused claim while preserving unknown acceptance');
$s->fake=new PeraCRM_Fake_Campaign_Provider(); $r=$s->invoke();
scheduler_expect($r['processed']===2 && $s->fake->calls===2 && worker_fixture_rows($job)[0]['state']==='unknown','subsequent scheduler never resends accepted unknown work');
$job=worker_fixture_reset(3); $claims=$w->claim($job,3); worker_fixture_expire($job);
$r=$s->invoke(true,['recovery'=>1]); $h=$s->health();
scheduler_expect($r['recovered']===1 && $h['counts']['expired_leases']===2 && $h['counts']['active_leases']===0,'recovery row bound and precise expired/active lease counts');
$s->invoke(true); $p=new PeraCRM_Fake_Campaign_Provider(); $p->mode='transient'; $w->run($job,$p,1); $h=$s->health();
scheduler_expect($h['counts']['retry_backlog']===1 && $h['counts']['due_retries']===0,'retry backlog distinct from currently due retries');
// Exercise an actual scheduled event only in the disposable fixture, never production hook routing.
$job=worker_fixture_reset(1); $callback=static function () use ($s) { $s->invoke(); };
add_action('peracrm_fixture_scheduler',$callback);
worker_fixture_target(static function () { wp_schedule_single_event(time()-1,'peracrm_fixture_scheduler'); });
worker_fixture_target(static function () { foreach (wp_get_ready_cron_jobs() as $events) { foreach ($events as $hook=>$entries) { if ($hook==='peracrm_fixture_scheduler') { foreach ($entries as $event) { do_action_ref_array($hook,$event['args']); } } } } wp_clear_scheduled_hook('peracrm_fixture_scheduler'); });
remove_action('peracrm_fixture_scheduler',$callback);
scheduler_expect(worker_fixture_rows($job)[0]['state']==='accepted','scheduled fixture invocation processes only isolated fake ledger');
// Malformed stored diagnostics cannot leak arbitrary option fields.
worker_fixture_target(static function () { update_option('peracrm_fixture_scheduler_last',['result'=>'completed','invoked_at'=>'secret@example.test','mailbox'=>'secret@example.test','processed'=>1,'successful'=>1],false); });
$json=wp_json_encode($s->health());
scheduler_expect(!str_contains($json,'@') && !preg_match('/mailbox|content_hash|owner_token|attempt_key|credentials/',$json),'allowlisted privacy-safe health metadata');
scheduler_expect($protected===scheduler_protected() && $mail===0 && $network===0 && get_current_blog_id()===$origin,'unchanged production/history/subscribers; zero mail/network; restored target context');
if (is_multisite()) { global $wpdb; scheduler_expect(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'peracrm_fixture_worker_jobs')),'no origin-blog fixture ledger'); }
remove_filter('pre_wp_mail',$mail_watch); remove_filter('pre_http_request',$network_watch);
echo "PASS scheduler real SQL acceptance\n";
