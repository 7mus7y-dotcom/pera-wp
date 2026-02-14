<?php

if (!defined('ABSPATH')) {
    exit;
}

$peracrm_push_vendor_autoload = defined('PERACRM_PATH') ? PERACRM_PATH . '/vendor-webpush/vendor/autoload.php' : '';
if ($peracrm_push_vendor_autoload && file_exists($peracrm_push_vendor_autoload)) {
    require_once $peracrm_push_vendor_autoload;
}

function peracrm_push_meta_key()
{
    return 'peracrm_push_subscriptions';
}

function peracrm_push_in_target_blog(callable $callback)
{
    if (function_exists('peracrm_with_target_blog')) {
        return peracrm_with_target_blog($callback);
    }

    return $callback();
}

function peracrm_push_is_configured()
{
    return defined('PERACRM_VAPID_PUBLIC_KEY')
        && defined('PERACRM_VAPID_PRIVATE_KEY')
        && defined('PERACRM_VAPID_SUBJECT')
        && trim((string) PERACRM_VAPID_PUBLIC_KEY) !== ''
        && trim((string) PERACRM_VAPID_PRIVATE_KEY) !== ''
        && trim((string) PERACRM_VAPID_SUBJECT) !== '';
}

function peracrm_push_get_vapid_config()
{
    return [
        'public_key' => defined('PERACRM_VAPID_PUBLIC_KEY') ? trim((string) PERACRM_VAPID_PUBLIC_KEY) : '',
        'private_key' => defined('PERACRM_VAPID_PRIVATE_KEY') ? trim((string) PERACRM_VAPID_PRIVATE_KEY) : '',
        'subject' => defined('PERACRM_VAPID_SUBJECT') ? trim((string) PERACRM_VAPID_SUBJECT) : '',
    ];
}

function peracrm_push_get_public_config()
{
    $vapid = peracrm_push_get_vapid_config();

    return [
        'public_key' => (string) ($vapid['public_key'] ?? ''),
        'sw_url' => home_url('/peracrm-sw.js'),
        'click_url' => '/crm/tasks/',
    ];
}

function peracrm_push_normalize_subscription($subscription, $user_agent = '')
{
    if (!is_array($subscription)) {
        return null;
    }

    $endpoint = isset($subscription['endpoint']) ? esc_url_raw((string) $subscription['endpoint']) : '';
    $keys = isset($subscription['keys']) && is_array($subscription['keys']) ? $subscription['keys'] : [];
    $p256dh = isset($keys['p256dh']) ? sanitize_text_field((string) $keys['p256dh']) : '';
    $auth = isset($keys['auth']) ? sanitize_text_field((string) $keys['auth']) : '';

    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        return null;
    }

    $now = current_time('mysql');

    return [
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => $p256dh,
            'auth' => $auth,
        ],
        'created_at' => isset($subscription['created_at']) ? sanitize_text_field((string) $subscription['created_at']) : $now,
        'last_seen_at' => $now,
        'user_agent' => sanitize_text_field((string) $user_agent),
    ];
}

function peracrm_push_list_user_subscriptions($user_id)
{
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return [];
    }

    $stored = get_user_meta($user_id, peracrm_push_meta_key(), true);
    if (!is_array($stored)) {
        return [];
    }

    $normalized = [];
    foreach ($stored as $item) {
        $entry = peracrm_push_normalize_subscription((array) $item, (string) ($item['user_agent'] ?? ''));
        if (!$entry) {
            continue;
        }
        $normalized[$entry['endpoint']] = $entry;
    }

    return array_values($normalized);
}

function peracrm_push_get_subscriptions($user_id)
{
    return peracrm_push_list_user_subscriptions($user_id);
}

function peracrm_push_save_subscription($user_id, $subscription, $user_agent = '')
{
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return new WP_Error('invalid_user', 'Invalid user.');
    }

    $entry = peracrm_push_normalize_subscription($subscription, $user_agent);
    if (!$entry) {
        return new WP_Error('invalid_subscription', 'Invalid push subscription payload.');
    }

    $subscriptions = peracrm_push_list_user_subscriptions($user_id);
    $deduped = [];
    foreach ($subscriptions as $item) {
        $deduped[(string) $item['endpoint']] = $item;
    }

    if (isset($deduped[$entry['endpoint']])) {
        $entry['created_at'] = (string) ($deduped[$entry['endpoint']]['created_at'] ?? $entry['created_at']);
    }

    $deduped[$entry['endpoint']] = $entry;
    update_user_meta($user_id, peracrm_push_meta_key(), array_values($deduped));

    return true;
}

function peracrm_push_remove_subscription($user_id, $endpoint)
{
    $user_id = absint($user_id);
    $endpoint = esc_url_raw((string) $endpoint);
    if ($user_id <= 0 || $endpoint === '') {
        return false;
    }

    $subscriptions = peracrm_push_list_user_subscriptions($user_id);
    $kept = [];
    $removed = false;

    foreach ($subscriptions as $item) {
        if ((string) $item['endpoint'] === $endpoint) {
            $removed = true;
            continue;
        }
        $kept[] = $item;
    }

    update_user_meta($user_id, peracrm_push_meta_key(), $kept);

    return $removed;
}

function peracrm_push_send_to_subscription($subscription, $payload)
{
    $subscription = peracrm_push_normalize_subscription((array) $subscription);
    if (!$subscription) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => 'Invalid subscription payload.',
        ];
    }

    if (!peracrm_push_is_configured()) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => 'VAPID constants are not configured.',
        ];
    }

    if (!class_exists('Minishlink\\WebPush\\WebPush') || !class_exists('Minishlink\\WebPush\\Subscription')) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => 'Web Push sender library missing. Expected vendor-webpush dependencies.',
        ];
    }

    $vapid = peracrm_push_get_vapid_config();
    $auth = [
        'VAPID' => [
            'subject' => (string) $vapid['subject'],
            'publicKey' => (string) $vapid['public_key'],
            'privateKey' => (string) $vapid['private_key'],
        ],
    ];

    try {
        $webPush = new Minishlink\WebPush\WebPush($auth, ['TTL' => 300]);
        $subscription_obj = Minishlink\WebPush\Subscription::create([
            'endpoint' => (string) $subscription['endpoint'],
            'publicKey' => (string) ($subscription['keys']['p256dh'] ?? ''),
            'authToken' => (string) ($subscription['keys']['auth'] ?? ''),
            'contentEncoding' => 'aes128gcm',
        ]);

        $report = $webPush->sendOneNotification($subscription_obj, wp_json_encode((array) $payload), [
            'TTL' => 300,
            'urgency' => 'normal',
            'topic' => 'peracrm-reminders',
        ]);

        $response = method_exists($report, 'getResponse') ? $report->getResponse() : null;
        $status = $response && method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 0;
        $body = '';
        if ($response && method_exists($response, 'getBody')) {
            $body = (string) $response->getBody();
        }

        return [
            'ok' => (bool) $report->isSuccess(),
            'status' => $status,
            'body' => $body !== '' ? $body : (method_exists($report, 'getReason') ? (string) $report->getReason() : ''),
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => $e->getMessage(),
        ];
    }
}

function peracrm_push_send_subscription($subscription, array $payload)
{
    $result = peracrm_push_send_to_subscription($subscription, $payload);

    return [
        'ok' => (bool) ($result['ok'] ?? false),
        'status_code' => (int) ($result['status'] ?? 0),
        'reason' => (string) ($result['body'] ?? ''),
    ];
}

function peracrm_push_send_to_user($user_id, $payload)
{
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return [];
    }

    $subscriptions = peracrm_push_list_user_subscriptions($user_id);
    $results = [];

    foreach ($subscriptions as $subscription) {
        $result = peracrm_push_send_to_subscription($subscription, $payload);
        if (in_array((int) ($result['status'] ?? 0), [404, 410], true)) {
            peracrm_push_remove_subscription($user_id, (string) ($subscription['endpoint'] ?? ''));
        }

        $results[] = [
            'endpoint' => (string) ($subscription['endpoint'] ?? ''),
            'ok' => (bool) ($result['ok'] ?? false),
            'status' => (int) ($result['status'] ?? 0),
            'body' => (string) ($result['body'] ?? ''),
        ];
    }

    return $results;
}

function peracrm_push_digest_window_key($timestamp)
{
    return wp_date('YmdHi', (int) $timestamp, wp_timezone());
}

function peracrm_push_run_digest()
{
    if (!peracrm_push_is_configured()) {
        return;
    }

    peracrm_push_in_target_blog(static function () {
        global $wpdb;

        if (!function_exists('peracrm_reminders_table_exists') || !peracrm_reminders_table_exists()) {
            return;
        }

        $table = peracrm_table('crm_reminders');
        $now = current_time('mysql');
        $window_start = floor((int) current_time('timestamp') / (15 * MINUTE_IN_SECONDS)) * (15 * MINUTE_IN_SECONDS);
        $window_key = peracrm_push_digest_window_key($window_start);

        $rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT advisor_user_id,
                        COUNT(*) AS pending_count,
                        SUM(CASE WHEN due_at < %s THEN 1 ELSE 0 END) AS overdue_count
                 FROM {$table}
                 WHERE status = %s
                 GROUP BY advisor_user_id",
                $now,
                'pending'
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $advisor_user_id = isset($row['advisor_user_id']) ? (int) $row['advisor_user_id'] : 0;
            $pending_count = isset($row['pending_count']) ? (int) $row['pending_count'] : 0;
            $overdue_count = isset($row['overdue_count']) ? (int) $row['overdue_count'] : 0;

            if ($advisor_user_id <= 0 || ($pending_count + $overdue_count) <= 0) {
                continue;
            }

            $subscriptions = peracrm_push_list_user_subscriptions($advisor_user_id);
            if (empty($subscriptions)) {
                continue;
            }

            $digest_hash = md5(implode('|', [$window_key, $pending_count, $overdue_count]));
            $last_hash = (string) get_user_meta($advisor_user_id, 'peracrm_push_last_digest_' . $window_key, true);
            if ($last_hash !== '' && hash_equals($last_hash, $digest_hash)) {
                continue;
            }

            $payload = [
                'type' => 'crm_reminder_digest',
                'title' => 'PeraCRM',
                'body' => sprintf('You have %d pending reminders (%d overdue).', $pending_count, $overdue_count),
                'pending_count' => $pending_count,
                'overdue_count' => $overdue_count,
                'click_url' => '/crm/tasks/',
            ];

            $results = peracrm_push_send_to_user($advisor_user_id, $payload);
            $has_success = false;
            foreach ($results as $result) {
                if (!empty($result['ok'])) {
                    $has_success = true;
                    break;
                }
            }

            if ($has_success) {
                update_user_meta($advisor_user_id, 'peracrm_push_last_digest_' . $window_key, $digest_hash);
            }
        }
    });
}

function peracrm_push_handle_send_test()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $can_access = current_user_can('manage_options') || (function_exists('peracrm_user_can_access_crm') && peracrm_user_can_access_crm(get_current_user_id()));
    if (!is_user_logged_in() || !$can_access) {
        wp_die(esc_html__('You are not allowed to do this.', 'peracrm'), 403);
    }

    check_admin_referer('peracrm_send_test_push', 'peracrm_send_test_push_nonce');

    $redirect = isset($_POST['peracrm_redirect']) ? esc_url_raw(wp_unslash($_POST['peracrm_redirect'])) : home_url('/crm/');
    if (!$redirect) {
        $redirect = home_url('/crm/');
    }

    $payload = [
        'type' => 'crm_test',
        'title' => 'PeraCRM',
        'body' => 'Test notification',
        'click_url' => '/crm/tasks/',
    ];

    $results = peracrm_push_in_target_blog(static function () use ($payload) {
        return peracrm_push_send_to_user(get_current_user_id(), $payload);
    });

    $ok_count = 0;
    foreach ((array) $results as $result) {
        if (!empty($result['ok'])) {
            $ok_count++;
        }
    }

    $notice = $ok_count > 0 ? 'test_push_sent' : 'test_push_failed';
    wp_safe_redirect(add_query_arg('peracrm_push_notice', $notice, $redirect));
    exit;
}
add_action('admin_post_peracrm_send_test_push', 'peracrm_push_handle_send_test');

function peracrm_push_render_service_worker()
{
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = $request_uri !== '' ? (string) wp_parse_url($request_uri, PHP_URL_PATH) : '';

    if ($request_path !== '/peracrm-sw.js') {
        return;
    }

    nocache_headers();
    header('Content-Type: application/javascript; charset=UTF-8');

    echo "self.addEventListener('push', function(event) {\n";
    echo "  var payload = {};\n";
    echo "  try { payload = event.data ? event.data.json() : {}; } catch (e) { payload = {}; }\n";
    echo "  var title = payload.title || 'PeraCRM';\n";
    echo "  var body = payload.body || '';\n";
    echo "  if (!body) {\n";
    echo "    var overdue = Number(payload.overdue_count || 0);\n";
    echo "    var pending = Number(payload.pending_count || 0);\n";
    echo "    body = overdue > 0 ? 'You have ' + overdue + ' overdue reminders.' : 'You have ' + pending + ' pending reminders.';\n";
    echo "  }\n";
    echo "  var clickUrl = payload.click_url || '/crm/tasks/';\n";
    echo "  event.waitUntil(self.registration.showNotification(title, { body: body, data: { click_url: clickUrl } }));\n";
    echo "});\n";
    echo "self.addEventListener('notificationclick', function(event) {\n";
    echo "  event.notification.close();\n";
    echo "  var clickUrl = (event.notification.data && event.notification.data.click_url) ? event.notification.data.click_url : '/crm/tasks/';\n";
    echo "  event.waitUntil(clients.openWindow(clickUrl));\n";
    echo "});\n";
    exit;
}
add_action('init', 'peracrm_push_render_service_worker', 1);
