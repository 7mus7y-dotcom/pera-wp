<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_template_include($template)
{
    if (is_admin()) {
        return $template;
    }

    $is_salesoffice_portal = function_exists('salesoffice_is_portal_route') && salesoffice_is_portal_route();
    $is_legacy_portal_test = is_page('portal-test');

    if (!$is_salesoffice_portal && !$is_legacy_portal_test) {
        return $template;
    }

    $portal_template = PERA_PORTAL_PATH . '/templates/page-portal-test.php';

    if (!file_exists($portal_template)) {
        return $template;
    }

    $GLOBALS['pera_portal_is_page'] = true;
    $GLOBALS['pera_portal_enqueue_assets'] = true;

    return $portal_template;
}

add_filter('template_include', 'pera_portal_template_include', 99);

function salesoffice_portal_render_app($module, $view)
{
    unset($view);

    if ('portal' !== $module) {
        return;
    }

    $out = defined('PERA_PORTAL_SHORTCODE_TAG') ? do_shortcode('[' . PERA_PORTAL_SHORTCODE_TAG . ']') : '';
    $out = trim((string) $out);

    if ($out === '') {
        echo '<section class="container"><article class="card-shell"><p class="pill pill--outline">Portal rendered empty</p></article></section>';

        return;
    }

    echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('salesoffice_render_app', 'salesoffice_portal_render_app', 10, 2);
