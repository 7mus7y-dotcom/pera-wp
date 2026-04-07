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
	function pera_latest_offers_format_price( string $raw_value ): string {
		$clean = preg_replace( '/[^0-9]/', '', $raw_value );
		if ( ! is_string( $clean ) || '' === $clean ) {
			return '—';
		}

		return '$' . number_format_i18n( (float) $clean, 0 );
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
	/**
	 * @param array<string,mixed> $offer_row
	 * @return array<string,mixed>
	 */
	function pera_latest_offers_card_view_model( int $property_id, array $offer_row ): array {
		$title      = pera_latest_offers_property_title( $property_id );
		$property_url = get_permalink( $property_id );

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
			'type'            => '' !== $type ? $type : '—',
			'floor'           => $floor_text,
			'net_sqm'         => $net_size,
			'gross_sqm'       => $gross_size,
			'list_price'      => $list_price,
			'cash_price'      => $cash_price,
			'notes'           => $notes,
			'floor_plan_url'  => $floor_plan_url,
		);
	}
}

if ( ! function_exists( 'pera_latest_offers_enqueue_card_styles' ) ) {
	function pera_latest_offers_enqueue_card_styles(): void {
		wp_enqueue_style(
			'pera-latest-offers-card',
			get_stylesheet_directory_uri() . '/css/latest-offers-card.css',
			array( 'pera-main-css' ),
			pera_get_asset_version( '/css/latest-offers-card.css' )
		);
	}
}

if ( ! function_exists( 'pera_latest_offers_collect_homepage_cards' ) ) {
	/**
	 * Collect up to N flattened latest-offer cards across recent published properties.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	function pera_latest_offers_collect_homepage_cards( int $limit = 6, int $candidate_limit = 36 ): array {
		$limit           = $limit > 0 ? $limit : 6;
		$candidate_limit = $candidate_limit > 0 ? $candidate_limit : 36;

		$property_ids = get_posts(
			array(
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
			)
		);

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

if ( ! function_exists( 'pera_latest_offers_render_cards_for_property' ) ) {
	function pera_latest_offers_render_cards_for_property( int $property_id ): void {
		if ( $property_id <= 0 ) {
			return;
		}

		$rows = pera_latest_offers_get_rows( $property_id );
		if ( empty( $rows ) ) {
			return;
		}

		pera_latest_offers_enqueue_card_styles();

		echo '<div class="pera-latest-offers-card-list">';
		foreach ( $rows as $offer_row ) {
			$card = pera_latest_offers_card_view_model( $property_id, $offer_row );
			pera_latest_offers_render_card( $card );
		}
		echo '</div>';
	}
}
