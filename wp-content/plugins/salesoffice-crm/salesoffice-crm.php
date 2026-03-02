<?php
/**
 * Plugin Name: Salesoffice CRM
 * Description: CRM module for Salesoffice app shell.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SALESOFFICE_CRM_PATH')) {
    define('SALESOFFICE_CRM_PATH', __DIR__);
}

if (!defined('SALESOFFICE_CRM_URL')) {
    define('SALESOFFICE_CRM_URL', plugin_dir_url(__FILE__));
}

if (!defined('SALESOFFICE_CRM_VERSION')) {
    define('SALESOFFICE_CRM_VERSION', '0.1.0');
}

require_once SALESOFFICE_CRM_PATH . '/inc/bootstrap.php';
