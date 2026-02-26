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

    if (!is_admin() && current_user_can('manage_options')) {
        add_action('wp_footer', 'pera_portal_enqueue_debug_comment', 99);
        add_action('wp_footer', 'pera_portal_localize_debug_comment', 99);
    }
}

add_action('wp_enqueue_scripts', 'pera_portal_enqueue_assets');

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
    echo "<!-- Pera Portal assets enqueued -->\n";
}

function pera_portal_localize_debug_comment()
{
    if (!empty($GLOBALS['pera_portal_config_localized'])) {
        echo "<!-- Pera Portal config localized -->\n";
    }
}

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
