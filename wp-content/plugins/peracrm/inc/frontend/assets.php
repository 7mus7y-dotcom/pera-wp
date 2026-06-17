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


if (!function_exists('peracrm_frontend_get_asset_url')) {
    function peracrm_frontend_get_asset_url(string $relative_plugin_path): string
    {
        if (!defined('PERACRM_URL') || '' === (string) PERACRM_URL) {
            return '';
        }

        return trailingslashit(PERACRM_URL) . ltrim($relative_plugin_path, '/');
    }
}

if (!function_exists('peracrm_frontend_enqueue_style_asset')) {
    function peracrm_frontend_enqueue_style_asset(string $handle, string $relative_plugin_path, array $deps = array()): bool
    {
        $asset = peracrm_frontend_get_asset_file($relative_plugin_path);
        if (!empty($asset)) {
            wp_enqueue_style(
                $handle,
                $asset['url'],
                $deps,
                (string) filemtime($asset['path'])
            );

            return true;
        }

        $asset_url = peracrm_frontend_get_asset_url($relative_plugin_path);
        if ('' === $asset_url) {
            return false;
        }

        wp_enqueue_style(
            $handle,
            $asset_url,
            $deps,
            defined('PERACRM_VERSION') ? (string) PERACRM_VERSION : null
        );

        return true;
    }
}

if (!function_exists('peracrm_frontend_is_crm_request_path')) {
    function peracrm_frontend_is_crm_request_path(): bool
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ('' === $request_uri) {
            return false;
        }

        $path = wp_parse_url($request_uri, PHP_URL_PATH);
        if (!is_string($path) || '' === $path) {
            return false;
        }

        $path = strtolower($path);

        return '/crm' === $path || 0 === strpos(trailingslashit($path), '/crm/');
    }
}

if (!function_exists('peracrm_is_portfolio_token_route')) {
    function peracrm_is_portfolio_token_route(): bool
    {
        if (function_exists('pera_portfolio_token_is_request') && pera_portfolio_token_is_request()) {
            return true;
        }

        return '' !== (string) get_query_var('portfolio_token', '');
    }
}

if (!function_exists('peracrm_frontend_dequeue_theme_assets')) {
    function peracrm_frontend_dequeue_theme_assets(): void
    {
        $is_crm_route = function_exists('pera_is_crm_route') && pera_is_crm_route();
        $is_crm_request_path = peracrm_frontend_is_crm_request_path();

        if (!$is_crm_route && !$is_crm_request_path) {
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
        $is_crm_route = function_exists('pera_is_crm_route') && pera_is_crm_route();
        $is_crm_request_path = peracrm_frontend_is_crm_request_path();
        $is_crm_request = $is_crm_route || $is_crm_request_path;
        $is_portfolio_token_route = function_exists('peracrm_is_portfolio_token_route') && peracrm_is_portfolio_token_route();

        if (!$is_crm_request && !$is_portfolio_token_route) {
            return;
        }

        $crm_css_deps = array();
        if (peracrm_frontend_enqueue_style_asset('peracrm-frontend-fonts', 'assets/frontend/fonts.css')) {
            $crm_css_deps[] = 'peracrm-frontend-fonts';
        }
        if (peracrm_frontend_enqueue_style_asset('peracrm-frontend-slider', 'assets/frontend/slider.css')) {
            $crm_css_deps[] = 'peracrm-frontend-slider';
        }

        peracrm_frontend_enqueue_style_asset('peracrm-frontend-css', 'assets/frontend/crm.css', $crm_css_deps);

        if (!$is_crm_request) {
            return;
        }

        $crm_js = peracrm_frontend_get_asset_file('assets/frontend/crm.js');
        if (empty($crm_js)) {
            return;
        }


        $phone_picker_js = peracrm_frontend_get_asset_file('assets/frontend/phone-country-picker.js');
        if (!empty($phone_picker_js)) {
            wp_enqueue_script(
                'pera-phone-country-picker',
                $phone_picker_js['url'],
                [],
                (string) filemtime($phone_picker_js['path']),
                true
            );

            wp_localize_script(
                'pera-phone-country-picker',
                'peraPhoneCountries',
                [
                    'countries' => function_exists('peracrm_phone_dial_code_options') ? peracrm_phone_dial_code_options() : [],
                ]
            );
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
                'headerSearchNonce' => wp_create_nonce('peracrm_header_search'),
                'createPortfolioNonce' => wp_create_nonce('pera_crm_create_portfolio_token'),
                'updatePortfolioNonce' => wp_create_nonce('pera_crm_update_portfolio_token'),
                'portfolioFieldsNonce' => wp_create_nonce('pera_crm_save_portfolio_property_fields'),
                'portfolioFloorPlanNonce' => wp_create_nonce('pera_crm_upload_portfolio_floor_plan'),
                'themePortfolioAddNonce' => wp_create_nonce('pera_crm_theme_portfolio_add_property'),
                'themePortfolioRemoveNonce' => wp_create_nonce('pera_crm_theme_portfolio_remove_property'),
                'themePortfolioRefreshNonce' => wp_create_nonce('pera_crm_refresh_theme_portfolio_url'),
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
