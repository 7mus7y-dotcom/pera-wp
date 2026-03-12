<?php
/**
 * Plugin Name: PeraCRM MU Loader
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('PERA_CRM_DISABLED') && PERA_CRM_DISABLED) {
    return;
}

if (defined('PERACRM_VERSION')) {
    return;
}

/**
 * Standard plugin lifecycle is authoritative: MU loader must not bootstrap the
 * standard plugin by file existence. It only provides legacy MU fallback when
 * the standard plugin is not active.
 */
$active_plugins = (array) get_option('active_plugins', []);
$is_standard_plugin_active = in_array('peracrm/peracrm.php', $active_plugins, true);

if (is_multisite()) {
    $network_active = get_site_option('active_sitewide_plugins', []);
    if (is_array($network_active) && isset($network_active['peracrm/peracrm.php'])) {
        $is_standard_plugin_active = true;
    }
}

if ($is_standard_plugin_active) {
    return;
}

$peracrm_mu_entrypoint = __DIR__ . '/peracrm/peracrm.php';
if (file_exists($peracrm_mu_entrypoint)) {
    if (!defined('PERACRM_MAIN_FILE')) {
        define('PERACRM_MAIN_FILE', __FILE__);
    }

    require_once $peracrm_mu_entrypoint;
}
