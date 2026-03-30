<?php
/**
 * Plugin Name: PeraCRM
 * Description: WordPress-native CRM framework.
 * Text Domain: peracrm
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('PERACRM_BOOTSTRAPPED') && PERACRM_BOOTSTRAPPED) {
    return;
}

define('PERACRM_BOOTSTRAPPED', true);

define('PERACRM_VERSION', '0.1.0');
define('PERACRM_SCHEMA_VERSION', 15);

if (!defined('PERACRM_MAIN_FILE')) {
    define('PERACRM_MAIN_FILE', __FILE__);
}

if (!defined('PERACRM_PATH')) {
    define('PERACRM_PATH', __DIR__);
}

if (!defined('PERACRM_URL')) {
    define('PERACRM_URL', untrailingslashit(plugin_dir_url(PERACRM_MAIN_FILE)));
}

if (!defined('PERACRM_INC')) {
    define('PERACRM_INC', PERACRM_PATH . '/inc');
}

add_action('plugins_loaded', function () {
    load_plugin_textdomain('peracrm', false, dirname(plugin_basename(PERACRM_MAIN_FILE)) . '/languages');
});

require_once PERACRM_INC . '/bootstrap.php';

if (function_exists('peracrm_register_lifecycle_hooks')) {
    peracrm_register_lifecycle_hooks();
}

/**
 * Integration snippet for enquiry handlers:
 *
 * $client_id = peracrm_find_or_create_client_by_email($_POST['email'], [
 *     'first_name' => $_POST['first_name'] ?? '',
 *     'last_name' => $_POST['last_name'] ?? '',
 *     'phone' => $_POST['phone'] ?? '',
 *     'source' => 'form',
 *     'status' => 'enquiry',
 * ]);
 * peracrm_log_event($client_id, 'enquiry', [
 *     'form' => 'property_enquiry',
 *     'property_id' => (int) $property_id,
 * ]);
 * peracrm_client_property_link($client_id, $property_id, 'enquiry');
 */
