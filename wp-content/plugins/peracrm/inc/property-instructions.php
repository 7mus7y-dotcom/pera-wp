<?php
/** Private CRM property assets and client instructions. No public post writes. */
if (!defined('ABSPATH')) { exit; }

function peracrm_instructions_install_schema()
{
    global $wpdb;
    $collate = $wpdb->get_charset_collate();
    $properties = peracrm_table('crm_instruction_properties');
    $instructions = peracrm_table('crm_property_instructions');
    dbDelta("CREATE TABLE {$properties} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id BIGINT UNSIGNED NOT NULL,
        identifier VARCHAR(191) NOT NULL,
        details LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
        PRIMARY KEY  (id),
        KEY client_id (client_id)
    ) ENGINE=InnoDB {$collate};");
    dbDelta("CREATE TABLE {$instructions} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id BIGINT UNSIGNED NOT NULL,
        property_id BIGINT UNSIGNED NOT NULL,
        instruction_type VARCHAR(10) NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'draft',
        advisor_user_id BIGINT UNSIGNED NOT NULL,
        details LONGTEXT NOT NULL,
        notes TEXT NULL,
        public_property_id BIGINT UNSIGNED NULL,
        sahibinden_url TEXT NULL,
        pera_url TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
        PRIMARY KEY  (id),
        KEY client_updated (client_id, updated_at),
        KEY property_id (property_id)
    ) ENGINE=InnoDB {$collate};");
    peracrm_instructions_assert_schema();
}

function peracrm_instructions_assert_schema()
{
    global $wpdb;
    $definitions = [
        'crm_instruction_properties' => ['id', 'client_id', 'identifier', 'details', 'created_at', 'updated_at', 'revision'],
        'crm_property_instructions' => ['id', 'client_id', 'property_id', 'instruction_type', 'status', 'advisor_user_id', 'details', 'notes', 'public_property_id', 'sahibinden_url', 'pera_url', 'created_at', 'updated_at', 'revision'],
    ];
    foreach ($definitions as $suffix => $required) {
        $table = peracrm_table($suffix);
        $columns = (array) $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        if ($wpdb->last_error || array_diff($required, $columns) || strtolower($status['Engine'] ?? '') !== 'innodb') {
            throw new RuntimeException('Instruction schema verification failed.');
        }
    }
}

function peracrm_instruction_statuses($type)
{
    $states = $type === 'rental'
        ? ['draft', 'ready_to_market', 'marketing', 'application_offer_received', 'tenant_agreed', 'contract_signed', 'active_tenancy', 'completed', 'cancelled', 'archived']
        : ['draft', 'valuation', 'ready_to_market', 'marketing', 'offer_received', 'under_contract', 'completed', 'withdrawn', 'archived'];
    return array_combine($states, array_map(static function ($s) { return ucwords(str_replace('_', ' ', $s)); }, $states));
}

/** Field catalog is shared by validation and both forms. Asset data lives once. */
function peracrm_instruction_fields()
{
    return [
        'property' => [
            'name' => ['Property name or building', 'text'],
            'district' => ['District', 'text'], 'neighbourhood' => ['Neighbourhood', 'text'],
            'address' => ['Address', 'textarea'], 'category' => ['Property category', 'text'],
            'bedrooms' => ['Bedrooms / configuration', 'text'], 'gross_area' => ['Gross area (m²)', 'number'],
            'net_area' => ['Net area (m²)', 'number'], 'occupancy' => ['Occupancy status', 'text'],
            'bathrooms' => ['Bathrooms', 'number'], 'floor' => ['Floor', 'text'],
            'building_floors' => ['Total building floors', 'number'], 'balcony' => ['Balcony', 'text'],
            'parking' => ['Parking', 'text'], 'furnishing' => ['Furnishing', 'text'], 'building_age' => ['Building age', 'number'],
        ],
        'common' => [
            'asking_price' => ['Asking price / monthly asking rent', 'number'], 'currency' => ['Currency', ['TRY', 'USD', 'EUR', 'GBP']],
            'occupancy_notes' => ['Occupancy and tenancy notes', 'textarea'],
            'start_date' => ['Instruction start date', 'date'], 'expiry_date' => ['Instruction expiry date', 'date'],
            'next_action' => ['Next operational action', 'text'],
        ],
        'sale' => [
            'negotiability' => ['Asking-price negotiability', 'text'], 'current_rent' => ['Current monthly rent', 'number'],
            'exclusivity' => ['Instruction exclusivity', ['exclusive', 'non_exclusive']],
            'title_deed' => ['Title deed availability / status', 'text'], 'power_of_attorney' => ['Power of Attorney status', 'text'],
            'valuation_notes' => ['Valuation and pricing notes', 'textarea'], 'marketing_status' => ['Marketing status', 'text'],
        ],
        'rental' => [
            'deposit' => ['Deposit amount', 'number'], 'deposit_status' => ['Deposit status', 'text'],
            'service_charge' => ['Aidat / service charge', 'number'], 'available_from' => ['Available from', 'date'],
            'tenancy_status' => ['Current tenancy status', 'text'], 'contract_start' => ['Tenancy commencement date', 'date'],
            'contract_expiry' => ['Contract expiry', 'date'], 'tenancy_anniversary' => ['Relevant tenancy anniversary', 'date'],
            'rent_review' => ['Rent-review date', 'date'], 'contracted_rent' => ['Current contracted rent', 'number'],
            'management_service' => ['Management service', ['letting_only', 'rent_collection', 'full_management']],
            'management_fee' => ['Management fee (amount / percent and basis)', 'text'],
            'tenant' => ['Tenant name / contact reference', 'textarea'], 'inventory' => ['Inventory / handover status', 'text'],
        ],
    ];
}

function peracrm_instruction_can_access($client_id, $write = false)
{
    $actor = peracrm_get_actor_user_id();
    return peracrm_user_can_access_client($actor, $client_id)
        && (!$write || user_can($actor, 'edit_crm_clients') || user_can($actor, 'manage_options'));
}

/** All reads are scoped and run on the CRM blog, including direct service calls. */
function peracrm_instructions_list($client_id)
{
    return peracrm_with_target_blog(static function () use ($client_id) {
        global $wpdb;
        if (!peracrm_instruction_can_access($client_id)) { return []; }
        $i = peracrm_table('crm_property_instructions');
        $p = peracrm_table('crm_instruction_properties');
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, p.identifier, p.details AS property_details, p.revision AS property_revision FROM {$i} i INNER JOIN {$p} p ON p.id = i.property_id AND p.client_id = i.client_id WHERE i.client_id = %d ORDER BY i.updated_at DESC, i.id DESC", $client_id
        ), ARRAY_A);
    });
}

function peracrm_instruction_validate_fields($raw, $catalog)
{
    $result = [];
    foreach ($catalog as $key => $field) {
        $value = $raw[$key] ?? '';
        if (!is_scalar($value)) { return new WP_Error('invalid_field', 'Invalid property field.'); }
        $value = trim((string) $value);
        $kind = $field[1];
        if ($value !== '' && is_array($kind) && !in_array($value, $kind, true)) {
            return new WP_Error('invalid_option', 'Invalid option: ' . $field[0]);
        }
        if ($value !== '' && $kind === 'number' && (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 || (float) $value > 999999999999)) {
            return new WP_Error('invalid_number', 'Invalid number: ' . $field[0]);
        }
        if ($value !== '' && $kind === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) { return new WP_Error('invalid_date', 'Invalid date: ' . $field[0]); }
        }
        $result[$key] = substr($kind === 'textarea' ? sanitize_textarea_field($value) : sanitize_text_field($value), 0, 5000);
    }
    return $result;
}

function peracrm_instruction_url($value)
{
    if (!is_scalar($value)) { return new WP_Error('invalid_url', 'Invalid listing URL.'); }
    $value = trim((string) $value);
    if ($value === '') { return ''; }
    if (!preg_match('#^https?://#i', $value) || !filter_var($value, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20\x7f]/', $value)) {
        return new WP_Error('invalid_url', 'Listing URLs must be valid HTTP or HTTPS URLs.');
    }
    $clean = esc_url_raw($value, ['http', 'https']);
    return $clean !== '' ? $clean : new WP_Error('invalid_url', 'Invalid listing URL.');
}

/** Nonce and method validation shared by endpoint and behavioral tests. */
function peracrm_instruction_mutate($client_id, array $raw, $nonce, $method)
{
    if ($method !== 'POST' || !wp_verify_nonce($nonce, 'peracrm_property_instruction_' . (int) $client_id)) {
        return new WP_Error('invalid_nonce', 'Security check failed.');
    }
    return peracrm_with_target_blog(static function () use ($client_id, $raw) {
        global $wpdb;
        $client_id = (int) $client_id;
        if (!peracrm_instruction_can_access($client_id, true)) { return new WP_Error('forbidden', 'Access denied.'); }
        $id = absint($raw['instruction_id'] ?? 0);
        $old = null;
        foreach (peracrm_instructions_list($client_id) as $row) {
            if ((int) $row['id'] === $id) { $old = $row; break; }
        }
        if ($id && !$old) { return new WP_Error('not_found', 'Instruction not found for this client.'); }
        $type = $old ? $old['instruction_type'] : ($raw['instruction_type'] ?? '');
        if (!in_array($type, ['sale', 'rental'], true)) { return new WP_Error('invalid_type', 'Choose sale or rental.'); }
        $status = $raw['status'] ?? 'draft';
        if (!is_string($status) || !isset(peracrm_instruction_statuses($type)[$status])) { return new WP_Error('invalid_status', 'Invalid instruction status.'); }
        $reason = isset($raw['transition_reason']) && is_scalar($raw['transition_reason']) ? sanitize_textarea_field($raw['transition_reason']) : '';
        $states = array_keys(peracrm_instruction_statuses($type));
        if ($old && $old['status'] !== $status && (array_search($status, $states, true) < array_search($old['status'], $states, true) || in_array($status, ['cancelled', 'withdrawn', 'archived'], true)) && trim($reason) === '') {
            return new WP_Error('reason_required', 'Explain backward transitions, cancellation, withdrawal, archival or reopening.');
        }
        $fields = peracrm_instruction_fields();
        $property_raw = isset($raw['property']) && is_array($raw['property']) ? $raw['property'] : [];
        $detail_raw = isset($raw['details']) && is_array($raw['details']) ? $raw['details'] : [];
        $property = peracrm_instruction_validate_fields($property_raw, $fields['property']);
        $details = peracrm_instruction_validate_fields($detail_raw, $fields['common'] + $fields[$type]);
        if (is_wp_error($property)) { return $property; }
        if (is_wp_error($details)) { return $details; }
        $reuse_id = !$old ? absint($raw['property_id'] ?? 0) : 0;
        if ($reuse_id) {
            $asset = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . peracrm_table('crm_instruction_properties') . ' WHERE id = %d AND client_id = %d', $reuse_id, $client_id), ARRAY_A);
            if (!$asset) { return new WP_Error('invalid_property', 'Property does not belong to this client.'); }
            $property = peracrm_json_decode($asset['details']);
            $raw['identifier'] = $asset['identifier'];
        }
        $identifier = is_scalar($raw['identifier'] ?? '') ? sanitize_text_field($raw['identifier'] ?? '') : '';
        if (trim($identifier) === '' && trim($property['name'] ?? '') === '') { return new WP_Error('name_required', 'Enter a property name or identifier.'); }
        $identifier = substr($identifier, 0, 191);
        $sahibinden = peracrm_instruction_url($raw['sahibinden_url'] ?? '');
        $pera = peracrm_instruction_url($raw['pera_url'] ?? '');
        if (is_wp_error($sahibinden)) { return $sahibinden; }
        if (is_wp_error($pera)) { return $pera; }
        $public_id = absint($raw['public_property_id'] ?? 0);
        if ($public_id && (get_post_type($public_id) !== 'property' || get_post_status($public_id) !== 'publish')) {
            return new WP_Error('invalid_public_property', 'Linked website property must be a published property.');
        }
        $advisor = absint($raw['advisor_user_id'] ?? ($old['advisor_user_id'] ?? 0));
        if (!$advisor) { $advisor = peracrm_resolve_assignee_user_id(0, peracrm_client_get_assigned_advisor_id($client_id)); }
        if (!$advisor || !peracrm_user_is_valid_advisor($advisor) || !peracrm_user_can_access_client($advisor, $client_id)) { return new WP_Error('invalid_advisor', 'Advisor must have access to this client.'); }
        $property_id = $old ? (int) $old['property_id'] : absint($raw['property_id'] ?? 0);
        $p = peracrm_table('crm_instruction_properties');
        $i = peracrm_table('crm_property_instructions');
        // A transaction prevents orphan assets and protects the shared asset during edits.
        if ($wpdb->query('START TRANSACTION') === false) { return new WP_Error('storage_failed', 'Storage unavailable.'); }
        try {
            if ($old) {
                $locked = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$i} WHERE id = %d AND client_id = %d FOR UPDATE", $id, $client_id), ARRAY_A);
                if (!$locked || (int) $locked['revision'] !== absint($raw['revision'] ?? 0)) { throw new RuntimeException('This instruction changed. Reload before editing.'); }
            }
            if ($property_id) {
                $asset = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p} WHERE id = %d AND client_id = %d FOR UPDATE", $property_id, $client_id), ARRAY_A);
                if (!$asset) { throw new RuntimeException('Property does not belong to this client.'); }
                if ($old && (int) $asset['revision'] !== absint($raw['property_revision'] ?? 0)) { throw new RuntimeException('Shared property details changed. Reload before editing.'); }
                // Reusing an asset for a second instruction never overwrites its physical details.
                if (!$old) { $identifier = $asset['identifier']; }
            }
            $now = peracrm_now_mysql();
            if (!$property_id) {
                if ($wpdb->insert($p, ['client_id' => $client_id, 'identifier' => $identifier, 'details' => peracrm_json_encode($property), 'created_at' => $now, 'updated_at' => $now]) === false) { throw new RuntimeException('Unable to create property.'); }
                $property_id = (int) $wpdb->insert_id;
            } elseif ($old) {
                if ($wpdb->update($p, ['identifier' => $identifier, 'details' => peracrm_json_encode($property), 'updated_at' => $now, 'revision' => (int) $asset['revision'] + 1], ['id' => $property_id, 'client_id' => $client_id]) === false) { throw new RuntimeException('Unable to update property.'); }
            }
            $data = ['client_id' => $client_id, 'property_id' => $property_id, 'instruction_type' => $type, 'status' => $status,
                'advisor_user_id' => $advisor, 'details' => peracrm_json_encode($details),
                'notes' => is_scalar($raw['notes'] ?? '') ? substr(sanitize_textarea_field($raw['notes'] ?? ''), 0, 5000) : '',
                'public_property_id' => $public_id ?: null, 'sahibinden_url' => $sahibinden, 'pera_url' => $pera, 'updated_at' => $now];
            if ($old) {
                $data['revision'] = (int) $old['revision'] + 1;
                $saved = $wpdb->update($i, $data, ['id' => $id, 'client_id' => $client_id]);
            } else {
                $data['created_at'] = $now;
                $saved = $wpdb->insert($i, $data);
                $id = (int) $wpdb->insert_id;
            }
            if ($saved === false || $wpdb->query('COMMIT') === false) { throw new RuntimeException('Unable to save instruction.'); }
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('storage_failed', $error->getMessage());
        }
        peracrm_log_event($client_id, 'property_instruction_saved', ['instruction_id' => $id, 'instruction_property_id' => $property_id, 'property_reference' => $identifier ?: ($property['name'] ?? ''),
            'previous_status' => $old['status'] ?? '', 'status' => $status, 'reason' => $reason, 'actor_user_id' => peracrm_get_actor_user_id()]);
        return $id;
    });
}

function peracrm_instruction_handle_post()
{
    $raw = wp_unslash($_POST);
    $client_id = absint($raw['client_id'] ?? 0);
    $nonce = is_scalar($raw['_wpnonce'] ?? '') ? (string) $raw['_wpnonce'] : '';
    $result = peracrm_instruction_mutate($client_id, $raw, $nonce, $_SERVER['REQUEST_METHOD'] ?? '');
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', ['response' => 400, 'back_link' => true]); }
    wp_safe_redirect(home_url('/crm/client/' . $client_id . '/#crm-property-instructions'));
    exit;
}
add_action('admin_post_peracrm_property_instruction', 'peracrm_instruction_handle_post');
