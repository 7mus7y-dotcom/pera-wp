<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_admin_posts_list_mobile_table_css' ) ) {
	/**
	 * Preserve the classic table layout on the Posts admin list screen in mobile view.
	 *
	 * This intentionally overrides WordPress mobile list-table card behavior for
	 * /wp-admin/edit.php?post_type=post so editors can horizontally scroll a real table.
	 */
	function pera_admin_posts_list_mobile_table_css( $hook_suffix ): void {
		if ( $hook_suffix !== 'edit.php' ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! is_object( $screen ) || ! isset( $screen->base, $screen->post_type ) || $screen->base !== 'edit' || $screen->post_type !== 'post' ) {
			return;
		}

		// TEMP: Disabled while diagnosing mobile blank state on wp-admin Posts list.
		// If mobile default WordPress card layout returns, the issue is confirmed in admin-posts-mobile-table.css.
		return;

		$asset_rel_path = '/assets/css/admin-posts-mobile-table.css';
		$version        = function_exists( 'pera_get_asset_version' )
			? pera_get_asset_version( $asset_rel_path )
			: wp_get_theme()->get( 'Version' );

		wp_enqueue_style(
			'pera-admin-posts-mobile-table',
			get_stylesheet_directory_uri() . $asset_rel_path,
			array(),
			$version
		);
	}
}
add_action( 'admin_enqueue_scripts', 'pera_admin_posts_list_mobile_table_css' );
