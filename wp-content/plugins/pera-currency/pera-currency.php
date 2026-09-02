<?php
/**
 * Plugin Name: Pera Currency
 * Description: Cache-safe USD property currency conversion infrastructure.
 * Version: 1.0.1
 * Author: Pera Property
 * Text Domain: pera-currency
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PERA_CURRENCY_VERSION', '1.0.1' );
define( 'PERA_CURRENCY_FILE', __FILE__ );
define( 'PERA_CURRENCY_DIR', plugin_dir_path( __FILE__ ) );

require_once PERA_CURRENCY_DIR . 'includes/interface-provider.php';
require_once PERA_CURRENCY_DIR . 'includes/class-ecb-provider.php';
require_once PERA_CURRENCY_DIR . 'includes/class-preference.php';
require_once PERA_CURRENCY_DIR . 'includes/class-formatter.php';
require_once PERA_CURRENCY_DIR . 'includes/class-rates.php';
require_once PERA_CURRENCY_DIR . 'includes/class-assets.php';
require_once PERA_CURRENCY_DIR . 'includes/functions.php';

Pera_Currency_Rates::bootstrap();
Pera_Currency_Assets::bootstrap();

register_activation_hook( __FILE__, array( 'Pera_Currency_Rates', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Pera_Currency_Rates', 'deactivate' ) );
