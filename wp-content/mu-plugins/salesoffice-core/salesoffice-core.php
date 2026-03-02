<?php
/**
 * Plugin Name: Salesoffice Core (MU)
 * Description: Salesoffice app routing, gating, and shared shell.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SALESOFFICE_CORE_PATH')) {
    define('SALESOFFICE_CORE_PATH', __DIR__);
}

if (!defined('SALESOFFICE_CORE_URL')) {
    define('SALESOFFICE_CORE_URL', content_url('/mu-plugins/salesoffice-core'));
}

if (!defined('SALESOFFICE_CORE_VERSION')) {
    define('SALESOFFICE_CORE_VERSION', '0.1.0');
}

require_once SALESOFFICE_CORE_PATH . '/inc/bootstrap.php';
