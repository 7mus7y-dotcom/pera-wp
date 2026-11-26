<?php
/**
 * Plugin Name: Pera Land Fields
 * Description: ACF editor fields for land listings.
 */

defined( 'ABSPATH' ) || exit;

function pera_land_register_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'          => 'group_pera_land_listing',
			'title'        => __( 'Land Listing Details', 'pera-land' ),
			'show_in_rest' => false,
			'location'     => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'land',
					),
				),
			),
			'fields'       => array(
				array(
					'key'   => 'field_pera_land_sale_tab',
					'label' => __( 'Sale & Plot', 'pera-land' ),
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_pera_land_quick_facts',
					'label'        => __( 'Quick facts', 'pera-land' ),
					'name'         => 'land_quick_facts',
					'type'         => 'textarea',
					'rows'         => 5,
					'instructions' => __( 'Add up to five short buyer-facing highlights, one per line.', 'pera-land' ),
				),
				array(
					'key'           => 'field_pera_land_availability',
					'label'         => __( 'Availability', 'pera-land' ),
					'name'          => 'land_availability',
					'type'          => 'select',
					'choices'       => array(
						'available'   => __( 'Available', 'pera-land' ),
						'under_offer' => __( 'Under offer', 'pera-land' ),
						'sold'        => __( 'Sold', 'pera-land' ),
					),
					'default_value' => 'available',
				),
				array(
					'key'           => 'field_pera_land_price_visibility',
					'label'         => __( 'Price visibility', 'pera-land' ),
					'name'          => 'land_price_visibility',
					'type'          => 'select',
					'choices'       => array(
						'on_request' => __( 'On request', 'pera-land' ),
						'public'     => __( 'Public price', 'pera-land' ),
					),
					'default_value' => 'on_request',
				),
				array(
					'key'          => 'field_pera_land_asking_price',
					'label'        => __( 'Asking price (USD)', 'pera-land' ),
					'name'         => 'land_asking_price',
					'type'         => 'number',
					'min'          => 0.01,
					'step'         => 0.01,
					'prepend'      => 'USD',
					'instructions' => __( 'Enter the asking price for the full offer in US dollars.', 'pera-land' ),
				),
				array(
					'key'          => 'field_pera_land_plot_area_sqm',
					'label'        => __( 'Total plot area', 'pera-land' ),
					'name'         => 'land_plot_area_sqm',
					'type'         => 'number',
					'min'          => 0.01,
					'step'         => 0.01,
					'append'       => 'm²',
					'instructions' => __( 'Use the combined area when multiple parcels are sold as one offer.', 'pera-land' ),
				),
				array(
					'key'          => 'field_pera_land_title_description',
					'label'        => __( 'Title deed description (Tapu niteliği)', 'pera-land' ),
					'name'         => 'land_title_description',
					'type'         => 'text',
					'instructions' => __( 'Record the wording from the title deed, such as arsa or tarla. Enter the documented planning use on the Planning tab.', 'pera-land' ),
				),
				array(
					'key'   => 'field_pera_land_parcel_count',
					'label' => __( 'Number of parcels in the offer', 'pera-land' ),
					'name'  => 'land_parcel_count',
					'type'  => 'number',
					'min'   => 1,
					'step'  => 1,
				),
				array(
					'key'   => 'field_pera_land_planning_tab',
					'label' => __( 'Planning', 'pera-land' ),
					'type'  => 'tab',
				),
				array(
					'key'           => 'field_pera_land_planning_status',
					'label'         => __( 'Planning verification', 'pera-land' ),
					'name'          => 'land_planning_status',
					'type'          => 'select',
					'choices'       => array(
						'pending'  => __( 'Pending verification', 'pera-land' ),
						'verified' => __( 'Verified', 'pera-land' ),
					),
					'default_value' => 'pending',
					'instructions'  => __( 'Select Verified to show the planning details entered below on the public listing.', 'pera-land' ),
				),
				array(
					'key'   => 'field_pera_land_planning_use',
					'label' => __( 'Documented planning use', 'pera-land' ),
					'name'  => 'land_planning_use',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_pera_land_emsal',
					'label' => __( 'Emsal / KAKS', 'pera-land' ),
					'name'  => 'land_emsal',
					'type'  => 'number',
					'min'   => 0,
					'step'  => 'any',
				),
				array(
					'key'          => 'field_pera_land_taks',
					'label'        => __( 'TAKS', 'pera-land' ),
					'name'         => 'land_taks',
					'type'         => 'number',
					'min'          => 0,
					'max'          => 1,
					'step'         => 'any',
					'instructions' => __( 'Enter the ratio as a decimal, for example 0.25.', 'pera-land' ),
				),
				array(
					'key'          => 'field_pera_land_height_limit',
					'label'        => __( 'Documented height limit', 'pera-land' ),
					'name'         => 'land_height_limit',
					'type'         => 'text',
					'instructions' => __( 'Enter the height limit as documented, including its unit where applicable, for example 9.50 m.', 'pera-land' ),
				),
				array(
					'key'   => 'field_pera_land_planning_notes',
					'label' => __( 'Public planning notes', 'pera-land' ),
					'name'  => 'land_planning_notes',
					'type'  => 'textarea',
					'rows'  => 3,
				),
				array(
					'key'   => 'field_pera_land_location_tab',
					'label' => __( 'Location & Access', 'pera-land' ),
					'type'  => 'tab',
				),
				array(
					'key'           => 'field_pera_land_map_visibility',
					'label'         => __( 'Map visibility', 'pera-land' ),
					'name'          => 'land_map_visibility',
					'type'          => 'select',
					'choices'       => array(
						'hidden'      => __( 'Hidden', 'pera-land' ),
						'approximate' => __( 'Approximate', 'pera-land' ),
						'exact'       => __( 'Exact', 'pera-land' ),
					),
					'default_value' => 'hidden',
				),
				array(
					'key'               => 'field_pera_land_approximate_map_pin',
					'label'             => __( 'Approximate public pin', 'pera-land' ),
					'name'              => 'land_approximate_map_pin',
					'type'              => 'google_map',
					'height'            => 300,
					'instructions'      => __( 'Place the pin at a nearby public landmark.', 'pera-land' ),
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_pera_land_map_visibility',
								'operator' => '==',
								'value'    => 'approximate',
							),
						),
					),
				),
				array(
					'key'               => 'field_pera_land_exact_map_pin',
					'label'             => __( 'Exact public pin', 'pera-land' ),
					'name'              => 'land_exact_map_pin',
					'type'              => 'google_map',
					'height'            => 300,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_pera_land_map_visibility',
								'operator' => '==',
								'value'    => 'exact',
							),
						),
					),
				),
				array(
					'key'           => 'field_pera_land_road_access',
					'label'         => __( 'Road access', 'pera-land' ),
					'name'          => 'land_road_access',
					'type'          => 'select',
					'choices'       => array(
						'unknown' => __( 'Unknown', 'pera-land' ),
						'yes'     => __( 'Yes', 'pera-land' ),
						'no'      => __( 'No', 'pera-land' ),
					),
					'default_value' => 'unknown',
				),
				array(
					'key'    => 'field_pera_land_frontage_m',
					'label'  => __( 'Road frontage', 'pera-land' ),
					'name'   => 'land_frontage_m',
					'type'   => 'number',
					'min'    => 0.01,
					'step'   => 0.01,
					'append' => 'm',
				),
				array(
					'key'   => 'field_pera_land_slope',
					'label' => __( 'Slope', 'pera-land' ),
					'name'  => 'land_slope',
					'type'  => 'text',
				),
				array(
					'key'          => 'field_pera_land_utilities',
					'label'        => __( 'Utilities', 'pera-land' ),
					'name'         => 'land_utilities',
					'type'         => 'textarea',
					'rows'         => 3,
					'instructions' => __( 'Describe known connections or nearby services. Leave blank when unknown.', 'pera-land' ),
				),
				array(
					'key'   => 'field_pera_land_media_tab',
					'label' => __( 'Media', 'pera-land' ),
					'type'  => 'tab',
				),
				array(
					'key'           => 'field_pera_land_gallery',
					'label'         => __( 'Plot gallery', 'pera-land' ),
					'name'          => 'land_gallery',
					'type'          => 'gallery',
					'return_format' => 'id',
					'instructions'  => __( 'Use the WordPress featured image for the archive card.', 'pera-land' ),
				),
				array(
					'key'           => 'field_pera_land_drone_video',
					'label'         => __( 'Drone video', 'pera-land' ),
					'name'          => 'land_drone_video',
					'type'          => 'file',
					'return_format' => 'id',
					'mime_types'    => 'mp4,webm',
					'instructions'  => __( 'The video will appear with the plot images in the gallery.', 'pera-land' ),
				),
				array(
					'key'           => 'field_pera_land_public_plot_plan',
					'label'         => __( 'Public plot plan', 'pera-land' ),
					'name'          => 'land_public_plot_plan',
					'type'          => 'file',
					'return_format' => 'id',
					'mime_types'    => 'pdf,jpg,jpeg,png,webp',
					'instructions'  => __( 'Use a file approved for public sharing. Use Internal Records for references to private files.', 'pera-land' ),
				),
				array(
					'key'   => 'field_pera_land_internal_tab',
					'label' => __( 'Internal Records', 'pera-land' ),
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_pera_land_parcels',
					'label'        => __( 'Parcel references', 'pera-land' ),
					'name'         => 'land_parcels',
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => __( 'Add parcel', 'pera-land' ),
					'sub_fields'   => array(
						array(
							'key'   => 'field_pera_land_parcel_ada',
							'label' => __( 'Ada', 'pera-land' ),
							'name'  => 'ada',
							'type'  => 'text',
						),
						array(
							'key'   => 'field_pera_land_parcel_parsel',
							'label' => __( 'Parsel', 'pera-land' ),
							'name'  => 'parsel',
							'type'  => 'text',
						),
					),
				),
				array(
					'key'   => 'field_pera_land_planning_source',
					'label' => __( 'Planning source or document reference', 'pera-land' ),
					'name'  => 'land_planning_source',
					'type'  => 'text',
				),
				array(
					'key'            => 'field_pera_land_planning_checked_on',
					'label'          => __( 'Planning date checked', 'pera-land' ),
					'name'           => 'land_planning_checked_on',
					'type'           => 'date_picker',
					'display_format' => 'd/m/Y',
					'return_format'  => 'Y-m-d',
				),
				array(
					'key'          => 'field_pera_land_private_document_references',
					'label'        => __( 'Private document references', 'pera-land' ),
					'name'         => 'land_private_document_references',
					'type'         => 'textarea',
					'rows'         => 3,
					'instructions' => __( 'Record document references from access-controlled storage. Use the Media tab only for public files.', 'pera-land' ),
				),
				array(
					'key'   => 'field_pera_land_internal_notes',
					'label' => __( 'Internal notes', 'pera-land' ),
					'name'  => 'land_internal_notes',
					'type'  => 'textarea',
					'rows'  => 4,
				),
			),
		)
	);
}
add_action( 'acf/init', 'pera_land_register_fields' );
