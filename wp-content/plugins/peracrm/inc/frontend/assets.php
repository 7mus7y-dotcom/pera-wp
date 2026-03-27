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


if (!function_exists('peracrm_frontend_dequeue_theme_assets')) {
    function peracrm_frontend_dequeue_theme_assets(): void
    {
        if (!function_exists('pera_is_crm_route') || !pera_is_crm_route()) {
            return;
        }

        // Theme global presentation bundle is not required for plugin-owned CRM shell.
        wp_dequeue_style('pera-main-css');
        wp_dequeue_script('pera-main-js');
        wp_dequeue_script('pera-whatsapp-click-log');
    }
}
add_action('wp_enqueue_scripts', 'peracrm_frontend_dequeue_theme_assets', 41);

if (!function_exists('pera_crm_enqueue_assets')) {
    function pera_crm_enqueue_assets(): void
    {
        if (!function_exists('pera_is_crm_route') || !pera_is_crm_route()) {
            return;
        }
        
        $fonts_css = peracrm_frontend_get_asset_file('assets/frontend/fonts.css');
        $crm_css = peracrm_frontend_get_asset_file('assets/frontend/crm.css');
        $slider_css = peracrm_frontend_get_asset_file('assets/frontend/slider.css');

        $crm_css_deps = array();
        if (!empty($fonts_css)) {
            wp_enqueue_style(
                'pera-crm-fonts-css',
                $fonts_css['url'],
                array(),
                (string) filemtime($fonts_css['path'])
            );
            $crm_css_deps[] = 'pera-crm-fonts-css';
        }
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

        if (function_exists('peracrm_whatsapp_logs_is_frontend_screen') && peracrm_whatsapp_logs_is_frontend_screen()) {
            peracrm_whatsapp_enqueue_logs_assets('frontend', (string) filemtime($crm_js['path']));
        }

        if (!is_user_logged_in()) {
            return;
        }

        $crm_push_js = peracrm_frontend_get_asset_file('assets/frontend/crm-push.js');
        if (empty($crm_push_js)) {
            return;
        }

        wp_enqueue_script(
            'pera-crm-push',
            $crm_push_js['url'],
            array(),
            (string) filemtime($crm_push_js['path']),
            true
        );

        $public_key = function_exists('peracrm_push_get_public_config') ? peracrm_push_get_public_config() : array();

        wp_localize_script(
            'pera-crm-push',
            'peraCrmPush',
            array(
                'swUrl' => esc_url_raw((string)($public_key['swUrl'] ?? home_url('/peracrm-sw.js'))),
                'publicKey' => (string)($public_key['publicKey'] ?? (defined('PERACRM_VAPID_PUBLIC_KEY') ? (string) PERACRM_VAPID_PUBLIC_KEY : '')),
                'subscribeUrl' => esc_url_raw((string)($public_key['subscribeUrl'] ?? rest_url('peracrm/v1/push/subscribe'))),
                'unsubscribeUrl' => esc_url_raw((string)($public_key['unsubscribeUrl'] ?? rest_url('peracrm/v1/push/unsubscribe'))),
                'digestRunUrl' => esc_url_raw((string)($public_key['digestRunUrl'] ?? rest_url('peracrm/v1/push/digest/run'))),
                'debugUrl' => esc_url_raw((string)($public_key['debugUrl'] ?? rest_url('peracrm/v1/push/debug'))),
                'canRunDigest' => isset($public_key['canRunDigest']) ? (bool) $public_key['canRunDigest'] : (function_exists('peracrm_push_user_can_run_digest') ? (bool) peracrm_push_user_can_run_digest(get_current_user_id()) : false),
                'isConfigured' => isset($public_key['isConfigured']) ? (bool) $public_key['isConfigured'] : false,
                'missingReasons' => isset($public_key['missingReasons']) && is_array($public_key['missingReasons']) ? $public_key['missingReasons'] : array(),
                'debug' => function_exists('peracrm_push_debug_snapshot') ? peracrm_push_debug_snapshot(get_current_user_id()) : array(),
                'clickUrl' => (string)($public_key['clickUrl'] ?? '/crm/tasks/'),
                'restNonce' => wp_create_nonce('wp_rest'),
            )
        );
    }
}
add_action('wp_enqueue_scripts', 'pera_crm_enqueue_assets', 40);
