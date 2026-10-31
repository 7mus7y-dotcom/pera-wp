<?php
// Test router only. Never expose a diagnostic/controller through production PHP.
if ( 'cli-server' !== PHP_SAPI || ! in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ), true ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', dirname( __DIR__, 5 ) . '/' );
define( 'PERA_ML_PUBLIC_RUSSIAN_ENABLED', true );
define( 'PERA_ML_PUBLIC_TURKISH_ENABLED', false );
define( 'PERA_ML_GEO_REDIRECT_ENABLED', 'on' === ( $_GET['state'] ?? '' ) );
define( 'PERA_ML_GEO_CACHE_READY', 'no' !== ( $_GET['ready'] ?? '' ) );
define( 'PERA_ML_GEO_COUNTRY_SOURCE', 'server' );
$actions = array(); $filters = array();
function add_action( $name, $cb, $priority = 10, $args = 1 ) { $GLOBALS['actions'][$name][$priority][] = $cb; }
function add_filter( $name, $cb, $priority = 10, $args = 1 ) { $GLOBALS['filters'][$name][$priority][] = $cb; }
function do_action( $name ) { $items = $GLOBALS['actions'][$name] ?? array(); ksort( $items ); foreach ( $items as $callbacks ) foreach ( $callbacks as $cb ) $cb(); }
function apply_filters( $name, $value, ...$args ) { $items = $GLOBALS['filters'][$name] ?? array(); ksort( $items ); foreach ( $items as $callbacks ) foreach ( $callbacks as $cb ) $value = $cb( $value, ...$args ); return $value; }
function get_option( $key, $default = false ) { return $default; }
function sanitize_key( $value ) { return strtolower( $value ); }
function wp_unslash( $value ) { return $value; }
function wp_parse_url( $value, $part = -1 ) { return parse_url( $value, $part ); }
function home_url( $path = '/' ) { return 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim( $path, '/' ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function is_feed() { return false; }
function is_robots() { return '/robots.txt' === parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ); }
function is_trackback() { return false; }
function is_embed() { return false; }
function is_preview() { return false; }
function is_404() { return false; }
function is_ssl() { return false; }
function get_query_var( $name ) { return ''; }
function is_front_page() { return '/' === $GLOBALS['canonical_path']; }
function is_home() { return false; }
function is_page_template( $name ) { return false; }
function is_post_type_archive( $type ) { return 'property' === $type && '/property/' === $GLOBALS['canonical_path']; }
function is_tax( $type ) { return 'district' === $type && 0 === strpos( $GLOBALS['canonical_path'], '/district/' ); }
function is_singular( $types = null ) {
    $path = $GLOBALS['canonical_path'];
    $type = 0 === strpos( $path, '/property/example' ) ? 'property' : ( 0 === strpos( $path, '/blog/' ) ? 'post' : ( '/about/' === $path ? 'page' : '' ) );
    return $type && ( null === $types || in_array( $type, (array) $types, true ) );
}
function is_archive() { return is_post_type_archive( 'property' ) || is_tax( 'district' ); }
function is_search() { return '/search/' === $GLOBALS['canonical_path']; }
function is_user_logged_in() { return isset( $_GET['loggedin'] ); }
function pera_property_archive_is_filtered_request() { return isset( $_GET['filtered'] ); }
function wp_get_nocache_headers() { return array( 'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0' ); }
function nocache_headers() { foreach ( wp_get_nocache_headers() as $key => $value ) header( "$key: $value", true ); }
function wp_safe_redirect( $url, $status = 302, $by = '' ) { header( 'Location: ' . apply_filters( 'wp_redirect', $url, $status ), true, apply_filters( 'wp_redirect_status', $status, $url ) ); return true; }
function remove_query_arg( $key, $url ) { return $url; }
$plugin = dirname( __DIR__, 2 );
require $plugin . '/includes/class-language-registry.php';
require $plugin . '/includes/class-router.php';
require $plugin . '/includes/class-geo-redirect.php';
// Load the ACTUAL route-specific theme caching callbacks, not a copy of their policy.
require dirname( $plugin, 2 ) . '/themes/hello-elementor-child/inc/modules/performance.php';
$registry = new Pera_ML_Language_Registry(); $router = new Pera_ML_Router( $registry );
$router->detect_and_strip_prefix(); $GLOBALS['canonical_path'] = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$router->restore_public_uri( (object) array( 'query_vars' => array() ) );
$_SERVER['GEOIP_COUNTRY_CODE'] = $_GET['country'] ?? 'US';
$_SERVER['PERA_ML_VERIFIED_AUDIENCE'] = $_GET['audience'] ?? 'human';
$geo = new Pera_ML_Geo_Redirect( $registry, $router ); $geo->hooks();
do_action( 'send_headers' );
do_action( 'template_redirect' );
if ( isset( $_GET['late'] ) ) { header( 'Cache-Control: public, max-age=7777', true ); header( 'Expires: Wed, 11 Jan 2034 05:00:00 GMT', true ); }
header( 'Content-Type: text/html' );
echo '<!doctype html><title>Header integration fixture</title>';
