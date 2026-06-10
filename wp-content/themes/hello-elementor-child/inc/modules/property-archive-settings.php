<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_get_property_archive_settings_page_id' ) ) {
	/**
	 * Locate the private page that stores ACF fields for the main property archive.
	 */
	function pera_get_property_archive_settings_page_id(): int {
		$page = get_page_by_path( 'property-archive-seo-settings', OBJECT, 'page' );

		if ( $page instanceof WP_Post ) {
			return (int) $page->ID;
		}

		return 0;
	}
}
