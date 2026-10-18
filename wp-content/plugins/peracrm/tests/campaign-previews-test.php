<?php
require __DIR__.'/campaign-drafts-test.php';
require $root.'/inc/campaigns/content.php';
require $root.'/inc/campaigns/renderer.php';
require $root.'/inc/campaigns/preview-routes.php';
function esc_attr($v) { return esc_html($v); }
function wp_salt($scheme) { return 'synthetic-test-secret'; }
foreach (['javascript:alert(1)','data:text/html,bad','https://user:pass@example.org','https://127.0.0.1/a','https://192.168.0.1/a','http://localhost/a','https://example.org/portfolio/private-secret','https://example.org/?token=secret','https://example.org/\evil','https://example.org:8443/a'] as $bad) { campaign_expect(peracrm_campaign_public_url($bad)==='','nonpublic/unsafe destination rejected'); }
campaign_expect(peracrm_campaign_public_url('https://example.org/news?a=1&b=2')==='https://example.org/news?a=1&b=2','valid public HTTP destination');
$rules=peracrm_campaign_audience_rules(['version'=>'1','language'=>'EN','topic'=>'news']);
campaign_expect($rules===['version'=>1,'language'=>'en','topic'=>'news'],'versioned exact rules normalization');
foreach ([['budget'=>10],['version'=>2],['topic'=>[]],['language'=>'bad tag']] as $invalid) { $denied=false;try { peracrm_campaign_audience_rules($invalid); }catch (InvalidArgumentException $e) { $denied=true; }campaign_expect($denied,'unsupported rules rejected'); }
$state=['actor'=>1,'blog'=>2,'revision'=>3];$packed=peracrm_campaign_audience_pack($state);
campaign_expect(peracrm_campaign_audience_unpack($packed)===$state,'aggregate progress signature roundtrip');
$denied=false;try { peracrm_campaign_audience_unpack($packed.'x'); }catch (InvalidArgumentException $e) { $denied=true; }campaign_expect($denied,'aggregate tampering rejected');
$row=['suppression_mailbox'=>'subscriber@example.org','subscription_state'=>'subscribed','source'=>'fixture','source_ref'=>'source','evidence_ref'=>'evidence','policy_version'=>'v1','recorded_at'=>'2020-01-01','confirmed_at'=>'2020-01-01','preferred_language'=>'en','preferences_json'=>'["news"]'];
$filters=['language'=>'en','topic'=>'news'];
campaign_expect(peracrm_campaigns_eligibility_reason($row,[],$filters)==='eligible','newsletter-only no fabricated CRM data');
foreach ([[['unsubscribe'=>1,'subscription_state'=>'unknown','preferred_language'=>''],'suppression'],[['subscription_state'=>'pending'],'consent'],[['confirmed_at'=>null],'consent'],[['client_id'=>100],'linked_client'],[['preferred_language'=>''],'language'],[['preferences_json'=>'[]'],'topic']] as [$change,$expected]) { campaign_expect(peracrm_campaigns_eligibility_reason(array_replace($row,$change),[],$filters)===$expected,'exclusive policy reason '.$expected); }
foreach ([['post_type'=>'crm_client','post_status'=>'publish','disposition'=>'none','expected'=>'eligible'],['post_type'=>'crm_client','post_status'=>'trash','disposition'=>'none','expected'=>'linked_client'],['post_type'=>'crm_client','post_status'=>'publish','disposition'=>'junk','expected'=>'linked_client']] as $linked) { campaign_expect(peracrm_campaigns_eligibility_reason($row+['client_id'=>100],$linked,$filters)===$linked['expected'],'linked-client policy parity'); }
$model=['template_version'=>1,'subject'=>'A <script>evil</script> & title','preheader'=>'Preheader','language'=>'en','sender'=>'Pera <mail@example.org>','introduction'=>'Intro','main_text'=>'Main','footer'=>'Identity & address','notices'=>['Footer approval missing'],'blocks'=>[['type'=>'text','heading'=>'Story <tag>','text'=>'Text & more'],['type'=>'cta','label'=>'Go','url'=>'https://example.org/news'],['type'=>'article','title'=>'Article','excerpt'=>'Excerpt','language'=>'en','url'=>'https://example.org/article','image'=>'https://example.org/image.jpg','alt'=>'An "image"'],['type'=>'property','notice'=>'Missing translation']]];
$out=peracrm_campaign_render($model);
foreach (['Intro','Main','Footer approval missing','Story','Text &amp; more','Excerpt','Missing translation','{{recipient_preferences_url}}','Identity &amp; address'] as $value) { campaign_expect(str_contains($out['html'],$value),'HTML normalized section '.$value); }
campaign_expect(!preg_match('/<(?:script|a|form|iframe)\b/i',$out['html']) && str_contains($out['html'],'&lt;script&gt;') && str_contains($out['html'],'&quot;image&quot;'),'HTML text and attribute escaping, no active controls');
campaign_expect(str_contains($out['text'],'Story <tag>') && strpos($out['text'],'Intro')<strpos($out['text'],'Story') && strpos($out['text'],'Story')<strpos($out['text'],'Article') && count($out['destinations'])===2,'HTML/plain text parity and ordered blocks');
campaign_expect(str_contains(peracrm_campaign_preview_csp(),"script-src 'none'") && str_contains(peracrm_campaign_preview_csp(),"form-action 'none'"),'preview restrictive CSP');
echo "PASS Phase 3B pure contracts\n";
