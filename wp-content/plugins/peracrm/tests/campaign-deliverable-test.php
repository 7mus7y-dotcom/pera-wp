<?php
/** Synthetic renderer/capture contracts. No tokens, recipient input or network. */
require __DIR__.'/campaign-snapshots-test.php';
$deliverable_model=$saved['payload']['content']['model'];
$deliverable_model['subject']='Subject <script> & "quoted"';
$deliverable_model['main_text']='Text <img src=x onerror=evil> & details';
$deliverable_model['blocks']=[
    ['type'=>'article','title'=>'Story <b> & news','excerpt'=>'Story summary','language'=>'en','url'=>'https://example.org/story?a=1&b=2','image'=>'https://example.org/image.jpg','alt'=>'An "image" <tag>'],
    ['type'=>'property','title'=>'Property','excerpt'=>'Property summary','language'=>'en','url'=>'https://example.org/property','image'=>'','alt'=>'','property'=>['summary'=>'2 beds · Size 80 m² · Price USD 200,000']],
    ['type'=>'cta','label'=>'Read "more" & enjoy','url'=>'https://example.org/contact'],
];
$output=peracrm_campaign_render($deliverable_model,'deliverable_v2');
$slots=peracrm_campaign_deliverable_slots();
peracrm_campaign_deliverable_validate($output,$slots);
campaign_expect(substr_count($output['html'],'<a href=')===5 && count($output['destinations'])===3, 'article, property, CTA and two typed slots are HTML links');
campaign_expect(str_contains($output['html'],'href="https://example.org/story?a=1&amp;b=2"') && str_contains($output['html'],'alt="An &quot;image&quot; &lt;tag&gt;"') && !str_contains($output['html'],'<script>') && str_contains($output['html'],'&lt;img src=x onerror=evil&gt;'), 'HTML body, link labels, attributes and query separators escaped');
foreach ($output['destinations'] as $link) {
    campaign_expect(str_contains($output['text'],$link['url']) && str_contains($output['html'],esc_attr($link['url'])), 'HTML/text have identical public destination');
}
campaign_expect(strpos($output['text'],'Story summary')<strpos($output['text'],'Property summary') && strpos($output['text'],'Property summary')<strpos($output['text'],'Read "more"') && str_contains($output['text'],'Size 80 m²') && str_contains($output['text'],$deliverable_model['main_text']), 'coherent ordered text and property details');
foreach (['DRAFT PREVIEW','REVIEWED CONTENT','navigation disabled','No email is sent','Placeholder links only'] as $copy) { campaign_expect(!str_contains($output['text'],$copy) && !str_contains($output['html'],$copy), 'staff-only copy absent from deliverable'); }
foreach (['javascript:alert(1)','data:text/html,x','https://user:pass@example.org','https://127.0.0.1/x','https://example.org/?token=secret','https://example.org/" onmouseover="evil','https://example.org/\\evil',"https://example.org/x\r\ny"] as $url) {
    $bad=$deliverable_model; $bad['blocks'][0]['url']=$url;
    campaign_expect(snapshot_rejects(fn()=>peracrm_campaign_render($bad,'deliverable_v2')), 'unsafe deliverable destination fails closed');
}
foreach (['subject','footer','main_text','sender'] as $key) {
    $bad=$deliverable_model; $bad[$key]='Injected {{recipient_unsubscribe_url}}';
    campaign_expect(snapshot_rejects(fn()=>peracrm_campaign_render($bad,'deliverable_v2')), 'author slot injection denied in '.$key);
}
foreach ([
    str_replace('href="{{recipient_preferences_url}}"','title="{{recipient_preferences_url}}"',$output['html']),
    $output['html'].'{{recipient_unsubscribe_url}}',
    str_replace('{{recipient_preferences_url}}','https://example.org/private',$output['html']),
    $output['html'].'{{unknown}}',
] as $html) { campaign_expect(snapshot_rejects(fn()=>peracrm_campaign_deliverable_validate(array_replace($output,['html'=>$html]),$slots)), 'missing, duplicate, unknown or wrong-context HTML slots denied'); }
$bad_text=str_replace("\n{{recipient_preferences_url}}\n",' inline {{recipient_preferences_url}} ',$output['text']);
campaign_expect(snapshot_rejects(fn()=>peracrm_campaign_deliverable_validate(array_replace($output,['text'=>$bad_text]),$slots)), 'inline text slot denied');
foreach (['purpose'=>'unsubscribe','html_context'=>'raw_html','scheme'=>'http','binding'=>'subscriber'] as $key=>$value) {
    $bad=$slots; $bad['recipient_preferences_url'][$key]=$value;
    campaign_expect(snapshot_rejects(fn()=>peracrm_campaign_deliverable_validate($output,$bad)), 'slot definition drift denied: '.$key);
}
$hash=hash('sha256',wp_json_encode($output));
$preview=peracrm_campaign_staff_preview_html($output['html']);
campaign_expect(!preg_match('/<a\b|href=/i',$preview) && str_contains($preview,'Story &lt;b&gt;') && $hash===hash('sha256',wp_json_encode($output)), 'preview removes navigation without mutating captured output');
// Historical review-only bytes decode exactly, regardless of today's renderer.
$historical_content=['schema_version'=>1,'template_version'=>1,'output'=>['html'=>'<p>REVIEWED CONTENT</p>','text'=>'Historical review-only bytes']];
$historical_payload=['schema_version'=>1,'state'=>'unpublished_content','content'=>$historical_content];
$historical=array_replace($saved,['payload_json'=>wp_json_encode($historical_payload),'content_hash'=>hash('sha256',wp_json_encode($historical_content))]);
unset($historical['payload']);
$decoded=peracrm_with_target_blog(fn()=>peracrm_campaign_snapshot_decode($historical));
campaign_expect($decoded['payload']===$historical_payload && $decoded['payload_json']===$historical['payload_json'] && $decoded['content_hash']===$historical['content_hash'] && !isset($decoded['payload']['content']['slots']), 'historical v1 bytes/hash preserved without silent upgrade');
// Restore fixture state for fresh proofs, then sign drift as if a previous renderer issued it.
$GLOBALS['snapshot_configuration']=$original_configuration;
$GLOBALS['wpdb']->tables=[];
$fresh=peracrm_campaign_snapshot_review(10,2,$document);
$fresh_binding=peracrm_campaign_audience_unpack($fresh['review_proof']);
foreach (['renderer_version'=>'old_renderer','slots'=>[], 'output'=>array_replace($fresh['content']['output'],['text'=>'Old output']), 'configuration'=>[]] as $key=>$value) {
    $old=$fresh['content']; $old[$key]=$value;
    $proof=array_replace($fresh_binding,['content_hash'=>hash('sha256',wp_json_encode($old))]);
    campaign_expect(is_wp_error(peracrm_campaign_snapshot_capture(10,2,$document,peracrm_campaign_audience_pack($proof),$nonce)) && campaign_count('peracrm_campaign_content_snapshots')===0, 'stale approval rejects changed '.$key);
}
$old_proof=array_replace($fresh_binding,['purpose'=>'content_review_v1','version'=>1]);
campaign_expect(is_wp_error(peracrm_campaign_snapshot_capture(10,2,$document,peracrm_campaign_audience_pack($old_proof),$nonce)), 'v1 review proof cannot approve v2 capture');
$captured=peracrm_campaign_snapshot_capture(10,2,$document,$fresh['review_proof'],$nonce);
campaign_expect(!is_wp_error($captured) && $captured['payload']['schema_version']===2 && $captured['payload']['content']['slots']===$slots && str_contains($captured['payload']['content']['output']['html'],'href="https://example.org/story"'), 'fresh v2 approval captures exact linked template and slot contract');
echo "PASS deliverable rendering, slot contexts, immutable approval and historical compatibility\n";
