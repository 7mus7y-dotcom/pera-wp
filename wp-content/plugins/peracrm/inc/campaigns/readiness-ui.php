<?php
if (!defined('ABSPATH')) { exit; }

function peracrm_campaign_readiness_ui_require(): void
{
    peracrm_with_target_blog(static function () {
        $actor=peracrm_get_actor_user_id();
        if ($actor<1 || peracrm_is_impersonating_crm_user() || !user_can($actor,'manage_options')
            || !user_can($actor,'peracrm_manage_campaigns') || !user_can($actor,'peracrm_view_campaign_reports')
            || (is_multisite() && peracrm_get_target_blog_id()<1)) { throw new RuntimeException('readiness_authority'); }
    });
}
function peracrm_campaign_readiness_ui_url(): string
{
    return peracrm_with_target_blog(static fn()=>admin_url('admin.php?page=peracrm-campaign-readiness'));
}
/** Strict form adapter. No free text, URLs, dates, actor overrides or remote content. */
function peracrm_campaign_readiness_ui_save(PeraCRM_Campaign_Readiness $service,array $input): void
{
    peracrm_campaign_readiness_ui_require();
    $limits=['fingerprint'=>64,'prerequisite'=>32,'provenance'=>32,'digest'=>64,'nonce'=>32,'confirmed'=>1];
    if (array_diff(array_keys($input),array_keys($limits)) || count($input)!==count($limits)) {
        throw new InvalidArgumentException('readiness_form');
    }
    foreach ($limits as $key=>$limit) {
        if (!is_string($input[$key]??null) || strlen($input[$key])>$limit) { throw new InvalidArgumentException('readiness_form'); }
    }
    if ($input['confirmed']!=='1' || preg_match('/\A[a-f0-9]{64}\z/',$input['fingerprint'])!==1
        || preg_match('/\A[a-f0-9]{64}\z/',$input['digest'])!==1) { throw new InvalidArgumentException('readiness_form'); }
    global $wpdb;
    $quiet=$wpdb->suppress_errors(true);
    try { $service->record($input['fingerprint'],$input['prerequisite'],$input['provenance'],$input['digest'],$input['nonce']); }
    finally { $wpdb->suppress_errors($quiet); }
}
function peracrm_campaign_readiness_ui_label(string $code): string
{
    $labels=[
        'schema'=>'Campaign database schema','envelope_renderer'=>'Email format and renderer contract',
        'provider_identity'=>'Production provider identity','token_signing_keys'=>'Production token signing keys',
        'output_encryption_keys'=>'Production output encryption keys','identity_configuration'=>'Approved senders and footer',
        'suppression_sync'=>'Suppression checkpoint freshness and scope','budget_breaker_latch'=>'Capacity, circuit breaker and pending submission',
        'event_processing'=>'Local event processing health','dns_dkim_dmarc'=>'DNS, DKIM and DMARC alignment',
        'iam_account_quotas'=>'IAM permissions and account quota headroom','sns_dlq_monitoring'=>'SNS, dead letter queue and monitoring',
        'host_log_protections'=>'Host and log protections','retention'=>'Evidence retention policy',
        'old_mailbox_withdrawal'=>'Old mailbox withdrawal policy','restore_verification'=>'Restore verification',
        'repository_runtime'=>'Automated local runtime check','repository_contract'=>'Automated repository contract check',
        'local_observation'=>'Automated local storage observation','repository_mock'=>'Repository mock only',
        'operator_attestation'=>'Operator attestation','provider_observation'=>'Provider observation reported by operator',
        'host_observation'=>'Host observation reported by operator','none'=>'No evidence',
        'validated'=>'Local check passed','recorded'=>'Required evidence recorded; no live verification',
        'unverified'=>'Unverified — evidence missing or invalid','stale'=>'Expired — verify again',
        'insufficient_evidence'=>'Evidence type does not satisfy this prerequisite','unavailable'=>'Unavailable',
        'unconfigured'=>'Not configured','scope_unconfigured'=>'Deployment scope not configured','approval_missing'=>'Sender or footer approval missing',
        'scope_mismatch'=>'Scope does not match','attention_required'=>'Needs operator attention',
        'sync_scope'=>'Suppression scope does not match','sync_storage'=>'Suppression checkpoint unavailable or incomplete',
        'sync_stale_cursor'=>'Suppression checkpoint is stale or inconsistent','budget_scope'=>'Capacity scope does not match',
        'budget_readiness'=>'Capacity readiness denied','budget_quota'=>'Fresh account quota required','budget_clock'=>'Clock verification required',
        'budget_pending'=>'Unresolved submission blocks capacity','budget_breaker'=>'Circuit breaker blocks capacity',
        'budget_exhausted'=>'Capacity limit reached','budget_storage'=>'Capacity storage unavailable','budget_unavailable'=>'Production capacity service unavailable',
        'dispatch_disabled'=>'Dispatch is disabled unconditionally',
    ];
    return $labels[$code]??ucfirst(str_replace('_',' ',$code));
}
add_action('admin_menu',static function () {
    try { peracrm_campaign_readiness_ui_require(); } catch (Throwable $e) { return; }
    $hook=add_menu_page('Campaign readiness','Campaign readiness','manage_options','peracrm-campaign-readiness',
        'peracrm_campaign_readiness_ui_render','dashicons-clipboard');
    add_action('load-'.$hook,static function () {
        try { peracrm_campaign_readiness_ui_require(); }
        catch (Throwable $e) { wp_die('Administrator campaign access required.','',['response'=>403]); }
        peracrm_subscribers_private_headers();
        $method=$_SERVER['REQUEST_METHOD']??'GET';
        if (!in_array($method,['GET','HEAD','POST'],true)) { wp_die('Method not allowed.','',['response'=>405]); }
        if ($method==='POST') {
            try {
                peracrm_campaign_readiness_ui_save(peracrm_campaign_readiness_service(),wp_unslash($_POST));
                $GLOBALS['peracrm_readiness_feedback']=['saved'=>true];
            } catch (Throwable $e) {
                status_header(422);
                $GLOBALS['peracrm_readiness_feedback']=['saved'=>false];
            }
        }
    });
});
add_action('admin_enqueue_scripts',static function ($hook) {
    if ($hook!=='toplevel_page_peracrm-campaign-readiness') { return; }
    wp_enqueue_style('peracrm-readiness',PERACRM_URL.'/assets/frontend/crm.css',[],(string)filemtime(PERACRM_PATH.'/assets/frontend/crm.css'));
    wp_enqueue_script('peracrm-readiness',PERACRM_URL.'/assets/frontend/crm.js',[],(string)filemtime(PERACRM_PATH.'/assets/frontend/crm.js'),true);
});
function peracrm_campaign_readiness_ui_render(): void
{
    try { peracrm_campaign_readiness_ui_require(); }
    catch (Throwable $e) { wp_die('Administrator campaign access required.','',['response'=>403]); }
    echo '<div class="wrap peracrm-readiness" data-readiness-page><h1>Campaign readiness</h1>';
    echo '<p class="peracrm-readiness-banner"><strong>Dispatch disabled — evidence cannot enable sending.</strong> Endpoint, suppression sync activation, intake and public collection remain disabled.</p>';
    $feedback=$GLOBALS['peracrm_readiness_feedback']??null;
    if ($feedback) {
        echo '<p tabindex="-1" data-readiness-feedback role="'.($feedback['saved']?'status':'alert').'">'.esc_html($feedback['saved']
            ?'Evidence saved to the audit history. Dispatch remains disabled.'
            :'Evidence was not saved. Reload this page and verify the current configuration, permissions, evidence type, SHA-256 and confirmation. If storage conflicts or the 64-record limit persists, contact the administrator. Rejected input has been cleared.').'</p>';
    }
    global $wpdb;
    $quiet=$wpdb->suppress_errors(true);
    try {
        $service=peracrm_campaign_readiness_service();
        $scope=$service->administratorScope(); $report=$service->assess();
        $fingerprint=$report['configuration_fingerprint'];
        echo '<section aria-labelledby="readiness-scope"><h2 id="readiness-scope">Configuration scope</h2><p>These are diagnostic pins. No provider or host was contacted. Configuration changes invalidate all evidence for the previous binding.</p><dl>';
        foreach (['blog_id'=>'CRM site ID','deployment'=>'Deployment','provider'=>'Provider','account_id'=>'Provider account ID','region'=>'Region','configuration_set'=>'Configuration set','configuration_version'=>'Deployment configuration version'] as $key=>$label) {
            echo '<dt>'.esc_html($label).'</dt><dd>'.esc_html((string)($scope[$key]??'Not configured')).'</dd>';
        }
        foreach (['scope_fingerprint'=>'Scope fingerprint','configuration_fingerprint'=>'Configuration fingerprint (includes CRM revision)'] as $key=>$label) {
            echo '<dt>'.esc_html($label).'</dt><dd><code>'.esc_html($report[$key]??'Unavailable').'</code></dd>';
        }
        echo '</dl></section><p>Automated checks observe local code and storage. Operator attestations express policy decisions. Provider and host observations refer to separately retained artifacts classified by the operator; this page does not authenticate or fetch them. Evidence expires after 30 days.</p>';
        foreach ([false=>'Automated internal checks',true=>'External verification'] as $external=>$heading) {
            echo '<section><h2>'.esc_html($heading).'</h2><div class="peracrm-readiness-table" role="region" tabindex="0" aria-label="'.esc_attr($heading).'"><table><thead><tr><th scope="col">Check</th><th scope="col">Result</th><th scope="col">Source</th></tr></thead><tbody>';
            foreach ($report['checks'] as $name=>$check) {
                if (isset(PeraCRM_Campaign_Readiness::EXTERNAL[$name])!==(bool)$external) { continue; }
                echo '<tr data-readiness-check="'.esc_attr($name).'" data-state="'.esc_attr($check['state']).'" data-source="'.esc_attr($check['source']).'"><th scope="row">'.esc_html(peracrm_campaign_readiness_ui_label($name)).'</th><td>'.esc_html(peracrm_campaign_readiness_ui_label($check['state']));
                if (isset($check['actor'],$check['verified_at'])) { echo '<br>Actor #'.(int)$check['actor'].' · '.esc_html($check['verified_at']).' UTC'; }
                echo '</td><td>'.esc_html(peracrm_campaign_readiness_ui_label($check['source'])).'</td></tr>';
            }
            echo '</tbody></table></div></section>';
        }
        if (!empty($report['events'])) {
            echo '<h2>Local event counts</h2><dl>';
            foreach ($report['events'] as $key=>$value) { echo '<dt>'.esc_html(peracrm_campaign_readiness_ui_label($key)).'</dt><dd>'.(int)$value.'</dd>'; }
            echo '</dl>';
        }
        echo '<section><h2>Outstanding blockers</h2><ul>';
        foreach ($report['blockers'] as $blocker) {
            $parts=explode(':',$blocker,2);
            echo '<li data-readiness-blocker="'.esc_attr($blocker).'">'.esc_html(peracrm_campaign_readiness_ui_label($parts[0]).(isset($parts[1])?' — '.peracrm_campaign_readiness_ui_label($parts[1]):'')).'</li>';
        }
        echo '</ul><p>Every worker runtime check still applies. A passing snapshot or saved record is never a send permit.</p></section>';
        if ($fingerprint && $scope) {
            echo '<section><h2>Record external evidence</h2><p id="readiness-evidence-help">Independently retain and redact the artifact, then enter its lowercase SHA-256. Do not enter credentials, URLs, message bodies or notes. Verify it again today before recording. The server records your real actor ID and current UTC verification date. Advance the deployment configuration version after external changes; never reuse an old version.</p><form method="post" action="'.esc_url(peracrm_campaign_readiness_ui_url()).'" data-readiness-form>';
            echo '<input type="hidden" name="fingerprint" value="'.esc_attr($fingerprint).'"><input type="hidden" name="nonce" value="'.esc_attr(wp_create_nonce('peracrm_readiness_'.$fingerprint)).'">';
            echo '<label for="readiness-prerequisite">Prerequisite</label><select id="readiness-prerequisite" name="prerequisite" required aria-describedby="readiness-evidence-help">';
            foreach (PeraCRM_Campaign_Readiness::EXTERNAL as $name=>$source) { echo '<option value="'.esc_attr($name).'">'.esc_html(peracrm_campaign_readiness_ui_label($name).' — requires '.peracrm_campaign_readiness_ui_label($source)).'</option>'; }
            echo '</select><label for="readiness-provenance">Evidence type</label><select id="readiness-provenance" name="provenance" required>';
            foreach (['repository_mock','operator_attestation','provider_observation','host_observation'] as $source) { echo '<option value="'.esc_attr($source).'">'.esc_html(peracrm_campaign_readiness_ui_label($source)).'</option>'; }
            echo '</select><p>Mocks and attestations do not satisfy prerequisites requiring provider or host observations. An incompatible observation type is rejected.</p><label for="readiness-digest">Redacted artifact SHA-256</label><input id="readiness-digest" name="digest" type="text" inputmode="text" autocomplete="off" spellcheck="false" pattern="[a-f0-9]{64}" minlength="64" maxlength="64" required aria-describedby="readiness-evidence-help"><label class="peracrm-readiness-confirm"><input type="checkbox" name="confirmed" value="1" required> I verified this redacted artifact today for this exact deployment and configuration.</label><button type="submit" class="button button-primary">Record evidence</button></form></section>';
        } else { echo '<p>Evidence recording is unavailable until deployment scope and CRM configuration are available. Missing evidence remains unverified.</p>'; }
        $raw=$_GET['audit_before']??'0';
        if (!is_string($raw) || preg_match('/\A[0-9]{1,18}\z/',$raw)!==1) { throw new InvalidArgumentException('readiness_audit_cursor'); }
        $audit=$service->auditPage((int)$raw);
        echo '<section><h2>Evidence audit history</h2><p>Append-only history, up to ten configuration bindings per page and 64 records per binding. Previous bindings remain historical and do not verify the current configuration. A current binding can still contain expired or insufficient records; use the results above.</p>';
        if (!$audit['groups']) { echo '<p>No evidence records on this page.</p>'; }
        foreach ($audit['groups'] as $group) {
            echo '<h3>'.($group['fingerprint']===$fingerprint?'Current configuration binding':'Historical configuration binding — not applicable').'</h3><p><code>'.esc_html($group['fingerprint']).'</code></p><ol>';
            foreach ($group['records'] as $record) {
                echo '<li>'.esc_html(peracrm_campaign_readiness_ui_label($record['prerequisite']).' · '.peracrm_campaign_readiness_ui_label($record['provenance'])).' · Actor #'.(int)$record['actor'].' · '.esc_html(gmdate('Y-m-d H:i:s',$record['verified_at'])).' UTC<br>Redacted artifact SHA-256: <code>'.esc_html($record['digest']).'</code></li>';
            }
            echo '</ol>';
        }
        if ((int)$raw>0) { echo '<a href="'.esc_url(peracrm_campaign_readiness_ui_url()).'">Newest configuration histories</a> '; }
        if ($audit['next']) { echo '<a href="'.esc_url(add_query_arg('audit_before',$audit['next'],peracrm_campaign_readiness_ui_url())).'">Older configuration histories</a>'; }
        echo '</section>';
    } catch (Throwable $e) { echo '<p role="alert">Readiness or audit storage is unavailable. Reload and check deployment scope, administrator permissions and storage. No readiness or send authority is established.</p>'; }
    finally { $wpdb->suppress_errors($quiet); }
    echo '</div>';
}
