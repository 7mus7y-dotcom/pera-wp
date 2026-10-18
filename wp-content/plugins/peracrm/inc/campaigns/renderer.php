<?php
if (!defined('ABSPATH')) { exit; }

/** Current editable draft model, not a snapshot. No subscriber data or public tokens. */
function peracrm_campaign_render_model($input)
{
    peracrm_campaign_drafts_require(true);
    $doc=peracrm_campaign_draft_document($input);
    $configuration=peracrm_campaign_configuration()['document'];
    $model=['template_version'=>1,'subject'=>$doc['subject'],'preheader'=>$doc['preheader'],'language'=>$doc['language'],'introduction'=>$doc['introduction'],'main_text'=>$doc['main_text'],'sender'=>'','footer'=>$configuration['footer_approved'] ? $configuration['footer'] : '', 'blocks'=>[], 'notices'=>peracrm_campaign_draft_missing($doc,$configuration)];
    foreach ($configuration['senders'] as $sender) {
        if ($sender['id']===$doc['sender_id'] && $sender['approved']) { $model['sender']=$sender['name'].' <'.$sender['email'].'> · Reply-to: '.$sender['reply_to']; }
    }
    return peracrm_with_target_blog(static function () use ($doc,$model) {
        $ids=[];foreach ($doc['blocks'] as $b) { if (isset($b['id'])) { $ids[]=$b['id']; } }
        if ($ids) { _prime_post_caches($ids,false,true); }
        foreach ($doc['blocks'] as $b) {
            if (in_array($b['type'],['article','property'],true)) { $b=peracrm_campaign_content_resolve_target($b['type'],$b['id'],$doc['language']); }
            elseif ($b['type']==='cta') { $b['url']=peracrm_campaign_public_url($b['url']); if ($b['url']==='') { $b['notice']='CTA public destination unavailable.'; } }
            $model['blocks'][]=$b;
        }
        return $model;
    });
}

/** HTML and text both consume this same normalized section list, in the same order. */
function peracrm_campaign_render($model)
{
    $sections=[['heading'=>'PERA · Property & perspective','text'=>'DRAFT PREVIEW · Current editable content. Domain/provider readiness unverified.'],['heading'=>$model['subject'] ?: 'Subject missing','text'=>$model['preheader']],['text'=>$model['sender'] ?: 'Approved sender missing'],['text'=>$model['introduction']],['text'=>$model['main_text']]];
    foreach ($model['notices'] as $notice) { $sections[]=['text'=>'Missing: '.$notice]; }
    $destinations=[];
    foreach ($model['blocks'] as $block) {
        $type=$block['type'];
        if (isset($block['notice'])) { $sections[]=['heading'=>ucfirst($type).' block','text'=>$block['notice']];continue; }
        if ($type==='text') { $sections[]=['heading'=>$block['heading'],'text'=>$block['text']];continue; }
        if ($type==='cta') { $section=['heading'=>$block['label'],'text'=>'','url'=>$block['url']]; }
        else {
            $section=['heading'=>$block['title'],'text'=>$block['excerpt']."\nLanguage: ".$block['language'],'url'=>$block['url'],'image'=>$block['image'],'alt'=>$block['alt']];
            if (!empty($block['image_notice'])) { $section['text'].="\n".$block['image_notice']; }
            if ($block['image']==='') { $section['text'].="\nImage unavailable."; }
            if ($type==='property') { $section['text'].="\n".($block['property']['summary'] ?? $block['property']['notice']); }
        }
        $sections[]=$section;
        if (!empty($section['url'])) { $destinations[]=['label'=>$section['heading'] ?: 'Content destination','url'=>$section['url']]; }
    }
    $sections[]=['heading'=>'Pera · Identity & address','text'=>$model['footer'] ?: 'Approved mandatory identity/address footer missing.'];
    $sections[]=['text'=>'Preferences: {{recipient_preferences_url}}' . "\nUnsubscribe: {{recipient_unsubscribe_url}}\nPlaceholder links only. No subscriber data or public tokens are issued.\nDraft content and image URLs are live; image files are not frozen."];
    $html='<!doctype html><html lang="'.esc_attr($model['language'] ?: 'und').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html($model['subject']).'</title></head><body style="margin:0;background:#f3f1eb;color:#162e2c;font-family:Arial,sans-serif;line-height:1.6"><table role="presentation" style="width:100%;border-collapse:collapse"><tr><td style="padding:16px 8px"><table role="presentation" style="width:100%;max-width:640px;margin:auto;background:#fff;border-top:6px solid #234d45;border-collapse:collapse;table-layout:fixed">';
    $text=[];
    foreach ($sections as $section) {
        $html.='<tr><td style="padding:16px 24px;border-bottom:1px solid #e5e5df;overflow-wrap:anywhere;word-break:break-word">';
        if (!empty($section['heading'])) { $html.='<h2 style="font-size:22px;line-height:1.3;margin:0 0 12px">'.esc_html($section['heading']).'</h2>'; $text[]=$section['heading']; }
        if (!empty($section['image']) && peracrm_campaign_public_url($section['image'])!=='') { $html.='<img src="'.esc_url($section['image']).'" alt="'.esc_attr($section['alt']).'" style="display:block;width:100%;max-width:592px;height:auto;border:0">'; $text[]='Image: '.$section['image'].' · '.$section['alt']; }
        if (!empty($section['text'])) { $html.='<p style="margin:0;white-space:pre-line">'.esc_html($section['text']).'</p>'; $text[]=$section['text']; }
        if (!empty($section['url']) && peracrm_campaign_public_url($section['url'])!=='') {
            // Deliberately inert in shared visual previews. Validated destinations are shown outside the frame.
            $html.='<span style="display:inline-block;padding:10px 18px;background:#234d45;color:#fff;border-radius:4px">View content · navigation disabled</span>';
            $text[]=$section['url'];
        }
        $html.='</td></tr>';$text[]='';
    }
    return ['html'=>$html.'</table></td></tr></table></body></html>','text'=>implode("\n",$text),'destinations'=>$destinations,'template_version'=>$model['template_version']];
}
