<?php
/**
 * Data shared by the Land archive and single listing.
 */

defined( 'ABSPATH' ) || exit;

function pera_land_field( int $post_id, string $name ) {
	return function_exists( 'get_field' ) ? get_field( $name, $post_id ) : get_post_meta( $post_id, $name, true );
}

function pera_land_text_field( int $post_id, string $name ): string {
	$value = pera_land_field( $post_id, $name );
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

function pera_land_currencies(): array {
	return array( 'TRY', 'USD', 'EUR', 'GBP', 'CNY' );
}

function pera_land_format_number( float $number ): string {
	return number_format_i18n( $number, floor( $number ) === $number ? 0 : 2 );
}

function pera_land_public_price( int $post_id ): array {
	if ( 'public' !== pera_land_text_field( $post_id, 'land_price_visibility' ) ) {
		return array();
	}

	$amount   = pera_land_text_field( $post_id, 'land_asking_price' );
	$currency = pera_land_text_field( $post_id, 'land_asking_currency' );
	if ( ! is_numeric( $amount ) || (float) $amount <= 0 || ! in_array( $currency, pera_land_currencies(), true ) ) {
		return array();
	}

	return array( 'amount' => (float) $amount, 'currency' => $currency );
}

function pera_land_planning_is_verified( int $post_id ): bool {
	return 'verified' === pera_land_text_field( $post_id, 'land_planning_status' )
		&& '' !== pera_land_text_field( $post_id, 'land_planning_source' )
		&& '' !== pera_land_text_field( $post_id, 'land_planning_checked_on' );
}

function pera_land_archive_settings_id(): int {
	static $id;
	if ( null === $id ) {
		$page = get_page_by_path( 'land-archive-seo-settings', OBJECT, 'page' );
		$id = $page instanceof WP_Post && 'private' === $page->post_status ? (int) $page->ID : 0;
	}
	return $id;
}

function pera_land_archive_description(): string {
	return pera_ml_ui(
		'Browse land for sale in Istanbul and compare plots by district, size and asking price. See each listing for its planning status and site details.',
		'theme.template.archive_land.description'
	);
}

function pera_land_archive_filters( array $input ): array {
	$filters = array();
	$district = $input['land_district'] ?? '';
	if ( is_string( $district ) && '' !== $district ) {
		$district = sanitize_title( wp_unslash( $district ) );
		if ( '' !== $district ) {
			$filters['land_district'] = $district;
		}
	}

	foreach ( array( 'land_min_area', 'land_max_area', 'land_max_price' ) as $key ) {
		$value = $input[ $key ] ?? '';
		if ( is_string( $value ) && preg_match( '/^\d{1,14}(?:\.\d{1,2})?$/D', $value ) && (float) $value > 0 ) {
			$filters[ $key ] = $value;
		}
	}

	if ( isset( $filters['land_min_area'], $filters['land_max_area'] ) && (float) $filters['land_min_area'] > (float) $filters['land_max_area'] ) {
		list( $filters['land_min_area'], $filters['land_max_area'] ) = array( $filters['land_max_area'], $filters['land_min_area'] );
	}

	if ( isset( $filters['land_max_price'] ) ) {
		$currency = $input['land_currency'] ?? '';
		$filters['land_currency'] = in_array( $currency, pera_land_currencies(), true ) ? $currency : 'TRY';
	}

	return $filters;
}

function pera_land_archive_available_clause(): array {
	return array(
		'relation' => 'OR',
		array( 'key' => 'land_availability', 'compare' => 'NOT EXISTS' ),
		array( 'key' => 'land_availability', 'value' => 'sold', 'compare' => '!=' ),
	);
}

function pera_land_filter_archive_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'land' ) || $query->is_feed() ) {
		return;
	}

	$filters = pera_land_archive_filters( $_GET );
	$query->set( 'pera_land_filters', $filters );
	$query->set( 'post_status', 'publish' );
	$query->set( 'has_password', false );
	$query->set( 'posts_per_page', 18 );
	$query->set( 'orderby', array( 'date' => 'DESC', 'ID' => 'DESC' ) );

	if ( isset( $filters['land_district'] ) ) {
		$tax_query = (array) $query->get( 'tax_query', array() );
		$tax_query[] = array(
			'taxonomy' => 'district',
			'field'    => 'slug',
			'terms'    => $filters['land_district'],
		);
		$query->set( 'tax_query', $tax_query );
	}

	$meta = array( 'relation' => 'AND', pera_land_archive_available_clause() );
	foreach ( array( 'land_min_area' => '>=', 'land_max_area' => '<=' ) as $key => $operator ) {
		if ( isset( $filters[ $key ] ) ) {
			$meta[] = array( 'key' => 'land_plot_area_sqm', 'value' => $filters[ $key ], 'compare' => $operator, 'type' => 'DECIMAL(16,2)' );
		}
	}

	if ( isset( $filters['land_max_price'] ) ) {
		$meta[] = array( 'key' => 'land_price_visibility', 'value' => 'public' );
		$meta[] = array( 'key' => 'land_asking_currency', 'value' => $filters['land_currency'] );
		$meta[] = array(
			'key'     => 'land_asking_price',
			'value'   => array( '0.01', $filters['land_max_price'] ),
			'compare' => 'BETWEEN',
			'type'    => 'DECIMAL(16,2)',
		);
	}

	$existing_meta = $query->get( 'meta_query', array() );
	if ( is_array( $existing_meta ) && $existing_meta ) {
		$meta[] = $existing_meta;
	}
	$query->set( 'meta_query', $meta );
}
add_action( 'pre_get_posts', 'pera_land_filter_archive_query' );

/**
 * Include parent districts when listings use their neighbourhood terms.
 */
function pera_land_archive_districts(): array {
	$ids = get_posts( array(
		'post_type'      => 'land',
		'post_status'    => 'publish',
		'has_password'   => false,
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'none',
		'meta_query'     => array( pera_land_archive_available_clause() ),
	) );
	if ( ! $ids ) {
		return array();
	}

	$terms = wp_get_object_terms( $ids, 'district' );
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$districts = array();
	foreach ( $terms as $term ) {
		$districts[ $term->term_id ] = $term;
		foreach ( get_ancestors( $term->term_id, 'district', 'taxonomy' ) as $parent_id ) {
			if ( ! isset( $districts[ $parent_id ] ) ) {
				$parent = get_term( $parent_id, 'district' );
				if ( $parent instanceof WP_Term ) {
					$districts[ $parent_id ] = $parent;
				}
			}
		}
	}

	usort( $districts, static function ( WP_Term $a, WP_Term $b ): int {
		return strnatcasecmp( $a->name, $b->name );
	} );
	return $districts;
}
