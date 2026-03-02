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

require_once PERA_PORTAL_PATH . '/includes/frontend/template-routing.php';
require_once PERA_PORTAL_PATH . '/includes/bootstrap.php';
