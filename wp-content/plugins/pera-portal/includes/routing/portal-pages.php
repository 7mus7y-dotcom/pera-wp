<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_register_page_rewrites()
{
    add_rewrite_rule('^portal/?$', 'index.php?pera_portal_page=landing', 'top');
    add_rewrite_rule('^portal/building/([0-9]+)/?$', 'index.php?pera_portal_page=building&pera_building_id=$matches[1]', 'top');
    add_rewrite_rule('^portal/quote/([^/]+)/?$', 'index.php?pera_portal_page=quote&pera_quote_token=$matches[1]', 'top');
}

add_action('init', 'pera_portal_register_page_rewrites');

function pera_portal_register_page_query_vars($vars)
{
    $vars[] = 'pera_portal_page';
    $vars[] = 'pera_building_id';
    $vars[] = 'pera_quote_token';

    return $vars;
}

add_filter('query_vars', 'pera_portal_register_page_query_vars');

function pera_portal_maybe_override_page_template($template)
{
    if (is_admin()) {
        return $template;
    }

    $portal_page = sanitize_key((string) get_query_var('pera_portal_page'));

    if ($portal_page === 'landing') {
        $portal_template = PERA_PORTAL_PATH . '/templates/portal-landing.php';
    } elseif ($portal_page === 'building') {
        $portal_template = PERA_PORTAL_PATH . '/templates/portal-building.php';
    } elseif ($portal_page === 'quote') {
        $portal_template = PERA_PORTAL_PATH . '/templates/portal-quote.php';
    } else {
        return $template;
    }

    if (!file_exists($portal_template)) {
        return $template;
    }

    $GLOBALS['pera_portal_is_page'] = true;

    return $portal_template;
}

add_filter('template_include', 'pera_portal_maybe_override_page_template', 98);

function pera_portal_render_rewrite_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $is_permalink_screen = false;

    if (isset($GLOBALS['hook_suffix']) && $GLOBALS['hook_suffix'] === 'options-permalink.php') {
        $is_permalink_screen = true;
    }

    if (!$is_permalink_screen && function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && isset($screen->id) && $screen->id === 'options-permalink') {
            $is_permalink_screen = true;
        }
    }

    if (!$is_permalink_screen) {
        return;
    }

    echo '<div class="notice notice-warning"><p>' . esc_html__('Permalinks may need resaving to activate /portal routes.', 'pera-portal') . '</p></div>';
}

add_action('admin_notices', 'pera_portal_render_rewrite_notice');

function pera_portal_output_quote_noindex_meta()
{
    if (sanitize_key((string) get_query_var('pera_portal_page')) !== 'quote') {
        return;
    }

    echo "\n" . '<meta name="robots" content="noindex, nofollow" />' . "\n";
}

add_action('wp_head', 'pera_portal_output_quote_noindex_meta', 1);
