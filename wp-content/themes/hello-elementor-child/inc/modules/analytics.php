<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_stylesheet_directory() . '/inc/modules/analytics/ahrefs.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/meta.php';

if ( ! function_exists( 'pera_analytics_head_scripts' ) ) {
	function pera_analytics_head_scripts(): void {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		if ( function_exists( 'pera_analytics_render_ahrefs' ) ) {
			pera_analytics_render_ahrefs();
		}

		if ( function_exists( 'pera_analytics_meta_pixel' ) ) {
			pera_analytics_meta_pixel();
		}
	}
}

if ( ! is_admin() ) {
	add_action( 'wp_head', 'pera_analytics_head_scripts', 20 );
}

require_once get_stylesheet_directory() . '/inc/modules/analytics/install.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/bots.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/queries.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/tracker.php';
require_once get_stylesheet_directory() . '/inc/modules/analytics/dashboard-widget.php';
