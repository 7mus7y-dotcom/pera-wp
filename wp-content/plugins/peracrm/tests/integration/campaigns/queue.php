<?php
/** Synthetic real WordPress/MySQL queue checks. No dispatch process is registered or invoked. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
function queue_expect($ok,$label) { if (!$ok) { throw new RuntimeException('FAIL queue: '.$label); } echo 'PASS queue: '.$label."\n"; }
function queue_target(callable $fn) { return peracrm_with_target_blog($fn); }
function queue_get($j) { return peracrm_campaign_queue_get((int)$j['campaign_id'],(int)$j['id']); }
function queue_op($j,$nonce,$op,$time=null) { return peracrm_campaign_queue_transition((int)$j['campaign_id'],(int)$j['id'],(int)$j['version'],$nonce,$op,$time); }
function queue_batch($j,$nonce,$limit=200) { return peracrm_campaign_queue_materialize((int)$j['campaign_id'],(int)$j['id'],(int)$j['version'],$nonce,$limit); }
function queue_history($revision) { return queue_target(static function () use ($revision) { global $wpdb; return wp_json_encode([$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_frozen_revisions').' WHERE id=%d',$revision),ARRAY_A),$wpdb->get_results('SELECT * FROM '.peracrm_table('peracrm_campaign_frozen_recipients').' ORDER BY id',ARRAY_A)]); }); }
$origin=get_current_blog_id();
$manager=queue_target(static function () { return (int)get_option('campaign_fixture_manager'); });
$admin=queue_target(static function () { return (int)get_option('campaign_fixture_administrator'); });
$employee=queue_target(static function () { return (int)get_option('campaign_fixture_employee'); });
wp_set_current_user($admin);
queue_target(static function () { global $wpdb; foreach (array_keys(peracrm_campaign_queue_definitions()) as $name) { $wpdb->query('DROP TABLE '.peracrm_table($name)); } update_option('peracrm_schema_version',25); update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>25]); });
queue_expect(!peracrm_campaigns_schema_ready() && peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema(),'additive v25 to v26 migration and repeated readiness');
// Missing secondary uniqueness must fail verification even if primary scope key exists.
queue_target(static function () { global $wpdb; $wpdb->query('ALTER TABLE '.peracrm_table('peracrm_campaign_deliveries').' DROP INDEX delivery_key'); });
$denied=false; try { queue_target(static function () { peracrm_campaigns_assert_schema(); }); } catch (Throwable $e) { $denied=true; }
queue_expect($denied,'missing delivery-key uniqueness fails schema verification');
queue_target(static function () { peracrm_campaigns_install_schema(); });
// Contained upgrade fault leaves old marker; explicit administrator retry repairs it.
queue_target(static function () { update_option('peracrm_schema_version',25); delete_option('peracrm_campaigns_schema_status'); });
$fault=static function ($sql) { return str_starts_with($sql,'SHOW COLUMNS FROM') && str_contains($sql,'peracrm_campaign_queue_jobs') ? 'SELECT nonexistent FROM peracrm_fixture_missing' : $sql; };
add_filter('query',$fault); $upgrade=peracrm_maybe_upgrade_schema(); remove_filter('query',$fault);
queue_expect(!$upgrade && !peracrm_campaigns_schema_ready() && queue_target(static function () { return (int)get_option('peracrm_schema_version'); })===25,'migration failure contains queue and preserves old marker');
queue_expect(!is_wp_error(peracrm_campaigns_retry_schema()) && peracrm_campaigns_schema_ready(),'authorized retry restores readiness');
$config=peracrm_campaign_configuration(true);
$config=peracrm_campaign_configuration_save($config['revision'],['senders'=>array_merge($config['document']['senders'],[['id'=>'queue','name'=>'Synthetic Pera','email'=>'queue@example.test','reply_to'=>'reply@example.test','approved'=>true]]),'footer'=>'Synthetic approved queue footer','footer_approved'=>true]);
queue_expect(!is_wp_error($config),'queue synthetic configuration');
wp_set_current_user($manager);
for ($i=0;$i<205;$i++) {
    $mailbox='queue-'.$i.'@example.test';
    $save=peracrm_campaigns_save_subscriber($mailbox,['preferred_language'=>'en','preferences'=>['queue']]);
    $consent=peracrm_campaigns_record_consent($mailbox,'subscribed',['source'=>'synthetic_fixture','source_ref'=>'queue','evidence_ref'=>'synthetic','policy_version'=>'v1','occurred_at'=>'2020-01-01 00:00:00']);
    if (is_wp_error($save) || is_wp_error($consent)) { throw new RuntimeException('Synthetic queue subscriber failed.'); }
}
$doc=['internal_name'=>'Queue fixture','subject'=>'Synthetic queue','sender_id'=>'queue','language'=>'en','main_text'=>'Immutable queue content','audience'=>['version'=>1,'topic'=>'queue']];
$d=peracrm_campaign_draft_save(0,0,$doc); queue_expect(!is_wp_error($d),'queue draft saved');
$c=$d['id']; $nonce=wp_create_nonce('peracrm_campaign_queue_'.$c);
queue_expect(is_wp_error(peracrm_campaign_queue_prepare($c,999999,$nonce)),'draft/unpublished ID cannot prepare');
$review=peracrm_campaign_snapshot_review($c,1,$d['document']);
$s=peracrm_campaign_snapshot_capture($c,1,$d['document'],$review['review_proof'],wp_create_nonce('peracrm_campaign_snapshot_'.$c.'_1'));
$fn=wp_create_nonce('peracrm_campaign_freeze_'.$c);
$a=peracrm_campaign_freeze_start($c,1,(int)$s['id'],$s['content_hash'],$d['document'],$fn);
queue_expect(!is_wp_error($a),'queue freeze started');
queue_expect(is_wp_error(peracrm_campaign_queue_prepare($c,999999,$nonce)),'incomplete freeze cannot prepare');
for ($i=0;$i<100 && !(int)$a['complete'];$i++) { $a=peracrm_campaign_freeze_continue($c,(int)$a['id'],(int)$a['version'],$fn); if (is_wp_error($a)) { throw new RuntimeException('Freeze fixture failed.'); } }
$a=peracrm_campaign_freeze_publish($c,(int)$a['id'],(int)$a['version'],$fn); queue_expect(!is_wp_error($a),'queue source published');
$r=peracrm_campaign_frozen_get($c,1); $rid=(int)$r['id'];
$history=queue_history($rid);
$protected=queue_target(static function () { global $wpdb; $out=[]; foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events','peracrm_subscriber_tokens'] as $name) { $out[$name]=$wpdb->get_results('SELECT * FROM '.peracrm_table($name).' ORDER BY 1',ARRAY_A); } return wp_json_encode($out); });
$cron=queue_target(static function () { return get_option('cron'); });
foreach ([0,$employee] as $actor) { wp_set_current_user($actor); queue_expect(is_wp_error(peracrm_campaign_queue_prepare($c,$rid,$nonce)),'staff authority denial'); }
wp_set_current_user($manager); update_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY,$employee);
queue_expect(is_wp_error(peracrm_campaign_queue_prepare($c,$rid,$nonce)),'impersonation denied'); delete_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY);
$payload=$s['payload_json'];
queue_target(static function () use ($s) { global $wpdb; $wpdb->update(peracrm_table('peracrm_campaign_content_snapshots'),['payload_json'=>'{}'],['id'=>$s['id']]); });
queue_expect(is_wp_error(peracrm_campaign_queue_prepare($c,$rid,$nonce)),'stored snapshot corruption rejected');
queue_target(static function () use ($s,$payload) { global $wpdb; $wpdb->update(peracrm_table('peracrm_campaign_content_snapshots'),['payload_json'=>$payload],['id'=>$s['id']]); });
$j=peracrm_campaign_queue_prepare($c,$rid,$nonce); queue_expect(!is_wp_error($j) && $j['state']==='preparing','published-only prepare');
queue_expect((int)peracrm_campaign_queue_prepare($c,$rid,$nonce)['id']===(int)$j['id'],'duplicate preparation returns same job');
queue_expect(is_wp_error(peracrm_campaign_queue_prepare($c+99999,$rid,wp_create_nonce('peracrm_campaign_queue_'.($c+99999)))),'campaign binding isolates revision');
queue_expect(is_wp_error(queue_op($j,$nonce,'schedule','2035-01-01T12:00:00Z')),'partial preparation cannot schedule');
$member=peracrm_campaign_freeze_recipients($c,(int)$a['id'])[0];
queue_target(static function () use ($member) { global $wpdb; $wpdb->update(peracrm_table('peracrm_campaign_frozen_recipients'),['mailbox'=>'tampered@example.test'],['id'=>$member['id']]); });
queue_expect(is_wp_error(queue_batch($j,$nonce,1)) && !peracrm_campaign_queue_page($c,(int)$j['id']),'recipient/evaluation binding corruption rejects and rolls back');
queue_target(static function () use ($member) { global $wpdb; $wpdb->update(peracrm_table('peracrm_campaign_frozen_recipients'),['mailbox'=>$member['mailbox']],['id'=>$member['id']]); });
$j=queue_op(queue_get($j),$nonce,'resume'); queue_expect(!is_wp_error($j),'resume after restored immutable binding');
$j=queue_batch($j,$nonce,100); queue_expect(!is_wp_error($j) && (int)$j['prepared_count']===100 && !$j['complete'],'bounded exact first batch');
$rows=peracrm_campaign_queue_page($c,(int)$j['id']);
$fail=static function ($sql) { return str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_campaign_queue_jobs') && str_contains($sql,'`last_id`') && str_contains($sql,"'preparing'") ? 'UPDATE peracrm_fixture_missing SET id=1' : $sql; };
add_filter('query',$fail); $failed=queue_batch($j,$nonce,100); remove_filter('query',$fail);
$current=queue_get($j); queue_expect(is_wp_error($failed) && $current['state']==='failed' && (int)$current['last_id']===(int)$j['last_id'] && $rows===peracrm_campaign_queue_page($c,(int)$j['id']),'SQL batch rollback preserves rows/cursor and persists failure');
$j=queue_op($current,$nonce,'resume'); queue_expect(!is_wp_error($j) && $j['state']==='preparing','resume failed preparation');
$j=queue_op($j,$nonce,'pause'); queue_expect($j['state']==='paused' && is_wp_error(queue_batch($j,$nonce)),'pause fences materialization');
$j=queue_op($j,$nonce,'resume');
while (!(int)$j['complete']) { $j=queue_batch($j,$nonce,100); if (is_wp_error($j)) { throw new RuntimeException('Queue continuation failed.'); } }
queue_expect($j['state']==='queued' && (int)$j['prepared_count']===205,'complete exact frozen membership only');
$all=peracrm_campaign_queue_page($c,(int)$j['id']); $page=peracrm_campaign_queue_page($c,(int)$j['id'],(int)end($all)['id']);
queue_expect(count($all)===200 && count($page)===5,'bounded keyset delivery reads'); $all=array_merge($all,$page);
queue_target(static function () use ($all) { global $wpdb; $errors=$wpdb->suppress_errors(true); $row=$all[0]; unset($row['id']); queue_expect($wpdb->insert(peracrm_table('peracrm_campaign_deliveries'),$row)===false,'database revision/mailbox uniqueness'); $row['mailbox']='other@example.test'; $row['frozen_recipient_id']=999999; queue_expect($wpdb->insert(peracrm_table('peracrm_campaign_deliveries'),$row)===false,'database deterministic delivery-key uniqueness'); $wpdb->suppress_errors($errors); });
foreach (['2035-01-01 12:00:00','2035-02-30T12:00:00Z','2020-01-01T00:00:00Z'] as $bad) { queue_expect(is_wp_error(queue_op($j,$nonce,'schedule',$bad)),'invalid schedule leaves state/version unchanged'); }
$stale=$j; $j=queue_op($j,$nonce,'schedule','2035-01-01T12:00:00+03:00');
queue_expect($j['scheduled_at']==='2035-01-01 09:00:00' && $j['state']==='scheduled' && is_wp_error(queue_op($stale,$nonce,'cancel')),'UTC intent and stale-state fencing');
$j=queue_op($j,$nonce,'pause'); $j=queue_op($j,$nonce,'resume');
queue_expect($j['state']==='scheduled' && $all===array_merge(peracrm_campaign_queue_page($c,(int)$j['id']),peracrm_campaign_queue_page($c,(int)$j['id'],(int)$all[199]['id'])),'pause/resume reuse exact delivery rows');
$j=queue_op($j,$nonce,'cancel'); $again=queue_op($j,$nonce,'cancel');
queue_expect((int)$again['version']===(int)$j['version'] && $again['state']==='cancelled' && is_wp_error(queue_op($j,$nonce,'resume')) && (int)peracrm_campaign_queue_prepare($c,$rid,$nonce)['id']===(int)$j['id'],'terminal cancellation/repeated requests preserve scope fence');
// Existing freeze cleanup cannot remove the published queue scope or its history.
wp_set_current_user($admin); $cleanup=peracrm_campaign_freeze_cleanup($c,(int)$a['id'],$fn); wp_set_current_user($manager);
queue_expect(is_wp_error($cleanup) && queue_get($j)['state']==='cancelled','cleanup cannot bypass cancellation fence');
$events=peracrm_campaign_queue_page($c,(int)$j['id'],0,true);
queue_expect(count($events)>5 && count(array_unique(array_column($events,'version')))===count($events) && array_values(array_unique(array_map('intval',array_column($events,'actor_user_id'))))===[$manager],'state transitions audit real actor and unique version');
queue_expect($history===queue_history($rid),'frozen content/membership history unchanged');
$after=queue_target(static function () { global $wpdb; $out=[]; foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events','peracrm_subscriber_tokens'] as $name) { $out[$name]=$wpdb->get_results('SELECT * FROM '.peracrm_table($name).' ORDER BY 1',ARRAY_A); } return wp_json_encode($out); });
queue_expect($protected===$after && $cron===queue_target(static function () { return get_option('cron'); }) && !peracrm_campaign_queue_dispatch_allowed() && is_wp_error(peracrm_campaign_queue_require_dispatch()),'no subscriber/token mutation, cron registration or dispatch');
if (is_multisite() && $origin!==peracrm_get_target_blog_id()) {
    global $wpdb;
    queue_expect($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'peracrm_campaign_queue_jobs'))===null,'no queue tables created on source blog');
}
queue_expect(get_current_blog_id()===$origin,'target blog restored');
queue_target(static function () use ($c,$rid) { update_option('campaign_queue_race_source',[$c,$rid],false); });
echo "PASS queue: real WordPress/MySQL storage and lifecycle suite\n";
