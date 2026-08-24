<?php
/**
 * Plugin Name: Pera Multilingual
 * Description: Server-rendered, stored multilingual versions of Pera Property content.
 * Version: 0.2.0
 * Author: Pera Property
 * Text Domain: pera-multilingual
 */

defined( 'ABSPATH' ) || exit;

define( 'PERA_ML_VERSION', '0.2.0' );
define( 'PERA_ML_FILE', __FILE__ );
define( 'PERA_ML_DIR', plugin_dir_path( __FILE__ ) );
define( 'PERA_ML_URL', plugin_dir_url( __FILE__ ) );

require_once PERA_ML_DIR . 'includes/class-language-registry.php';
require_once PERA_ML_DIR . 'includes/class-storage.php';
require_once PERA_ML_DIR . 'includes/class-translation-status.php';
require_once PERA_ML_DIR . 'includes/class-vocabulary.php';
require_once PERA_ML_DIR . 'includes/class-fields.php';
require_once PERA_ML_DIR . 'includes/class-ajax.php';
require_once PERA_ML_DIR . 'includes/providers/interface-provider.php';
require_once PERA_ML_DIR . 'includes/providers/class-mock-provider.php';
require_once PERA_ML_DIR . 'includes/providers/class-openai-provider.php';
require_once PERA_ML_DIR . 'includes/class-translator.php';
require_once PERA_ML_DIR . 'includes/class-ui-registry.php';
require_once PERA_ML_DIR . 'includes/class-ui.php';
require_once PERA_ML_DIR . 'includes/class-theme-ui-discovery.php';
require_once PERA_ML_DIR . 'includes/class-translation-health.php';
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

/** Read an approved custom-field translation; never invokes a provider. */
function pera_ml_field( $post_id, $field, $source_value, $language = null ) { return Pera_ML_Plugin::instance()->fields()->get( $post_id, $field, $source_value, $language ); }
/** Read the translated display name or description of the canonical term. */
function pera_ml_term( $term, $field = 'name', $language = null ) { return Pera_ML_Plugin::instance()->fields()->term( $term, $field, $language ); }
/** Localize a visitor-facing URL without changing external or system URLs. */
function pera_ml_url( $url, $language = null ) { $plugin = Pera_ML_Plugin::instance(); return $plugin->router()->url_for_language( $url, $language ? $language : $plugin->router()->current_language() ); }
/** Translate a controlled property label without provider traffic. */
function pera_ml_vocab( $value, $language = null ) { $plugin = Pera_ML_Plugin::instance(); return $plugin->vocabulary()->translate( $value, $language ? $language : $plugin->router()->current_language() ); }
/** Read stored visitor-facing copy, falling back to its canonical English source. */
function pera_ml_ui( $source, $key = '', $language = null ) { return Pera_ML_Plugin::instance()->ui()->get( $source, $key, $language ); }
/** Explicitly import a reviewed UI translation. This never invokes a provider. */
function pera_ml_store_ui_translation( $key, $source, $language, $translation, $provider = 'manual' ) { return Pera_ML_Plugin::instance()->ui()->store( $key, $source, $language, $translation, $provider ); }
/** Explicitly generate one UI translation; call only from administrative/offline workflows. */
function pera_ml_translate_ui( $key, $source, $language, $provider_id = '' ) { return Pera_ML_Plugin::instance()->ui()->translate_and_store( $key, $source, $language, $provider_id ); }
function pera_ml_current_language() { return Pera_ML_Plugin::instance()->router()->current_language(); }
