<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_client_property_link($client_id, $property_id, $relation_type)
{
    if (!peracrm_client_property_table_exists()) {
        return false;
    }

    global $wpdb;

    $table = peracrm_table('crm_client_property');

    $query = $wpdb->prepare(
        "INSERT INTO {$table} (client_id, property_id, relation_type, created_at)
         VALUES (%d, %d, %s, %s)
         ON DUPLICATE KEY UPDATE created_at = created_at",
        (int) $client_id,
        (int) $property_id,
        sanitize_key($relation_type),
        peracrm_now_mysql()
    );

    $result = $wpdb->query($query);

    return $result !== false;
}

function peracrm_client_property_unlink($client_id, $property_id, $relation_type)
{
    if (!peracrm_client_property_table_exists()) {
        return false;
    }

    global $wpdb;

    $table = peracrm_table('crm_client_property');

    $result = $wpdb->delete(
        $table,
        [
            'client_id' => (int) $client_id,
            'property_id' => (int) $property_id,
            'relation_type' => sanitize_key($relation_type),
        ],
        [
            '%d',
            '%d',
            '%s',
        ]
    );

    return $result !== false;
}

function peracrm_client_property_list($client_id, $relation_type, $limit = 200)
{
    if (!peracrm_client_property_table_exists()) {
        return [];
    }

    global $wpdb;

    $table = peracrm_table('crm_client_property');

    $query = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE client_id = %d AND relation_type = %s ORDER BY created_at DESC LIMIT %d",
        (int) $client_id,
        sanitize_key($relation_type),
        (int) $limit
    );

    return $wpdb->get_results($query, ARRAY_A);
}


function peracrm_client_property_get_row($client_id, $property_id, $relation_type)
{
    if (!peracrm_client_property_table_exists()) {
        return null;
    }

    global $wpdb;

    $table = peracrm_table('crm_client_property');

    $query = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE client_id = %d AND property_id = %d AND relation_type = %s LIMIT 1",
        (int) $client_id,
        (int) $property_id,
        sanitize_key($relation_type)
    );

    $row = $wpdb->get_row($query, ARRAY_A);

    return is_array($row) ? $row : null;
}

function peracrm_client_property_update_portfolio_fields($client_id, $property_id, $fields)
{
    if (!peracrm_client_property_table_exists()) {
        return false;
    }

    $client_id = (int) $client_id;
    $property_id = (int) $property_id;

    if ($client_id <= 0 || $property_id <= 0 || !is_array($fields) || empty($fields)) {
        return false;
    }

    $allowed_keys = [
        'floor_number',
        'net_size',
        'gross_size',
        'list_price',
        'cash_price',
    ];

    $set_parts = [];
    $params = [];

    foreach ($allowed_keys as $key) {
        if (!array_key_exists($key, $fields)) {
            continue;
        }

        if ($fields[$key] === null) {
            $set_parts[] = "{$key} = NULL";
            continue;
        }

        $set_parts[] = "{$key} = %s";
        $params[] = (string) $fields[$key];
    }

    if (empty($set_parts)) {
        return false;
    }

    global $wpdb;

    $table = peracrm_table('crm_client_property');
    $query = "UPDATE {$table} SET " . implode(', ', $set_parts) . " WHERE client_id = %d AND property_id = %d AND relation_type = %s";
    $params[] = $client_id;
    $params[] = $property_id;
    $params[] = 'portfolio';

    $result = $wpdb->query($wpdb->prepare($query, $params));

    if ($result === false) {
        return false;
    }

    if ($result > 0) {
        return true;
    }

    $existing = peracrm_client_property_get_row($client_id, $property_id, 'portfolio');

    return is_array($existing);
}
function peracrm_client_property_table_exists()
{
    global $wpdb;

    static $exists = null;
    if (null !== $exists) {
        return $exists;
    }

    $table = peracrm_table('crm_client_property');
    $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
    $exists = $wpdb->get_var($query) === $table;

    return $exists;
}
