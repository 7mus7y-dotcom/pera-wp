<?php
if (!defined('ABSPATH')) { exit; }

function peracrm_campaign_audience_rules($input)
{
    if (!is_array($input) || array_diff(array_keys($input),['version','language','topic']) || (isset($input['version']) && !in_array($input['version'],[1,'1'],true))) { throw new InvalidArgumentException('Only version 1 language/topic audience rules are supported.'); }
    $rules=['version'=>1];
    foreach (['language','topic'] as $key) {
        $value=$input[$key] ?? '';
        if (!is_string($value) || strlen($value)>($key==='language' ? 35 : 64) || ($value!=='' && !preg_match($key==='language' ? '/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/D' : '/^[a-z][a-z0-9_-]{0,63}$/D',$value))) { throw new InvalidArgumentException('Use an explicit language tag and topic key.'); }
        if ($value!=='') { $rules[$key]=$key==='language' ? strtolower($value) : $value; }
    }
    return $rules;
}

function peracrm_campaign_audience_pack($state)
{
    $payload=base64_encode(wp_json_encode($state));
    return $payload.'.'.hash_hmac('sha256',$payload,wp_salt('auth'));
}

function peracrm_campaign_audience_unpack($cursor)
{
    if (!is_string($cursor) || strlen($cursor)>4096 || substr_count($cursor,'.')!==1) { throw new InvalidArgumentException('Audience progress is invalid; restart preview.'); }
    [$payload,$signature]=explode('.',$cursor,2);
    if (!hash_equals(hash_hmac('sha256',$payload,wp_salt('auth')),$signature)) { throw new InvalidArgumentException('Audience progress is invalid; restart preview.'); }
    $state=json_decode(base64_decode($payload,true),true);
    if (!is_array($state)) { throw new InvalidArgumentException('Audience progress is invalid; restart preview.'); }
    return $state;
}

/** No state table, subscriber writes or recipient materialization: signed aggregate progress only. */
function peracrm_campaign_audience_preview($id,$revision,$rules,$cursor='')
{
    peracrm_campaign_drafts_require(true);
    $rules=peracrm_campaign_audience_rules($rules);
    $draft=peracrm_campaign_draft_get($id);
    if (!$draft || (int)$draft['revision']!==$revision || peracrm_campaign_audience_rules($draft['document']['audience'] ?? [])!==$rules) { throw new UnexpectedValueException('Save the current draft and rules, then restart audience preview.'); }
    return peracrm_with_target_blog(static function () use ($id,$revision,$rules,$cursor) {
        global $wpdb;
        $subscribers=peracrm_table('peracrm_subscribers');$suppression=peracrm_table('peracrm_email_suppressions');$party=peracrm_table('peracrm_party');
        $binding=['actor'=>peracrm_get_actor_user_id(),'blog'=>get_current_blog_id(),'id'=>$id,'revision'=>$revision,'rules_hash'=>hash('sha256',wp_json_encode($rules))];
        if ($cursor!=='') {
            $state=peracrm_campaign_audience_unpack($cursor);
            foreach ($binding as $key=>$value) { if (($state[$key] ?? null)!==$value) { throw new UnexpectedValueException('Draft, rules or actor changed; restart audience preview.'); } }
            if (($state['expires'] ?? 0)<time()) { throw new UnexpectedValueException('Audience preview expired; restart.'); }
        } else {
            $boundary=(int)$wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$subscribers}");peracrm_campaigns_db_require(true);
            $state=$binding+['boundary'=>$boundary,'last'=>0,'as_of'=>gmdate('Y-m-d H:i:s'),'expires'=>time()+1800,'considered'=>0,'counts'=>array_fill_keys(['eligible','suppression','consent','linked_client','language','topic','rules'],0)];
        }
        $rows=peracrm_campaign_audience_batch($state['last'],$state['boundary'],201);
        $more=count($rows)>200;
        $filters=$rules;unset($filters['version']);
        foreach (array_slice($rows,0,200) as $row) {
            $reason=peracrm_campaigns_eligibility_reason($row,['post_type'=>$row['linked_type'],'post_status'=>$row['linked_status'],'disposition'=>$row['linked_disposition']],$filters);
            $state['counts'][$reason]++;$state['considered']++;$state['last']=(int)$row['id'];
        }
        // Recheck after processing so a concurrent draft save cannot return apparently current counts.
        $current=$wpdb->get_var($wpdb->prepare('SELECT revision FROM '.peracrm_table('peracrm_campaign_drafts').' WHERE id=%d',$id));peracrm_campaigns_db_require(true);
        if ((int)$current!==$revision) { throw new UnexpectedValueException('Draft changed; restart audience preview.'); }
        return ['considered'=>$state['considered'],'eligible'=>$state['counts']['eligible'],'exclusions'=>array_diff_key($state['counts'],['eligible'=>true]),'as_of'=>$state['as_of'],'checked_at'=>gmdate('Y-m-d H:i:s'),'complete'=>!$more,'cursor'=>$more ? peracrm_campaign_audience_pack($state) : '', 'notice'=>'Advisory counts; recheck at freeze/send. Fixed candidate ID boundary; concurrent edits/deletions can affect results. This is not a transactional snapshot. Exclusions use suppression, consent, linked client, language, then topic precedence.'];
    });
}

/** @internal One shared batched read, no per-recipient client/profile queries. */
function peracrm_campaign_audience_batch($last, $boundary, $limit)
{
    global $wpdb;
    $subscribers=peracrm_table('peracrm_subscribers'); $suppression=peracrm_table('peracrm_email_suppressions'); $party=peracrm_table('peracrm_party');
    $rows=$wpdb->get_results($wpdb->prepare("SELECT s.*, x.normalized_email AS suppression_mailbox,x.unsubscribe,x.hard_bounce,x.complaint,x.administrative,p.post_type AS linked_type,p.post_status AS linked_status,t.disposition AS linked_disposition FROM {$subscribers} s LEFT JOIN {$suppression} x ON x.normalized_email=s.normalized_email LEFT JOIN {$wpdb->posts} p ON p.ID=s.client_id LEFT JOIN {$party} t ON t.party_id=s.client_id WHERE s.id>%d AND s.id<=%d ORDER BY s.id ASC LIMIT %d", $last, $boundary, $limit), ARRAY_A);
    peracrm_campaigns_db_require(true);
    return $rows;
}
