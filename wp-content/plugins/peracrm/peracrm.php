<?php
/**
 * Plugin Name: PeraCRM
 * Description: WordPress-native CRM framework.
 * Text Domain: peracrm
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERACRM_VERSION')) {
    define('PERACRM_VERSION', '0.1.0');
}

if (!defined('PERACRM_SCHEMA_VERSION')) {
    define('PERACRM_SCHEMA_VERSION', 8);
}

if (!defined('PERACRM_MAIN_FILE')) {
    define('PERACRM_MAIN_FILE', __FILE__);
}

if (!defined('PERACRM_PATH')) {
    define('PERACRM_PATH', __DIR__);
}

if (!defined('PERACRM_INC')) {
    // Canonical core bootstrap directory remains /inc for legacy runtime compatibility.
    define('PERACRM_INC', __DIR__ . '/inc');
}

if (!defined('PERACRM_INCLUDES')) {
    // /includes contains migration-era plugin-owned wrappers (install/routing/frontend).
    define('PERACRM_INCLUDES', __DIR__ . '/includes');
}

if (!defined('PERACRM_URL')) {
    define('PERACRM_URL', plugins_url('', PERACRM_MAIN_FILE));
}

require_once PERACRM_INCLUDES . '/install.php';

add_action('plugins_loaded', static function () {
    load_plugin_textdomain('peracrm', false, dirname(plugin_basename(PERACRM_MAIN_FILE)) . '/languages');
});

register_activation_hook(PERACRM_MAIN_FILE, 'peracrm_activate');
register_deactivation_hook(PERACRM_MAIN_FILE, 'peracrm_deactivate');

require_once PERACRM_INC . '/bootstrap.php';

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
