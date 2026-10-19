<?php
if (!defined('ABSPATH')) { exit; }

/** Operational UI discovery. All queries use the authoritative target and existing staff gate. */
function peracrm_campaign_queue_ui_read(callable $callback)
{
    peracrm_campaign_drafts_require(true);
    return peracrm_with_target_blog(static function () use ($callback) {
        global $wpdb;
        $previous = $wpdb->suppress_errors(true);
        try { return $callback(); } finally { $wpdb->suppress_errors($previous); }
    });
}
function peracrm_campaign_queue_url($campaign = 0, $job = 0)
{
    return peracrm_campaign_drafts_url($campaign ? $campaign.'/queue/'.($job ? $job.'/' : '') : 'queue/');
}
function peracrm_campaign_queue_ui_jobs($campaign = 0, $before = 0)
{
    return peracrm_campaign_queue_ui_read(static function () use ($campaign, $before) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT j.id,j.campaign_id,j.frozen_revision_id,j.state,j.complete,j.prepared_count,j.expected_count,j.scheduled_at,c.internal_name,r.draft_revision FROM '.peracrm_table('peracrm_campaign_queue_jobs').' j LEFT JOIN '.peracrm_table('peracrm_campaign_drafts').' c ON c.id=j.campaign_id LEFT JOIN '.peracrm_table('peracrm_campaign_frozen_revisions').' r ON r.id=j.frozen_revision_id WHERE (%d=0 OR j.campaign_id=%d) AND (%d=0 OR j.id<%d) ORDER BY j.id DESC LIMIT 26', $campaign,$campaign,$before,$before), ARRAY_A);
        peracrm_campaigns_db_require(true); return $rows;
    });
}
function peracrm_campaign_queue_ui_revisions($campaign, $before = 0)
{
    return peracrm_campaign_queue_ui_read(static function () use ($campaign,$before) {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare('SELECT r.id,r.draft_revision,r.published_at,j.id AS job_id FROM '.peracrm_table('peracrm_campaign_frozen_revisions').' r LEFT JOIN '.peracrm_table('peracrm_campaign_queue_jobs').' j ON j.frozen_revision_id=r.id WHERE r.campaign_id=%d AND (%d=0 OR r.id<%d) ORDER BY r.id DESC LIMIT 21',$campaign,$before,$before),ARRAY_A);
        peracrm_campaigns_db_require(true); return $rows;
    });
}
/** Aggregate only: mailbox, delivery keys, provider correlation and lease owners never leave this read. */
function peracrm_campaign_queue_ui_summary($campaign,$id)
{
    return peracrm_campaign_queue_ui_read(static function () use ($campaign,$id) {
        global $wpdb;
        $job=peracrm_campaign_queue_get($campaign,$id);
        if (!$job) { throw new InvalidArgumentException('Job unavailable for this campaign.'); }
        $revision=$wpdb->get_row($wpdb->prepare('SELECT r.draft_revision,a.considered FROM '.peracrm_table('peracrm_campaign_frozen_revisions').' r INNER JOIN '.peracrm_table('peracrm_campaign_freeze_attempts').' a ON a.id=r.attempt_id WHERE r.id=%d AND r.campaign_id=%d',$job['frozen_revision_id'],$campaign),ARRAY_A);
        peracrm_campaigns_db_require(true);
        $counts=array_fill_keys(['held','claimed','inflight','retry','accepted','excluded','failed','unknown'],0);
        $rows=$wpdb->get_results($wpdb->prepare('SELECT state,COUNT(*) AS total FROM '.peracrm_table('peracrm_campaign_deliveries').' WHERE job_id=%d GROUP BY state',$id),ARRAY_A);
        peracrm_campaigns_db_require(true);
        foreach ($rows as $row) { if (isset($counts[$row['state']])) { $counts[$row['state']]=(int)$row['total']; } }
        $leases=$wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(lease_until>UTC_TIMESTAMP()),0) AS active,COALESCE(SUM(lease_until<=UTC_TIMESTAMP()),0) AS expired FROM ".peracrm_table('peracrm_campaign_deliveries')." WHERE job_id=%d AND state IN ('claimed','inflight')",$id),ARRAY_A);
        peracrm_campaigns_db_require(true);
        return ['job'=>$job,'revision'=>$revision,'counts'=>$counts,'leases'=>$leases];
    });
}
/** datetime-local is explicitly Europe/Istanbul, then passed with its offset to the existing UTC validator. */
function peracrm_campaign_queue_ui_schedule($input)
{
    if (!is_string($input) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D',$input)) { throw new InvalidArgumentException('Enter a future Istanbul date and time (Europe/Istanbul).'); }
    $local=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$input,new DateTimeZone('Europe/Istanbul'));
    $errors=DateTimeImmutable::getLastErrors();
    if (!$local || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $local->format('Y-m-d\TH:i')!==$input) { throw new InvalidArgumentException('Enter a valid Istanbul calendar date and time.'); }
    $explicit=$local->format('Y-m-d\TH:i:sP');
    peracrm_campaign_queue_schedule_utc($explicit);
    return $explicit;
}
function peracrm_campaign_queue_ui_time($utc)
{
    return $utc ? peracrm_campaign_freeze_time($utc).' · Europe/Istanbul (stored UTC)' : 'Not recorded';
}
function peracrm_campaign_queue_ui_button($operation,$label)
{
    echo '<button type="button" class="btn btn--ghost" data-queue-action="'.esc_attr($operation).'">'.esc_html($label).'</button>';
}
function peracrm_campaign_queue_ui_panel($campaign,$id,$after=0,$recipients=false)
{
    $summary=peracrm_campaign_queue_ui_summary($campaign,$id);
    $job=$summary['job']; $counts=$summary['counts'];
    if ($recipients && !peracrm_campaigns_staff_can('peracrm_manage_subscribers',true)) { throw new RuntimeException('Subscriber access required.'); }
    ob_start();
    try {
        echo '<section data-queue-job="'.(int)$id.'" data-queue-version="'.(int)$job['version'].'" data-queue-scheduled-value="'.esc_attr($job['scheduled_at'] ? (new DateTimeImmutable($job['scheduled_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('Y-m-d\TH:i') : '').'"><h2>Job #'.(int)$id.' · Frozen revision '.(int)($summary['revision']['draft_revision'] ?? 0).'</h2>';
        echo '<p data-queue-state>Lifecycle: '.esc_html($job['state']).' · Preparation: '.((int)$job['complete'] ? 'complete' : 'incomplete').' · Version '.(int)$job['version'].'</p>';
        echo '<p data-queue-totals>Considered: '.(int)($summary['revision']['considered'] ?? 0).' · Prepared: '.(int)$job['prepared_count'].' / '.(int)$job['expected_count'].'</p>';
        echo '<p>Schedule intent: '.esc_html(peracrm_campaign_queue_ui_time($job['scheduled_at'])).'</p><p>Preparation completion does not mean delivery completion. Provider acceptance does not verify inbox delivery.</p>';
        echo '<dl class="peracrm-queue-counts">';
        foreach ($counts as $state=>$total) { echo '<dt>'.esc_html(ucfirst($state).' deliveries').'</dt><dd data-queue-count="'.esc_attr($state).'">'.(int)$total.'</dd>'; }
        echo '<dt>Retry backlog</dt><dd>'.(int)$counts['retry'].'</dd><dt>Active leases</dt><dd>'.(int)$summary['leases']['active'].'</dd><dt>Expired leases</dt><dd>'.(int)$summary['leases']['expired'].'</dd></dl>';
        echo '<p>Failed job: '.($job['state']==='failed' ? 'yes — preparation recovery required' : 'no').'. Failed deliveries: '.(int)$counts['failed'].'. These are separate conditions.</p>';
        if ($counts['unknown']) { echo '<p role="alert">Unknown outcomes require reconciliation. Do not retry or assume acceptance. Provider lookup and verified-evidence reconciliation UI are deferred until a real provider is implemented.</p>'; }
        if ($counts['claimed'] || $counts['inflight'] || $counts['retry'] || $counts['unknown'] || !(int)$job['submission_complete']) { echo '<p>Unresolved work remains. This job is not evidence of completed delivery.</p>'; }
        if ($job['state']==='failed') { echo '<p role="alert">Preparation failed; the batch rolled back. Resume preparation, then continue bounded batches. Existing ledger and idempotency fences are retained.</p>'; }
        if ($job['state']==='cancelled') { echo '<p>Terminal cancellation. This revision cannot be prepared again; its ledger and cancellation fence are retained.</p>'; }
        echo '<div class="peracrm-campaign-actions">';
        if ($job['state']==='preparing' && !(int)$job['complete']) { peracrm_campaign_queue_ui_button('continue','Continue next 200 recipients'); }
        if (in_array($job['state'],['preparing','queued','scheduled'],true)) { peracrm_campaign_queue_ui_button('pause','Pause job'); }
        if (in_array($job['state'],['paused','failed'],true)) { peracrm_campaign_queue_ui_button('resume',$job['state']==='failed' ? 'Resume preparation' : 'Resume job'); }
        if (in_array($job['state'],['queued','scheduled'],true) && (int)$job['complete']) { peracrm_campaign_queue_ui_button('schedule','Record schedule intent'); }
        if (in_array($job['state'],['preparing','queued','scheduled','paused','failed'],true)) { peracrm_campaign_queue_ui_button('cancel','Cancel job permanently'); }
        peracrm_campaign_queue_ui_button('state','Reload current state'); echo '</div>';
        if (peracrm_campaigns_staff_can('peracrm_manage_subscribers',true)) {
            peracrm_campaign_queue_ui_button('recipients','View recipient details');
            if ($recipients) {
                $rows=peracrm_campaign_queue_page($campaign,$id,$after);
                echo '<h3>Recipient details (up to 200)</h3><p>Frozen mailbox and delivery state only. No provider payloads or identifiers.</p><ul data-queue-recipients>';
                foreach ($rows as $row) { echo '<li>'.esc_html($row['mailbox'].' · '.$row['state']).'</li>'; }
                echo '</ul>';
                if (count($rows)===200) { echo '<button type="button" class="btn btn--ghost" data-queue-action="recipients" data-after="'.(int)end($rows)['id'].'">Next recipient page</button>'; }
                if (!$rows) { echo '<p>No prepared recipients on this page.</p>'; }
            }
        } else { echo '<p>Subscriber permission is required for recipient details. Aggregate counts remain available.</p>'; }
        echo '</section>'; return ob_get_clean();
    } catch (Throwable $error) { ob_end_clean(); throw $error; }
}

/** Narrow adapter: no worker, scheduler invocation, reconciliation override or provider action. */
function peracrm_campaign_queue_ui_route()
{
    peracrm_subscribers_private_headers(); header('X-Content-Type-Options: nosniff');
    if (!peracrm_campaigns_staff_can('peracrm_manage_campaigns',true)) { wp_send_json_error(['message'=>'Queue access denied.'],403); }
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { wp_send_json_error(['message'=>'POST required.'],405); }
    if (!check_ajax_referer('peracrm_campaign_queue_ui','nonce',false)) { wp_send_json_error(['message'=>'Invalid request. Reload this page.'],403); }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>10000) { wp_send_json_error(['message'=>'Request too large.'],413); }
    try {
        $id=(int)($_POST['id'] ?? 0); $job=(int)($_POST['job'] ?? 0); $version=(int)($_POST['version'] ?? 0);
        $op=is_string($_POST['operation'] ?? null) ? $_POST['operation'] : '';
        $nonce=wp_unslash($_POST['service_nonce'] ?? ''); $result=null;
        if ($op==='recipients' && !peracrm_campaigns_staff_can('peracrm_manage_subscribers',true)) { wp_send_json_error(['message'=>'Subscriber access required for recipient details.'],403); }
        if ($op==='prepare') { $result=peracrm_campaign_queue_prepare($id,(int)($_POST['revision'] ?? 0),$nonce); }
        elseif ($op==='continue') { $result=peracrm_campaign_queue_materialize($id,$job,$version,$nonce,200); }
        elseif (in_array($op,['schedule','pause','resume','cancel'],true)) {
            if ($op==='cancel' && ($_POST['confirmed'] ?? '')!=='cancel') { throw new InvalidArgumentException('Confirm permanent cancellation before continuing.'); }
            $schedule=$op==='schedule' ? peracrm_campaign_queue_ui_schedule(wp_unslash($_POST['schedule'] ?? '')) : null;
            $result=peracrm_campaign_queue_transition($id,$job,$version,$nonce,$op,$schedule);
        } elseif (!in_array($op,['state','recipients'],true)) { throw new InvalidArgumentException('Unsupported queue action.'); }
        if (is_wp_error($result)) {
            $code=$result->get_error_code();
            $message=$code==='campaign_conflict' ? 'This job changed or this tab is stale. Reload current state before acting; schedule input is retained.' : ($code==='campaign_invalid' ? $result->get_error_message() : 'Queue change unavailable. Check campaign access and storage readiness. Reload state; resume preparation if failed.');
            wp_send_json_error(['message'=>$message],$code==='campaign_conflict' ? 409 : 422);
        }
        if ($op==='prepare') { wp_send_json_success(['url'=>peracrm_campaign_queue_url($id,(int)$result['id'])]); }
        wp_send_json_success(['html'=>peracrm_campaign_queue_ui_panel($id,$job,max(0,(int)($_POST['after'] ?? 0)),$op==='recipients'),'health_html'=>peracrm_campaign_queue_ui_health()]);
    } catch (InvalidArgumentException $error) { wp_send_json_error(['message'=>$error->getMessage()],422); }
    catch (Throwable $error) { wp_send_json_error(['message'=>'Queue state unavailable. Check campaign access and storage readiness, then reload. No automatic retry.'],503); }
}
add_action('wp_ajax_peracrm_campaign_queue','peracrm_campaign_queue_ui_route');
add_action('wp_ajax_nopriv_peracrm_campaign_queue',static function () { peracrm_subscribers_private_headers(); wp_send_json_error(['message'=>'Queue access denied.'],403); });

/** Refresh global health alongside job state; never present an old health snapshot as current. */
function peracrm_campaign_queue_ui_health()
{
    peracrm_campaign_drafts_require(true);
    ob_start();
    try {
?>
<?php if (peracrm_campaigns_staff_can('peracrm_view_campaign_reports',true)) : $health=(new PeraCRM_Campaign_Operations())->health(); ?>
<section data-queue-health><h2>Queue health (target blog)</h2><p>Read only. Last invocation is distinct from successful known processing and provider acceptance.</p><dl class="peracrm-queue-counts">
<?php foreach (['scheduler_state'=>'Scheduler','schema_ready'=>'Schema ready','module_enabled'=>'Module enabled','queue_ready'=>'Queue ready'] as $key=>$label) { echo '<dt>'.esc_html($label).'</dt><dd>'.esc_html(is_bool($health[$key]) ? ($health[$key] ? 'yes' : 'no') : $health[$key]).'</dd>'; }
foreach (($health['counts'] ?? []) as $key=>$value) { echo '<dt>'.esc_html(ucfirst(str_replace('_',' ',$key))).'</dt><dd>'.(int)$value.'</dd>'; }
echo '<dt>Last invocation</dt><dd>'.esc_html(peracrm_campaign_queue_ui_time($health['last_invocation']['invoked_at'] ?? null).' · '.($health['last_invocation']['result'] ?? 'never invoked')).'</dd>';
foreach (['last_successful_processing'=>'Last successful processing','last_provider_acceptance'=>'Last provider acceptance (not delivery)'] as $key=>$label) { echo '<dt>'.esc_html($label).'</dt><dd>'.esc_html(peracrm_campaign_queue_ui_time($health[$key])).'</dd>'; } ?></dl>
<?php foreach ($health['diagnostics'] as $diagnostic) { echo '<p>'.esc_html(str_replace('_',' ',$diagnostic)).'</p>'; } ?>
<p>Unknown outcomes require reconciliation. Invocation alone does not prove successful processing or delivery. No provider lookup or manual outcome overrides are available.</p></section>
<?php else : ?><section data-queue-health><p>Campaign report permission is required for global queue health.</p></section><?php endif; ?>
<?php
        return ob_get_clean();
    } catch (Throwable $error) { ob_end_clean(); throw $error; }
}
