<?php
/**
 * Plugin Name: Pera Portal
 * Description: Internal staff portal scaffold.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PERA_PORTAL_VERSION', '1.0.0');
define('PERA_PORTAL_PATH', plugin_dir_path(__FILE__));
define('PERA_PORTAL_URL', plugin_dir_url(__FILE__));

require_once PERA_PORTAL_PATH . 'includes/bootstrap.php';

register_activation_hook(__FILE__, function (): void {
    if (function_exists('pera_portal_register_page_rewrites')) {
        pera_portal_register_page_rewrites();
    }

    flush_rewrite_rules();
});
