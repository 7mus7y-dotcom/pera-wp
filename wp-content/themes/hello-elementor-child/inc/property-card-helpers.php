<?php
/**
 * Property card helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_render_property_card' ) ) {
	/**
	 * Render a property card with provided args.
	 *
	 * @param array $args Card arguments.
	 */
	function pera_render_property_card( array $args = array() ): void {
		$prev = get_query_var( 'pera_property_card_args', null );

		set_query_var( 'pera_property_card_args', $args );

		get_template_part( 'parts/property-card-v2' );

		set_query_var( 'pera_property_card_args', $prev );
	}
}

if ( ! function_exists( 'pera_get_property_card_location_terms' ) ) {
	/**
	 * Resolve district/region terms for property-card style outputs.
	 *
	 * District uses the canonical deepest-term helper when available.
	 * Region intentionally preserves current behavior (first assigned term).
	 *
	 * @param int $post_id Property post ID.
	 * @return array{district_term:WP_Term|null,region_term:WP_Term|null}
	 */
	function pera_get_property_card_location_terms( int $post_id ): array {
		$district_term = null;
		$region_term   = null;

		if ( $post_id > 0 && function_exists( 'pera_get_deepest_term' ) ) {
			$deepest = pera_get_deepest_term( $post_id, 'district' );
			if ( $deepest instanceof WP_Term && ! is_wp_error( $deepest ) ) {
				$district_term = $deepest;
			}
		}

		if ( ! $district_term && $post_id > 0 ) {
			$district_terms = get_the_terms( $post_id, 'district' );
			$district_term  = ( ! empty( $district_terms ) && ! is_wp_error( $district_terms ) ) ? $district_terms[0] : null;
		}

		if ( $post_id > 0 ) {
			$region_terms = get_the_terms( $post_id, 'region' );
			$region_term  = ( ! empty( $region_terms ) && ! is_wp_error( $region_terms ) ) ? $region_terms[0] : null;
		}

		return array(
			'district_term' => $district_term,
			'region_term'   => $region_term,
		);
	}
}
