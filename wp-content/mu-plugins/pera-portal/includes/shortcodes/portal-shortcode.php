<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_render_shortcode($atts = [])
{
    // Do not include theme CRM router; access check must stay pure.
    $can_access = function_exists('pera_portal_current_user_can_access')
        ? (bool) pera_portal_current_user_can_access()
        : (function_exists('pera_portal_user_can_access') && (bool) pera_portal_user_can_access());

    if (!$can_access) {
        return '<p class="pera-portal-access-denied">' . esc_html__('Access denied.', 'pera-portal') . '</p>';
    }

    $atts = shortcode_atts([
        'building' => '',
        'floor' => '',
        'wrap' => '1',
    ], $atts, PERA_PORTAL_SHORTCODE_TAG);

    $building_id = absint($atts['building']);
    $floor_id = absint($atts['floor']);
    $wrap_main = isset($atts['wrap']) ? (bool) absint((string) $atts['wrap']) : true;

    if (function_exists('pera_portal_mark_assets_needed')) {
        pera_portal_mark_assets_needed();
    }

    if (function_exists('pera_portal_set_script_config')) {
        pera_portal_set_script_config([
            'rest_url' => esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'building_id' => $building_id,
            'floor_id' => $floor_id,
        ]);
    }

    if (function_exists('pera_portal_enqueue_assets')) {
        pera_portal_enqueue_assets();
    }

    ob_start();
    $previous_wrap_state = $GLOBALS['pera_portal_wrap_main'] ?? null;
    $GLOBALS['pera_portal_wrap_main'] = $wrap_main;

    $template_path = PERA_PORTAL_PATH . '/templates/portal-shell.php';
    if (file_exists($template_path)) {
        include $template_path;
    }

    if (null === $previous_wrap_state) {
        unset($GLOBALS['pera_portal_wrap_main']);
    } else {
        $GLOBALS['pera_portal_wrap_main'] = $previous_wrap_state;
    }

    return (string) ob_get_clean();
}

add_shortcode(PERA_PORTAL_SHORTCODE_TAG, 'pera_portal_render_shortcode');
