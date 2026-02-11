<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_deal_stage_options()
{
    return [
        'qualified' => 'Qualified',
        'reserved' => 'Reserved',
        'contract_signed' => 'Contract signed',
        'closed_won' => 'Closed won',
        'closed_lost' => 'Closed lost',
    ];
}

function peracrm_deals_commission_type_options()
{
    return ['percent', 'fixed'];
}

function peracrm_deals_commission_status_options()
{
    return ['expected', 'invoiced', 'received', 'void'];
}

function peracrm_deals_sanitize_commission_type($value)
{
    $value = sanitize_key((string) $value);
    return in_array($value, peracrm_deals_commission_type_options(), true) ? $value : 'percent';
}

function peracrm_deals_sanitize_decimal($value, $max = null)
{
    if ($value === null || $value === '') {
        return null;
    }

    $value = is_numeric($value) ? (float) $value : null;
    if ($value === null || $value < 0) {
        return null;
    }

    if ($max !== null) {
        $value = min((float) $max, $value);
    }

    return $value;
}

function peracrm_deals_sanitize_currency($value, $default = 'USD')
{
    $value = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $value), 0, 3));
    if (strlen($value) !== 3) {
        return $default;
    }

    return $value;
}

function peracrm_deals_sanitize_commission_status($value)
{
    $value = sanitize_key((string) $value);
    return in_array($value, peracrm_deals_commission_status_options(), true) ? $value : 'expected';
}

function peracrm_deals_sanitize_date($value)
{
    $value = sanitize_text_field((string) $value);
    if ($value === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return null;
    }

    return $value;
}

function peracrm_deals_sanitize_datetime($value)
{
    $value = sanitize_text_field((string) $value);
    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    if (!$dt || $dt->format('Y-m-d H:i:s') !== $value) {
        return null;
    }

    return $value;
}

function peracrm_deals_sanitize_commission_payload(array $data, array $existing = [])
{
    $commission_type = array_key_exists('commission_type', $data)
        ? peracrm_deals_sanitize_commission_type($data['commission_type'])
        : peracrm_deals_sanitize_commission_type($existing['commission_type'] ?? 'percent');

    $commission_rate = null;
    if ($commission_type === 'percent') {
        if (array_key_exists('commission_rate', $data)) {
            $input_percent = peracrm_deals_sanitize_decimal($data['commission_rate'], 100.0);
            if ($input_percent !== null) {
                $commission_rate = peracrm_deals_sanitize_decimal($input_percent / 100, 1.0);
            }
        } else {
            $commission_rate = peracrm_deals_sanitize_decimal($existing['commission_rate'] ?? null, 1.0);
        }
    }

    $raw_commission_amount = array_key_exists('commission_amount', $data)
        ? $data['commission_amount']
        : ($existing['commission_amount'] ?? null);

    $raw_commission_currency = array_key_exists('commission_currency', $data)
        ? $data['commission_currency']
        : ($existing['commission_currency'] ?? ($data['currency'] ?? ($existing['currency'] ?? 'USD')));

    $raw_commission_status = array_key_exists('commission_status', $data)
        ? $data['commission_status']
        : ($existing['commission_status'] ?? 'expected');

    $raw_commission_due_date = array_key_exists('commission_due_date', $data)
        ? $data['commission_due_date']
        : ($existing['commission_due_date'] ?? null);

    $raw_commission_paid_at = array_key_exists('commission_paid_at', $data)
        ? $data['commission_paid_at']
        : ($existing['commission_paid_at'] ?? null);

    $raw_commission_notes = array_key_exists('commission_notes', $data)
        ? $data['commission_notes']
        : ($existing['commission_notes'] ?? '');

    return [
        'commission_type' => $commission_type,
        'commission_rate' => $commission_rate,
        'commission_amount' => peracrm_deals_sanitize_decimal($raw_commission_amount),
        'commission_currency' => peracrm_deals_sanitize_currency($raw_commission_currency),
        'commission_status' => peracrm_deals_sanitize_commission_status($raw_commission_status),
        'commission_due_date' => peracrm_deals_sanitize_date($raw_commission_due_date),
        'commission_paid_at' => peracrm_deals_sanitize_datetime($raw_commission_paid_at),
        'commission_notes' => sanitize_textarea_field((string) $raw_commission_notes),
    ];
}

function peracrm_deals_table_exists()
{
    global $wpdb;

    static $exists = null;
    if (null !== $exists) {
        return $exists;
    }

    $exists = peracrm_with_target_blog(static function () use ($wpdb) {
        $table = peracrm_table('peracrm_deals');
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
        return $wpdb->get_var($query) === $table;
    });

    return $exists;
}

function peracrm_deal_sanitize_stage($stage)
{
    $stage = sanitize_key((string) $stage);
    $allowed = peracrm_deal_stage_options();

    return isset($allowed[$stage]) ? $stage : 'qualified';
}

function peracrm_deal_is_closed_stage($stage)
{
    return in_array($stage, ['closed_won', 'closed_lost'], true);
}

function peracrm_deals_create(array $data)
{
    if (!peracrm_deals_table_exists()) {
        return 0;
    }

    global $wpdb;

    $party_id = (int) ($data['party_id'] ?? 0);
    if ($party_id <= 0) {
        return 0;
    }

    $title = sanitize_text_field((string) ($data['title'] ?? ''));
    if ($title === '') {
        return 0;
    }

    $stage = peracrm_deal_sanitize_stage($data['stage'] ?? 'qualified');
    $now = peracrm_now_mysql();
    $closed_at = !empty($data['closed_at']) ? sanitize_text_field((string) $data['closed_at']) : null;
    if (null === $closed_at && peracrm_deal_is_closed_stage($stage)) {
        $closed_at = $now;
    }

    $commission = peracrm_deals_sanitize_commission_payload($data, []);
    if ($commission['commission_status'] === 'received' && empty($commission['commission_paid_at'])) {
        $commission['commission_paid_at'] = current_time('mysql');
    }

    $result = peracrm_with_target_blog(static function () use ($wpdb, $party_id, $title, $stage, $data, $now, $closed_at, $commission) {
        $table = peracrm_table('peracrm_deals');
        $inserted = $wpdb->insert(
            $table,
            [
                'party_id' => $party_id,
                'title' => $title,
                'primary_property_id' => !empty($data['primary_property_id']) ? (int) $data['primary_property_id'] : null,
                'stage' => $stage,
                'deal_value' => isset($data['deal_value']) && $data['deal_value'] !== '' ? (float) $data['deal_value'] : null,
                'currency' => peracrm_deals_sanitize_currency($data['currency'] ?? 'USD'),
                'commission_type' => $commission['commission_type'],
                'commission_rate' => $commission['commission_rate'],
                'commission_amount' => $commission['commission_amount'],
                'commission_currency' => $commission['commission_currency'],
                'commission_status' => $commission['commission_status'],
                'commission_due_date' => $commission['commission_due_date'],
                'commission_paid_at' => $commission['commission_paid_at'],
                'commission_notes' => $commission['commission_notes'],
                'expected_close_date' => !empty($data['expected_close_date']) ? sanitize_text_field((string) $data['expected_close_date']) : null,
                'closed_at' => $closed_at,
                'owner_user_id' => !empty($data['owner_user_id']) ? (int) $data['owner_user_id'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d', '%s', '%d', '%s', '%f', '%s',
                '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%d', '%s', '%s',
            ]
        );

        if (false === $inserted) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    });

    return (int) $result;
}

function peracrm_deals_update($deal_id, array $data)
{
    $deal_id = (int) $deal_id;
    if ($deal_id <= 0 || !peracrm_deals_table_exists()) {
        return false;
    }

    $deal = peracrm_deals_get($deal_id);
    if (!$deal) {
        return false;
    }

    global $wpdb;

    $title = isset($data['title']) ? sanitize_text_field((string) $data['title']) : $deal['title'];
    $stage = isset($data['stage']) ? peracrm_deal_sanitize_stage($data['stage']) : $deal['stage'];
    $now = peracrm_now_mysql();

    $closed_at = $deal['closed_at'];
    if (peracrm_deal_is_closed_stage($stage) && $closed_at === '') {
        $closed_at = $now;
    }

    $commission = peracrm_deals_sanitize_commission_payload($data, $deal);
    if (
        $deal['commission_status'] !== 'received'
        && $commission['commission_status'] === 'received'
        && empty($commission['commission_paid_at'])
    ) {
        $commission['commission_paid_at'] = current_time('mysql');
    }

    $updated = peracrm_with_target_blog(static function () use ($wpdb, $deal_id, $title, $stage, $data, $closed_at, $now, $commission) {
        $table = peracrm_table('peracrm_deals');
        return $wpdb->update(
            $table,
            [
                'title' => $title,
                'stage' => $stage,
                'primary_property_id' => isset($data['primary_property_id']) ? (int) $data['primary_property_id'] : null,
                'deal_value' => isset($data['deal_value']) && $data['deal_value'] !== '' ? (float) $data['deal_value'] : null,
                'currency' => isset($data['currency']) ? peracrm_deals_sanitize_currency($data['currency']) : 'USD',
                'commission_type' => $commission['commission_type'],
                'commission_rate' => $commission['commission_rate'],
                'commission_amount' => $commission['commission_amount'],
                'commission_currency' => $commission['commission_currency'],
                'commission_status' => $commission['commission_status'],
                'commission_due_date' => $commission['commission_due_date'],
                'commission_paid_at' => $commission['commission_paid_at'],
                'commission_notes' => $commission['commission_notes'],
                'expected_close_date' => isset($data['expected_close_date']) ? sanitize_text_field((string) $data['expected_close_date']) : null,
                'closed_at' => $closed_at !== '' ? $closed_at : null,
                'owner_user_id' => isset($data['owner_user_id']) ? (int) $data['owner_user_id'] : null,
                'updated_at' => $now,
            ],
            ['id' => $deal_id],
            ['%s', '%s', '%d', '%f', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'],
            ['%d']
        );
    });

    return false !== $updated;
}

function peracrm_deals_get($deal_id)
{
    $deal_id = (int) $deal_id;
    if ($deal_id <= 0 || !peracrm_deals_table_exists()) {
        return null;
    }

    global $wpdb;

    $row = peracrm_with_target_blog(static function () use ($wpdb, $deal_id) {
        $table = peracrm_table('peracrm_deals');
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $deal_id);
        return $wpdb->get_row($query, ARRAY_A);
    });

    if (!is_array($row)) {
        return null;
    }

    return peracrm_deal_normalize_row($row);
}

function peracrm_deals_get_by_party($party_id)
{
    $party_id = (int) $party_id;
    if ($party_id <= 0 || !peracrm_deals_table_exists()) {
        return [];
    }

    global $wpdb;

    $rows = peracrm_with_target_blog(static function () use ($wpdb, $party_id) {
        $table = peracrm_table('peracrm_deals');
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE party_id = %d ORDER BY created_at DESC",
            $party_id
        );

        return $wpdb->get_results($query, ARRAY_A);
    });

    return array_map('peracrm_deal_normalize_row', $rows);
}

function peracrm_deal_normalize_row(array $row)
{
    return [
        'id' => (int) $row['id'],
        'party_id' => (int) $row['party_id'],
        'title' => (string) $row['title'],
        'primary_property_id' => isset($row['primary_property_id']) ? (int) $row['primary_property_id'] : 0,
        'stage' => peracrm_deal_sanitize_stage($row['stage']),
        'deal_value' => isset($row['deal_value']) && $row['deal_value'] !== null ? (float) $row['deal_value'] : null,
        'currency' => isset($row['currency']) ? (string) $row['currency'] : 'USD',
        'commission_type' => peracrm_deals_sanitize_commission_type($row['commission_type'] ?? 'percent'),
        'commission_rate' => isset($row['commission_rate']) && $row['commission_rate'] !== null ? (float) $row['commission_rate'] : null,
        'commission_amount' => isset($row['commission_amount']) && $row['commission_amount'] !== null ? (float) $row['commission_amount'] : null,
        'commission_currency' => peracrm_deals_sanitize_currency($row['commission_currency'] ?? 'USD'),
        'commission_status' => peracrm_deals_sanitize_commission_status($row['commission_status'] ?? 'expected'),
        'commission_due_date' => isset($row['commission_due_date']) ? (string) $row['commission_due_date'] : '',
        'commission_paid_at' => isset($row['commission_paid_at']) ? (string) $row['commission_paid_at'] : '',
        'commission_notes' => isset($row['commission_notes']) ? (string) $row['commission_notes'] : '',
        'expected_close_date' => isset($row['expected_close_date']) ? (string) $row['expected_close_date'] : '',
        'closed_at' => isset($row['closed_at']) ? (string) $row['closed_at'] : '',
        'owner_user_id' => isset($row['owner_user_id']) ? (int) $row['owner_user_id'] : 0,
        'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
        'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
    ];
}

function peracrm_deals_calculate_expected_commission(array $deal_row)
{
    $commission_amount = isset($deal_row['commission_amount']) ? peracrm_deals_sanitize_decimal($deal_row['commission_amount']) : null;
    if ($commission_amount !== null) {
        return (float) $commission_amount;
    }

    $commission_type = peracrm_deals_sanitize_commission_type($deal_row['commission_type'] ?? 'percent');
    $commission_rate = isset($deal_row['commission_rate']) ? peracrm_deals_sanitize_decimal($deal_row['commission_rate'], 1.0) : null;
    $deal_value = isset($deal_row['deal_value']) && is_numeric($deal_row['deal_value']) ? (float) $deal_row['deal_value'] : null;

    if ($commission_type === 'percent' && $commission_rate !== null && $deal_value !== null) {
        return (float) ($deal_value * $commission_rate);
    }

    return 0.0;
}

function peracrm_deals_get_commission_totals(array $args = [])
{
    if (!peracrm_deals_table_exists()) {
        return [
            'expected_total' => [],
            'invoiced_total' => [],
            'received_mtd_total' => [],
            'received_ytd_total' => [],
        ];
    }

    global $wpdb;

    return peracrm_with_target_blog(static function () use ($wpdb, $args) {
        $table = peracrm_table('peracrm_deals');
        $owner_user_id = isset($args['owner_user_id']) ? (int) $args['owner_user_id'] : 0;
        $date_from = peracrm_deals_sanitize_date($args['date_from'] ?? null);
        $date_to = peracrm_deals_sanitize_date($args['date_to'] ?? null);

        $expected_total = [];
        $invoiced_total = [];
        $received_mtd_total = [];
        $received_ytd_total = [];

        $where = ["commission_status IN ('expected','invoiced')"];
        $params = [];

        if ($owner_user_id > 0) {
            $where[] = 'owner_user_id = %d';
            $params[] = $owner_user_id;
        }

        $expected_expr = "CASE WHEN commission_amount IS NOT NULL THEN commission_amount WHEN commission_type = 'percent' AND commission_rate IS NOT NULL AND deal_value IS NOT NULL THEN (deal_value * commission_rate) ELSE 0 END";

        $query_expected = "SELECT commission_currency, commission_status, SUM({$expected_expr}) AS total FROM {$table} WHERE " . implode(' AND ', $where) . ' GROUP BY commission_currency, commission_status';
        if (!empty($params)) {
            $query_expected = $wpdb->prepare($query_expected, ...$params);
        }

        $rows_expected = $wpdb->get_results($query_expected, ARRAY_A);
        foreach ($rows_expected as $row) {
            $currency = peracrm_deals_sanitize_currency($row['commission_currency'] ?? 'USD');
            $total = (float) ($row['total'] ?? 0);
            $expected_total[$currency] = ($expected_total[$currency] ?? 0.0) + $total;
            if (($row['commission_status'] ?? '') === 'invoiced') {
                $invoiced_total[$currency] = ($invoiced_total[$currency] ?? 0.0) + $total;
            }
        }

        $ts = current_time('timestamp');
        $month_start = wp_date('Y-m-01 00:00:00', $ts);
        $month_end = wp_date('Y-m-t 23:59:59', $ts);
        $year_start = wp_date('Y-01-01 00:00:00', $ts);
        $year_end = wp_date('Y-12-31 23:59:59', $ts);

        $received_mtd_total = peracrm_deals_query_received_total_by_currency($table, $owner_user_id, $date_from, $date_to, $month_start, $month_end);
        $received_ytd_total = peracrm_deals_query_received_total_by_currency($table, $owner_user_id, $date_from, $date_to, $year_start, $year_end);

        return [
            'expected_total' => $expected_total,
            'invoiced_total' => $invoiced_total,
            'received_mtd_total' => $received_mtd_total,
            'received_ytd_total' => $received_ytd_total,
        ];
    });
}

function peracrm_deals_query_received_total_by_currency($table, $owner_user_id, $date_from, $date_to, $range_start, $range_end)
{
    global $wpdb;

    $expected_expr = "CASE WHEN commission_amount IS NOT NULL THEN commission_amount WHEN commission_type = 'percent' AND commission_rate IS NOT NULL AND deal_value IS NOT NULL THEN (deal_value * commission_rate) ELSE 0 END";

    $where = [
        'commission_status = %s',
        'commission_paid_at IS NOT NULL',
        'commission_paid_at >= %s',
        'commission_paid_at <= %s',
    ];
    $params = ['received', $range_start, $range_end];

    if ($owner_user_id > 0) {
        $where[] = 'owner_user_id = %d';
        $params[] = (int) $owner_user_id;
    }

    if ($date_from !== null) {
        $where[] = 'commission_paid_at >= %s';
        $params[] = $date_from . ' 00:00:00';
    }

    if ($date_to !== null) {
        $where[] = 'commission_paid_at <= %s';
        $params[] = $date_to . ' 23:59:59';
    }

    $query = "SELECT commission_currency, SUM({$expected_expr}) AS total FROM {$table} WHERE " . implode(' AND ', $where) . ' GROUP BY commission_currency';
    $query = $wpdb->prepare($query, ...$params);

    $rows = $wpdb->get_results($query, ARRAY_A);
    $totals = [];
    foreach ($rows as $row) {
        $currency = peracrm_deals_sanitize_currency($row['commission_currency'] ?? 'USD');
        $totals[$currency] = (float) ($row['total'] ?? 0);
    }

    return $totals;
}


function peracrm_deals_get_received_ytd_by_advisor(array $args = [])
{
    if (!peracrm_deals_table_exists()) {
        return [];
    }

    global $wpdb;

    return peracrm_with_target_blog(static function () use ($wpdb, $args) {
        $table = peracrm_table('peracrm_deals');

        $ts = current_time('timestamp');
        $year_start = wp_date('Y-01-01 00:00:00', $ts);
        $year_end = wp_date('Y-12-31 23:59:59', $ts);

        $date_from = peracrm_deals_sanitize_date($args['date_from'] ?? null);
        $date_to = peracrm_deals_sanitize_date($args['date_to'] ?? null);

        $expected_expr = "CASE WHEN commission_amount IS NOT NULL THEN commission_amount WHEN commission_type = 'percent' AND commission_rate IS NOT NULL AND deal_value IS NOT NULL THEN (deal_value * commission_rate) ELSE 0 END";

        $where = [
            'commission_status = %s',
            'commission_paid_at IS NOT NULL',
            'commission_paid_at >= %s',
            'commission_paid_at <= %s',
            'owner_user_id IS NOT NULL',
            'owner_user_id > 0',
        ];
        $params = ['received', $year_start, $year_end];

        if ($date_from !== null) {
            $where[] = 'commission_paid_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_to !== null) {
            $where[] = 'commission_paid_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $query = "SELECT owner_user_id, commission_currency, SUM({$expected_expr}) AS total FROM {$table} WHERE " . implode(' AND ', $where) . ' GROUP BY owner_user_id, commission_currency';
        $query = $wpdb->prepare($query, ...$params);

        $rows = $wpdb->get_results($query, ARRAY_A);
        $totals = [];

        foreach ($rows as $row) {
            $owner_user_id = (int) ($row['owner_user_id'] ?? 0);
            if ($owner_user_id <= 0) {
                continue;
            }

            $currency = peracrm_deals_sanitize_currency($row['commission_currency'] ?? 'USD');
            if (!isset($totals[$owner_user_id])) {
                $totals[$owner_user_id] = [];
            }

            $totals[$owner_user_id][$currency] = (float) ($row['total'] ?? 0);
        }

        return $totals;
    });
}

function peracrm_party_is_client($party_id)
{
    $party_id = (int) $party_id;
    if ($party_id <= 0 || !peracrm_deals_table_exists()) {
        return false;
    }

    global $wpdb;

    $count = peracrm_with_target_blog(static function () use ($wpdb, $party_id) {
        $table = peracrm_table('peracrm_deals');
        $query = $wpdb->prepare(
            "SELECT party_id FROM {$table} WHERE party_id = %d AND stage = %s LIMIT 1",
            $party_id,
            'closed_won'
        );

        return $wpdb->get_var($query);
    });

    return !empty($count);
}

function peracrm_deals_count_by_stage()
{
    if (!peracrm_deals_table_exists()) {
        return [];
    }

    global $wpdb;

    return peracrm_with_target_blog(static function () use ($wpdb) {
        $table = peracrm_table('peracrm_deals');
        $rows = $wpdb->get_results(
            "SELECT stage, COUNT(*) AS total FROM {$table} GROUP BY stage",
            ARRAY_A
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['stage']] = (int) $row['total'];
        }

        return $counts;
    });
}

function peracrm_count_distinct_clients_from_deals()
{
    if (!peracrm_deals_table_exists()) {
        return 0;
    }

    global $wpdb;

    return (int) peracrm_with_target_blog(static function () use ($wpdb) {
        $table = peracrm_table('peracrm_deals');
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT party_id) FROM {$table} WHERE stage = %s",
            'closed_won'
        );

        return (int) $wpdb->get_var($query);
    });
}
