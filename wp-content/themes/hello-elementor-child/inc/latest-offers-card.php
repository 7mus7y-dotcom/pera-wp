<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_latest_offers_meta_key' ) ) {
	function pera_latest_offers_meta_key(): string {
		return '_pera_latest_offers';
	}
}

if ( ! function_exists( 'pera_latest_offers_get_rows' ) ) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function pera_latest_offers_get_rows( int $property_id ): array {
		if ( $property_id <= 0 ) {
			return array();
		}

		$rows = get_post_meta( $property_id, pera_latest_offers_meta_key(), true );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$normalized[] = array(
				'type'          => isset( $row['type'] ) ? trim( (string) $row['type'] ) : '',
				'floor'         => isset( $row['floor'] ) ? trim( (string) $row['floor'] ) : '',
				'net_sqm'       => isset( $row['net_sqm'] ) ? trim( (string) $row['net_sqm'] ) : '',
				'gross_sqm'     => isset( $row['gross_sqm'] ) ? trim( (string) $row['gross_sqm'] ) : '',
				'list_price'    => isset( $row['list_price'] ) ? trim( (string) $row['list_price'] ) : '',
				'cash_price'    => isset( $row['cash_price'] ) ? trim( (string) $row['cash_price'] ) : '',
				'notes'         => isset( $row['notes'] ) ? trim( (string) $row['notes'] ) : '',
				'floor_plan_id' => isset( $row['floor_plan_id'] ) ? (int) $row['floor_plan_id'] : 0,
			);
		}

		return $normalized;
	}
}

if ( ! function_exists( 'pera_latest_offers_format_price' ) ) {
	/**
	 * Normalize raw price input to a numeric value in whole currency units.
	 *
	 * Accepts values such as:
	 * - 600000
	 * - 600,000
	 * - 600000.00
	 * - $600,000.00
	 *
	 * @return float|null
	 */
	function pera_latest_offers_parse_price( string $raw_value ): ?float {
		$clean = trim( preg_replace( '/[^0-9,\.]/', '', $raw_value ) ?? '' );
		if ( '' === $clean ) {
			return null;
		}

		$has_comma = false !== strpos( $clean, ',' );
		$has_dot   = false !== strpos( $clean, '.' );

		if ( $has_comma && $has_dot ) {
			$last_comma = (int) strrpos( $clean, ',' );
			$last_dot   = (int) strrpos( $clean, '.' );
			$decimal    = $last_comma > $last_dot ? ',' : '.';
			$thousand   = ',' === $decimal ? '.' : ',';

			$clean = str_replace( $thousand, '', $clean );
			$clean = str_replace( $decimal, '.', $clean );
		} elseif ( $has_comma || $has_dot ) {
			$separator     = $has_comma ? ',' : '.';
			$parts         = explode( $separator, $clean );
			$part_count    = count( $parts );
			$last_fragment = (string) end( $parts );

			if ( 2 === $part_count && preg_match( '/^\d{1,2}$/', $last_fragment ) ) {
				$clean = str_replace( $separator, '.', $clean );
			} else {
				$clean = str_replace( $separator, '', $clean );
			}
		}

		if ( '' === $clean || ! is_numeric( $clean ) ) {
			return null;
		}

		$value = (float) $clean;
		return $value > 0 ? $value : null;
	}

	function pera_latest_offers_format_price( string $raw_value ): string {
		$price = pera_latest_offers_parse_price( $raw_value );
		if ( null === $price ) {
			return '—';
		}

		return '$' . number_format_i18n( $price, 0 );
	}
}

if ( ! function_exists( 'pera_latest_offers_format_size' ) ) {
	function pera_latest_offers_format_size( string $raw_value ): string {
		$clean = preg_replace( '/[^0-9.,]/', '', $raw_value );
		if ( ! is_string( $clean ) || '' === $clean ) {
			return '—';
		}

		$clean = str_replace( ',', '.', $clean );
		if ( ! is_numeric( $clean ) ) {
			return '—';
		}

		$size = (float) $clean;
		if ( $size <= 0 ) {
			return '—';
		}

		$display = fmod( $size, 1.0 ) === 0.0
			? number_format_i18n( $size, 0 )
			: number_format_i18n( $size, 2 );

		return $display . ' m²';
	}
}

if ( ! function_exists( 'pera_latest_offers_floor_plan_url' ) ) {
	function pera_latest_offers_floor_plan_url( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );
		return is_string( $attachment_url ) ? $attachment_url : '';
	}
}

if ( ! function_exists( 'pera_latest_offers_format_notes' ) ) {
	function pera_latest_offers_format_notes( string $notes ): string {
		return trim( $notes );
	}
}

if ( ! function_exists( 'pera_is_portfolio_token_page' ) ) {
	function pera_is_portfolio_token_page(): bool {
		$token = get_query_var( 'portfolio_token' );
		return ! empty( $token );
	}
}

if ( ! function_exists( 'pera_latest_offers_property_title' ) ) {
	function pera_latest_offers_property_title( int $property_id ): string {
		$title = trim( (string) get_the_title( $property_id ) );
		if ( '' === $title ) {
			return __( 'Untitled property', 'hello-elementor-child' );
		}

		return $title;
	}
}

if ( ! function_exists( 'pera_latest_offers_format_floor' ) ) {
	function pera_latest_offers_format_floor( string $raw_floor ): string {
		$floor = trim( $raw_floor );
		if ( '' === $floor ) {
			return '';
		}

		return sprintf( __( 'Floor %s', 'hello-elementor-child' ), $floor );
	}
}

if ( ! function_exists( 'pera_latest_offers_card_view_model' ) ) {
	if ( ! function_exists( 'pera_latest_offers_primary_term_name' ) ) {
		function pera_latest_offers_primary_term_name( int $property_id, string $taxonomy ): string {
			if ( $property_id <= 0 || '' === $taxonomy ) {
				return '';
			}

			$terms = get_the_terms( $property_id, $taxonomy );
			if ( is_wp_error( $terms ) || empty( $terms ) || ! is_array( $terms ) ) {
				return '';
			}

			$term = reset( $terms );
			if ( ! ( $term instanceof WP_Term ) ) {
				return '';
			}

			return trim( (string) $term->name );
		}
	}

	if ( ! function_exists( 'pera_latest_offers_property_location_names' ) ) {
		/**
		 * @return array{region_name:string,district_name:string}
		 */
		function pera_latest_offers_property_location_names( int $property_id ): array {
			$region_name   = '';
			$district_name = '';

			if ( $property_id > 0 && function_exists( 'pera_get_property_card_location_terms' ) ) {
				$location_terms = pera_get_property_card_location_terms( $property_id );
				$region_term    = $location_terms['region_term'] ?? null;
				$district_term  = $location_terms['district_term'] ?? null;

				if ( $region_term instanceof WP_Term ) {
					$region_name = trim( (string) $region_term->name );
				}
				if ( $district_term instanceof WP_Term ) {
					$district_name = trim( (string) $district_term->name );
				}
			}

			if ( '' === $region_name ) {
				$region_name = pera_latest_offers_primary_term_name( $property_id, 'region' );
			}
			if ( '' === $district_name ) {
				$district_name = pera_latest_offers_primary_term_name( $property_id, 'district' );
			}

			return array(
				'region_name'   => $region_name,
				'district_name' => $district_name,
			);
		}
	}

	if ( ! function_exists( 'pera_latest_offers_main_image_id' ) ) {
		function pera_latest_offers_main_image_id( int $property_id ): int {
			if ( $property_id <= 0 || ! function_exists( 'get_field' ) ) {
				return 0;
			}

			$main_image = get_field( 'main_image', $property_id );

			if ( is_array( $main_image ) ) {
				$image_id = isset( $main_image['ID'] ) ? (int) $main_image['ID'] : 0;
				if ( $image_id > 0 ) {
					return $image_id;
				}
			}

			if ( is_numeric( $main_image ) ) {
				$image_id = (int) $main_image;
				if ( $image_id > 0 ) {
					return $image_id;
				}
			}

			if ( is_string( $main_image ) ) {
				$image_url = trim( $main_image );
				if ( '' !== $image_url ) {
					$image_id = attachment_url_to_postid( $image_url );
					if ( $image_id > 0 ) {
						return (int) $image_id;
					}
				}
			}

			return 0;
		}
	}

if ( ! function_exists( 'pera_latest_offers_property_map_url' ) ) {
	function pera_latest_offers_property_map_url( int $property_id ): string {
		if ( $property_id <= 0 || ! function_exists( 'get_field' ) ) {
			return '';
		}

			$map = get_field( 'map', $property_id );
			if ( ! is_array( $map ) ) {
				return '';
			}

			$lat = isset( $map['lat'] ) ? trim( (string) $map['lat'] ) : '';
			$lng = isset( $map['lng'] ) ? trim( (string) $map['lng'] ) : '';
			if ( '' === $lat || '' === $lng ) {
				return '';
			}

		return 'https://www.google.com/maps?q=' . rawurlencode( $lat . ',' . $lng );
	}
}

if ( ! function_exists( 'pera_latest_offers_property_map_coords' ) ) {
	/**
	 * @return array{lat:float,lng:float}|array
	 */
	function pera_latest_offers_property_map_coords( int $property_id ): array {
		if ( $property_id <= 0 || ! function_exists( 'get_field' ) ) {
			return array();
		}

		$map = get_field( 'map', $property_id );
		if ( ! is_array( $map ) ) {
			return array();
		}

		$lat_raw = isset( $map['lat'] ) ? trim( (string) $map['lat'] ) : '';
		$lng_raw = isset( $map['lng'] ) ? trim( (string) $map['lng'] ) : '';
		if ( '' === $lat_raw || '' === $lng_raw ) {
			return array();
		}

		if ( ! is_numeric( $lat_raw ) || ! is_numeric( $lng_raw ) ) {
			return array();
		}

		$lat = (float) $lat_raw;
		$lng = (float) $lng_raw;

		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return array();
		}

		return array(
			'lat' => $lat,
			'lng' => $lng,
		);
	}
}

	/**
	 * @param array<string,mixed> $offer_row
	 * @return array<string,mixed>
	 */
	function pera_latest_offers_card_view_model( int $property_id, array $offer_row ): array {
		$title      = pera_latest_offers_property_title( $property_id );
		$property_url = get_permalink( $property_id );
		$image_id     = pera_latest_offers_main_image_id( $property_id );
		$location_names = pera_latest_offers_property_location_names( $property_id );
		$region_name    = $location_names['region_name'] ?? '';
		$district_name  = $location_names['district_name'] ?? '';
		$map_url        = pera_latest_offers_property_map_url( $property_id );

		$type       = isset( $offer_row['type'] ) ? trim( (string) $offer_row['type'] ) : '';
		$floor_text = isset( $offer_row['floor'] ) ? pera_latest_offers_format_floor( (string) $offer_row['floor'] ) : '';
		$net_size   = isset( $offer_row['net_sqm'] ) ? pera_latest_offers_format_size( (string) $offer_row['net_sqm'] ) : '—';
		$gross_size = isset( $offer_row['gross_sqm'] ) ? pera_latest_offers_format_size( (string) $offer_row['gross_sqm'] ) : '—';
		$list_price = isset( $offer_row['list_price'] ) ? pera_latest_offers_format_price( (string) $offer_row['list_price'] ) : '—';
		$cash_price = isset( $offer_row['cash_price'] ) ? pera_latest_offers_format_price( (string) $offer_row['cash_price'] ) : '—';
		$notes      = isset( $offer_row['notes'] ) ? pera_latest_offers_format_notes( (string) $offer_row['notes'] ) : '';

		$floor_plan_id  = isset( $offer_row['floor_plan_id'] ) ? (int) $offer_row['floor_plan_id'] : 0;
		$floor_plan_url = pera_latest_offers_floor_plan_url( $floor_plan_id );

		return array(
			'property_id'     => $property_id,
			'property_title'  => $title,
			'property_url'    => is_string( $property_url ) ? $property_url : '',
			'image_id'        => $image_id,
			'region_name'     => $region_name,
			'district_name'   => $district_name,
			'type'            => '' !== $type ? $type : '—',
			'floor'           => $floor_text,
			'net_sqm'         => $net_size,
			'gross_sqm'       => $gross_size,
			'list_price'      => $list_price,
			'cash_price'      => $cash_price,
			'notes'           => $notes,
			'floor_plan_url'  => $floor_plan_url,
			'map_url'         => $map_url,
		);
	}
}

if ( ! function_exists( 'pera_latest_offers_enqueue_card_styles' ) ) {
	function pera_latest_offers_enqueue_card_styles(): void {
		wp_enqueue_style(
			'pera-card-typography',
			get_stylesheet_directory_uri() . '/css/card-typography.css',
			array( 'pera-main-css' ),
			pera_get_asset_version( '/css/card-typography.css' )
		);

		wp_enqueue_style(
			'pera-latest-offers-card',
			get_stylesheet_directory_uri() . '/css/latest-offers-card.css',
			array( 'pera-main-css', 'pera-card-typography' ),
			pera_get_asset_version( '/css/latest-offers-card.css' )
		);
	}
}

if ( ! function_exists( 'pera_latest_offers_should_enqueue_card_styles' ) ) {
	function pera_latest_offers_should_enqueue_card_styles(): bool {
		if ( is_front_page() || is_page_template( 'home-page.php' ) ) {
			return true;
		}

		if ( is_page_template( 'page-citizenship-properties.php' ) ) {
			return true;
		}

		if ( function_exists( 'pera_portfolio_token_is_request' ) && pera_portfolio_token_is_request() ) {
			return true;
		}

		if ( function_exists( 'pera_theme_portfolio_token_is_request' ) && pera_theme_portfolio_token_is_request() ) {
			return true;
		}

		return false;
	}
}

add_action(
	'wp_enqueue_scripts',
	function (): void {
		if ( ! function_exists( 'pera_latest_offers_should_enqueue_card_styles' ) || ! pera_latest_offers_should_enqueue_card_styles() ) {
			return;
		}

		pera_latest_offers_enqueue_card_styles();
	},
	20
);

if ( ! function_exists( 'pera_latest_offers_collect_cards' ) ) {
	/**
	 * Collect flattened latest-offer cards across published properties.
	 *
	 * @param array<string,mixed> $query_args Additional get_posts() query args (for example tax_query).
	 * @return array<int,array<string,mixed>>
	 */
	function pera_latest_offers_collect_cards( int $limit = 6, int $candidate_limit = 36, array $query_args = array() ): array {
		$limit           = $limit > 0 ? $limit : 6;
		$candidate_limit = $candidate_limit > 0 ? $candidate_limit : 36;

		$base_query_args = array(
			'post_type'              => 'property',
			'post_status'            => 'publish',
			'posts_per_page'         => $candidate_limit,
			'meta_query'             => array(
				array(
					'key'     => pera_latest_offers_meta_key(),
					'compare' => 'EXISTS',
				),
			),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$final_query_args = $base_query_args;

		if ( ! empty( $query_args ) ) {
			$final_query_args = wp_parse_args( $query_args, $final_query_args );
		}

		if ( isset( $query_args['meta_query'] ) && is_array( $query_args['meta_query'] ) ) {
			$final_query_args['meta_query'] = array_merge(
				$base_query_args['meta_query'],
				$query_args['meta_query']
			);
		}

		$property_ids = get_posts( $final_query_args );

		if ( empty( $property_ids ) || ! is_array( $property_ids ) ) {
			return array();
		}

		$cards = array();

		foreach ( $property_ids as $property_id ) {
			$property_id = (int) $property_id;
			if ( $property_id <= 0 ) {
				continue;
			}

			$rows = pera_latest_offers_get_rows( $property_id );
			if ( empty( $rows ) ) {
				continue;
			}

			foreach ( $rows as $offer_row ) {
				$cards[] = pera_latest_offers_card_view_model( $property_id, $offer_row );
				if ( count( $cards ) >= $limit ) {
					break 2;
				}
			}
		}

		return array_values( array_filter( $cards ) );
	}
}

if ( ! function_exists( 'pera_latest_offers_collect_homepage_cards' ) ) {
	/**
	 * Collect up to N flattened latest-offer cards across recent published properties.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	function pera_latest_offers_collect_homepage_cards( int $limit = 6, int $candidate_limit = 36 ): array {
		return function_exists( 'pera_latest_offers_collect_cards' )
			? pera_latest_offers_collect_cards( $limit, $candidate_limit )
			: array();
	}
}

if ( ! function_exists( 'pera_latest_offers_render_card' ) ) {
	/**
	 * @param array<string,mixed> $card
	 */
	function pera_latest_offers_render_card( array $card ): void {
		if ( empty( $card ) ) {
			return;
		}

		$previous = get_query_var( 'pera_latest_offer_card', null );
		set_query_var( 'pera_latest_offer_card', $card );
		get_template_part( 'partials/latest-offers-card' );
		set_query_var( 'pera_latest_offer_card', $previous );
	}
}

if ( ! function_exists( 'pera_latest_offers_marker_dto_from_card' ) ) {
	/**
	 * @param array<string,mixed> $card
	 * @return array<string,mixed>|null
	 */
	function pera_latest_offers_marker_dto_from_card( array $card ): ?array {
		$post_id = isset( $card['property_id'] ) ? (int) $card['property_id'] : 0;
		if ( $post_id <= 0 ) {
			return null;
		}

		$coords = pera_latest_offers_property_map_coords( $post_id );
		if ( empty( $coords ) ) {
			return null;
		}

		$title     = isset( $card['property_title'] ) ? trim( (string) $card['property_title'] ) : '';
		$permalink = isset( $card['property_url'] ) ? (string) $card['property_url'] : '';
		$list      = isset( $card['list_price'] ) ? trim( (string) $card['list_price'] ) : '';
		$cash      = isset( $card['cash_price'] ) ? trim( (string) $card['cash_price'] ) : '';

		$price_display = '—';
		if ( '' !== $list && '—' !== $list ) {
			$price_display = $list;
		} elseif ( '' !== $cash && '—' !== $cash ) {
			$price_display = $cash;
		}

		$location_bits = array();
		$district      = isset( $card['district_name'] ) ? trim( (string) $card['district_name'] ) : '';
		$region        = isset( $card['region_name'] ) ? trim( (string) $card['region_name'] ) : '';
		if ( '' !== $district ) {
			$location_bits[] = $district;
		}
		if ( '' !== $region ) {
			$location_bits[] = $region;
		}
		$location_label = implode( ', ', $location_bits );

		$image_id      = isset( $card['image_id'] ) ? (int) $card['image_id'] : 0;
		$thumbnail_url = '';
		if ( $image_id > 0 ) {
			$thumbnail = wp_get_attachment_image_url( $image_id, 'medium_large' );
			if ( is_string( $thumbnail ) ) {
				$thumbnail_url = $thumbnail;
			}
		}

		return array(
			'post_id'         => $post_id,
			'title'           => $title,
			'permalink'       => $permalink,
			'lat'             => (float) $coords['lat'],
			'lng'             => (float) $coords['lng'],
			'price_display'   => $price_display,
			'location_label'  => $location_label,
			'thumbnail_url'   => $thumbnail_url,
		);
	}
}

if ( ! function_exists( 'pera_latest_offers_render_cards_for_property' ) ) {
	function pera_latest_offers_render_cards_for_property( int $property_id ): void {
		if ( $property_id <= 0 ) {
			return;
		}

		$rows = pera_latest_offers_get_rows( $property_id );
		if ( empty( $rows ) ) {
			return;
		}

			echo '<div class="pera-latest-offers-card-list">';
		foreach ( $rows as $offer_row ) {
			$card = pera_latest_offers_card_view_model( $property_id, $offer_row );
			pera_latest_offers_render_card( $card );
		}
		echo '</div>';
	}
}
