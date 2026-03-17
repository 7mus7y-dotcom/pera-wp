<?php
/**
 * Plugin Name: PeraCRM MU Loader
 */

if (!defined('ABSPATH')) {
    exit;
}

if ( defined('PERA_CRM_DISABLED') && PERA_CRM_DISABLED ) {
    return;
}


$peracrm_regular_plugin_entrypoint = WP_PLUGIN_DIR . '/peracrm/peracrm.php';
if (file_exists($peracrm_regular_plugin_entrypoint)) {
    return;
}
if (!defined('PERACRM_MAIN_FILE')) {
    define('PERACRM_MAIN_FILE', __FILE__);
}

$peracrm_entrypoint = __DIR__ . '/peracrm/peracrm.php';
if (file_exists($peracrm_entrypoint)) {
    require_once $peracrm_entrypoint;
}
