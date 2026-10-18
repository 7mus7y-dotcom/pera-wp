<?php
if (!defined('ABSPATH')) { exit; }

/** Public destinations only. No credentials, local/reserved hosts or private portfolio routes. No network request. */
function peracrm_campaign_public_url($url)
{
    if (!is_string($url) || strlen($url)>2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !filter_var($url,FILTER_VALIDATE_URL)) { return ''; }
    $p=wp_parse_url($url);
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''),['http','https'],true) || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && !in_array($p['port'],[80,443],true))) { return ''; }
    $host=strtolower(trim($p['host'] ?? '', '[]'));
    if ($host==='' || !str_contains($host,'.') || preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid)$/D',$host)) { return ''; }
    if (filter_var($host,FILTER_VALIDATE_IP) && !filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) { return ''; }
    // Public campaign URLs never carry private portfolio/preference credentials.
    $decoded=strtolower(rawurldecode($url));
    if (preg_match('~(?:portfolio|token|unsubscribe|preferences|wp-admin|wp-login)~',$decoded)) { return ''; }
    return esc_url_raw($url,['http','https']);
}

function peracrm_campaign_content_text($text,$length=600)
{
    $text=trim(wp_strip_all_tags(strip_shortcodes((string)$text)));
    return function_exists('mb_substr') ? mb_substr($text,0,$length) : wp_check_invalid_utf8(substr($text,0,$length),true);
}

/** The ML plugin explicitly defines English canonical source; no locale/name inference. */
function peracrm_campaign_content_fields($post,$language)
{
    $fields=['post_title'=>(string)$post->post_title];
    $excerpt_field=$post->post_excerpt!=='' ? 'post_excerpt' : 'post_content';
    $fields[$excerpt_field]=(string)$post->$excerpt_field;
    if (!class_exists('Pera_ML_Plugin') || !class_exists('Pera_ML_Storage')) { return ['notice'=>'Explicit content language unavailable: Pera Multilingual is not active.']; }
    $available=Pera_ML_Plugin::instance()->registry()->publicly_available();
    if (!isset($available[$language])) { return ['notice'=>'Requested language is missing or not publicly available.']; }
    if ($language!=='en') {
        foreach ($fields as $key=>$source) {
            if ($source==='') { continue; }
            $row=(new Pera_ML_Storage())->get('post',(int)$post->ID,$key,$language,$source);
            if (!$row || ($row['status'] ?? '')!=='current' || !empty($row['is_stale']) || trim($row['translated_text'] ?? '')==='') { return ['notice'=>'Current translation unavailable for requested language: '.$language.'.']; }
            $fields[$key]=$row['translated_text'];
        }
    }
    return ['title'=>peracrm_campaign_content_text($fields['post_title'],200),'excerpt'=>peracrm_campaign_content_text($fields[$excerpt_field]),'language'=>$language];
}

/** Canonical raw ACF v2 repeater storage. Never call theme helpers or legacy price fields. */
function peracrm_campaign_property_units($id)
{
    $count=get_post_meta($id,'v2_units',true);
    if (!is_numeric($count) || (int)$count<1 || (int)$count>100) { return ['notice'=>'Property unit, size and USD price data unavailable (bounded canonical v2 units required).']; }
    $units=[];
    for ($i=0;$i<(int)$count;$i++) {
        $row=[];
        foreach (['v2_beds','v2_gross_size_min','v2_gross_size_max','v2_price_usd_min','v2_price_usd_max'] as $field) {
            $raw=get_post_meta($id,'v2_units_'.$i.'_'.$field,true);
            $row[$field]=is_numeric($raw) && is_finite((float)$raw) && (float)$raw>=0 && (float)$raw<=1000000000000 ? (float)$raw : null;
        }
        $units[]=$row;
    }
    $parts=[];
    foreach ($units as $row) {
        $range=static function ($min,$max,$prefix='',$suffix='') {
            if ($min===null || $min<=0) { return 'unavailable'; }
            return $prefix.number_format($min, $min==floor($min) ? 0 : 2,'.',',').($max!==null && $max>$min ? '–'.number_format($max,$max==floor($max) ? 0 : 2,'.',',') : '').$suffix;
        };
        $parts[]=($row['v2_beds']===null ? 'Beds unavailable' : $row['v2_beds'].' beds').' · Size '.$range($row['v2_gross_size_min'],$row['v2_gross_size_max'],'',' m²').' · Price '.$range($row['v2_price_usd_min'],$row['v2_price_usd_max'],'USD ');
    }
    return ['units'=>$units,'summary'=>implode("\n",$parts)];
}

/** @internal Called inside authoritative target context, after permission check. */
function peracrm_campaign_content_resolve_target($type,$id,$language)
{
    $post_type=$type==='article' ? 'post' : 'property';
    if (!post_type_exists($post_type)) { return ['type'=>$type,'notice'=>'Property content unavailable: its post type is not registered by an active plugin.']; }
    $post=get_post($id);
    if (!$post || $post->post_type!==$post_type || $post->post_status!=='publish' || $post->post_password!=='' || !is_post_publicly_viewable($post)) { return ['type'=>$type,'notice'=>'Selected content is unavailable or no longer public.']; }
    $fields=peracrm_campaign_content_fields($post,$language);
    if (isset($fields['notice'])) { return ['type'=>$type]+$fields; }
    $url=get_permalink($post);
    if (function_exists('pera_ml_url')) { $url=pera_ml_url($url,$language); }
    $url=peracrm_campaign_public_url($url);
    if ($url==='') { return ['type'=>$type,'notice'=>'Canonical public destination unavailable.']; }
    $image_id=get_post_thumbnail_id($id);
    $image=$image_id ? peracrm_campaign_public_url(wp_get_attachment_image_url($image_id,'large')) : '';
    $alt=$image_id ? (string)get_post_meta($image_id,'_wp_attachment_image_alt',true) : '';
    if ($language!=='en' && $alt!=='') {
        $translated=(new Pera_ML_Storage())->get('post',$image_id,'meta:_wp_attachment_image_alt',$language,$alt);
        $alt=$translated && ($translated['status'] ?? '')==='current' && empty($translated['is_stale']) ? ($translated['translated_text'] ?? '') : '';
    }
    $model=['type'=>$type,'id'=>(int)$id,'url'=>$url,'image'=>$image,'alt'=>peracrm_campaign_content_text($alt,200)]+$fields;
    $model['image_notice']=$image!=='' && $model['alt']==='' ? 'Image description unavailable for requested language.' : '';
    if ($type==='property') { $model['property']=peracrm_campaign_property_units($id); }
    return $model;
}

function peracrm_campaign_content_resolve($type,$id,$language)
{
    peracrm_campaign_drafts_require(true);
    if (!in_array($type,['article','property'],true)) { throw new InvalidArgumentException('Unsupported content kind.'); }
    return peracrm_with_target_blog(static function () use ($type,$id,$language) { return peracrm_campaign_content_resolve_target($type,(int)$id,$language); });
}

/** 21 candidates, 20 returned. Stable ID keyset; bounded metadata priming. No private titles in errors. */
function peracrm_campaign_content_search($type,$term,$after,$language)
{
    peracrm_campaign_drafts_require(true);
    if (!in_array($type,['article','property'],true) || !is_string($term) || strlen($term)>120 || !is_int($after) || $after<0) { throw new InvalidArgumentException('Invalid content search.'); }
    return peracrm_with_target_blog(static function () use ($type,$term,$after,$language) {
        global $wpdb;
        $post_type=$type==='article' ? 'post' : 'property';
        $object=get_post_type_object($post_type);
        if (!$object || !is_post_type_viewable($object)) { return ['items'=>[],'next'=>null,'notice'=>'Property content unavailable: the post type is not registered as public by an active plugin.']; }
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish' AND post_password='' AND ID>%d AND post_title LIKE %s ORDER BY ID ASC LIMIT 21",$post_type,$after,'%'.$wpdb->esc_like($term).'%'));
        peracrm_campaigns_db_require(true);
        $has_more=count($rows)>20;$rows=array_slice($rows,0,20);
        if ($rows) { update_meta_cache('post',array_map(static function ($p) { return (int)$p->ID; },$rows)); }
        $items=[];
        foreach ($rows as $row) {
            $p=new WP_Post($row);
            if (!is_post_publicly_viewable($p)) { continue; }
            // Labels are canonical source labels, explicitly identified. Preview always resolves afresh.
            $fields=peracrm_campaign_content_fields($p,$language);
            $items[]=['id'=>(int)$p->ID,'title'=>peracrm_campaign_content_text($p->post_title,200),'availability'=>$fields['notice'] ?? ('Available: '.$language),'type'=>$type];
        }
        return ['items'=>$items,'next'=>$has_more ? (int)end($rows)->ID : null,'notice'=>'Search labels use canonical source titles. Translation availability applies to the requested language.'];
    });
}
