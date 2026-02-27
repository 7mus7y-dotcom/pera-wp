<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! function_exists( 'pera_crm_should_load_integration' ) ) {
	/**
	 * Evaluate CRM bootstrap load gating in a side-effect free way.
	 *
	 * @param array<string,mixed> $context Request context.
	 */
	function pera_crm_should_load_integration( array $context ): bool {
		$is_wp_admin          = ! empty( $context['is_wp_admin'] );
		$is_crm_route         = ! empty( $context['is_crm_route'] );
		$is_crm_ajax          = ! empty( $context['is_crm_ajax'] );
		$is_allowed_admin_crm = ! empty( $context['is_allowed_admin_crm'] );

		return $is_crm_route
			|| $is_crm_ajax
			|| ( $is_wp_admin && $is_allowed_admin_crm );
	}
}
if ( ! function_exists( 'pera_crm_is_allowed_admin_screen' ) ) {
	/**
	 * Guard CRM admin includes to an explicit allowlist.
	 */
	function pera_crm_is_allowed_admin_screen(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';

		if ( '' === $page ) {
			return false;
		}

		if ( 'peracrm_diagnostics' === $page ) {
			return true;
		}

		return 0 === strpos( $page, 'peracrm_' );
	}
}

$request_uri  = $_SERVER['REQUEST_URI'] ?? '';
$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
$request_path = is_string( $request_path ) ? $request_path : '';

$normalized_request_path = $request_path !== '' ? trailingslashit( $request_path ) : '';
$is_ajax                 = defined( 'DOING_AJAX' ) && DOING_AJAX;
$ajax_action             = $is_ajax && isset( $_REQUEST['action'] ) ? (string) wp_unslash( $_REQUEST['action'] ) : '';
$is_admin_ajax_path      = $request_path !== '' && (
	$request_path === '/wp-admin/admin-ajax.php'
	|| substr( $request_path, -13 ) === 'admin-ajax.php'
);
$is_login_request        = $request_path === '/wp-login.php';
$is_wp_admin             = is_admin();
$is_frontend             = ! $is_wp_admin && ! $is_login_request;

$property_archive_prefixes = array(
	'/property/',
	'/district/',
	'/region/',
	'/property_type/',
	'/property_tags/',
	'/special/',
	'/favourites/',
	'/favorites/',
	'/client-portal/',
);

$is_property_archive_route = false;
if ( $normalized_request_path !== '' ) {
	foreach ( $property_archive_prefixes as $prefix ) {
		if ( strpos( $normalized_request_path, $prefix ) === 0 ) {
			$is_property_archive_route = true;
			break;
		}
	}
}

$property_archive_ajax_actions = array(
	'pera_filter_properties_v2',
);

$has_property_filter_qs = isset( $_GET['district'] )
	|| isset( $_GET['property_tags'] )
	|| isset( $_GET['property_type'] )
	|| isset( $_GET['min_price'] )
	|| isset( $_GET['max_price'] )
	|| isset( $_GET['v2_beds'] );

$is_property_ajax = $is_ajax
	&& $is_admin_ajax_path
	&& $ajax_action !== ''
	&& in_array( $ajax_action, $property_archive_ajax_actions, true );

$needs_property_archive_helpers = $is_property_archive_route || $is_property_ajax || $has_property_filter_qs;

$is_crm_route = function_exists( 'pera_is_crm_route' )
	? pera_is_crm_route()
	: ( $normalized_request_path !== '' && strpos( $normalized_request_path, '/crm/' ) === 0 );

$is_crm_ajax = $is_ajax && (
	strpos( $ajax_action, 'peracrm_' ) === 0
	|| strpos( $ajax_action, 'pera_crm_' ) === 0
	|| in_array( $ajax_action, array( 'peracrm_property_search', 'peracrm_create_portfolio_token' ), true )
);

$is_crm_capable_user = is_user_logged_in()
	&& function_exists( 'peracrm_user_can_access_crm' )
	&& (bool) peracrm_user_can_access_crm();

$load_crm_integration = pera_crm_should_load_integration(
	array(
		'is_wp_admin'          => $is_wp_admin,
		'is_crm_route'         => $is_crm_route,
		'is_crm_ajax'          => $is_crm_ajax,
		'is_crm_capable_user'  => $is_crm_capable_user,
		'is_allowed_admin_crm' => $is_wp_admin && pera_crm_is_allowed_admin_screen(),
	)
);

$token_qv = function_exists( 'get_query_var' ) ? (string) get_query_var( 'portfolio_token' ) : '';
$is_portfolio_route = ( $token_qv !== '' )
	|| ( $normalized_request_path !== '' && strpos( $normalized_request_path, '/portfolio/' ) === 0 );

require_once get_stylesheet_directory() . '/inc/bootstrap/always.php';

if ( $needs_property_archive_helpers ) {
	require_once get_stylesheet_directory() . '/inc/bootstrap/property-archive.php';
}

if ( $is_wp_admin ) {
	require_once get_stylesheet_directory() . '/inc/bootstrap/admin.php';
}

if ( $load_crm_integration ) {
	require_once get_stylesheet_directory() . '/inc/bootstrap/crm-gated.php';
}

if ( $is_frontend ) {
	require_once get_stylesheet_directory() . '/inc/bootstrap/frontend.php';
}

require_once get_stylesheet_directory() . '/inc/bootstrap/portfolio-token.php';
