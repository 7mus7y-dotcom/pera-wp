<?php
/**
 * Plugin Name: Salesoffice Portal
 * Description: Salesoffice portal module.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERA_PORTAL_PATH')) {
    define('PERA_PORTAL_PATH', __DIR__);
}

if (!defined('PERA_PORTAL_URL')) {
    define('PERA_PORTAL_URL', plugin_dir_url(__FILE__));
}

if (!defined('PERA_PORTAL_VERSION')) {
    define('PERA_PORTAL_VERSION', '0.1.0');
}

if (!defined('SALESOFFICE_PORTAL_PATH')) {
    define('SALESOFFICE_PORTAL_PATH', PERA_PORTAL_PATH);
}

if (!defined('SALESOFFICE_PORTAL_URL')) {
    define('SALESOFFICE_PORTAL_URL', PERA_PORTAL_URL);
}

if (!defined('SALESOFFICE_PORTAL_VERSION')) {
    define('SALESOFFICE_PORTAL_VERSION', PERA_PORTAL_VERSION);
}

require_once PERA_PORTAL_PATH . '/includes/bootstrap.php';
require_once PERA_PORTAL_PATH . '/includes/frontend/template-routing.php';

if (!function_exists('pera_portal_prepare_portal_route_assets')) {
    function pera_portal_prepare_portal_route_assets()
    {
        if (!function_exists('salesoffice_is_portal_route') || !salesoffice_is_portal_route()) {
            return;
        }

        $GLOBALS['pera_portal_is_page'] = true;
        $GLOBALS['pera_portal_enqueue_assets'] = true;

        if (function_exists('pera_portal_mark_assets_needed')) {
            pera_portal_mark_assets_needed();
        }

        if (function_exists('pera_portal_set_script_config') && defined('PERA_PORTAL_REST_NAMESPACE')) {
            pera_portal_set_script_config([
                'rest_url' => esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'building_id' => 0,
                'floor_id' => 0,
                'mode' => 'external',
            ]);
        }
    }
}

add_action('wp', 'pera_portal_prepare_portal_route_assets', 0);

if (!function_exists('salesoffice_portal_render_app')) {
    function salesoffice_portal_render_app($module, $view)
    {
        unset($view);

        if ('portal' !== (string) $module) {
            return;
        }

        echo "\n<!-- so-portal:handler-loaded -->\n";

        $shortcode_tag = defined('PERA_PORTAL_SHORTCODE_TAG') ? (string) PERA_PORTAL_SHORTCODE_TAG : '';
        if ('' === $shortcode_tag) {
            echo '<section class="container"><article class="card-shell"><p class="pill pill--outline">Portal shortcode tag not defined</p></article></section>';

            return;
        }

        $default_building_id = 56499;
        $default_floor_primary = 56523;
        $default_floor_fallback = 56500;

        $building_id = isset($_GET['building_id']) ? absint(wp_unslash($_GET['building_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($building_id <= 0) {
            $building_id = $default_building_id;
        }

        $building_post = get_post($building_id);
        if (!($building_post instanceof WP_Post) || 'pera_building' !== $building_post->post_type) {
            $building_id = $default_building_id;
            $building_post = get_post($building_id);
        }

        if (!($building_post instanceof WP_Post) || 'pera_building' !== $building_post->post_type) {
            echo '<section class="container"><article class="card-shell"><p class="pill pill--outline">Portal building unavailable</p></article></section>';

            return;
        }

        $floor_id = isset($_GET['floor_id']) ? absint(wp_unslash($_GET['floor_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($floor_id <= 0) {
            $floor_id = $default_floor_primary;
        }

        $floor_post = get_post($floor_id);
        if (!($floor_post instanceof WP_Post) || 'pera_floor' !== $floor_post->post_type) {
            $floor_id = $default_floor_fallback;
            $floor_post = get_post($floor_id);
        }

        if (!($floor_post instanceof WP_Post) || 'pera_floor' !== $floor_post->post_type) {
            $floor_id = 0;
        }

        echo "<!-- so-portal:building={$building_id} floor={$floor_id} -->\n";

        // Ensure portal assets flag is set (defensive).
        if (function_exists('pera_portal_mark_assets_needed')) {
            pera_portal_mark_assets_needed();
        }

        $shortcode_parts = [
            $shortcode_tag,
            'building="' . esc_attr((string) $building_id) . '"',
        ];

        if ($floor_id > 0) {
            $shortcode_parts[] = 'floor="' . esc_attr((string) $floor_id) . '"';
        }

        $shortcode_parts[] = 'mode="external"';

        $out = do_shortcode('[' . implode(' ', $shortcode_parts) . ']');
        $out = trim((string) $out);

        if ('' === $out) {
            echo '<section class="container"><article class="card-shell"><p class="pill pill--outline">Portal rendered empty</p></article></section>';

            return;
        }

        echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

// Force registration even if template routing changes.
add_action('salesoffice_render_app', 'salesoffice_portal_render_app', 10, 2);
