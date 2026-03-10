<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_register_units_manager_submenu()
{
    add_submenu_page(
        'pera-portal',
        __('Units Manager', 'pera-portal'),
        __('Units Manager', 'pera-portal'),
        'read',
        'pera-portal-units-manager',
        'pera_portal_render_units_manager_page'
    );
}

function pera_portal_hide_disallowed_units_manager_submenu()
{
    if (function_exists('pera_portal_user_is_allowed_for_admin_ui') && pera_portal_user_is_allowed_for_admin_ui()) {
        return;
    }

    remove_submenu_page('pera-portal', 'pera-portal-units-manager');
}

function pera_portal_units_manager_get_allowed_currencies()
{
    return ['EUR', 'GBP', 'USD', 'TRY'];
}

function pera_portal_units_manager_get_allowed_statuses()
{
    return ['available', 'reserved', 'sold'];
}

function pera_portal_units_manager_get_field($field_name, $post_id)
{
    if (function_exists('get_field')) {
        return get_field($field_name, $post_id);
    }

    return get_post_meta($post_id, $field_name, true);
}

function pera_portal_units_manager_update_field($field_name, $value, $post_id)
{
    if ($value === '' || $value === null) {
        if (function_exists('update_field')) {
            update_field($field_name, '', $post_id);
        }

        delete_post_meta($post_id, $field_name);
        return;
    }

    if (function_exists('update_field')) {
        update_field($field_name, $value, $post_id);
        return;
    }

    update_post_meta($post_id, $field_name, $value);
}

function pera_portal_units_manager_get_building_options()
{
    return get_posts([
        'post_type' => 'pera_building',
        'post_status' => ['publish', 'private', 'draft'],
        'orderby' => 'title',
        'order' => 'ASC',
        'posts_per_page' => -1,
    ]);
}

function pera_portal_units_manager_get_floor_options_for_building($building_id)
{
    $building_id = absint($building_id);
    if ($building_id <= 0) {
        return [];
    }

    return get_posts([
        'post_type' => 'pera_floor',
        'post_status' => ['publish', 'private', 'draft'],
        'orderby' => 'title',
        'order' => 'ASC',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'building',
                'value' => (string) $building_id,
                'compare' => '=',
            ],
        ],
    ]);
}

function pera_portal_units_manager_normalize_related_post_id($value)
{
    if (is_array($value) && isset($value['ID'])) {
        return absint($value['ID']);
    }

    if (is_object($value) && isset($value->ID)) {
        return absint($value->ID);
    }

    if (is_scalar($value)) {
        return absint($value);
    }

    return 0;
}

function pera_portal_units_manager_floor_matches_context($floor_id, $building_id)
{
    $floor_id = absint($floor_id);
    $building_id = absint($building_id);

    if ($floor_id <= 0 || get_post_type($floor_id) !== 'pera_floor') {
        return false;
    }

    if ($building_id <= 0) {
        return true;
    }

    $floor_building = pera_portal_units_manager_normalize_related_post_id(pera_portal_units_manager_get_field('building', $floor_id));
    return $floor_building > 0 && $floor_building === $building_id;
}

function pera_portal_units_manager_get_units_for_floor($floor_id)
{
    $query = new WP_Query([
        'post_type' => 'pera_unit',
        'post_status' => ['publish', 'private', 'draft'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => 'floor',
                'value' => (string) absint($floor_id),
                'compare' => '=',
            ],
        ],
    ]);

    $rows = [];

    foreach ($query->posts as $unit_post) {
        $unit_id = (int) $unit_post->ID;

        $rows[] = [
            'id' => $unit_id,
            'title' => (string) $unit_post->post_title,
            'unit_code' => sanitize_text_field((string) pera_portal_units_manager_get_field('unit_code', $unit_id)),
            'unit_type' => sanitize_text_field((string) pera_portal_units_manager_get_field('unit_type', $unit_id)),
            'net_size' => pera_portal_units_manager_get_field('net_size', $unit_id),
            'gross_size' => pera_portal_units_manager_get_field('gross_size', $unit_id),
            'price' => pera_portal_units_manager_get_field('price', $unit_id),
            'currency' => strtoupper(sanitize_text_field((string) pera_portal_units_manager_get_field('currency', $unit_id))),
            'status' => sanitize_key((string) pera_portal_units_manager_get_field('status', $unit_id)),
            'sort_order' => pera_portal_units_manager_get_field('sort_order', $unit_id),
            'unit_detail_plan' => pera_portal_units_manager_get_field('unit_detail_plan', $unit_id),
        ];
    }

    $has_sort_order = false;
    foreach ($rows as $row) {
        if ($row['sort_order'] !== '' && $row['sort_order'] !== null && is_numeric($row['sort_order'])) {
            $has_sort_order = true;
            break;
        }
    }

    usort($rows, static function ($left, $right) use ($has_sort_order) {
        if ($has_sort_order) {
            $left_sort_order = is_numeric($left['sort_order']) ? (float) $left['sort_order'] : PHP_FLOAT_MAX;
            $right_sort_order = is_numeric($right['sort_order']) ? (float) $right['sort_order'] : PHP_FLOAT_MAX;

            if ($left_sort_order < $right_sort_order) {
                return -1;
            }

            if ($left_sort_order > $right_sort_order) {
                return 1;
            }
        }

        $left_code = strtolower((string) $left['unit_code']);
        $right_code = strtolower((string) $right['unit_code']);

        if ($left_code !== '' || $right_code !== '') {
            if ($left_code < $right_code) {
                return -1;
            }

            if ($left_code > $right_code) {
                return 1;
            }
        }

        $left_title = strtolower((string) $left['title']);
        $right_title = strtolower((string) $right['title']);

        if ($left_title < $right_title) {
            return -1;
        }

        if ($left_title > $right_title) {
            return 1;
        }

        return (int) $left['id'] <=> (int) $right['id'];
    });

    return $rows;
}

function pera_portal_units_manager_get_plan_meta($plan_field)
{
    $attachment_id = 0;
    $url = '';

    if (is_array($plan_field)) {
        if (!empty($plan_field['ID'])) {
            $attachment_id = absint($plan_field['ID']);
        }

        if (!empty($plan_field['url'])) {
            $url = esc_url_raw((string) $plan_field['url']);
        }
    } elseif (is_numeric($plan_field)) {
        $attachment_id = absint($plan_field);
    } elseif (is_string($plan_field) && filter_var($plan_field, FILTER_VALIDATE_URL)) {
        $url = esc_url_raw($plan_field);
    }

    if ($attachment_id > 0 && $url === '') {
        $attachment_url = wp_get_attachment_url($attachment_id);
        if (is_string($attachment_url) && $attachment_url !== '') {
            $url = esc_url_raw($attachment_url);
        }
    }

    return [
        'has_plan' => $attachment_id > 0 || $url !== '',
        'attachment_id' => $attachment_id,
        'url' => $url,
    ];
}

function pera_portal_units_manager_build_unit_diagnostics_map(array $issues)
{
    $map = [];

    foreach ($issues as $issue) {
        $unit_id = isset($issue['unit_id']) ? absint($issue['unit_id']) : 0;
        if ($unit_id <= 0) {
            continue;
        }

        if (!isset($map[$unit_id])) {
            $map[$unit_id] = [
                'error' => 0,
                'warning' => 0,
            ];
        }

        $severity = isset($issue['severity']) ? (string) $issue['severity'] : 'info';

        if ($severity === 'error') {
            $map[$unit_id]['error']++;
            continue;
        }

        if ($severity === 'warning') {
            $map[$unit_id]['warning']++;
        }
    }

    return $map;
}

function pera_portal_units_manager_get_base_args_from_request()
{
    return [
        'page' => 'pera-portal-units-manager',
        'building_id' => isset($_REQUEST['building_id']) ? absint(wp_unslash($_REQUEST['building_id'])) : 0,
        'floor_id' => isset($_REQUEST['floor_id']) ? absint(wp_unslash($_REQUEST['floor_id'])) : 0,
        'status' => isset($_REQUEST['status']) ? sanitize_key((string) wp_unslash($_REQUEST['status'])) : 'all',
        'unit_code' => isset($_REQUEST['unit_code']) ? sanitize_text_field((string) wp_unslash($_REQUEST['unit_code'])) : '',
    ];
}

function pera_portal_units_manager_apply_filters(array $units, $status_filter, $unit_code_search)
{
    $status_filter = sanitize_key((string) $status_filter);
    $unit_code_search = sanitize_text_field((string) $unit_code_search);

    if ($status_filter !== 'all') {
        $units = array_values(array_filter($units, static function ($row) use ($status_filter) {
            return (string) ($row['status'] ?? '') === $status_filter;
        }));
    }

    if ($unit_code_search !== '') {
        $units = array_values(array_filter($units, static function ($row) use ($unit_code_search) {
            return stripos((string) ($row['unit_code'] ?? ''), $unit_code_search) !== false;
        }));
    }

    return $units;
}

function pera_portal_units_manager_redirect_with_notice($type, array $messages, array $base_args, array $row_state = [], array $new_row_state = [])
{
    $key = '_pera_portal_units_manager_flash_' . get_current_user_id();
    update_user_meta(get_current_user_id(), $key, [
        'type' => $type,
        'messages' => $messages,
        'row_state' => $row_state,
        'new_row_state' => $new_row_state,
    ]);

    wp_safe_redirect(add_query_arg($base_args, admin_url('admin.php')));
    exit;
}

function pera_portal_units_manager_get_flash_notice()
{
    $key = '_pera_portal_units_manager_flash_' . get_current_user_id();
    $value = get_user_meta(get_current_user_id(), $key, true);
    delete_user_meta(get_current_user_id(), $key);

    return is_array($value) ? $value : null;
}

function pera_portal_units_manager_validate_unit_payload($unit_id, $floor_id, array $payload)
{
    $errors = [];

    $payload['unit_code'] = sanitize_text_field((string) ($payload['unit_code'] ?? ''));
    $payload['unit_type'] = sanitize_text_field((string) ($payload['unit_type'] ?? ''));
    $payload['currency'] = strtoupper(sanitize_text_field((string) ($payload['currency'] ?? '')));
    $payload['status'] = sanitize_key((string) ($payload['status'] ?? ''));
    $payload['net_size'] = trim((string) ($payload['net_size'] ?? ''));
    $payload['gross_size'] = trim((string) ($payload['gross_size'] ?? ''));
    $payload['price'] = trim((string) ($payload['price'] ?? ''));
    $payload['sort_order'] = trim((string) ($payload['sort_order'] ?? ''));

    if ($payload['unit_code'] === '') {
        $errors[] = __('Unit code is required.', 'pera-portal');
    }

    if ($floor_id <= 0 || get_post_type($floor_id) !== 'pera_floor') {
        $errors[] = __('Invalid floor context.', 'pera-portal');
    }

    if (!in_array($payload['currency'], pera_portal_units_manager_get_allowed_currencies(), true)) {
        $errors[] = __('Currency must be one of EUR, GBP, USD, TRY.', 'pera-portal');
    }

    if (!in_array($payload['status'], pera_portal_units_manager_get_allowed_statuses(), true)) {
        $errors[] = __('Status must be available, reserved, or sold.', 'pera-portal');
    }

    foreach (['net_size', 'gross_size', 'price', 'sort_order'] as $numeric_field) {
        if ($payload[$numeric_field] !== '' && !is_numeric($payload[$numeric_field])) {
            $errors[] = sprintf(
                /* translators: %s: field label */
                __('%s must be numeric or blank.', 'pera-portal'),
                ucwords(str_replace('_', ' ', $numeric_field))
            );
        }
    }

    $existing_units = get_posts([
        'post_type' => 'pera_unit',
        'post_status' => ['publish', 'private', 'draft'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'floor',
                'value' => (string) $floor_id,
                'compare' => '=',
            ],
        ],
    ]);

    $candidate_code = strtolower(trim($payload['unit_code']));
    foreach ($existing_units as $existing_unit_id) {
        $existing_unit_id = absint($existing_unit_id);
        if ($existing_unit_id <= 0 || $existing_unit_id === $unit_id) {
            continue;
        }

        $existing_code = strtolower(trim((string) pera_portal_units_manager_get_field('unit_code', $existing_unit_id)));
        if ($existing_code !== '' && $existing_code === $candidate_code) {
            $errors[] = __('Duplicate unit_code detected for this floor.', 'pera-portal');
            break;
        }
    }

    return [
        'errors' => $errors,
        'payload' => $payload,
    ];
}

function pera_portal_units_manager_handle_plan_upload($unit_id)
{
    if (!isset($_FILES['unit_detail_plan_file']) || !is_array($_FILES['unit_detail_plan_file'])) {
        return null;
    }

    if (!isset($_FILES['unit_detail_plan_file']['size']) || (int) $_FILES['unit_detail_plan_file']['size'] <= 0) {
        return null;
    }

    $file_name = isset($_FILES['unit_detail_plan_file']['name']) ? sanitize_file_name((string) $_FILES['unit_detail_plan_file']['name']) : '';
    $file_type = wp_check_filetype($file_name, [
        'jpg|jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ]);

    if (empty($file_type['type'])) {
        return new WP_Error('pera_portal_invalid_plan_type', __('Plan file must be JPG, PNG, or PDF.', 'pera-portal'));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_upload('unit_detail_plan_file', $unit_id);

    if (is_wp_error($attachment_id)) {
        return $attachment_id;
    }

    return absint($attachment_id);
}

function pera_portal_units_manager_persist_unit($unit_id, $floor_id, array $payload)
{
    wp_update_post([
        'ID' => $unit_id,
        'post_title' => $payload['unit_code'],
    ]);

    pera_portal_units_manager_update_field('floor', (string) $floor_id, $unit_id);
    pera_portal_units_manager_update_field('unit_code', $payload['unit_code'], $unit_id);
    pera_portal_units_manager_update_field('unit_type', $payload['unit_type'], $unit_id);
    pera_portal_units_manager_update_field('net_size', $payload['net_size'], $unit_id);
    pera_portal_units_manager_update_field('gross_size', $payload['gross_size'], $unit_id);
    pera_portal_units_manager_update_field('price', $payload['price'], $unit_id);
    pera_portal_units_manager_update_field('currency', $payload['currency'], $unit_id);
    pera_portal_units_manager_update_field('status', $payload['status'], $unit_id);
    pera_portal_units_manager_update_field('sort_order', $payload['sort_order'], $unit_id);

    $remove_plan = isset($_POST['remove_plan']) && (string) wp_unslash($_POST['remove_plan']) === '1';
    if ($remove_plan) {
        pera_portal_units_manager_update_field('unit_detail_plan', '', $unit_id);
    }

    $upload_result = pera_portal_units_manager_handle_plan_upload($unit_id);
    if (is_wp_error($upload_result)) {
        return $upload_result;
    }

    if (is_int($upload_result) && $upload_result > 0) {
        pera_portal_units_manager_update_field('unit_detail_plan', $upload_result, $unit_id);
    }

    return true;
}

function pera_portal_units_manager_handle_save_row_action()
{
    if (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'pera-portal'));
    }

    check_admin_referer('pera_portal_units_manager_save_row');

    $base_args = pera_portal_units_manager_get_base_args_from_request();
    $floor_id = $base_args['floor_id'];
    $building_id = $base_args['building_id'];
    $unit_id = isset($_POST['unit_id']) ? absint(wp_unslash($_POST['unit_id'])) : 0;

    if (!pera_portal_units_manager_floor_matches_context($floor_id, $building_id)) {
        pera_portal_units_manager_redirect_with_notice('error', [__('Invalid floor selected.', 'pera-portal')], $base_args);
    }

    if ($unit_id <= 0 || get_post_type($unit_id) !== 'pera_unit') {
        pera_portal_units_manager_redirect_with_notice('error', [__('Invalid unit selected.', 'pera-portal')], $base_args);
    }

    $payload = [
        'unit_code' => isset($_POST['unit_code']) ? wp_unslash($_POST['unit_code']) : '',
        'unit_type' => isset($_POST['unit_type']) ? wp_unslash($_POST['unit_type']) : '',
        'net_size' => isset($_POST['net_size']) ? wp_unslash($_POST['net_size']) : '',
        'gross_size' => isset($_POST['gross_size']) ? wp_unslash($_POST['gross_size']) : '',
        'price' => isset($_POST['price']) ? wp_unslash($_POST['price']) : '',
        'currency' => isset($_POST['currency']) ? wp_unslash($_POST['currency']) : '',
        'status' => isset($_POST['status']) ? wp_unslash($_POST['status']) : '',
        'sort_order' => isset($_POST['sort_order']) ? wp_unslash($_POST['sort_order']) : '',
    ];

    $validated = pera_portal_units_manager_validate_unit_payload($unit_id, $floor_id, $payload);
    if (!empty($validated['errors'])) {
        pera_portal_units_manager_redirect_with_notice('error', $validated['errors'], $base_args, [$unit_id => $validated['payload']]);
    }

    $save_result = pera_portal_units_manager_persist_unit($unit_id, $floor_id, $validated['payload']);
    if (is_wp_error($save_result)) {
        pera_portal_units_manager_redirect_with_notice('error', [$save_result->get_error_message()], $base_args, [$unit_id => $validated['payload']]);
    }

    pera_portal_units_manager_redirect_with_notice('success', [__('Unit saved successfully.', 'pera-portal')], $base_args);
}

function pera_portal_units_manager_handle_create_row_action()
{
    if (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'pera-portal'));
    }

    check_admin_referer('pera_portal_units_manager_create_row');

    $base_args = pera_portal_units_manager_get_base_args_from_request();
    $floor_id = $base_args['floor_id'];
    $building_id = $base_args['building_id'];

    if (!pera_portal_units_manager_floor_matches_context($floor_id, $building_id)) {
        pera_portal_units_manager_redirect_with_notice('error', [__('Invalid floor selected.', 'pera-portal')], $base_args);
    }

    $payload = [
        'unit_code' => isset($_POST['unit_code']) ? wp_unslash($_POST['unit_code']) : '',
        'unit_type' => isset($_POST['unit_type']) ? wp_unslash($_POST['unit_type']) : '',
        'net_size' => isset($_POST['net_size']) ? wp_unslash($_POST['net_size']) : '',
        'gross_size' => isset($_POST['gross_size']) ? wp_unslash($_POST['gross_size']) : '',
        'price' => isset($_POST['price']) ? wp_unslash($_POST['price']) : '',
        'currency' => isset($_POST['currency']) ? wp_unslash($_POST['currency']) : '',
        'status' => isset($_POST['status']) ? wp_unslash($_POST['status']) : '',
        'sort_order' => isset($_POST['sort_order']) ? wp_unslash($_POST['sort_order']) : '',
    ];

    $validated = pera_portal_units_manager_validate_unit_payload(0, $floor_id, $payload);
    if (!empty($validated['errors'])) {
        pera_portal_units_manager_redirect_with_notice('error', $validated['errors'], $base_args, [], $validated['payload']);
    }

    $unit_id = wp_insert_post([
        'post_type' => 'pera_unit',
        'post_status' => 'publish',
        'post_title' => $validated['payload']['unit_code'],
    ], true);

    if (is_wp_error($unit_id)) {
        pera_portal_units_manager_redirect_with_notice('error', [$unit_id->get_error_message()], $base_args, [], $validated['payload']);
    }

    $save_result = pera_portal_units_manager_persist_unit((int) $unit_id, $floor_id, $validated['payload']);
    if (is_wp_error($save_result)) {
        wp_delete_post((int) $unit_id, true);
        pera_portal_units_manager_redirect_with_notice('error', [$save_result->get_error_message()], $base_args, [], $validated['payload']);
    }

    pera_portal_units_manager_redirect_with_notice('success', [__('Unit created successfully.', 'pera-portal')], $base_args);
}

function pera_portal_units_manager_handle_delete_row_action()
{
    if (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'pera-portal'));
    }

    check_admin_referer('pera_portal_units_manager_delete_row');

    $base_args = pera_portal_units_manager_get_base_args_from_request();
    $floor_id = $base_args['floor_id'];
    $building_id = $base_args['building_id'];
    $unit_id = isset($_POST['unit_id']) ? absint(wp_unslash($_POST['unit_id'])) : 0;

    if (!pera_portal_units_manager_floor_matches_context($floor_id, $building_id)) {
        pera_portal_units_manager_redirect_with_notice('error', [__('Invalid floor selected.', 'pera-portal')], $base_args);
    }

    if ($unit_id <= 0 || get_post_type($unit_id) !== 'pera_unit') {
        pera_portal_units_manager_redirect_with_notice('error', [__('Invalid unit selected.', 'pera-portal')], $base_args);
    }

    $unit_floor = absint((string) pera_portal_units_manager_get_field('floor', $unit_id));
    if ($unit_floor !== $floor_id) {
        pera_portal_units_manager_redirect_with_notice('error', [__('Unit does not belong to selected floor.', 'pera-portal')], $base_args);
    }

    wp_trash_post($unit_id);

    pera_portal_units_manager_redirect_with_notice('success', [__('Unit moved to trash.', 'pera-portal')], $base_args);
}

function pera_portal_units_manager_handle_export_csv_action()
{
    if (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'pera-portal'));
    }

    check_admin_referer('pera_portal_units_manager_export_csv');

    $base_args = pera_portal_units_manager_get_base_args_from_request();
    $building_id = absint($base_args['building_id']);
    $floor_id = absint($base_args['floor_id']);
    $status_filter = (string) $base_args['status'];
    $unit_code_search = (string) $base_args['unit_code'];

    if (!in_array($status_filter, ['all', 'available', 'reserved', 'sold'], true)) {
        $status_filter = 'all';
    }

    if (!pera_portal_units_manager_floor_matches_context($floor_id, $building_id)) {
        wp_safe_redirect(add_query_arg([
            'page' => 'pera-portal-units-manager',
            'building_id' => $building_id,
            'floor_id' => $floor_id,
        ], admin_url('admin.php')));
        exit;
    }

    $floor_post = get_post($floor_id);
    if (!$floor_post instanceof WP_Post || $floor_post->post_type !== 'pera_floor') {
        wp_safe_redirect(add_query_arg(['page' => 'pera-portal-units-manager'], admin_url('admin.php')));
        exit;
    }

    $floor_building_id = pera_portal_units_manager_normalize_related_post_id(pera_portal_units_manager_get_field('building', $floor_id));
    if ($building_id <= 0 && $floor_building_id > 0) {
        $building_id = $floor_building_id;
    }

    $building_post = $building_id > 0 ? get_post($building_id) : null;
    $building_title = ($building_post instanceof WP_Post && $building_post->post_type === 'pera_building') ? (string) $building_post->post_title : '';

    $units = pera_portal_units_manager_get_units_for_floor($floor_id);
    $units = pera_portal_units_manager_apply_filters($units, $status_filter, $unit_code_search);

    $filename = sprintf(
        'pera-portal-units-building-%d-floor-%d-%s.csv',
        $building_id,
        $floor_id,
        gmdate('Ymd-His')
    );

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        wp_die(esc_html__('Unable to prepare CSV export.', 'pera-portal'));
    }

    $headers = [
        'building_id',
        'building_title',
        'floor_id',
        'floor_title',
        'unit_id',
        'unit_code',
        'unit_type',
        'net_size',
        'gross_size',
        'price',
        'currency',
        'status',
        'sort_order',
        'detail_plan_url',
    ];
    fputcsv($output, $headers);

    foreach ($units as $unit_row) {
        $plan_meta = pera_portal_units_manager_get_plan_meta($unit_row['unit_detail_plan'] ?? null);

        fputcsv($output, [
            $building_id,
            $building_title,
            $floor_id,
            (string) $floor_post->post_title,
            (int) ($unit_row['id'] ?? 0),
            (string) ($unit_row['unit_code'] ?? ''),
            (string) ($unit_row['unit_type'] ?? ''),
            (string) ($unit_row['net_size'] ?? ''),
            (string) ($unit_row['gross_size'] ?? ''),
            (string) ($unit_row['price'] ?? ''),
            strtoupper(sanitize_text_field((string) ($unit_row['currency'] ?? ''))),
            sanitize_key((string) ($unit_row['status'] ?? '')),
            (string) ($unit_row['sort_order'] ?? ''),
            (string) ($plan_meta['url'] ?? ''),
        ]);
    }

    fclose($output);
    exit;
}

function pera_portal_render_units_manager_page()
{
    if (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'pera-portal'));
    }

    $building_id = isset($_GET['building_id']) ? absint(wp_unslash($_GET['building_id'])) : 0;
    $floor_id = isset($_GET['floor_id']) ? absint(wp_unslash($_GET['floor_id'])) : 0;
    $status_filter = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : 'all';
    $unit_code_search = isset($_GET['unit_code']) ? sanitize_text_field((string) wp_unslash($_GET['unit_code'])) : '';
    $flash_notice = pera_portal_units_manager_get_flash_notice();

    if (!in_array($status_filter, ['all', 'available', 'reserved', 'sold'], true)) {
        $status_filter = 'all';
    }

    $buildings = pera_portal_units_manager_get_building_options();
    $floors = pera_portal_units_manager_get_floor_options_for_building($building_id);

    $valid_floor_ids = array_map('absint', wp_list_pluck($floors, 'ID'));
    if ($floor_id > 0 && !in_array($floor_id, $valid_floor_ids, true)) {
        $floor_id = 0;
    }

    $diagnostics_report = null;
    $diagnostics_error = '';
    $diagnostics_map = [];

    if ($floor_id > 0) {
        $diagnostics_report = PeraPortalDiagnosticsService::runForFloor($floor_id);
        if (is_wp_error($diagnostics_report)) {
            $diagnostics_error = $diagnostics_report->get_error_message();
            $diagnostics_report = null;
        } else {
            $diagnostics_map = pera_portal_units_manager_build_unit_diagnostics_map(
                isset($diagnostics_report['issues']) && is_array($diagnostics_report['issues']) ? $diagnostics_report['issues'] : []
            );
        }
    }

    $units = [];
    if ($floor_id > 0) {
        $units = pera_portal_units_manager_get_units_for_floor($floor_id);
        $units = pera_portal_units_manager_apply_filters($units, $status_filter, $unit_code_search);
    }

    if (is_array($flash_notice) && !empty($flash_notice['row_state']) && is_array($flash_notice['row_state'])) {
        foreach ($units as &$unit_row) {
            $unit_id = (int) $unit_row['id'];
            if (isset($flash_notice['row_state'][$unit_id]) && is_array($flash_notice['row_state'][$unit_id])) {
                $unit_row = array_merge($unit_row, $flash_notice['row_state'][$unit_id]);
            }
        }
        unset($unit_row);
    }

    $new_row_defaults = [
        'unit_code' => '',
        'unit_type' => '',
        'net_size' => '',
        'gross_size' => '',
        'price' => '',
        'currency' => 'EUR',
        'status' => 'available',
        'sort_order' => '',
    ];

    if (is_array($flash_notice) && !empty($flash_notice['new_row_state']) && is_array($flash_notice['new_row_state'])) {
        $new_row_defaults = array_merge($new_row_defaults, $flash_notice['new_row_state']);
    }

    $reset_url = add_query_arg([
        'page' => 'pera-portal-units-manager',
    ], admin_url('admin.php'));

    $diagnostics_url = '';
    $export_url = '';
    if ($building_id > 0 && $floor_id > 0) {
        $diagnostics_url = wp_nonce_url(add_query_arg([
            'page' => 'pera-portal-diagnostics',
            'building_id' => $building_id,
            'floor_id' => $floor_id,
            'run_diagnostics' => 1,
        ], admin_url('admin.php')), 'pera_portal_run_diagnostics');

        $export_url = wp_nonce_url(add_query_arg([
            'action' => 'pera_portal_units_manager_export_csv',
            'building_id' => $building_id,
            'floor_id' => $floor_id,
            'status' => $status_filter,
            'unit_code' => $unit_code_search,
        ], admin_url('admin-post.php')), 'pera_portal_units_manager_export_csv');
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Units Manager', 'pera-portal'); ?></h1>

        <form method="get">
            <input type="hidden" name="page" value="pera-portal-units-manager" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="pera_portal_units_building_id"><?php echo esc_html__('Building', 'pera-portal'); ?></label></th>
                        <td>
                            <select class="regular-text" name="building_id" id="pera_portal_units_building_id">
                                <option value="0"><?php echo esc_html__('Select a building', 'pera-portal'); ?></option>
                                <?php foreach ($buildings as $building_post) : ?>
                                    <option value="<?php echo esc_attr((string) $building_post->ID); ?>" <?php selected($building_id, (int) $building_post->ID); ?>>
                                        <?php echo esc_html((string) $building_post->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pera_portal_units_floor_id"><?php echo esc_html__('Floor', 'pera-portal'); ?></label></th>
                        <td>
                            <select class="regular-text" name="floor_id" id="pera_portal_units_floor_id" <?php disabled($building_id <= 0); ?>>
                                <option value="0"><?php echo esc_html__('Select a floor', 'pera-portal'); ?></option>
                                <?php foreach ($floors as $floor_post) : ?>
                                    <option value="<?php echo esc_attr((string) $floor_post->ID); ?>" <?php selected($floor_id, (int) $floor_post->ID); ?>>
                                        <?php echo esc_html((string) $floor_post->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pera_portal_units_unit_code"><?php echo esc_html__('Search Unit Code', 'pera-portal'); ?></label></th>
                        <td><input class="regular-text" type="text" name="unit_code" id="pera_portal_units_unit_code" value="<?php echo esc_attr($unit_code_search); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pera_portal_units_status"><?php echo esc_html__('Status', 'pera-portal'); ?></label></th>
                        <td>
                            <select class="regular-text" name="status" id="pera_portal_units_status">
                                <option value="all" <?php selected($status_filter, 'all'); ?>><?php echo esc_html__('All', 'pera-portal'); ?></option>
                                <option value="available" <?php selected($status_filter, 'available'); ?>><?php echo esc_html__('Available', 'pera-portal'); ?></option>
                                <option value="reserved" <?php selected($status_filter, 'reserved'); ?>><?php echo esc_html__('Reserved', 'pera-portal'); ?></option>
                                <option value="sold" <?php selected($status_filter, 'sold'); ?>><?php echo esc_html__('Sold', 'pera-portal'); ?></option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Apply Filters', 'pera-portal'); ?></button>
                <a class="button" href="<?php echo esc_url($reset_url); ?>"><?php echo esc_html__('Reset Filters', 'pera-portal'); ?></a>
                <?php if ($export_url !== '') : ?>
                    <a class="button" href="<?php echo esc_url($export_url); ?>"><?php echo esc_html__('Export CSV', 'pera-portal'); ?></a>
                <?php endif; ?>
                <?php if ($diagnostics_url !== '') : ?>
                    <a class="button" href="<?php echo esc_url($diagnostics_url); ?>"><?php echo esc_html__('View Diagnostics Summary', 'pera-portal'); ?></a>
                <?php endif; ?>
            </p>
        </form>

        <?php if (is_array($flash_notice) && !empty($flash_notice['messages']) && is_array($flash_notice['messages'])) : ?>
            <div class="notice notice-<?php echo esc_attr($flash_notice['type'] === 'success' ? 'success' : 'error'); ?>"><p><?php echo esc_html(implode(' ', $flash_notice['messages'])); ?></p></div>
        <?php endif; ?>

        <?php if ($diagnostics_error !== '') : ?>
            <div class="notice notice-error"><p><?php echo esc_html($diagnostics_error); ?></p></div>
        <?php endif; ?>

        <?php if ($floor_id <= 0) : ?>
            <div class="notice notice-info"><p><?php echo esc_html__('Select a building and floor to load units.', 'pera-portal'); ?></p></div>
        <?php else : ?>
            <?php if (is_array($diagnostics_report)) : ?>
                <div class="notice notice-info inline">
                    <p>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: 1: error count, 2: warning count */
                            __('Diagnostics: %1$d errors, %2$d warnings for selected floor.', 'pera-portal'),
                            (int) ($diagnostics_report['summary']['error_count'] ?? 0),
                            (int) ($diagnostics_report['summary']['warning_count'] ?? 0)
                        ));
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <h2><?php echo esc_html__('Add Unit', 'pera-portal'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="pera_portal_units_manager_create_row" />
                <?php wp_nonce_field('pera_portal_units_manager_create_row'); ?>
                <input type="hidden" name="building_id" value="<?php echo esc_attr((string) $building_id); ?>" />
                <input type="hidden" name="floor_id" value="<?php echo esc_attr((string) $floor_id); ?>" />
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>" />
                <input type="hidden" name="unit_code" value="<?php echo esc_attr($unit_code_search); ?>" />

                <table class="widefat striped" style="margin-bottom:16px;">
                    <tbody>
                        <tr>
                            <td><input type="text" name="unit_code" value="<?php echo esc_attr((string) $new_row_defaults['unit_code']); ?>" placeholder="<?php echo esc_attr__('Unit Code', 'pera-portal'); ?>" required /></td>
                            <td><input type="text" name="unit_type" value="<?php echo esc_attr((string) $new_row_defaults['unit_type']); ?>" placeholder="<?php echo esc_attr__('Unit Type', 'pera-portal'); ?>" /></td>
                            <td><input type="text" name="net_size" value="<?php echo esc_attr((string) $new_row_defaults['net_size']); ?>" placeholder="<?php echo esc_attr__('Net Size', 'pera-portal'); ?>" /></td>
                            <td><input type="text" name="gross_size" value="<?php echo esc_attr((string) $new_row_defaults['gross_size']); ?>" placeholder="<?php echo esc_attr__('Gross Size', 'pera-portal'); ?>" /></td>
                            <td><input type="text" name="price" value="<?php echo esc_attr((string) $new_row_defaults['price']); ?>" placeholder="<?php echo esc_attr__('Price', 'pera-portal'); ?>" /></td>
                            <td>
                                <select name="currency" required>
                                    <?php foreach (pera_portal_units_manager_get_allowed_currencies() as $currency_value) : ?>
                                        <option value="<?php echo esc_attr($currency_value); ?>" <?php selected((string) $new_row_defaults['currency'], $currency_value); ?>><?php echo esc_html($currency_value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="status" required>
                                    <?php foreach (pera_portal_units_manager_get_allowed_statuses() as $status_value) : ?>
                                        <option value="<?php echo esc_attr($status_value); ?>" <?php selected((string) $new_row_defaults['status'], $status_value); ?>><?php echo esc_html(ucfirst($status_value)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="sort_order" value="<?php echo esc_attr((string) $new_row_defaults['sort_order']); ?>" placeholder="<?php echo esc_attr__('Sort Order', 'pera-portal'); ?>" /></td>
                            <td><input type="file" name="unit_detail_plan_file" accept=".jpg,.jpeg,.png,.pdf" /></td>
                            <td><button type="submit" class="button button-primary"><?php echo esc_html__('Create', 'pera-portal'); ?></button></td>
                        </tr>
                    </tbody>
                </table>
            </form>

            <table class="widefat striped fixed">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Unit ID', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Unit Code', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Type', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Net Size', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Gross Size', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Price', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Currency', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Status', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Sort Order', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Plan', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Diagnostics', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Actions', 'pera-portal'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($units)) : ?>
                        <tr>
                            <td colspan="12"><?php echo esc_html__('No units found for the selected filters.', 'pera-portal'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($units as $unit_row) : ?>
                            <?php
                            $unit_id = (int) $unit_row['id'];
                            $unit_diagnostics = isset($diagnostics_map[$unit_id]) ? $diagnostics_map[$unit_id] : ['error' => 0, 'warning' => 0];
                            $issue_count = (int) $unit_diagnostics['error'] + (int) $unit_diagnostics['warning'];

                            if ((int) $unit_diagnostics['error'] > 0) {
                                $diagnostics_label = sprintf(__('Error (%d)', 'pera-portal'), $issue_count);
                            } elseif ((int) $unit_diagnostics['warning'] > 0) {
                                $diagnostics_label = sprintf(__('Warning (%d)', 'pera-portal'), $issue_count);
                            } else {
                                $diagnostics_label = __('OK', 'pera-portal');
                            }

                            $plan_meta = pera_portal_units_manager_get_plan_meta($unit_row['unit_detail_plan']);
                            $is_plan_pdf = $plan_meta['attachment_id'] > 0 && wp_attachment_is('application/pdf', $plan_meta['attachment_id']);
                            $row_form_id = 'pera-portal-unit-row-' . $unit_id;
                            ?>
                            <tr>
                                <td><?php echo esc_html((string) $unit_id); ?></td>
                                <td><input form="<?php echo esc_attr($row_form_id); ?>" type="text" name="unit_code" value="<?php echo esc_attr((string) $unit_row['unit_code']); ?>" required /></td>
                                <td><input form="<?php echo esc_attr($row_form_id); ?>" type="text" name="unit_type" value="<?php echo esc_attr((string) $unit_row['unit_type']); ?>" /></td>
                                <td><input form="<?php echo esc_attr($row_form_id); ?>" type="text" name="net_size" value="<?php echo esc_attr((string) $unit_row['net_size']); ?>" /></td>
                                <td><input form="<?php echo esc_attr($row_form_id); ?>" type="text" name="gross_size" value="<?php echo esc_attr((string) $unit_row['gross_size']); ?>" /></td>
                                <td><input form="<?php echo esc_attr($row_form_id); ?>" type="text" name="price" value="<?php echo esc_attr((string) $unit_row['price']); ?>" /></td>
                                <td>
                                    <select form="<?php echo esc_attr($row_form_id); ?>" name="currency" required>
                                        <?php foreach (pera_portal_units_manager_get_allowed_currencies() as $currency_value) : ?>
                                            <option value="<?php echo esc_attr($currency_value); ?>" <?php selected(strtoupper((string) $unit_row['currency']), $currency_value); ?>><?php echo esc_html($currency_value); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select form="<?php echo esc_attr($row_form_id); ?>" name="status" required>
                                        <?php foreach (pera_portal_units_manager_get_allowed_statuses() as $status_value) : ?>
                                            <option value="<?php echo esc_attr($status_value); ?>" <?php selected((string) $unit_row['status'], $status_value); ?>><?php echo esc_html(ucfirst($status_value)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input form="<?php echo esc_attr($row_form_id); ?>" type="text" name="sort_order" value="<?php echo esc_attr((string) $unit_row['sort_order']); ?>" /></td>
                                <td>
                                    <?php if ($plan_meta['has_plan'] && $plan_meta['url'] !== '') : ?>
                                        <a href="<?php echo esc_url((string) $plan_meta['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($is_plan_pdf ? __('Open PDF', 'pera-portal') : __('Preview', 'pera-portal')); ?></a><br />
                                    <?php else : ?>
                                        <?php echo esc_html__('Missing', 'pera-portal'); ?><br />
                                    <?php endif; ?>
                                    <input form="<?php echo esc_attr($row_form_id); ?>" type="file" name="unit_detail_plan_file" accept=".jpg,.jpeg,.png,.pdf" />
                                    <?php if ($plan_meta['has_plan']) : ?>
                                        <label>
                                            <input form="<?php echo esc_attr($row_form_id); ?>" type="checkbox" name="remove_plan" value="1" />
                                            <?php echo esc_html__('Remove', 'pera-portal'); ?>
                                        </label>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($diagnostics_label); ?></td>
                                <td>
                                    <form id="<?php echo esc_attr($row_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="display:inline;">
                                        <input type="hidden" name="action" value="pera_portal_units_manager_save_row" />
                                        <?php wp_nonce_field('pera_portal_units_manager_save_row'); ?>
                                        <input type="hidden" name="unit_id" value="<?php echo esc_attr((string) $unit_id); ?>" />
                                        <input type="hidden" name="building_id" value="<?php echo esc_attr((string) $building_id); ?>" />
                                        <input type="hidden" name="floor_id" value="<?php echo esc_attr((string) $floor_id); ?>" />
                                        <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>" />
                                        <input type="hidden" name="unit_code" value="<?php echo esc_attr($unit_code_search); ?>" />
                                        <button type="submit" class="button button-primary button-small"><?php echo esc_html__('Save', 'pera-portal'); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline; margin-left: 6px;" onsubmit="return window.confirm('<?php echo esc_js(__('Move this unit to trash?', 'pera-portal')); ?>');">
                                        <input type="hidden" name="action" value="pera_portal_units_manager_delete_row" />
                                        <?php wp_nonce_field('pera_portal_units_manager_delete_row'); ?>
                                        <input type="hidden" name="unit_id" value="<?php echo esc_attr((string) $unit_id); ?>" />
                                        <input type="hidden" name="building_id" value="<?php echo esc_attr((string) $building_id); ?>" />
                                        <input type="hidden" name="floor_id" value="<?php echo esc_attr((string) $floor_id); ?>" />
                                        <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>" />
                                        <input type="hidden" name="unit_code" value="<?php echo esc_attr($unit_code_search); ?>" />
                                        <button type="submit" class="button button-small"><?php echo esc_html__('Delete', 'pera-portal'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_post_pera_portal_units_manager_save_row', 'pera_portal_units_manager_handle_save_row_action');
add_action('admin_post_pera_portal_units_manager_create_row', 'pera_portal_units_manager_handle_create_row_action');
add_action('admin_post_pera_portal_units_manager_delete_row', 'pera_portal_units_manager_handle_delete_row_action');
add_action('admin_post_pera_portal_units_manager_export_csv', 'pera_portal_units_manager_handle_export_csv_action');
add_action('admin_menu', 'pera_portal_register_units_manager_submenu');
add_action('network_admin_menu', 'pera_portal_register_units_manager_submenu');
add_action('admin_menu', 'pera_portal_hide_disallowed_units_manager_submenu', 99);
add_action('network_admin_menu', 'pera_portal_hide_disallowed_units_manager_submenu', 99);
