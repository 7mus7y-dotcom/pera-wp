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
			return true;
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
		add_rewrite_rule( '^crm/new/?$', 'index.php?pera_crm=1&pera_crm_action=new', 'top' );
		add_rewrite_rule( '^crm/client/([0-9]+)/?$', 'index.php?pera_crm=1&pera_crm_view=client&pera_crm_client_id=$matches[1]&client_id=$matches[1]', 'top' );
		add_rewrite_rule( '^crm/clients/?$', 'index.php?pera_crm=1&pera_crm_view=leads&paged=1', 'top' );
		add_rewrite_rule( '^crm/clients/page/([0-9]+)/?$', 'index.php?pera_crm=1&pera_crm_view=leads&paged=$matches[1]', 'top' );
		add_rewrite_rule( '^crm/leads/?$', 'index.php?pera_crm=1&pera_crm_view=leads&paged=1', 'top' );
		add_rewrite_rule( '^crm/leads/page/([0-9]+)/?$', 'index.php?pera_crm=1&pera_crm_view=leads&paged=$matches[1]', 'top' );
		add_rewrite_rule( '^crm/tasks/?$', 'index.php?pera_crm=1&pera_crm_view=tasks', 'top' );
		add_rewrite_rule( '^crm/pipeline/?$', 'index.php?pera_crm=1&pera_crm_view=pipeline', 'top' );
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
		$vars[] = 'pera_crm_action';
		$vars[] = 'pera_crm_view';
		$vars[] = 'pera_crm_client_id';
		$vars[] = 'client_id';
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
		$phone = function_exists( 'peracrm_phone_canonical_from_source' )
			? peracrm_phone_canonical_from_source( $_POST, 'peracrm_phone_country', 'peracrm_phone_national', 'peracrm_phone' )
			: ( isset( $_POST['peracrm_phone'] ) ? preg_replace( '/[^0-9+]/', '', sanitize_text_field( wp_unslash( (string) $_POST['peracrm_phone'] ) ) ) : '' );

		if ( '' === $phone && isset( $_POST['phone'] ) ) {
			$phone = preg_replace( '/[^0-9+]/', '', sanitize_text_field( wp_unslash( (string) $_POST['phone'] ) ) );
		}

		$fields = array(
			'first_name'     => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['first_name'] ) ) : '',
			'last_name'      => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['last_name'] ) ) : '',
			'email'          => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '',
			'phone'          => $phone,
			'source'         => isset( $_POST['source'] ) ? sanitize_key( wp_unslash( (string) $_POST['source'] ) ) : '',
			'notes'          => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['notes'] ) ) : '',
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

if ( ! function_exists( 'pera_crm_get_client_view_url' ) ) {
	/**
	 * Resolve CRM client detail URL.
	 */
	function pera_crm_get_client_view_url( int $client_id, array $args = array() ): string {
		$client_id  = max( 0, $client_id );
		$client_url = home_url( '/crm/client/' . $client_id . '/' );

		if ( empty( $args ) ) {
			return $client_url;
		}

		return add_query_arg( $args, $client_url );
	}
}
if ( ! function_exists( 'pera_crm_set_flash_message' ) ) {
	/**
	 * Store one-time CRM notice for the current user.
	 */
	function pera_crm_set_flash_message( string $message, string $type = 'info' ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		set_transient(
			'pera_crm_flash_' . $user_id,
			array(
				'message' => sanitize_text_field( $message ),
				'type'    => sanitize_key( $type ),
			),
			2 * MINUTE_IN_SECONDS
		);
	}
}

if ( ! function_exists( 'pera_crm_handle_new_lead' ) ) {
	/**
	 * Handle front-end lead creation from /crm/new form submit.
	 *
	 * Audit findings:
	 * - Email is stored in CRM client post meta, canonical keys are _peracrm_email + crm_primary_email (+ normalized variants).
	 * - Advisor assignment is stored in client post meta using assigned_advisor_user_id and crm_assigned_advisor.
	 * - Source currently exists as post meta crm_source (no source taxonomy registration found), so this route writes crm_source.
	 */
	function pera_crm_handle_new_lead(): void {
		if ( ! pera_is_crm_route() ) {
			return;
		}

		if ( 'new' !== sanitize_key( (string) get_query_var( 'pera_crm_action', '' ) ) ) {
			return;
		}

		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/crm/new/' ) ) );
			exit;
		}

		$mu_can_access = function_exists( 'peracrm_user_can_access_crm' ) ? (bool) peracrm_user_can_access_crm() : true;

		if ( ! pera_crm_user_can_access() || ! $mu_can_access || ! current_user_can( 'edit_crm_clients' ) ) {
			wp_die( esc_html__( 'You are not allowed to create CRM leads.', 'hello-elementor-child' ), 'Forbidden', array( 'response' => 403 ) );
		}

		$nonce = isset( $_POST['pera_crm_create_lead_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['pera_crm_create_lead_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_create_lead' ) ) {
			wp_safe_redirect( pera_crm_build_create_lead_redirect_url( 'invalid_nonce' ) );
			exit;
		}

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$phone      = function_exists( 'peracrm_phone_canonical_from_source' )
			? peracrm_phone_canonical_from_source( $_POST, 'peracrm_phone_country', 'peracrm_phone_national', 'peracrm_phone' )
			: ( isset( $_POST['peracrm_phone'] ) ? preg_replace( '/[^0-9+]/', '', sanitize_text_field( wp_unslash( (string) $_POST['peracrm_phone'] ) ) ) : '' );

		if ( '' === $phone && isset( $_POST['phone'] ) ) {
			$phone = preg_replace( '/[^0-9+]/', '', sanitize_text_field( wp_unslash( (string) $_POST['phone'] ) ) );
		}

		$_POST['peracrm_phone'] = $phone;
		$_POST['phone']         = $phone;
		$source     = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( (string) $_POST['source'] ) ) : '';
		$notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['notes'] ) ) : '';

		$allowed_sources = array(
			'meta_ads',
			'instagram_dm',
			'whatsapp_dm',
			'website',
			'referral',
			'other',
		);

		if ( '' === $first_name || '' === $last_name || '' === $email || '' === $source ) {
			wp_safe_redirect( pera_crm_build_create_lead_redirect_url( 'missing_required' ) );
			exit;
		}

		if ( ! is_email( $email ) ) {
			wp_safe_redirect( pera_crm_build_create_lead_redirect_url( 'invalid_email' ) );
			exit;
		}

		if ( ! in_array( $source, $allowed_sources, true ) ) {
			wp_safe_redirect( pera_crm_build_create_lead_redirect_url( 'invalid_source' ) );
			exit;
		}

		$existing_client_id = 0;
		if ( function_exists( 'peracrm_find_existing_client_id_by_email' ) ) {
			$existing_client_id = (int) peracrm_find_existing_client_id_by_email( $email );
		}

		if ( $existing_client_id <= 0 ) {
			$existing = get_posts(
				array(
					'post_type'      => 'crm_client',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						'relation' => 'OR',
						array(
							'key'   => '_peracrm_email',
							'value' => $email,
						),
						array(
							'key'   => 'crm_primary_email',
							'value' => $email,
						),
					),
				)
			);

			if ( ! empty( $existing ) ) {
				$existing_client_id = (int) $existing[0];
			}
		}

		if ( $existing_client_id > 0 ) {
			pera_crm_set_flash_message( __( 'A lead with this email already exists.', 'hello-elementor-child' ), 'warning' );
			wp_safe_redirect( pera_crm_get_client_view_url( $existing_client_id, array( 'duplicate' => 1 ) ) );
			exit;
		}

		$lead_title = trim( $first_name . ' ' . $last_name );

		$current_user = get_current_user_id();
		$post_id      = wp_insert_post(
			array(
				'post_type'   => 'crm_client',
				'post_status' => 'publish',
				'post_title'  => '' !== $lead_title ? $lead_title : $email,
				'post_author' => $current_user,
			),
			true
		);

		if ( is_wp_error( $post_id ) || (int) $post_id <= 0 ) {
			wp_safe_redirect( pera_crm_build_create_lead_redirect_url( 'create_failed' ) );
			exit;
		}

		$post_id = (int) $post_id;
		update_post_meta( $post_id, 'crm_first_name', $first_name );
		update_post_meta( $post_id, 'crm_last_name', $last_name );
		update_post_meta( $post_id, 'crm_source', $source );

		if ( function_exists( 'peracrm_sync_client_contact_meta' ) ) {
			peracrm_sync_client_contact_meta( $post_id, $email, $phone );
		} else {
			update_post_meta( $post_id, '_peracrm_email', $email );
			if ( '' !== $phone ) {
				update_post_meta( $post_id, '_peracrm_phone', $phone );
			}
		}

		update_post_meta( $post_id, '_peracrm_owner_user_id', $current_user );
		update_post_meta( $post_id, 'assigned_advisor_user_id', $current_user );
		update_post_meta( $post_id, 'crm_assigned_advisor', $current_user );

		if ( function_exists( 'peracrm_party_upsert_status' ) ) {
			peracrm_party_upsert_status(
				$post_id,
				array(
					'lead_pipeline_stage'   => 'new_enquiry',
					'engagement_state'      => 'engaged',
					'disposition'           => 'none',
					'lead_stage_updated_at' => function_exists( 'peracrm_now_mysql' ) ? peracrm_now_mysql() : current_time( 'mysql' ),
				)
			);
		}

		if ( '' !== $notes ) {
			if ( function_exists( 'peracrm_note_add' ) ) {
				peracrm_note_add( $post_id, $current_user, $notes );
			} elseif ( function_exists( 'peracrm_notes_create' ) ) {
				peracrm_notes_create( $post_id, $current_user, $notes, 'internal' );
			} elseif ( function_exists( 'peracrm_log_event' ) ) {
				peracrm_log_event(
					$post_id,
					'note_added',
					array(
						'note' => $notes,
					)
				);
			}
		}

		$success_url = home_url( '/crm/client/' . $post_id . '/' );

		wp_safe_redirect( $success_url );
		exit;
	}
}
add_action( 'template_redirect', 'pera_crm_handle_new_lead' );

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
		$action = sanitize_key( (string) get_query_var( 'pera_crm_action', '' ) );
		$view   = sanitize_key( (string) get_query_var( 'pera_crm_view', 'overview' ) );

		if ( 'new' === $action ) {
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

		if ( 'pipeline' === $view ) {
			$pipeline_template = get_stylesheet_directory() . '/page-crm-pipeline.php';
			if ( file_exists( $pipeline_template ) ) {
				status_header( 200 );
				return $pipeline_template;
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

if ( ! function_exists( 'pera_crm_filter_client_document_title' ) ) {
	/**
	 * Set CRM client view title to the current client name.
	 */
	function pera_crm_filter_client_document_title( string $title ): string {
		if ( is_admin() || ! pera_is_crm_route() ) {
			return $title;
		}

		if ( 'client' !== sanitize_key( (string) get_query_var( 'pera_crm_view', '' ) ) ) {
			return $title;
		}

		$client_id = function_exists( 'pera_crm_client_view_get_client_id' )
			? pera_crm_client_view_get_client_id()
			: (int) get_query_var( 'pera_crm_client_id', 0 );

		if ( $client_id <= 0 || 'crm_client' !== get_post_type( $client_id ) ) {
			return $title;
		}

		$client_title = get_the_title( $client_id );

		return '' !== $client_title ? $client_title : $title;
	}
}
add_filter( 'pre_get_document_title', 'pera_crm_filter_client_document_title' );

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
		$css_version  = function_exists( 'pera_get_asset_version' ) ? pera_get_asset_version( $css_rel_path ) : ( file_exists( $css_abs_path ) ? (string) filemtime( $css_abs_path ) : wp_get_theme()->get( 'Version' ) );

		$crm_css_deps    = array( 'pera-main-css' );
		$slider_rel_path = '/css/slider.css';
		$slider_abs_path = get_stylesheet_directory() . $slider_rel_path;
		if ( file_exists( $slider_abs_path ) ) {
			wp_enqueue_style(
				'pera-slider-css',
				get_stylesheet_directory_uri() . $slider_rel_path,
				array( 'pera-main-css' ),
				(string) filemtime( $slider_abs_path )
			);
			$crm_css_deps[] = 'pera-slider-css';
		}

		wp_enqueue_style(
			'pera-crm-css',
			get_stylesheet_directory_uri() . $css_rel_path,
			$crm_css_deps,
			$css_version
		);

		$js_rel_path = '/js/crm.js';
		$js_abs_path = get_stylesheet_directory() . $js_rel_path;
		if ( file_exists( $js_abs_path ) ) {
			wp_enqueue_script(
				'pera-crm-js',
				get_stylesheet_directory_uri() . $js_rel_path,
				array(),
				function_exists( 'pera_get_asset_version' ) ? pera_get_asset_version( $js_rel_path ) : (string) filemtime( $js_abs_path ),
				true
			);

			wp_localize_script(
				'pera-crm-js',
				'peraCrmData',
				array(
					'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
					'propertySearchNonce' => wp_create_nonce( 'pera_crm_property_search' ),
				)
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'pera_crm_enqueue_assets', 40 );


if ( ! function_exists( 'pera_crm_add_body_class' ) ) {
	/**
	 * Add a CRM route body class for scoped styling.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	function pera_crm_add_body_class( array $classes ): array {
		if ( pera_is_crm_route() ) {
			$classes[] = 'crm-route';
		}

		return $classes;
	}
}
add_filter( 'body_class', 'pera_crm_add_body_class' );

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

if ( ! function_exists( 'pera_crm_rewrite_notice' ) ) {
	/**
	 * Remind admins where to flush rewrites after deploys that change CRM routes.
	 */
	function pera_crm_rewrite_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'options-permalink' !== $screen->id ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'CRM routes changed? Save Permalinks once (or run wp rewrite flush --hard) to refresh /crm/* rewrites. This is not auto-flushed per request.', 'hello-elementor-child' );
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'pera_crm_rewrite_notice' );

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
