<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_enqueue_assets()
{
    static $did_enqueue = false;
    static $did_inline = false;

    if (empty($GLOBALS['so_portal_assets_needed'])) {
        return;
    }

    if (!$did_enqueue) {
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

        $did_enqueue = true;
    }

    if (!$did_inline) {
        $config = $GLOBALS['so_portal_script_config'] ?? [
            'rest_url' => esc_url_raw(rest_url(SO_PORTAL_REST_NAMESPACE . '/')),
            'nonce' => '',
            'building_id' => 0,
            'floor_id' => 0,
            'mode' => 'external',
        ];

        wp_add_inline_script(
            'so-portal-viewer',
            'window.PeraPortalConfig = ' . wp_json_encode($config) . ';',
            'before'
        );

        $did_inline = true;
    }
}

add_action('wp_enqueue_scripts', 'so_portal_enqueue_assets', 20);

function so_portal_enqueue_assets_late()
{
    if (empty($GLOBALS['so_portal_assets_needed'])) {
        return;
    }

    so_portal_enqueue_assets();
}

add_action('wp_footer', 'so_portal_enqueue_assets_late', 1);

function so_portal_mark_assets_needed()
{
    $GLOBALS['so_portal_assets_needed'] = true;
}

function so_portal_set_script_config(array $config)
{
    $GLOBALS['so_portal_script_config'] = $config;
}
