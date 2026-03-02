<?php
/**
 * Plugin Name: Salesoffice Portal
 * Description: Independent Salesoffice portal viewer (shortcode + REST + assets).
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SO_PORTAL_VERSION')) {
    define('SO_PORTAL_VERSION', '0.1.0');
}

if (!defined('SO_PORTAL_PATH')) {
    define('SO_PORTAL_PATH', plugin_dir_path(__FILE__));
}

if (!defined('SO_PORTAL_URL')) {
    define('SO_PORTAL_URL', plugin_dir_url(__FILE__));
}

require_once SO_PORTAL_PATH . 'includes/bootstrap.php';

/**
 * Optional: integrate with salesoffice-core router if present.
 * We hook defensively; this plugin must work even without salesoffice-core.
 */
add_action('salesoffice_render_app', function ($module, $view) {
    unset($view);

    if ('portal' !== (string) $module) {
        return;
    }

    echo "\n<!-- so-portal:handler-loaded -->\n";

    $default_building_id = 56499;
    $default_floor_primary = 56523;
    $default_floor_fallback = 56500;

    $building_id = isset($_GET['building_id']) ? absint(wp_unslash($_GET['building_id'])) : 0; // phpcs:ignore
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

    $floor_id = isset($_GET['floor_id']) ? absint(wp_unslash($_GET['floor_id'])) : 0; // phpcs:ignore
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

    if ($floor_id > 0) {
        $out = do_shortcode('[so_portal building="' . (int) $building_id . '" floor="' . (int) $floor_id . '" mode="external"]');
    } else {
        $out = do_shortcode('[so_portal building="' . (int) $building_id . '" mode="external"]');
    }

    $out = trim((string) $out);

    if ($out === '') {
        echo '<section class="container"><article class="card-shell"><p class="pill pill--outline">Portal rendered empty</p></article></section>';

        return;
    }

    echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}, 10, 2);
