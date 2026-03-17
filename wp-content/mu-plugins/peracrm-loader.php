<?php
/**
 * Plugin Name: PeraCRM MU Loader
 * Description: Loads the nested PeraCRM MU-plugin entrypoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

$peracrm_regular_plugin_entrypoint = WP_PLUGIN_DIR . '/peracrm/peracrm.php';
if (file_exists($peracrm_regular_plugin_entrypoint)) {
    return;
}

require_once __DIR__ . '/peracrm/peracrm.php';
