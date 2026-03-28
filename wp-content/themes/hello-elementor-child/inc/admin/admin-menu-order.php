<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_admin_enable_custom_menu_order' ) ) {
	/**
	 * Child theme owns site-wide wp-admin menu presentation preferences.
	 */
	function pera_admin_enable_custom_menu_order( $enabled ): bool {
		return current_user_can( 'manage_options' );
	}
}
add_filter( 'custom_menu_order', 'pera_admin_enable_custom_menu_order' );

if ( ! function_exists( 'pera_admin_reorder_menu_items' ) ) {
	/**
	 * Promote key top-level items, then append everything else as-is.
	 *
	 * Targeted slugs:
	 * - Dashboard: index.php
	 * - My properties: edit.php?post_type=property
	 * - Pera portal: pera-portal
	 * - CRM clients: edit.php?post_type=crm_client
	 * - Posts: edit.php
	 * - WhatsApp logs: pera-whatsapp-logs
	 * - Emails: pera-enquiry-email-log
	 *
	 * Missing items are skipped gracefully to preserve role-based visibility.
	 *
	 * @param array<int,string> $menu_order Ordered top-level slugs from WP.
	 * @return array<int,string>
	 */
	function pera_admin_reorder_menu_items( array $menu_order ): array {
		if ( empty( $menu_order ) || ! current_user_can( 'manage_options' ) ) {
			return $menu_order;
		}

		$priority = array(
			'index.php',
			'edit.php?post_type=property',
			'pera-portal',
			'edit.php?post_type=crm_client',
			'edit.php',
			'pera-whatsapp-logs',
			'pera-enquiry-email-log',
		);

		$existing   = array_values( array_unique( $menu_order ) );
		$reordered  = array();
		$existing_lut = array_flip( $existing );

		foreach ( $priority as $slug ) {
			if ( isset( $existing_lut[ $slug ] ) ) {
				$reordered[] = $slug;
			}
		}

		foreach ( $existing as $slug ) {
			if ( ! in_array( $slug, $reordered, true ) ) {
				$reordered[] = $slug;
			}
		}

		return $reordered;
	}
}
add_filter( 'menu_order', 'pera_admin_reorder_menu_items', 20 );
