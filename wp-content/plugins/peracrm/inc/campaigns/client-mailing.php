<?php
/** Mailing permission for an existing CRM mailbox. No automatic enrolment or client linking. */
if (!defined('ABSPATH')) { exit; }

function peracrm_client_mailing_authorize($client_id, $write = false)
{
    peracrm_subscribers_require_staff($write);
    if (!is_int($client_id) || $client_id < 1 || !peracrm_user_can_access_client(peracrm_get_actor_user_id(), $client_id)
        || get_post_type($client_id) !== 'crm_client' || in_array(get_post_status($client_id), ['trash', 'auto-draft', false], true)) {
        throw new RuntimeException('Client access denied.');
    }
}

/** Read the actual profile field, without repairing an invalid address or using aliases. */
function peracrm_client_mailing_email($client_id, $lock = false)
{
    global $wpdb;
    if ($lock) {
        $post = $wpdb->get_row($wpdb->prepare("SELECT post_type, post_status FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE", $client_id), ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$post || $post['post_type'] !== 'crm_client' || in_array($post['post_status'], ['trash','auto-draft'], true)) {
            throw new RuntimeException('Client unavailable.');
        }
    }
    $values = $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s ORDER BY meta_id LIMIT 2" . ($lock ? ' FOR UPDATE' : ''), $client_id, '_peracrm_email'));
    peracrm_campaigns_db_require(true);
    if (!$values || (count($values) === 1 && trim((string)$values[0]) === '')) { return ''; }
    if (count($values) !== 1) { throw new InvalidArgumentException('Ambiguous email field.'); }
    return peracrm_campaigns_normalize_email($values[0]);
}

/** Staff-only read. Suppression tombstones are visible even without a subscriber. */
function peracrm_client_mailing_status($client_id)
{
    return peracrm_with_target_blog(static function () use ($client_id) {
        global $wpdb;
        peracrm_client_mailing_authorize($client_id);
        try { $email = peracrm_client_mailing_email($client_id); }
        catch (InvalidArgumentException $error) { return ['status'=>'invalid_email', 'email'=>'', 'subscriber_id'=>0]; }
        if ($email === '') { return ['status'=>'missing_email', 'email'=>'', 'subscriber_id'=>0]; }
        $row = peracrm_campaigns_repository_identity($email);
        $suppression = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email = %s', $email), ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (peracrm_campaigns_has_suppression($suppression ?: [])) { $state = 'suppressed'; }
        elseif (($row['subscription_state'] ?? '') === 'unsubscribed' || !empty($row['withdrawn_at'])) { $state = 'unsubscribed'; }
        elseif (!$row) { $state = 'missing'; }
        elseif ($row['subscription_state'] === 'subscribed' && !empty($row['confirmed_at']) && !empty($row['recorded_at']) && peracrm_campaigns_has_required_evidence($row)) { $state = 'subscribed'; }
        else { $state = 'unconfirmed'; }
        return ['status'=>$state, 'email'=>$email, 'subscriber_id'=>(int)($row['id'] ?? 0)];
    });
}

/** One mailbox transaction creates and confirms permission, or rolls the entire action back. */
function peracrm_client_mailing_confirm($client_id, $expected_email, $confirmed, $evidence_ref)
{
    try {
        return peracrm_with_target_blog(static function () use ($client_id, $expected_email, $confirmed, $evidence_ref) {
            peracrm_client_mailing_authorize($client_id, true);
            if ($confirmed !== true || !is_string($evidence_ref) || trim($evidence_ref) === '') { throw new InvalidArgumentException('Permission confirmation required.'); }
            $email = peracrm_campaigns_normalize_email($expected_email);
            $evidence = peracrm_campaigns_evidence([
                'source'=>'crm_staff_confirmation', 'source_ref'=>'crm-client:'.$client_id,
                'evidence_ref'=>trim($evidence_ref), 'policy_version'=>'crm-marketing-confirmation-v1',
                'occurred_at'=>gmdate('Y-m-d H:i:s'),
            ], true);
            return peracrm_campaigns_staff_mutate($email, static function ($email, $subscriber, $suppression, $actor) use ($client_id, $evidence) {
                global $wpdb;
                peracrm_client_mailing_authorize($client_id, true);
                if (!peracrm_subscribers_intake_allowed(false)) { throw new RuntimeException('Subscriber intake disabled.'); }
                // Bind the confirmation to the mailbox shown on the form, despite concurrent profile edits.
                if (peracrm_client_mailing_email($client_id, true) !== $email) { throw new InvalidArgumentException('Client email changed. Reload before confirming.'); }
                if (peracrm_campaigns_has_suppression($suppression) || ($subscriber['subscription_state'] ?? '') === 'unsubscribed' || !empty($subscriber['withdrawn_at'])) {
                    throw new InvalidArgumentException('Existing opt-out or suppression cannot be cleared here.');
                }
                if (($subscriber['subscription_state'] ?? '') === 'subscribed' && !empty($subscriber['confirmed_at']) && !empty($subscriber['recorded_at']) && peracrm_campaigns_has_required_evidence($subscriber)) { return 'already_subscribed'; }
                if (!$subscriber) {
                    $now = gmdate('Y-m-d H:i:s');
                    $data = ['email'=>$email, 'normalized_email'=>$email, 'client_id'=>null,
                        'subscription_state'=>'unknown', 'preferences_json'=>'[]', 'preferred_language'=>'',
                        'created_at'=>$now, 'updated_at'=>$now, 'version'=>1];
                    peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_subscribers'), $data));
                    $subscriber = $data + ['id'=>(int)$wpdb->insert_id];
                    peracrm_campaigns_repository_replace_identity($subscriber['id'], $email, true);
                    peracrm_campaigns_repository_event($email, $subscriber, 'created', 'unknown', [], $actor);
                }
                peracrm_campaigns_store_consent($email, $subscriber, $suppression, $actor, 'subscribed', $evidence);
                return 'saved';
            });
        });
    } catch (Throwable $error) { return new WP_Error('client_mailing_rejected', 'Mailing permission could not be saved. Reload and check the client email, permission reference and subscriber intake.'); }
}

/** POST-only handler; nonce, subscriber capability and client authority are independent checks. */
function peracrm_client_mailing_post()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !peracrm_campaigns_staff_can('peracrm_manage_subscribers', true)) { wp_die('Forbidden', '', ['response'=>403]); }
    $raw_id = $_POST['client_id'] ?? '';
    if (!is_string($raw_id) || !ctype_digit($raw_id) || (int)$raw_id < 1) { wp_die('Forbidden', '', ['response'=>403]); }
    $client_id = (int)$raw_id;
    check_admin_referer('peracrm_client_mailing_'.$client_id);
    try { peracrm_with_target_blog(static function () use ($client_id) { peracrm_client_mailing_authorize($client_id, true); }); }
    catch (Throwable $error) { wp_die('Forbidden', '', ['response'=>403]); }
    $result = peracrm_client_mailing_confirm($client_id, wp_unslash($_POST['expected_email'] ?? ''), ($_POST['permission_confirmed'] ?? '') === '1', wp_unslash($_POST['evidence_ref'] ?? ''));
    $notice = is_wp_error($result) ? 'failed' : 'saved';
    $url = peracrm_with_target_blog(static function () use ($client_id, $notice) { return add_query_arg('mailing_notice', $notice, home_url('/crm/client/'.$client_id.'/')); });
    wp_safe_redirect($url.'#crm-client-mailing-list');
    exit;
}
add_action('admin_post_peracrm_client_mailing', 'peracrm_client_mailing_post');

/** Read-only, count-only comparison. Fixed client-ID boundary; no transactional snapshot claim. */
function peracrm_client_mailing_audit()
{
    return peracrm_with_target_blog(static function () {
        global $wpdb;
        peracrm_subscribers_require_staff();
        if (!user_can(peracrm_get_actor_user_id(), 'manage_options')) { throw new RuntimeException('Administrator required.'); }
        $boundary = (int)$wpdb->get_var($wpdb->prepare("SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type=%s", 'crm_client'));
        peracrm_campaigns_db_require(true);
        $counts = array_fill_keys(['subscribed','suppressed','unsubscribed','missing','unconfirmed','missing_email','invalid_email'], 0);
        $cursor = 0;
        do {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status NOT IN ('trash','auto-draft') AND ID>%d AND ID<=%d ORDER BY ID LIMIT 500", 'crm_client', $cursor, $boundary));
            peracrm_campaigns_db_require(true);
            foreach ($ids as $id) { $cursor = (int)$id; $counts[peracrm_client_mailing_status($cursor)['status']]++; }
        } while (count($ids) === 500);
        return ['checked_at_utc'=>gmdate('Y-m-d H:i:s'), 'client_records'=>array_sum($counts), 'counts'=>$counts, 'read_only'=>true,
            'scope'=>'Current saved client email; counts are per client, not unique mailbox. Advisory live comparison.'];
    });
}
