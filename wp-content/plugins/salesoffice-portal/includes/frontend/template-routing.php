<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_template_include($template)
{
    if (is_admin()) {
        return $template;
    }

    if (function_exists('salesoffice_is_route') && salesoffice_is_route()) {
        return $template;
    }

    if (!is_page('portal-test')) {
        return $template;
    }

    $portal_template = SO_PORTAL_PATH . 'templates/page-portal-test.php';

    if (!file_exists($portal_template)) {
        return $template;
    }

    so_portal_mark_assets_needed();

    return $portal_template;
}

add_filter('template_include', 'so_portal_template_include', 99);
