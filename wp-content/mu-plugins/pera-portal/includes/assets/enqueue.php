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

function pera_portal_enqueue_assets()
{
    if (empty($GLOBALS['pera_portal_enqueue_assets'])) {
        return;
    }

    wp_register_style(
        'pera-portal-viewer',
        PERA_PORTAL_URL . '/assets/dist/portal-viewer.css',
        [],
        PERA_PORTAL_VERSION
    );

    wp_register_script(
        'pera-portal-viewer',
        PERA_PORTAL_URL . '/assets/dist/portal-viewer.js',
        [],
        PERA_PORTAL_VERSION,
        true
    );

    wp_enqueue_style('pera-portal-viewer');
    wp_enqueue_script('pera-portal-viewer');

    if (!empty($GLOBALS['pera_portal_script_config']) && is_array($GLOBALS['pera_portal_script_config'])) {
        wp_localize_script('pera-portal-viewer', 'PeraPortalConfig', $GLOBALS['pera_portal_script_config']);
    }
}

add_action('wp_enqueue_scripts', 'pera_portal_enqueue_assets');

function pera_portal_enqueue_admin_assets($hook)
{
    unset($hook);

    if (empty($_GET['page']) || wp_unslash($_GET['page']) !== 'pera-portal') {
        return;
    }

    wp_register_style(
        'pera-portal-viewer',
        PERA_PORTAL_URL . '/assets/dist/portal-viewer.css',
        [],
        PERA_PORTAL_VERSION
    );

    wp_register_script(
        'pera-portal-viewer',
        PERA_PORTAL_URL . '/assets/dist/portal-viewer.js',
        [],
        PERA_PORTAL_VERSION,
        true
    );

    wp_enqueue_style('pera-portal-viewer');
    wp_enqueue_script('pera-portal-viewer');

    if (!empty($GLOBALS['pera_portal_script_config']) && is_array($GLOBALS['pera_portal_script_config'])) {
        wp_localize_script('pera-portal-viewer', 'PeraPortalConfig', $GLOBALS['pera_portal_script_config']);
    }
}

add_action('admin_enqueue_scripts', 'pera_portal_enqueue_admin_assets');
