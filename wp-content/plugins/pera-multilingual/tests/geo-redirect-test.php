<?php
/** Standalone request-level regression harness. Uses the real registry, router, content and SEO classes. */
define( 'ABSPATH', __DIR__ );
set_error_handler( function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) throw new ErrorException( $message, 0, $severity, $file, $line );
	return false;
} );
define( 'PERA_ML_PUBLIC_RUSSIAN_ENABLED', true );
define( 'PERA_ML_PUBLIC_TURKISH_ENABLED', false );
$mode = isset( $argv[1] ) ? $argv[1] : 'server';
if ( 'dns' === $mode ) define( 'PERA_ML_GEO_CRAWLER_MODE', 'dns' );
if ( 'undefined' !== $mode ) define( 'PERA_ML_GEO_REDIRECT_ENABLED', 'disabled' !== $mode );
define( 'PERA_ML_GEO_CACHE_READY', 'cache-not-ready' !== $mode );
define( 'PERA_ML_GEO_COUNTRY_SOURCE', 'cloudflare' === $mode ? 'cloudflare' : 'server' );
define( 'PERA_ML_GEO_TRUSTED_PROXY_CIDRS', array( '192.0.2.0/24', '2001:db8::/32' ) );
$GLOBALS['hooks'] = array(); $GLOBALS['filters'] = array(); $GLOBALS['context'] = array();
$GLOBALS['home'] = 'https://example.test/'; $GLOBALS['assertions'] = 0;
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][$name][] = array( $callback, $priority ); }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['filters'][$name][] = $callback; }
function apply_filters( $name, $value, ...$args ) { foreach ( $GLOBALS['filters'][$name] ?? array() as $callback ) $value = $callback( $value, ...$args ); return $value; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '/' ) { return rtrim( $GLOBALS['home'], '/' ) . '/' . ltrim( $path, '/' ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function is_admin() { return ! empty( $GLOBALS['context']['admin'] ); }
function wp_doing_ajax() { return ! empty( $GLOBALS['context']['ajax'] ); }
function wp_doing_cron() { return ! empty( $GLOBALS['context']['cron'] ); }
function is_feed() { return ! empty( $GLOBALS['context']['feed'] ); }
function is_robots() { return ! empty( $GLOBALS['context']['robots'] ); }
function is_trackback() { return ! empty( $GLOBALS['context']['trackback'] ); }
function is_embed() { return ! empty( $GLOBALS['context']['embed'] ); }
function is_preview() { return ! empty( $GLOBALS['context']['preview'] ); }
function is_404() { return ! empty( $GLOBALS['context']['404'] ); }
function is_singular( $types = null ) { return empty( $GLOBALS['context']['archive'] ) && empty( $GLOBALS['context']['search'] ); }
function is_archive() { return ! empty( $GLOBALS['context']['archive'] ); }
function is_search() { return ! empty( $GLOBALS['context']['search'] ); }
function is_home() { return false; }
function is_front_page() { return false; }
function is_ssl() { return true; }
function get_query_var( $key ) { return $GLOBALS['context'][$key] ?? ''; }
function wp_safe_redirect( $url, $status = 302, $by = '' ) { $GLOBALS['emitted_redirect'] = array( $url, $status ); return false; }
function nocache_headers() { $GLOBALS['no_cache'] = true; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value ); }
function remove_query_arg( $key, $url ) {
	return rtrim( preg_replace( '/([?&])' . preg_quote( $key, '/' ) . '=[^&#]*&?/', '$1', $url ), '?&' );
}
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'property', 'post_content' => 'English body' ); }
function get_post_type_object( $type ) { return (object) array( 'public' => true ); }
function get_the_ID() { return 123; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_attr__( $value, $domain ) { return $value; }
function esc_url( $value ) { return $value; }
function expect( $expected, $actual, $label ) {
	++$GLOBALS['assertions'];
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}
require dirname( __DIR__ ) . '/includes/class-language-registry.php';
require dirname( __DIR__ ) . '/includes/class-router.php';
require dirname( __DIR__ ) . '/includes/class-local-geoip.php';
require dirname( __DIR__ ) . '/includes/class-crawler-detection.php';
require dirname( __DIR__ ) . '/includes/class-geo-redirect.php';
require dirname( __DIR__ ) . '/includes/class-content.php';
require dirname( __DIR__ ) . '/includes/class-seo.php';
$registry = new Pera_ML_Language_Registry();
function request( $uri, $country = 'CN', $cookie = '', $audience = 'human', $context = array() ) {
	global $registry;
	$_GET = array(); parse_str( (string) parse_url( $uri, PHP_URL_QUERY ), $_GET );
	$_COOKIE = $cookie ? array( Pera_ML_Geo_Redirect::COOKIE => $cookie ) : array();
	$_SERVER = array( 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'GEOIP_COUNTRY_CODE' => $country, 'PERA_ML_VERIFIED_AUDIENCE' => $audience );
	$GLOBALS['context'] = $context; $GLOBALS['no_cache'] = false;
	$router = new Pera_ML_Router( $registry ); $router->detect_and_strip_prefix();
	$router->restore_public_uri( (object) array( 'query_vars' => array() ) );
	$geo = new Pera_ML_Geo_Redirect( $registry, $router ); $geo->prepare();
	return array( $geo, $router );
}
$active = ! in_array( $mode, array( 'disabled', 'undefined', 'cache-not-ready' ), true );
list( $geo, $router ) = request( '/ar/property/example/?beds=2' );
$geo->hooks();
expect( $active, isset( $GLOBALS['hooks']['template_redirect'] ), 'hooks registered only when operational' );
if ( ! $active ) {
	expect( '', $geo->destination(), 'feature disabled: no redirect' );
	expect( false, $GLOBALS['no_cache'], 'feature disabled: no cache side effects' );
	expect( 'ar', $router->current_language(), 'existing prefixed routing unchanged' );
	expect( '/de/property/example/?beds=2', $router->url_for_language( '/ar/property/example/?beds=2', 'de' ), 'existing URL switching unchanged' );
	expect( '/property/example/', $geo->switcher_url( '/property/example/', 'en' ), 'disabled switcher unchanged' );
} elseif ( 'cloudflare' === $mode ) {
	$_SERVER['HTTP_CF_IPCOUNTRY'] = 'CN'; $_SERVER['REMOTE_ADDR'] = '198.51.100.1';
	expect( '', $geo->country(), 'untrusted CF country rejected' );
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.1';
	expect( '', $geo->country(), 'forwarded peer cannot confer trust' );
	$_SERVER['REMOTE_ADDR'] = '192.0.2.7'; expect( 'CN', $geo->country(), 'trusted proxy country accepted' );
	$_SERVER['REMOTE_ADDR'] = '2001:db8::7'; expect( 'CN', $geo->country(), 'trusted IPv6 proxy accepted' );
	foreach ( array( 'XX', 'T1', 'ZZ', array( 'CN' ), 'CN,DE', '' ) as $bad ) { $_SERVER['HTTP_CF_IPCOUNTRY'] = $bad; expect( '', $geo->country(), 'invalid CF country rejected' ); }
} else {
	$cases = array(
		array( '/property/example/', 'CN', '/zh/property/example/', 'China English property' ),
		array( '/ar/property/example/', 'CN', '/zh/property/example/', 'China Arabic property' ),
		array( '/blog/example/', 'DE', '/de/blog/example/', 'Germany English article' ),
		array( '/de/about/', 'RU', '/ru/about/', 'Russia German page' ),
		array( '/zh/property/example/', 'CN', '', 'already correct language' ),
		array( '/property/page/3/', 'CN', '/zh/property/page/3/', 'archive pagination' ),
		array( '/ar/property/?beds=2&district=besiktas&utm_source=test&sort=price%2Basc', 'DE', '/de/property/?beds=2&district=besiktas&utm_source=test&sort=price%2Basc', 'filters preserved byte for byte' ),
		array( '/?s=sea%20view&post_type=property', 'CN', '/zh/?s=sea%20view&post_type=property', 'search query' ),
		array( '/de/about/', 'US', '/about/', 'other country English' ),
		array( '/de/about/', 'XX', '', 'unknown country no redirect' ),
		array( '/de/about/', 'QQ', '', 'non-ISO country no redirect' ),
		array( '/ar/about/', '', '', 'missing GeoIP no redirect' ),
	);
	foreach ( $cases as $case ) {
		list( $geo ) = request( $case[0], $case[1] );
		expect( $case[2] ? 'https://example.test' . $case[2] : '', $geo->destination(), $case[3] );
		expect( true, $GLOBALS['no_cache'], 'all eligible responses uncacheable' );
		if ( $case[2] ) { list( $next ) = request( $case[2], $case[1] ); expect( '', $next->destination(), 'follow destination: no loop' ); }
	}
	foreach ( array( 'HK' => 'zh', 'TW' => 'zh', 'MO' => 'zh', 'AT' => 'de', 'BY' => 'ru', 'SA' => 'ar', 'AE' => 'ar', 'QA' => 'ar', 'KW' => 'ar', 'BH' => 'ar', 'OM' => 'ar' ) as $country => $language ) {
		list( $geo ) = request( '/about/', $country ); expect( "https://example.test/$language/about/", $geo->destination(), "mapping $country" );
	}
	list( $geo ) = request( '/about/', 'CN', 'en' ); expect( '', $geo->destination(), 'returning English preference beats China' );
	list( $geo ) = request( '/ar/about/', 'CN', 'de' ); expect( 'https://example.test/de/about/', $geo->destination(), 'returning preference beats both URL and country' );
	list( $geo ) = request( '/about/?pera_ml_select=en', 'CN', 'zh' );
	expect( 'https://example.test/about/', $geo->destination(), 'explicit English beats stored Chinese and country' );
	expect( 'zh', $_COOKIE[Pera_ML_Geo_Redirect::COOKIE], 'preference never mutated by country detection' );
	$options = $geo->cookie_options();
	expect( true, $options['secure'] && $options['httponly'], 'cookie secure and HttpOnly' ); expect( 'Lax', $options['samesite'], 'SameSite Lax' );
	expect( true, abs( $options['expires'] - time() - 31536000 ) < 3, '12 month lifetime' ); expect( false, isset( $options['domain'] ), 'host-only cookie' );
	list( $geo ) = request( '/de/about/?pera_ml_select=de', 'CN', 'en' ); expect( 'https://example.test/de/about/', $geo->destination(), 'users can change preference freely' );
	list( $geo ) = request( '/about/?pera_ml_select[]=zh', 'CN' ); expect( 'https://example.test/zh/about/?pera_ml_select[]=zh', $geo->destination(), 'array preference ignored safely' );
	foreach ( array( 'bot', '', 'unknown' ) as $audience ) {
		list( $geo ) = request( '/de/about/', 'CN', 'zh', $audience ); expect( '', $geo->destination(), 'bots and unverified audience bypass even stored preference' );
	}
	list( $geo ) = request( '/about/', 'CN', '', '' ); $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0'; $_SERVER['HTTP_PERA_ML_VERIFIED_AUDIENCE'] = 'human'; $geo->prepare(); expect( '', $geo->destination(), 'UA and client audience header cannot establish human identity' );
	foreach ( array( '/sitemap_index.xml', '/wp-sitemap.xml', '/de/wp-sitemap-posts-property-1.xml', '/sitemap/', '/robots.txt', '/feed/', '/about/feed/', '/wp-json/wp/v2/posts', '/ar/wp-json/wp/v2/posts', '/wp-admin/', '/wp-login.php', '/?rest_route=/wp/v2/posts', '/?sitemap=posts', '/?feed=rss2' ) as $uri ) {
		list( $geo ) = request( $uri ); expect( '', $geo->destination(), "excluded $uri" );
	}
	foreach ( array( 'admin', 'ajax', 'cron', 'feed', 'robots', 'trackback', 'embed', 'preview', '404' ) as $excluded_context ) {
		list( $geo ) = request( '/about/', 'CN', '', 'human', array( $excluded_context => true ) ); expect( '', $geo->destination(), "excluded context $excluded_context" );
	}
	list( $geo ) = request( '/about/' ); $_SERVER['REQUEST_METHOD'] = 'POST'; $geo->prepare(); expect( '', $geo->destination(), 'POST excluded' );
	list( $geo, $router ) = request( '/property/missing-translation/' );
	$storage = new class { public function get( ...$args ) { return null; } };
	expect( 'https://example.test/zh/property/missing-translation/', $geo->destination(), 'missing translation stays same property' );
	$router->set_request_language( 'zh' ); $content = new Pera_ML_Content( $registry, $router, $storage );
	expect( 'English title', $content->title( 'English title', 123 ), 'actual missing translation falls back to English title' );
	expect( 'English body', $content->content( 'English body' ), 'actual missing translation falls back to English body' );
	list( $geo ) = request( '/ar/property/example' );
	expect( 'https://example.test/zh/property/example/', $geo->combine_redirect( 'https://example.test/ar/property/example/', 301 ), 'combine slash canonical and geo in one hop' );
	expect( 302, $geo->redirect_status( 301, '' ), 'combined redirect temporary' );
	expect( 'https://external.test/', $geo->combine_redirect( 'https://external.test/', 301 ), 'external redirect unchanged' ); expect( 301, $geo->redirect_status( 301, '' ), 'unrelated status unchanged' );
	list( $geo ) = request( '/property/?district[]=besiktas&beds=2' );
	expect( 'https://example.test/zh/property/?district=besiktas&beds=2', $geo->combine_redirect( 'https://example.test/property/?district=besiktas&beds=2', 301 ), 'existing archive filter normalization combined' );
	$GLOBALS['home'] = 'https://example.test/site/';
	list( $geo ) = request( '/site/ar/about/?ref=test' ); expect( 'https://example.test/site/zh/about/?ref=test', $geo->destination(), 'subdirectory no duplicate site path' ); expect( '/site/', $geo->cookie_options()['path'], 'cookie scoped to site' );
	list( $geo ) = request( '/outside/about/' ); expect( '', $geo->destination(), 'outside installation excluded' ); $GLOBALS['home'] = 'https://example.test/';
	list( $geo ) = request( '/about/' ); $GLOBALS['filters']['pera_ml_country_languages'][] = function( $mapping ) { $mapping['CN'] = 'de'; return $mapping; }; $geo->prepare(); expect( 'https://example.test/de/about/', $geo->destination(), 'configurable mapping' ); unset( $GLOBALS['filters']['pera_ml_country_languages'] );
	list( $geo, $router ) = request( '/ar/about/' ); $seo = new Pera_ML_SEO( $registry, $router, $storage );
	expect( 'https://example.test/ar/about/', $seo->canonical(), 'canonical unchanged by negotiation' );
	ob_start(); $seo->alternates(); $alternates = ob_get_clean(); expect( true, false !== strpos( $alternates, 'hreflang="zh" href="https://example.test/zh/about/"' ), 'hreflang still independently addressable' );
	list( $geo ) = request( '/property/example/' ); $geo->redirect();
	expect( array( 'https://example.test/zh/property/example/', 302 ), $GLOBALS['emitted_redirect'], 'actual redirect callback emits safe 302' );
	expect( '/about/?pera_ml_select=en', $geo->switcher_url( '/about/', 'en' ), 'manual switcher marker' );
	$GLOBALS['filters']['pera_ml_languages'][] = static function ( $languages ) { $languages['zh']['enabled'] = false; return $languages; };
	list( $geo ) = request( '/about/', 'DE' ); expect( 'https://example.test/de/about/', $geo->destination(), 'German negotiation works when Chinese disabled' );
	list( $geo ) = request( '/de/about/?pera_ml_select=de', 'DE' ); expect( 'https://example.test/de/about/', $geo->destination(), 'manual preference works when Chinese disabled' );
	unset( $GLOBALS['filters']['pera_ml_languages'] );
	if ( 'dns' === $mode ) {
		list( $geo ) = request( '/about/?pera_ml_select=en', 'CN', 'zh', '' );
		expect( 'https://example.test/about/', $geo->destination(), 'explicit manual English works without audience infrastructure' );
		list( $geo ) = request( '/ar/property/example/?beds=2', 'CN', 'en', '' );
		$_SERVER['REMOTE_ADDR'] = '81.2.69.160'; $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
		$_SERVER['HTTP_SEC_FETCH_USER'] = '?1'; $_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate'; $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
		$key = hash( 'sha256', $_SERVER['REMOTE_ADDR'] );
		$GLOBALS['options'][ Pera_ML_Crawler_Detection::CACHE ] = array( $key => array( 'result' => 'visitor', 'expires' => time() + 60 ) );
		$geo->prepare(); expect( 'https://example.test/property/example/?beds=2', $geo->destination(), 'stored English beats China with local crawler mode' );
		$_COOKIE = array(); $geo->prepare(); expect( 'https://example.test/zh/property/example/?beds=2', $geo->destination(), 'confirmed local browser can negotiate country' );
		$GLOBALS['options'][ Pera_ML_Crawler_Detection::CACHE ][ $key ]['result'] = 'bot';
		$geo->prepare(); expect( '', $geo->destination(), 'forward-verified crawler stays on deep URL despite browser UA' );
	}
}
foreach ( array( array( '192.0.2.7', '192.0.2.0/24', true ), array( '192.0.3.7', '192.0.2.0/24', false ), array( '2001:db8::1', '2001:db8::/32', true ), array( '2001:db9::1', '2001:db8::/32', false ), array( '127.0.0.1', '0.0.0.0/99', false ), array( '127.0.0.1', '0.0.0.0/no', false ), array( '127.0.0.1', '::/0', false ) ) as $cidr ) expect( $cidr[2], Pera_ML_Geo_Redirect::in_cidr( $cidr[0], $cidr[1] ), 'CIDR validation' );
echo "Pera ML geo redirect $mode: {$GLOBALS['assertions']} assertions passed\n";
