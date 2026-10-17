<?php
/** Standalone regression coverage: php tests/property-archive-seo-preview-test.php */
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}
define( 'ABSPATH', __DIR__ );
$state = array();
$cache_calls = 0;
$hooks = array();
function is_admin() { return $GLOBALS['state']['admin_screen']; }
function wp_doing_ajax() { return $GLOBALS['state']['ajax']; }
function is_post_type_archive( $type ) { return 'property' === $type && $GLOBALS['state']['archive']; }
function is_tax() { return $GLOBALS['state']['tax']; }
function is_search() { return $GLOBALS['state']['search']; }
function is_paged() { return $GLOBALS['state']['paged']; }
function is_user_logged_in() { return $GLOBALS['state']['logged_in']; }
function current_user_can( $capability ) { return 'manage_options' === $capability && $GLOBALS['state']['manage_options']; }
function nocache_headers() { $GLOBALS['cache_calls']++; }
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][ $hook ] = array( $callback, $priority ); }
function preview_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
require dirname( __DIR__ ) . '/inc/modules/property-archive-settings.php';
$defaults = array( 'admin_screen' => false, 'ajax' => false, 'archive' => true, 'tax' => false, 'search' => false, 'paged' => false, 'logged_in' => true, 'manage_options' => true );
$cases = array(
	'admin must opt in' => array( array(), array(), false ),
	'admin explicit preview' => array( array( 'seo_archive_preview' => '1' ), array(), true ),
	'anonymous preview denied' => array( array( 'seo_archive_preview' => '1' ), array( 'logged_in' => false, 'manage_options' => false ), false ),
	'logged-out capability denied' => array( array( 'seo_archive_preview' => '1' ), array( 'logged_in' => false ), false ),
	'employee without manage_options denied' => array( array( 'seo_archive_preview' => '1' ), array( 'manage_options' => false ), false ),
	'zero flag denied' => array( array( 'seo_archive_preview' => '0' ), array(), false ),
	'array flag denied' => array( array( 'seo_archive_preview' => array( '1' ) ), array(), false ),
	'taxonomy denied' => array( array( 'seo_archive_preview' => '1' ), array( 'tax' => true ), false ),
	'other page denied' => array( array( 'seo_archive_preview' => '1' ), array( 'archive' => false ), false ),
	'paged archive denied' => array( array( 'seo_archive_preview' => '1' ), array( 'paged' => true ), false ),
	'search denied' => array( array( 'seo_archive_preview' => '1' ), array( 'search' => true ), false ),
	'admin screen denied' => array( array( 'seo_archive_preview' => '1' ), array( 'admin_screen' => true ), false ),
	'AJAX denied' => array( array( 'seo_archive_preview' => '1' ), array( 'ajax' => true ), false ),
	'filter denied' => array( array( 'seo_archive_preview' => '1', 'district' => 'besiktas' ), array(), false ),
	'query pagination denied' => array( array( 'seo_archive_preview' => '1', 'paged' => '2' ), array(), false ),
);
foreach ( $cases as $label => $case ) {
	list( $_GET, $overrides, $expected ) = $case;
	$state = array_merge( $defaults, $overrides );
	$original_query = $_GET;
	preview_expect( $expected === pera_property_archive_seo_preview_enabled(), $label );
	preview_expect( $_GET === $original_query, $label . ' leaves the request intact' );
}
// Cache protection must be sent before template output, and only for an authorised preview.
preview_expect( 0 === $hooks['template_redirect'][1], 'cache protection runs early' );
$_GET = array( 'seo_archive_preview' => '1' );
$state = array_merge( $defaults, array( 'manage_options' => false ) );
$hooks['template_redirect'][0]();
preview_expect( 0 === $cache_calls && ! defined( 'DONOTCACHEPAGE' ), 'public request receives no preview cache mutation' );
preview_expect( pera_property_archive_seo_preview_is_main_request(), 'public preview marker still uses normal archive presentation' );
$state = $defaults;
$hooks['template_redirect'][0]();
preview_expect( 1 === $cache_calls && DONOTCACHEPAGE, 'authorised preview sends no-cache headers and cache opt-out' );
echo "Property archive SEO preview gate tests passed\n";
