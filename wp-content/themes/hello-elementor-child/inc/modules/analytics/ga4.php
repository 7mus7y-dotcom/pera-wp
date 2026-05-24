<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PERA_GA4_MEASUREMENT_ID' ) ) {
	define( 'PERA_GA4_MEASUREMENT_ID', 'G-GS59R7GGV1' );
}

if ( ! function_exists( 'pera_analytics_inject_tracking_config' ) ) {
	function pera_analytics_inject_tracking_config(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( ! wp_script_is( 'pera-main-js', 'enqueued' ) ) {
			return;
		}

		$ga4_id = trim( (string) PERA_GA4_MEASUREMENT_ID );
		if ( '' === $ga4_id ) {
			return;
		}

		$inline = "window.peraTrackingConfig = window.peraTrackingConfig || {};\n";
		$inline .= 'window.peraTrackingConfig.gaMeasurementId = ' . wp_json_encode( $ga4_id ) . ';';

		wp_add_inline_script( 'pera-main-js', $inline, 'before' );
	}
}

add_action( 'wp_enqueue_scripts', 'pera_analytics_inject_tracking_config', 40 );
