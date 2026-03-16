<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('peracrm_frontend_get_asset_file')) {
    function peracrm_frontend_get_asset_file(string $relative_plugin_path): array
    {
        $plugin_file = trailingslashit(PERACRM_PATH) . ltrim($relative_plugin_path, '/');
        if (file_exists($plugin_file)) {
            return [
                'path' => $plugin_file,
                'url' => trailingslashit(PERACRM_URL) . ltrim($relative_plugin_path, '/'),
            ];
        }

        return [];
    }
}

if (!function_exists('pera_crm_enqueue_assets')) {
    function pera_crm_enqueue_assets(): void
    {
        if (!function_exists('pera_is_crm_route') || !pera_is_crm_route()) {
            return;
        }

        $crm_css = peracrm_frontend_get_asset_file('assets/frontend/crm.css');
        $slider_css = peracrm_frontend_get_asset_file('assets/frontend/slider.css');

        $crm_css_deps = array();
        if (!empty($slider_css)) {
            wp_enqueue_style(
                'pera-slider-css',
                $slider_css['url'],
                array(),
                (string) filemtime($slider_css['path'])
            );
            $crm_css_deps[] = 'pera-slider-css';
        }

        if (!empty($crm_css)) {
            wp_enqueue_style(
                'pera-crm-css',
                $crm_css['url'],
                $crm_css_deps,
                (string) filemtime($crm_css['path'])
            );
        }

        $crm_js = peracrm_frontend_get_asset_file('assets/frontend/crm.js');
        if (empty($crm_js)) {
            return;
        }

        wp_enqueue_script(
            'pera-crm-js',
            $crm_js['url'],
            [],
            (string) filemtime($crm_js['path']),
            true
        );

        wp_localize_script(
            'pera-crm-js',
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
}
add_action('wp_enqueue_scripts', 'pera_crm_enqueue_assets', 40);
