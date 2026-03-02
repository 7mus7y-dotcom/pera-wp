<?php

if (!defined('ABSPATH')) {
    exit;
}

function salesoffice_register_routes()
{
    add_rewrite_rule('^so/crm/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=crm&salesoffice_app_view=overview', 'top');
    add_rewrite_rule('^so/crm/new/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=crm&salesoffice_app_view=new', 'top');
    add_rewrite_rule('^so/crm/client/([0-9]+)/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=crm&salesoffice_app_view=client&salesoffice_client_id=$matches[1]', 'top');
    add_rewrite_rule('^so/crm/clients/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=crm&salesoffice_app_view=leads&paged=1', 'top');
    add_rewrite_rule('^so/crm/clients/page/([0-9]+)/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=crm&salesoffice_app_view=leads&paged=$matches[1]', 'top');
    add_rewrite_rule('^so/crm/leads/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=crm&salesoffice_app_view=leads&paged=1', 'top');
    add_rewrite_rule('^so/crm/leads/page/([0-9]+)/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=crm&salesoffice_app_view=leads&paged=$matches[1]', 'top');
    add_rewrite_rule('^so/crm/tasks/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=crm&salesoffice_app_view=tasks', 'top');
    add_rewrite_rule('^so/crm/pipeline/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=crm&salesoffice_app_view=pipeline', 'top');
    add_rewrite_rule('^so/portal/?$', 'index.php?salesoffice_app=1&salesoffice_app_module=portal&salesoffice_app_view=overview', 'top');
}
add_action('init', 'salesoffice_register_routes');

function salesoffice_register_query_vars($vars)
{
    $vars[] = 'salesoffice_app';
    $vars[] = 'salesoffice_app_module';
    $vars[] = 'salesoffice_app_view';
    $vars[] = 'salesoffice_client_id';

    return $vars;
}
add_filter('query_vars', 'salesoffice_register_query_vars');

function salesoffice_is_route()
{
    return '1' === (string) get_query_var('salesoffice_app');
}

function salesoffice_is_crm_route()
{
    return salesoffice_is_route() && 'crm' === sanitize_key((string) get_query_var('salesoffice_app_module'));
}

function salesoffice_is_portal_route()
{
    return salesoffice_is_route() && 'portal' === sanitize_key((string) get_query_var('salesoffice_app_module'));
}

function salesoffice_user_can_access($user_id = 0)
{
    if (function_exists('peracrm_user_can_access_crm')) {
        return (bool) peracrm_user_can_access_crm($user_id);
    }

    return user_can($user_id > 0 ? $user_id : get_current_user_id(), 'administrator');
}

function salesoffice_gate_routes()
{
    if (!salesoffice_is_route()) {
        return;
    }

    if (!is_user_logged_in()) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '/so/';
        wp_safe_redirect(wp_login_url(home_url($request_uri)));
        exit;
    }

    if (!salesoffice_user_can_access()) {
        wp_die(esc_html__('You are not allowed to access this area.', 'salesoffice-core'), 'Forbidden', ['response' => 403]);
    }
}
add_action('template_redirect', 'salesoffice_gate_routes', 1);

function salesoffice_template_include($template)
{
    if (!salesoffice_is_route()) {
        return $template;
    }

    return SALESOFFICE_CORE_PATH . '/templates/app-shell.php';
}
add_filter('template_include', 'salesoffice_template_include', 99);

function salesoffice_enqueue_shared_ui()
{
    if (!salesoffice_is_route()) {
        return;
    }

    wp_enqueue_style('salesoffice-ui', SALESOFFICE_CORE_URL . '/assets/css/salesoffice-ui.css', [], SALESOFFICE_CORE_VERSION);
}
add_action('wp_enqueue_scripts', 'salesoffice_enqueue_shared_ui', 20);
