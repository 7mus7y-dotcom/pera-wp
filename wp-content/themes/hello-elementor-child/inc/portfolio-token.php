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
	 * @return array{is_request:bool,is_valid:bool,status:int,portfolio_id:int,property_ids:array<int>,client_name:string,expires_at:int}
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
			'property_ids' => array(),
			'client_name'  => '',
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

		$client_id   = (int) get_post_meta( $portfolio_id, '_portfolio_client_id', true );
		$client_name = '';

		if ( $client_id > 0 && get_post_type( $client_id ) === 'crm_client' ) {
			$client_name = trim( wp_strip_all_tags( (string) get_the_title( $client_id ) ) );
		}

		$context['is_valid']     = true;
		$context['status']       = 200;
		$context['portfolio_id'] = $portfolio_id;
		$context['property_ids'] = $property_ids;
		$context['client_name']  = $client_name;
		$context['expires_at']   = max( 0, $expires_at );

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
		if ( ! pera_portfolio_token_is_request() ) {
			return $template;
		}

		$portfolio_template = trailingslashit( get_stylesheet_directory() ) . 'page-portfolio-token.php';
		if ( file_exists( $portfolio_template ) ) {
			return $portfolio_template;
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
		if ( pera_portfolio_token_is_request() ) {
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
		if ( ! pera_portfolio_token_is_request() ) {
			return $robots;
		}

		$robots['noindex']  = true;
		$robots['nofollow'] = true;

		unset( $robots['index'], $robots['follow'] );

		return $robots;
	}
}
add_filter( 'wp_robots', 'pera_portfolio_token_wp_robots', 99 );

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
