<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_template_include($template)
{
    if (is_admin()) {
        return $template;
    }

    if (function_exists('salesoffice_is_route') && salesoffice_is_route()) {
        return $template;
    }

    $is_legacy_portal_test = is_page('portal-test');

    if (!$is_legacy_portal_test) {
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

// Render hook registration is owned by salesoffice-portal.php entrypoint.
