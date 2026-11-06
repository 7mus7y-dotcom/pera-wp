<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PERA_AHREFS_ANALYTICS_KEY' ) ) {
	define( 'PERA_AHREFS_ANALYTICS_KEY', 'qBXJ/3s644JbBBMoxm/6ZQ' );
}

if ( ! function_exists( 'pera_analytics_inject_ahrefs_config' ) ) {
	function pera_analytics_inject_ahrefs_config(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! wp_script_is( 'pera-main-js', 'enqueued' ) ) {
			return;
		}
		$key = trim( (string) PERA_AHREFS_ANALYTICS_KEY );
		if ( '' === $key ) {
			return;
		}
		$inline = "window.peraTrackingConfig = window.peraTrackingConfig || {};\n";
		$inline .= 'window.peraTrackingConfig.ahrefsKey = ' . wp_json_encode( $key ) . ';';
		wp_add_inline_script( 'pera-main-js', $inline, 'before' );
	}
}
add_action( 'wp_enqueue_scripts', 'pera_analytics_inject_ahrefs_config', 40 );
