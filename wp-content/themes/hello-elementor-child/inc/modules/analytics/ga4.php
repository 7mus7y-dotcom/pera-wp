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
		$meta_pixel_id = defined( 'PERA_META_PIXEL_ID' ) ? trim( (string) PERA_META_PIXEL_ID ) : '';
		if ( '' === $ga4_id && '' === $meta_pixel_id ) {
			return;
		}

		$inline = "window.peraTrackingConfig = window.peraTrackingConfig || {};\n";
		if ( '' !== $ga4_id ) {
			$inline .= 'window.peraTrackingConfig.gaMeasurementId = ' . wp_json_encode( $ga4_id ) . ';';
		}
		if ( '' !== $meta_pixel_id ) {
			$inline .= 'window.peraTrackingConfig.metaPixelId = ' . wp_json_encode( $meta_pixel_id ) . ';';
		}

		wp_add_inline_script( 'pera-main-js', $inline, 'before' );
	}
}

add_action( 'wp_enqueue_scripts', 'pera_analytics_inject_tracking_config', 40 );
