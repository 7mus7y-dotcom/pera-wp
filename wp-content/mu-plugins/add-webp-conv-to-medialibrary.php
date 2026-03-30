<?php
/**
 * MU Plugin shim: load Pera WebP Tools from the normal plugin layer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pera_webp_plugin = WP_CONTENT_DIR . '/plugins/pera-webp-tools/pera-webp-tools.php';

if ( file_exists( $pera_webp_plugin ) ) {
	require_once $pera_webp_plugin;
}
