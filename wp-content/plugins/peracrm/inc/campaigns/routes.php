<?php
if (!defined('ABSPATH')) { exit; }

add_action('init', static function () {
    add_rewrite_rule('^crm/subscribers/(?:import|suppression)/?$', 'index.php?pera_crm=1&pera_crm_view=subscribers', 'top');
    add_rewrite_rule('^crm/subscribers/([0-9]+)/?$', 'index.php?pera_crm=1&pera_crm_view=subscribers&peracrm_subscriber_id=$matches[1]', 'top');
    add_rewrite_rule('^crm/subscribers/?$', 'index.php?pera_crm=1&pera_crm_view=subscribers', 'top');
    add_rewrite_rule('^email-preferences/?$', 'index.php?peracrm_email_preferences=1', 'top');
});
add_filter('query_vars', static function ($vars) { return array_merge($vars, ['peracrm_subscriber_id', 'peracrm_email_preferences']); });

/** Route gate runs before template/read handlers; direct view query vars also require it. */
add_action('template_redirect', static function () {
    if ((string) get_query_var('pera_crm_view') !== 'subscribers') { return; }
    if (!peracrm_campaigns_staff_can('peracrm_manage_subscribers')) { wp_die('Subscriber access is not permitted.', '', ['response' => 403]); }
    peracrm_subscribers_private_headers();
}, 1);

function peracrm_subscribers_private_headers()
{
    if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
    nocache_headers();
    header('Cache-Control: no-store, private, max-age=0');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
}

/** Canonical comparison uses configured target URL, never a caller-provided blog ID. */
function peracrm_subscribers_on_authoritative_domain()
{
    $url = wp_parse_url(peracrm_subscribers_preferences_url());
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $authority = strtolower($url['host'] ?? '') . (isset($url['port']) ? ':' . $url['port'] : '');
    return get_current_blog_id() === peracrm_get_target_blog_id() && hash_equals($authority, $host) && (($url['scheme'] ?? '') !== 'https' || is_ssl());
}

/** Public rendering bypasses staff routing and all theme templates/hooks/assets. */
add_action('template_redirect', static function () {
    if ((string) get_query_var('peracrm_email_preferences') !== '1') { return; }
    peracrm_subscribers_private_headers();
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    if (!peracrm_subscribers_on_authoritative_domain()) {
        // Wrong-domain POSTs never mutate. No automatic POST forwarding.
        status_header(400);
        echo 'Please use the original preferences link.';
        exit;
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw_token = $_POST['token'] ?? $_GET['token'] ?? '';
    $token = is_string($raw_token) ? wp_unslash($raw_token) : '';
    $valid = peracrm_subscribers_public_valid($token);
    $message = '';
    if ($method === 'POST') {
        $action = isset($_POST['preference_action']) && is_string($_POST['preference_action']) ? $_POST['preference_action'] : '';
        // A later provider phase can map its tested one-click POST format to withdraw.
        // Repeated valid opt-outs always pass; idempotence bounds storage work.
        $allowed = $valid && ($action === 'withdraw' || peracrm_subscribers_public_rate_allowed('token:' . hash('sha256', $token), 30));
        if (!$valid && !peracrm_subscribers_public_rate_allowed('invalid:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 60)) { status_header(429); }
        $topics = isset($_POST['topics']) && is_string($_POST['topics']) ? explode('|', trim(wp_unslash($_POST['topics']))) : [];
        if ($topics === ['']) { $topics = []; }
        $success = $allowed && peracrm_subscribers_public_post($token, $action, $topics);
        $message = $success ? ($action === 'withdraw' ? 'Your permission to receive marketing email has been withdrawn.' : 'Your topic preferences have been saved. This does not change global email permission.') : 'This request could not be completed. Please use the original preferences link.';
    } elseif ($method !== 'GET' && $method !== 'HEAD') { status_header(405); header('Allow: GET, HEAD, POST'); exit; }
    include PERACRM_INC . '/views/pages/subscriber-preferences.php';
    exit;
}, 0);

/** One plugin handler; each operation also has service-level authority. */
function peracrm_subscribers_staff_post()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !peracrm_campaigns_staff_can('peracrm_manage_subscribers', true)) { wp_die('Forbidden', '', ['response' => 403]); }
    check_admin_referer('peracrm_subscribers');
    $operation = (string) ($_POST['operation'] ?? '');
    $destination = peracrm_subscribers_url();
    try {
        if ($operation === 'profile' || $operation === 'withdraw') {
            $row = peracrm_subscribers_detail((int) ($_POST['subscriber_id'] ?? 0));
            if (!$row) { throw new RuntimeException('Subscriber unavailable.'); }
            if ($operation === 'profile') {
                $topics = trim((string) wp_unslash($_POST['topics'] ?? ''));
                $client = trim((string) ($_POST['client_id'] ?? ''));
                $result = peracrm_campaigns_save_subscriber($row['normalized_email'], ['preferred_language' => (string) wp_unslash($_POST['language'] ?? ''), 'preferences' => $topics === '' ? [] : explode('|', $topics), 'client_id' => $client === '' ? null : (int) $client]);
            } else { $result = peracrm_campaigns_withdraw($row['normalized_email'], ['source' => 'staff_withdrawal']); }
            if (is_wp_error($result)) { throw new RuntimeException('Change was not accepted.'); }
            $destination = peracrm_subscribers_url($row['id'] . '/');
        } elseif ($operation === 'suppression') {
            $email = peracrm_campaigns_normalize_email((string) wp_unslash($_POST['email'] ?? ''));
            if (!peracrm_subscribers_intake_allowed((bool) preg_match('/@(?:[a-z0-9-]+\.)*example\.test$/D', $email))) { throw new RuntimeException('Readiness gate is closed.'); }
            $result = peracrm_campaigns_add_suppression((string) wp_unslash($_POST['email'] ?? ''), (string) ($_POST['reason'] ?? ''), ['source' => (string) wp_unslash($_POST['source'] ?? ''), 'source_ref' => (string) wp_unslash($_POST['source_ref'] ?? ''), 'occurred_at' => (string) ($_POST['occurred_at'] ?? '')]);
            if (is_wp_error($result)) { throw new RuntimeException('Suppression was not accepted.'); }
        } elseif ($operation === 'preview') {
            $file = $_FILES['csv'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || (int) ($file['size'] ?? 0) > PERACRM_SUBSCRIBER_CSV_BYTES || filesize($file['tmp_name']) > PERACRM_SUBSCRIBER_CSV_BYTES || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') { throw new RuntimeException('Upload a CSV up to 1 MiB.'); }
            $stream = fopen($file['tmp_name'], 'rb');
            try { $id = peracrm_subscribers_preview($stream, wp_unslash($_POST['mapping'] ?? []), !empty($_POST['synthetic']), !empty($_POST['suppression_complete'])); }
            finally { if (is_resource($stream)) { fclose($stream); } unlink($file['tmp_name']); }
            $destination = add_query_arg('batch', $id, peracrm_subscribers_url('import/'));
        } elseif ($operation === 'apply' || $operation === 'cancel') {
            $id = (string) ($_POST['batch'] ?? '');
            if ($operation === 'apply') { peracrm_subscribers_apply_batch($id); } else { peracrm_subscribers_cancel_batch($id); }
            $destination = add_query_arg('batch', $id, peracrm_subscribers_url('import/'));
        } elseif ($operation === 'readiness') {
            peracrm_subscribers_readiness(wp_unslash($_POST['readiness'] ?? []));
            $destination = peracrm_subscribers_url('import/');
        } elseif ($operation === 'export') {
            $id = (string) ($_POST['batch'] ?? '');
            peracrm_subscribers_batch($id);
            peracrm_subscribers_private_headers();
            header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="subscriber-preview.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['row', 'email', 'state', 'status', 'result'], ',', '"', '');
            for ($page = 1; $page <= 20; $page++) {
                $rows = peracrm_subscribers_batch_rows($id, $page);
                foreach ($rows as $entry) {
                    $row = json_decode($entry['payload_json'], true);
                    fputcsv($out, array_map('peracrm_subscribers_csv_cell', [$entry['line_number'], $row['email'] ?? '', $row['state'] ?? '', $entry['status'], $entry['result']]), ',', '"', '');
                }
                if (count($rows) < 50) { break; }
            }
            fclose($out); exit;
        } else { throw new RuntimeException('Invalid operation.'); }
        $destination = add_query_arg('subscriber_notice', 'saved', $destination);
    } catch (Throwable $error) {
        if ($operation === 'preview' && isset($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) { unlink($_FILES['csv']['tmp_name']); }
        // Controlled errors only; never put addresses, tokens, SQL or exception text in URLs.
        $destination = add_query_arg('subscriber_notice', 'failed', $destination);
    }
    wp_safe_redirect($destination); exit;
}
add_action('admin_post_peracrm_subscribers', 'peracrm_subscribers_staff_post');
