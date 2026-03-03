<?php
/**
 * Plugin Name: Pera Portal MU Loader
 * Description: Compatibility shim. Loads MU implementation only when standard plugin is not active.
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Ensure is_plugin_active() is available.
 */
if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

/*
 * If the standard plugin is active, do not load MU version.
 */
if (function_exists('is_plugin_active') && is_plugin_active('pera-portal/pera-portal.php')) {
    return;
}

/*
 * Fallback to legacy MU implementation.
 */
require_once __DIR__ . '/pera-portal/pera-portal.php';
