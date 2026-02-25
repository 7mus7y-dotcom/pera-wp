<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_render_shortcode($atts = [])
{
    if (!function_exists('pera_portal_user_can_access') || !pera_portal_user_can_access()) {
        return '<p class="pera-portal-access-denied">' . esc_html__('Access denied.', 'pera-portal') . '</p>';
    }

    $atts = shortcode_atts([
        'building' => '',
    ], $atts, PERA_PORTAL_SHORTCODE_TAG);

    $building_id = absint($atts['building']);

    if (function_exists('pera_portal_mark_assets_needed')) {
        pera_portal_mark_assets_needed();
    }

    if (function_exists('pera_portal_set_script_config')) {
        pera_portal_set_script_config([
            'rest_url' => esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'building_id' => $building_id,
        ]);
    }

    ob_start();
    $template_path = PERA_PORTAL_PATH . '/templates/portal-shell.php';
    if (file_exists($template_path)) {
        include $template_path;
    }

    return (string) ob_get_clean();
}

add_shortcode(PERA_PORTAL_SHORTCODE_TAG, 'pera_portal_render_shortcode');
