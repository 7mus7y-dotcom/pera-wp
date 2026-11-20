<?php
if (!defined('ABSPATH')) { exit; }

function peracrm_delivery_review_url(array $args = []): string
{
    return add_query_arg(['subscriber_view'=>'delivery-review'] + $args, peracrm_subscribers_url());
}

/** Current suppression is the cleanup flag. No duplicated or mutable delivery permission. */
function peracrm_delivery_review_list(string $kind = 'hard_bounce', string $search = '', int $page = 1): array
{
    peracrm_subscribers_require_staff();
    if (!in_array($kind,['hard_bounce','complaint','unsubscribe','administrative','temporary'],true)) {
        throw new InvalidArgumentException('Unknown review category.');
    }
    return peracrm_with_target_blog(static function () use ($kind,$search,$page) {
        global $wpdb;
        $x=peracrm_table('peracrm_email_suppressions'); $s=peracrm_table('peracrm_subscribers');
        $events=peracrm_table('peracrm_email_suppression_events'); $facts=peracrm_table('peracrm_campaign_ses_events');
        $offset=(max(1,min(100000,$page))-1)*25;
        $search=$wpdb->prepare(' LIKE %s','%'.$wpdb->esc_like(substr($search,0,254)).'%');
        if ($kind==='temporary') {
            // Normalized authenticated facts only. Temporary/undetermined feedback never grants deletion authority.
            $recipient='JSON_UNQUOTE(JSON_EXTRACT(f.fact_json, "$.recipient"))';
            $type='JSON_UNQUOTE(JSON_EXTRACT(f.fact_json, "$.type"))';
            $bounce='JSON_UNQUOTE(JSON_EXTRACT(f.fact_json, "$.bounce_type"))';
            $where="({$type}='DeliveryDelay' OR ({$type}='Bounce' AND {$bounce} IN ('Transient','Undetermined'))) AND {$recipient}{$search}";
            $from=" FROM {$facts} f LEFT JOIN {$s} s ON s.normalized_email={$recipient}
                LEFT JOIN {$x} x ON x.normalized_email={$recipient} WHERE {$where}";
            $total=(int)$wpdb->get_var('SELECT COUNT(*)'.$from); peracrm_campaigns_db_require(true);
            $rows=$wpdb->get_results($wpdb->prepare("SELECT {$recipient} AS normalized_email,
                s.id AS subscriber_id,s.version AS subscriber_version,s.client_id,s.subscription_state,
                x.hard_bounce,x.complaint,x.unsubscribe,x.administrative,
                {$type} AS feedback_type,{$bounce} AS bounce_type,
                JSON_UNQUOTE(JSON_EXTRACT(f.fact_json, \"$.subtype\")) AS feedback_subtype,
                JSON_UNQUOTE(JSON_EXTRACT(f.fact_json, \"$.occurred_at\")) AS occurred_at,
                'ses_notification' AS source,f.recorded_at".$from.' ORDER BY f.recorded_at DESC,f.event_key DESC LIMIT 25 OFFSET %d',$offset),ARRAY_A);
        } else {
            $from=" FROM {$x} x LEFT JOIN {$s} s ON s.normalized_email=x.normalized_email
                LEFT JOIN {$events} e ON e.id=(SELECT z.id FROM {$events} z
                    WHERE z.normalized_email=x.normalized_email AND z.reason='{$kind}' ORDER BY z.id DESC LIMIT 1)
                WHERE x.{$kind}=1 AND x.normalized_email{$search}";
            $total=(int)$wpdb->get_var('SELECT COUNT(*)'.$from); peracrm_campaigns_db_require(true);
            $rows=$wpdb->get_results($wpdb->prepare('SELECT x.*,s.id AS subscriber_id,s.version AS subscriber_version,
                s.client_id,s.subscription_state,e.source,e.occurred_at,e.recorded_at'.$from.' ORDER BY x.updated_at DESC,x.normalized_email LIMIT 25 OFFSET %d',$offset),ARRAY_A);
        }
        peracrm_campaigns_db_require(true);
        foreach ($rows as &$row) {
            // Never expose a client ID or URL the viewer is not authorized to open.
            $row['linked_client_present']=!empty($row['client_id']);
            if ($row['linked_client_present'] && !peracrm_user_can_access_client(peracrm_get_actor_user_id(),(int)$row['client_id'])) { $row['client_id']=null; }
            $row['cleanup_candidate']=$kind!=='temporary' && !empty($row['subscriber_id']);
        }
        unset($row);
        return ['rows'=>$rows,'total'=>$total,'kind'=>$kind];
    });
}

/** Explicit per-profile review. Mailbox lock + ID/version guard reject stale or recreated profiles. */
function peracrm_delivery_review_remove(string $email,int $id,int $version,bool $confirmed)
{
    if (!$confirmed || $id<1 || $version<1) { return new WP_Error('cleanup_confirmation','Confirm the exact profile before removal.'); }
    return peracrm_campaigns_staff_mutate($email,static function ($email,$subscriber,$suppression,$actor) use ($id,$version) {
        global $wpdb;
        if (!peracrm_campaigns_has_suppression($suppression)) { throw new RuntimeException('Only suppressed profiles may be removed here.'); }
        if (!$subscriber) { return true; } // Double submission cannot recreate or clear anything.
        if ((int)$subscriber['id']!==$id || (int)$subscriber['version']!==$version) { throw new RuntimeException('Profile changed. Review it again.'); }
        peracrm_campaigns_repository_event($email,$subscriber,'deleted',$subscriber['subscription_state'],
            ['source'=>'staff_delivery_cleanup'],$actor,['cleanup_reasons'=>array_values(array_filter(
                ['hard_bounce','complaint','unsubscribe','administrative'],static fn($key)=>!empty($suppression[$key])))]);
        peracrm_campaigns_repository_replace_identity($id,$email,false);
        $deleted=$wpdb->delete(peracrm_table('peracrm_subscribers'),['id'=>$id,'version'=>$version]);
        peracrm_campaigns_db_require($deleted);
        if ($deleted!==1) { throw new RuntimeException('Profile changed.'); }
        return true;
    });
}

/** Heuristics are review hints, never mailbox verification, consent, deletion or send authority. */
function peracrm_delivery_review_hints(array $row): array
{
    $hints=[]; $email=$row['mailbox']??'';
    try { peracrm_campaigns_normalize_email($email); } catch (Throwable $e) { $hints[]='Invalid email format'; }
    $parts=explode('@',strtolower($email)); $local=$parts[0]??''; $domain=$parts[1]??'';
    if (in_array($local,['email','test','none','noemail','unknown','example','noreply','no-reply'],true)
        || preg_match('/\A(?:none|noemail|test|example|unknown)\./',$domain)
        || preg_match('/\.(?:test|invalid|localhost)\z/',$domain)) { $hints[]='Possible placeholder: review manually'; }
    if (peracrm_campaigns_has_suppression($row)) { $hints[]='Suppressed: must not send'; }
    if (empty($row['subscriber_id'])) { $hints[]='No current subscriber profile'; }
    elseif (($row['subscription_state']??'')!=='subscribed' || empty($row['confirmed_at'])
        || empty($row['recorded_at']) || !empty($row['withdrawn_at']) || !peracrm_campaigns_has_required_evidence($row)) { $hints[]='Current consent needs review'; }
    return $hints;
}

/** SELECT-only review of held/retry rows in a paused job. Does not touch the frozen audience. */
function peracrm_delivery_review_unsent(int $jobId,string $search = '',int $page = 1,bool $zohoOnly = true,int $limit = 25): array
{
    peracrm_subscribers_require_staff();
    if (!peracrm_campaigns_staff_can('peracrm_view_campaign_reports')) { throw new RuntimeException('Campaign report access required.'); }
    if ($limit<1 || $limit>10001) { throw new InvalidArgumentException('Review limit exceeded.'); }
    return peracrm_with_target_blog(static function () use ($jobId,$search,$page,$zohoOnly,$limit) {
        global $wpdb;
        $jobs=peracrm_table('peracrm_campaign_queue_jobs'); $d=peracrm_table('peracrm_campaign_deliveries');
        $s=peracrm_table('peracrm_subscribers'); $x=peracrm_table('peracrm_email_suppressions');
        $job=$wpdb->get_row($wpdb->prepare("SELECT id,campaign_id,state FROM {$jobs} WHERE id=%d",$jobId),ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$job || $job['state']!=='paused') { throw new RuntimeException('Choose a paused job for unsent-list review.'); }
        $where=$wpdb->prepare("d.job_id=%d AND d.state IN ('held','retry') AND d.mailbox LIKE %s",$jobId,'%'.$wpdb->esc_like(substr($search,0,254)).'%');
        if ($zohoOnly) { $where.=" AND s.source='owner_attested_zoho_migration'"; }
        // Bind to the original subscriber ID as well as mailbox; a replacement profile is not the frozen recipient.
        $from=" FROM {$d} d LEFT JOIN {$s} s ON s.id=d.subscriber_id AND s.normalized_email=d.mailbox
            LEFT JOIN {$x} x ON x.normalized_email=d.mailbox WHERE {$where}";
        $total=(int)$wpdb->get_var('SELECT COUNT(*)'.$from); peracrm_campaigns_db_require(true);
        $rows=$wpdb->get_results($wpdb->prepare('SELECT d.id AS delivery_id,d.mailbox,d.state AS delivery_state,
            s.id AS subscriber_id,s.client_id,s.subscription_state,s.confirmed_at,s.recorded_at,s.withdrawn_at,
            s.source,s.source_ref,s.evidence_ref,s.policy_version,s.preferred_language,
            x.hard_bounce,x.complaint,x.unsubscribe,x.administrative'.$from.' ORDER BY d.id LIMIT %d OFFSET %d',
            $limit,(max(1,min(100000,$page))-1)*$limit),ARRAY_A);
        peracrm_campaigns_db_require(true);
        foreach ($rows as &$row) {
            $row['hints']=peracrm_delivery_review_hints($row);
            $row['linked_client_present']=!empty($row['client_id']);
            if ($row['linked_client_present'] && !peracrm_user_can_access_client(peracrm_get_actor_user_id(),(int)$row['client_id'])) { $row['client_id']=null; }
        }
        unset($row);
        return ['job'=>$job,'total'=>$total,'rows'=>$rows];
    });
}

/** Bounded read-only CSV preparation. Build before headers so failures cannot produce a misleading partial file. */
function peracrm_delivery_review_export(int $jobId,string $search = '',bool $zohoOnly = true): array
{
    // One bounded SELECT for CSV rows avoids offset gaps if profiles change during export.
    $result=peracrm_delivery_review_unsent($jobId,$search,1,$zohoOnly,10001);
    if ($result['total']>10000 || count($result['rows'])>10000) { throw new RuntimeException('Narrow the search to at most 10000 unsent recipients.'); }
    $output=[['email','delivery_state','source','source_ref','language','consent_recorded_utc','consent_confirmed_utc','review_hints']];
    foreach ($result['rows'] as $row) {
        $output[]=array_map('peracrm_subscribers_csv_cell',[$row['mailbox'],$row['delivery_state'],$row['source']??'',
            $row['source_ref']??'',$row['preferred_language']??'',$row['recorded_at']??'',$row['confirmed_at']??'',
            implode('; ',$row['hints'])?:'Mailbox deliverability unverified']);
    }
    return $output;
}
