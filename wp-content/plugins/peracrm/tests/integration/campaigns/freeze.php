<?php
/** Synthetic real WordPress/MySQL service checks, deliberately no new browser acceptance. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
function freeze_expect($ok,$label) { if (!$ok) { throw new RuntimeException('FAIL freeze: '.$label); } echo 'PASS freeze: '.$label."\n"; }
function freeze_target(callable $fn) { return peracrm_with_target_blog($fn); }
function freeze_identity($email) { return freeze_target(static function () use ($email) { return peracrm_campaigns_repository_identity($email); }); }
function freeze_evidence() { return ['source'=>'synthetic_fixture','source_ref'=>'freeze','evidence_ref'=>'synthetic-consent','policy_version'=>'v1','occurred_at'=>'2020-01-01 00:00:00']; }
function freeze_subscriber($email) {
    freeze_expect(!is_wp_error(peracrm_campaigns_save_subscriber($email,['preferred_language'=>'en','preferences'=>['freeze']])), 'synthetic subscriber '.$email);
    freeze_expect(!is_wp_error(peracrm_campaigns_record_consent($email,'subscribed',freeze_evidence())), 'synthetic consent '.$email);
    return (int)freeze_identity($email)['id'];
}
function freeze_capture($document) {
    $draft=peracrm_campaign_draft_save(0,0,$document);
    freeze_expect(!is_wp_error($draft),'save freeze draft');
    $review=peracrm_campaign_snapshot_review($draft['id'],1,$draft['document']);
    $capture=peracrm_campaign_snapshot_capture($draft['id'],1,$draft['document'],$review['review_proof'],wp_create_nonce('peracrm_campaign_snapshot_'.$draft['id'].'_1'));
    freeze_expect(!is_wp_error($capture),'capture freeze content');
    return [$draft,$capture,wp_create_nonce('peracrm_campaign_freeze_'.$draft['id'])];
}
function freeze_start($set) { [$d,$s,$n]=$set; return peracrm_campaign_freeze_start($d['id'],$d['revision'],(int)$s['id'],$s['content_hash'],$d['document'],$n); }
function freeze_next($a,$nonce,$limit=200) {
    $next=peracrm_campaign_freeze_continue((int)$a['campaign_id'],(int)$a['id'],(int)$a['version'],$nonce,$limit);
    freeze_expect(!is_wp_error($next),'bounded continuation'); return $next;
}
function freeze_finish($a,$nonce) { for ($i=0;$i<100 && !(int)$a['complete'];$i++) { $a=freeze_next($a,$nonce); } freeze_expect((int)$a['complete']===1,'exhaust fixed boundary'); return $a; }
function freeze_protected_bytes() {
    return freeze_target(static function () { global $wpdb; $out=[]; foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events','peracrm_subscriber_tokens'] as $name) { $out[$name]=$wpdb->get_results('SELECT * FROM '.peracrm_table($name).' ORDER BY 1',ARRAY_A); } return wp_json_encode($out); });
}
$origin=get_current_blog_id();
$manager=freeze_target(static function () { return (int)get_option('campaign_fixture_manager'); });
$admin=freeze_target(static function () { return (int)get_option('campaign_fixture_administrator'); });
$employee=freeze_target(static function () { return (int)get_option('campaign_fixture_employee'); });
wp_set_current_user($admin);
freeze_target(static function () {
    global $wpdb;
    foreach (array_keys(peracrm_campaigns_freeze_definitions()) as $name) { $wpdb->query('DROP TABLE '.peracrm_table($name)); }
    update_option('peracrm_schema_version',24); update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>24]);
});
freeze_expect(!peracrm_campaigns_schema_ready() && peracrm_maybe_upgrade_schema() && peracrm_maybe_upgrade_schema(),'additive v24 upgrade and repeated readiness');
freeze_target(static function () { peracrm_campaigns_assert_schema(); });
$config=peracrm_campaign_configuration(true);
$config=peracrm_campaign_configuration_save($config['revision'],['senders'=>[['id'=>'freeze','name'=>'Synthetic Pera','email'=>'freeze@example.test','reply_to'=>'reply@example.test','approved'=>true]],'footer'=>'Synthetic approved identity/address','footer_approved'=>true]);
freeze_expect(!is_wp_error($config),'freeze approved configuration');
wp_set_current_user($manager);
$doc=['internal_name'=>'Freeze fixture','subject'=>'Synthetic freeze','sender_id'=>'freeze','language'=>'en','main_text'=>'Immutable captured text','audience'=>['version'=>1,'topic'=>'freeze']];
$first=freeze_subscriber('freeze-first@example.test');
$second=freeze_subscriber('freeze-second@example.test');
$third=freeze_subscriber('freeze-third@example.test');
$fourth=freeze_subscriber('freeze-fourth@example.test');
$set=freeze_capture($doc); [$draft,$capture,$nonce]=$set; $id=$draft['id'];
freeze_expect(is_wp_error(peracrm_campaign_freeze_start($id,1,(int)$capture['id'],str_repeat('0',64),$draft['document'],$nonce)),'invalid content binding rejected');
freeze_expect(is_wp_error(peracrm_campaign_freeze_start($id,1,(int)$capture['id'],$capture['content_hash'],array_replace($draft['document'],['subject'=>'unsaved']),$nonce)),'unsaved review binding rejected');
foreach ([0,$employee] as $actor) { wp_set_current_user($actor); freeze_expect(is_wp_error(freeze_start($set)),'start authority denied'); }
wp_set_current_user($manager);
update_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY,$employee);
freeze_expect(is_wp_error(freeze_start($set)),'impersonated freeze denied');
delete_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY);
$before=freeze_protected_bytes(); $a=freeze_start($set);
freeze_expect(!is_wp_error($a),'start materialization');
$again=freeze_start($set);
freeze_expect((int)$again['id']===(int)$a['id'] && (int)$again['boundary_id']===(int)$a['boundary_id'],'duplicate start preserves boundary');
freeze_expect(is_wp_error(peracrm_campaign_draft_save($id,1,$draft['document'])),'draft service blocks active edit');
freeze_expect($before===freeze_protected_bytes(),'start/replay/edit denial do not mutate subscribers, consent, suppression or tokens');
freeze_expect(is_wp_error(peracrm_campaign_freeze_publish($id,(int)$a['id'],1,$nonce)),'partial publication denied');
$after_boundary=freeze_subscriber('freeze-after-boundary@example.test');
freeze_expect($after_boundary>(int)$a['boundary_id'],'new subscriber beyond fixed boundary');
// Process through the first synthetic eligible candidate, then mutate later candidates.
while ((int)$a['last_id']<$first) { $a=freeze_next($a,$nonce,1); }
$old_version=(int)$a['version'];
freeze_expect(is_wp_error(peracrm_campaign_freeze_continue($id,(int)$a['id'],$old_version-1,$nonce,1)),'duplicate/stale continuation cannot advance');
// Change linked client email; freeze must keep subscriber mailbox and never follow client metadata.
$client=freeze_target(static function () { $c=wp_insert_post(['post_type'=>'crm_client','post_status'=>'publish','post_title'=>'Synthetic freeze client']); update_post_meta($c,'_peracrm_email','different@example.test'); global $wpdb; $wpdb->replace(peracrm_table('peracrm_party'),['party_id'=>$c,'lead_pipeline_stage'=>'new_enquiry','engagement_state'=>'engaged','disposition'=>'none','updated_at'=>gmdate('Y-m-d H:i:s')]); return $c; });
freeze_target(static function () use ($second,$client) { global $wpdb; $wpdb->update(peracrm_table('peracrm_subscribers'),['client_id'=>$client],['id'=>$second]); });
peracrm_campaigns_record_consent('freeze-third@example.test','unsubscribed',freeze_evidence());
// Adversarial direct identity changes: public profile services intentionally do not permit email rewrites.
freeze_target(static function () use ($first,$fourth) { global $wpdb; $wpdb->update(peracrm_table('peracrm_subscribers'),['email'=>'freeze-renamed@example.test','normalized_email'=>'freeze-renamed@example.test'],['id'=>$first]); $wpdb->update(peracrm_table('peracrm_subscribers'),['email'=>'freeze-first@example.test','normalized_email'=>'freeze-first@example.test'],['id'=>$fourth]); });
// Roll back after a recipient INSERT, before progress is committed.
$fail=static function ($sql) { return str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_campaign_freeze_attempts') && str_contains($sql,'`last_id`') && str_contains($sql,"'materializing'") ? 'UPDATE peracrm_fixture_missing SET id=1' : $sql; };
$before_fail=peracrm_campaign_freeze_recipients($id,(int)$a['id'],0,true);
add_filter('query',$fail); $failed=peracrm_campaign_freeze_continue($id,(int)$a['id'],(int)$a['version'],$nonce,1); remove_filter('query',$fail);
$failed_state=peracrm_campaign_freeze_get($id,(int)$a['id']);
freeze_expect(is_wp_error($failed) && $failed_state['state']==='failed' && (int)$failed_state['last_id']===(int)$a['last_id'] && $before_fail===peracrm_campaign_freeze_recipients($id,(int)$a['id'],0,true),'batch failure rolls back membership and progress; failed fence persists');
freeze_expect(is_wp_error(peracrm_campaign_freeze_publish($id,(int)$a['id'],(int)$failed_state['version'],$nonce)),'failed publication denied');
$a=peracrm_campaign_freeze_resume($id,(int)$a['id'],(int)$failed_state['version'],$nonce);
freeze_expect(!is_wp_error($a),'resume exact binding after rollback');
$a=freeze_finish($a,$nonce);
$all=[]; $after=0;
do { $page=peracrm_campaign_freeze_recipients($id,(int)$a['id'],$after,true); $all=array_merge($all,$page); if ($page) { $after=(int)end($page)['id']; } } while (count($page)===200);
$counts=peracrm_campaign_freeze_counts(); foreach ($all as $row) { $counts[$row['reason']]++; }
freeze_expect(count($all)===(int)$a['considered'] && $counts===json_decode($a['counts_json'],true) && array_sum($counts)===(int)$a['considered'],'exact mutually exclusive reconciliation');
$synthetic=array_column($all,null,'subscriber_id');
freeze_expect(!isset($synthetic[$after_boundary]) && $synthetic[$first]['reason']==='eligible' && $synthetic[$second]['mailbox']==='freeze-second@example.test' && $synthetic[$second]['reason']==='eligible' && $synthetic[$third]['reason']==='suppression' && $synthetic[$fourth]['reason']==='duplicate','fixed boundary, changed client email and withdrawal during materialization');
$before_publish=freeze_protected_bytes();
$published=peracrm_campaign_freeze_publish($id,(int)$a['id'],(int)$a['version'],$nonce);
freeze_expect(!is_wp_error($published) && $published['state']==='frozen','atomic complete publication');
$history=peracrm_campaign_frozen_get($id,1); $members=peracrm_campaign_freeze_recipients($id,(int)$a['id']);
freeze_expect($history['content']===$capture && $before_publish===freeze_protected_bytes(),'stored capture and no subscriber/evidence/token mutations');
freeze_target(static function () use ($members) { global $wpdb; $row=$members[0]; unset($row['id']); $errors=$wpdb->suppress_errors(true); freeze_expect($wpdb->insert(peracrm_table('peracrm_campaign_frozen_recipients'),$row)===false,'database revision/mailbox uniqueness'); $wpdb->suppress_errors($errors); });
freeze_expect(is_wp_error(peracrm_campaign_freeze_cancel($id,(int)$a['id'],(int)$published['version'],$nonce)) && is_wp_error(peracrm_campaign_freeze_continue($id,(int)$a['id'],(int)$published['version'],$nonce)),'published membership cannot mutate');
$later=peracrm_campaign_draft_save($id,1,array_replace($draft['document'],['subject'=>'Later revision']));
freeze_expect(!is_wp_error($later) && $later['revision']===2 && peracrm_campaign_frozen_get($id,1)===$history && peracrm_campaign_freeze_recipients($id,(int)$a['id'])===$members,'later draft preserves immutable published content/membership');
freeze_expect(is_wp_error(freeze_start($set)),'old capture cannot freeze later revision');
// Zero-included materialization cannot publish. Cancelled/failed attempts release editing, but cannot resume across edits.
$zero_set=freeze_capture(array_replace($doc,['audience'=>['version'=>1,'topic'=>'no_fixture_has_this']]));
[$zd,$zs,$zn]=$zero_set; $z=freeze_finish(freeze_start($zero_set),$zn);
freeze_expect(json_decode($z['counts_json'],true)['eligible']===0 && is_wp_error(peracrm_campaign_freeze_publish($zd['id'],(int)$z['id'],(int)$z['version'],$zn)),'zero included publication denied');
$z=peracrm_campaign_freeze_cancel($zd['id'],(int)$z['id'],(int)$z['version'],$zn);
freeze_expect(!is_wp_error($z) && is_wp_error(peracrm_campaign_freeze_publish($zd['id'],(int)$z['id'],(int)$z['version'],$zn)) && !is_wp_error(peracrm_campaign_draft_save($zd['id'],1,$zd['document'])),'cancelled publication denied and edit restriction released');
$failed_set=freeze_capture($doc); [$fd,$fs,$fn]=$failed_set; $f=freeze_start($failed_set);
add_filter('query',$fail); peracrm_campaign_freeze_continue($fd['id'],(int)$f['id'],1,$fn,1); remove_filter('query',$fail);
$f=peracrm_campaign_freeze_get($fd['id'],(int)$f['id']);
freeze_expect(!is_wp_error(peracrm_campaign_draft_save($fd['id'],1,$fd['document'])) && is_wp_error(peracrm_campaign_freeze_resume($fd['id'],(int)$f['id'],(int)$f['version'],$fn)),'failed attempt releases editing and incompatible revision invalidates resume');
// Authorized read/campaign binding and target-blog isolation.
freeze_expect(peracrm_campaign_freeze_get($id+99999,(int)$a['id'])===null && peracrm_campaign_freeze_recipients($id+99999,(int)$a['id'])===[],'foreign campaign reads denied');
wp_set_current_user($employee); $denied=false; try { peracrm_campaign_frozen_get($id,1); } catch (Throwable $e) { $denied=true; } freeze_expect($denied,'unauthorized frozen read denied');
wp_set_current_user($manager);
if (is_multisite() && $origin!==peracrm_get_target_blog_id()) { global $wpdb; freeze_expect(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'peracrm_campaign_freeze_attempts')),'no origin-blog freeze tables'); }
freeze_expect(get_current_blog_id()===$origin,'target context restored');
// Retention is manual, administrator-only and bounded; publication always survives.
freeze_expect(is_wp_error(peracrm_campaign_freeze_cleanup($zd['id'],(int)$z['id'],$zn)),'manager cleanup denied');
wp_set_current_user($admin);
freeze_target(static function () use ($z) { global $wpdb; $wpdb->update(peracrm_table('peracrm_campaign_freeze_attempts'),['updated_at'=>'2000-01-01 00:00:00'],['id'=>$z['id']]); });
$cleanup=peracrm_campaign_freeze_cleanup($zd['id'],(int)$z['id'],wp_create_nonce('peracrm_campaign_freeze_'.$zd['id']));
freeze_expect(!is_wp_error($cleanup) && $cleanup['deleted_rows']<=100 && peracrm_campaign_frozen_get($id,1)===$history,'bounded abandoned cleanup preserves published history');
freeze_expect(is_wp_error(peracrm_campaign_freeze_cleanup($id,(int)$a['id'],wp_create_nonce('peracrm_campaign_freeze_'.$id))),'published cleanup denied');
// Shared audience semantics must produce identical outcomes when inputs do not change.
wp_set_current_user($manager); $parity_set=freeze_capture($doc); [$pd,$ps,$pn]=$parity_set;
$cursor=''; do { $preview=peracrm_campaign_audience_preview($pd['id'],1,$pd['document']['audience'],$cursor); $cursor=$preview['cursor']; } while (!$preview['complete']);
$parity=freeze_finish(freeze_start($parity_set),$pn); $pc=json_decode($parity['counts_json'],true);
freeze_expect($pc['duplicate']===0 && (int)$parity['considered']===$preview['considered'] && $pc['eligible']===$preview['eligible'] && array_diff_key($pc,['eligible'=>true,'duplicate'=>true])===$preview['exclusions'],'shared eligibility parity against unchanged live inputs');
// Configuration changes invalidate both uncaptured starts and publication bindings.
$stale_set=freeze_capture($doc); wp_set_current_user($admin);
$configuration=peracrm_campaign_configuration(true); $changed=peracrm_campaign_configuration_save($configuration['revision'],$configuration['document']);
freeze_expect(!is_wp_error($changed),'advance configuration revision'); wp_set_current_user($manager);
freeze_expect(is_wp_error(freeze_start($stale_set)) && is_wp_error(peracrm_campaign_freeze_publish($pd['id'],(int)$parity['id'],(int)$parity['version'],$pn)),'stale configuration rejects start and complete publication');
$parity=peracrm_campaign_freeze_cancel($pd['id'],(int)$parity['id'],(int)$parity['version'],$pn);
freeze_expect(!is_wp_error($parity),'stale binding can still be safely cancelled');
// Save a fresh binding for the separate-process race harness.
wp_set_current_user($manager); $race_set=freeze_capture($doc);
freeze_target(static function () use ($race_set) { update_option('campaign_freeze_race_set',$race_set,false); });
echo "PASS freeze: service-only single/multisite suite; no browser acceptance claimed\n";
