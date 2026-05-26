<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_stylesheet_directory() . '/inc/modules/analytics/ahrefs.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/ga4.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/meta.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/source-classification.php';

if ( ! function_exists( 'pera_analytics_head_scripts' ) ) {
	function pera_analytics_head_scripts(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( is_feed() || is_preview() || is_trackback() || is_robots() ) {
			return;
		}

		if ( function_exists( 'pera_analytics_render_ahrefs' ) ) {
			pera_analytics_render_ahrefs();
		}

		if ( function_exists( 'pera_analytics_meta_pixel_print_head' ) ) {
			pera_analytics_meta_pixel_print_head();
		}
	}
}


if ( ! is_admin() ) {
	add_action( 'wp_head', 'pera_analytics_head_scripts', 20 );

	if ( function_exists( 'pera_analytics_meta_pixel_print_noscript' ) ) {
		add_action( 'wp_body_open', 'pera_analytics_meta_pixel_print_noscript', 1 );
		add_action( 'wp_footer', 'pera_analytics_meta_pixel_print_noscript', 1 );
	}
}

require_once get_stylesheet_directory() . '/inc/modules/analytics/install.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/bots.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/queries.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/tracker.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/dashboard-widget.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/admin-page.php';
