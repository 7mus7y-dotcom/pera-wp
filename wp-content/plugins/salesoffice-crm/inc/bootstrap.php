<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once SALESOFFICE_CRM_PATH . '/inc/crm-data.php';
require_once SALESOFFICE_CRM_PATH . '/inc/crm-client-view.php';

if (!function_exists('pera_is_crm_route')) {
    function pera_is_crm_route()
    {
        return function_exists('salesoffice_is_crm_route') && salesoffice_is_crm_route();
    }
}

function salesoffice_crm_partial($name, array $args = [])
{
    salesoffice_crm_template('partials/' . sanitize_file_name((string) $name), $args);
}

function salesoffice_crm_template($name, array $args = [])
{
    $template_path = SALESOFFICE_CRM_PATH . '/' . ltrim((string) $name, '/');

    if ('.php' !== substr($template_path, -4)) {
        $template_path .= '.php';
    }

    if (!file_exists($template_path)) {
        return;
    }

    $template_args = $args;
    unset($args);
    extract($template_args, EXTR_SKIP);
    include $template_path;
}

function salesoffice_crm_render_app($module, $view)
{
    if ('crm' !== $module) {
        return;
    }

    $view_map = [
        'overview' => 'templates/crm-overview',
        'leads' => 'templates/crm-overview',
        'tasks' => 'templates/crm-overview',
        'new' => 'templates/crm-new',
        'client' => 'templates/crm-client',
        'pipeline' => 'templates/crm-pipeline',
    ];

    $selected_view = sanitize_key((string) $view);
    if (!isset($view_map[$selected_view])) {
        $selected_view = 'overview';
    }

    salesoffice_crm_template($view_map[$selected_view]);
}
add_action('salesoffice_render_app', 'salesoffice_crm_render_app', 10, 2);

function salesoffice_crm_enqueue_assets()
{
    if (!function_exists('salesoffice_is_crm_route') || !salesoffice_is_crm_route()) {
        return;
    }

    wp_enqueue_style('salesoffice-crm', SALESOFFICE_CRM_URL . 'assets/css/crm.css', ['salesoffice-ui'], SALESOFFICE_CRM_VERSION);

    wp_enqueue_script('salesoffice-crm-js', SALESOFFICE_CRM_URL . 'assets/js/crm.js', [], SALESOFFICE_CRM_VERSION, true);

    if (file_exists(SALESOFFICE_CRM_PATH . '/assets/js/crm-push.js')) {
        wp_enqueue_script('salesoffice-crm-push-js', SALESOFFICE_CRM_URL . 'assets/js/crm-push.js', [], SALESOFFICE_CRM_VERSION, true);
    }

    wp_localize_script(
        'salesoffice-crm-js',
        'peraCrmData',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'propertySearchNonce' => wp_create_nonce('pera_crm_property_search'),
            'createPortfolioNonce' => wp_create_nonce('pera_crm_create_portfolio_token'),
            'updatePortfolioNonce' => wp_create_nonce('pera_crm_update_portfolio_token'),
            'portfolioFieldsNonce' => wp_create_nonce('pera_crm_save_portfolio_property_fields'),
            'portfolioFloorPlanNonce' => wp_create_nonce('pera_crm_upload_portfolio_floor_plan'),
        ]
    );
}
add_action('wp_enqueue_scripts', 'salesoffice_crm_enqueue_assets', 40);
