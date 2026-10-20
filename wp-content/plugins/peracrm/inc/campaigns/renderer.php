<?php
if (!defined('ABSPATH')) { exit; }

/** Current editable draft model, not a snapshot. No subscriber data or public tokens. */
function peracrm_campaign_render_model($input, $configuration = null)
{
    peracrm_campaign_drafts_require(true);
    $doc=peracrm_campaign_draft_document($input);
    // Snapshot callers supply the exact approved document bound to their review.
    // Ordinary editable previews still resolve the current configuration here.
    if ($configuration === null) { $configuration=peracrm_campaign_configuration()['document']; }
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
function peracrm_campaign_render($model, $format = 'review_v1')
{
    if (!in_array($format, ['review_v1', 'deliverable_v2'], true)) { throw new InvalidArgumentException('Unsupported renderer.'); }
    $deliverable = $format === 'deliverable_v2';
    if ($deliverable) { peracrm_campaign_deliverable_model_validate($model); }
    $captured=($model['content_context'] ?? '')==='immutable_capture';
    $sections=[['heading'=>'PERA · Property & perspective','text'=>$captured ? 'REVIEWED CONTENT · Prepared immutable revision. No email is sent. Domain/provider readiness unverified.' : 'DRAFT PREVIEW · Current editable content. Domain/provider readiness unverified.'],['heading'=>$model['subject'] ?: 'Subject missing','text'=>$model['preheader']],['text'=>$model['sender'] ?: 'Approved sender missing'],['text'=>$model['introduction']],['text'=>$model['main_text']]];
    if ($deliverable) { $sections[0] = ['heading'=>'PERA · Property & perspective']; }
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
    if ($deliverable) {
        foreach (peracrm_campaign_deliverable_slots() as $name=>$slot) {
            $sections[] = ['heading'=>$slot['label'], 'text'=>'', 'slot'=>$name];
        }
    } else {
        $sections[]=['text'=>'Preferences: {{recipient_preferences_url}}' . "\nUnsubscribe: {{recipient_unsubscribe_url}}\nPlaceholder links only. No subscriber data or public tokens are issued.\n" . ($captured ? 'Content bytes and image URLs are stored; image files are not frozen.' : 'Draft content and image URLs are live; image files are not frozen.')];
    }
    $html='<!doctype html><html lang="'.esc_attr($model['language'] ?: 'und').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html($model['subject']).'</title></head><body style="margin:0;background:#f3f1eb;color:#162e2c;font-family:Arial,sans-serif;line-height:1.6"><table role="presentation" style="width:100%;border-collapse:collapse"><tr><td style="padding:16px 8px"><table role="presentation" style="width:100%;max-width:640px;margin:auto;background:#fff;border-top:6px solid #234d45;border-collapse:collapse;table-layout:fixed">';
    $text=[];
    foreach ($sections as $section) {
        $html.='<tr><td style="padding:16px 24px;border-bottom:1px solid #e5e5df;overflow-wrap:anywhere;word-break:break-word">';
        if (!empty($section['heading'])) { $html.='<h2 style="font-size:22px;line-height:1.3;margin:0 0 12px">'.esc_html($section['heading']).'</h2>'; $text[]=$section['heading']; }
        if (!empty($section['image']) && peracrm_campaign_public_url($section['image'])!=='') { $html.='<img src="'.esc_url($section['image']).'" alt="'.esc_attr($section['alt']).'" style="display:block;width:100%;max-width:592px;height:auto;border:0">'; $text[]='Image: '.$section['image'].' · '.$section['alt']; }
        if (!empty($section['text'])) { $html.='<p style="margin:0;white-space:pre-line">'.esc_html($section['text']).'</p>'; $text[]=$section['text']; }
        if (!empty($section['url']) && peracrm_campaign_public_url($section['url'])!=='') {
            // Captured delivery links remain functional; review-only rendering stays inert.
            if ($deliverable) {
                $html.='<a href="'.esc_attr($section['url']).'" style="display:inline-block;padding:10px 18px;background:#234d45;color:#fff;border-radius:4px">'.esc_html($section['heading'] ?: 'View content').'</a>';
            } else {
                $html.='<span style="display:inline-block;padding:10px 18px;background:#234d45;color:#fff;border-radius:4px">View content · navigation disabled</span>';
            }
            $text[]=$section['url'];
        }
        if (isset($section['slot'])) {
            $marker='{{'.$section['slot'].'}}';
            $html.='<a href="'.$marker.'">'.esc_html($section['heading']).'</a>';
            $text[]=$marker;
        }
        $html.='</td></tr>';$text[]='';
    }
    return ['html'=>$html.'</table></td></tr></table></body></html>','text'=>implode("\n",$text),'destinations'=>$destinations,'template_version'=>$deliverable ? 2 : $model['template_version']];
}


/** Immutable contract: URL values are absent from shared captures, never arbitrary HTML. */
function peracrm_campaign_deliverable_slots()
{
    return [
        'recipient_preferences_url'=>['type'=>'recipient_url', 'purpose'=>'preferences', 'scheme'=>'https', 'html_context'=>'quoted_href', 'text_context'=>'standalone_url', 'binding'=>'recipient_attempt', 'label'=>'Preferences'],
        'recipient_unsubscribe_url'=>['type'=>'recipient_url', 'purpose'=>'unsubscribe', 'scheme'=>'https', 'html_context'=>'quoted_href', 'text_context'=>'standalone_url', 'binding'=>'recipient_attempt', 'label'=>'Unsubscribe'],
    ];
}

/** Scan string values, never JSON punctuation from nested model objects. */
function peracrm_campaign_deliverable_has_slot($value)
{
    if (is_array($value)) {
        foreach ($value as $item) { if (peracrm_campaign_deliverable_has_slot($item)) { return true; } }
        return false;
    }
    return is_string($value) && (str_contains($value, '{{') || str_contains($value, '}}'));
}

/** Reject author-controlled interpolation and fail closed rather than omit bad links. */
function peracrm_campaign_deliverable_model_validate($model)
{
    $json=wp_json_encode($model);
    if (!is_string($json) || peracrm_campaign_deliverable_has_slot($model)
        || !empty($model['notices']) || empty($model['subject']) || empty($model['sender']) || empty($model['footer'])) {
        throw new InvalidArgumentException('Invalid deliverable model.');
    }
    foreach ($model['blocks'] as $block) {
        if (!in_array($block['type'] ?? '', ['text','cta','article','property'], true)) { throw new InvalidArgumentException('Unsupported deliverable block.'); }
        if (isset($block['notice'])) { throw new InvalidArgumentException('Incomplete deliverable block.'); }
        foreach (['url','image'] as $key) {
            if (!empty($block[$key]) && peracrm_campaign_public_url($block[$key]) === '') { throw new InvalidArgumentException('Unsafe deliverable URL.'); }
        }
        if (in_array($block['type'], ['article','property','cta'], true) && empty($block['url'])) { throw new InvalidArgumentException('Missing deliverable destination.'); }
    }
}

/** Exact slot schema and placement: no global replacement, tokens or recipient input here. */
function peracrm_campaign_deliverable_validate($output, $slots)
{
    if ($slots !== peracrm_campaign_deliverable_slots() || ($output['template_version'] ?? null) !== 2) { throw new InvalidArgumentException('Unsupported deliverable contract.'); }
    foreach (['html','text'] as $part) {
        $bytes=$output[$part] ?? null;
        if (!is_string($bytes) || $bytes === '' || strlen($bytes)>1048576 || preg_match('//u', $bytes)!==1 || str_contains($bytes, "\0")) { throw new InvalidArgumentException('Invalid deliverable output.'); }
        foreach ($slots as $name=>$slot) {
            $marker='{{'.$name.'}}';
            $context=$part==='html' ? '<a href="'.$marker.'">'.esc_html($slot['label']).'</a>' : $slot['label']."\n".$marker."\n";
            if (substr_count($bytes, $marker)!==1 || substr_count($bytes, $context)!==1) { throw new InvalidArgumentException('Invalid recipient slot context.'); }
            $bytes=str_replace($marker, '', $bytes);
        }
        if (str_contains($bytes, '{{') || str_contains($bytes, '}}')) { throw new InvalidArgumentException('Unknown recipient slot.'); }
    }
}

/** Display copy only. Captured HTML, text, destinations and hashes are never modified. */
function peracrm_campaign_staff_preview_html($html)
{
    // Renderer emits only these anchors. Historical v1 contains none. Remove all
    // anchor tags in the display projection, retaining their styles and escaped labels.
    return str_replace('</a>', '</span>', preg_replace('~<a href="[^"]*"([^>]*)>~', '<span$1>', $html));
}
