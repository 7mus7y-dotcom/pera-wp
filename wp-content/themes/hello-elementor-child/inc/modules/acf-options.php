<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_register_property_archive_seo_options_page' ) ) {
	/**
	 * Register the Property Archive SEO ACF options subpage.
	 */
	function pera_register_property_archive_seo_options_page() {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
			return;
		}

		acf_add_options_sub_page(
			array(
				'page_title'  => 'Property Archive SEO',
				'menu_title'  => 'Property Archive SEO',
				'parent_slug' => 'edit.php?post_type=property',
				'menu_slug'   => 'property-archive-seo',
				'capability'  => 'manage_options',
				'post_id'     => 'property_archive',
				'redirect'    => false,
			)
		);
	}
}

if ( false === has_action( 'acf/init', 'pera_register_property_archive_seo_options_page' ) ) {
	add_action( 'acf/init', 'pera_register_property_archive_seo_options_page' );
}
