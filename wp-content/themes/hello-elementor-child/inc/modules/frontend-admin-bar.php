<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'show_admin_bar', 'pera_disable_frontend_admin_bar', PHP_INT_MAX );

/**
 * Frontend-only admin bar disablement; wp-admin behavior is intentionally preserved.
 */
function pera_disable_frontend_admin_bar( bool $show ): bool {
	if ( is_admin() ) {
		return $show;
	}

	return false;
}

add_action( 'wp_enqueue_scripts', 'pera_dequeue_frontend_admin_bar_assets', 100 );

/**
 * Remove admin bar assets from frontend requests only.
 */
function pera_dequeue_frontend_admin_bar_assets(): void {
	if ( is_admin() ) {
		return;
	}

	wp_dequeue_style( 'admin-bar' );
	wp_deregister_style( 'admin-bar' );

	wp_dequeue_script( 'admin-bar' );
	wp_deregister_script( 'admin-bar' );
}
