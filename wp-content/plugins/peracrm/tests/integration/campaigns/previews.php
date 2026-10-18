<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
function preview_expect($ok,$label) { if (!$ok) { throw new RuntimeException('FAIL Phase 3B: '.$label); } echo 'PASS Phase 3B: '.$label."\n"; }
function preview_throws(callable $fn) { try { $fn(); return false; } catch (Throwable $e) { return true; } }
$origin=get_current_blog_id();
$manager=peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_manager'); });
$employee=peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_employee'); });
wp_set_current_user($manager);
$unavailable=peracrm_campaign_content_search('property','',0,'en');
preview_expect($unavailable['items']===[] && str_contains($unavailable['notice'],'not registered'),'default theme property unavailable explicitly');
peracrm_with_target_blog(static function () {
    Pera_ML_Storage::install();
    update_option('campaign_fixture_property_enabled',1);
    register_post_type('property',['public'=>true,'label'=>'Fixture properties','supports'=>['title','editor','excerpt','thumbnail']]);
});
$ids=peracrm_with_target_blog(static function () {
    $ids=[];
    foreach (range(1,25) as $i) { $ids[]=wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'Phase3B Article '.$i,'post_content'=>'Published story '.str_repeat('long-text ',90)]); }
    foreach (['draft','private','trash','pending','future'] as $status) { wp_insert_post(['post_type'=>'post','post_status'=>$status,'post_title'=>'Phase3B SECRET '.$status,'post_content'=>'secret','post_date'=>gmdate('Y-m-d H:i:s',time()+86400),'post_date_gmt'=>gmdate('Y-m-d H:i:s',time()+86400)]); }
    wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_password'=>'secret','post_title'=>'Phase3B SECRET password']);
    $property=wp_insert_post(['post_type'=>'property','post_status'=>'publish','post_title'=>'Phase3B Property','post_content'=>'A public v2 property.']);
    update_post_meta($property,'v2_units',2);
    foreach ([['v2_beds'=>2,'v2_gross_size_min'=>90,'v2_gross_size_max'=>110,'v2_price_usd_min'=>350000,'v2_price_usd_max'=>400000],['v2_beds'=>3]] as $i=>$row) { foreach ($row as $key=>$value) { update_post_meta($property,'v2_units_'.$i.'_'.$key,$value); } }
    update_post_meta($property,'price',1);update_post_meta($property,'price_usd',2);
    update_option('campaign_fixture_article',$ids[0]);update_option('campaign_fixture_property',$property);
    return ['articles'=>$ids,'property'=>$property];
});
$first=peracrm_campaign_content_search('article','Phase3B',0,'en');
$second=peracrm_campaign_content_search('article','Phase3B',$first['next'],'en');
echo 'Selector page diagnostics: '.wp_json_encode(['first'=>array_column($first['items'],'id'),'second'=>array_column($second['items'],'id'),'next'=>$second['next']])."\n";
preview_expect(count($first['items'])===20 && count($second['items'])===5 && $second['next']===null,'public selector stable bounded pagination excludes private/draft/password/deleted');
preview_expect(!array_intersect(array_column($first['items'],'id'),array_column($second['items'],'id')),'selector pages do not overlap');
$p=peracrm_campaign_content_resolve('property',$ids['property'],'en');
preview_expect(str_contains($p['property']['summary'],'USD 350,000–400,000') && str_contains($p['property']['summary'],'unavailable') && $p['property']['units'][0]['v2_gross_size_min']===90.0,'canonical raw v2 pricing/size, missing unit values and no deprecated prices');
preview_expect(isset(peracrm_campaign_content_resolve('article',$ids['articles'][0],'de')['notice']),'missing translation never substitutes source');
peracrm_with_target_blog(static function () use ($ids) {
    $p=get_post($ids['articles'][0]);
    (new Pera_ML_Storage())->put('post',$p->ID,'post_title','de',$p->post_title,'Deutscher Titel');
    (new Pera_ML_Storage())->put('post',$p->ID,'post_content','de',$p->post_content,'Deutscher Inhalt');
});
preview_expect(peracrm_campaign_content_resolve('article',$ids['articles'][0],'de')['title']==='Deutscher Titel','actual stored current translations via plugin API');
preview_expect(isset(peracrm_campaign_content_resolve('article',$ids['articles'][0],'ru')['notice']),'public language gate respected');
peracrm_with_target_blog(static function () use ($ids) { wp_update_post(['ID'=>$ids['articles'][0],'post_content'=>'Changed canonical source']); });
preview_expect(isset(peracrm_campaign_content_resolve('article',$ids['articles'][0],'de')['notice']),'stale translations unavailable');
$document=['internal_name'=>'Phase3B preview','subject'=>'Preview subject','language'=>'en','introduction'=>str_repeat('Long introduction with words ',80),'blocks'=>[['type'=>'article','id'=>$ids['articles'][1]],['type'=>'property','id'=>$ids['property']],['type'=>'text','heading'=>'Last','text'=>'Plain story'],['type'=>'cta','label'=>'Read more','url'=>'https://example.org/news']],'audience'=>['version'=>1,'language'=>'en','topic'=>'news']];
$draft=peracrm_campaign_draft_save(0,0,$document);
preview_expect(!is_wp_error($draft),'new blocks and audience rules save in immutable revision');
$reloaded=peracrm_campaign_draft_get($draft['id']);
preview_expect(array_column($reloaded['document']['blocks'],'type')===['article','property','text','cta'] && $reloaded['document']['audience']=== $document['audience'],'new fields save/reload in order');
$duplicate=peracrm_campaign_draft_duplicate($draft['id']);
preview_expect(!is_wp_error($duplicate) && $duplicate['document']['blocks']===$reloaded['document']['blocks'],'duplication preserves references/rules');
preview_expect(is_wp_error(peracrm_campaign_draft_save($draft['id'],0,$document)),'new block saves retain stale protection');
$output=peracrm_campaign_render(peracrm_campaign_render_model($document));
preview_expect(str_contains($output['html'],'USD 350,000') && str_contains($output['text'],'USD 350,000') && str_contains($output['text'],'{{recipient_unsubscribe_url}}') && !preg_match('/<(?:script|form|a|iframe)\b/i',$output['html']),'one HTML/plain text model, inert preview and placeholders');
peracrm_with_target_blog(static function () use ($ids) { wp_update_post(['ID'=>$ids['articles'][1],'post_status'=>'private']); });
$output=peracrm_campaign_render(peracrm_campaign_render_model($document));
preview_expect(str_contains($output['text'],'no longer public') && !str_contains($output['text'],'Phase3B Article 2'),'preview revalidates public status without leaking title');
wp_set_current_user($employee);
foreach ([static function () { peracrm_campaign_content_search('article','',0,'en'); },static function () use ($document) { peracrm_campaign_render_model($document); },static function () use ($draft) { peracrm_campaign_audience_preview($draft['id'],1,[]); }] as $fn) { preview_expect(preview_throws($fn),'employee denied services'); }
wp_set_current_user($manager);
peracrm_with_target_blog(static function () use ($manager,$employee) { update_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY,$employee); });
preview_expect(preview_throws(static function () use ($document) { peracrm_campaign_render_model($document); }) && preview_throws(static function () { peracrm_campaign_content_search('article','',0,'en'); }),'impersonation denies selector and renderer');
delete_user_meta($manager,PERACRM_VIEW_AS_USER_META_KEY);
// Batched synthetic subscribers cover every exclusion and no linked-client inference.
peracrm_with_target_blog(static function () {
    global $wpdb;
    foreach (range(1,405) as $i) {
        $email='phase3b-'.$i.'@example.test';
        $data=['email'=>$email,'normalized_email'=>$email,'client_id'=>null,'subscription_state'=>'subscribed','source'=>'fixture','source_ref'=>'source','evidence_ref'=>'evidence','policy_version'=>'v1','recorded_at'=>'2020-01-01 00:00:00','confirmed_at'=>'2020-01-01 00:00:00','preferred_language'=>'en','preferences_json'=>'["news"]','created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'),'version'=>1];
        if ($i%7===0) { $data['subscription_state']='pending'; }
        elseif ($i%7===1) { $data['client_id']=99999999; }
        elseif ($i%7===2) { $data['preferred_language']=''; }
        elseif ($i%7===3) { $data['preferences_json']='[]'; }
        $wpdb->insert(peracrm_table('peracrm_subscribers'),$data);
        $wpdb->insert(peracrm_table('peracrm_email_suppressions'),['normalized_email'=>$email,'unsubscribe'=>$i%7===4 ? 1 : 0,'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
    }
});
$before=peracrm_with_target_blog(static function () { global $wpdb; $out=[];foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events'] as $table) { $out[$table]=$wpdb->get_results('SELECT * FROM '.peracrm_table($table).' ORDER BY normalized_email',ARRAY_A); } $out['token_count']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.peracrm_table('peracrm_subscriber_tokens')); return wp_json_encode($out); });
$expected=peracrm_with_target_blog(static function () use ($document) {
    global $wpdb;$emails=$wpdb->get_col('SELECT normalized_email FROM '.peracrm_table('peracrm_subscribers'));$count=0;
    foreach ($emails as $email) { if (peracrm_campaigns_is_eligible($email,['language'=>'en','topic'=>'news'])) { $count++; } }
    return $count;
});
$queries=[];$capture=static function ($sql) use (&$queries) { $queries[]=$sql;return $sql; };
add_filter('query',$capture);$batch=peracrm_campaign_audience_preview($draft['id'],1,$document['audience']);remove_filter('query',$capture);
preview_expect(!$batch['complete'] && $batch['considered']===200 && count($queries)<=10,'first bounded batch, constant query count');
$cursor=$batch['cursor'];
$bound_state=peracrm_campaign_audience_unpack($cursor);
$other_rules=$document['audience'];$other_rules['topic']='properties';
preview_expect(preview_throws(static function () use ($draft,$other_rules,$cursor) { peracrm_campaign_audience_preview($draft['id'],1,$other_rules,$cursor); }),'rule changes invalidate progress');
$forged_state=$bound_state;$forged_state['actor']++;
preview_expect(preview_throws(static function () use ($draft,$document,$forged_state) { peracrm_campaign_audience_preview($draft['id'],1,$document['audience'],peracrm_campaign_audience_pack($forged_state)); }),'actor binding invalidates correctly signed foreign progress');
$forged_state=$bound_state;$forged_state['blog']++;
preview_expect(preview_throws(static function () use ($draft,$document,$forged_state) { peracrm_campaign_audience_preview($draft['id'],1,$document['audience'],peracrm_campaign_audience_pack($forged_state)); }),'blog binding invalidates correctly signed foreign progress');
$forged_state=$bound_state;$forged_state['expires']=time()-1;
preview_expect(preview_throws(static function () use ($draft,$document,$forged_state) { peracrm_campaign_audience_preview($draft['id'],1,$document['audience'],peracrm_campaign_audience_pack($forged_state)); }),'expired progress rejected');
$replay=peracrm_campaign_audience_preview($draft['id'],1,$document['audience']);
preview_expect($replay['considered']===$batch['considered'],'restart/retry does not mutate subscribers');
while (!$batch['complete']) { $batch=peracrm_campaign_audience_preview($draft['id'],1,$document['audience'],$batch['cursor']); }
preview_expect($batch['eligible']===$expected && $batch['considered']===$batch['eligible']+array_sum($batch['exclusions']),'batched eligibility exact parity and mutually exclusive reconciliation');
preview_expect(preview_throws(static function () use ($draft,$document,$cursor) { peracrm_campaign_audience_preview($draft['id'],1,$document['audience'],$cursor.'x'); }),'tampered progress denied');
$next=peracrm_campaign_draft_save($draft['id'],1,$document);
preview_expect(preview_throws(static function () use ($draft,$document,$cursor) { peracrm_campaign_audience_preview($draft['id'],2,$document['audience'],$cursor); }),'revision invalidates multi-request progress');
$after=peracrm_with_target_blog(static function () { global $wpdb;$out=[];foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events'] as $table) { $out[$table]=$wpdb->get_results('SELECT * FROM '.peracrm_table($table).' ORDER BY normalized_email',ARRAY_A); }$out['token_count']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.peracrm_table('peracrm_subscriber_tokens')); return wp_json_encode($out); });
preview_expect($before===$after,'preview performs no subscriber/consent/suppression mutations or public token issuance');
peracrm_with_target_blog(static function () { global $wpdb; preview_expect($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like(peracrm_table('peracrm_campaign_recipients'))))===null,'no recipient ledger exists'); });
preview_expect(get_current_blog_id()===$origin,'target context restored');
peracrm_with_target_blog(static function () use ($draft) { update_option('campaign_fixture_preview_draft',$draft['id']); });
