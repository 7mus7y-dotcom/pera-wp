<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$request_uri  = $_SERVER['REQUEST_URI'] ?? '';
$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
$request_path = is_string( $request_path ) ? $request_path : '';

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
if ( $request_path !== '' ) {
	$normalized_request_path = trailingslashit( $request_path );
	foreach ( $property_archive_prefixes as $prefix ) {
		if ( strpos( $normalized_request_path, $prefix ) === 0 ) {
			$is_property_archive_route = true;
			break;
		}
	}
}

$is_crm_route = function_exists( 'pera_is_crm_route' )
	? pera_is_crm_route()
	: ( is_string( $request_path ) && ( strpos( trailingslashit( $request_path ), '/crm/' ) === 0 ) );

$is_ajax     = defined( 'DOING_AJAX' ) && DOING_AJAX;
$ajax_action = $is_ajax && isset( $_REQUEST['action'] ) ? (string) wp_unslash( $_REQUEST['action'] ) : '';
$is_admin_ajax_path = $request_path !== '' && (
	$request_path === '/wp-admin/admin-ajax.php'
	|| substr( $request_path, -13 ) === 'admin-ajax.php'
);
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
$is_crm_ajax = $is_ajax && (
	strpos( $ajax_action, 'peracrm_' ) === 0
	|| strpos( $ajax_action, 'pera_crm_' ) === 0
	|| in_array( $ajax_action, array( 'peracrm_property_search', 'peracrm_create_portfolio_token' ), true )
);

$needs_property_archive_helpers = $is_property_archive_route || $is_property_ajax || $has_property_filter_qs;

$token_qv = function_exists( 'get_query_var' ) ? (string) get_query_var( 'portfolio_token' ) : '';
$is_portfolio_route = ( $token_qv !== '' )
	|| ( is_string( $request_path ) && ( strpos( trailingslashit( $request_path ), '/portfolio/' ) === 0 ) );

/**
 * Access control helpers shared by admin + front-end.
 */
require_once get_stylesheet_directory() . '/inc/access-control.php';

/**
 * Load taxonomy term meta (term excerpt + featured image).
 * Used by inc/seo-all.php for term meta descriptions + social images.
 */
require_once get_stylesheet_directory() . '/inc/taxonomy-meta.php';
/**
 * SEO helper functions used by templates.
 */
require_once get_stylesheet_directory() . '/inc/seo-helpers.php';

/**
 * Enforce district ancestors for property assignments.
 */
require_once get_stylesheet_directory() . '/inc/district-ancestors.php';
/**
 * Favourites (property)
 */
require_once get_stylesheet_directory() . '/inc/favourites.php';
if ( $needs_property_archive_helpers ) {
	require_once get_stylesheet_directory() . '/inc/property-pagination.php';
	require_once get_stylesheet_directory() . '/inc/property-archive-query.php';
}
require_once get_stylesheet_directory() . '/inc/property-card-helpers.php';
require_once get_stylesheet_directory() . '/inc/client-portal.php';

// admin-ajax.php requests are not /crm/* routes, but CRM AJAX handlers still need these modules loaded.
if ( $is_crm_route || $is_crm_ajax ) {
	require_once get_stylesheet_directory() . '/inc/crm-data.php';
	require_once get_stylesheet_directory() . '/inc/crm-router.php';
	require_once get_stylesheet_directory() . '/inc/crm-client-view.php';
}

require_once get_stylesheet_directory() . '/inc/disable-hello-parent-loads.php';

if ( $is_portfolio_route || $is_crm_route || $is_crm_ajax ) {
	require_once get_stylesheet_directory() . '/inc/portfolio-token.php';
}
