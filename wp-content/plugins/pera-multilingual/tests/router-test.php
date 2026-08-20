<?php
/** Focused standalone router tests: php tests/router-test.php */

define( 'ABSPATH', __DIR__ );
$GLOBALS['pera_ml_home'] = 'https://www.peraproperty.com/';
$GLOBALS['pera_ml_admin'] = false;
$GLOBALS['pera_ml_options'] = array( 'show_on_front' => 'page', 'page_on_front' => 55858, 'page_for_posts' => 58622 );

function add_action() {}
function add_filter() {}
function is_admin() { return $GLOBALS['pera_ml_admin']; }
function wp_unslash( $value ) { return $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '/' ) { return rtrim( $GLOBALS['pera_ml_home'], '/' ) . '/' . ltrim( $path, '/' ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function get_option( $name ) { return isset( $GLOBALS['pera_ml_options'][ $name ] ) ? $GLOBALS['pera_ml_options'][ $name ] : false; }

final class Pera_ML_Test_Registry {
	private $languages = array(
		'en' => array( 'code' => 'en', 'prefix' => '', 'enabled' => true ),
		'zh' => array( 'code' => 'zh', 'prefix' => 'zh', 'enabled' => true ),
		'ar' => array( 'code' => 'ar', 'prefix' => 'ar', 'enabled' => true ),
	);
	public function get( $code ) { return isset( $this->languages[ $code ] ) ? $this->languages[ $code ] : null; }
	public function all() { return $this->languages; }
	public function from_prefix( $prefix ) { return 'zh' === $prefix || 'ar' === $prefix ? $this->languages[ $prefix ] : null; }
}

function pera_ml_expect( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

require dirname( __DIR__ ) . '/includes/class-router.php';
$registry = new Pera_ML_Test_Registry();

$cases = array(
	array( 'https://www.peraproperty.com/foo/', 'zh', 'https://www.peraproperty.com/zh/foo/', 'English to Chinese' ),
	array( 'https://www.peraproperty.com/zh/foo/', 'ar', 'https://www.peraproperty.com/ar/foo/', 'Chinese to Arabic' ),
	array( 'https://www.peraproperty.com/ar/foo/', 'en', 'https://www.peraproperty.com/foo/', 'Arabic to English' ),
	array( 'https://www.peraproperty.com/zh/foo/', 'zh', 'https://www.peraproperty.com/zh/foo/', 'Chinese idempotence' ),
	array( 'https://www.peraproperty.com/foo/?beds=2', 'zh', 'https://www.peraproperty.com/zh/foo/?beds=2', 'query string' ),
	array( 'https://www.peraproperty.com/foo/#results', 'ar', 'https://www.peraproperty.com/ar/foo/#results', 'fragment' ),
	array( 'https://www.peraproperty.com/property/page/3/', 'zh', 'https://www.peraproperty.com/zh/property/page/3/', 'pagination' ),
	array( 'https://www.peraproperty.com/foo', 'zh', 'https://www.peraproperty.com/zh/foo', 'no trailing slash' ),
	array( 'https://www.peraproperty.com/', 'zh', 'https://www.peraproperty.com/zh/', 'homepage' ),
	array( 'https://external.example/foo/', 'zh', 'https://external.example/foo/', 'external URL' ),
);

array_push( $cases, array( 'https://www.peraproperty.com/wp-admin/admin-ajax.php?x=1#y', 'zh', 'https://www.peraproperty.com/wp-admin/admin-ajax.php?x=1#y', 'system URL helper safety' ) );

foreach ( $cases as $case ) {
	$router = new Pera_ML_Router( $registry );
	pera_ml_expect( $case[2], $router->url_for_language( $case[0], $case[1] ), $case[3] );
}

$GLOBALS['pera_ml_home'] = 'https://www.peraproperty.com/site/';
$router = new Pera_ML_Router( $registry );
pera_ml_expect( 'https://www.peraproperty.com/site/zh/foo/?x=1#map', $router->url_for_language( 'https://www.peraproperty.com/site/foo/?x=1#map', 'zh' ), 'subdirectory URL' );
pera_ml_expect( 'https://www.peraproperty.com/outside/foo/', $router->url_for_language( 'https://www.peraproperty.com/outside/foo/', 'zh' ), 'outside home path' );
$_SERVER['REQUEST_URI'] = '/outside/zh/foo/';
$router->detect_and_strip_prefix();
pera_ml_expect( '/outside/zh/foo/', $_SERVER['REQUEST_URI'], 'outside home path is not stripped' );
$_SERVER['REQUEST_URI'] = '/site/zh/foo/?q=hello';
$router->detect_and_strip_prefix();
pera_ml_expect( '/site/foo/?q=hello', $_SERVER['REQUEST_URI'], 'subdirectory strip preserves query' );
$wp = (object) array( 'query_vars' => array() );
$router->restore_public_uri( $wp );
pera_ml_expect( '/site/zh/foo/?q=hello', $_SERVER['REQUEST_URI'], 'public URI restoration' );

/*
 * This harness cannot run WP_Query, but it models the query vars handed to it at
 * the end of parse_request. In WordPress, page_id produces is_page/front-page
 * state, while an empty request containing pera_ml_lang produces is_home state.
 */
$GLOBALS['pera_ml_home'] = 'https://www.peraproperty.com/';
$route_cases = array(
	array( '/', array(), 'front', 'English front page' ),
	array( '/zh/', array(), 'front', 'Chinese front page' ),
	array( '/ar/', array(), 'front', 'Arabic front page' ),
	array( '/blog/', array( 'pagename' => 'blog' ), 'posts', 'English posts page' ),
	array( '/zh/blog/', array( 'pagename' => 'blog' ), 'posts', 'Chinese posts page' ),
	array( '/ar/blog/', array( 'pagename' => 'blog' ), 'posts', 'Arabic posts page' ),
);
foreach ( $route_cases as $case ) {
	$router = new Pera_ML_Router( $registry );
	$_SERVER['REQUEST_URI'] = $case[0];
	$router->detect_and_strip_prefix();
	$canonical_path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	$wp = (object) array( 'query_vars' => $case[1] );
	$router->restore_public_uri( $wp );
	if ( '/' === $canonical_path && 'en' === $router->current_language() ) {
		// Model WordPress's empty-query static-front-page conversion for plain /.
		$wp->query_vars['page_id'] = (int) get_option( 'page_on_front' );
	}
	$resolved = isset( $wp->query_vars['page_id'] ) && (int) get_option( 'page_on_front' ) === $wp->query_vars['page_id'] ? 'front' : ( isset( $wp->query_vars['pagename'] ) && 'blog' === $wp->query_vars['pagename'] ? 'posts' : 'home-index' );
	pera_ml_expect( $case[2], $resolved, $case[3] );
	pera_ml_expect( $case[0], $_SERVER['REQUEST_URI'], $case[3] . ' retains public URI' );
}

$GLOBALS['pera_ml_options']['page_on_front'] = 12345;
$router = new Pera_ML_Router( $registry ); $_SERVER['REQUEST_URI'] = '/zh/'; $router->detect_and_strip_prefix();
$wp = (object) array( 'query_vars' => array() ); $router->restore_public_uri( $wp );
pera_ml_expect( 12345, $wp->query_vars['page_id'], 'front page ID comes from WordPress configuration' );
pera_ml_expect( false, $router->prevent_prefix_loss( 'https://www.peraproperty.com/', 'https://www.peraproperty.com/zh/' ), 'language root canonical does not redirect or loop' );
pera_ml_expect( 'https://www.peraproperty.com/zh/', $router->url_for_language( 'https://www.peraproperty.com/', 'zh' ), 'language root canonical and hreflang URL' );
$GLOBALS['pera_ml_options']['page_on_front'] = 55858;

$router = new Pera_ML_Router( $registry ); $_SERVER['REQUEST_URI'] = '/zh/property/foo/'; $router->detect_and_strip_prefix();
$wp = (object) array( 'query_vars' => array( 'post_type' => 'property', 'name' => 'foo' ) ); $router->restore_public_uri( $wp );
pera_ml_expect( 'foo', $wp->query_vars['name'], 'inner prefixed route query is preserved' );
pera_ml_expect( 'zh', $wp->query_vars['pera_ml_lang'], 'inner prefixed route language marker is preserved' );

$GLOBALS['pera_ml_home'] = 'https://www.peraproperty.com/';
foreach ( array( '/zh/wp-admin/', '/zh/wp-admin/admin-ajax.php', '/zh/wp-json/wp/v2/posts', '/zh/wp-cron.php', '/zh/xmlrpc.php', '/zh/wp-content/app.css', '/zh/wp-includes/app.js', '/zh/robots.txt' ) as $endpoint ) {
	$router = new Pera_ML_Router( $registry ); $_SERVER['REQUEST_URI'] = $endpoint; unset( $_GET['rest_route'] );
	$router->detect_and_strip_prefix();
	pera_ml_expect( $endpoint, $_SERVER['REQUEST_URI'], "system endpoint {$endpoint}" );
}
$router = new Pera_ML_Router( $registry ); $_SERVER['REQUEST_URI'] = '/zh/?rest_route=/wp/v2/posts'; $_GET['rest_route'] = '/wp/v2/posts';
$router->detect_and_strip_prefix();
pera_ml_expect( '/zh/?rest_route=/wp/v2/posts', $_SERVER['REQUEST_URI'], 'REST query endpoint' ); unset( $_GET['rest_route'] );
$GLOBALS['pera_ml_admin'] = true; $router = new Pera_ML_Router( $registry ); $_SERVER['REQUEST_URI'] = '/zh/foo/';
$router->detect_and_strip_prefix(); pera_ml_expect( '/zh/foo/', $_SERVER['REQUEST_URI'], 'admin request context' ); $GLOBALS['pera_ml_admin'] = false;

$router = new Pera_ML_Router( $registry ); $_SERVER['REQUEST_URI'] = '/zh/property/foo'; $router->detect_and_strip_prefix();
pera_ml_expect( 'https://www.peraproperty.com/zh/property/foo/', $router->prevent_prefix_loss( 'https://www.peraproperty.com/property/foo/', 'https://www.peraproperty.com/zh/property/foo' ), 'canonical keeps prefix and trailing slash' );
pera_ml_expect( false, $router->prevent_prefix_loss( 'https://www.peraproperty.com/property/foo/', 'https://www.peraproperty.com/zh/property/foo/' ), 'canonical loop prevention' );
pera_ml_expect( false, $router->prevent_prefix_loss( 'https://external.example/property/foo/', 'https://www.peraproperty.com/zh/property/foo' ), 'external canonical rejected' );

echo "Pera ML router tests passed\n";
