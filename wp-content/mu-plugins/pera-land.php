<?php
/**
 * Plugin Name: Pera Land
 * Description: Registers land listings and their public permalink structure.
 */

defined( 'ABSPATH' ) || exit;

function pera_land_register_post_type(): void {
	register_post_type(
		'land',
		array(
			'labels' => array(
				'name'          => __( 'Land Listings', 'pera-land' ),
				'singular_name' => __( 'Land Listing', 'pera-land' ),
				'menu_name'     => __( 'Land', 'pera-land' ),
				'add_new_item'  => __( 'Add Land Listing', 'pera-land' ),
				'edit_item'     => __( 'Edit Land Listing', 'pera-land' ),
				'new_item'      => __( 'New Land Listing', 'pera-land' ),
				'view_item'     => __( 'View Land Listing', 'pera-land' ),
				'view_items'    => __( 'View Land Listings', 'pera-land' ),
				'all_items'     => __( 'All Land Listings', 'pera-land' ),
				'search_items'  => __( 'Search Land Listings', 'pera-land' ),
				'not_found'     => __( 'No land listings found.', 'pera-land' ),
				'archives'      => __( 'Land for Sale in Istanbul', 'pera-land' ),
			),
			'public'              => true,
			'exclude_from_search' => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-location-alt',
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'taxonomies'          => array( 'district' ),
			'has_archive'         => 'land-for-sale-in-istanbul',
			'rewrite'             => array(
				'slug'       => 'land',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'pera_land_register_post_type' );

// The existing district taxonomy may be registered after the post type.
add_action( 'registered_taxonomy_district', 'pera_land_attach_district' );

function pera_land_attach_district(): void {
	register_taxonomy_for_object_type( 'district', 'land' );
}
