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

    $result = peracrm_with_target_blog(static function () use ($wpdb, $party_id, $title, $stage, $data, $now, $closed_at) {
        $table = peracrm_table('peracrm_deals');
        $inserted = $wpdb->insert(
            $table,
            [
                'party_id' => $party_id,
                'title' => $title,
                'primary_property_id' => !empty($data['primary_property_id']) ? (int) $data['primary_property_id'] : null,
                'stage' => $stage,
                'deal_value' => isset($data['deal_value']) && $data['deal_value'] !== '' ? (float) $data['deal_value'] : null,
                'currency' => strtoupper(substr(sanitize_text_field((string) ($data['currency'] ?? 'USD')), 0, 3)),
                'expected_close_date' => !empty($data['expected_close_date']) ? sanitize_text_field((string) $data['expected_close_date']) : null,
                'closed_at' => $closed_at,
                'owner_user_id' => !empty($data['owner_user_id']) ? (int) $data['owner_user_id'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%s',
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

    $updated = peracrm_with_target_blog(static function () use ($wpdb, $deal_id, $title, $stage, $data, $closed_at, $now) {
        $table = peracrm_table('peracrm_deals');
        return $wpdb->update(
            $table,
            [
                'title' => $title,
                'stage' => $stage,
                'primary_property_id' => isset($data['primary_property_id']) ? (int) $data['primary_property_id'] : null,
                'deal_value' => isset($data['deal_value']) && $data['deal_value'] !== '' ? (float) $data['deal_value'] : null,
                'currency' => isset($data['currency']) ? strtoupper(substr(sanitize_text_field((string) $data['currency']), 0, 3)) : 'USD',
                'expected_close_date' => isset($data['expected_close_date']) ? sanitize_text_field((string) $data['expected_close_date']) : null,
                'closed_at' => $closed_at !== '' ? $closed_at : null,
                'owner_user_id' => isset($data['owner_user_id']) ? (int) $data['owner_user_id'] : null,
                'updated_at' => $now,
            ],
            ['id' => $deal_id],
            ['%s', '%s', '%d', '%f', '%s', '%s', '%s', '%d', '%s'],
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
        'deal_value' => isset($row['deal_value']) ? $row['deal_value'] : null,
        'currency' => isset($row['currency']) ? (string) $row['currency'] : 'USD',
        'expected_close_date' => isset($row['expected_close_date']) ? (string) $row['expected_close_date'] : '',
        'closed_at' => isset($row['closed_at']) ? (string) $row['closed_at'] : '',
        'owner_user_id' => isset($row['owner_user_id']) ? (int) $row['owner_user_id'] : 0,
        'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
        'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
    ];
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
