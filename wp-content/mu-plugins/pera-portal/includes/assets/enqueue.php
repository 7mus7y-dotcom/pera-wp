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

function pera_portal_should_output_assets_debug_comment()
{
    return isset($_GET['portal_debug'])
        && wp_unslash($_GET['portal_debug']) === '1'
        && function_exists('pera_portal_current_user_can_access')
        && pera_portal_current_user_can_access();
}

function pera_portal_enqueue_assets()
{
    if (empty($GLOBALS['pera_portal_enqueue_assets'])) {
        if (function_exists('pera_portal_current_user_can_access') && !pera_portal_current_user_can_access()) {
            return;
        }

        if (is_singular()) {
            $post_id = get_queried_object_id();
            $post = get_post($post_id);

            if ($post instanceof WP_Post && has_shortcode($post->post_content, PERA_PORTAL_SHORTCODE_TAG)) {
                $GLOBALS['pera_portal_enqueue_assets'] = true;
            }
        }
    }

    if (empty($GLOBALS['pera_portal_enqueue_assets'])) {
        return;
    }

    static $did_register = false;
    static $did_enqueue = false;
    static $did_localize = false;

    if (!$did_register) {
        $portal_css_asset = 'portal-viewer.css';
        $portal_js_asset = 'portal-viewer.js';
        $portal_css_version = pera_portal_get_dist_asset_version($portal_css_asset);
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

        wp_register_script(
            'pera-portal-viewer',
            pera_portal_get_dist_asset_url($portal_js_asset),
            [],
            $portal_js_version,
            true
        );

        $did_register = true;
    }

    if (!$did_enqueue) {
        wp_enqueue_style('pera-portal-viewer');
        wp_enqueue_script('pera-portal-viewer');
        $did_enqueue = true;
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

    if (pera_portal_should_output_assets_debug_comment()) {
        add_action('wp_footer', 'pera_portal_enqueue_debug_comment', 99);
    }
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

function pera_portal_enqueue_debug_comment()
{
    $asset_versions = isset($GLOBALS['pera_portal_asset_versions']) && is_array($GLOBALS['pera_portal_asset_versions'])
        ? $GLOBALS['pera_portal_asset_versions']
        : [];
    $js_ver = isset($asset_versions['js']) ? $asset_versions['js'] : 'unknown';
    $css_ver = isset($asset_versions['css']) ? $asset_versions['css'] : 'unknown';

    printf("<!-- pera-portal assets: js_ver=%s css_ver=%s -->\n", esc_html($js_ver), esc_html($css_ver));
}

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
