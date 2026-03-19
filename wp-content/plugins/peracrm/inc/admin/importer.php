<?php

if (!defined('ABSPATH')) {
    exit;
}

const PERACRM_IMPORT_MAX_FILESIZE = 5242880;
const PERACRM_IMPORT_BATCH_SIZE = 50;
const PERACRM_IMPORT_STATE_TTL = 21600;

function peracrm_import_record_type_options()
{
    return [
        'lead' => 'Lead',
        'client' => 'Client',
    ];
}

function peracrm_import_mode_options()
{
    return [
        'create_only' => 'Create only',
        'create_or_update' => 'Create or update',
        'update_only' => 'Update only',
    ];
}

function peracrm_import_destination_fields()
{
    return [
        '' => 'Ignore',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'full_name' => 'Full name',
        'email' => 'Email',
        'phone' => 'Phone',
        'secondary_phone' => 'Secondary phone',
        'whatsapp' => 'WhatsApp',
        'nationality' => 'Nationality',
        'budget' => 'Budget',
        'preferred_area' => 'Preferred area',
        'preferred_property_type' => 'Preferred property type',
        'source' => 'Source',
        'status' => 'Status',
        'assigned_to' => 'Assigned to',
        'import_summary' => 'Import summary',
        'source_created_at' => 'Source created at',
    ];
}

function peracrm_import_required_capability()
{
    return function_exists('peracrm_admin_required_capability')
        ? peracrm_admin_required_capability()
        : 'manage_options';
}

function peracrm_import_user_can_manage()
{
    $cap = peracrm_import_required_capability();
    return current_user_can($cap) || current_user_can('manage_options');
}

function peracrm_import_state_key($suffix)
{
    return 'peracrm_import_' . $suffix . '_' . get_current_user_id();
}

function peracrm_import_get_upload_state()
{
    $state = get_transient(peracrm_import_state_key('upload'));
    return is_array($state) ? $state : [];
}

function peracrm_import_set_upload_state(array $state)
{
    set_transient(peracrm_import_state_key('upload'), $state, PERACRM_IMPORT_STATE_TTL);
}

function peracrm_import_get_validation_state()
{
    $state = get_transient(peracrm_import_state_key('validation'));
    return is_array($state) ? $state : [];
}

function peracrm_import_set_validation_state(array $state)
{
    set_transient(peracrm_import_state_key('validation'), $state, PERACRM_IMPORT_STATE_TTL);
}

function peracrm_import_clear_state($delete_file = false)
{
    $upload = peracrm_import_get_upload_state();
    delete_transient(peracrm_import_state_key('upload'));
    delete_transient(peracrm_import_state_key('validation'));

    if ($delete_file && !empty($upload['file_path']) && is_string($upload['file_path']) && file_exists($upload['file_path'])) {
        wp_delete_file($upload['file_path']);
    }
}

function peracrm_import_detect_delimiter($path)
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        return ',';
    }

    $line = fgets($handle);
    fclose($handle);
    if (!is_string($line)) {
        return ',';
    }

    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
    return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
}

function peracrm_import_parse_csv($path, $delimiter = null)
{
    if (!file_exists($path)) {
        return new WP_Error('missing_file', 'CSV file is no longer available.');
    }

    $delimiter = $delimiter ?: peracrm_import_detect_delimiter($path);
    $handle = fopen($path, 'rb');
    if (!$handle) {
        return new WP_Error('open_failed', 'Unable to open CSV file.');
    }

    $headers = [];
    $rows = [];
    $row_number = 0;

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $row_number++;
        $data = array_map(static function ($value) use ($row_number) {
            $value = is_string($value) ? trim($value) : '';
            if ($row_number === 1) {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
            }
            return $value;
        }, $data);

        if ($row_number === 1) {
            $headers = $data;
            continue;
        }

        $assoc = [];
        foreach ($headers as $index => $header) {
            $assoc[$header !== '' ? $header : 'column_' . $index] = isset($data[$index]) ? trim((string) $data[$index]) : '';
        }
        $rows[] = $assoc;
    }

    fclose($handle);

    if (empty($headers)) {
        return new WP_Error('missing_headers', 'CSV file must include a header row.');
    }

    return [
        'delimiter' => $delimiter,
        'headers' => $headers,
        'rows' => $rows,
    ];
}

function peracrm_import_default_mapping(array $headers)
{
    $destinations = peracrm_import_destination_fields();
    $mapping = [];
    foreach ($headers as $header) {
        $normalized = sanitize_key(str_replace(['-', ' '], '_', strtolower((string) $header)));
        $map = '';
        $aliases = [
            'firstname' => 'first_name',
            'first_name' => 'first_name',
            'lastname' => 'last_name',
            'last_name' => 'last_name',
            'fullname' => 'full_name',
            'full_name' => 'full_name',
            'email' => 'email',
            'phone' => 'phone',
            'mobile' => 'phone',
            'secondary_phone' => 'secondary_phone',
            'whatsapp' => 'whatsapp',
            'nationality' => 'nationality',
            'budget' => 'budget',
            'preferred_area' => 'preferred_area',
            'preferred_property_type' => 'preferred_property_type',
            'source' => 'source',
            'lead_source' => 'source',
            'status' => 'status',
            'assigned_to' => 'assigned_to',
            'owner' => 'assigned_to',
            'description' => 'import_summary',
            'summary' => 'import_summary',
            'created_time' => 'source_created_at',
            'created_at' => 'source_created_at',
        ];
        if (isset($aliases[$normalized]) && isset($destinations[$aliases[$normalized]])) {
            $map = $aliases[$normalized];
        }
        $mapping[$header] = $map;
    }

    return $mapping;
}

function peracrm_import_normalize_email($email)
{
    // Normalizes email shape only; validity is checked separately during validation.
    return strtolower(trim((string) $email));
}

function peracrm_import_normalize_phone($phone)
{
    $digits = preg_replace('/\D+/', '', trim((string) $phone));
    return $digits !== '' ? $digits : '';
}

function peracrm_import_sanitize_budget($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $normalized = preg_replace('/[^0-9.,-]/', '', $value);
    if ($normalized === '' || $normalized === '-' || $normalized === ',' || $normalized === '.') {
        return null;
    }

    if (substr_count($normalized, ',') > 0 && substr_count($normalized, '.') === 0) {
        $normalized = str_replace(',', '.', $normalized);
    } else {
        $normalized = str_replace(',', '', $normalized);
    }

    return is_numeric($normalized) ? (float) $normalized : null;
}

function peracrm_import_parse_datetime($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
}

function peracrm_import_map_status_to_stage($status)
{
    $normalized = strtolower(trim((string) $status));
    if ($normalized === '') {
        return 'new_enquiry';
    }

    $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);
    $normalized = trim((string) $normalized);

    $map = [
        'new' => 'new_enquiry',
        'new enquiry' => 'new_enquiry',
        'attempted to contact' => 'contacted',
        'contacted' => 'contacted',
        'qualified' => 'qualified',
        'proposal' => 'offer_made',
        'price quote' => 'offer_made',
        'proposal price quote' => 'offer_made',
        'negotiation' => 'offer_made',
        'review' => 'qualified',
        'negotiation review' => 'offer_made',
        'closed won' => 'deal_closed',
        'closed lost' => 'deal_lost',
    ];

    $mapped = isset($map[$normalized]) ? $map[$normalized] : $normalized;

    return function_exists('peracrm_party_sanitize_stage')
        ? peracrm_party_sanitize_stage($mapped)
        : $mapped;
}

function peracrm_import_build_mapped_row(array $csv_row, array $mapping)
{
    $mapped = [];
    foreach ($mapping as $source => $destination) {
        $destination = sanitize_key((string) $destination);
        if ($destination === '') {
            continue;
        }
        $mapped[$destination] = isset($csv_row[$source]) ? trim((string) $csv_row[$source]) : '';
    }

    if (($mapped['full_name'] ?? '') !== '') {
        if (($mapped['first_name'] ?? '') === '' || ($mapped['last_name'] ?? '') === '') {
            $parts = preg_split('/\s+/', trim((string) $mapped['full_name']));
            if (($mapped['first_name'] ?? '') === '' && !empty($parts)) {
                $mapped['first_name'] = (string) array_shift($parts);
            }
            if (($mapped['last_name'] ?? '') === '' && !empty($parts)) {
                $mapped['last_name'] = trim(implode(' ', $parts));
            }
        }
    }

    return $mapped;
}

function peracrm_import_row_identity_keys(array $mapped)
{
    $keys = [];

    $email = peracrm_import_normalize_email($mapped['email'] ?? '');
    if ($email !== '') {
        $keys['email:' . $email] = 'email:' . $email;
    }

    foreach (['phone', 'secondary_phone', 'whatsapp'] as $field) {
        $phone = peracrm_import_normalize_phone($mapped[$field] ?? '');
        if ($phone !== '') {
            $keys['phone:' . $phone] = 'phone:' . $phone;
        }
    }

    return array_values($keys);
}

function peracrm_import_match_existing_record(array $mapped)
{
    $email = peracrm_import_normalize_email($mapped['email'] ?? '');
    $phone_candidates = [];
    foreach (['phone', 'secondary_phone', 'whatsapp'] as $key) {
        $normalized = peracrm_import_normalize_phone($mapped[$key] ?? '');
        if ($normalized !== '') {
            $phone_candidates[$normalized] = $normalized;
        }
    }

    if ($email !== '') {
        $ids = get_posts([
            'post_type' => 'crm_client',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_peracrm_email', 'value' => $email],
                ['key' => 'crm_primary_email_normalized', 'value' => $email],
                ['key' => 'primary_email_normalized', 'value' => $email],
            ],
        ]);
        if (!empty($ids)) {
            return (int) $ids[0];
        }
    }

    foreach ($phone_candidates as $phone) {
        $ids = get_posts([
            'post_type' => 'crm_client',
            'post_status' => 'any',
            'posts_per_page' => 50,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_peracrm_phone', 'value' => $phone, 'compare' => 'LIKE'],
                ['key' => 'crm_phone', 'value' => $phone, 'compare' => 'LIKE'],
            ],
        ]);
        foreach ($ids as $id) {
            $stored = [
                peracrm_import_normalize_phone(get_post_meta($id, '_peracrm_phone', true)),
                peracrm_import_normalize_phone(get_post_meta($id, 'crm_phone', true)),
            ];
            if (in_array($phone, $stored, true)) {
                return (int) $id;
            }
        }
    }

    return 0;
}

function peracrm_import_assigned_user_id($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }

    if (is_numeric($value)) {
        $user_id = (int) $value;
        return function_exists('peracrm_user_is_staff') && peracrm_user_is_staff($user_id) ? $user_id : 0;
    }

    $staff = function_exists('peracrm_get_staff_users') ? peracrm_get_staff_users() : [];
    $needle = strtolower($value);
    foreach ($staff as $user) {
        if (!$user instanceof WP_User) {
            continue;
        }
        $haystacks = [strtolower($user->display_name), strtolower($user->user_email), strtolower($user->user_login)];
        if (in_array($needle, $haystacks, true)) {
            return (int) $user->ID;
        }
    }

    return 0;
}

function peracrm_import_validate_rows(array $rows, array $mapping, $record_type, $mode)
{
    $summary = [
        'total_rows' => count($rows),
        'valid_rows' => 0,
        'rows_missing_identity' => 0,
        'rows_invalid_email' => 0,
        'rows_would_create' => 0,
        'rows_would_update' => 0,
        'duplicate_rows' => 0,
    ];

    $seen = [];
    $results = [];
    foreach ($rows as $index => $row) {
        $mapped = peracrm_import_build_mapped_row($row, $mapping);
        $identity_keys = peracrm_import_row_identity_keys($mapped);
        $first_name = trim((string) ($mapped['first_name'] ?? ''));
        $last_name = trim((string) ($mapped['last_name'] ?? ''));
        $has_name = $first_name !== '' || $last_name !== '' || trim((string) ($mapped['full_name'] ?? '')) !== '';
        $errors = [];

        if (($mapped['email'] ?? '') !== '' && !is_email(peracrm_import_normalize_email($mapped['email'] ?? ''))) {
            $errors[] = 'Invalid email';
            $summary['rows_invalid_email']++;
        }

        if (empty($identity_keys) && !$has_name) {
            $errors[] = 'Missing email/phone and name';
            $summary['rows_missing_identity']++;
        }

        $has_duplicate_identity = false;
        foreach ($identity_keys as $identity_key) {
            if (isset($seen[$identity_key])) {
                $has_duplicate_identity = true;
            }
        }
        if ($has_duplicate_identity) {
            $errors[] = 'Duplicate identity in CSV';
            $summary['duplicate_rows']++;
        }
        foreach ($identity_keys as $identity_key) {
            $seen[$identity_key] = true;
        }

        $existing_id = peracrm_import_match_existing_record($mapped);
        $action = 'skip';
        if ($existing_id > 0) {
            $action = 'update';
            if ($mode === 'create_only') {
                $errors[] = 'Existing record matched in create only mode';
            }
        } else {
            $action = 'create';
            if ($mode === 'update_only') {
                $errors[] = 'No existing record matched in update only mode';
            }
            if (empty($identity_keys) && !$has_name) {
                $errors[] = 'Row cannot be created without identity or name';
            }
        }

        if (empty($errors)) {
            $summary['valid_rows']++;
            if ($action === 'create') {
                $summary['rows_would_create']++;
            }
            if ($action === 'update') {
                $summary['rows_would_update']++;
            }
        }

        $results[] = [
            'row_number' => $index + 2,
            'mapped' => $mapped,
            'existing_id' => $existing_id,
            'action' => $action,
            'errors' => $errors,
            'record_type' => $record_type,
        ];
    }

    return [
        'summary' => $summary,
        'results' => $results,
    ];
}

function peracrm_import_build_preview_rows(array $results, $limit = 20)
{
    $preview = [];
    foreach (array_slice($results, 0, max(1, (int) $limit)) as $result) {
        $preview[] = [
            'row_number' => (int) ($result['row_number'] ?? 0),
            'action' => sanitize_key((string) ($result['action'] ?? 'skip')),
            'errors' => array_values(array_map('sanitize_text_field', (array) ($result['errors'] ?? []))),
            'mapped' => array_map('sanitize_text_field', (array) ($result['mapped'] ?? [])),
        ];
    }

    return $preview;
}

function peracrm_import_upsert_meta_value($post_id, $meta_key, $value, $is_update)
{
    $post_id = (int) $post_id;
    $meta_key = (string) $meta_key;
    if ($post_id <= 0 || $meta_key === '') {
        return;
    }

    if ($value === null || $value === '') {
        if (!$is_update) {
            delete_post_meta($post_id, $meta_key);
        }
        return;
    }

    update_post_meta($post_id, $meta_key, $value);
}

function peracrm_import_apply_row(array $validated_row, $record_type, $mode)
{
    $mapped = $validated_row['mapped'];
    $post_id = (int) ($validated_row['existing_id'] ?? 0);
    $mode = sanitize_key((string) $mode);
    $is_update = $post_id > 0;

    if ($mode === 'update_only' && !$is_update) {
        return new WP_Error('import_mode_create_blocked', 'Update only mode cannot create new records.');
    }

    if ($mode === 'create_only' && $is_update) {
        return new WP_Error('import_mode_update_blocked', 'Create only mode cannot update existing records.');
    }

    $first_name = sanitize_text_field((string) ($mapped['first_name'] ?? ''));
    $last_name = sanitize_text_field((string) ($mapped['last_name'] ?? ''));
    $full_name = trim((string) ($mapped['full_name'] ?? ''));
    $incoming_name_present = $full_name !== '' || $first_name !== '' || $last_name !== '';
    if ($full_name === '') {
        $full_name = trim($first_name . ' ' . $last_name);
    }
    if ($full_name === '') {
        $full_name = sanitize_text_field((string) ($mapped['email'] ?? ''));
    }
    if ($full_name === '') {
        $full_name = 'Imported record';
    }

    if ($is_update) {
        if ($incoming_name_present) {
            wp_update_post([
                'ID' => $post_id,
                'post_title' => $full_name,
            ]);
        }
    } else {
        $post_id = wp_insert_post([
            'post_type' => 'crm_client',
            'post_status' => 'publish',
            'post_title' => $full_name,
        ], true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        $post_id = (int) $post_id;
    }

    $email = peracrm_import_normalize_email($mapped['email'] ?? '');
    $phone = peracrm_import_normalize_phone($mapped['phone'] ?? '');
    $secondary_phone = peracrm_import_normalize_phone($mapped['secondary_phone'] ?? '');
    $whatsapp = peracrm_import_normalize_phone($mapped['whatsapp'] ?? '');
    $budget = peracrm_import_sanitize_budget($mapped['budget'] ?? '');
    $source_created_at = peracrm_import_parse_datetime($mapped['source_created_at'] ?? '');
    $assigned_user_id = peracrm_import_assigned_user_id($mapped['assigned_to'] ?? '');
    $raw_status = trim((string) ($mapped['status'] ?? ''));
    $safe_stage = peracrm_import_map_status_to_stage($raw_status);
    $source_value = sanitize_text_field((string) ($mapped['source'] ?? ''));
    if ($source_value === '' && !$is_update) {
        $source_value = 'zoho_csv_import';
    }

    if ($first_name !== '') {
        update_post_meta($post_id, 'crm_first_name', $first_name);
    }
    if ($last_name !== '') {
        update_post_meta($post_id, 'crm_last_name', $last_name);
    }

    if ($email !== '') {
        update_post_meta($post_id, 'crm_primary_email', $email);
        update_post_meta($post_id, 'primary_email', $email);
        update_post_meta($post_id, 'crm_primary_email_normalized', $email);
        update_post_meta($post_id, 'primary_email_normalized', $email);
        update_post_meta($post_id, '_peracrm_email', $email);
    }

    foreach ([
        '_peracrm_phone' => $phone,
        'crm_phone' => $phone,
        '_peracrm_secondary_phone' => $secondary_phone,
        '_peracrm_whatsapp' => $whatsapp,
        '_peracrm_nationality' => sanitize_text_field((string) ($mapped['nationality'] ?? '')),
        '_peracrm_preferred_area' => sanitize_text_field((string) ($mapped['preferred_area'] ?? '')),
        '_peracrm_preferred_property_type' => sanitize_text_field((string) ($mapped['preferred_property_type'] ?? '')),
        'crm_source' => $source_value,
        '_peracrm_import_summary' => sanitize_textarea_field((string) ($mapped['import_summary'] ?? '')),
        '_peracrm_source_created_at' => $source_created_at ?: '',
        '_peracrm_import_record_type' => $record_type,
        '_peracrm_raw_import_status' => $raw_status,
    ] as $meta_key => $meta_value) {
        peracrm_import_upsert_meta_value($post_id, $meta_key, $meta_value, $is_update);
    }

    if ($budget === null && !$is_update) {
        delete_post_meta($post_id, '_peracrm_budget_min_usd');
        delete_post_meta($post_id, '_peracrm_budget_max_usd');
    } elseif ($budget !== null) {
        update_post_meta($post_id, '_peracrm_budget_min_usd', $budget);
        update_post_meta($post_id, '_peracrm_budget_max_usd', $budget);
    }

    if ($assigned_user_id > 0) {
        update_post_meta($post_id, 'assigned_advisor_user_id', $assigned_user_id);
        update_post_meta($post_id, 'crm_assigned_advisor', $assigned_user_id);
    }

    if (function_exists('peracrm_party_upsert_status')) {
        peracrm_party_upsert_status($post_id, [
            'lead_pipeline_stage' => $safe_stage ?: 'new_enquiry',
            'engagement_state' => 'engaged',
            'disposition' => 'none',
            'lead_stage_updated_at' => peracrm_now_mysql(),
        ]);
    }

    if (function_exists('peracrm_log_event')) {
        peracrm_log_event($post_id, $is_update ? 'import_updated' : 'import_created', [
            'source' => 'zoho_csv_import',
            'record_type' => $record_type,
            'mode' => $mode,
        ]);
    }

    return $post_id;
}

function peracrm_import_last_batch_state_key()
{
    return peracrm_import_state_key('last_batch');
}

function peracrm_import_get_last_batch_summary()
{
    $state = get_transient(peracrm_import_last_batch_state_key());
    return is_array($state) ? $state : [];
}

function peracrm_import_set_last_batch_summary(array $state)
{
    set_transient(peracrm_import_last_batch_state_key(), $state, PERACRM_IMPORT_STATE_TTL);
}

function peracrm_import_batches_table_exists()
{
    global $wpdb;
    $table = peracrm_table('peracrm_import_batches');
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

function peracrm_import_log_batch(array $data)
{
    if (!peracrm_import_batches_table_exists()) {
        return 0;
    }

    global $wpdb;
    $table = peracrm_table('peracrm_import_batches');
    $wpdb->insert($table, [
        'file_name' => sanitize_file_name((string) ($data['file_name'] ?? '')),
        'record_type' => sanitize_key((string) ($data['record_type'] ?? 'lead')),
        'mode' => sanitize_key((string) ($data['mode'] ?? 'create_only')),
        'imported_by' => (int) ($data['imported_by'] ?? 0),
        'total_rows' => (int) ($data['total_rows'] ?? 0),
        'created_count' => (int) ($data['created_count'] ?? 0),
        'updated_count' => (int) ($data['updated_count'] ?? 0),
        'skipped_count' => (int) ($data['skipped_count'] ?? 0),
        'failed_count' => (int) ($data['failed_count'] ?? 0),
        'mapping_json' => peracrm_json_encode($data['mapping'] ?? []),
        'created_at' => peracrm_now_mysql(),
    ], ['%s','%s','%s','%d','%d','%d','%d','%d','%d','%s','%s']);

    return (int) $wpdb->insert_id;
}
