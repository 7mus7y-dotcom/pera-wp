<?php
/** Real WordPress/MySQL Phase 3A only; never production data. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
function draft_expect($ok,$label) { if (!$ok) { throw new RuntimeException('FAIL Phase 3A: '.$label); } echo 'PASS Phase 3A: '.$label."\n"; }
function draft_target(callable $fn) { return peracrm_with_target_blog($fn); }
function draft_throws(callable $fn) { try { $fn(); return false; } catch (Throwable $error) { return true; } }
$manager=draft_target(static function () { return (int)get_option('campaign_fixture_manager'); });
$admin=draft_target(static function () { return (int)get_option('campaign_fixture_administrator'); });
$employee=draft_target(static function () { return (int)get_option('campaign_fixture_employee'); });
wp_set_current_user($manager);
$origin=get_current_blog_id();
// Genuine upgrade from the deployed Phase 2 table surface, through the fenced migration owner.
draft_target(static function () {
    global $wpdb;
    foreach (array_keys(peracrm_campaigns_draft_definitions()) as $name) { $wpdb->query('DROP TABLE '.peracrm_table($name)); }
    update_option('peracrm_schema_version',22);
    update_option('peracrm_campaigns_schema_status',['state'=>'ready','version'=>22]);
});
do_action('init');
draft_expect(peracrm_campaigns_schema_ready(),'actual v22 to v23 upgrade');
draft_target(static function () { peracrm_campaigns_assert_schema(); });
$empty=peracrm_campaign_draft_save(0,0,[]);
draft_expect(!is_wp_error($empty),'incomplete draft saves with no configuration');
$doc=peracrm_campaign_draft_get($empty['id'])['document'];
draft_expect(count(peracrm_campaign_draft_missing($doc,peracrm_campaign_configuration()['document']))>=5,'missing requirements explicit');
$doc['internal_name']='Fixture <b>newsletter</b>';
$doc['subject']='Subject <script>alert(1)</script>';
$doc['preheader']='Preview'; $doc['language']='DE'; $doc['sender_id']='editorial';
$doc['introduction']="Hello\n<b>readers</b>"; $doc['main_text']='Main content';
$doc['blocks']=[['type'=>'cta','label'=>'Visit','url'=>'https://example.test/story?a=1&b=2'],['type'=>'text','heading'=>'Second','text'=>'Second body']];
$saved=peracrm_campaign_draft_save($empty['id'],1,$doc);
draft_expect(!is_wp_error($saved) && $saved['revision']===2,'save advances revision');
$reload=peracrm_campaign_draft_get($empty['id']);
draft_expect($reload['document']['language']==='de' && $reload['document']['internal_name']==='Fixture newsletter' && !str_contains($reload['document']['subject'],'<'),'sanitized document round trip');
draft_expect(array_column($reload['document']['blocks'],'type')===['cta','text'],'ordered blocks survive reload');
$before=peracrm_campaign_draft_history($empty['id']);
$conflict=peracrm_campaign_draft_save($empty['id'],1,['subject'=>'stale overwrite']);
draft_expect(is_wp_error($conflict) && $conflict->get_error_code()==='campaign_conflict' && peracrm_campaign_draft_history($empty['id'])===$before,'stale save changes neither current draft nor history');
draft_expect($before[0]['document_json']===$reload['document_json'] && $before[1]['document_json']===wp_json_encode($empty['document']),'immutable snapshots retain prior content');
draft_expect((int)$before[0]['actor_user_id']===$manager && (int)$reload['created_by']===$manager,'real actor audited');
$copy=peracrm_campaign_draft_duplicate($empty['id']);
draft_expect(!is_wp_error($copy) && $copy['id']!==$empty['id'] && $copy['revision']===1 && count(peracrm_campaign_draft_history($copy['id']))===1,'duplication creates new draft and new history only');
draft_expect($copy['document']['blocks']===$reload['document']['blocks'] && str_ends_with($copy['document']['internal_name'],' (copy)'),'duplication preserves ordered content');
$rows=peracrm_campaign_draft_list();
draft_expect(count($rows)>=2 && isset($rows[0]['internal_name'],$rows[0]['subject'],$rows[0]['language'],$rows[0]['status'],$rows[0]['updated_at']),'list supplies required fields');
// Revision insert failure must roll back the mutable draft and preserve its old history.
$fail=static function ($query) { return str_starts_with($query,'INSERT INTO') && str_contains($query,'peracrm_campaign_draft_revisions') ? 'INSERT INTO peracrm_fixture_missing VALUES (1)' : $query; };
add_filter('query',$fail);
$failed=peracrm_campaign_draft_save($empty['id'],2,['subject'=>'must roll back']);
remove_filter('query',$fail);
draft_expect(is_wp_error($failed) && peracrm_campaign_draft_get($empty['id'])['document_json']===$reload['document_json'] && peracrm_campaign_draft_history($empty['id'])===$before,'revision failure atomically rolls back current content');
$invalids=[['subject'=>str_repeat('x',201)],['preheader'=>str_repeat('x',301)],['internal_name'=>str_repeat('x',161)],['introduction'=>str_repeat('x',4001)],['main_text'=>str_repeat('x',16001)],['language'=>'not a tag'],['sender_id'=>'<bad> invalid'],['schema_version'=>2],['recipient_ids'=>[1]],['blocks'=>array_fill(0,21,['type'=>'text'])],['blocks'=>[['type'=>'property']]],['blocks'=>[['type'=>'article']]],['blocks'=>[['type'=>'cta','url'=>'javascript:alert(1)']]],['blocks'=>[['type'=>'cta','url'=>'https://user:pass@example.test/']]],['blocks'=>[['type'=>'text','text'=>str_repeat('x',4001)]]],['blocks'=>[['type'=>'text','html'=>'<p>unsupported</p>']]],['subject'=>[]]];
foreach ($invalids as $n=>$invalid) { draft_expect(is_wp_error(peracrm_campaign_draft_save($empty['id'],2,$invalid)),'invalid/bounded input rejected '.$n); }
draft_expect(peracrm_campaign_draft_history($empty['id'])===$before,'invalid input never appends revisions');
$configuration=['senders'=>[['id'=>'editorial','name'=>'Pera','email'=>'news@example.test','reply_to'=>'reply@example.test','approved'=>true]],'footer'=>"Pera legal identity\nPostal address",'footer_approved'=>true];
draft_expect(is_wp_error(peracrm_campaign_configuration_save(0,$configuration)) && draft_throws(static function () { peracrm_campaign_configuration(true); }),'manager denied administrative configuration reads and writes');
wp_set_current_user($admin);
$config=peracrm_campaign_configuration_save(0,$configuration);
draft_expect(!is_wp_error($config) && peracrm_campaign_configuration(true)['approved_by']===$admin,'target administrator approval audited');
draft_expect(is_wp_error(peracrm_campaign_configuration_save(0,$configuration)),'stale configuration save conflicts');
draft_expect(is_wp_error(peracrm_campaign_configuration_save(1,['senders'=>array_fill(0,11,$configuration['senders'][0])])),'configuration bounded to ten identities');
draft_expect(is_wp_error(peracrm_campaign_configuration_save(1,['senders'=>[['id'=>'bad','approved'=>true]]])),'incomplete identity cannot be approved');
draft_expect(is_wp_error(peracrm_campaign_configuration_save(1,['footer_approved'=>true])),'empty footer cannot be approved');
wp_set_current_user($manager);
draft_expect(peracrm_campaign_draft_missing($reload['document'],peracrm_campaign_configuration()['document'])===[],'approved configuration resolves missing draft requirements');
wp_set_current_user($admin);
$unapproved=$configuration; $unapproved['senders'][0]['approved']=false; $unapproved['footer_approved']=false;
peracrm_campaign_configuration_save(1,$unapproved);
wp_set_current_user($manager);
draft_expect(peracrm_campaign_configuration()['document']['senders']===[] && peracrm_campaign_configuration()['document']['footer']==='','editor sees only approved sender/footer values');
draft_expect(count(peracrm_campaign_draft_missing($reload['document'],peracrm_campaign_configuration()['document']))===2 && peracrm_campaign_draft_get($empty['id'])['document_json']===$reload['document_json'],'revoked approval preserves saved draft content');
$reordered=$reload['document']; $reordered['blocks']=array_reverse($reordered['blocks']);
draft_expect(!is_wp_error(peracrm_campaign_draft_save($empty['id'],2,$reordered)),'saving remains possible after approval removal');
draft_expect(array_column(peracrm_campaign_draft_get($empty['id'])['document']['blocks'],'type')===['text','cta'],'reordered blocks persisted');
foreach ([$employee,0] as $denied) {
    wp_set_current_user($denied);
    draft_expect(draft_throws(static function () { peracrm_campaign_draft_list(); }) && draft_throws(static function () use ($empty) { peracrm_campaign_draft_get($empty['id']); }) && draft_throws(static function () use ($empty) { peracrm_campaign_draft_history($empty['id']); }) && draft_throws(static function () { peracrm_campaign_configuration(); }),'employee/anonymous reads denied');
    draft_expect(is_wp_error(peracrm_campaign_draft_save(0,0,[])) && is_wp_error(peracrm_campaign_draft_duplicate($empty['id'])) && is_wp_error(peracrm_campaign_configuration_save(2,$configuration)),'employee/anonymous writes denied');
}
wp_set_current_user($manager);
draft_target(static function () use ($manager,$employee) { update_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY,$employee); });
draft_expect(is_wp_error(peracrm_campaign_draft_save(0,0,[])) && is_wp_error(peracrm_campaign_draft_duplicate($empty['id'])),'impersonated create/save/duplicate denied');
delete_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY);
wp_set_current_user($admin);
draft_target(static function () use ($admin,$employee) { update_user_meta($admin,PERACRM_VIEW_AS_USER_META_KEY,$employee); });
draft_expect(is_wp_error(peracrm_campaign_configuration_save(2,$configuration)),'impersonated administrator configuration denied');
delete_user_meta($admin,PERACRM_VIEW_AS_USER_META_KEY);
if (is_multisite()) {
    // A blog-1-only administrator is not a target administrator or campaign reader.
    switch_to_blog(1);
    $outsider=wp_insert_user(['user_login'=>'campaign-origin-only','user_pass'=>wp_generate_password(),'role'=>'administrator']);
    restore_current_blog();
    wp_set_current_user($outsider);
    draft_expect(draft_throws(static function () { peracrm_campaign_draft_list(); }) && is_wp_error(peracrm_campaign_configuration_save(2,$configuration)),'origin-blog authority cannot cross target boundary');
    foreach (array_keys(peracrm_campaigns_draft_definitions()) as $name) { draft_expect($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.$name)))===null,'no draft machinery on origin blog '.$name); }
}
wp_set_current_user($admin);
// Real v23 unique-revision assertion failure contains normal init, requires explicit recovery.
draft_target(static function () { global $wpdb; $wpdb->query('ALTER TABLE '.peracrm_table('peracrm_campaign_draft_revisions').' DROP INDEX campaign_revision'); delete_option('peracrm_campaigns_schema_status'); });
$continued=false; add_action('init',static function () use (&$continued) { $continued=true; },6); do_action('init');
draft_expect($continued && !peracrm_campaigns_schema_ready() && is_wp_error(peracrm_campaign_draft_save(0,0,[])),'draft schema failure contains init and denies writes');
$ddl=0; $watch=static function ($query) use (&$ddl) { if (str_starts_with($query,'CREATE TABLE')) { $ddl++; } return $query; }; add_filter('query',$watch); do_action('init'); remove_filter('query',$watch); draft_expect($ddl===0,'failed migration circuit breaker prevents repeated DDL');
draft_target(static function () { global $wpdb; $wpdb->query('ALTER TABLE '.peracrm_table('peracrm_campaign_draft_revisions').' ADD UNIQUE KEY campaign_revision (campaign_id,revision)'); });
draft_expect(peracrm_campaigns_retry_schema()===true,'administrator recovers draft schema assertion');
draft_target(static function () { peracrm_upgrade_schema_to(23,23); });
draft_expect((int)peracrm_campaign_draft_get($empty['id'])['revision']===3 && count(peracrm_campaign_draft_history($empty['id']))===3,'repeated migration preserves all immutable revisions');
wp_set_current_user($manager);
$race=peracrm_campaign_draft_save(0,0,['internal_name'=>'Concurrency fixture','subject'=>'Original']);
draft_target(static function () use ($race) { update_option('campaign_draft_race_id',$race['id']); });
// Browser fixture exposes only synthetic IDs/URLs and disposable login credentials.
file_put_contents('/var/www/html/phase3-fixture.json',wp_json_encode(['target'=>draft_target(static function () { return home_url('/'); }),'id'=>$empty['id']]));
draft_expect(get_current_blog_id()===$origin && (!is_multisite() || !ms_is_switched()),'origin context restored after every success/denial/failure');
