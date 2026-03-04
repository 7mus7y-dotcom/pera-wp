<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_mark_assets_needed()
{
    $GLOBALS['pera_portal_enqueue_assets'] = true;
}

function pera_portal_set_script_config(array $config)
{
    $GLOBALS['pera_portal_script_config'] = $config;
}

function pera_portal_get_dist_asset_path($asset)
{
    return PERA_PORTAL_PATH . '/assets/dist/' . ltrim($asset, '/');
}

function pera_portal_get_dist_asset_url($asset)
{
    return PERA_PORTAL_URL . '/assets/dist/' . ltrim($asset, '/');
}

function pera_portal_get_dist_asset_version($asset)
{
    $asset_path = pera_portal_get_dist_asset_path($asset);

    if (file_exists($asset_path)) {
        $asset_hash = md5_file($asset_path);

        if ($asset_hash !== false) {
            return $asset_hash;
        }
    }

    return defined('PERA_PORTAL_VERSION') ? (string) PERA_PORTAL_VERSION : '1.0.0';
}

function pera_portal_enqueue_assets()
{
    $portal_page = sanitize_key((string) get_query_var('pera_portal_page'));
    $is_portal_rewrite = in_array($portal_page, ['landing', 'building'], true);

    if ($is_portal_rewrite) {
        $GLOBALS['pera_portal_is_page'] = true;
    }

    if (empty($GLOBALS['pera_portal_enqueue_assets'])) {
        if (is_admin()) {
            return;
        }

        $is_rest_request = defined('REST_REQUEST') && REST_REQUEST;
        if (wp_doing_ajax() || $is_rest_request) {
            return;
        }

        if ($is_portal_rewrite) {
            $GLOBALS['pera_portal_enqueue_compat'] = true;
        }

        $is_singular_request = is_singular();
        if (!$is_singular_request) {
            if (empty($GLOBALS['pera_portal_enqueue_compat'])) {
                return;
            }
        } else {
            if (function_exists('pera_portal_current_user_can_access') && !pera_portal_current_user_can_access()) {
                return;
            }

            $probe = pera_portal_get_shortcode_probe(true);
            if (!empty($probe['has_shortcode_matched'])) {
                $GLOBALS['pera_portal_enqueue_assets'] = true;
                $GLOBALS['pera_portal_enqueue_compat'] = true;
            }
        }
    }

    $should_enqueue_compat = !empty($GLOBALS['pera_portal_enqueue_compat']) || !empty($GLOBALS['pera_portal_enqueue_assets']);

    if (!$should_enqueue_compat && empty($GLOBALS['pera_portal_enqueue_assets'])) {
        return;
    }

    static $did_register = false;
    static $did_enqueue_compat = false;
    static $did_enqueue_viewer = false;
    static $did_localize = false;

    if (!$did_register) {
        $portal_css_asset = 'portal-viewer.css';
        $portal_compat_css_asset = 'portal-compat.css';
        $portal_js_asset = 'portal-viewer.js';
        $portal_css_version = pera_portal_get_dist_asset_version($portal_css_asset);
        $portal_compat_css_version = pera_portal_get_dist_asset_version($portal_compat_css_asset);
        $portal_js_version = pera_portal_get_dist_asset_version($portal_js_asset);

        $GLOBALS['pera_portal_asset_versions'] = [
            'css' => $portal_css_version,
            'js' => $portal_js_version,
        ];

        wp_register_style(
            'pera-portal-viewer',
            pera_portal_get_dist_asset_url($portal_css_asset),
            [],
            $portal_css_version
        );

        wp_register_style(
            'pera-portal-compat',
            pera_portal_get_dist_asset_url($portal_compat_css_asset),
            [],
            $portal_compat_css_version
        );

        wp_register_script(
            'pera-portal-viewer',
            pera_portal_get_dist_asset_url($portal_js_asset),
            [],
            $portal_js_version,
            true
        );

        $did_register = true;
    }

    if ($should_enqueue_compat && !$did_enqueue_compat) {
        wp_enqueue_style('pera-portal-compat');
        $did_enqueue_compat = true;
    }

    if (!empty($GLOBALS['pera_portal_enqueue_assets']) && !$did_enqueue_viewer) {
        wp_enqueue_style('pera-portal-viewer');
        wp_enqueue_script('pera-portal-viewer');
        $did_enqueue_viewer = true;
    }

    if (
        !$did_localize
        && !empty($GLOBALS['pera_portal_script_config'])
        && is_array($GLOBALS['pera_portal_script_config'])
    ) {
        wp_localize_script('pera-portal-viewer', 'PeraPortalConfig', $GLOBALS['pera_portal_script_config']);
        $did_localize = true;

        if (!is_admin() && current_user_can('manage_options')) {
            $GLOBALS['pera_portal_config_localized'] = true;
        }
    }

    if (!is_admin() && pera_portal_should_output_debug_marker()) {
        add_action('wp_footer', 'pera_portal_output_debug_marker', 99);
    }
}

function pera_portal_request_uri()
{
    if (!isset($_SERVER['REQUEST_URI'])) {
        return '';
    }

    return (string) wp_unslash($_SERVER['REQUEST_URI']);
}

function pera_portal_is_portal_request_plausible()
{
    $request_uri = strtolower(pera_portal_request_uri());

    if ($request_uri === '') {
        return false;
    }

    return strpos($request_uri, '/portal') !== false
        || strpos($request_uri, 'portal-test') !== false
        || strpos($request_uri, 'pera-portal') !== false;
}

function pera_portal_get_shortcode_probe($force = false)
{
    if (!$force && isset($GLOBALS['pera_portal_shortcode_probe']) && is_array($GLOBALS['pera_portal_shortcode_probe'])) {
        return $GLOBALS['pera_portal_shortcode_probe'];
    }

    $probe = [
        'executed' => false,
        'substring_matched' => false,
        'has_shortcode_executed' => false,
        'has_shortcode_matched' => null,
    ];

    if (!is_singular()) {
        $GLOBALS['pera_portal_shortcode_probe'] = $probe;
        return $probe;
    }

    $post_id = get_queried_object_id();
    $post = get_post($post_id);

    if (!($post instanceof WP_Post)) {
        $GLOBALS['pera_portal_shortcode_probe'] = $probe;
        return $probe;
    }

    $probe['executed'] = true;
    $probe['substring_matched'] = stripos($post->post_content, '[pera_portal') !== false;

    if (!$probe['substring_matched']) {
        $probe['has_shortcode_matched'] = false;
        $GLOBALS['pera_portal_shortcode_probe'] = $probe;
        return $probe;
    }

    $probe['has_shortcode_executed'] = true;
    $probe['has_shortcode_matched'] = has_shortcode($post->post_content, PERA_PORTAL_SHORTCODE_TAG);

    $GLOBALS['pera_portal_shortcode_probe'] = $probe;

    return $probe;
}

function pera_portal_should_output_debug_marker()
{
    if (defined('PERA_PORTAL_DEBUG') && PERA_PORTAL_DEBUG) {
        return true;
    }

    $debug_requested = isset($_GET['portal_debug']) && (string) wp_unslash($_GET['portal_debug']) === '1';

    if (!$debug_requested) {
        return false;
    }

    return function_exists('pera_portal_current_user_can_access')
        ? (bool) pera_portal_current_user_can_access()
        : current_user_can('manage_options');
}

function pera_portal_output_debug_marker()
{
    $script_config = isset($GLOBALS['pera_portal_script_config']) && is_array($GLOBALS['pera_portal_script_config'])
        ? $GLOBALS['pera_portal_script_config']
        : [];

    $probe = pera_portal_get_shortcode_probe(false);

    $summary = sprintf(
        'Pera Portal debug: assets_needed=%s is_singular=%s detected_shortcode=%s building_id=%d floor_id=%d mode=%s',
        !empty($GLOBALS['pera_portal_enqueue_assets']) ? '1' : '0',
        is_singular() ? '1' : '0',
        !empty($probe['has_shortcode_matched']) ? '1' : '0',
        isset($script_config['building_id']) ? absint($script_config['building_id']) : 0,
        isset($script_config['floor_id']) ? absint($script_config['floor_id']) : 0,
        isset($script_config['mode']) ? sanitize_key((string) $script_config['mode']) : 'external'
    );

    echo '<!-- ' . esc_html($summary) . " -->\n";
}

add_action('wp_enqueue_scripts', 'pera_portal_enqueue_assets');


function pera_portal_add_body_class($classes)
{
    if (!empty($GLOBALS['pera_portal_is_page'])) {
        $classes[] = 'pera-portal-page';
    }

    return $classes;
}

add_filter('body_class', 'pera_portal_add_body_class');

function pera_portal_enqueue_assets_late()
{
    if (empty($GLOBALS['pera_portal_enqueue_assets'])) {
        return;
    }

    pera_portal_enqueue_assets();
}

add_action('wp_footer', 'pera_portal_enqueue_assets_late', 1);

function pera_portal_enqueue_admin_assets($hook)
{
    unset($hook);

    $page = isset($_GET['page']) ? wp_unslash($_GET['page']) : '';

    if ($page !== 'pera-portal' && $page !== 'pera-portal-viewer') {
        return;
    }

    $portal_css_asset = 'portal-viewer.css';
    $portal_js_asset = 'portal-viewer.js';

    wp_register_style(
        'pera-portal-viewer',
        pera_portal_get_dist_asset_url($portal_css_asset),
        [],
        pera_portal_get_dist_asset_version($portal_css_asset)
    );

    wp_register_script(
        'pera-portal-viewer',
        pera_portal_get_dist_asset_url($portal_js_asset),
        [],
        pera_portal_get_dist_asset_version($portal_js_asset),
        true
    );

    wp_enqueue_style('pera-portal-viewer');
    wp_enqueue_script('pera-portal-viewer');

    if (!empty($GLOBALS['pera_portal_script_config']) && is_array($GLOBALS['pera_portal_script_config'])) {
        wp_localize_script('pera-portal-viewer', 'PeraPortalConfig', $GLOBALS['pera_portal_script_config']);
    }
}

add_action('admin_enqueue_scripts', 'pera_portal_enqueue_admin_assets');
