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

function peracrm_push_get_vapid_config()
{
    $public = defined('PERACRM_VAPID_PUBLIC_KEY') ? (string) PERACRM_VAPID_PUBLIC_KEY : '';
    $private = defined('PERACRM_VAPID_PRIVATE_KEY') ? (string) PERACRM_VAPID_PRIVATE_KEY : '';
    $subject = defined('PERACRM_VAPID_SUBJECT') ? (string) PERACRM_VAPID_SUBJECT : '';

    if ($public === '') {
        $public = (string) get_option('peracrm_vapid_public_key', '');
    }
    if ($private === '') {
        $private = (string) get_option('peracrm_vapid_private_key', '');
    }
    if ($subject === '') {
        $subject = (string) get_option('peracrm_vapid_subject', '');
    }

    return [
        'public_key' => trim($public),
        'private_key' => trim($private),
        'subject' => trim($subject),
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

function peracrm_push_get_subscriptions($user_id)
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

    $subscriptions = peracrm_push_get_subscriptions($user_id);
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

    $subscriptions = peracrm_push_get_subscriptions($user_id);
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

function peracrm_push_round_window_end($timestamp = null)
{
    $timestamp = $timestamp === null ? current_time('timestamp') : (int) $timestamp;
    $bucket = 300;
    $window_end = (int) (ceil($timestamp / $bucket) * $bucket);

    return wp_date('Y-m-d H:i:s', $window_end, wp_timezone());
}

function peracrm_push_log_has_event($event_key)
{
    global $wpdb;

    $table = peracrm_push_log_table_name();
    $query = $wpdb->prepare("SELECT id FROM {$table} WHERE event_key = %s LIMIT 1", $event_key);

    return (bool) $wpdb->get_var($query);
}

function peracrm_push_log_insert($advisor_user_id, $window_end, $event_key)
{
    global $wpdb;

    $table = peracrm_push_log_table_name();
    $result = $wpdb->insert(
        $table,
        [
            'advisor_user_id' => (int) $advisor_user_id,
            'window_end' => (string) $window_end,
            'event_key' => (string) $event_key,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s']
    );

    return $result !== false;
}

function peracrm_push_send_subscription($subscription, array $payload)
{
    $vapid = peracrm_push_get_vapid_config();

    if (
        class_exists('Minishlink\WebPush\WebPush')
        && class_exists('Minishlink\WebPush\Subscription')
        && $vapid['public_key'] !== ''
        && $vapid['private_key'] !== ''
        && $vapid['subject'] !== ''
    ) {
        $auth = [
            'VAPID' => [
                'subject' => (string) $vapid['subject'],
                'publicKey' => (string) $vapid['public_key'],
                'privateKey' => (string) $vapid['private_key'],
            ],
        ];

        try {
            $webPush = new Minishlink\WebPush\WebPush($auth);
            $subscription_obj = Minishlink\WebPush\Subscription::create([
                'endpoint' => (string) ($subscription['endpoint'] ?? ''),
                'publicKey' => (string) ($subscription['keys']['p256dh'] ?? ''),
                'authToken' => (string) ($subscription['keys']['auth'] ?? ''),
            ]);

            $report = $webPush->sendOneNotification($subscription_obj, wp_json_encode($payload));
            $response = method_exists($report, 'getResponse') ? $report->getResponse() : null;
            $status_code = $response && method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 0;

            return [
                'ok' => (bool) $report->isSuccess(),
                'status_code' => $status_code,
                'reason' => method_exists($report, 'getReason') ? (string) $report->getReason() : '',
            ];
        } catch (Throwable $e) {
            return new WP_Error('push_send_exception', $e->getMessage());
        }
    }

    return apply_filters('peracrm_push_send_subscription', new WP_Error('push_sender_not_configured', 'Web Push sender not configured.'), $subscription, $payload);
}

function peracrm_push_run_tick()
{
    if (!function_exists('peracrm_with_target_blog')) {
        return;
    }

    peracrm_with_target_blog(static function () {
        global $wpdb;

        if (!function_exists('peracrm_reminders_table_exists') || !peracrm_reminders_table_exists()) {
            return;
        }

        if (function_exists('peracrm_push_log_create_table')) {
            peracrm_push_log_create_table();
        }

        $table = peracrm_table('crm_reminders');
        $now = current_time('mysql');
        $window_end = peracrm_push_round_window_end();

        $query = $wpdb->prepare(
            "SELECT advisor_user_id, COUNT(*) AS overdue_count
             FROM {$table}
             WHERE status = %s AND due_at < %s
             GROUP BY advisor_user_id",
            'pending',
            $now
        );

        $rows = (array) $wpdb->get_results($query, ARRAY_A);

        foreach ($rows as $row) {
            $advisor_user_id = isset($row['advisor_user_id']) ? (int) $row['advisor_user_id'] : 0;
            $overdue_count = isset($row['overdue_count']) ? (int) $row['overdue_count'] : 0;
            if ($advisor_user_id <= 0 || $overdue_count <= 0) {
                continue;
            }

            $subscriptions = peracrm_push_get_subscriptions($advisor_user_id);
            if (empty($subscriptions)) {
                continue;
            }

            $event_key = $advisor_user_id . ':' . $window_end;
            if (peracrm_push_log_has_event($event_key)) {
                continue;
            }

            $payload = [
                'pending_count' => $overdue_count,
                'overdue_count' => $overdue_count,
                'click_url' => '/crm/tasks/',
                'window_end' => $window_end,
                'event_key' => $event_key,
            ];

            $success_count = 0;
            foreach ($subscriptions as $subscription) {
                $result = peracrm_push_send_subscription($subscription, $payload);

                if (is_wp_error($result)) {
                    continue;
                }

                $status_code = isset($result['status_code']) ? (int) $result['status_code'] : 0;
                if ($status_code === 404 || $status_code === 410) {
                    peracrm_push_remove_subscription($advisor_user_id, (string) ($subscription['endpoint'] ?? ''));
                    continue;
                }

                if ($status_code >= 200 && $status_code < 300) {
                    $success_count++;
                }
            }

            if ($success_count > 0) {
                peracrm_push_log_insert($advisor_user_id, $window_end, $event_key);
            }
        }
    });
}

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
    echo "  var overdue = Number(payload.overdue_count || 0);\n";
    echo "  var pending = Number(payload.pending_count || 0);\n";
    echo "  var body = overdue > 0\n";
    echo "    ? 'You have ' + overdue + ' overdue reminders. Tap to view.'\n";
    echo "    : 'You have ' + pending + ' reminders due. Tap to view.';\n";
    echo "  var clickUrl = payload.click_url || '/crm/tasks/';\n";
    echo "  event.waitUntil(self.registration.showNotification('CRM Reminder Due', { body: body, data: { click_url: clickUrl } }));\n";
    echo "});\n";
    echo "self.addEventListener('notificationclick', function(event) {\n";
    echo "  event.notification.close();\n";
    echo "  var clickUrl = (event.notification.data && event.notification.data.click_url) ? event.notification.data.click_url : '/crm/tasks/';\n";
    echo "  event.waitUntil(clients.openWindow(clickUrl));\n";
    echo "});\n";
    exit;
}
add_action('init', 'peracrm_push_render_service_worker', 1);
