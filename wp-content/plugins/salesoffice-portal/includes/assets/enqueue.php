<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_get_script_config()
{
    $default = [
        'rest_url' => esc_url_raw(rest_url(SO_PORTAL_REST_NAMESPACE . '/')),
        'nonce' => '',
        'building_id' => 0,
        'floor_id' => 0,
        'mode' => 'external',
    ];

    if (!empty($GLOBALS['so_portal_script_config']) && is_array($GLOBALS['so_portal_script_config'])) {
        return array_merge($default, $GLOBALS['so_portal_script_config']);
    }

    return $default;
}

function so_portal_enqueue_viewer_assets()
{
    wp_enqueue_style(
        'so-portal-viewer',
        SO_PORTAL_URL . 'assets/dist/portal-viewer.css',
        [],
        SO_PORTAL_VERSION
    );

    wp_enqueue_script(
        'so-portal-viewer',
        SO_PORTAL_URL . 'assets/dist/portal-viewer.js',
        [],
        SO_PORTAL_VERSION,
        true
    );

    wp_add_inline_script(
        'so-portal-viewer',
        'window.SoPortalConfig = ' . wp_json_encode(so_portal_get_script_config()) . ';',
        'before'
    );
}

function so_portal_enqueue_assets()
{
    if (empty($GLOBALS['so_portal_assets_needed'])) {
        return;
    }

    so_portal_enqueue_viewer_assets();
}

add_action('wp_enqueue_scripts', 'so_portal_enqueue_assets', 20);

function so_portal_mark_assets_needed()
{
    $GLOBALS['so_portal_assets_needed'] = true;
}

function so_portal_set_script_config(array $config)
{
    $GLOBALS['so_portal_script_config'] = $config;
}
