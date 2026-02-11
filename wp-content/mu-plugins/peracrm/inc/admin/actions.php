<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_admin_user_can_manage()
{
    return function_exists('peracrm_user_can_access_crm')
        ? peracrm_user_can_access_crm()
        : (current_user_can('manage_options') || current_user_can('edit_crm_clients'));
}


function peracrm_pipeline_stage_options()
{
    if (function_exists('peracrm_lead_stage_options')) {
        $options = (array) peracrm_lead_stage_options();
        if (!empty($options)) {
            return $options;
        }
    }

    if (function_exists('peracrm_party_stage_options')) {
        $options = (array) peracrm_party_stage_options();
        if (!empty($options)) {
            return $options;
        }
    }

    $options = [
        'enquiry' => 'Enquiry',
        'active' => 'Active',
        'dormant' => 'Dormant',
        'closed' => 'Closed',
    ];

    if (function_exists('peracrm_parties_count_by_stage')) {
        foreach ((array) peracrm_parties_count_by_stage(false) as $stage_key => $count) {
            $stage_key = sanitize_key((string) $stage_key);
            if ($stage_key === '' || isset($options[$stage_key])) {
                continue;
            }
            $options[$stage_key] = ucwords(str_replace('_', ' ', $stage_key));
        }
    }

    return $options;
}

function peracrm_pipeline_status_labels()
{
    return peracrm_pipeline_stage_options();
}

function peracrm_pipeline_client_type_options()
{
    return [
        'all' => 'All types',
        'citizenship' => 'Citizenship',
        'investor' => 'Investor',
        'lifestyle' => 'Lifestyle',
    ];
}

function peracrm_pipeline_health_options()
{
    return [
        'all' => 'All health',
        'hot' => 'Hot',
        'warm' => 'Warm',
        'cold' => 'Cold',
        'at_risk' => 'At risk',
        'none' => 'None',
    ];
}

function peracrm_pipeline_get_user_views($user_id)
{
    $views = get_user_meta((int) $user_id, '_peracrm_pipeline_views', true);
    if (!is_array($views)) {
        return [];
    }

    $sanitized = [];
    foreach ($views as $view) {
        if (!is_array($view)) {
            continue;
        }
        if (empty($view['id']) || empty($view['name']) || empty($view['filters']) || !is_array($view['filters'])) {
            continue;
        }
        $sanitized[] = [
            'id' => sanitize_text_field($view['id']),
            'name' => sanitize_text_field($view['name']),
            'filters' => $view['filters'],
            'created_at' => isset($view['created_at']) ? (int) $view['created_at'] : 0,
        ];
    }

    return $sanitized;
}

function peracrm_pipeline_sanitize_view_name($name)
{
    $name = sanitize_text_field($name);
    $name = trim($name);
    if (strlen($name) > 40) {
        $name = substr($name, 0, 40);
    }

    return $name;
}

function peracrm_pipeline_sanitize_view_filters($raw_filters, $is_admin)
{
    $client_type_options = function_exists('peracrm_pipeline_client_type_options')
        ? peracrm_pipeline_client_type_options()
        : [];
    $health_options = function_exists('peracrm_pipeline_health_options')
        ? peracrm_pipeline_health_options()
        : [];

    $client_type = isset($raw_filters['client_type']) ? sanitize_key($raw_filters['client_type']) : 'all';
    if (!isset($client_type_options[$client_type])) {
        $client_type = 'all';
    }

    $health = isset($raw_filters['health']) ? sanitize_key($raw_filters['health']) : 'all';
    if (!isset($health_options[$health])) {
        $health = 'all';
    }

    $hide_empty_columns = !empty($raw_filters['hide_empty_columns']) ? 1 : 0;

    $filters = [
        'client_type' => $client_type,
        'health' => $health,
        'hide_empty_columns' => $hide_empty_columns,
    ];

    if ($is_admin) {
        $advisor_id = isset($raw_filters['advisor']) ? absint($raw_filters['advisor']) : 0;
        if ($advisor_id > 0 && !peracrm_user_is_staff($advisor_id)) {
            $advisor_id = 0;
        }
        $filters['advisor_id'] = $advisor_id;
    }

    return $filters;
}

function peracrm_pipeline_build_base_url($filters = [])
{
    $base = [
        'post_type' => 'crm_client',
        'page' => 'peracrm-pipeline',
    ];

    if (isset($filters['client_type'])) {
        $base['client_type'] = sanitize_key($filters['client_type']);
    }
    if (isset($filters['health'])) {
        $base['health'] = sanitize_key($filters['health']);
    }
    if (array_key_exists('advisor_id', $filters)) {
        $base['advisor'] = absint($filters['advisor_id']);
    }
    if (!empty($filters['hide_empty_columns'])) {
        $base['hide_empty_columns'] = 1;
    }
    if (!empty($filters['view_id'])) {
        $base['view_id'] = sanitize_text_field($filters['view_id']);
    }

    return add_query_arg($base, admin_url('edit.php'));
}

function peracrm_pipeline_build_base_meta_query($client_type, $advisor_id)
{
    $meta_query = [
        'relation' => 'AND',
    ];

    if ($client_type !== 'all') {
        $meta_query[] = [
            'key' => '_peracrm_client_type',
            'value' => $client_type,
            'compare' => '=',
        ];
    }

    $advisor_id = (int) $advisor_id;
    if ($advisor_id > 0) {
        $meta_keys = peracrm_pipeline_assigned_meta_keys();
        if (!empty($meta_keys)) {
            $assigned_query = ['relation' => 'OR'];
            foreach ($meta_keys as $meta_key) {
                $assigned_query[] = [
                    'key' => $meta_key,
                    'value' => $advisor_id,
                    'compare' => '=',
                ];
            }
            $meta_query[] = $assigned_query;
        }
    }

    return $meta_query;
}

function peracrm_admin_get_client($client_id)
{
    $client = get_post((int) $client_id);
    if (!$client || 'crm_client' !== $client->post_type) {
        return null;
    }

    return $client;
}

function peracrm_admin_get_reminder($reminder_id)
{
    return peracrm_reminders_get($reminder_id);
}

function peracrm_admin_get_client_reminders($client_id, $limit = 20)
{
    return peracrm_reminders_list_for_client($client_id, $limit, 0, null);
}

function peracrm_admin_get_advisor_reminders_until($advisor_user_id, $until_mysql, $limit = 200)
{
    $limit = max(1, (int) $limit);
    $until_mysql = sanitize_text_field($until_mysql);
    if ($until_mysql === '') {
        return [];
    }

    $reminders = peracrm_reminders_list_for_advisor($advisor_user_id, $limit, 0, 'pending', 'all', 'asc');

    return array_values(array_filter($reminders, function ($reminder) use ($until_mysql) {
        return isset($reminder['due_at']) && $reminder['due_at'] <= $until_mysql;
    }));
}

function peracrm_admin_get_client_property_count($client_id, $relation_type)
{
    if (!function_exists('peracrm_client_property_table_exists') || !peracrm_client_property_table_exists()) {
        return 0;
    }

    global $wpdb;

    $table = peracrm_table('crm_client_property');

    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE client_id = %d AND relation_type = %s",
        (int) $client_id,
        sanitize_key($relation_type)
    );

    return (int) $wpdb->get_var($query);
}

function peracrm_admin_client_table_has_linked_user_column()
{
    static $has_column = null;

    if (null !== $has_column) {
        return $has_column;
    }

    global $wpdb;

    $table = peracrm_table('crm_client');
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (!$table_exists) {
        $has_column = false;
        return $has_column;
    }

    $column = $wpdb->get_col("SHOW COLUMNS FROM {$table} LIKE 'linked_user_id'");
    $has_column = !empty($column);

    return $has_column;
}

function peracrm_admin_get_client_linked_user_id($client_id)
{
    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        return 0;
    }

    if (peracrm_admin_client_table_has_linked_user_column()) {
        global $wpdb;
        $table = peracrm_table('crm_client');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT linked_user_id FROM {$table} WHERE id = %d", $client_id)
        );
        if (null !== $row) {
            return (int) $row->linked_user_id;
        }
        return 0;
    }

    return (int) get_post_meta($client_id, 'linked_user_id', true);
}

function peracrm_admin_find_linked_user_id($client_id)
{
    $linked_user_id = peracrm_admin_get_client_linked_user_id($client_id);
    if ($linked_user_id > 0 || peracrm_admin_client_table_has_linked_user_column()) {
        return $linked_user_id;
    }

    $users = get_users([
        'meta_key' => 'crm_client_id',
        'meta_value' => (int) $client_id,
        'number' => 1,
        'fields' => 'ids',
    ]);

    if (empty($users)) {
        return 0;
    }

    return (int) $users[0];
}

function peracrm_admin_update_client_linked_user_id($client_id, $user_id)
{
    $client_id = (int) $client_id;
    $user_id = (int) $user_id;
    if ($client_id <= 0) {
        return false;
    }

    if (peracrm_admin_client_table_has_linked_user_column()) {
        global $wpdb;
        $table = peracrm_table('crm_client');
        $result = $wpdb->update(
            $table,
            ['linked_user_id' => $user_id > 0 ? $user_id : null],
            ['id' => $client_id],
            ['%d'],
            ['%d']
        );
        if (false !== $result) {
            if ($user_id <= 0) {
                delete_post_meta($client_id, 'linked_user_id');
            }
            return true;
        }
    }

    if ($user_id > 0) {
        return (bool) update_post_meta($client_id, 'linked_user_id', $user_id);
    }

    delete_post_meta($client_id, 'linked_user_id');
    return true;
}

function peracrm_admin_parse_datetime($raw_datetime)
{
    $raw_datetime = sanitize_text_field($raw_datetime);
    if ($raw_datetime === '') {
        return '';
    }

    $timezone = wp_timezone();
    $formats = ['Y-m-d\TH:i', 'Y-m-d H:i'];

    foreach ($formats as $format) {
        $date = date_create_from_format($format, $raw_datetime, $timezone);
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($raw_datetime);
    if ($timestamp) {
        return wp_date('Y-m-d H:i:s', $timestamp, $timezone);
    }

    return '';
}

function peracrm_admin_redirect_with_notice($url, $notice)
{
    $url = add_query_arg('peracrm_notice', $notice, $url);
    $url = wp_validate_redirect($url, admin_url('edit.php?post_type=crm_client'));
    wp_safe_redirect($url);
    exit;
}

function peracrm_admin_client_screen_url($client_id)
{
    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        return admin_url('edit.php?post_type=crm_client');
    }

    if (get_post_type($client_id) === 'crm_client') {
        return wp_validate_redirect(
            admin_url('post.php?post=' . $client_id . '&action=edit'),
            admin_url('edit.php?post_type=crm_client')
        );
    }

    return wp_validate_redirect(
        admin_url('admin.php?page=peracrm-client-view&client_id=' . $client_id),
        admin_url('edit.php?post_type=crm_client')
    );
}

function peracrm_admin_search_user_for_link($search_term, $client_id = 0)
{
    $client_id = (int) $client_id;
    if ($client_id > 0 && !current_user_can('edit_post', $client_id)) {
        return [];
    }

    $search_term = sanitize_text_field($search_term);
    $search_term = trim($search_term);
    if (strlen($search_term) > 100) {
        $search_term = substr($search_term, 0, 100);
    }
    if ($search_term === '') {
        return [];
    }

    return get_users([
        'search' => '*' . $search_term . '*',
        'search_columns' => ['user_login', 'user_email', 'display_name'],
        'number' => 5,
    ]);
}

function peracrm_admin_get_assigned_advisor_id_for_client($client_id)
{
    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        return 0;
    }

    if (function_exists('peracrm_client_get_assigned_advisor_id')) {
        return (int) peracrm_client_get_assigned_advisor_id($client_id);
    }

    return 0;
}

function peracrm_handle_add_note()
{
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_add_note', 'peracrm_add_note_nonce');

    $client_id = isset($_POST['peracrm_client_id']) ? (int) $_POST['peracrm_client_id'] : 0;
    $client = peracrm_admin_get_client($client_id);
    if (!$client) {
        wp_die('Invalid client');
    }

    $assigned_advisor_id = peracrm_admin_get_assigned_advisor_id_for_client($client_id);
    $can_override = current_user_can('manage_options') || current_user_can('peracrm_manage_all_reminders');
    $is_assigned_advisor = $assigned_advisor_id > 0 && $assigned_advisor_id === get_current_user_id();
    if (!$can_override && !$is_assigned_advisor) {
        wp_die('Unauthorized');
    }

    $note_body = isset($_POST['peracrm_note_body']) ? sanitize_textarea_field(wp_unslash($_POST['peracrm_note_body'])) : '';
    $note_body = trim($note_body);
    if (strlen($note_body) > 5000) {
        $note_body = substr($note_body, 0, 5000);
    }
    if ($note_body === '') {
        peracrm_admin_redirect_with_notice(get_edit_post_link($client_id, 'raw'), 'note_missing');
    }

    $note_id = peracrm_note_add($client_id, get_current_user_id(), $note_body);
    if (!$note_id) {
        peracrm_admin_redirect_with_notice(get_edit_post_link($client_id, 'raw'), 'note_failed');
    }

    peracrm_admin_redirect_with_notice(get_edit_post_link($client_id, 'raw'), 'note_added');
}

function peracrm_handle_pipeline_save_view()
{
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    if (!current_user_can('edit_crm_clients')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_pipeline_save_view');

    $name = isset($_POST['view_name']) ? peracrm_pipeline_sanitize_view_name(wp_unslash($_POST['view_name'])) : '';
    if ($name === '') {
        peracrm_admin_redirect_with_notice(peracrm_pipeline_build_base_url(), 'pipeline_view_name_missing');
    }

    $is_admin = current_user_can('manage_options');
    $filters = peracrm_pipeline_sanitize_view_filters(wp_unslash($_POST), $is_admin);
    if (!$is_admin) {
        unset($filters['advisor_id']);
    }

    $user_id = get_current_user_id();
    $views = peracrm_pipeline_get_user_views($user_id);
    $view_id = uniqid('view_', true);
    $views[] = [
        'id' => $view_id,
        'name' => $name,
        'filters' => $filters,
        'created_at' => time(),
    ];

    if (count($views) > 10) {
        $views = array_slice($views, -10);
    }

    update_user_meta($user_id, '_peracrm_pipeline_views', $views);

    $redirect = peracrm_pipeline_build_base_url(array_merge($filters, ['view_id' => $view_id]));
    peracrm_admin_redirect_with_notice($redirect, 'pipeline_view_saved');
}

function peracrm_handle_pipeline_delete_view()
{
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    if (!current_user_can('edit_crm_clients')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_pipeline_delete_view');

    $view_id = isset($_POST['view_id']) ? sanitize_text_field(wp_unslash($_POST['view_id'])) : '';
    if ($view_id === '') {
        peracrm_admin_redirect_with_notice(peracrm_pipeline_build_base_url(), 'pipeline_view_missing');
    }

    $user_id = get_current_user_id();
    $views = peracrm_pipeline_get_user_views($user_id);
    $updated = [];
    $found = false;
    foreach ($views as $view) {
        if (!is_array($view) || !isset($view['id'])) {
            continue;
        }
        if ($view['id'] === $view_id) {
            $found = true;
            continue;
        }
        $updated[] = $view;
    }

    if (!$found) {
        peracrm_admin_redirect_with_notice(peracrm_pipeline_build_base_url(), 'pipeline_view_missing');
    }

    update_user_meta($user_id, '_peracrm_pipeline_views', $updated);

    $redirect = peracrm_pipeline_build_base_url();
    peracrm_admin_redirect_with_notice($redirect, 'pipeline_view_deleted');
}


function peracrm_pipeline_allowed_stage_values()
{
    return array_values(array_filter(array_map('sanitize_key', array_keys(peracrm_pipeline_stage_options())), static function ($stage) {
        return $stage !== '';
    }));
}

function peracrm_pipeline_default_stage_key()
{
    $options = peracrm_pipeline_stage_options();
    if (isset($options['new_enquiry'])) {
        return 'new_enquiry';
    }

    if (isset($options['enquiry'])) {
        return 'enquiry';
    }

    $keys = array_keys($options);
    if (!empty($keys)) {
        return sanitize_key((string) $keys[0]);
    }

    return 'enquiry';
}

function peracrm_handle_pipeline_move_stage()
{
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_pipeline_move_stage');

    $client_id = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;
    $client = peracrm_admin_get_client($client_id);
    if (!$client) {
        wp_die('Invalid client');
    }

    $filters = peracrm_pipeline_sanitize_view_filters(wp_unslash($_POST), current_user_can('manage_options'));
    $view_id = isset($_POST['view_id']) ? sanitize_text_field(wp_unslash($_POST['view_id'])) : '';
    if ($view_id !== '') {
        $filters['view_id'] = $view_id;
    }
    $redirect = peracrm_pipeline_build_base_url($filters);

    if (!current_user_can('edit_post', $client_id)) {
        peracrm_admin_redirect_with_notice($redirect, 'stage_denied');
    }

    $allowed_statuses = peracrm_pipeline_allowed_stage_values();
    $to_status = isset($_POST['to_status']) ? sanitize_key(wp_unslash($_POST['to_status'])) : '';
    if (!in_array($to_status, $allowed_statuses, true)) {
        peracrm_admin_redirect_with_notice($redirect, 'stage_invalid');
    }

    $can_override = current_user_can('manage_options') || current_user_can('peracrm_manage_assignments');
    if (!$can_override) {
        if (!function_exists('peracrm_client_get_assigned_advisor_id')) {
            peracrm_admin_redirect_with_notice($redirect, 'stage_denied');
        }
        $assigned_id = (int) peracrm_client_get_assigned_advisor_id($client_id);
        if ($assigned_id <= 0 || $assigned_id !== get_current_user_id()) {
            peracrm_admin_redirect_with_notice($redirect, 'stage_denied');
        }
    }

    $party = function_exists('peracrm_party_get') ? peracrm_party_get($client_id) : [];
    $from_status = sanitize_key((string) ($party['lead_pipeline_stage'] ?? ''));
    if (!in_array($from_status, $allowed_statuses, true)) {
        $from_status = peracrm_pipeline_default_stage_key();
    }
    if ($from_status === $to_status) {
        peracrm_admin_redirect_with_notice($redirect, 'stage_invalid');
    }

    if (!function_exists('peracrm_party_upsert_status')) {
        peracrm_admin_redirect_with_notice($redirect, 'stage_failed');
    }

    $stage_saved = peracrm_party_upsert_status($client_id, [
        'lead_pipeline_stage' => $to_status,
        'engagement_state' => $party['engagement_state'] ?? 'engaged',
        'disposition' => $party['disposition'] ?? 'none',
        'lead_stage_updated_at' => peracrm_now_mysql(),
    ]);

    if (function_exists('peracrm_party_get')) {
        $updated_party = peracrm_party_get($client_id);
        $updated_stage = sanitize_key((string) ($updated_party['lead_pipeline_stage'] ?? ''));
        $stage_verified = ($updated_stage === $to_status);
    } else {
        $stage_verified = (true === $stage_saved);
    }

    if (!$stage_verified) {
        peracrm_admin_redirect_with_notice($redirect, 'stage_failed');
    }

    $can_log = function_exists('peracrm_activity_table_exists') && peracrm_activity_table_exists();
    if ($can_log && function_exists('peracrm_activity_insert')) {
        $payload = [
            'from' => $from_status,
            'to' => $to_status,
            'actor_user_id' => get_current_user_id(),
            'context' => 'pipeline',
        ];
        if (function_exists('peracrm_log_event')) {
            peracrm_log_event($client_id, 'status_changed', $payload);
        } else {
            peracrm_activity_insert($client_id, 'status_changed', $payload);
        }
    }

    peracrm_admin_redirect_with_notice($redirect, 'stage_moved');
}

function peracrm_pipeline_bulk_redirect($redirect, $action_key, $done, $failed, $capped = false)
{
    $args = [
        'bulk_action' => sanitize_key($action_key),
        'bulk_done' => (int) $done,
        'bulk_failed' => (int) $failed,
    ];
    if ($capped) {
        $args['bulk_capped'] = 1;
    }
    $redirect = add_query_arg($args, $redirect);

    wp_safe_redirect($redirect);
    exit;
}

function peracrm_handle_pipeline_bulk_action()
{
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_pipeline_bulk_action');

    $is_admin = current_user_can('manage_options');
    $filters = peracrm_pipeline_sanitize_view_filters(wp_unslash($_POST), $is_admin);
    $view_id = isset($_POST['view_id']) ? sanitize_text_field(wp_unslash($_POST['view_id'])) : '';
    if ($view_id !== '') {
        $filters['view_id'] = $view_id;
    }
    $redirect = peracrm_pipeline_build_base_url($filters);

    $client_ids_raw = isset($_POST['client_ids']) ? (array) wp_unslash($_POST['client_ids']) : [];
    $client_ids = array_values(array_filter(array_map('absint', $client_ids_raw)));
    $total_client_ids = count($client_ids);
    $max_batch = 200;
    $capped = false;
    $skipped = 0;
    if ($total_client_ids > $max_batch) {
        $capped = true;
        $skipped = $total_client_ids - $max_batch;
        $client_ids = array_slice($client_ids, 0, $max_batch);
    }

    $action_key = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : '';
    $allowed_actions = ['move_stage', 'reassign_advisor', 'add_reminder'];
    if (!in_array($action_key, $allowed_actions, true)) {
        peracrm_pipeline_bulk_redirect($redirect, $action_key, 0, $total_client_ids, $capped);
    }

    if (empty($client_ids)) {
        peracrm_pipeline_bulk_redirect($redirect, $action_key, 0, 0, $capped);
    }

    $done = 0;
    $failed = $skipped;
    $actor_id = get_current_user_id();
    $can_reassign = peracrm_admin_user_can_reassign();
    $can_override = $is_admin || ($action_key === 'reassign_advisor' && $can_reassign);

    $allowed_statuses = peracrm_pipeline_allowed_stage_values();
    $to_status = '';
    $new_advisor = 0;
    $due_at_mysql = '';
    $reminder_note = '';
    $reminder_advisor_id = $actor_id;

    if ('move_stage' === $action_key) {
        $to_status = isset($_POST['to_status']) ? sanitize_key(wp_unslash($_POST['to_status'])) : '';
        if (!in_array($to_status, $allowed_statuses, true)) {
            peracrm_pipeline_bulk_redirect($redirect, $action_key, 0, $total_client_ids, $capped);
        }
    }

    if ('reassign_advisor' === $action_key) {
        if (!$can_reassign) {
            peracrm_pipeline_bulk_redirect($redirect, $action_key, 0, $total_client_ids, $capped);
        }
        $new_advisor = isset($_POST['advisor_user_id']) ? absint($_POST['advisor_user_id']) : 0;
        if ($new_advisor > 0 && !peracrm_user_is_staff($new_advisor)) {
            peracrm_pipeline_bulk_redirect($redirect, $action_key, 0, $total_client_ids, $capped);
        }
    }

    if ('add_reminder' === $action_key) {
        $has_reminders = function_exists('peracrm_reminders_table_exists') && peracrm_reminders_table_exists();
        if (!$has_reminders || !function_exists('peracrm_reminder_add')) {
            peracrm_pipeline_bulk_redirect($redirect, $action_key, 0, $total_client_ids, $capped);
        }
        $due_at_raw = isset($_POST['bulk_due_at']) ? wp_unslash($_POST['bulk_due_at']) : '';
        $due_at_mysql = peracrm_admin_parse_datetime($due_at_raw);
        if ($due_at_mysql === '') {
            peracrm_pipeline_bulk_redirect($redirect, $action_key, 0, $total_client_ids, $capped);
        }
        $reminder_note = isset($_POST['bulk_note']) ? sanitize_textarea_field(wp_unslash($_POST['bulk_note'])) : '';
        $reminder_note = trim($reminder_note);
        if (strlen($reminder_note) > 5000) {
            $reminder_note = substr($reminder_note, 0, 5000);
        }
        if ($is_admin) {
            $reminder_advisor_id = isset($_POST['reminder_advisor_user_id']) ? absint($_POST['reminder_advisor_user_id']) : 0;
            if ($reminder_advisor_id > 0 && !peracrm_user_is_staff($reminder_advisor_id)) {
                peracrm_pipeline_bulk_redirect($redirect, $action_key, 0, $total_client_ids, $capped);
            }
            if ($reminder_advisor_id <= 0) {
                $reminder_advisor_id = $actor_id;
            }
        }
    }

    foreach ($client_ids as $client_id) {
        $client = peracrm_admin_get_client($client_id);
        if (!$client) {
            $failed++;
            continue;
        }

        if (!current_user_can('edit_post', $client_id)) {
            $failed++;
            continue;
        }

        if (!$can_override) {
            $assigned_id = peracrm_admin_get_assigned_advisor_id_for_client($client_id);
            if ($assigned_id <= 0 || $assigned_id !== $actor_id) {
                $failed++;
                continue;
            }
        }

        if ('move_stage' === $action_key) {
            $party = function_exists('peracrm_party_get') ? peracrm_party_get($client_id) : [];
            $from_status = sanitize_key((string) ($party['lead_pipeline_stage'] ?? ''));
            if (!in_array($from_status, $allowed_statuses, true)) {
                $from_status = peracrm_pipeline_default_stage_key();
            }
            if ($from_status === $to_status) {
                $failed++;
                continue;
            }

            if (!function_exists('peracrm_party_upsert_status')) {
                $failed++;
                continue;
            }

            $stage_saved = peracrm_party_upsert_status($client_id, [
                'lead_pipeline_stage' => $to_status,
                'engagement_state' => $party['engagement_state'] ?? 'engaged',
                'disposition' => $party['disposition'] ?? 'none',
                'lead_stage_updated_at' => peracrm_now_mysql(),
            ]);

            if (function_exists('peracrm_party_get')) {
                $updated_party = peracrm_party_get($client_id);
                $updated_stage = sanitize_key((string) ($updated_party['lead_pipeline_stage'] ?? ''));
                $stage_verified = ($updated_stage === $to_status);
            } else {
                $stage_verified = (true === $stage_saved);
            }

            if (!$stage_verified) {
                $failed++;
                continue;
            }

            $can_log = function_exists('peracrm_activity_table_exists') && peracrm_activity_table_exists();
            if ($can_log && function_exists('peracrm_activity_insert')) {
                $payload = [
                    'from' => $from_status,
                    'to' => $to_status,
                    'actor_user_id' => $actor_id,
                    'context' => 'pipeline_bulk',
                ];
                if (function_exists('peracrm_log_event')) {
                    peracrm_log_event($client_id, 'status_changed', $payload);
                } else {
                    peracrm_activity_insert($client_id, 'status_changed', $payload);
                }
            }

            $done++;
            continue;
        }

        if ('reassign_advisor' === $action_key) {
            $old_advisor = function_exists('peracrm_client_get_assigned_advisor_id')
                ? (int) peracrm_client_get_assigned_advisor_id($client_id)
                : 0;

            $update_keys = ['assigned_advisor_user_id', 'crm_assigned_advisor'];
            foreach ($update_keys as $meta_key) {
                if ($new_advisor > 0) {
                    update_post_meta($client_id, $meta_key, $new_advisor);
                } else {
                    delete_post_meta($client_id, $meta_key);
                }
            }

            if ($new_advisor !== $old_advisor) {
                $can_log = function_exists('peracrm_activity_table_exists') && peracrm_activity_table_exists();
                if ($can_log && function_exists('peracrm_log_event')) {
                    peracrm_log_event($client_id, 'advisor_reassigned', [
                        'from' => $old_advisor,
                        'to' => $new_advisor,
                    ]);
                }
            }

            $done++;
            continue;
        }

        if ('add_reminder' === $action_key) {
            $assigned_advisor = $reminder_advisor_id > 0 ? $reminder_advisor_id : $actor_id;
            $reminder_id = peracrm_reminder_add($client_id, $assigned_advisor, $due_at_mysql, $reminder_note);
            if (!$reminder_id) {
                $failed++;
                continue;
            }

            $done++;
        }
    }

    peracrm_pipeline_bulk_redirect($redirect, $action_key, $done, $failed, $capped);
}

function peracrm_csv_safe_cell($value)
{
    if (!is_string($value)) {
        return $value;
    }

    if (preg_match('/^[=+\\-@]/', $value)) {
        return "'" . $value;
    }

    return $value;
}

function peracrm_handle_pipeline_export_csv()
{
    check_admin_referer('peracrm_pipeline_export_csv');

    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    if (!current_user_can('edit_crm_clients')) {
        wp_die('Unauthorized');
    }

    $is_admin = current_user_can('manage_options');
    $can_manage_all = $is_admin || current_user_can('peracrm_manage_all_clients');

    $client_type_options = peracrm_pipeline_client_type_options();
    $health_options = peracrm_pipeline_health_options();

    $client_type = isset($_POST['client_type']) ? sanitize_key(wp_unslash($_POST['client_type'])) : 'all';
    if (!isset($client_type_options[$client_type])) {
        $client_type = 'all';
    }

    $health_filter = isset($_POST['health']) ? sanitize_key(wp_unslash($_POST['health'])) : 'all';
    if (!isset($health_options[$health_filter])) {
        $health_filter = 'all';
    }

    $advisor_id = isset($_POST['advisor_id']) ? absint($_POST['advisor_id']) : 0;
    if (!$can_manage_all) {
        $advisor_id = get_current_user_id();
    } elseif ($advisor_id > 0 && !peracrm_user_is_staff($advisor_id)) {
        $advisor_id = 0;
    }

    $scope_advisor_id = $advisor_id > 0 ? $advisor_id : 0;
    if (!$can_manage_all) {
        $scope_advisor_id = get_current_user_id();
    }

    $status_labels = peracrm_pipeline_status_labels();
    $status_keys = array_keys($status_labels);
    $meta_query = peracrm_pipeline_build_base_meta_query($client_type, $scope_advisor_id);
    $meta_query[] = [
        'key' => '_peracrm_status',
        'value' => $status_keys,
        'compare' => 'IN',
    ];

    $has_activity_table = function_exists('peracrm_activity_table_exists') && peracrm_activity_table_exists();
    $has_reminders_table = function_exists('peracrm_reminders_table_exists') && peracrm_reminders_table_exists();

    $max_rows = 500;
    $batch_size = 200;
    $matched_ids = [];
    $health_map = [];
    $capped = false;
    $paged = 1;

    while (count($matched_ids) < $max_rows) {
        $query = new WP_Query([
            'post_type' => 'crm_client',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => $batch_size,
            'paged' => $paged,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => $meta_query,
        ]);

        $page_ids = array_values(array_map('intval', $query->posts));
        if (empty($page_ids)) {
            break;
        }

        update_meta_cache('post', $page_ids);
        if ($has_activity_table && function_exists('peracrm_client_health_prime_cache')) {
            peracrm_client_health_prime_cache($page_ids);
        }

        foreach ($page_ids as $client_id) {
            if ($has_activity_table && function_exists('peracrm_client_health_get')) {
                $health_map[$client_id] = peracrm_client_health_get($client_id);
            }

            if ($health_filter !== 'all') {
                $health_key = isset($health_map[$client_id]['key']) ? $health_map[$client_id]['key'] : 'none';
                if ($health_key !== $health_filter) {
                    continue;
                }
            }

            $matched_ids[] = $client_id;
            if (count($matched_ids) >= $max_rows) {
                if ($query->max_num_pages > $paged || count($page_ids) > array_search($client_id, $page_ids, true) + 1) {
                    $capped = true;
                }
                break 2;
            }
        }

        if ($paged >= (int) $query->max_num_pages) {
            break;
        }
        $paged++;
    }

    $matched_ids = array_values(array_unique($matched_ids));
    if ($has_activity_table && function_exists('peracrm_client_health_prime_cache')) {
        peracrm_client_health_prime_cache($matched_ids);
        foreach ($matched_ids as $client_id) {
            if (!isset($health_map[$client_id]) && function_exists('peracrm_client_health_get')) {
                $health_map[$client_id] = peracrm_client_health_get($client_id);
            }
        }
    }

    $reminder_counts = ['open_count' => [], 'overdue_count' => [], 'next_due' => []];
    if ($has_reminders_table && function_exists('peracrm_reminders_counts_by_client_ids')) {
        $reminder_scope = $scope_advisor_id > 0 ? $scope_advisor_id : null;
        $reminder_counts = peracrm_reminders_counts_by_client_ids($matched_ids, $reminder_scope);
    }

    $assigned_advisor_ids = [];
    foreach ($matched_ids as $client_id) {
        if (function_exists('peracrm_client_get_assigned_advisor_id')) {
            $assigned_id = (int) peracrm_client_get_assigned_advisor_id($client_id);
            if ($assigned_id > 0) {
                $assigned_advisor_ids[] = $assigned_id;
            }
        }
    }
    $assigned_advisor_ids = array_values(array_unique($assigned_advisor_ids));
    $advisor_map = [];
    if (!empty($assigned_advisor_ids)) {
        $advisors = get_users([
            'include' => $assigned_advisor_ids,
            'fields' => ['ID', 'display_name'],
        ]);
        foreach ($advisors as $advisor) {
            $advisor_map[(int) $advisor->ID] = $advisor->display_name;
        }
    }

    nocache_headers();
    $filename = sprintf('peracrm-pipeline-export-%s.csv', wp_date('Y-m-d'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    if ($capped) {
        fputcsv($output, ['NOTE: Export capped to first 500 rows.']);
    }

    fputcsv($output, [
        'client_id',
        'client_name',
        'status',
        'client_type',
        'assigned_advisor_id',
        'assigned_advisor_name',
        'budget_min_usd',
        'budget_max_usd',
        'phone',
        'email',
        'health',
        'last_activity',
        'open_reminders',
        'overdue_reminders',
        'next_due',
    ]);

    $open_counts = isset($reminder_counts['open_count']) ? $reminder_counts['open_count'] : [];
    $overdue_counts = isset($reminder_counts['overdue_count']) ? $reminder_counts['overdue_count'] : [];
    $next_due_map = isset($reminder_counts['next_due']) ? $reminder_counts['next_due'] : [];

    foreach ($matched_ids as $client_id) {
        $client_name = get_the_title($client_id);
        $status = get_post_meta($client_id, '_peracrm_status', true);
        $client_type_value = get_post_meta($client_id, '_peracrm_client_type', true);
        $assigned_id = function_exists('peracrm_client_get_assigned_advisor_id')
            ? (int) peracrm_client_get_assigned_advisor_id($client_id)
            : 0;
        $assigned_name = $assigned_id > 0 && isset($advisor_map[$assigned_id]) ? $advisor_map[$assigned_id] : '';
        $budget_min = get_post_meta($client_id, '_peracrm_budget_min_usd', true);
        $budget_max = get_post_meta($client_id, '_peracrm_budget_max_usd', true);
        $phone = get_post_meta($client_id, '_peracrm_phone', true);
        $email = get_post_meta($client_id, '_peracrm_email', true);

        $health_label = 'None';
        $last_activity = '';
        if ($has_activity_table && isset($health_map[$client_id])) {
            $health_label = isset($health_map[$client_id]['label']) ? $health_map[$client_id]['label'] : 'None';
            $last_activity_ts = isset($health_map[$client_id]['last_activity_ts']) ? (int) $health_map[$client_id]['last_activity_ts'] : 0;
            if ($last_activity_ts) {
                $last_activity = wp_date('c', $last_activity_ts);
            }
        }

        $open = $has_reminders_table && isset($open_counts[$client_id]) ? (int) $open_counts[$client_id] : 0;
        $overdue = $has_reminders_table && isset($overdue_counts[$client_id]) ? (int) $overdue_counts[$client_id] : 0;
        $next_due = $has_reminders_table && isset($next_due_map[$client_id]) ? $next_due_map[$client_id] : '';

        fputcsv($output, [
            $client_id,
            peracrm_csv_safe_cell($client_name),
            $status,
            $client_type_value,
            $assigned_id,
            peracrm_csv_safe_cell($assigned_name),
            $budget_min,
            $budget_max,
            peracrm_csv_safe_cell($phone),
            peracrm_csv_safe_cell($email),
            $health_label,
            $last_activity,
            $open,
            $overdue,
            $next_due,
        ]);
    }

    fclose($output);
    exit;
}

function peracrm_handle_link_user()
{
    $client_id = isset($_POST['peracrm_client_id']) ? (int) $_POST['peracrm_client_id'] : 0;
    $client = peracrm_admin_get_client($client_id);
    if (!$client) {
        wp_die('Invalid client');
    }

    if (!current_user_can('edit_post', $client_id)) {
        wp_die('Unauthorized');
    }

    $link_user_nonce = isset($_POST['peracrm_link_user_nonce']) ? sanitize_text_field(wp_unslash($_POST['peracrm_link_user_nonce'])) : '';
    if ($link_user_nonce === '' || !wp_verify_nonce($link_user_nonce, 'peracrm_link_user')) {
        wp_die('Invalid nonce');
    }

    $search_term = isset($_POST['peracrm_user_search']) ? wp_unslash($_POST['peracrm_user_search']) : '';
    $search_term = sanitize_text_field($search_term);
    $search_term = trim($search_term);

    $user = null;
    if ($search_term !== '' && is_email($search_term)) {
        $user = get_user_by('email', $search_term);
    }

    if (!$user) {
        $users = peracrm_admin_search_user_for_link($search_term, $client_id);
        if (empty($users)) {
            peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'user_missing');
        }

        if (count($users) > 1) {
            peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'user_ambiguous');
        }

        $user = $users[0];
    }
    $user_id = (int) $user->ID;
    if ($user_id <= 0) {
        peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'user_missing');
    }

    $existing_client_id = (int) get_user_meta($user_id, 'crm_client_id', true);
    if ($existing_client_id > 0 && $existing_client_id !== $client_id) {
        peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'user_already_linked');
    }

    $existing_user_id = peracrm_admin_find_linked_user_id($client_id);
    if ($existing_user_id > 0 && $existing_user_id !== $user_id) {
        peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'client_already_linked');
    }

    update_user_meta($user_id, 'crm_client_id', $client_id);
    $linked = peracrm_admin_update_client_linked_user_id($client_id, $user_id);

    if (!$linked) {
        if ($existing_client_id > 0) {
            update_user_meta($user_id, 'crm_client_id', $existing_client_id);
        } else {
            delete_user_meta($user_id, 'crm_client_id');
        }
        peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'link_failed');
    }

    peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'link_success');
}

function peracrm_handle_unlink_user()
{
    $client_id = isset($_POST['peracrm_client_id']) ? (int) $_POST['peracrm_client_id'] : 0;
    $client = peracrm_admin_get_client($client_id);
    if (!$client) {
        wp_die('Invalid client');
    }

    if (!current_user_can('edit_post', $client_id)) {
        wp_die('Unauthorized');
    }

    $unlink_nonce = isset($_REQUEST['peracrm_unlink_user_nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['peracrm_unlink_user_nonce'])) : '';
    if ($unlink_nonce === '' || !wp_verify_nonce($unlink_nonce, 'peracrm_unlink_user')) {
        wp_die('Invalid nonce');
    }

    $linked_user_id = peracrm_admin_find_linked_user_id($client_id);
    if ($linked_user_id <= 0) {
        peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'unlink_missing');
    }

    $updated = peracrm_admin_update_client_linked_user_id($client_id, 0);
    if (!$updated) {
        peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'unlink_failed');
    }

    $current_client_id = (int) get_user_meta($linked_user_id, 'crm_client_id', true);
    if ($current_client_id === $client_id) {
        $deleted = delete_user_meta($linked_user_id, 'crm_client_id');
        if (!$deleted) {
            peracrm_admin_update_client_linked_user_id($client_id, $linked_user_id);
            peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'unlink_failed');
        }
    }

    peracrm_admin_redirect_with_notice(peracrm_admin_client_screen_url($client_id), 'unlink_success');
}

function peracrm_admin_user_can_reassign()
{
    return current_user_can('manage_options') || current_user_can('peracrm_manage_assignments');
}

function peracrm_handle_save_client_profile()
{
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to update CRM profiles.');
    }

    $client_id = isset($_POST['peracrm_client_id']) ? absint($_POST['peracrm_client_id']) : 0;
    if ($client_id <= 0) {
        wp_die('Invalid client.');
    }

    check_admin_referer('peracrm_save_client_profile', 'peracrm_save_client_profile_nonce');

    $post_type = get_post_type($client_id);
    if ($post_type !== 'crm_client') {
        wp_die('Invalid client.');
    }

    if (!current_user_can('edit_post', $client_id)) {
        wp_die('You do not have permission to edit this client.');
    }

    $should_log = defined('WP_DEBUG') && WP_DEBUG;
    if ($should_log) {
        error_log('[peracrm] peracrm_handle_save_client_profile start post_id=' . $client_id);
    }

    $status = isset($_POST['peracrm_status']) ? sanitize_key(wp_unslash($_POST['peracrm_status'])) : '';
    $client_type = isset($_POST['peracrm_client_type']) ? sanitize_key(wp_unslash($_POST['peracrm_client_type'])) : '';
    $preferred_contact = isset($_POST['peracrm_preferred_contact']) ? sanitize_key(wp_unslash($_POST['peracrm_preferred_contact'])) : '';

    $budget_min = isset($_POST['peracrm_budget_min_usd']) ? wp_unslash($_POST['peracrm_budget_min_usd']) : '';
    $budget_max = isset($_POST['peracrm_budget_max_usd']) ? wp_unslash($_POST['peracrm_budget_max_usd']) : '';

    $phone_raw = isset($_POST['peracrm_phone']) ? sanitize_text_field(wp_unslash($_POST['peracrm_phone'])) : '';
    $phone = preg_replace('/[^0-9+]/', '', $phone_raw);

    $email_raw = isset($_POST['peracrm_email']) ? sanitize_text_field(wp_unslash($_POST['peracrm_email'])) : '';
    $email = sanitize_email($email_raw);

    $data = [
        'status' => $status,
        'client_type' => $client_type,
        'preferred_contact' => $preferred_contact,
        'budget_min_usd' => $budget_min,
        'budget_max_usd' => $budget_max,
        'phone' => $phone,
        'email' => $email,
    ];

    $success = function_exists('peracrm_client_update_profile')
        ? peracrm_client_update_profile($client_id, $data)
        : false;

    if ($success && function_exists('peracrm_map_legacy_status_to_party_fields') && function_exists('peracrm_party_upsert_status')) {
        peracrm_party_upsert_status($client_id, peracrm_map_legacy_status_to_party_fields($status));
    }

    $redirect = wp_get_referer();
    if (!$redirect) {
        $redirect = add_query_arg(
            [
                'post' => $client_id,
                'action' => 'edit',
            ],
            admin_url('post.php')
        );
    }

    if (!$success) {
        if ($should_log) {
            error_log('[peracrm] peracrm_handle_save_client_profile end post_id=' . $client_id . ' success=0');
        }
        peracrm_admin_redirect_with_notice($redirect, 'profile_failed');
    }

    if ($should_log) {
        error_log('[peracrm] peracrm_handle_save_client_profile end post_id=' . $client_id . ' success=1');
    }

    peracrm_admin_redirect_with_notice($redirect, 'profile_saved');
}

function peracrm_handle_reassign_client_advisor()
{
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to reassign advisors.');
    }

    $client_id = isset($_POST['peracrm_client_id']) ? absint($_POST['peracrm_client_id']) : 0;
    if ($client_id <= 0) {
        wp_die('Invalid client.');
    }

    check_admin_referer('peracrm_reassign_client_advisor', 'peracrm_reassign_client_advisor_nonce');

    if (!current_user_can('edit_post', $client_id) || !peracrm_admin_user_can_reassign()) {
        wp_die('You do not have permission to reassign this client.');
    }

    $new_advisor = isset($_POST['peracrm_assigned_advisor']) ? absint($_POST['peracrm_assigned_advisor']) : 0;
    if ($new_advisor > 0 && !peracrm_user_is_staff($new_advisor)) {
        wp_die('Invalid advisor selection.');
    }

    $old_advisor = function_exists('peracrm_client_get_assigned_advisor_id')
        ? (int) peracrm_client_get_assigned_advisor_id($client_id)
        : 0;

    $update_keys = ['assigned_advisor_user_id', 'crm_assigned_advisor'];
    foreach ($update_keys as $meta_key) {
        if ($new_advisor > 0) {
            update_post_meta($client_id, $meta_key, $new_advisor);
        } else {
            delete_post_meta($client_id, $meta_key);
        }
    }

    if ($new_advisor !== $old_advisor) {
        $can_log = function_exists('peracrm_activity_table_exists') && peracrm_activity_table_exists();
        if ($can_log && function_exists('peracrm_log_event')) {
            peracrm_log_event($client_id, 'advisor_reassigned', [
                'from' => $old_advisor,
                'to' => $new_advisor,
            ]);
        }
    }

    $redirect = wp_get_referer();
    if (!$redirect) {
        $redirect = add_query_arg(
            [
                'post' => $client_id,
                'action' => 'edit',
            ],
            admin_url('post.php')
        );
    }

    peracrm_admin_redirect_with_notice($redirect, 'advisor_reassigned');
}

function peracrm_handle_add_reminder()
{
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_add_reminder', 'peracrm_add_reminder_nonce');

    $client_id = isset($_POST['peracrm_client_id']) ? (int) $_POST['peracrm_client_id'] : 0;
    $client = peracrm_admin_get_client($client_id);
    if (!$client) {
        wp_die('Invalid client');
    }

    $current_user_id = get_current_user_id();
    $assigned_advisor_id = function_exists('peracrm_client_get_assigned_advisor_id')
        ? (int) peracrm_client_get_assigned_advisor_id($client_id)
        : 0;
    $can_manage = current_user_can('manage_options') || current_user_can('peracrm_manage_all_reminders');
    if (!$can_manage && $assigned_advisor_id !== $current_user_id) {
        wp_die('Unauthorized');
    }

    $due_at_raw = isset($_POST['peracrm_due_at']) ? wp_unslash($_POST['peracrm_due_at']) : '';
    $due_at_mysql = peracrm_admin_parse_datetime($due_at_raw);
    if ($due_at_mysql === '') {
        peracrm_admin_redirect_with_notice(get_edit_post_link($client_id, 'raw'), 'reminder_missing');
    }

    $note = isset($_POST['peracrm_reminder_note']) ? sanitize_textarea_field(wp_unslash($_POST['peracrm_reminder_note'])) : '';

    $note = substr($note, 0, 5000);
    $assigned_advisor = function_exists('peracrm_client_get_assigned_advisor_id')
        ? (int) peracrm_client_get_assigned_advisor_id($client_id)
        : 0;
    if ($assigned_advisor <= 0) {
        $assigned_advisor = get_current_user_id();
    }

    $reminder_id = peracrm_reminder_add($client_id, $assigned_advisor, $due_at_mysql, $note);
    if (!$reminder_id) {
        peracrm_admin_redirect_with_notice(get_edit_post_link($client_id, 'raw'), 'reminder_failed');
    }

    peracrm_admin_redirect_with_notice(get_edit_post_link($client_id, 'raw'), 'reminder_added');
}

function peracrm_handle_mark_reminder_done()
{
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_mark_reminder_done');

    $reminder_id = isset($_POST['peracrm_reminder_id']) ? (int) $_POST['peracrm_reminder_id'] : 0;
    $reminder = peracrm_admin_get_reminder($reminder_id);
    if (!$reminder) {
        wp_die('Invalid reminder');
    }

    $actor_id = get_current_user_id();
    $assigned_advisor_id = isset($reminder['advisor_user_id']) ? (int) $reminder['advisor_user_id'] : 0;
    $can_manage = current_user_can('manage_options') || current_user_can('peracrm_manage_all_reminders');
    if (!$can_manage && $assigned_advisor_id !== $actor_id) {
        wp_die('Unauthorized');
    }

    $client_id = (int) $reminder['client_id'];
    $client = peracrm_admin_get_client($client_id);
    if (!$client) {
        wp_die('Invalid client');
    }

    $redirect = isset($_POST['peracrm_redirect']) ? esc_url_raw(wp_unslash($_POST['peracrm_redirect'])) : '';
    if ($redirect === '') {
        $redirect = get_edit_post_link($client_id, 'raw');
    }

    $success = peracrm_reminder_update_status($reminder_id, 'done', get_current_user_id());
    if (!$success) {
        peracrm_admin_redirect_with_notice($redirect, 'reminder_failed');
    }

    peracrm_admin_redirect_with_notice($redirect, 'reminder_done');
}

function peracrm_handle_update_reminder_status()
{
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce');

    $reminder_id = isset($_POST['peracrm_reminder_id']) ? (int) $_POST['peracrm_reminder_id'] : 0;
    $reminder = peracrm_admin_get_reminder($reminder_id);
    if (!$reminder) {
        wp_die('Invalid reminder');
    }

    $actor_id = get_current_user_id();
    $assigned_advisor_id = isset($reminder['advisor_user_id']) ? (int) $reminder['advisor_user_id'] : 0;
    $can_manage = current_user_can('manage_options') || current_user_can('peracrm_manage_all_reminders');
    if (!$can_manage && $assigned_advisor_id !== $actor_id) {
        wp_die('Unauthorized');
    }

    $client_id = (int) $reminder['client_id'];
    $client = peracrm_admin_get_client($client_id);
    if (!$client) {
        wp_die('Invalid client');
    }

    $redirect = isset($_POST['peracrm_redirect']) ? esc_url_raw(wp_unslash($_POST['peracrm_redirect'])) : '';
    if ($redirect === '') {
        $redirect = get_edit_post_link($client_id, 'raw');
    }

    $status = isset($_POST['peracrm_status']) ? sanitize_key(wp_unslash($_POST['peracrm_status'])) : '';
    $status = peracrm_reminders_sanitize_status($status);
    if ($status === '') {
        peracrm_admin_redirect_with_notice($redirect, 'reminder_invalid_status');
    }

    $success = peracrm_reminder_update_status($reminder_id, $status, get_current_user_id());
    if (!$success) {
        peracrm_admin_redirect_with_notice($redirect, 'reminder_failed');
    }

    if ($status === 'done') {
        peracrm_admin_redirect_with_notice($redirect, 'reminder_done');
    }

    if ($status === 'dismissed') {
        peracrm_admin_redirect_with_notice($redirect, 'reminder_dismissed');
    }

    peracrm_admin_redirect_with_notice($redirect, 'reminder_updated');
}

function peracrm_admin_notices()
{
    if (!isset($_GET['peracrm_notice'])) {
        return;
    }

    $is_crm_edit_screen = isset($_GET['post'])
        && get_post_type((int) $_GET['post']) === 'crm_client'
        && isset($_GET['action'])
        && sanitize_key(wp_unslash($_GET['action'])) === 'edit';

    $is_crm_client_view = isset($_GET['page'])
        && sanitize_key(wp_unslash($_GET['page'])) === 'peracrm-client-view';

    if (!$is_crm_edit_screen && !$is_crm_client_view) {
        return;
    }

    $notice = sanitize_key(wp_unslash($_GET['peracrm_notice']));
    $messages = [
        'note_added' => ['success', 'CRM note added.'],
        'note_missing' => ['error', 'Please add a note before saving.'],
        'note_failed' => ['error', 'Unable to save CRM note.'],
        'reminder_added' => ['success', 'CRM reminder created.'],
        'reminder_missing' => ['error', 'Please provide a due date for the reminder.'],
        'reminder_failed' => ['error', 'Unable to update CRM reminder.'],
        'reminder_done' => ['success', 'CRM reminder marked as done.'],
        'reminder_dismissed' => ['success', 'CRM reminder dismissed.'],
        'reminder_updated' => ['success', 'CRM reminder updated.'],
        'reminder_invalid_status' => ['error', 'Please choose a valid reminder status.'],
        'link_success' => ['success', 'User linked to CRM client.'],
        'link_failed' => ['error', 'Unable to link user to CRM client.'],
        'unlink_success' => ['success', 'User unlinked from CRM client.'],
        'unlink_failed' => ['error', 'Unable to unlink user from CRM client.'],
        'user_missing' => ['error', 'Please enter a valid user email or username.'],
        'user_ambiguous' => ['error', 'Multiple users matched. Please use a more specific search.'],
        'user_already_linked' => ['error', 'That user is already linked to another CRM client.'],
        'client_already_linked' => ['error', 'This CRM client is already linked to another user.'],
        'conflict' => ['error', 'This CRM client is already linked to another user.'],
        'unlink_missing' => ['error', 'This CRM client does not have a linked user.'],
        'profile_saved' => ['success', 'Client profile updated.'],
        'profile_failed' => ['error', 'Unable to update client profile.'],
        'deal_saved' => ['success', 'Deal saved.'],
        'deal_failed' => ['error', 'Unable to save deal.'],
        'deal_validation_failed' => ['error', 'Deal validation failed.'],
        'advisor_reassigned' => ['success', 'Advisor reassigned.'],
        'pipeline_view_saved' => ['success', 'Pipeline view saved.'],
        'pipeline_view_deleted' => ['success', 'Pipeline view deleted.'],
        'pipeline_view_missing' => ['error', 'Pipeline view not found.'],
        'pipeline_view_name_missing' => ['error', 'Please enter a view name.'],
    ];

    if (!isset($messages[$notice])) {
        return;
    }

    [$class, $message] = $messages[$notice];

    if ($notice === 'deal_validation_failed' && isset($_GET['peracrm_deal_error'])) {
        $custom_message = sanitize_text_field(rawurldecode(wp_unslash($_GET['peracrm_deal_error'])));
        if ($custom_message !== '') {
            $message = $custom_message;
        }
    }

    printf(
        '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
        esc_attr($class),
        esc_html($message)
    );
}

function peracrm_admin_add_client_columns($columns)
{
    $columns['peracrm_type'] = 'Type';
    $columns['peracrm_pipeline_stage'] = 'Pipeline Stage';
    $columns['peracrm_engagement_state'] = 'Engagement';
    $columns['peracrm_disposition'] = 'Disposition';
    $columns['peracrm_account'] = 'Account';
    $columns['peracrm_health'] = 'Health';
    $columns['last_activity'] = 'Last activity';
    return $columns;
}

function peracrm_admin_client_sortable_columns($columns)
{
    $columns['last_activity'] = 'last_activity';
    return $columns;
}

function peracrm_admin_client_filters()
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || 'edit-crm_client' !== $screen->id) {
        return;
    }

    echo '<label for="peracrm-party-stage" class="screen-reader-text">Pipeline Stage</label>';
    echo '<select name="lead_pipeline_stage" id="peracrm-party-stage"><option value="">All pipeline stages</option>';
    $selected_stage = peracrm_admin_get_party_stage_filter();
    foreach (peracrm_party_stage_options() as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($selected_stage, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';

    echo '<label for="peracrm-party-engagement" class="screen-reader-text">Engagement</label>';
    echo '<select name="engagement_state" id="peracrm-party-engagement"><option value="">All engagement</option>';
    $selected_engagement = peracrm_admin_get_party_engagement_filter();
    foreach (peracrm_party_engagement_options() as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($selected_engagement, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';

    echo '<label for="peracrm-party-disposition" class="screen-reader-text">Disposition</label>';
    echo '<select name="disposition" id="peracrm-party-disposition"><option value="">All disposition</option>';
    $selected_disposition = peracrm_admin_get_party_disposition_filter();
    foreach (peracrm_party_disposition_options() as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($selected_disposition, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';

    $type = peracrm_admin_get_derived_type_filter();
    echo '<label for="peracrm-party-type" class="screen-reader-text">Type</label>';
    echo '<select name="peracrm_type" id="peracrm-party-type">';
    echo '<option value="">All types</option>';
    echo '<option value="lead"' . selected($type, 'lead', false) . '>Lead</option>';
    echo '<option value="client"' . selected($type, 'client', false) . '>Client</option>';
    echo '</select>';
}

function peracrm_admin_client_list_query($query)
{
    $context = peracrm_admin_client_list_context($query);
    if (!$context['is_client_list']) {
        return;
    }

    if ($context['health'] !== '') {
        if (!$context['has_reminders_table'] && in_array($context['health'], ['at_risk', 'hot'], true)) {
            $query->set('post__in', [0]);
            return;
        }
        if (!$context['has_activity_table'] && in_array($context['health'], ['hot', 'warm'], true)) {
            $query->set('post__in', [0]);
            return;
        }
        if (!$context['has_activity_table'] && !$context['has_reminders_table'] && 'none' !== $context['health']) {
            $query->set('post__in', [0]);
            return;
        }
    }

    if (!$context['has_activity_table'] && 'none' === $context['engagement']) {
        $query->set('post__in', [0]);
    }
}

function peracrm_admin_client_list_clauses($clauses, $query)
{
    $context = peracrm_admin_client_list_context($query);
    if (!$context['is_client_list']) {
        return $clauses;
    }

    global $wpdb;

    $needs_activity = $context['has_activity_table'] && ('last_activity' === $context['orderby'] || $context['engagement'] !== '' || $context['health'] !== '');
    $needs_reminders = $context['health'] !== '' && $context['has_reminders_table'];

    $activity_alias = 'peracrm_activity';
    if ($needs_activity) {
        $activity_table = peracrm_table('crm_activity');
        if (false === strpos($clauses['join'], " {$activity_table} ")) {
            $clauses['join'] .= " LEFT JOIN {$activity_table} AS {$activity_alias} ON {$wpdb->posts}.ID = {$activity_alias}.client_id";
        }
    }

    $reminders_alias = 'peracrm_reminders';
    if ($needs_reminders) {
        $reminders_table = peracrm_table('crm_reminders');
        $now_mysql = current_time('mysql');
        $reminders_subquery = $wpdb->prepare(
            "(SELECT client_id,
                     SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) AS open_count,
                     SUM(CASE WHEN status = %s AND due_at < %s THEN 1 ELSE 0 END) AS overdue_count
              FROM {$reminders_table}
              GROUP BY client_id) AS {$reminders_alias}",
            'pending',
            'pending',
            $now_mysql
        );
        if (false === strpos($clauses['join'], " {$reminders_alias}")) {
            $clauses['join'] .= " LEFT JOIN {$reminders_subquery} ON {$wpdb->posts}.ID = {$reminders_alias}.client_id";
        }
    }

    $last_activity_expr = $needs_activity ? "MAX({$activity_alias}.created_at)" : 'NULL';
    $open_expr = $needs_reminders ? "COALESCE({$reminders_alias}.open_count, 0)" : '0';
    $overdue_expr = $needs_reminders ? "COALESCE({$reminders_alias}.overdue_count, 0)" : '0';

    if ($needs_activity && false === strpos($clauses['fields'], 'peracrm_last_activity_at')) {
        $clauses['fields'] .= ", {$last_activity_expr} AS peracrm_last_activity_at";
    }
    if ($context['health'] !== '') {
        if (false === strpos($clauses['fields'], 'peracrm_open_reminders')) {
            $clauses['fields'] .= ", {$open_expr} AS peracrm_open_reminders";
            $clauses['fields'] .= ", {$overdue_expr} AS peracrm_overdue_reminders";
        }

        $now = current_time('timestamp');
        $seven_days = wp_date('Y-m-d H:i:s', $now - DAY_IN_SECONDS * 7);
        $fourteen_days = wp_date('Y-m-d H:i:s', $now - DAY_IN_SECONDS * 14);
        $thirty_days = wp_date('Y-m-d H:i:s', $now - DAY_IN_SECONDS * 30);

        $case_expr = $wpdb->prepare(
            "CASE
                WHEN {$overdue_expr} > 0 THEN 'at_risk'
                WHEN {$last_activity_expr} >= %s AND {$open_expr} > 0 AND {$overdue_expr} = 0 THEN 'hot'
                WHEN {$last_activity_expr} >= %s AND {$overdue_expr} = 0 THEN 'warm'
                WHEN {$last_activity_expr} < %s OR ({$last_activity_expr} IS NULL AND {$open_expr} > 0 AND {$overdue_expr} = 0) THEN 'cold'
                ELSE 'none'
            END",
            $seven_days,
            $fourteen_days,
            $thirty_days
        );

        if (false === strpos($clauses['fields'], 'peracrm_health_key')) {
            $clauses['fields'] .= ", {$case_expr} AS peracrm_health_key";
        }
    }

    if ($needs_activity) {
        if (empty($clauses['groupby'])) {
            $clauses['groupby'] = "{$wpdb->posts}.ID";
        } elseif (false === strpos($clauses['groupby'], "{$wpdb->posts}.ID")) {
            $clauses['groupby'] .= ", {$wpdb->posts}.ID";
        }
    }

    $having_conditions = [];
    if ($context['engagement'] !== '' && $context['has_activity_table']) {
        $now = current_time('timestamp');
        $seven_days = wp_date('Y-m-d H:i:s', $now - DAY_IN_SECONDS * 7);
        $thirty_days = wp_date('Y-m-d H:i:s', $now - DAY_IN_SECONDS * 30);

        if ('hot' === $context['engagement']) {
            $having_conditions[] = $wpdb->prepare('peracrm_last_activity_at >= %s', $seven_days);
        } elseif ('warm' === $context['engagement']) {
            $having_conditions[] = $wpdb->prepare('peracrm_last_activity_at < %s', $seven_days);
            $having_conditions[] = $wpdb->prepare('peracrm_last_activity_at >= %s', $thirty_days);
        } elseif ('cold' === $context['engagement']) {
            $having_conditions[] = $wpdb->prepare('peracrm_last_activity_at < %s', $thirty_days);
        } elseif ('none' === $context['engagement']) {
            $having_conditions[] = 'peracrm_last_activity_at IS NULL';
        }
    }

    if ($context['health'] !== '') {
        $having_conditions[] = $wpdb->prepare('peracrm_health_key = %s', $context['health']);
    }

    if (!empty($having_conditions)) {
        $existing_having = trim($clauses['having']);
        $append_having = implode(' AND ', $having_conditions);
        $clauses['having'] = $existing_having === '' ? $append_having : "{$existing_having} AND {$append_having}";
    }

    if (function_exists('peracrm_party_table_exists') && peracrm_party_table_exists()) {
        $party_table = peracrm_table('peracrm_party');
        if (false === strpos($clauses['join'], " {$party_table} ")) {
            $clauses['join'] .= " LEFT JOIN {$party_table} AS peracrm_party ON {$wpdb->posts}.ID = peracrm_party.party_id";
        }

        if ($context['lead_pipeline_stage'] !== '') {
            $clauses['where'] .= $wpdb->prepare(' AND peracrm_party.lead_pipeline_stage = %s', $context['lead_pipeline_stage']);
        }
        if ($context['engagement_state'] !== '') {
            $clauses['where'] .= $wpdb->prepare(' AND peracrm_party.engagement_state = %s', $context['engagement_state']);
        }
        if ($context['disposition'] !== '') {
            $clauses['where'] .= $wpdb->prepare(' AND peracrm_party.disposition = %s', $context['disposition']);
        }
    }

    if ($context['derived_type'] !== '' && function_exists('peracrm_deals_table_exists') && peracrm_deals_table_exists()) {
        $deals_table = peracrm_table('peracrm_deals');
        $client_sub = "(SELECT DISTINCT party_id FROM {$deals_table} WHERE stage = 'completed') AS peracrm_client_parties";
        if (false === strpos($clauses['join'], 'peracrm_client_parties')) {
            $clauses['join'] .= " LEFT JOIN {$client_sub} ON {$wpdb->posts}.ID = peracrm_client_parties.party_id";
        }

        if ($context['derived_type'] === 'client') {
            $clauses['where'] .= ' AND peracrm_client_parties.party_id IS NOT NULL';
        }
        if ($context['derived_type'] === 'lead') {
            $clauses['where'] .= ' AND peracrm_client_parties.party_id IS NULL';
        }
    }

    if ('last_activity' === $context['orderby'] && $context['has_activity_table']) {
        $clauses['orderby'] = 'peracrm_last_activity_at IS NULL, peracrm_last_activity_at DESC';
    }

    return $clauses;
}

function peracrm_admin_prime_client_health_cache($posts, $query)
{
    $context = peracrm_admin_client_list_context($query);
    if (!$context['is_client_list']) {
        return $posts;
    }

    if (!function_exists('peracrm_client_health_prime_cache')) {
        return $posts;
    }

    $client_ids = [];
    foreach ($posts as $post) {
        if ($post instanceof WP_Post) {
            $client_ids[] = (int) $post->ID;
        }
    }

    if (!empty($client_ids)) {
        peracrm_client_health_prime_cache($client_ids);
    }

    return $posts;
}

function peracrm_admin_client_list_context($query)
{
    static $cache = [];
    $key = is_object($query) ? spl_object_hash($query) : 'default';

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $is_client_list = false;
    if ($query instanceof WP_Query && is_admin() && $query->is_main_query()) {
        global $pagenow;
        $post_type = $query->get('post_type');
        $is_client_list = ('edit.php' === $pagenow && 'crm_client' === $post_type);
        if ($is_client_list && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && 'edit-crm_client' !== $screen->id) {
                $is_client_list = false;
            }
        }
    }

    $has_activity_table = function_exists('peracrm_activity_table_exists') && peracrm_activity_table_exists();
    $has_reminders_table = function_exists('peracrm_reminders_table_exists') && peracrm_reminders_table_exists();
    $orderby = $query instanceof WP_Query ? sanitize_key($query->get('orderby')) : '';

    $cache[$key] = [
        'is_client_list' => $is_client_list,
        'has_activity_table' => $has_activity_table,
        'has_reminders_table' => $has_reminders_table,
        'orderby' => $orderby,
        'engagement' => peracrm_admin_get_engagement_filter(),
        'health' => peracrm_admin_get_health_filter(),
        'lead_pipeline_stage' => peracrm_admin_get_party_stage_filter(),
        'engagement_state' => peracrm_admin_get_party_engagement_filter(),
        'disposition' => peracrm_admin_get_party_disposition_filter(),
        'derived_type' => peracrm_admin_get_derived_type_filter(),
    ];

    return $cache[$key];
}

function peracrm_admin_get_engagement_filter()
{
    static $filter = null;
    if (null !== $filter) {
        return $filter;
    }

    $value = isset($_GET['engagement']) ? sanitize_key(wp_unslash($_GET['engagement'])) : '';
    $allowed = ['hot', 'warm', 'cold', 'none'];
    if (!in_array($value, $allowed, true)) {
        $value = '';
    }

    $filter = $value;
    return $filter;
}

function peracrm_admin_get_health_filter()
{
    static $filter = null;
    if (null !== $filter) {
        return $filter;
    }

    $value = isset($_GET['peracrm_health']) ? sanitize_key(wp_unslash($_GET['peracrm_health'])) : '';
    $allowed = ['hot', 'warm', 'cold', 'at_risk', 'none'];
    if (!in_array($value, $allowed, true)) {
        $value = '';
    }

    $filter = $value;
    return $filter;
}

function peracrm_admin_render_client_columns($column, $post_id)
{
    static $party_status_cache = null;
    static $client_ids_cache = null;

    if (null === $party_status_cache || null === $client_ids_cache) {
        $ids = [];
        $query = $GLOBALS['wp_query'] ?? null;
        if ($query instanceof WP_Query && !empty($query->posts)) {
            foreach ($query->posts as $post_obj) {
                if ($post_obj instanceof WP_Post) {
                    $ids[] = (int) $post_obj->ID;
                }
            }
        }

        $party_status_cache = function_exists('peracrm_party_get_status_by_ids') ? peracrm_party_get_status_by_ids($ids) : [];
        $client_ids_cache = function_exists('peracrm_party_batch_get_closed_won_client_ids') ? array_flip(peracrm_party_batch_get_closed_won_client_ids($ids)) : [];
    }

    if ('peracrm_type' === $column) {
        echo isset($client_ids_cache[(int) $post_id]) ? 'Client' : 'Lead';
        return;
    }

    if ('peracrm_pipeline_stage' === $column) {
        $stage = isset($party_status_cache[(int) $post_id]['lead_pipeline_stage']) ? $party_status_cache[(int) $post_id]['lead_pipeline_stage'] : 'new_enquiry';
        $labels = function_exists('peracrm_party_stage_options') ? peracrm_party_stage_options() : [];
        echo esc_html($labels[$stage] ?? $stage);
        return;
    }

    if ('peracrm_engagement_state' === $column) {
        $value = isset($party_status_cache[(int) $post_id]['engagement_state']) ? $party_status_cache[(int) $post_id]['engagement_state'] : 'engaged';
        $labels = function_exists('peracrm_party_engagement_options') ? peracrm_party_engagement_options() : [];
        echo esc_html($labels[$value] ?? $value);
        return;
    }

    if ('peracrm_disposition' === $column) {
        $value = isset($party_status_cache[(int) $post_id]['disposition']) ? $party_status_cache[(int) $post_id]['disposition'] : 'none';
        $labels = function_exists('peracrm_party_disposition_options') ? peracrm_party_disposition_options() : [];
        echo esc_html($labels[$value] ?? $value);
        return;
    }

    if ('peracrm_account' === $column) {
        static $linked_user_cache = [];
        static $user_cache = [];

        if (array_key_exists($post_id, $linked_user_cache)) {
            $linked_user_id = $linked_user_cache[$post_id];
        } else {
            $linked_user_id = peracrm_admin_get_client_linked_user_id($post_id);
            if ($linked_user_id <= 0 && !peracrm_admin_client_table_has_linked_user_column()) {
                $users = get_users([
                    'meta_key' => 'crm_client_id',
                    'meta_value' => (int) $post_id,
                    'number' => 1,
                    'fields' => 'ids',
                ]);
                $linked_user_id = empty($users) ? 0 : (int) $users[0];
            }
            $linked_user_cache[$post_id] = $linked_user_id;
        }

        if ($linked_user_id <= 0) {
            echo 'Not linked';
            return;
        }

        if (isset($user_cache[$linked_user_id])) {
            $user = $user_cache[$linked_user_id];
        } else {
            $user = get_userdata($linked_user_id);
            $user_cache[$linked_user_id] = $user;
        }
        if (!$user) {
            echo 'Not linked';
            return;
        }

        $edit_link = get_edit_user_link($user->ID);
        $email = esc_html($user->user_email);
        if ($edit_link) {
            echo 'Linked: <a href="' . esc_url($edit_link) . '">' . $email . '</a>';
            return;
        }

        echo 'Linked: ' . $email;
        return;
    }

    if ('peracrm_health' === $column) {
        if (!function_exists('peracrm_client_health_get')) {
            echo '&mdash;';
            return;
        }

        $health = peracrm_client_health_get($post_id);
        if (function_exists('peracrm_client_health_badge_html')) {
            echo peracrm_client_health_badge_html($health);
            return;
        }

        echo esc_html(isset($health['label']) ? $health['label'] : 'None');
        return;
    }

    if ('last_activity' !== $column) {
        return;
    }

    if (!function_exists('peracrm_activity_last')) {
        echo '&mdash;';
        return;
    }

    $activity = peracrm_activity_last($post_id);
    if (!$activity) {
        echo '&mdash;';
        return;
    }

    $event_type = isset($activity['event_type']) ? $activity['event_type'] : '';
    $label = peracrm_admin_activity_label($event_type);
    $created_at = isset($activity['created_at']) ? $activity['created_at'] : '';

    $bucket = function_exists('peracrm_activity_engagement_bucket')
        ? peracrm_activity_engagement_bucket($created_at)
        : 'none';
    $badge = peracrm_admin_activity_badge($bucket);

    $timestamp = $created_at ? strtotime($created_at) : 0;
    $relative = '';
    $title = '';
    if ($timestamp) {
        $relative = human_time_diff($timestamp, current_time('timestamp')) . ' ago';
        $title = wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
    }

    echo $badge . esc_html($label);
    if ($relative) {
        echo ' <span title="' . esc_attr($title) . '">' . esc_html($relative) . '</span>';
    }
}

function peracrm_admin_activity_label($event_type)
{
    $event_type = sanitize_key($event_type);
    $labels = [
        'view_property' => 'Viewed property',
        'login' => 'Logged in',
        'account_visit' => 'Visited account',
        'enquiry' => 'Submitted enquiry',
    ];

    if (isset($labels[$event_type])) {
        return $labels[$event_type];
    }

    if ($event_type === '') {
        return 'Activity';
    }

    return ucfirst($event_type);
}

function peracrm_admin_activity_badge($bucket)
{
    $bucket = sanitize_key($bucket);
    $colors = [
        'hot' => '#46b450',
        'warm' => '#dba617',
        'cold' => '#99a1a7',
        'none' => '#ccd0d4',
    ];

    $color = isset($colors[$bucket]) ? $colors[$bucket] : $colors['none'];

    return sprintf(
        '<span aria-hidden="true" style="display:inline-block;width:8px;height:8px;border-radius:50%%;background:%1$s;margin-right:6px;vertical-align:middle;"></span>',
        esc_attr($color)
    );
}

function peracrm_admin_get_party_stage_filter()
{
    $value = isset($_GET['lead_pipeline_stage']) ? sanitize_key(wp_unslash($_GET['lead_pipeline_stage'])) : '';
    if ($value === '') {
        return '';
    }

    return isset(peracrm_party_stage_options()[$value]) ? $value : '';
}

function peracrm_admin_get_party_engagement_filter()
{
    $value = isset($_GET['engagement_state']) ? sanitize_key(wp_unslash($_GET['engagement_state'])) : '';
    if ($value === '') {
        return '';
    }

    return isset(peracrm_party_engagement_options()[$value]) ? $value : '';
}

function peracrm_admin_get_party_disposition_filter()
{
    $value = isset($_GET['disposition']) ? sanitize_key(wp_unslash($_GET['disposition'])) : '';
    if ($value === '') {
        return '';
    }

    return isset(peracrm_party_disposition_options()[$value]) ? $value : '';
}

function peracrm_admin_get_derived_type_filter()
{
    $value = isset($_GET['peracrm_type']) ? sanitize_key(wp_unslash($_GET['peracrm_type'])) : '';
    return in_array($value, ['lead', 'client'], true) ? $value : '';
}


function peracrm_handle_save_party_status_on_post_save($post_id, $post, $update)
{
    if (!$update || !$post instanceof WP_Post || $post->post_type !== 'crm_client') {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!isset($_POST['peracrm_save_party_status_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['peracrm_save_party_status_nonce'])), 'peracrm_save_party_status')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $allowed_stage_values = function_exists('peracrm_pipeline_allowed_stage_values')
        ? peracrm_pipeline_allowed_stage_values()
        : (function_exists('peracrm_party_stage_options')
            ? array_map('sanitize_key', array_keys((array) peracrm_party_stage_options()))
            : ['new_enquiry']);

    $lead_pipeline_stage = isset($_POST['lead_pipeline_stage']) ? sanitize_key(wp_unslash($_POST['lead_pipeline_stage'])) : '';
    $engagement_state = isset($_POST['engagement_state']) ? sanitize_key(wp_unslash($_POST['engagement_state'])) : '';
    $disposition = isset($_POST['disposition']) ? sanitize_key(wp_unslash($_POST['disposition'])) : '';
    if ($lead_pipeline_stage === '' || !in_array($lead_pipeline_stage, $allowed_stage_values, true)) {
        $lead_pipeline_stage = function_exists('peracrm_party_default_status')
            ? (string) (peracrm_party_default_status()['lead_pipeline_stage'] ?? 'new_enquiry')
            : 'new_enquiry';
    }

    $saved = peracrm_party_upsert_status($post_id, [
        'lead_pipeline_stage' => $lead_pipeline_stage,
        'engagement_state' => $engagement_state,
        'disposition' => $disposition,
        'lead_stage_updated_at' => peracrm_now_mysql(),
    ]);

    $status_saved = (bool) $saved;
    if ($status_saved && function_exists('peracrm_party_get')) {
        $party = peracrm_party_get($post_id);
        $status_saved = sanitize_key((string) ($party['lead_pipeline_stage'] ?? '')) === $lead_pipeline_stage
            && sanitize_key((string) ($party['engagement_state'] ?? '')) === $engagement_state
            && sanitize_key((string) ($party['disposition'] ?? '')) === $disposition;
    }

    if (!isset($GLOBALS['peracrm_party_status_save_ok']) || !is_array($GLOBALS['peracrm_party_status_save_ok'])) {
        $GLOBALS['peracrm_party_status_save_ok'] = [];
    }

    $GLOBALS['peracrm_party_status_save_ok'][(int) $post_id] = $status_saved;
}

function peracrm_admin_fix_crm_client_post_update_redirect($location, $post_id)
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== 'crm_client') {
        return $location;
    }

    $nonce = isset($_POST['peracrm_save_party_status_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['peracrm_save_party_status_nonce']))
        : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'peracrm_save_party_status')) {
        return $location;
    }

    $path = wp_parse_url($location, PHP_URL_PATH);
    if ($path && basename($path) === 'edit.php') {
        $location = add_query_arg('post_type', 'crm_client', $location);
    }

    $save_status_map = isset($GLOBALS['peracrm_party_status_save_ok']) && is_array($GLOBALS['peracrm_party_status_save_ok'])
        ? $GLOBALS['peracrm_party_status_save_ok']
        : [];

    if (array_key_exists($post_id, $save_status_map) && !$save_status_map[$post_id]) {
        $location = add_query_arg('peracrm_notice', 'profile_failed', $location);
    }

    return $location;
}

function peracrm_handle_save_party_status()
{
    if (!peracrm_admin_user_can_manage()) {
        wp_die('Unauthorized', 403);
    }

    check_admin_referer('peracrm_save_party_status');

    $client_id = isset($_POST['peracrm_client_id']) ? (int) $_POST['peracrm_client_id'] : 0;
    if ($client_id <= 0 || !current_user_can('edit_post', $client_id)) {
        wp_safe_redirect(add_query_arg(['post' => $client_id, 'action' => 'edit', 'peracrm_notice' => 'profile_failed'], admin_url('post.php')));
        exit;
    }

    $saved = peracrm_party_upsert_status($client_id, [
        'lead_pipeline_stage' => $_POST['lead_pipeline_stage'] ?? '',
        'engagement_state' => $_POST['engagement_state'] ?? '',
        'disposition' => $_POST['disposition'] ?? '',
        'lead_stage_updated_at' => peracrm_now_mysql(),
    ]);

    wp_safe_redirect(add_query_arg([
        'post' => $client_id,
        'action' => 'edit',
        'peracrm_notice' => $saved ? 'profile_saved' : 'profile_failed',
    ], admin_url('post.php')));
    exit;
}

function peracrm_handle_create_deal()
{
    if (!peracrm_admin_user_can_manage()) {
        wp_die('Unauthorized', 403);
    }

    if (!peracrm_is_valid_deal_submission_request('peracrm_create_deal')) {
        wp_die('Invalid deal request', 400);
    }

    $client_id = isset($_POST['peracrm_client_id']) ? (int) $_POST['peracrm_client_id'] : 0;
    if ($client_id <= 0 || !current_user_can('edit_post', $client_id)) {
        wp_die('Invalid client', 400);
    }

    $validation_error = peracrm_validate_deal_submission($client_id, $_POST);
    if ($validation_error !== '') {
        wp_safe_redirect(add_query_arg([
            'post' => $client_id,
            'action' => 'edit',
            'peracrm_notice' => 'deal_validation_failed',
            'peracrm_deal_error' => rawurlencode($validation_error),
        ], admin_url('post.php')));
        exit;
    }

    $party = peracrm_party_get($client_id);
    $is_junk = ($party['disposition'] ?? 'none') === 'junk_lead';
    $override = !empty($_POST['override_junk']);
    if ($is_junk && !$override) {
        wp_safe_redirect(add_query_arg(['post' => $client_id, 'action' => 'edit', 'peracrm_notice' => 'profile_failed'], admin_url('post.php')));
        exit;
    }

    $created = peracrm_deals_create([
        'party_id' => $client_id,
        'title' => $_POST['title'] ?? '',
        'stage' => $_POST['stage'] ?? 'reservation_taken',
        'primary_property_id' => $_POST['primary_property_id'] ?? null,
        'deal_value' => $_POST['deal_value'] ?? null,
        'currency' => $_POST['currency'] ?? 'USD',
        'expected_close_date' => $_POST['expected_close_date'] ?? null,
        'owner_user_id' => $_POST['owner_user_id'] ?? null,
        'commission_type' => $_POST['commission_type'] ?? 'percent',
        'commission_rate' => $_POST['commission_rate'] ?? null,
        'commission_amount' => $_POST['commission_amount'] ?? null,
        'commission_currency' => $_POST['commission_currency'] ?? 'USD',
        'commission_status' => $_POST['commission_status'] ?? 'expected',
        'commission_due_date' => $_POST['commission_due_date'] ?? null,
        'commission_paid_at' => $_POST['commission_paid_at'] ?? null,
        'commission_notes' => $_POST['commission_notes'] ?? '',
        'closed_reason' => function_exists('peracrm_deal_sanitize_closed_reason') ? peracrm_deal_sanitize_closed_reason($_POST['closed_reason'] ?? 'none') : ($_POST['closed_reason'] ?? 'none'),
    ]);

    wp_safe_redirect(add_query_arg([
        'post' => $client_id,
        'action' => 'edit',
        'peracrm_notice' => $created > 0 ? 'deal_saved' : 'deal_failed',
    ], admin_url('post.php')));
    exit;
}

function peracrm_handle_update_deal()
{
    if (!peracrm_admin_user_can_manage()) {
        wp_die('Unauthorized', 403);
    }

    if (!peracrm_is_valid_deal_submission_request('peracrm_update_deal')) {
        wp_die('Invalid deal request', 400);
    }

    $client_id = isset($_POST['peracrm_client_id']) ? (int) $_POST['peracrm_client_id'] : 0;
    $deal_id = isset($_POST['deal_id']) ? (int) $_POST['deal_id'] : 0;

    if ($client_id <= 0 || $deal_id <= 0 || !current_user_can('edit_post', $client_id)) {
        wp_die('Invalid deal', 400);
    }

    $validation_error = peracrm_validate_deal_submission($client_id, $_POST);
    if ($validation_error !== '') {
        wp_safe_redirect(add_query_arg([
            'post' => $client_id,
            'action' => 'edit',
            'peracrm_notice' => 'deal_validation_failed',
            'peracrm_deal_error' => rawurlencode($validation_error),
            'peracrm_deal_id' => $deal_id,
        ], admin_url('post.php')));
        exit;
    }

    $updated = peracrm_deals_update($deal_id, [
        'title' => $_POST['title'] ?? '',
        'stage' => $_POST['stage'] ?? 'reservation_taken',
        'primary_property_id' => $_POST['primary_property_id'] ?? null,
        'deal_value' => $_POST['deal_value'] ?? null,
        'currency' => $_POST['currency'] ?? 'USD',
        'expected_close_date' => $_POST['expected_close_date'] ?? null,
        'owner_user_id' => $_POST['owner_user_id'] ?? null,
        'commission_type' => $_POST['commission_type'] ?? 'percent',
        'commission_rate' => $_POST['commission_rate'] ?? null,
        'commission_amount' => $_POST['commission_amount'] ?? null,
        'commission_currency' => $_POST['commission_currency'] ?? 'USD',
        'commission_status' => $_POST['commission_status'] ?? 'expected',
        'commission_due_date' => $_POST['commission_due_date'] ?? null,
        'commission_paid_at' => $_POST['commission_paid_at'] ?? null,
        'commission_notes' => $_POST['commission_notes'] ?? '',
        'closed_reason' => function_exists('peracrm_deal_sanitize_closed_reason') ? peracrm_deal_sanitize_closed_reason($_POST['closed_reason'] ?? 'none') : ($_POST['closed_reason'] ?? 'none'),
    ]);

    wp_safe_redirect(add_query_arg([
        'post' => $client_id,
        'action' => 'edit',
        'peracrm_notice' => $updated ? 'deal_saved' : 'deal_failed',
    ], admin_url('post.php')));
    exit;
}

function peracrm_is_valid_deal_submission_request($action)
{
    $expected_action = sanitize_key((string) $action);
    if ($expected_action === '') {
        return false;
    }

    $request_action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    if ($request_action !== $expected_action) {
        return false;
    }

    $nonce = isset($_POST['peracrm_deal_nonce']) ? sanitize_text_field(wp_unslash($_POST['peracrm_deal_nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, $expected_action)) {
        return false;
    }

    $submit_marker = isset($_POST['peracrm_deal_submit']) ? (int) $_POST['peracrm_deal_submit'] : 0;
    if ($submit_marker !== 1 && $request_action !== $expected_action) {
        return false;
    }

    return true;
}

function peracrm_deal_requires_offer_made_fields($client_id)
{
    $client_id = (int) $client_id;
    $stage = '';

    if ($client_id > 0 && function_exists('peracrm_party_get')) {
        $party = peracrm_party_get($client_id);
        $stage = sanitize_key((string) ($party['lead_pipeline_stage'] ?? ''));
    }

    if ($stage === '' && isset($_POST['peracrm_save_party_status_nonce'])) {
        $status_nonce = sanitize_text_field(wp_unslash($_POST['peracrm_save_party_status_nonce']));
        if ($status_nonce !== '' && wp_verify_nonce($status_nonce, 'peracrm_save_party_status')) {
            $posted_stage = isset($_POST['lead_pipeline_stage']) ? sanitize_key(wp_unslash($_POST['lead_pipeline_stage'])) : '';
            $stage = function_exists('peracrm_map_legacy_lead_stage')
                ? peracrm_map_legacy_lead_stage($posted_stage)
                : $posted_stage;
        }
    }

    return $stage === 'offer_made';
}

function peracrm_validate_deal_submission($client_id, $raw_data)
{
    if (!peracrm_deal_requires_offer_made_fields($client_id)) {
        return '';
    }

    $raw_data = is_array($raw_data) ? $raw_data : [];

    $title = isset($raw_data['title']) ? trim((string) wp_unslash($raw_data['title'])) : '';
    $primary_property_id = isset($raw_data['primary_property_id']) ? absint($raw_data['primary_property_id']) : 0;
    $deal_value = isset($raw_data['deal_value']) ? trim((string) wp_unslash($raw_data['deal_value'])) : '';

    if ($title === '') {
        return 'Deal title is mandatory when Lead Pipeline Stage is Offer made.';
    }

    if ($primary_property_id <= 0) {
        return 'Primary property is mandatory when Lead Pipeline Stage is Offer made.';
    }

    if ($deal_value === '' || !is_numeric($deal_value) || (float) $deal_value <= 0) {
        return 'Deal value is mandatory when Lead Pipeline Stage is Offer made.';
    }

    return '';
}

function peracrm_register_dashboard_widget()
{
    if (!peracrm_admin_user_can_manage()) {
        return;
    }

    wp_add_dashboard_widget('peracrm_analytics', 'PeraCRM Analytics', 'peracrm_render_dashboard_widget');
    wp_add_dashboard_widget('peracrm_commission_totals', 'Commission Totals', 'peracrm_render_commission_dashboard_widget');
}

function peracrm_render_dashboard_widget()
{
    $party_stage_counts = peracrm_parties_count_by_stage(true);
    $deal_stage_counts = peracrm_deals_count_by_stage();
    $client_count = peracrm_count_distinct_clients_from_deals();

    echo '<p><strong>Lead pipeline stages (non-junk):</strong></p><ul>';
    foreach ($party_stage_counts as $stage => $count) {
        echo '<li>' . esc_html($stage) . ': ' . esc_html((string) $count) . '</li>';
    }
    echo '</ul>';

    echo '<p><strong>Deals by stage:</strong></p><ul>';
    foreach ($deal_stage_counts as $stage => $count) {
        echo '<li>' . esc_html($stage) . ': ' . esc_html((string) $count) . '</li>';
    }
    echo '</ul>';

    echo '<p><strong>Derived clients:</strong> ' . esc_html((string) $client_count) . '</p>';
}


function peracrm_render_commission_dashboard_widget()
{
    $totals = peracrm_deals_get_commission_totals();

    echo '<table class="widefat striped"><thead><tr><th>Currency</th><th>Expected</th><th>Invoiced</th><th>Received (MTD)</th><th>Received (YTD)</th></tr></thead><tbody>';

    $currencies = array_unique(array_merge(
        array_keys($totals['expected_total']),
        array_keys($totals['invoiced_total']),
        array_keys($totals['received_mtd_total']),
        array_keys($totals['received_ytd_total'])
    ));

    sort($currencies);

    if (empty($currencies)) {
        echo '<tr><td colspan="5">No commission data yet.</td></tr>';
    } else {
        foreach ($currencies as $currency) {
            echo '<tr>';
            echo '<td>' . esc_html($currency) . '</td>';
            echo '<td>' . esc_html(number_format((float) ($totals['expected_total'][$currency] ?? 0), 2)) . '</td>';
            echo '<td>' . esc_html(number_format((float) ($totals['invoiced_total'][$currency] ?? 0), 2)) . '</td>';
            echo '<td>' . esc_html(number_format((float) ($totals['received_mtd_total'][$currency] ?? 0), 2)) . '</td>';
            echo '<td>' . esc_html(number_format((float) ($totals['received_ytd_total'][$currency] ?? 0), 2)) . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';

    $advisors = peracrm_get_staff_users();
    $advisor_map = [];
    foreach ($advisors as $advisor) {
        $advisor_map[(int) $advisor->ID] = $advisor;
    }

    $received_ytd_by_advisor = peracrm_deals_get_received_ytd_by_advisor();
    $advisor_totals = [];

    foreach ($received_ytd_by_advisor as $owner_user_id => $received_ytd) {
        $owner_user_id = (int) $owner_user_id;
        if ($owner_user_id <= 0 || !isset($advisor_map[$owner_user_id])) {
            continue;
        }

        $sortable_total = array_sum(array_map('floatval', $received_ytd));
        if ($sortable_total <= 0) {
            continue;
        }

        $advisor_totals[] = [
            'advisor' => $advisor_map[$owner_user_id],
            'totals' => $received_ytd,
            'sort_total' => $sortable_total,
        ];
    }

    usort($advisor_totals, static function ($a, $b) {
        return $b['sort_total'] <=> $a['sort_total'];
    });

    $advisor_totals = array_slice($advisor_totals, 0, 5);
    echo '<p><strong>Top 5 advisors by received YTD:</strong></p>';

    if (empty($advisor_totals)) {
        echo '<p>—</p>';
        return;
    }

    echo '<ol>';
    foreach ($advisor_totals as $row) {
        $parts = [];
        foreach ($row['totals'] as $currency => $amount) {
            $parts[] = sprintf('%s %s', number_format((float) $amount, 2), $currency);
        }
        echo '<li>' . esc_html($row['advisor']->display_name . ': ' . implode(', ', $parts)) . '</li>';
    }
    echo '</ol>';
}
