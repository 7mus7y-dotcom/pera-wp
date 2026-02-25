<?php
/**
 * Plugin Name: Pera Portal MU Loader
 * Description: Loads the nested Pera Portal MU-plugin entrypoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/pera-portal/pera-portal.php';
