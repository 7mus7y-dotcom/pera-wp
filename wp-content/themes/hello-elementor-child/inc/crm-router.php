<?php
/**
 * CRM front-end routing and access control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_crm_user_can_access' ) ) {
	/**
	 * Check whether a user can access front-end CRM pages.
	 */
	function pera_crm_user_can_access( int $user_id = 0 ): bool {
		if ( function_exists( 'peracrm_user_can_access_crm' ) ) {
			return (bool) peracrm_user_can_access_crm( $user_id );
		}

		$user = $user_id > 0 ? get_user_by( 'id', $user_id ) : wp_get_current_user();

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		$allowed_roles = array( 'employee', 'manager', 'administrator' );
		$user_roles    = (array) $user->roles;

		return (bool) array_intersect( $allowed_roles, $user_roles );
	}
}

if ( ! function_exists( 'pera_crm_register_route' ) ) {
	/**
	 * Register rewrite rules for /crm/*.
	 */
	function pera_crm_register_route(): void {
		add_rewrite_rule( '^crm/?$', 'index.php?pera_crm=1', 'top' );
		add_rewrite_rule( '^crm/new/?$', 'index.php?pera_crm=1&pera_crm_view=new', 'top' );
		add_rewrite_rule( '^crm/client/([0-9]+)/?$', 'index.php?pera_crm=1&pera_crm_view=client&pera_crm_client_id=$matches[1]', 'top' );
		add_rewrite_rule( '^crm/leads/?$', 'index.php?pera_crm=1&pera_crm_view=leads&paged=1', 'top' );
		add_rewrite_rule( '^crm/leads/page/([0-9]+)/?$', 'index.php?pera_crm=1&pera_crm_view=leads&paged=$matches[1]', 'top' );
	}
}
add_action( 'init', 'pera_crm_register_route' );

if ( ! function_exists( 'pera_is_crm_route' ) ) {
	/**
	 * Whether current request resolved to the CRM virtual route.
	 */
	function pera_is_crm_route(): bool {
		return '1' === (string) get_query_var( 'pera_crm' );
	}
}

if ( ! function_exists( 'pera_crm_register_query_var' ) ) {
	/**
	 * Register CRM virtual query var.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	function pera_crm_register_query_var( array $vars ): array {
		$vars[] = 'pera_crm';
		$vars[] = 'pera_crm_view';
		$vars[] = 'pera_crm_client_id';
		$vars[] = 'crm_error';
		$vars[] = 'crm_notice';
		return $vars;
	}
}
add_filter( 'query_vars', 'pera_crm_register_query_var' );

if ( ! function_exists( 'pera_crm_build_create_lead_redirect_url' ) ) {
	/**
	 * Build /crm/new redirect URL while preserving user-entered fields.
	 */
	function pera_crm_build_create_lead_redirect_url( string $error_code ): string {
		$fields = array(
			'lead_title'     => isset( $_POST['lead_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lead_title'] ) ) : '',
			'email'          => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '',
			'phone'          => isset( $_POST['phone'] ) ? preg_replace( '/[^0-9+\-\s()]/', '', wp_unslash( (string) $_POST['phone'] ) ) : '',
			'pipeline_stage' => isset( $_POST['pipeline_stage'] ) ? sanitize_key( wp_unslash( (string) $_POST['pipeline_stage'] ) ) : '',
		);

		$args = array(
			'crm_error' => $error_code,
		) + array_filter(
			$fields,
			static function ( $value ): bool {
				return '' !== (string) $value;
			}
		);

		return add_query_arg( $args, home_url( '/crm/new/' ) );
	}
}

if ( ! function_exists( 'pera_crm_handle_front_create_lead' ) ) {
	/**
	 * Handle front-end lead creation from /crm/new form submit.
	 */
	function pera_crm_handle_front_create_lead(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/crm/new/' ) ) );
			exit;
		}

		if ( ! pera_crm_user_can_access() || ! current_user_can( 'edit_crm_clients' ) ) {
			wp_die( esc_html__( 'You are not allowed to create CRM leads.', 'hello-elementor-child' ), 'Forbidden', array( 'response' => 403 ) );
		}

		check_admin_referer( 'pera_crm_create_lead', 'pera_crm_create_lead_nonce' );

		$lead_title     = isset( $_POST['lead_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lead_title'] ) ) : '';
		$email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$phone          = isset( $_POST['phone'] ) ? preg_replace( '/[^0-9+\-\s()]/', '', wp_unslash( (string) $_POST['phone'] ) ) : '';
		$pipeline_stage = isset( $_POST['pipeline_stage'] ) ? sanitize_key( wp_unslash( (string) $_POST['pipeline_stage'] ) ) : 'new_enquiry';

		if ( '' === $lead_title ) {
			wp_safe_redirect( pera_crm_build_create_lead_redirect_url( 'missing_title' ) );
			exit;
		}

		if ( '' !== $email && ! is_email( $email ) ) {
			wp_safe_redirect( pera_crm_build_create_lead_redirect_url( 'invalid_email' ) );
			exit;
		}

		$stages = function_exists( 'pera_crm_get_pipeline_stages' ) ? pera_crm_get_pipeline_stages() : array();
		if ( '' === $pipeline_stage || ! isset( $stages[ $pipeline_stage ] ) ) {
			$pipeline_stage = 'new_enquiry';
		}

		$current_user = get_current_user_id();
		$post_id      = wp_insert_post(
			array(
				'post_type'   => 'crm_client',
				'post_status' => 'publish',
				'post_title'  => $lead_title,
				'post_author' => $current_user,
			),
			true
		);

		if ( is_wp_error( $post_id ) || (int) $post_id <= 0 ) {
			wp_safe_redirect( pera_crm_build_create_lead_redirect_url( 'create_failed' ) );
			exit;
		}

		$post_id = (int) $post_id;

		if ( '' !== $email ) {
			update_post_meta( $post_id, '_peracrm_email', $email );
		}

		if ( '' !== $phone ) {
			update_post_meta( $post_id, '_peracrm_phone', $phone );
		}

		update_post_meta( $post_id, '_peracrm_owner_user_id', $current_user );
		update_post_meta( $post_id, 'assigned_advisor_user_id', $current_user );
		update_post_meta( $post_id, 'crm_assigned_advisor', $current_user );

		if ( function_exists( 'peracrm_party_upsert_status' ) ) {
			peracrm_party_upsert_status(
				$post_id,
				array(
					'lead_pipeline_stage'   => $pipeline_stage,
					'engagement_state'      => 'engaged',
					'disposition'           => 'none',
					'lead_stage_updated_at' => function_exists( 'peracrm_now_mysql' ) ? peracrm_now_mysql() : current_time( 'mysql' ),
				)
			);
		}

		wp_safe_redirect( add_query_arg( 'crm_notice', 'created', home_url( '/crm/client/' . $post_id . '/' ) ) );
		exit;
	}
}
add_action( 'admin_post_peracrm_front_create_lead', 'pera_crm_handle_front_create_lead' );

if ( ! function_exists( 'pera_crm_gate_or_redirect' ) ) {
	/**
	 * Enforce authentication and role-based access for CRM.
	 */
	function pera_crm_gate_or_redirect(): void {
		if ( ! is_user_logged_in() ) {
			$requested_url = is_ssl() ? 'https://' : 'http://';
			$requested_url .= isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
			$requested_url .= isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

			wp_safe_redirect( wp_login_url( $requested_url ) );
			exit;
		}

		if ( ! pera_crm_user_can_access() ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'hello-elementor-child' ), 'Forbidden', array( 'response' => 403 ) );
		}
	}
}

if ( ! function_exists( 'pera_crm_maybe_load_template' ) ) {
	/**
	 * Load CRM template for /crm/ route.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	function pera_crm_maybe_load_template( string $template ): string {
		if ( is_admin() ) {
			return $template;
		}

		if ( ! pera_is_crm_route() ) {
			return $template;
		}

		pera_crm_gate_or_redirect();
		$view = sanitize_key( (string) get_query_var( 'pera_crm_view', 'overview' ) );

		if ( 'new' === $view ) {
			$new_template = get_stylesheet_directory() . '/page-crm-new.php';
			if ( file_exists( $new_template ) ) {
				status_header( 200 );
				return $new_template;
			}
		}

		if ( 'client' === $view ) {
			$client_template = get_stylesheet_directory() . '/page-crm-client.php';
			if ( file_exists( $client_template ) ) {
				status_header( 200 );
				return $client_template;
			}
		}

		$crm_template = get_stylesheet_directory() . '/page-crm.php';
		if ( file_exists( $crm_template ) ) {
			status_header( 200 );
			return $crm_template;
		}

		return $template;
	}
}
add_filter( 'template_include', 'pera_crm_maybe_load_template', 30 );

if ( ! function_exists( 'pera_crm_enqueue_assets' ) ) {
	/**
	 * Enqueue CRM-only assets.
	 */
	function pera_crm_enqueue_assets(): void {
		if ( ! pera_is_crm_route() ) {
			return;
		}

		$css_rel_path = '/css/crm.css';
		$css_abs_path = get_stylesheet_directory() . $css_rel_path;
		$css_version  = file_exists( $css_abs_path ) ? (string) filemtime( $css_abs_path ) : wp_get_theme()->get( 'Version' );

		wp_enqueue_style(
			'pera-crm-css',
			get_stylesheet_directory_uri() . $css_rel_path,
			array( 'pera-main-css' ),
			$css_version
		);

		$js_rel_path = '/js/crm.js';
		$js_abs_path = get_stylesheet_directory() . $js_rel_path;
		if ( file_exists( $js_abs_path ) ) {
			wp_enqueue_script(
				'pera-crm-js',
				get_stylesheet_directory_uri() . $js_rel_path,
				array(),
				(string) filemtime( $js_abs_path ),
				true
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'pera_crm_enqueue_assets', 40 );

if ( ! function_exists( 'pera_crm_flush_rewrite_on_activation' ) ) {
	/**
	 * Flush rewrites once when the theme is activated.
	 */
	function pera_crm_flush_rewrite_on_activation(): void {
		pera_crm_register_route();
		flush_rewrite_rules();
	}
}
add_action( 'after_switch_theme', 'pera_crm_flush_rewrite_on_activation' );

if ( ! function_exists( 'pera_crm_add_header_nav_item' ) ) {
	/**
	 * Add CRM menu item for authorised users in main menu.
	 *
	 * @param string   $items Menu HTML.
	 * @param stdClass $args  Menu args.
	 * @return string
	 */
	function pera_crm_add_header_nav_item( string $items, $args ): string {
		if ( empty( $args->theme_location ) || 'main_menu_v1' !== $args->theme_location ) {
			return $items;
		}

		if ( ! is_user_logged_in() || ! pera_crm_user_can_access() ) {
			return $items;
		}

		$items .= sprintf(
			'<li class="menu-item menu-item-crm"><a href="%s">%s</a></li>',
			esc_url( home_url( '/crm/' ) ),
			esc_html__( 'CRM', 'hello-elementor-child' )
		);

		return $items;
	}
}
add_filter( 'wp_nav_menu_items', 'pera_crm_add_header_nav_item', 20, 2 );
