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

function peracrm_push_missing_config_reasons()
{
    $reasons = [];

    if (!defined('PERACRM_VAPID_PUBLIC_KEY') || trim((string) PERACRM_VAPID_PUBLIC_KEY) === '') {
        $reasons[] = 'missing VAPID_PUBLIC_KEY';
    }

    if (!defined('PERACRM_VAPID_PRIVATE_KEY') || trim((string) PERACRM_VAPID_PRIVATE_KEY) === '') {
        $reasons[] = 'missing VAPID_PRIVATE_KEY';
    }

    if (!defined('PERACRM_VAPID_SUBJECT') || trim((string) PERACRM_VAPID_SUBJECT) === '') {
        $reasons[] = 'missing VAPID_SUBJECT';
    }

    if (!class_exists('Minishlink\WebPush\WebPush') || !class_exists('Minishlink\WebPush\Subscription')) {
        $reasons[] = 'push library unavailable';
    }

    return $reasons;
}

function peracrm_push_is_configured()
{
    return count(peracrm_push_missing_config_reasons()) === 0;
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
    $public_key = (string) ($vapid['public_key'] ?? '');

    return [
        'publicKey' => $public_key,
        'swUrl' => home_url('/peracrm-sw.js'),
        'subscribeUrl' => rest_url('peracrm/v1/push/subscribe'),
        'unsubscribeUrl' => rest_url('peracrm/v1/push/unsubscribe'),
        'digestRunUrl' => rest_url('peracrm/v1/push/digest/run'),
        'debugUrl' => rest_url('peracrm/v1/push/debug'),
        'canRunDigest' => peracrm_push_user_can_run_digest(get_current_user_id()),
        'isConfigured' => peracrm_push_is_configured(),
        'missingReasons' => peracrm_push_missing_config_reasons(),
        'clickUrl' => peracrm_push_default_click_url(),
        // Deprecated aliases kept for backwards compatibility.
        'public_key' => $public_key,
        'sw_url' => home_url('/peracrm-sw.js'),
        'click_url' => peracrm_push_default_click_url(),
    ];
}

function peracrm_push_default_click_url()
{
    return (string) apply_filters('peracrm_push_click_url', '/crm/tasks/');
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
            'body' => implode('; ', peracrm_push_missing_config_reasons()),
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

function peracrm_push_should_log_payload_bodies()
{
    return (defined('PERA_CRM_DEBUG_TASKS') && PERA_CRM_DEBUG_TASKS)
        || (defined('PERA_CRM_DEBUG_PUSH') && PERA_CRM_DEBUG_PUSH);
}

function peracrm_push_get_log_table_columns()
{
    global $wpdb;

    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $table = function_exists('peracrm_push_log_table_name') ? peracrm_push_log_table_name() : peracrm_table('crm_push_log');
    if ($table === '' || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return $cache;
    }

    $rows = (array) $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
    foreach ($rows as $row) {
        $field = isset($row['Field']) ? sanitize_key((string) $row['Field']) : '';
        if ($field !== '') {
            $cache[$field] = true;
        }
    }

    return $cache;
}

function peracrm_push_log_attempt($args)
{
    global $wpdb;

    $table = function_exists('peracrm_push_log_table_name') ? peracrm_push_log_table_name() : peracrm_table('crm_push_log');
    $available = peracrm_push_get_log_table_columns();
    if ($table === '' || empty($available)) {
        return;
    }

    $required_columns = ['user_id', 'endpoint_hash', 'payload_type', 'status_code', 'ok', 'created_at'];
    foreach ($required_columns as $required_column) {
        if (!isset($available[$required_column])) {
            return;
        }
    }

    $payload = isset($args['payload']) && is_array($args['payload']) ? $args['payload'] : [];
    $payload_json = '';
    if (peracrm_push_should_log_payload_bodies()) {
        $payload_json = (string) wp_json_encode($payload);
        if (strlen($payload_json) > 4000) {
            $payload_json = substr($payload_json, 0, 4000);
        }
    }

    $endpoint = isset($args['endpoint']) ? esc_url_raw((string) $args['endpoint']) : '';
    $endpoint_hash = $endpoint !== '' ? hash('sha256', $endpoint) : '';

    $row = [
        'user_id' => isset($args['user_id']) ? absint($args['user_id']) : 0,
        'endpoint_hash' => $endpoint_hash,
        'endpoint' => peracrm_push_should_log_payload_bodies() ? $endpoint : '',
        'payload_type' => isset($args['payload_type']) ? sanitize_key((string) $args['payload_type']) : '',
        'payload_json' => $payload_json,
        'window_key' => isset($args['window_key']) ? sanitize_text_field((string) $args['window_key']) : '',
        'status_code' => isset($args['status']) ? (int) $args['status'] : 0,
        'ok' => !empty($args['ok']) ? 1 : 0,
        'response_body' => isset($args['body']) ? substr((string) $args['body'], 0, 2000) : '',
        'created_at' => current_time('mysql'),
    ];

    $formats_map = [
        'user_id' => '%d',
        'endpoint_hash' => '%s',
        'endpoint' => '%s',
        'payload_type' => '%s',
        'payload_json' => '%s',
        'window_key' => '%s',
        'status_code' => '%d',
        'ok' => '%d',
        'response_body' => '%s',
        'created_at' => '%s',
    ];

    $insert = [];
    $formats = [];
    foreach ($row as $column => $value) {
        if (!isset($available[sanitize_key((string) $column)])) {
            continue;
        }
        $insert[$column] = $value;
        $formats[] = $formats_map[$column] ?? '%s';
    }

    if (empty($insert)) {
        return;
    }

    $inserted = $wpdb->insert($table, $insert, $formats);
    if (false === $inserted && peracrm_push_should_log_payload_bodies()) {
        error_log('peracrm_push_log_attempt insert failed: ' . $wpdb->last_error);
    }
}

function peracrm_push_get_recent_log_rows($limit = 10)
{
    global $wpdb;

    $table = function_exists('peracrm_push_log_table_name') ? peracrm_push_log_table_name() : peracrm_table('crm_push_log');
    $available = peracrm_push_get_log_table_columns();
    if ($table === '' || empty($available) || !isset($available['id'])) {
        return [];
    }

    $supported = ['id', 'created_at', 'user_id', 'payload_type', 'status_code', 'ok'];
    $select_columns = [];
    foreach ($supported as $column) {
        if (isset($available[$column])) {
            $select_columns[] = $column;
        }
    }

    if (empty($select_columns)) {
        return [];
    }

    $limit = max(1, min(50, (int) $limit));
    $rows = (array) $wpdb->get_results('SELECT ' . implode(', ', $select_columns) . " FROM {$table} ORDER BY id DESC LIMIT {$limit}", ARRAY_A);

    return array_map(static function ($row) {
        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : 0,
            'payload_type' => isset($row['payload_type']) ? (string) $row['payload_type'] : '',
            'status_code' => isset($row['status_code']) ? (int) $row['status_code'] : 0,
            'ok' => !empty($row['ok']),
        ];
    }, $rows);
}

function peracrm_push_send_to_user($user_id, $payload, $context = [])
{
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return [];
    }

    $subscriptions = peracrm_push_list_user_subscriptions($user_id);
    $results = [];
    $context = is_array($context) ? $context : [];

    foreach ($subscriptions as $subscription) {
        $result = peracrm_push_send_to_subscription($subscription, $payload);
        peracrm_push_log_attempt([
            'user_id' => $user_id,
            'endpoint' => (string) ($subscription['endpoint'] ?? ''),
            'payload_type' => isset($context['payload_type']) ? (string) $context['payload_type'] : (string) ($payload['type'] ?? 'unknown'),
            'payload' => $payload,
            'window_key' => isset($context['window_key']) ? (string) $context['window_key'] : '',
            'ok' => (bool) ($result['ok'] ?? false),
            'status' => (int) ($result['status'] ?? 0),
            'body' => (string) ($result['body'] ?? ''),
        ]);

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
    $summary = [
        'window_start' => '',
        'window_key' => '',
        'now_local' => current_time('mysql'),
        'now_utc' => gmdate('Y-m-d H:i:s'),
        'rows_considered' => 0,
        'pushes_attempted' => 0,
        'pushes_sent' => 0,
        'last_send_error_reason' => '',
        'skipped' => [
            'invalid_user' => 0,
            'zero_pending' => 0,
            'no_subs' => 0,
            'deduped' => 0,
            'not_configured' => 0,
            'table_missing' => 0,
            'send_error' => 0,
        ],
    ];

    if (!peracrm_push_is_configured()) {
        $summary['skipped']['not_configured'] = 1;
        return $summary;
    }

    return peracrm_push_in_target_blog(static function () use ($summary) {
        global $wpdb;

        if (!function_exists('peracrm_reminders_table_exists') || !peracrm_reminders_table_exists()) {
            $summary['skipped']['table_missing'] = 1;
            return $summary;
        }

        $table = peracrm_table('crm_reminders');
        $now = current_time('mysql');
        $window_start = floor((int) current_time('timestamp') / (15 * MINUTE_IN_SECONDS)) * (15 * MINUTE_IN_SECONDS);
        $window_key = peracrm_push_digest_window_key($window_start);
        $summary['window_start'] = wp_date('Y-m-d H:i:s', $window_start, wp_timezone());
        $summary['window_key'] = $window_key;

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

        $summary['rows_considered'] = count($rows);

        foreach ($rows as $row) {
            $advisor_user_id = isset($row['advisor_user_id']) ? (int) $row['advisor_user_id'] : 0;
            $pending_count = isset($row['pending_count']) ? (int) $row['pending_count'] : 0;
            $overdue_count = isset($row['overdue_count']) ? (int) $row['overdue_count'] : 0;

            if ($advisor_user_id <= 0) {
                $summary['skipped']['invalid_user']++;
                continue;
            }

            if ($pending_count <= 0) {
                $summary['skipped']['zero_pending']++;
                continue;
            }

            $subscriptions = peracrm_push_list_user_subscriptions($advisor_user_id);
            if (empty($subscriptions)) {
                $summary['skipped']['no_subs']++;
                continue;
            }

            $digest_hash = md5(implode('|', [$window_key, $pending_count, $overdue_count, $advisor_user_id, wp_date('P', $window_start, wp_timezone())]));
            $meta_key = 'peracrm_push_last_digest_' . $window_key;
            $last_hash = (string) get_user_meta($advisor_user_id, $meta_key, true);
            if ($last_hash !== '' && hash_equals($last_hash, $digest_hash)) {
                $summary['skipped']['deduped']++;
                continue;
            }

            $payload = [
                'type' => 'crm_reminder_digest',
                'title' => 'PeraCRM',
                'body' => sprintf('You have %d pending reminders (%d overdue).', $pending_count, $overdue_count),
                'pending_count' => $pending_count,
                'overdue_count' => $overdue_count,
                'click_url' => peracrm_push_default_click_url(),
            ];

            $results = peracrm_push_send_to_user($advisor_user_id, $payload, [
                'payload_type' => 'digest',
                'window_key' => $window_key,
            ]);

            $summary['pushes_attempted'] += count($results);
            $has_send_error = false;
            foreach ($results as $result) {
                if (!empty($result['ok'])) {
                    $summary['pushes_sent']++;
                    continue;
                }

                $status = isset($result['status']) ? (int) $result['status'] : 0;
                if (!in_array($status, [404, 410], true)) {
                    $has_send_error = true;
                    if ($summary['last_send_error_reason'] === '') {
                        $summary['last_send_error_reason'] = isset($result['body']) ? (string) $result['body'] : '';
                    }
                }
            }

            if ($has_send_error) {
                $summary['skipped']['send_error']++;
            }

            if (count($results) > 0) {
                update_user_meta($advisor_user_id, $meta_key, $digest_hash);
            } else {
                $summary['skipped']['no_subs']++;
            }
        }

        if (peracrm_push_should_log_payload_bodies()) {
            error_log('peracrm_push_run_digest summary: ' . wp_json_encode($summary));
            error_log('peracrm_push_run_digest skipped reasons: ' . wp_json_encode($summary['skipped']));
        }

        return $summary;
    });
}

function peracrm_push_get_cron_health()
{
    $next = (int) wp_next_scheduled('peracrm_push_digest');

    return [
        'event' => 'peracrm_push_digest',
        'next_scheduled' => $next,
        'next_scheduled_local' => $next > 0 ? wp_date('Y-m-d H:i:s', $next, wp_timezone()) : '',
        'disable_wp_cron' => defined('DISABLE_WP_CRON') ? (bool) DISABLE_WP_CRON : false,
    ];
}

function peracrm_push_user_can_run_digest($user_id = null)
{
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }

    return user_can($user_id, 'manage_options') || user_can($user_id, 'edit_crm_clients');
}

function peracrm_push_get_current_window_key()
{
    $window_start = floor((int) current_time('timestamp') / (15 * MINUTE_IN_SECONDS)) * (15 * MINUTE_IN_SECONDS);

    return peracrm_push_digest_window_key($window_start);
}

function peracrm_push_get_debug_data($acting_user_id = null, $target_user_id = null)
{
    global $wpdb;

    $acting_user_id = $acting_user_id ? absint($acting_user_id) : get_current_user_id();
    $target_user_id = $target_user_id ? absint($target_user_id) : $acting_user_id;
    if ($target_user_id <= 0) {
        $target_user_id = $acting_user_id;
    }

    $window_start_ts = floor((int) current_time('timestamp') / (15 * MINUTE_IN_SECONDS)) * (15 * MINUTE_IN_SECONDS);
    $window_key = peracrm_push_digest_window_key($window_start_ts);
    $meta_key = 'peracrm_push_last_digest_' . $window_key;

    $subscriptions = [];
    if (function_exists('peracrm_push_list_user_subscriptions')) {
        $subscriptions = (array) peracrm_push_list_user_subscriptions($target_user_id);
    }

    $subscription_debug = [];
    foreach ($subscriptions as $subscription) {
        $endpoint = isset($subscription['endpoint']) ? (string) $subscription['endpoint'] : '';
        $subscription_debug[] = [
            'endpoint_hash' => $endpoint !== '' ? hash('sha256', $endpoint) : '',
            'last_updated_at' => isset($subscription['last_seen_at']) ? (string) $subscription['last_seen_at'] : (string) ($subscription['created_at'] ?? ''),
        ];
    }

    $pending_count = 0;
    $overdue_count = 0;
    $has_reminders_table = function_exists('peracrm_reminders_table_exists') && peracrm_reminders_table_exists();
    if ($has_reminders_table) {
        $table = peracrm_table('crm_reminders');
        $now = current_time('mysql');
        $counts = (array) $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) AS pending_count,
                        SUM(CASE WHEN due_at < %s THEN 1 ELSE 0 END) AS overdue_count
                 FROM {$table}
                 WHERE status = %s AND advisor_user_id = %d",
                $now,
                'pending',
                $target_user_id
            ),
            ARRAY_A
        );
        $pending_count = isset($counts['pending_count']) ? (int) $counts['pending_count'] : 0;
        $overdue_count = isset($counts['overdue_count']) ? (int) $counts['overdue_count'] : 0;
    }

    $cron_health = function_exists('peracrm_push_get_cron_health') ? peracrm_push_get_cron_health() : [];
    $recent_logs = function_exists('peracrm_push_get_recent_log_rows') ? peracrm_push_get_recent_log_rows(10) : [];

    return [
        'now_local' => current_time('mysql'),
        'now_utc' => gmdate('Y-m-d H:i:s'),
        'wp_timezone_string' => wp_timezone_string(),
        'isConfigured' => peracrm_push_is_configured(),
        'missingReasons' => peracrm_push_missing_config_reasons(),
        'swUrl' => home_url('/peracrm-sw.js'),
        'siteUrl' => site_url('/'),
        'homeUrl' => home_url('/'),
        'restUrl' => rest_url('peracrm/v1/'),
        'clickUrl' => peracrm_push_default_click_url(),
        'acting_user_id' => $acting_user_id,
        'target_user_id' => $target_user_id,
        'subs_count' => count($subscriptions),
        'subscriptions' => $subscription_debug,
        'reminders' => [
            'pending' => $pending_count,
            'overdue' => $overdue_count,
            'table_exists' => $has_reminders_table,
        ],
        'cron' => is_array($cron_health) ? $cron_health : [],
        'digest_window_start' => wp_date('Y-m-d H:i:s', $window_start_ts, wp_timezone()),
        'digest_window_key' => $window_key,
        'last_digest_meta_key' => $meta_key,
        'last_digest_meta' => (string) get_user_meta($target_user_id, $meta_key, true),
        'push_log_recent' => is_array($recent_logs) ? $recent_logs : [],
        'self_check' => [
            'has_push_log_reader' => function_exists('peracrm_push_get_recent_log_rows'),
            'has_push_sender' => function_exists('peracrm_push_send_to_user'),
            'has_digest_runner' => function_exists('peracrm_push_run_digest_for_current_window'),
            'has_reminders_table_check' => function_exists('peracrm_reminders_table_exists'),
            'has_webpush_class' => class_exists('Minishlink\WebPush\WebPush'),
            'has_subscription_class' => class_exists('Minishlink\WebPush\Subscription'),
        ],
    ];
}

function peracrm_push_debug_snapshot($user_id = null)
{
    $user_id = $user_id ? absint($user_id) : get_current_user_id();

    return [
        'can_run_digest' => peracrm_push_user_can_run_digest($user_id),
        'cron' => peracrm_push_get_cron_health(),
        'window_key' => peracrm_push_get_current_window_key(),
    ];
}

function peracrm_push_run_digest_for_current_window()
{
    return peracrm_push_run_digest();
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
        'click_url' => peracrm_push_default_click_url(),
    ];

    $results = peracrm_push_in_target_blog(static function () use ($payload) {
        return peracrm_push_send_to_user(get_current_user_id(), $payload, ['payload_type' => 'test']);
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

    $default_click_url_json = wp_json_encode(peracrm_push_default_click_url());

    echo "const DEFAULT_CLICK_URL = " . $default_click_url_json . ";\n";
    echo "self.addEventListener('push', function(event) {\n";
    echo "  var payload = {};\n";
    echo "  var textPayload = '';\n";
    echo "  if (event.data) {\n";
    echo "    try { payload = event.data.json() || {}; } catch (jsonErr) {\n";
    echo "      try { textPayload = event.data.text() || ''; } catch (textErr) { textPayload = ''; }\n";
    echo "      if (textPayload) {\n";
    echo "        try { payload = JSON.parse(textPayload); } catch (parseErr) { payload = { body: textPayload }; }\n";
    echo "      }\n";
    echo "    }\n";
    echo "  }\n";
    echo "  var title = payload.title || 'PeraCRM';\n";
    echo "  var body = payload.body || '';\n";
    echo "  if (!body) {\n";
    echo "    var overdue = Number(payload.overdue_count || 0);\n";
    echo "    var pending = Number(payload.pending_count || 0);\n";
    echo "    if (overdue > 0 || pending > 0) {\n";
    echo "      body = overdue > 0 ? 'You have ' + overdue + ' overdue reminders.' : 'You have ' + pending + ' pending reminders.';\n";
    echo "    } else {\n";
    echo "      body = 'You have a new CRM reminder update.';\n";
    echo "    }\n";
    echo "  }\n";
    echo "  var clickUrl = payload.click_url || (payload.data && payload.data.click_url) || DEFAULT_CLICK_URL;\n";
    echo "  var iconUrl = payload.icon || '/wp-content/uploads/2024/03/cropped-favicon-192x192.png';\n";
    echo "  var badgeUrl = payload.badge || '/wp-content/uploads/2024/03/cropped-favicon-192x192.png';\n";
    echo "  event.waitUntil(self.registration.showNotification(title, { body: body, icon: iconUrl, badge: badgeUrl, data: { click_url: clickUrl } }));\n";
    echo "});\n";
    echo "self.addEventListener('notificationclick', function(event) {\n";
    echo "  event.notification.close();\n";
    echo "  var clickUrl = (event.notification.data && event.notification.data.click_url) ? event.notification.data.click_url : DEFAULT_CLICK_URL;\n";
    echo "  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(windowClients) {\n";
    echo "    for (var i = 0; i < windowClients.length; i++) {\n";
    echo "      var client = windowClients[i];\n";
    echo "      if (client.url && client.url.indexOf(clickUrl) !== -1 && 'focus' in client) { return client.focus(); }\n";
    echo "    }\n";
    echo "    if (clients.openWindow) { return clients.openWindow(clickUrl); }\n";
    echo "    return undefined;\n";
    echo "  }));\n";
    echo "});\n";
    exit;
}
add_action('init', 'peracrm_push_render_service_worker', 1);
