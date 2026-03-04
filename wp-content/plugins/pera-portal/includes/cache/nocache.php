<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pera_portal_nocache_is_diag_enabled')) {
    function pera_portal_nocache_is_diag_enabled()
    {
        return (defined('PERA_PORTAL_DIAG_HEADERS') && PERA_PORTAL_DIAG_HEADERS)
            || (defined('WP_DEBUG') && WP_DEBUG);
    }
}

if (!function_exists('pera_portal_nocache_normalize_path')) {
    function pera_portal_nocache_normalize_path($path)
    {
        $path = (string) $path;
        $path = $path === '' ? '/' : $path;
        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = untrailingslashit($path);
        }

        return $path;
    }
}

if (!function_exists('pera_portal_nocache_get_request_path')) {
    function pera_portal_nocache_get_request_path()
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';

        if ($request_uri === '') {
            return '/';
        }

        $request_path = (string) parse_url($request_uri, PHP_URL_PATH);

        return pera_portal_nocache_normalize_path($request_path);
    }
}

if (!function_exists('pera_portal_nocache_get_home_base_path')) {
    function pera_portal_nocache_get_home_base_path()
    {
        $home_path = (string) parse_url(home_url('/'), PHP_URL_PATH);

        return pera_portal_nocache_normalize_path($home_path);
    }
}

if (!function_exists('pera_portal_nocache_path_matches')) {
    function pera_portal_nocache_path_matches($request_path, $match_path)
    {
        $request_path = pera_portal_nocache_normalize_path($request_path);
        $match_path = pera_portal_nocache_normalize_path($match_path);

        return $request_path === $match_path || strpos($request_path, $match_path . '/') === 0;
    }
}

if (!function_exists('pera_portal_nocache_is_portal_path')) {
    function pera_portal_nocache_is_portal_path($request_path)
    {
        $base_path = pera_portal_nocache_get_home_base_path();
        $portal_prefix = $base_path === '/' ? '/portal' : $base_path . '/portal';

        return pera_portal_nocache_path_matches($request_path, $portal_prefix);
    }
}

if (!function_exists('pera_portal_nocache_is_excluded_path')) {
    function pera_portal_nocache_is_excluded_path($request_path)
    {
        $base_path = pera_portal_nocache_get_home_base_path();

        $prefix = static function ($path) use ($base_path) {
            $path = pera_portal_nocache_normalize_path($path);
            return $base_path === '/' ? $path : $base_path . $path;
        };

        $excluded_paths = [
            $prefix('/wp-json'),
            $prefix('/wp-admin'),
            $prefix('/wp-content/plugins/pera-portal/assets/dist'),
        ];

        foreach ($excluded_paths as $excluded_path) {
            if (pera_portal_nocache_path_matches($request_path, $excluded_path)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('pera_portal_should_apply_html_nocache')) {
    function pera_portal_should_apply_html_nocache()
    {
        if (is_admin()) {
            return false;
        }

        if (wp_doing_ajax()) {
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        $request_path = pera_portal_nocache_get_request_path();

        if (pera_portal_nocache_is_excluded_path($request_path)) {
            return false;
        }

        return pera_portal_nocache_is_portal_path($request_path);
    }
}

if (!function_exists('pera_portal_set_nocache_constants')) {
    function pera_portal_set_nocache_constants()
    {
        if (!pera_portal_should_apply_html_nocache()) {
            return;
        }

        $nocache_constants = [
            'DONOTCACHEPAGE',
            'DONOTCACHEDB',
            'DONOTCACHEOBJECT',
            'DONOTMINIFY',
        ];

        foreach ($nocache_constants as $nocache_constant) {
            if (!defined($nocache_constant)) {
                define($nocache_constant, true);
            }
        }

        $GLOBALS['pera_portal_html_nocache'] = true;
    }
}

if (!function_exists('pera_portal_send_nocache_headers')) {
    function pera_portal_send_nocache_headers()
    {
        if (!pera_portal_should_apply_html_nocache()) {
            return;
        }

        nocache_headers();

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);
        header('Vary: Cookie', false);

        if (pera_portal_nocache_is_diag_enabled()) {
            header('X-Pera-Portal-NoCache: 1', true);
        }
    }
}

if (!function_exists('pera_portal_output_nocache_diag_comment')) {
    function pera_portal_output_nocache_diag_comment()
    {
        if (!pera_portal_nocache_is_diag_enabled() || !pera_portal_should_apply_html_nocache()) {
            return;
        }

        $build_mtime = function_exists('pera_portal_get_build_version_int')
            ? (string) pera_portal_get_build_version_int()
            : 'n/a';

        $asset_ver_js = function_exists('pera_portal_get_asset_version')
            ? (string) pera_portal_get_asset_version('portal-viewer.js')
            : 'n/a';

        printf(
            "\n<!-- pera-portal:nocache=1 build_mtime=%s asset_ver_js=%s -->\n",
            esc_html($build_mtime),
            esc_html($asset_ver_js)
        );
    }
}

add_action('plugins_loaded', 'pera_portal_set_nocache_constants', 0);
add_action('send_headers', 'pera_portal_send_nocache_headers', 0);
add_action('wp_footer', 'pera_portal_output_nocache_diag_comment', 999);
