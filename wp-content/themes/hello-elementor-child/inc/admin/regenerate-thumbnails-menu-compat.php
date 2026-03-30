<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_admin_move_regenerate_thumbnails_to_media_menu' ) ) {
	/**
	 * Re-parent Regenerate Thumbnails admin page from Tools/Settings to Media.
	 */
	function pera_admin_move_regenerate_thumbnails_to_media_menu(): void {
		if ( ! function_exists( 'RegenerateThumbnails' ) ) {
			return;
		}

		$regenerate_thumbnails = RegenerateThumbnails();
		if (
			! is_object( $regenerate_thumbnails )
			|| ! method_exists( $regenerate_thumbnails, 'regenerate_interface' )
		) {
			return;
		}

		remove_submenu_page( 'tools.php', 'regenerate-thumbnails' );
		remove_submenu_page( 'options-general.php', 'regenerate-thumbnails' );

		$hook_suffix = add_submenu_page(
			'upload.php',
			_x( 'Regenerate Thumbnails', 'admin page title', 'regenerate-thumbnails' ),
			_x( 'Regenerate Thumbnails', 'admin menu entry title', 'regenerate-thumbnails' ),
			(string) $regenerate_thumbnails->capability,
			'regenerate-thumbnails',
			array( $regenerate_thumbnails, 'regenerate_interface' )
		);

		if ( is_string( $hook_suffix ) && $hook_suffix !== '' ) {
			$regenerate_thumbnails->menu_id = $hook_suffix;
			add_action( 'admin_head-' . $hook_suffix, array( $regenerate_thumbnails, 'add_admin_notice_if_resizing_not_supported' ) );
		}
	}
}
add_action( 'admin_menu', 'pera_admin_move_regenerate_thumbnails_to_media_menu', 999 );
