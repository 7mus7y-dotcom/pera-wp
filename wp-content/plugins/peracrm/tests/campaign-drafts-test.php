<?php
/** Pure normalization/authority regressions; actual SQL and contention are in integration/campaigns. */
require __DIR__.'/campaign-foundations-test.php';
require $root.'/inc/campaigns/drafts.php';
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function wp_parse_url($url,$component=-1) { return parse_url($url,$component); }
function draft_rejects($input) { try { peracrm_campaign_draft_document($input); return false; } catch (InvalidArgumentException $error) { return true; } }
$GLOBALS['campaign_actor']=1;
$doc=peracrm_campaign_draft_document(['subject'=>'<b>hello</b>','language'=>'EN','blocks'=>[['type'=>'text','text'=>'<p>plain</p>'],['type'=>'cta','label'=>'Read','url'=>'https://example.test/']]]);
campaign_expect($doc['subject']==='hello' && $doc['language']==='en' && $doc['blocks'][0]['text']==='plain' && array_column($doc['blocks'],'type')===['text','cta'],'draft plain-text sanitization and ordered schema');
foreach (['internal_name'=>160,'subject'=>200,'preheader'=>300,'language'=>35,'sender_id'=>64,'introduction'=>4000,'main_text'=>16000] as $field=>$limit) { campaign_expect(draft_rejects([$field=>str_repeat('x',$limit+1)]),'draft bound '.$field); }
foreach ([['schema_version'=>2],['schema_version'=>'1x'],['blocks'=>array_fill(0,21,['type'=>'text'])],['blocks'=>[['type'=>'article']]],['blocks'=>[['type'=>'property']]],['blocks'=>[['type'=>'text','html'=>'<p>HTML</p>']]],['blocks'=>[['type'=>'cta','url'=>'javascript:alert(1)']]],['blocks'=>[['type'=>'cta','url'=>'https://user:pass@example.test/']]],['sender_id'=>'<bad> invalid'],['subject'=>[]],['audience'=>[]]] as $invalid) { campaign_expect(draft_rejects($invalid),'draft unsupported/invalid shape rejected'); }
$config=peracrm_campaign_configuration_document(['senders'=>[['id'=>'news','name'=>'Pera','email'=>'news@example.test','reply_to'=>'reply@example.test','approved'=>true]],'footer'=>'Legal identity and postal address','footer_approved'=>true]);
$doc['internal_name']='News'; $doc['sender_id']='news';
campaign_expect(peracrm_campaign_draft_missing($doc,$config)===[],'approved sender/footer satisfy explicit requirements');
$config['senders'][0]['approved']=false; $config['footer_approved']=false;
campaign_expect(count(peracrm_campaign_draft_missing($doc,$config))===2,'missing approvals remain explicit without changing content');
foreach ([0,3] as $actor) {
    $GLOBALS['campaign_actor']=$actor;
    campaign_expect(is_wp_error(peracrm_campaign_draft_save(0,0,$doc)) && is_wp_error(peracrm_campaign_draft_duplicate(1)) && is_wp_error(peracrm_campaign_configuration_save(0,[])),'draft write authority denied');
    $denied=false; try { peracrm_campaign_drafts_require(); } catch (RuntimeException $error) { $denied=true; }
    campaign_expect($denied,'draft read authority denied');
}
$GLOBALS['campaign_actor']=2;
campaign_expect(is_wp_error(peracrm_campaign_configuration_save(0,[])),'manager configuration denied');
$GLOBALS['campaign_actor']=1; $GLOBALS['campaign_impersonating']=true;
campaign_expect(is_wp_error(peracrm_campaign_draft_save(0,0,$doc)) && is_wp_error(peracrm_campaign_draft_duplicate(1)) && is_wp_error(peracrm_campaign_configuration_save(0,[])),'all impersonated campaign writes denied');
echo "PASS: draft schema/authority contracts (not SQL evidence)\n";
