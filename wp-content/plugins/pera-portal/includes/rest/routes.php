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

function pera_portal_rest_sanitize_mode($value)
{
    $mode = sanitize_key((string) $value);

    if (!in_array($mode, ['internal', 'external', 'investor'], true)) {
        return 'external';
    }

    return $mode;
}

function pera_portal_rest_post_statuses_for_mode($mode)
{
    if ($mode === 'internal') {
        return ['publish', 'private', 'draft'];
    }

    return ['publish'];
}

function pera_portal_rest_floor_belongs_to_building($floor_id, $building_id)
{
    $floor_building = function_exists('get_field') ? get_field('building', $floor_id) : get_post_meta($floor_id, 'building', true);

    if (is_array($floor_building) && isset($floor_building['ID'])) {
        $floor_building = (int) $floor_building['ID'];
    }

    if (is_object($floor_building) && isset($floor_building->ID)) {
        $floor_building = (int) $floor_building->ID;
    }

    if (is_numeric($floor_building)) {
        return (int) $floor_building === (int) $building_id;
    }

    return false;
}

function pera_portal_rest_get_units(WP_REST_Request $request)
{
    $floor_id = absint($request->get_param('floor_id'));
    $building_id = absint($request->get_param('building_id'));
    $mode = pera_portal_rest_sanitize_mode($request->get_param('mode'));

    if ($mode === 'internal' && (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access())) {
        $mode = 'external';
    }

    if ($building_id > 0 && $floor_id <= 0) {
        return new WP_Error('floor_required_with_building', __('floor_id is required when building_id is provided.', 'pera-portal'), ['status' => 400]);
    }

    if ($building_id > 0 && $floor_id > 0 && !pera_portal_rest_floor_belongs_to_building($floor_id, $building_id)) {
        return new WP_Error('floor_not_in_building', __('The requested floor_id does not belong to the provided building_id.', 'pera-portal'), ['status' => 400]);
    }

    if ($floor_id <= 0) {
        return rest_ensure_response([]);
    }

    $query = new WP_Query([
        'post_type' => 'pera_unit',
        'post_status' => pera_portal_rest_post_statuses_for_mode($mode),
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
            'title' => wp_strip_all_tags((string) get_the_title($unit_id), true),
            'unit_code' => sanitize_text_field((string) $unit_code),
            'unit_type' => sanitize_text_field((string) $unit_type),
            'net_size' => $net_size_value,
            'gross_size' => $gross_size_value,
            'price' => $price_value,
            'price_per_sqm' => $price_per_sqm,
            'currency' => sanitize_text_field((string) $currency),
            'status' => $status,
            'detail_plan_url' => esc_url_raw($plan_url),
            'detail_plan_filename' => sanitize_file_name((string) $plan_filename),
            'detail_plan_mime' => sanitize_mime_type((string) $plan_mime),
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
            'title' => sanitize_text_field(wp_strip_all_tags((string) get_the_title($floor_id))),
            'floor_number' => $floor_number === null ? '' : sanitize_text_field(wp_strip_all_tags((string) $floor_number)),
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

function pera_portal_rest_permission_diag()
{
    $can_access = function_exists('pera_portal_current_user_can_access')
        ? (bool) pera_portal_current_user_can_access()
        : current_user_can('manage_options');

    if ($can_access) {
        return true;
    }

    $status = is_user_logged_in() ? 403 : 401;

    return new WP_Error('pera_portal_forbidden', __('Portal access required.', 'pera-portal'), ['status' => $status]);
}

function pera_portal_rest_get_asset_details($type)
{
    $asset_filename = $type === 'script' ? 'portal-viewer.js' : 'portal-viewer.css';
    $asset = [
        'src' => '',
        'version' => '',
    ];

    if ($type === 'script') {
        $scripts = wp_scripts();
        if ($scripts instanceof WP_Scripts && isset($scripts->registered['pera-portal-viewer'])) {
            $registered = $scripts->registered['pera-portal-viewer'];
            $asset['src'] = is_string($registered->src) ? $registered->src : '';
            $asset['version'] = is_scalar($registered->ver) ? (string) $registered->ver : '';
        } else {
            $asset['src'] = pera_portal_get_dist_asset_url($asset_filename);
            $asset['version'] = pera_portal_get_asset_version($asset_filename);
        }
    } else {
        $styles = wp_styles();
        if ($styles instanceof WP_Styles && isset($styles->registered['pera-portal-viewer'])) {
            $registered = $styles->registered['pera-portal-viewer'];
            $asset['src'] = is_string($registered->src) ? $registered->src : '';
            $asset['version'] = is_scalar($registered->ver) ? (string) $registered->ver : '';
        } else {
            $asset['src'] = pera_portal_get_dist_asset_url($asset_filename);
            $asset['version'] = pera_portal_get_asset_version($asset_filename);
        }
    }

    $build_mtime = function_exists('pera_portal_get_build_version_int')
        ? (int) pera_portal_get_build_version_int()
        : 0;

    $build_file = function_exists('pera_portal_get_dist_build_file_path')
        ? (string) pera_portal_get_dist_build_file_path()
        : '';

    $asset_mtime = 0;
    if (function_exists('pera_portal_get_dist_asset_path')) {
        $asset_path = pera_portal_get_dist_asset_path($asset_filename);
        if (is_readable($asset_path)) {
            $asset_file_mtime = @filemtime($asset_path);
            if ($asset_file_mtime !== false) {
                $asset_mtime = (int) $asset_file_mtime;
            }
        }
    }

    $build_version = $build_mtime > 0
        ? (string) $build_mtime
        : (defined('PERA_PORTAL_VERSION') ? (string) PERA_PORTAL_VERSION : '1.0.0');

    $resolved_version = function_exists('pera_portal_get_asset_version')
        ? (string) pera_portal_get_asset_version($asset_filename)
        : ($asset['version'] !== '' ? (string) $asset['version'] : $build_version);

    $asset['build_version'] = $build_version;
    $asset['build_file'] = $build_file;
    $asset['build_mtime'] = $build_mtime;
    $asset['asset_mtime'] = $asset_mtime;
    $asset['resolved_version'] = $resolved_version;

    if ($asset['version'] === '') {
        $asset['version'] = $resolved_version;
    }

    return $asset;
}

function pera_portal_rest_get_diag()
{
    $config = isset($GLOBALS['pera_portal_script_config']) && is_array($GLOBALS['pera_portal_script_config'])
        ? $GLOBALS['pera_portal_script_config']
        : [];

    $queried_object_id = get_queried_object_id();
    $post = $queried_object_id > 0 ? get_post($queried_object_id) : null;

    $shortcode_probe = function_exists('pera_portal_get_shortcode_probe')
        ? pera_portal_get_shortcode_probe(true)
        : [
            'executed' => false,
            'substring_matched' => false,
            'has_shortcode_executed' => false,
            'has_shortcode_matched' => null,
        ];

    $template = '';
    if ($post instanceof WP_Post) {
        $template = get_page_template_slug($post);
        if (!is_string($template)) {
            $template = '';
        }
    }

    return rest_ensure_response([
        'assets_needed' => !empty($GLOBALS['pera_portal_enqueue_assets']),
        'script_config' => [
            'building_id' => isset($config['building_id']) ? absint($config['building_id']) : 0,
            'floor_id' => isset($config['floor_id']) ? absint($config['floor_id']) : 0,
            'mode' => isset($config['mode']) ? sanitize_key((string) $config['mode']) : 'external',
            'rest_url' => isset($config['rest_url']) ? esc_url_raw((string) $config['rest_url']) : esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
            'has_nonce' => !empty($config['nonce']),
        ],
        'request' => [
            'request_uri' => function_exists('pera_portal_request_uri') ? pera_portal_request_uri() : (isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : ''),
            'is_admin' => is_admin(),
            'is_singular' => is_singular(),
            'queried_object_id' => $queried_object_id,
            'template' => $template,
            'rest_request' => defined('REST_REQUEST') && REST_REQUEST,
            'ajax' => wp_doing_ajax(),
            'shortcode_probe' => [
                'substring_matched' => !empty($shortcode_probe['substring_matched']),
                'has_shortcode_executed' => !empty($shortcode_probe['has_shortcode_executed']),
                'has_shortcode_matched' => array_key_exists('has_shortcode_matched', $shortcode_probe)
                    ? $shortcode_probe['has_shortcode_matched']
                    : null,
            ],
        ],
        'assets' => [
            'css_enqueued' => wp_style_is('pera-portal-viewer', 'enqueued'),
            'js_enqueued' => wp_script_is('pera-portal-viewer', 'enqueued'),
            'portal_viewer_css' => pera_portal_rest_get_asset_details('style'),
            'portal_viewer_js' => pera_portal_rest_get_asset_details('script'),
        ],
    ]);
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
        'permission_callback' => 'pera_portal_current_user_can_access',
        'args' => [
            'floor_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static function ($value) {
                    return absint($value) > 0;
                },
            ],
            'building_id' => [
                'required' => false,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static function ($value) {
                    return absint($value) >= 0;
                },
            ],
            'mode' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'pera_portal_rest_sanitize_mode',
                'validate_callback' => static function ($value) {
                    return in_array(pera_portal_rest_sanitize_mode($value), ['internal', 'external', 'investor'], true);
                },
                'default' => 'external',
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

    register_rest_route(PERA_PORTAL_REST_NAMESPACE, '/diag', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pera_portal_rest_get_diag',
        'permission_callback' => 'pera_portal_rest_permission_diag',
    ]);
}

add_action('rest_api_init', 'pera_portal_register_rest_routes');
add_filter('rest_pre_serve_request', 'pera_portal_rest_serve_raw_floor_svg', 10, 4);
