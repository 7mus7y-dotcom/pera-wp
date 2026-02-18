<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$request_uri  = $_SERVER['REQUEST_URI'] ?? '';
$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );

$is_crm_route = function_exists( 'pera_is_crm_route' )
	? pera_is_crm_route()
	: ( is_string( $request_path ) && ( strpos( trailingslashit( $request_path ), '/crm/' ) === 0 ) );

$is_ajax     = defined( 'DOING_AJAX' ) && DOING_AJAX;
$ajax_action = $is_ajax && isset( $_REQUEST['action'] ) ? (string) wp_unslash( $_REQUEST['action'] ) : '';
$is_crm_ajax = $is_ajax && (
	strpos( $ajax_action, 'peracrm_' ) === 0
	|| strpos( $ajax_action, 'pera_crm_' ) === 0
	|| in_array( $ajax_action, array( 'peracrm_property_search', 'peracrm_create_portfolio_token' ), true )
);

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
require_once get_stylesheet_directory() . '/inc/property-pagination.php';
require_once get_stylesheet_directory() . '/inc/property-archive-query.php';
require_once get_stylesheet_directory() . '/inc/property-card-helpers.php';
require_once get_stylesheet_directory() . '/inc/client-portal.php';

// admin-ajax.php requests are not /crm/* routes, but CRM AJAX handlers still need these modules loaded.
if ( $is_crm_route || $is_crm_ajax ) {
	require_once get_stylesheet_directory() . '/inc/crm-data.php';
	require_once get_stylesheet_directory() . '/inc/crm-router.php';
	require_once get_stylesheet_directory() . '/inc/crm-client-view.php';
}

require_once get_stylesheet_directory() . '/inc/disable-hello-parent-loads.php';

if ( $is_portfolio_route ) {
	require_once get_stylesheet_directory() . '/inc/portfolio-token.php';
}
