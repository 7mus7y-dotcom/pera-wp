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

if (!function_exists('salesoffice_portal_render_app')) {
    function salesoffice_portal_render_app($module, $view)
    {
        unset($view);

        if ('portal' !== (string) $module) {
            return;
        }

        // Marker so app-shell fallback cannot trigger if handler is registered.
        echo "\n<!-- salesoffice-portal:handler-loaded -->\n";

        $shortcode_tag = defined('PERA_PORTAL_SHORTCODE_TAG') ? (string) PERA_PORTAL_SHORTCODE_TAG : '';
        if ('' === $shortcode_tag) {
            echo '<section class="container"><article class="card-shell"><p class="pill pill--outline">Portal shortcode tag not defined</p></article></section>';

            return;
        }

        $out = do_shortcode('[' . $shortcode_tag . ']');
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
