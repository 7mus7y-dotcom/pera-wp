<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_party_stage_options()
{
    return function_exists('peracrm_lead_stage_options')
        ? peracrm_lead_stage_options()
        : [
            'new_enquiry' => 'New enquiry',
            'contacted' => 'Contacted',
            'qualified' => 'Qualified',
            'viewing_arranged' => 'Viewing arranged',
            'offer_made' => 'Offer made',
            'negotiation' => 'Negotiation',
        ];
}

function peracrm_party_engagement_options()
{
    return [
        'engaged' => 'Engaged',
        'dormant' => 'Dormant',
        'closed' => 'Closed',
    ];
}

function peracrm_party_disposition_options()
{
    return [
        'none' => 'None',
        'junk_lead' => 'Junk lead',
        'duplicate' => 'Duplicate',
    ];
}

function peracrm_party_table_exists()
{
    global $wpdb;

    static $exists = null;
    if (null !== $exists) {
        return $exists;
    }

    $exists = peracrm_with_target_blog(static function () use ($wpdb) {
        $table = peracrm_table('peracrm_party');
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
        return $wpdb->get_var($query) === $table;
    });

    return $exists;
}

function peracrm_party_sanitize_stage($value)
{
    $value = function_exists('peracrm_map_legacy_lead_stage')
        ? peracrm_map_legacy_lead_stage($value)
        : sanitize_key((string) $value);
    $allowed = peracrm_party_stage_options();

    return isset($allowed[$value]) ? $value : 'new_enquiry';
}

function peracrm_party_sanitize_engagement($value)
{
    $value = sanitize_key((string) $value);
    $allowed = peracrm_party_engagement_options();

    if ($value === 'closed') {
        return 'closed';
    }

    return isset($allowed[$value]) ? $value : 'engaged';
}

function peracrm_party_sanitize_disposition($value)
{
    $value = sanitize_key((string) $value);
    if ($value === 'junk') {
        $value = 'junk_lead';
    }

    $allowed = peracrm_party_disposition_options();

    return isset($allowed[$value]) ? $value : 'none';
}

function peracrm_party_default_status()
{
    return [
        'party_id' => 0,
        'lead_pipeline_stage' => 'new_enquiry',
        'engagement_state' => 'engaged',
        'disposition' => 'none',
        'lead_stage_updated_at' => '',
        'updated_at' => '',
    ];
}

function peracrm_party_get($party_id)
{
    $party_id = (int) $party_id;
    if ($party_id <= 0 || !peracrm_party_table_exists()) {
        return peracrm_party_default_status();
    }

    global $wpdb;

    $row = peracrm_with_target_blog(static function () use ($wpdb, $party_id) {
        $table = peracrm_table('peracrm_party');
        $query = $wpdb->prepare(
            "SELECT party_id, lead_pipeline_stage, engagement_state, disposition, lead_stage_updated_at, updated_at
             FROM {$table}
             WHERE party_id = %d
             LIMIT 1",
            $party_id
        );

        return $wpdb->get_row($query, ARRAY_A);
    });

    if (!is_array($row)) {
        $default = peracrm_party_default_status();
        $default['party_id'] = $party_id;
        return $default;
    }

    return [
        'party_id' => (int) $row['party_id'],
        'lead_pipeline_stage' => peracrm_party_sanitize_stage($row['lead_pipeline_stage']),
        'engagement_state' => peracrm_party_sanitize_engagement($row['engagement_state']),
        'disposition' => peracrm_party_sanitize_disposition($row['disposition']),
        'lead_stage_updated_at' => isset($row['lead_stage_updated_at']) ? (string) $row['lead_stage_updated_at'] : '',
        'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
    ];
}

function peracrm_party_upsert_status($party_id, array $data)
{
    $party_id = (int) $party_id;
    if ($party_id <= 0 || !peracrm_party_table_exists()) {
        return false;
    }

    global $wpdb;

    $stage = peracrm_party_sanitize_stage($data['lead_pipeline_stage'] ?? 'new_enquiry');
    $engagement = peracrm_party_sanitize_engagement($data['engagement_state'] ?? 'engaged');
    $disposition = peracrm_party_sanitize_disposition($data['disposition'] ?? 'none');

    $updated_at = peracrm_now_mysql();
    $lead_stage_updated_at = isset($data['lead_stage_updated_at']) && $data['lead_stage_updated_at'] !== ''
        ? sanitize_text_field($data['lead_stage_updated_at'])
        : $updated_at;

    $result = peracrm_with_target_blog(static function () use ($wpdb, $party_id, $stage, $engagement, $disposition, $updated_at, $lead_stage_updated_at) {
        $table = peracrm_table('peracrm_party');

        $query = $wpdb->prepare(
            "INSERT INTO {$table} (party_id, lead_pipeline_stage, engagement_state, disposition, lead_stage_updated_at, updated_at)
             VALUES (%d, %s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                lead_pipeline_stage = VALUES(lead_pipeline_stage),
                engagement_state = VALUES(engagement_state),
                disposition = VALUES(disposition),
                lead_stage_updated_at = VALUES(lead_stage_updated_at),
                updated_at = VALUES(updated_at)",
            $party_id,
            $stage,
            $engagement,
            $disposition,
            $lead_stage_updated_at,
            $updated_at
        );

        return $wpdb->query($query);
    });

    return false !== $result;
}

function peracrm_party_batch_get_closed_won_client_ids(array $party_ids)
{
    $party_ids = array_values(array_filter(array_map('intval', $party_ids), static function ($id) {
        return $id > 0;
    }));

    if (empty($party_ids) || !function_exists('peracrm_deals_table_exists') || !peracrm_deals_table_exists()) {
        return [];
    }

    global $wpdb;

    return peracrm_with_target_blog(static function () use ($wpdb, $party_ids) {
        $table = peracrm_table('peracrm_deals');
        $placeholders = implode(', ', array_fill(0, count($party_ids), '%d'));
        $params = array_merge(['completed'], $party_ids);
        $query = $wpdb->prepare(
            "SELECT DISTINCT party_id
             FROM {$table}
             WHERE stage = %s
               AND party_id IN ({$placeholders})",
            $params
        );

        $rows = $wpdb->get_col($query);

        return array_values(array_map('intval', $rows));
    });
}

function peracrm_party_get_status_by_ids(array $party_ids)
{
    $party_ids = array_values(array_filter(array_map('intval', $party_ids), static function ($id) {
        return $id > 0;
    }));

    if (empty($party_ids) || !peracrm_party_table_exists()) {
        return [];
    }

    global $wpdb;

    return peracrm_with_target_blog(static function () use ($wpdb, $party_ids) {
        $table = peracrm_table('peracrm_party');
        $placeholders = implode(', ', array_fill(0, count($party_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT party_id, lead_pipeline_stage, engagement_state, disposition
             FROM {$table}
             WHERE party_id IN ({$placeholders})",
            $party_ids
        );

        $rows = $wpdb->get_results($query, ARRAY_A);
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[(int) $row['party_id']] = [
                'lead_pipeline_stage' => peracrm_party_sanitize_stage($row['lead_pipeline_stage']),
                'engagement_state' => peracrm_party_sanitize_engagement($row['engagement_state']),
                'disposition' => peracrm_party_sanitize_disposition($row['disposition']),
            ];
        }

        return $mapped;
    });
}

function peracrm_parties_count_by_stage($exclude_junk = true)
{
    if (!peracrm_party_table_exists()) {
        return [];
    }

    global $wpdb;

    return peracrm_with_target_blog(static function () use ($wpdb, $exclude_junk) {
        $table = peracrm_table('peracrm_party');
        $where = $exclude_junk ? $wpdb->prepare('WHERE disposition != %s', 'junk_lead') : '';
        $rows = $wpdb->get_results(
            "SELECT lead_pipeline_stage, COUNT(*) AS total
             FROM {$table}
             {$where}
             GROUP BY lead_pipeline_stage",
            ARRAY_A
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['lead_pipeline_stage']] = (int) $row['total'];
        }

        return $counts;
    });
}
