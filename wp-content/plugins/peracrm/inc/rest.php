<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'peracrm_register_rest_routes');

function peracrm_rest_collection_args()
{
    return [
        'page' => [
            'description' => 'Current page.',
            'type' => 'integer',
            'default' => 1,
            'minimum' => 1,
        ],
        'per_page' => [
            'description' => 'Items per page.',
            'type' => 'integer',
            'default' => 20,
            'minimum' => 1,
            'maximum' => 100,
        ],
    ];
}

function peracrm_register_rest_routes()
{
    register_rest_route('peracrm/v1', '/leads', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'peracrm_rest_get_leads',
        'permission_callback' => 'peracrm_rest_can_access',
        'args' => peracrm_rest_collection_args(),
    ]);

    register_rest_route('peracrm/v1', '/clients', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'peracrm_rest_get_clients',
        'permission_callback' => 'peracrm_rest_can_access',
        'args' => peracrm_rest_collection_args(),
    ]);

    register_rest_route('peracrm/v1', '/deals', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'peracrm_rest_get_deals',
        'permission_callback' => 'peracrm_rest_can_access',
        'args' => peracrm_rest_collection_args(),
    ]);

    if (function_exists('peracrm_rest_register_push_routes')) {
        peracrm_rest_register_push_routes();
    }
}

function peracrm_rest_can_access(WP_REST_Request $request)
{
    if (!is_user_logged_in()) {
        return new WP_Error('peracrm_forbidden', 'Authentication required.', ['status' => 401]);
    }

    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        $nonce = (string) $request->get_param('_wpnonce');
    }

    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('peracrm_invalid_nonce', 'Invalid REST nonce.', ['status' => 403]);
    }

    if (!current_user_can('manage_options') && !current_user_can('edit_crm_clients') && !current_user_can('edit_crm_leads') && !current_user_can('edit_crm_deals')) {
        return new WP_Error('peracrm_forbidden', 'Insufficient capability.', ['status' => 403]);
    }

    return true;
}

function peracrm_rest_get_pagination(WP_REST_Request $request)
{
    $page = max(1, (int) $request->get_param('page'));
    $per_page = (int) $request->get_param('per_page');
    if ($per_page <= 0) {
        $per_page = 20;
    }
    $per_page = min(100, $per_page);

    return [$page, $per_page];
}

function peracrm_rest_current_user_can_manage_all_clients()
{
    $callback = static function () {
        return function_exists('peracrm_user_can_manage_all_clients')
            ? peracrm_user_can_manage_all_clients()
            : (current_user_can('manage_options') || current_user_can('peracrm_manage_all_clients'));
    };

    if (function_exists('peracrm_with_target_blog')) {
        return (bool) peracrm_with_target_blog($callback);
    }

    return (bool) $callback();
}

function peracrm_rest_get_scoped_client_ids_for_current_user()
{
    if (peracrm_rest_current_user_can_manage_all_clients()) {
        return null;
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return [];
    }

    $ids = apply_filters('peracrm_allowed_client_ids_for_user', [], $user_id);
    if (!is_array($ids) || empty($ids)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('intval', $ids))));
}

function peracrm_rest_get_client_ids_by_type($type, $per_page, $offset, $allowed_client_ids = null)
{
    global $wpdb;

    if (is_array($allowed_client_ids) && empty($allowed_client_ids)) {
        return [0, []];
    }

    return peracrm_with_target_blog(static function () use ($wpdb, $type, $per_page, $offset, $allowed_client_ids) {
        $posts_table = $wpdb->posts;
        $deals_table = peracrm_table('peracrm_deals');

        $completed_subquery = "SELECT party_id, COUNT(*) AS completed_count FROM {$deals_table} WHERE stage = 'completed' GROUP BY party_id";

        $condition = $type === 'clients'
            ? 'COALESCE(d.completed_count, 0) > 0'
            : 'COALESCE(d.completed_count, 0) = 0';

        $scope_clause = '';
        $scope_params = [];
        if (is_array($allowed_client_ids)) {
            $scope_placeholders = implode(',', array_fill(0, count($allowed_client_ids), '%d'));
            $scope_clause = " AND p.ID IN ({$scope_placeholders})";
            $scope_params = array_map('intval', $allowed_client_ids);
        }

        $total_sql = "SELECT COUNT(1)
            FROM {$posts_table} p
            LEFT JOIN ({$completed_subquery}) d ON d.party_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status = %s
              AND {$condition}{$scope_clause}";
        $total_params = array_merge(['crm_client', 'publish'], $scope_params);
        $total = (int) $wpdb->get_var($wpdb->prepare($total_sql, $total_params));

        $rows_sql = "SELECT p.ID AS party_id, COALESCE(d.completed_count, 0) AS completed_count
            FROM {$posts_table} p
            LEFT JOIN ({$completed_subquery}) d ON d.party_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status = %s
              AND {$condition}{$scope_clause}
            ORDER BY p.post_date DESC, p.ID DESC
            LIMIT %d OFFSET %d";

        $rows_params = array_merge(['crm_client', 'publish'], $scope_params, [(int) $per_page, (int) $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($rows_sql, $rows_params), ARRAY_A);

        return [$total, (array) $rows];
    });
}

function peracrm_rest_get_leads(WP_REST_Request $request)
{
    [$page, $per_page] = peracrm_rest_get_pagination($request);
    $offset = ($page - 1) * $per_page;
    $allowed_client_ids = peracrm_rest_get_scoped_client_ids_for_current_user();

    [$total, $rows] = peracrm_rest_get_client_ids_by_type('leads', $per_page, $offset, $allowed_client_ids);

    $items = [];
    foreach ((array) $rows as $row) {
        $party_id = (int) ($row['party_id'] ?? 0);
        $post = get_post($party_id);
        if (!$post || $post->post_type !== 'crm_client' || $post->post_status !== 'publish') {
            continue;
        }

        $party = function_exists('peracrm_party_get') ? peracrm_party_get($party_id) : [];
        $items[] = [
            'id' => $party_id,
            'name' => (string) $post->post_title,
            'lead_stage' => (string) ($party['lead_pipeline_stage'] ?? 'new_enquiry'),
            'engagement_state' => (string) ($party['engagement_state'] ?? 'engaged'),
            'disposition' => (string) ($party['disposition'] ?? 'none'),
            'completed_deal_count' => isset($row['completed_count']) ? (int) $row['completed_count'] : 0,
        ];
    }

    return new WP_REST_Response([
        'items' => $items,
        'returned' => count($items),
        'page' => $page,
        'per_page' => $per_page,
        'total' => (int) $total,
    ]);
}

function peracrm_rest_get_clients(WP_REST_Request $request)
{
    [$page, $per_page] = peracrm_rest_get_pagination($request);
    $offset = ($page - 1) * $per_page;
    $allowed_client_ids = peracrm_rest_get_scoped_client_ids_for_current_user();

    [$total, $rows] = peracrm_rest_get_client_ids_by_type('clients', $per_page, $offset, $allowed_client_ids);

    $items = [];
    foreach ((array) $rows as $row) {
        $party_id = (int) ($row['party_id'] ?? 0);
        $post = get_post($party_id);
        if (!$post || $post->post_type !== 'crm_client' || $post->post_status !== 'publish') {
            continue;
        }

        $completed_count = isset($row['completed_count']) ? (int) $row['completed_count'] : 0;

        $items[] = [
            'id' => $party_id,
            'name' => (string) $post->post_title,
            'status' => 'client',
            'completed_deal_count' => $completed_count,
        ];
    }

    return new WP_REST_Response([
        'items' => $items,
        'returned' => count($items),
        'page' => $page,
        'per_page' => $per_page,
        'total' => (int) $total,
    ]);
}

function peracrm_rest_get_deals(WP_REST_Request $request)
{
    [$page, $per_page] = peracrm_rest_get_pagination($request);

    global $wpdb;

    $offset = ($page - 1) * $per_page;
    $allowed_client_ids = peracrm_rest_get_scoped_client_ids_for_current_user();

    if (is_array($allowed_client_ids) && empty($allowed_client_ids)) {
        return new WP_REST_Response([
            'items' => [],
            'returned' => 0,
            'page' => $page,
            'per_page' => $per_page,
            'total' => 0,
        ]);
    }

    $result = peracrm_with_target_blog(static function () use ($wpdb, $per_page, $offset, $allowed_client_ids) {
        $table = peracrm_table('peracrm_deals');
        $scope_clause = '';
        $scope_params = [];
        if (is_array($allowed_client_ids)) {
            $scope_placeholders = implode(',', array_fill(0, count($allowed_client_ids), '%d'));
            $scope_clause = " WHERE party_id IN ({$scope_placeholders})";
            $scope_params = array_map('intval', $allowed_client_ids);
        }

        $total_sql = "SELECT COUNT(*) FROM {$table}{$scope_clause}";
        if (!empty($scope_params)) {
            $total = (int) $wpdb->get_var($wpdb->prepare($total_sql, $scope_params));
        } else {
            $total = (int) $wpdb->get_var($total_sql);
        }

        $sql = "SELECT id, party_id, title, stage, closed_reason, deal_value, currency, owner_user_id, updated_at FROM {$table}{$scope_clause} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $query_params = array_merge($scope_params, [(int) $per_page, (int) $offset]);
        $sql = $wpdb->prepare($sql, $query_params);
        $rows = $wpdb->get_results($sql, ARRAY_A);

        return [$total, $rows];
    });

    [$total, $rows] = is_array($result) ? $result : [0, []];

    $items = [];
    foreach ((array) $rows as $row) {
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'party_id' => (int) ($row['party_id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'stage' => function_exists('peracrm_deal_sanitize_stage') ? peracrm_deal_sanitize_stage($row['stage'] ?? '') : (string) ($row['stage'] ?? ''),
            'closed_reason' => function_exists('peracrm_deal_sanitize_closed_reason') ? peracrm_deal_sanitize_closed_reason($row['closed_reason'] ?? 'none') : 'none',
            'deal_value' => isset($row['deal_value']) ? (float) $row['deal_value'] : null,
            'currency' => (string) ($row['currency'] ?? 'USD'),
            'owner_user_id' => (int) ($row['owner_user_id'] ?? 0),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return new WP_REST_Response([
        'items' => $items,
        'returned' => count($items),
        'page' => $page,
        'per_page' => $per_page,
        'total' => (int) $total,
    ]);
}
