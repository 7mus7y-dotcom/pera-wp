<?php
/**
 * Plugin Name: Pera Portal MU Loader
 * Description: Loads the nested Pera Portal MU-plugin entrypoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('PERA_PORTAL_PATH')) {
    return;
}

require_once __DIR__ . '/pera-portal/pera-portal.php';
