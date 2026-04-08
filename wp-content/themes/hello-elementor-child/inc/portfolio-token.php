<?php
/**
 * Public token-based property portfolios.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_portfolio_token_register_post_type' ) ) {
	/**
	 * Register the portfolio post type used to store token portfolios.
	 */
	function pera_portfolio_token_register_post_type(): void {
		$show_ui = current_user_can( 'manage_options' );

		register_post_type(
			'portfolio',
			array(
				'labels'              => array(
					'name'          => __( 'Portfolios', 'hello-elementor-child' ),
					'singular_name' => __( 'Portfolio', 'hello-elementor-child' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => $show_ui,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title' ),
			)
		);
	}
}
add_action( 'init', 'pera_portfolio_token_register_post_type' );

if ( ! function_exists( 'pera_portfolio_user_can_manage' ) ) {
	/**
	 * Check whether current user can manage portfolio creation for CRM flows.
	 */
	function pera_portfolio_user_can_manage(): bool {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_crm_clients' ) ) {
			return true;
		}

		if ( function_exists( 'pera_is_frontend_admin_equivalent' ) && pera_is_frontend_admin_equivalent() ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'pera_portfolio_token_add_rewrite_rule' ) ) {
	/**
	 * Add /portfolio/{token}/ route.
	 */
	function pera_portfolio_token_add_rewrite_rule(): void {
		add_rewrite_rule( '^portfolio/([^/]+)/?$', 'index.php?portfolio_token=$matches[1]', 'top' );
		add_rewrite_rule( '^portfolio-theme/([^/]+)/?$', 'index.php?portfolio_theme_token=$matches[1]', 'top' );
	}
}
add_action( 'init', 'pera_portfolio_token_add_rewrite_rule' );

if ( ! function_exists( 'pera_portfolio_token_query_vars' ) ) {
	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	function pera_portfolio_token_query_vars( array $vars ): array {
		$vars[] = 'portfolio_token';
		$vars[] = 'portfolio_theme_token';
		return $vars;
	}
}
add_filter( 'query_vars', 'pera_portfolio_token_query_vars' );

if ( ! function_exists( 'pera_portfolio_token_get_request_token' ) ) {
	/**
	 * Get sanitized token from query var.
	 */
	function pera_portfolio_token_get_request_token(): string {
		$raw_token = (string) get_query_var( 'portfolio_token' );
		$token     = preg_replace( '/[^A-Za-z0-9]/', '', $raw_token );
		return is_string( $token ) ? $token : '';
	}
}

if ( ! function_exists( 'pera_portfolio_token_is_request' ) ) {
	/**
	 * Check if current front-end request is a portfolio token URL.
	 */
	function pera_portfolio_token_is_request(): bool {
		return pera_portfolio_token_get_request_token() !== '';
	}
}

if ( ! function_exists( 'pera_theme_portfolio_token_get_request_token' ) ) {
	/**
	 * Get sanitized theme-portfolio token from query var.
	 */
	function pera_theme_portfolio_token_get_request_token(): string {
		$raw_token = (string) get_query_var( 'portfolio_theme_token' );
		$token     = preg_replace( '/[^A-Za-z0-9]/', '', $raw_token );
		return is_string( $token ) ? $token : '';
	}
}

if ( ! function_exists( 'pera_theme_portfolio_token_is_request' ) ) {
	/**
	 * Check if current front-end request is a /portfolio-theme/{token}/ URL.
	 */
	function pera_theme_portfolio_token_is_request(): bool {
		return pera_theme_portfolio_token_get_request_token() !== '';
	}
}

if ( ! function_exists( 'pera_theme_portfolio_token_parse_property_ids' ) ) {
	/**
	 * Normalize theme portfolio property IDs from post meta values.
	 *
	 * @param mixed $raw_property_ids Raw meta value.
	 * @return int[]
	 */
	function pera_theme_portfolio_token_parse_property_ids( $raw_property_ids ): array {
		$property_ids = array();

		if ( is_array( $raw_property_ids ) ) {
			$property_ids = $raw_property_ids;
		} elseif ( is_string( $raw_property_ids ) && '' !== trim( $raw_property_ids ) ) {
			$decoded = json_decode( $raw_property_ids, true );
			if ( is_array( $decoded ) ) {
				$property_ids = $decoded;
			} else {
				$property_ids = preg_split( '/[\s,]+/', $raw_property_ids ) ?: array();
			}
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $property_ids ) ) ) );
	}
}

if ( ! function_exists( 'pera_theme_portfolio_token_find_client_id' ) ) {
	/**
	 * Resolve owning crm_client ID by _peracrm_theme_portfolio_token.
	 */
	function pera_theme_portfolio_token_find_client_id( string $token ): int {
		if ( '' === $token ) {
			return 0;
		}

		$client_ids = get_posts(
			array(
				'post_type'              => 'crm_client',
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_peracrm_theme_portfolio_token',
				'meta_value'             => $token,
			)
		);

		if ( ! empty( $client_ids ) ) {
			return (int) $client_ids[0];
		}

		return 0;
	}
}

if ( ! function_exists( 'pera_theme_portfolio_token_get_request_context' ) ) {
	/**
	 * Resolve context for /portfolio-theme/{token}/ requests.
	 *
	 * @return array{is_request:bool,is_valid:bool,status:int,client_id:int,client_name:string,advisor_name:string,property_ids:array<int>,created_at:int,updated_at:int}
	 */
	function pera_theme_portfolio_token_get_request_context(): array {
		static $context = null;

		if ( is_array( $context ) ) {
			return $context;
		}

		$context = array(
			'is_request'  => false,
			'is_valid'    => false,
			'status'      => 404,
			'client_id'   => 0,
			'client_name' => '',
			'advisor_name'=> '',
			'property_ids'=> array(),
			'created_at'  => 0,
			'updated_at'  => 0,
		);

		$token = pera_theme_portfolio_token_get_request_token();
		if ( '' === $token ) {
			return $context;
		}

		$context['is_request'] = true;

		$resolved = pera_theme_portfolio_token_get_request_context_by_token( $token );
		if ( empty( $resolved['is_valid'] ) ) {
			return $context;
		}

		$client_id = (int) ( $resolved['client_id'] ?? 0 );
		if ( $client_id <= 0 ) {
			return $context;
		}

		$context['is_valid']     = true;
		$context['status']       = (int) ( $resolved['status'] ?? 200 );
		$context['client_id']    = $client_id;
		$context['client_name']  = isset( $resolved['client_name'] ) ? (string) $resolved['client_name'] : '';
		$context['advisor_name'] = isset( $resolved['advisor_name'] ) ? (string) $resolved['advisor_name'] : '';
		$context['property_ids'] = isset( $resolved['property_ids'] ) && is_array( $resolved['property_ids'] )
			? array_values( array_filter( array_map( 'absint', $resolved['property_ids'] ) ) )
			: array();
		$context['created_at']   = max( 0, (int) get_post_meta( $client_id, '_peracrm_theme_portfolio_created_at', true ) );
		$context['updated_at']   = max( 0, (int) get_post_meta( $client_id, '_peracrm_theme_portfolio_updated_at', true ) );

		return $context;
	}
}

if ( ! function_exists( 'pera_theme_portfolio_token_build_offer_groups' ) ) {
	/**
	 * Build grouped property + offer data for theme portfolio rendering.
	 *
	 * @param int[] $property_ids Ordered property IDs.
	 * @return array<int,array<string,mixed>>
	 */
	function pera_theme_portfolio_token_build_offer_groups( array $property_ids ): array {
		$property_ids = array_values( array_unique( array_filter( array_map( 'absint', $property_ids ) ) ) );
		if ( empty( $property_ids ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'property',
				'post_status'            => 'publish',
				'post__in'               => $property_ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => count( $property_ids ),
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		if ( ! $query->have_posts() ) {
			return array();
		}

		$groups = array();

		while ( $query->have_posts() ) {
			$query->the_post();
			$property_id = (int) get_the_ID();
			$offers      = function_exists( 'pera_latest_offers_get_rows' ) ? pera_latest_offers_get_rows( $property_id ) : array();
			$cards       = array();

			foreach ( $offers as $offer_row ) {
				if ( ! is_array( $offer_row ) ) {
					continue;
				}
				if ( function_exists( 'pera_latest_offers_card_view_model' ) ) {
					$cards[] = pera_latest_offers_card_view_model( $property_id, $offer_row );
				}
			}

			$groups[] = array(
				'property_id'    => $property_id,
				'property_title' => trim( (string) get_the_title( $property_id ) ),
				'property_url'   => (string) get_permalink( $property_id ),
				'offers'         => is_array( $offers ) ? $offers : array(),
				'cards'          => $cards,
			);
		}

		wp_reset_postdata();

		return $groups;
	}
}

if ( ! function_exists( 'pera_portfolio_token_generate_unique_token' ) ) {
	/**
	 * Generate a unique 40-char token for a portfolio.
	 */
	function pera_portfolio_token_generate_unique_token(): string {
		$token = '';

		do {
			$token = wp_generate_password( 40, false, false );

			$existing = get_posts(
				array(
					'post_type'              => 'portfolio',
					'post_status'            => 'any',
					'fields'                 => 'ids',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_key'               => '_portfolio_token',
					'meta_value'             => $token,
				)
			);
		} while ( ! empty( $existing ) );

		return $token;
	}
}

if ( ! function_exists( 'pera_portfolio_token_create_portfolio' ) ) {
	/**
	 * Create a portfolio post and return its metadata.
	 *
	 * @param int[]      $property_ids Ordered list of property IDs.
	 * @param int        $client_id Optional CRM client ID.
	 * @param int|string $expires_at Optional Unix timestamp.
	 * @return array{post_id:int,token:string,url:string}|WP_Error
	 */
	function pera_portfolio_token_create_portfolio( array $property_ids, int $client_id = 0, $expires_at = 0 ) {
		$property_ids = array_values( array_unique( array_filter( array_map( 'absint', $property_ids ) ) ) );

		if ( empty( $property_ids ) ) {
			return new WP_Error( 'portfolio_no_properties', __( 'At least one property ID is required.', 'hello-elementor-child' ) );
		}

		$token = pera_portfolio_token_generate_unique_token();

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'portfolio',
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Portfolio %s', gmdate( 'Y-m-d H:i:s' ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$expires_ts = is_numeric( $expires_at ) ? (int) $expires_at : 0;

		update_post_meta( $post_id, '_portfolio_token', $token );
		update_post_meta( $post_id, '_portfolio_property_ids', $property_ids );
		update_post_meta( $post_id, '_portfolio_client_id', max( 0, $client_id ) );
		update_post_meta( $post_id, '_portfolio_expires_at', max( 0, $expires_ts ) );
		update_post_meta( $post_id, '_portfolio_revoked', 0 );

		return array(
			'post_id' => (int) $post_id,
			'token'   => $token,
			'url'     => trailingslashit( home_url( '/portfolio/' . $token ) ),
		);
	}
}

if ( ! function_exists( 'pera_portfolio_token_get_request_context' ) ) {
	/**
	 * Resolve the current portfolio token request.
	 *
	 * @return array{is_request:bool,is_valid:bool,status:int,portfolio_id:int,client_id:int,property_ids:array<int>,client_name:string,advisor_name:string,expires_at:int}
	 */
	function pera_portfolio_token_get_request_context(): array {
		static $context = null;

		if ( is_array( $context ) ) {
			return $context;
		}

		$context = array(
			'is_request'   => false,
			'is_valid'     => false,
			'status'       => 404,
			'portfolio_id' => 0,
			'client_id'    => 0,
			'property_ids' => array(),
			'client_name'  => '',
			'advisor_name' => '',
			'expires_at'   => 0,
		);

		$token = pera_portfolio_token_get_request_token();
		if ( '' === $token ) {
			return $context;
		}

		$context['is_request'] = true;

		$portfolio_ids = get_posts(
			array(
				'post_type'              => 'portfolio',
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_portfolio_token',
				'meta_value'             => $token,
			)
		);

		if ( empty( $portfolio_ids ) ) {
			$theme_context = pera_theme_portfolio_token_get_request_context_by_token( $token );
			if ( ! empty( $theme_context['is_valid'] ) ) {
				$context['is_valid']     = true;
				$context['status']       = (int) ( $theme_context['status'] ?? 200 );
				$context['client_id']    = (int) ( $theme_context['client_id'] ?? 0 );
				$context['property_ids'] = isset( $theme_context['property_ids'] ) && is_array( $theme_context['property_ids'] )
					? array_values( array_filter( array_map( 'absint', $theme_context['property_ids'] ) ) )
					: array();
				$context['client_name']  = isset( $theme_context['client_name'] ) ? (string) $theme_context['client_name'] : '';
				$context['advisor_name'] = isset( $theme_context['advisor_name'] ) ? (string) $theme_context['advisor_name'] : '';
			}

			return $context;
		}

		$portfolio_id = (int) $portfolio_ids[0];
		$revoked      = (int) get_post_meta( $portfolio_id, '_portfolio_revoked', true ) === 1;
		$expires_at   = (int) get_post_meta( $portfolio_id, '_portfolio_expires_at', true );
		$is_expired   = $expires_at > 0 && time() > $expires_at;

		if ( $revoked || $is_expired ) {
			$context['status']       = 410;
			$context['portfolio_id'] = $portfolio_id;
			return $context;
		}

		$property_ids = get_post_meta( $portfolio_id, '_portfolio_property_ids', true );
		$property_ids = is_array( $property_ids ) ? $property_ids : array();
		$property_ids = array_values( array_unique( array_filter( array_map( 'absint', $property_ids ) ) ) );

		$client_id    = (int) get_post_meta( $portfolio_id, '_portfolio_client_id', true );
		$client_name  = '';
		$advisor_name = '';

		if ( $client_id > 0 ) {
			$client_post = get_post( $client_id );

			if ( $client_post instanceof WP_Post && 'crm_client' === $client_post->post_type ) {
				$client_name = trim( wp_strip_all_tags( (string) get_the_title( $client_id ) ) );

				$advisor_user = get_userdata( (int) $client_post->post_author );
				if ( $advisor_user instanceof WP_User ) {
					$advisor_name = trim( wp_strip_all_tags( (string) $advisor_user->display_name ) );
				}
			}
		}

		$context['is_valid']     = true;
		$context['status']       = 200;
		$context['portfolio_id'] = $portfolio_id;
		$context['client_id']    = $client_id;
		$context['property_ids'] = $property_ids;
		$context['client_name']  = $client_name;
		$context['advisor_name'] = $advisor_name;
		$context['expires_at']   = max( 0, $expires_at );

		return $context;
	}
}

if ( ! function_exists( 'pera_theme_portfolio_token_get_request_context_by_token' ) ) {
	/**
	 * Resolve context for theme portfolio tokens while keeping /portfolio/{token}/ as the live route.
	 *
	 * @param string $token Token value from /portfolio/{token}/.
	 * @return array{is_valid:bool,status:int,client_id:int,client_name:string,advisor_name:string,property_ids:array<int>}
	 */
	function pera_theme_portfolio_token_get_request_context_by_token( string $token ): array {
		$context = array(
			'is_valid'    => false,
			'status'      => 404,
			'client_id'   => 0,
			'client_name' => '',
			'advisor_name'=> '',
			'property_ids'=> array(),
		);

		$token = preg_replace( '/[^A-Za-z0-9]/', '', $token );
		$token = is_string( $token ) ? $token : '';
		if ( '' === $token ) {
			return $context;
		}

		$client_id = pera_theme_portfolio_token_find_client_id( $token );
		if ( $client_id <= 0 ) {
			return $context;
		}

		$client_post = get_post( $client_id );
		if ( ! ( $client_post instanceof WP_Post ) || 'crm_client' !== $client_post->post_type ) {
			return $context;
		}

		$property_ids = pera_theme_portfolio_token_parse_property_ids( get_post_meta( $client_id, '_peracrm_theme_portfolio_property_ids', true ) );
		$client_name  = trim( wp_strip_all_tags( (string) get_the_title( $client_id ) ) );
		$advisor_name = '';
		$advisor_user = get_userdata( (int) $client_post->post_author );
		if ( $advisor_user instanceof WP_User ) {
			$advisor_name = trim( wp_strip_all_tags( (string) $advisor_user->display_name ) );
		}

		$context['is_valid']     = true;
		$context['status']       = 200;
		$context['client_id']    = $client_id;
		$context['client_name']  = $client_name;
		$context['advisor_name'] = $advisor_name;
		$context['property_ids'] = $property_ids;

		return $context;
	}
}

if ( ! function_exists( 'pera_portfolio_token_template_include' ) ) {
	/**
	 * Serve the dedicated template for token routes.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	function pera_portfolio_token_template_include( string $template ): string {
		if ( pera_theme_portfolio_token_is_request() ) {
			$theme_portfolio_template = trailingslashit( get_stylesheet_directory() ) . 'page-portfolio-theme-token.php';
			if ( file_exists( $theme_portfolio_template ) ) {
				return $theme_portfolio_template;
			}
		}

		if ( pera_portfolio_token_is_request() ) {
			$portfolio_template = trailingslashit( get_stylesheet_directory() ) . 'page-portfolio-token.php';
			if ( file_exists( $portfolio_template ) ) {
				return $portfolio_template;
			}
		}

		return $template;
	}
}
add_filter( 'template_include', 'pera_portfolio_token_template_include', 99 );

if ( ! function_exists( 'pera_portfolio_token_disable_canonical_redirect' ) ) {
	/**
	 * Disable canonical guessing redirects for token pages.
	 */
	function pera_portfolio_token_disable_canonical_redirect( $redirect_url, string $requested_url ) {
		if ( pera_portfolio_token_is_request() || pera_theme_portfolio_token_is_request() ) {
			return false;
		}

		return $redirect_url;
	}
}
add_filter( 'redirect_canonical', 'pera_portfolio_token_disable_canonical_redirect', 10, 2 );

if ( ! function_exists( 'pera_portfolio_token_wp_robots' ) ) {
	/**
	 * Prevent indexing token pages.
	 *
	 * @param array<string,mixed> $robots
	 * @return array<string,mixed>
	 */
	function pera_portfolio_token_wp_robots( array $robots ): array {
		if ( ! pera_portfolio_token_is_request() && ! pera_theme_portfolio_token_is_request() ) {
			return $robots;
		}

		$robots['noindex']  = true;
		$robots['nofollow'] = true;

		unset( $robots['index'], $robots['follow'] );

		return $robots;
	}
}
add_filter( 'wp_robots', 'pera_portfolio_token_wp_robots', 99 );

if ( ! function_exists( 'pera_portfolio_token_get_primary_term_name' ) ) {
	/**
	 * Resolve a display term name for a taxonomy on a property post.
	 *
	 * District prefers the deepest assigned child term when available.
	 */
	function pera_portfolio_token_get_primary_term_name( int $property_id, string $taxonomy ): string {
		if ( $property_id <= 0 || '' === $taxonomy ) {
			return '';
		}

		if ( 'district' === $taxonomy && function_exists( 'pera_get_deepest_term' ) ) {
			$deepest_term = pera_get_deepest_term( $property_id, $taxonomy );
			if ( $deepest_term instanceof WP_Term ) {
				return trim( (string) $deepest_term->name );
			}
		}

		$terms = get_the_terms( $property_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) || ! isset( $terms[0] ) ) {
			return '';
		}

		return trim( (string) $terms[0]->name );
	}
}

if ( ! function_exists( 'pera_portfolio_token_get_main_image_id' ) ) {
	/**
	 * Resolve the linked property main image ID.
	 */
	function pera_portfolio_token_get_main_image_id( int $property_id ): int {
		if ( $property_id <= 0 || ! function_exists( 'get_field' ) ) {
			return 0;
		}

		$main_image = get_field( 'main_image', $property_id );
		if ( is_array( $main_image ) ) {
			$image_id = isset( $main_image['ID'] ) ? (int) $main_image['ID'] : 0;
			return $image_id > 0 ? $image_id : 0;
		}

		if ( is_numeric( $main_image ) ) {
			$image_id = (int) $main_image;
			return $image_id > 0 ? $image_id : 0;
		}

		return 0;
	}
}

if ( ! function_exists( 'pera_portfolio_token_get_property_map_url' ) ) {
	/**
	 * Resolve a Google Maps URL from property map ACF value.
	 */
	function pera_portfolio_token_get_property_map_url( int $property_id ): string {
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

if ( ! function_exists( 'pera_portfolio_token_get_document_title' ) ) {
	/**
	 * Build scoped browser title for token pages.
	 */
	function pera_portfolio_token_get_document_title( string $title ): string {
		if ( ! pera_portfolio_token_is_request() && ! pera_theme_portfolio_token_is_request() ) {
			return $title;
		}

		$context = pera_portfolio_token_is_request()
			? pera_portfolio_token_get_request_context()
			: pera_theme_portfolio_token_get_request_context();
		$client  = isset( $context['client_name'] ) ? trim( (string) $context['client_name'] ) : '';
		$prefix  = '' !== $client ? $client : __( 'Portfolio', 'hello-elementor-child' );

		return sprintf( '%s - A custom portfolio | Pera Property', $prefix );
	}
}
add_filter( 'pre_get_document_title', 'pera_portfolio_token_get_document_title', 999 );

if ( ! function_exists( 'pera_portfolio_token_render_crm_offer_card' ) ) {
	/**
	 * Render token-route offer cards with the shared theme offers-card component.
	 *
	 * @param int   $property_id Property post ID.
	 * @param array $portfolio_row Portfolio row data keyed by property relation metadata.
	 */
	function pera_portfolio_token_render_crm_offer_card( int $property_id, array $portfolio_row = array() ): void {
		if ( $property_id <= 0 ) {
			return;
		}

		$offer_row = array(
			'type'          => trim( (string) ( $portfolio_row['unit_type'] ?? '' ) ),
			'floor'         => trim( (string) ( $portfolio_row['floor_number'] ?? '' ) ),
			'net_sqm'       => trim( (string) ( $portfolio_row['net_size'] ?? '' ) ),
			'gross_sqm'     => trim( (string) ( $portfolio_row['gross_size'] ?? '' ) ),
			'list_price'    => trim( (string) ( $portfolio_row['list_price'] ?? '' ) ),
			'cash_price'    => trim( (string) ( $portfolio_row['cash_price'] ?? '' ) ),
			'notes'         => trim( (string) ( $portfolio_row['notes'] ?? '' ) ),
			'floor_plan_id' => isset( $portfolio_row['floor_plan_attachment_id'] ) ? (int) $portfolio_row['floor_plan_attachment_id'] : 0,
		);

		$has_portfolio_offer_data = false;
		foreach ( array( 'type', 'floor', 'net_sqm', 'gross_sqm', 'list_price', 'cash_price', 'notes', 'floor_plan_id' ) as $offer_key ) {
			if ( 'floor_plan_id' === $offer_key ) {
				if ( (int) $offer_row[ $offer_key ] > 0 ) {
					$has_portfolio_offer_data = true;
					break;
				}
				continue;
			}

			if ( '' !== trim( (string) $offer_row[ $offer_key ] ) ) {
				$has_portfolio_offer_data = true;
				break;
			}
		}

		if ( ! $has_portfolio_offer_data && function_exists( 'pera_latest_offers_get_rows' ) ) {
			$latest_offer_rows = pera_latest_offers_get_rows( $property_id );
			if ( ! empty( $latest_offer_rows ) && is_array( $latest_offer_rows[0] ?? null ) ) {
				$offer_row = $latest_offer_rows[0];
			}
		}

		$card = function_exists( 'pera_latest_offers_card_view_model' )
			? pera_latest_offers_card_view_model( $property_id, $offer_row )
			: array();

		if ( function_exists( 'pera_latest_offers_render_card' ) ) {
			pera_latest_offers_render_card( $card );
		}
	}
}

if ( ! function_exists( 'pera_portfolio_token_rewrite_notice' ) ) {
	/**
	 * Admin reminder to flush rewrites after deploys.
	 */
	function pera_portfolio_token_rewrite_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'Portfolio token routes changed? Save Permalinks once (or run wp rewrite flush --hard) to refresh /portfolio/* rewrites.', 'hello-elementor-child' );
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'pera_portfolio_token_rewrite_notice' );

if ( ! function_exists( 'pera_portfolio_token_register_wp_cli' ) ) {
	/**
	 * Register WP-CLI helpers for portfolio creation.
	 */
	function pera_portfolio_token_register_wp_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		if ( class_exists( 'Pera_Portfolio_Token_CLI_Command' ) ) {
			return;
		}

		/**
		 * Manage token-based portfolios.
		 */
		class Pera_Portfolio_Token_CLI_Command {
			/**
			 * Create a portfolio token URL.
			 *
			 * ## OPTIONS
			 *
			 * --properties=<ids>
			 * : Comma-separated property IDs (order is preserved).
			 *
			 * [--client=<id>]
			 * : Optional CRM client post ID.
			 *
			 * [--expires=<time>]
			 * : Optional strtotime-compatible expiration, e.g. "+30 days".
			 *
			 * ## EXAMPLES
			 *
			 *     wp pera portfolio create --client=123 --properties=564,777,888 --expires="+30 days"
			 *
			 * @param array<int,string>          $args Positional args.
			 * @param array<string,string|int> $assoc_args Named args.
			 */
			public function create( array $args, array $assoc_args ): void {
				$properties_raw = isset( $assoc_args['properties'] ) ? (string) $assoc_args['properties'] : '';
				if ( '' === $properties_raw ) {
					WP_CLI::error( 'Missing required --properties argument.' );
				}

				$property_ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $properties_raw ) ) ) );
				$property_ids = array_values( array_filter( $property_ids ) );
				if ( empty( $property_ids ) ) {
					WP_CLI::error( 'No valid property IDs provided.' );
				}

				$client_id = isset( $assoc_args['client'] ) ? absint( (string) $assoc_args['client'] ) : 0;
				$expires   = isset( $assoc_args['expires'] ) ? trim( (string) $assoc_args['expires'] ) : '';

				$expires_at = 0;
				if ( '' !== $expires ) {
					$expires_at = strtotime( $expires );
					if ( false === $expires_at ) {
						WP_CLI::error( 'Could not parse --expires value. Use a strtotime-compatible format.' );
					}
					$expires_at = (int) $expires_at;
				}

				$result = pera_portfolio_token_create_portfolio( $property_ids, $client_id, $expires_at );
				if ( is_wp_error( $result ) ) {
					WP_CLI::error( $result->get_error_message() );
				}

				WP_CLI::success( 'Portfolio created.' );
				WP_CLI::line( 'Post ID: ' . (int) $result['post_id'] );
				WP_CLI::line( 'Token: ' . (string) $result['token'] );
				WP_CLI::line( 'URL: ' . (string) $result['url'] );
			}
		}

		WP_CLI::add_command( 'pera portfolio', 'Pera_Portfolio_Token_CLI_Command' );
	}
}
add_action( 'init', 'pera_portfolio_token_register_wp_cli', 30 );
