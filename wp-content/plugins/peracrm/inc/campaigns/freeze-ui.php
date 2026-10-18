<?php
if (!defined('ABSPATH')) { exit; }

/** UI discovery only: bounded metadata, always target-blog scoped and authorized. */
function peracrm_campaign_freeze_history($id, $before = 0)
{
    peracrm_campaign_drafts_require(true);
    return peracrm_with_target_blog(static function () use ($id, $before) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_freeze_attempts').' WHERE campaign_id=%d AND (%d=0 OR id<%d) ORDER BY id DESC LIMIT 20', $id, $before, $before), ARRAY_A);
        peracrm_campaigns_db_require(true);
        return $rows;
    });
}

function peracrm_campaign_freeze_time($utc)
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('Y-m-d H:i:s T');
}

function peracrm_campaign_freeze_output($content)
{
    $output = $content['output'];
    $meta = '<meta http-equiv="Content-Security-Policy" content="'.esc_attr(peracrm_campaign_preview_csp()).'"><meta name="referrer" content="no-referrer">';
    echo '<dl>';
    foreach (['language'=>'Language','subject'=>'Subject','preheader'=>'Preheader','sender'=>'Sender / reply-to','footer'=>'Approved footer'] as $key=>$label) {
        echo '<dt>'.esc_html($label).'</dt><dd>'.esc_html($content['model'][$key]).'</dd>';
    }
    echo '<dt>Audience rules</dt><dd>'.esc_html('Language: '.($content['rules']['language'] ?? 'Any').' · Topic: '.($content['rules']['topic'] ?? 'Any')).'</dd></dl>';
    echo '<p>Editorial configuration approval does not verify provider, DNS or domain readiness. Preference links remain controlled placeholders. Image URLs do not guarantee immutable image files.</p>';
    foreach ($content['warnings'] as $warning) { echo '<p>'.esc_html($warning).'</p>'; }
    echo '<details open><summary>Resolved HTML preview</summary><div class="peracrm-email-preview"><iframe title="Isolated saved campaign HTML" sandbox="" referrerpolicy="no-referrer" srcdoc="'.esc_attr(str_replace('<head>','<head>'.$meta,$output['html'])).'"></iframe></div></details>';
    echo '<details><summary>Resolved plain-text preview</summary><pre class="peracrm-campaign-history">'.esc_html($output['text']).'</pre></details>';
}

function peracrm_campaign_freeze_button($operation, $label)
{
    echo '<button type="button" class="btn btn--ghost" data-freeze-action="'.esc_attr($operation).'">'.esc_html($label).'</button>';
}

/** Server state survives reload. Never label a complete but unpublished attempt frozen. */
function peracrm_campaign_freeze_panel($id, $selected = 0, $before = 0, $after = 0)
{
    $draft = peracrm_campaign_draft_get($id);
    if (!$draft) { throw new InvalidArgumentException('Campaign unavailable.'); }
    $history = peracrm_campaign_freeze_history($id, $before);
    $latest = $before ? peracrm_campaign_freeze_history($id) : $history;
    $current_attempt = isset($latest[0]) && (int)$latest[0]['draft_revision']===(int)$draft['revision'] ? $latest[0] : null;
    $attempt = $selected ? peracrm_campaign_freeze_get($id, $selected) : ($history[0] ?? null);
    ob_start();
    try {
    echo '<h3 tabindex="-1" data-freeze-heading>Review and freeze</h3><p>Freeze revision prepares an immutable version; it does not send anything. Eligibility is evaluated during materialization and will be checked again before future delivery. Historical inclusion is not current sending permission.</p>';
    echo '<p>Saved revision '.(int)$draft['revision'].' · '.esc_html($draft['status']).'</p>';
    if ($draft['status'] === 'draft' && !$current_attempt) { peracrm_campaign_freeze_button('review','Review saved revision'); }
    if ($attempt) {
        $counts = json_decode($attempt['counts_json'], true);
        $owner = (int)$attempt['actor_user_id'] === peracrm_get_actor_user_id();
        echo '<section data-freeze-attempt="'.(int)$attempt['id'].'" data-freeze-version="'.(int)$attempt['version'].'"><h4>Revision '.(int)$attempt['draft_revision'].' · '.esc_html($attempt['state']).'</h4>';
        echo '<p data-freeze-counts>Considered: '.(int)$attempt['considered'].' · Included: '.(int)$counts['eligible'].' · Excluded: '.((int)$attempt['considered']-(int)$counts['eligible']).'</p>';
        echo '<p>'.esc_html((int)$attempt['complete'] ? 'Materialization complete' : 'Materialization incomplete').' · Candidate cursor '.(int)$attempt['last_id'].' of ID boundary '.(int)$attempt['boundary_id'].' · Actor '.(int)$attempt['actor_user_id'].' · Updated '.esc_html(peracrm_campaign_freeze_time($attempt['updated_at'])).'</p>';
        $reasons=['eligible'=>'Included','suppression'=>'Suppressed','consent'=>'No current consent','linked_client'=>'Linked CRM client ineligible','language'=>'Language mismatch or missing','topic'=>'Topic mismatch or missing','rules'=>'Invalid audience rules','duplicate'=>'Duplicate mailbox'];
        echo '<dl>'; foreach ($counts as $reason=>$total) { echo '<dt>'.esc_html($reasons[$reason] ?? $reason).'</dt><dd>'.(int)$total.'</dd>'; } echo '</dl>';
        if ($attempt['state'] === 'failed') { echo '<p role="alert">A batch failed and was rolled back. Reload current state, then resume this exact saved revision or cancel. Changed configuration or a later saved revision requires cancellation and a new revision.</p>'; }
        if ($attempt['state'] === 'cancelled') { echo '<p>Cancelled permanently. Save a new draft revision before reviewing and freezing again. The cancelled revision tombstone remains intact.</p>'; }
        if ($owner && $attempt['state'] === 'materializing') {
            if (!(int)$attempt['complete']) { peracrm_campaign_freeze_button('continue','Continue next 200 candidates'); }
            elseif ($counts['eligible'] > 0) { peracrm_campaign_freeze_button('publish','Publish frozen revision'); }
            else { echo '<p role="alert">No included recipients. Publication is unavailable. Cancel and save a newly corrected revision.</p>'; }
            peracrm_campaign_freeze_button('cancel','Cancel materialization');
        }
        if ($owner && $attempt['state'] === 'failed') { peracrm_campaign_freeze_button('resume','Resume materialization'); peracrm_campaign_freeze_button('cancel','Cancel materialization'); }
        if (!$owner && in_array($attempt['state'], ['materializing','failed'], true)) { echo '<p>Only the initiating actor can continue, resume, cancel or publish this attempt.</p>'; }
        if ($attempt['state'] === 'frozen') {
            $frozen = peracrm_campaign_frozen_get($id, (int)$attempt['draft_revision']);
            if (!$frozen) { throw new RuntimeException('Published revision unavailable.'); }
            echo '<h4>Stored immutable frozen revision '.(int)$frozen['draft_revision'].'</h4><p>Published by actor '.(int)$frozen['published_by'].' · '.esc_html(peracrm_campaign_freeze_time($frozen['published_at'])).' (Istanbul; stored UTC)</p>';
            $capture = $frozen['content'];
            echo '<p>Content evaluation: '.esc_html(peracrm_campaign_freeze_time($capture['payload']['evaluation_started_at'])).' to '.esc_html(peracrm_campaign_freeze_time($capture['payload']['evaluation_completed_at'])).'. Membership evaluated during '.esc_html(peracrm_campaign_freeze_time($attempt['created_at'])).' to '.esc_html(peracrm_campaign_freeze_time($attempt['updated_at'])).'.</p>';
            peracrm_campaign_freeze_output($capture['payload']['content']);
        }
        if (peracrm_campaigns_staff_can('peracrm_manage_subscribers',true)) {
            echo '<details><summary>Historical recipient membership (up to 200)</summary><p>Mailbox and evaluation time only. Inclusion is historical, not live sending permission.</p>';
            $members = peracrm_campaign_freeze_recipients($id,(int)$attempt['id'],$after);
            echo '<ul>'; foreach ($members as $member) { echo '<li>'.esc_html($member['mailbox'].' · '.peracrm_campaign_freeze_time($member['evaluated_at'])).'</li>'; } echo '</ul>';
            if (count($members)===200) { echo '<button type="button" class="btn btn--ghost" data-freeze-action="recipients" data-after="'.(int)end($members)['id'].'">Next recipient page</button>'; }
            echo '</details>';
        }
        echo '</section>';
    }
    if ($draft['status']==='frozen' || ($current_attempt && $current_attempt['state']==='cancelled')) { peracrm_campaign_freeze_button('new_revision','Start new editable revision'); }
    echo '<h4>Freeze revision history</h4><ul>';
    foreach ($history as $entry) { echo '<li><button type="button" class="btn btn--ghost" data-freeze-action="history" data-attempt="'.(int)$entry['id'].'">'.esc_html('Revision '.$entry['draft_revision'].' · '.$entry['state'].' · '.peracrm_campaign_freeze_time($entry['created_at'])).'</button></li>'; }
    echo '</ul>';
    if (count($history)===20) { echo '<button type="button" class="btn btn--ghost" data-freeze-action="older" data-before="'.(int)end($history)['id'].'">Older freeze attempts</button>'; }
    peracrm_campaign_freeze_button('state','Reload current state');
    return ob_get_clean();
    } catch (Throwable $error) { ob_end_clean(); throw $error; }
}

/** Authenticated POST adapter. Service nonce, actor, locks and version fences remain authoritative. */
function peracrm_campaign_freeze_route()
{
    peracrm_subscribers_private_headers();
    header('X-Content-Type-Options: nosniff');
    if (!peracrm_campaigns_staff_can('peracrm_manage_campaigns',true)) { wp_send_json_error(['message'=>'Campaign access denied.'],403); }
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { wp_send_json_error(['message'=>'POST required.'],405); }
    if (!check_ajax_referer('peracrm_campaign_freeze_ui','nonce',false)) { wp_send_json_error(['message'=>'Invalid request. Reload saved state.'],403); }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>200000) { wp_send_json_error(['message'=>'Request too large.'],413); }
    try {
        $id=(int)($_POST['id'] ?? 0); $revision=(int)($_POST['revision'] ?? 0);
        $attempt=(int)($_POST['attempt'] ?? 0); $version=(int)($_POST['version'] ?? 0);
        $operation=is_string($_POST['operation'] ?? null) ? $_POST['operation'] : '';
        $draft=peracrm_campaign_draft_get($id);
        if (!$draft) { throw new InvalidArgumentException('Campaign unavailable.'); }
        $nonce=wp_unslash($_POST['service_nonce'] ?? '');
        $input=isset($_POST['document']) && is_array($_POST['document']) ? wp_unslash($_POST['document']) : [];
        if (isset($input['blocks']) && is_array($input['blocks'])) { $input['blocks']=array_values($input['blocks']); }
        $result=null; $review=null;
        if (in_array($operation,['review','start'],true)) {
            $review=peracrm_campaign_snapshot_review($id,$revision,$input);
            if ($operation==='start') {
                $capture=peracrm_campaign_snapshot_capture($id,$revision,$input,wp_unslash($_POST['proof'] ?? ''),wp_unslash($_POST['capture_nonce'] ?? ''));
                if (is_wp_error($capture)) { $result=$capture; }
                else { $result=peracrm_campaign_freeze_start($id,$revision,(int)$capture['id'],$capture['content_hash'],$input,$nonce); }
            }
        } elseif (in_array($operation,['continue','resume','cancel','publish'],true)) {
            $function='peracrm_campaign_freeze_'.$operation;
            $result=$function($id,$attempt,$version,$nonce);
        } elseif ($operation==='new_revision') {
            if ($revision!==(int)$draft['revision'] || peracrm_campaign_draft_document($input)!==peracrm_campaign_draft_document($draft['document'])) { throw new UnexpectedValueException('Save or restore your changes before starting a revision.'); }
            $result=peracrm_campaign_draft_save($id,$revision,$input);
        } elseif (!in_array($operation,['state','history','older','recipients'],true)) { throw new InvalidArgumentException('Unsupported action.'); }
        if ($operation==='recipients' && !peracrm_campaigns_staff_can('peracrm_manage_subscribers',true)) { wp_send_json_error(['message'=>'Subscriber access required for recipient details.'],403); }
        if (is_wp_error($result)) { wp_send_json_error(['message'=>$result->get_error_message().' Reload current state before retrying.'], $result->get_error_code()==='campaign_conflict' ? 409 : 422); }
        if ($operation==='new_revision') { wp_send_json_success(['reload'=>true]); }
        if ($operation==='review') {
            $advisory=peracrm_campaign_audience_preview($id,$revision,$input['audience'] ?? []);
            ob_start(); echo '<h4>Review saved revision '.(int)$revision.'</h4>'; peracrm_campaign_freeze_output($review['content']);
            echo '<p>'.esc_html(($advisory['complete'] ? 'Complete' : 'Partial (first 200 candidates)').' advisory count · As of '.$advisory['as_of'].' UTC · Considered '.$advisory['considered'].' · Eligible '.$advisory['eligible'].' · Excluded '.array_sum($advisory['exclusions'])).'. These are advisory counts; freeze reevaluates eligibility.</p>'; peracrm_campaign_freeze_button('start','Freeze revision');
            wp_send_json_success(['review_html'=>ob_get_clean(),'proof'=>$review['review_proof'],'capture_nonce'=>wp_create_nonce('peracrm_campaign_snapshot_'.$id.'_'.$revision)]);
        }
        wp_send_json_success(['html'=>peracrm_campaign_freeze_panel($id,in_array($operation,['history','recipients'],true) ? $attempt : 0,(int)($_POST['before'] ?? 0),(int)($_POST['after'] ?? 0))]);
    } catch (UnexpectedValueException $error) { wp_send_json_error(['message'=>'Saved revision, sources or configuration changed, or this tab is stale. Reload current state and review again.'],409); }
    catch (InvalidArgumentException $error) { wp_send_json_error(['message'=>$error->getMessage()],422); }
    catch (Throwable $error) { wp_send_json_error(['message'=>'Campaign operation unavailable. No automatic retry. Reload current state; resume or cancel if available.'],503); }
}
add_action('wp_ajax_peracrm_campaign_freeze','peracrm_campaign_freeze_route');
add_action('wp_ajax_nopriv_peracrm_campaign_freeze',static function () { peracrm_subscribers_private_headers(); wp_send_json_error(['message'=>'Campaign access denied.'],403); });
