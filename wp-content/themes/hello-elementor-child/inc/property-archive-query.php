<?php
/**
 * Property archive query builder.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_property_archive_build_args_from_context' ) ) {
	function pera_property_archive_build_args_from_context( array $ctx, array $overrides = array() ): array {
		$paged                       = $ctx['paged'] ?? null;
		$current_district            = $ctx['current_district'] ?? null;
		$current_tag                 = $ctx['current_tag'] ?? null;
		$current_type                = $ctx['current_type'] ?? null;
		$selected_beds               = $ctx['selected_beds'] ?? null;
		$current_keyword             = $ctx['current_keyword'] ?? null;
		$current_keyword_is_post_id  = $ctx['current_keyword_is_post_id'] ?? null;
		$current_keyword_post_id     = $ctx['current_keyword_post_id'] ?? null;
		$taxonomy_context            = $ctx['taxonomy_context'] ?? null;
		$has_price_qs                = $ctx['has_price_qs'] ?? null;
		$qs_min                      = $ctx['qs_min'] ?? null;
		$qs_max                      = $ctx['qs_max'] ?? null;
		$sort                        = isset($ctx['sort']) ? (string) $ctx['sort'] : 'date_desc';

		// Ensure these are defined (defensive)
		if ( ! isset( $paged ) ) {
			$paged = max( 1, (int) get_query_var( 'paged' ) );
			if ( get_query_var( 'page' ) ) {
				$paged = max( $paged, (int) get_query_var( 'page' ) );
			}
		}

		$current_district = isset($current_district) && is_array($current_district) ? $current_district : array();
		$current_tag      = isset($current_tag) && is_array($current_tag) ? $current_tag : array();
		$current_type     = isset($current_type) ? (string) $current_type : '';
		$selected_beds    = isset($selected_beds) ? (string) $selected_beds : '';
		$current_keyword  = isset($current_keyword) ? (string) $current_keyword : '';
		$taxonomy_context = isset( $taxonomy_context ) && is_array( $taxonomy_context ) ? $taxonomy_context : array();

		$has_price_qs = isset($has_price_qs) ? (bool) $has_price_qs : false;
		$qs_min       = isset($qs_min) ? (int) $qs_min : 0;
		$qs_max       = isset($qs_max) ? (int) $qs_max : 0;

		// Base args
		$args = array(
			'post_type'              => 'property',
			'post_status'            => 'publish',
			'posts_per_page'         => 12,
			'paged'                  => $paged,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		// ------------------------------------------------------------
		// TAX QUERY
		// ------------------------------------------------------------
		$tax_query = array();

		// Taxonomy context (property term archives)
		if ( ! empty( $taxonomy_context['taxonomy'] ) && ! empty( $taxonomy_context['term_id'] ) ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy_context['taxonomy'],
				'field'    => 'term_id',
				'terms'    => array( (int) $taxonomy_context['term_id'] ),
			);
		}

		// District (multi)
		if ( ! empty( $current_district ) ) {
			$tax_query[] = array(
				'taxonomy' => 'district',
				'field'    => 'slug',
				'terms'    => $current_district,
				'operator' => 'IN',
			);
		}

		// Property type (single)
		if ( $current_type !== '' ) {
			$tax_query[] = array(
				'taxonomy' => 'property_type',
				'field'    => 'slug',
				'terms'    => $current_type,
			);
		}

		// Tags (multi)
		if ( ! empty( $current_tag ) ) {
			$tax_query[] = array(
				'taxonomy' => 'property_tags',
				'field'    => 'slug',
				'terms'    => $current_tag,
				'operator' => 'IN',
			);
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_query );
		}

		// ------------------------------------------------------------
		// META QUERY (V2)
		// ------------------------------------------------------------
		$meta_query = array();

		// V2 Beds filter (radio) -> v2_index_flat LIKE "|2|"
		if ( $selected_beds !== '' && preg_match( '/^\d+$/', $selected_beds ) ) {
			$b = (int) $selected_beds;

			$meta_query[] = array(
				'key'     => 'v2_index_flat',
				'value'   => '|' . $b . '|',
				'compare' => 'LIKE',
			);
		}

		// Price filter: overlap logic using v2_price_usd_min/max
		// A property matches if:
		// - v2_price_usd_max >= min (when min provided)
		// - v2_price_usd_min <= max (when max provided)
		if ( $has_price_qs ) {

			$min = ( $qs_min > 0 ) ? $qs_min : null;
			$max = ( $qs_max > 0 ) ? $qs_max : null;

			if ( $min !== null ) {
				$meta_query[] = array(
					'key'     => 'v2_price_usd_max',
					'value'   => $min,
					'type'    => 'NUMERIC',
					'compare' => '>=',
				);
			}

			if ( $max !== null ) {
				$meta_query[] = array(
					'key'     => 'v2_price_usd_min',
					'value'   => $max,
					'type'    => 'NUMERIC',
					'compare' => '<=',
				);
			}
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta_query );
		}

		// ------------------------------------------------------------
		// KEYWORD
		// ------------------------------------------------------------
		if ( $current_keyword !== '' ) {
			if ( $current_keyword_is_post_id ) {
				$args['p'] = $current_keyword_post_id;
			} else {
				$args['s'] = $current_keyword;
				if ( function_exists( 'pera_is_frontend_admin_equivalent' ) && pera_is_frontend_admin_equivalent() ) {
					$args['pera_kw_project'] = 1;
				}
			}
		}

		// ------------------------------------------------------------
		// SORTING (V2)
		// - price sorts use v2_price_usd_min
		// ------------------------------------------------------------
		switch ( $sort ) {
			case 'price_asc':
				$args['meta_key'] = 'v2_price_usd_min';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';
				break;

			case 'price_desc':
				$args['meta_key'] = 'v2_price_usd_min';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;

			case 'date_asc':
				$args['orderby'] = 'date';
				$args['order']   = 'ASC';
				break;

			case 'date_desc':
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
		}

		if ( ! empty( $overrides['post__in'] ) ) {
			$args['post__in'] = $overrides['post__in'];
		}

		if ( ! empty( $overrides['orderby'] ) ) {
			$args['orderby'] = $overrides['orderby'];
		}

		return $args;
	}
}
