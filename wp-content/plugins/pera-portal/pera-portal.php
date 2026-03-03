<?php
/**
 * Plugin Name: Pera Portal
 * Description: Internal staff portal scaffold.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERA_PORTAL_VERSION')) {
    define('PERA_PORTAL_VERSION', '1.0.0');
}

if (!defined('PERA_PORTAL_PATH')) {
    define('PERA_PORTAL_PATH', plugin_dir_path(__FILE__));
}

if (!defined('PERA_PORTAL_URL')) {
    define('PERA_PORTAL_URL', plugin_dir_url(__FILE__));
}

require_once PERA_PORTAL_PATH . '/includes/bootstrap.php';
