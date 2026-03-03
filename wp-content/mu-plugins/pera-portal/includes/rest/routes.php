<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_rest_get_floor(WP_REST_Request $request)
{
    $floor_id = absint($request->get_param('floor_id'));
    $svg_path = '';
    $svg_url = '';

    if (function_exists('get_field')) {
        $file = get_field('floor_svg', $floor_id);

        if (is_array($file) && !empty($file['url'])) {
            $svg_url = (string) $file['url'];
        } elseif (is_numeric($file)) {
            $attachment_url = wp_get_attachment_url((int) $file);
            if (is_string($attachment_url) && $attachment_url !== '') {
                $svg_url = $attachment_url;
            }
        }

        if (is_array($file) && !empty($file['ID']) && is_numeric($file['ID'])) {
            $attached_file = get_attached_file((int) $file['ID']);
            if (is_string($attached_file) && $attached_file !== '') {
                $svg_path = $attached_file;
            }
        } elseif (is_numeric($file)) {
            $attached_file = get_attached_file((int) $file);
            if (is_string($attached_file) && $attached_file !== '') {
                $svg_path = $attached_file;
            }
        }
    }

    if ($svg_path === '' && $svg_url !== '') {
        $attachment_id = attachment_url_to_postid($svg_url);
        if ($attachment_id > 0) {
            $attached_file = get_attached_file($attachment_id);
            if (is_string($attached_file) && $attached_file !== '') {
                $svg_path = $attached_file;
            }
        }
    }

    if ($svg_path === '') {
        $svg_path = PERA_PORTAL_PATH . '/data/fixtures/demo-floor.svg';
    }

    $svg_markup = '';
    if ($svg_path !== '' && file_exists($svg_path)) {
        $svg_markup = (string) file_get_contents($svg_path);
    } elseif ($svg_url !== '') {
        $remote_svg = wp_remote_get($svg_url, ['timeout' => 10]);
        if (!is_wp_error($remote_svg) && (int) wp_remote_retrieve_response_code($remote_svg) === 200) {
            $svg_markup = (string) wp_remote_retrieve_body($remote_svg);
        }
    }

    if ($svg_markup === '') {
        return new WP_Error('pera_portal_floor_svg_missing', __('Floor SVG not found.', 'pera-portal'), ['status' => 404]);
    }

    $response = new WP_REST_Response($svg_markup, 200);
    $response->header('Content-Type', 'image/svg+xml; charset=' . get_option('blog_charset'));

    return $response;
}

function pera_portal_rest_permission_public_floor(WP_REST_Request $request)
{
    $floor_id = absint($request->get_param('floor_id'));
    $floor = get_post($floor_id);

    if (!$floor || $floor->post_type !== 'pera_floor') {
        return new WP_Error('pera_portal_floor_not_found', __('Floor not found.', 'pera-portal'), ['status' => 404]);
    }

    if ($floor->post_status === 'publish') {
        return true;
    }

    if ($floor->post_status === 'private') {
        return new WP_Error('pera_portal_floor_forbidden', __('Floor is private.', 'pera-portal'), ['status' => 403]);
    }

    return new WP_Error('pera_portal_floor_not_found', __('Floor not found.', 'pera-portal'), ['status' => 404]);
}

function pera_portal_rest_permission_public_building_floors(WP_REST_Request $request)
{
    $building_id = absint($request->get_param('building_id'));
    $building = get_post($building_id);

    if (!$building || $building->post_type !== 'pera_building') {
        return new WP_Error('pera_portal_building_not_found', __('Building not found.', 'pera-portal'), ['status' => 404]);
    }

    if ($building->post_status === 'publish') {
        return true;
    }

    if ($building->post_status === 'private') {
        return new WP_Error('pera_portal_building_forbidden', __('Building is private.', 'pera-portal'), ['status' => 403]);
    }

    return new WP_Error('pera_portal_building_not_found', __('Building not found.', 'pera-portal'), ['status' => 404]);
}

function pera_portal_rest_permission_public_floor_units(WP_REST_Request $request)
{
    $floor_id = absint($request->get_param('floor_id'));
    $floor = get_post($floor_id);

    if (!$floor || $floor->post_type !== 'pera_floor') {
        return new WP_Error('pera_portal_floor_not_found', __('Floor not found.', 'pera-portal'), ['status' => 404]);
    }

    if ($floor->post_status === 'publish') {
        return true;
    }

    if ($floor->post_status === 'private') {
        return new WP_Error('pera_portal_floor_forbidden', __('Floor is private.', 'pera-portal'), ['status' => 403]);
    }

    return new WP_Error('pera_portal_floor_not_found', __('Floor not found.', 'pera-portal'), ['status' => 404]);
}

function pera_portal_rest_serve_raw_floor_svg($served, $result, $request, $server)
{
    if ($request->get_route() !== '/' . PERA_PORTAL_REST_NAMESPACE . '/floor') {
        return $served;
    }

    if (!($result instanceof WP_REST_Response)) {
        return $served;
    }

    $data = $result->get_data();
    if (!is_string($data)) {
        return $served;
    }

    $server->send_header('Content-Type', 'image/svg+xml; charset=' . get_option('blog_charset'));
    echo $data;

    return true;
}

function pera_portal_rest_get_units(WP_REST_Request $request)
{
    $floor_id = absint($request->get_param('floor_id'));

    if ($floor_id <= 0) {
        return rest_ensure_response([]);
    }

    $query = new WP_Query([
        'post_type' => 'pera_unit',
        // External viewer mode depends on public REST access, so only published content is exposed.
        'post_status' => ['publish'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => 'floor',
                'value' => (string) $floor_id,
                'compare' => '=',
            ],
        ],
    ]);

    $units = [];

    foreach ($query->posts as $unit_post) {
        $unit_id = (int) $unit_post->ID;

        $unit_code = function_exists('get_field') ? get_field('unit_code', $unit_id) : get_post_meta($unit_id, 'unit_code', true);
        $unit_type = function_exists('get_field') ? get_field('unit_type', $unit_id) : get_post_meta($unit_id, 'unit_type', true);
        $net_size = function_exists('get_field') ? get_field('net_size', $unit_id) : get_post_meta($unit_id, 'net_size', true);
        $gross_size = function_exists('get_field') ? get_field('gross_size', $unit_id) : get_post_meta($unit_id, 'gross_size', true);
        $price = function_exists('get_field') ? get_field('price', $unit_id) : get_post_meta($unit_id, 'price', true);
        $currency = function_exists('get_field') ? get_field('currency', $unit_id) : get_post_meta($unit_id, 'currency', true);
        $status = function_exists('get_field') ? get_field('status', $unit_id) : get_post_meta($unit_id, 'status', true);
        $plan = function_exists('get_field') ? get_field('unit_detail_plan', $unit_id) : null;

        $plan_url = '';
        $plan_filename = '';
        $plan_mime = '';

        if (is_array($plan)) {
            $plan_url = isset($plan['url']) ? (string) $plan['url'] : '';
            $plan_filename = isset($plan['filename']) ? (string) $plan['filename'] : '';
            $plan_mime = isset($plan['mime_type']) ? (string) $plan['mime_type'] : '';
        }

        $price_value = is_numeric($price) ? (float) $price : null;
        $gross_size_value = is_numeric($gross_size) ? (float) $gross_size : null;
        $net_size_value = is_numeric($net_size) ? (float) $net_size : null;
        $size_for_ppsqm = $gross_size_value !== null ? $gross_size_value : $net_size_value;
        $price_per_sqm = null;

        if ($price_value !== null && $size_for_ppsqm !== null && $size_for_ppsqm > 0) {
            $price_per_sqm = $price_value / $size_for_ppsqm;
        }

        $currency = is_string($currency) ? trim($currency) : $currency;
        $status = is_string($status) ? trim($status) : $status;

        $currency = $currency ? $currency : 'GBP';
        $status = $status ? $status : 'available';
        $status = sanitize_key((string) $status);

        if (!in_array($status, ['available', 'reserved', 'sold'], true)) {
            $status = 'available';
        }

        $units[] = [
            'id' => $unit_id,
            'title' => get_the_title($unit_id),
            'unit_code' => (string) $unit_code,
            'unit_type' => sanitize_text_field((string) $unit_type),
            'net_size' => $net_size_value,
            'gross_size' => $gross_size_value,
            'price' => $price_value,
            'price_per_sqm' => $price_per_sqm,
            'currency' => sanitize_text_field((string) $currency),
            'status' => $status,
            'detail_plan_url' => esc_url_raw($plan_url),
            'detail_plan_filename' => $plan_filename,
            'detail_plan_mime' => $plan_mime,
        ];
    }

    return rest_ensure_response($units);
}

function pera_portal_rest_get_floors(WP_REST_Request $request)
{
    $building_id = absint($request->get_param('building_id'));

    if ($building_id <= 0) {
        return new WP_Error('pera_portal_invalid_building', __('Invalid building ID.', 'pera-portal'), ['status' => 400]);
    }

    $query = new WP_Query([
        'post_type' => 'pera_floor',
        // External viewer mode depends on public REST access, so only published content is exposed.
        'post_status' => ['publish'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => 'building',
                'value' => (string) $building_id,
                'compare' => '=',
            ],
        ],
    ]);

    $floors = [];

    foreach ($query->posts as $floor_post) {
        $floor_id = (int) $floor_post->ID;
        $floor_number = function_exists('get_field') ? get_field('floor_number', $floor_id) : get_post_meta($floor_id, 'floor_number', true);
        $file = function_exists('get_field') ? get_field('floor_svg', $floor_id) : null;

        $svg_url = '';
        if (is_array($file) && !empty($file['url'])) {
            $svg_url = (string) $file['url'];
        } elseif (is_numeric($file)) {
            $attachment_url = wp_get_attachment_url((int) $file);
            if (is_string($attachment_url) && $attachment_url !== '') {
                $svg_url = $attachment_url;
            }
        }

        $floors[] = [
            'id' => $floor_id,
            'title' => get_the_title($floor_id),
            'floor_number' => $floor_number === null ? '' : (string) $floor_number,
            'has_svg' => $svg_url !== '',
            'svg_url' => $svg_url !== '' ? esc_url_raw($svg_url) : '',
        ];
    }

    usort($floors, static function ($a, $b) {
        $a_number = isset($a['floor_number']) ? trim((string) $a['floor_number']) : '';
        $b_number = isset($b['floor_number']) ? trim((string) $b['floor_number']) : '';
        $a_numeric = $a_number !== '' && is_numeric($a_number);
        $b_numeric = $b_number !== '' && is_numeric($b_number);

        if ($a_numeric && $b_numeric) {
            return (float) $a_number <=> (float) $b_number;
        }

        if ($a_numeric) {
            return -1;
        }

        if ($b_numeric) {
            return 1;
        }

        return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });

    return rest_ensure_response($floors);
}

function pera_portal_register_rest_routes()
{
    register_rest_route(PERA_PORTAL_REST_NAMESPACE, '/floor', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pera_portal_rest_get_floor',
        'permission_callback' => 'pera_portal_rest_permission_public_floor',
        'args' => [
            'floor_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static function ($value) {
                    return absint($value) > 0;
                },
            ],
        ],
    ]);

    register_rest_route(PERA_PORTAL_REST_NAMESPACE, '/units', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pera_portal_rest_get_units',
        'permission_callback' => 'pera_portal_rest_permission_public_floor_units',
        'args' => [
            'floor_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static function ($value) {
                    return absint($value) > 0;
                },
            ],
        ],
    ]);

    register_rest_route(PERA_PORTAL_REST_NAMESPACE, '/floors', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pera_portal_rest_get_floors',
        'permission_callback' => 'pera_portal_rest_permission_public_building_floors',
        'args' => [
            'building_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static function ($value) {
                    return absint($value) > 0;
                },
            ],
        ],
    ]);
}

add_action('rest_api_init', 'pera_portal_register_rest_routes');
add_filter('rest_pre_serve_request', 'pera_portal_rest_serve_raw_floor_svg', 10, 4);
