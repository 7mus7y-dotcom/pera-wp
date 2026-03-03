<?php
/**
 * Plugin Name: Pera Portal
 * Description: Internal staff portal scaffold.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERA_PORTAL_PATH')) {
    define('PERA_PORTAL_PATH', __DIR__);
}

if (!defined('PERA_PORTAL_URL')) {
    define('PERA_PORTAL_URL', content_url('/mu-plugins/pera-portal'));
}

if (!defined('PERA_PORTAL_VERSION')) {
    define('PERA_PORTAL_VERSION', '0.1.0');
}

require_once PERA_PORTAL_PATH . '/includes/bootstrap.php';
