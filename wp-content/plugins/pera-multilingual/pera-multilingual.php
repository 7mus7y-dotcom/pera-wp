<?php
/**
 * Plugin Name: Pera Multilingual
 * Description: Server-rendered, stored multilingual versions of Pera Property content.
 * Version: 0.1.1
 * Author: Pera Property
 * Text Domain: pera-multilingual
 */

defined( 'ABSPATH' ) || exit;

define( 'PERA_ML_VERSION', '0.1.1' );
define( 'PERA_ML_FILE', __FILE__ );
define( 'PERA_ML_DIR', plugin_dir_path( __FILE__ ) );
define( 'PERA_ML_URL', plugin_dir_url( __FILE__ ) );

require_once PERA_ML_DIR . 'includes/class-language-registry.php';
require_once PERA_ML_DIR . 'includes/class-storage.php';
require_once PERA_ML_DIR . 'includes/providers/interface-provider.php';
require_once PERA_ML_DIR . 'includes/providers/class-mock-provider.php';
require_once PERA_ML_DIR . 'includes/providers/class-openai-provider.php';
require_once PERA_ML_DIR . 'includes/class-translator.php';
require_once PERA_ML_DIR . 'includes/class-router.php';
require_once PERA_ML_DIR . 'includes/class-content.php';
require_once PERA_ML_DIR . 'includes/class-seo.php';
require_once PERA_ML_DIR . 'includes/class-plugin.php';
require_once PERA_ML_DIR . 'admin/class-admin.php';

register_activation_hook( __FILE__, array( 'Pera_ML_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Pera_ML_Plugin', 'deactivate' ) );

Pera_ML_Plugin::instance()->boot();

/**
 * Render or return the language switcher.
 *
 * @param array<string,mixed> $args { Optional. 'echo' defaults to true. }
 * @return string
 */
function pera_ml_language_switcher( array $args = array() ) {
	$html = Pera_ML_Plugin::instance()->content()->language_switcher( $args );
	if ( ! isset( $args['echo'] ) || $args['echo'] ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated with escaped values.
	}
	return $html;
}

/** Retrieve a stored translation without causing a provider request. */
function pera_ml_get_translation( $object_type, $object_id, $field, $language, $source = '' ) {
	return Pera_ML_Plugin::instance()->storage()->get( $object_type, (int) $object_id, $field, $language, $source );
}

/** Store a structured translation. Useful for importers and future editors. */
function pera_ml_store_translation( $object_type, $object_id, $field, $language, $source, $translation, $provider = 'manual' ) {
	return Pera_ML_Plugin::instance()->storage()->put( $object_type, (int) $object_id, $field, $language, $source, $translation, $provider );
}
