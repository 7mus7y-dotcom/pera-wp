<?php
/** Run with PHP. WordPress stubs; namespaced header spies avoid real CLI headers. */
namespace PeraPreviewTest;

function check( $condition, $message ) {
    if ( ! $condition ) {
        throw new \RuntimeException( $message );
    }
}
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function current_user_can( $capability ) {
    check( 'manage_options' === $capability, 'administrator capability' );
    return $GLOBALS['admin'];
}
function is_singular( $type ) {
    check( 'property' === $type, 'property scope' );
    return $GLOBALS['property'];
}
function add_action( $hook, $callback, $priority ) {
    $GLOBALS['hooks'][] = array( $hook, $callback, $priority );
}
function headers_sent() { return $GLOBALS['sent']; }
function nocache_headers() { $GLOBALS['nocache']++; }
function header( $value, $replace ) {
    check( true === $replace, 'replace cache headers' );
    $GLOBALS['headers'][] = $value;
}

$root = dirname( __DIR__, 3 );
$plugin = file_get_contents( $root . '/mu-plugins/pera-property-layout-preview.php' );
// Isolate headers and WP functions without altering the code under test.
$load = static function () use ( $plugin ) {
    eval( 'namespace PeraPreviewTest;' . substr( $plugin, 5 ) );
};
define( 'ABSPATH', __DIR__ );
if ( isset( $argv[1] ) ) {
    $_GET = 'normal' === $argv[1] ? array() : array( 'layout_preview' => array( '1' ) );
    if ( 'predefined' === $argv[1] ) {
        define( 'DONOTCACHEPAGE', false );
    }
    $load();
    check( 'normal' === $argv[1] ? ! defined( 'DONOTCACHEPAGE' ) :
        ( 'predefined' === $argv[1] ? false === DONOTCACHEPAGE : true === DONOTCACHEPAGE ),
        'MU-load constant before hooks/auth/query' );
    exit;
}
foreach ( array( 'normal', 'query', 'predefined' ) as $case ) {
    passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . $case, $status );
    check( 0 === $status, 'isolated cache constant case: ' . $case );
}
$_GET = array();
$load();
check( array( array( 'init', 'pera_property_layout_preview_nocache', 0 ) ) === $GLOBALS['hooks'], 'early cache hook only; no rendering or asset hooks' );
$values = array( '1', '0', '', '01', 'true', ' 1', '1 ', "1\n", '<b>1</b>', array( '1' ), array(), 1, true, null );
$cases = 0;
foreach ( array( false, true ) as $logged_in ) {
foreach ( array( false, true ) as $admin ) {
foreach ( array( false, true ) as $property ) {
foreach ( array_merge( array( '__absent__' ), $values ) as $value ) {
    $GLOBALS['logged_in'] = $logged_in;
    $GLOBALS['admin'] = $admin;
    $GLOBALS['property'] = $property;
    $_GET = '__absent__' === $value ? array( 'other' => '1' ) : array( 'layout_preview' => $value );
    check( ( $logged_in && $admin && $property && '1' === $value ) === pera_property_layout_preview_enabled(), 'fail-closed gate' );
    $GLOBALS['headers'] = array();
    $GLOBALS['nocache'] = 0;
    $GLOBALS['sent'] = false;
    pera_property_layout_preview_nocache();
    $expected_headers = '__absent__' === $value ? array() : array(
        'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0',
        'CDN-Cache-Control: no-store', 'Surrogate-Control: no-store',
    );
    check( $expected_headers === $GLOBALS['headers'], 'cache headers only for query-bearing requests' );
    check( ( '__absent__' === $value ? 0 : 1 ) === $GLOBALS['nocache'], 'WordPress no-cache helper' );
    $cases++;
}}}}
$GLOBALS['sent'] = true;
$GLOBALS['headers'] = array();
$GLOBALS['nocache'] = 0;
pera_property_layout_preview_nocache();
check( array() === $GLOBALS['headers'] && 0 === $GLOBALS['nocache'], 'no header warnings after output' );
$theme = dirname( __DIR__ );
$template = file_get_contents( $theme . '/single-property.php' );
check( ! preg_match( '/layout_preview|pera_property_layout_preview_enabled/', $template ), 'production rendering never consumes gate' );
foreach ( array( $template, file_get_contents( $theme . '/css/main.css' ), file_get_contents( $theme . '/css/property.css' ), file_get_contents( $theme . '/js/property-sticky-summary.js' ) ) as $source ) {
    check( ! preg_match( '/property-location--preview|property-location-preview__|property-editorial-grid--preview|property-sticky-preview/', $source ), 'no legacy experimental selectors' );
}
echo "Preview gate/cache tests passed ($cases visitor/query combinations)\n";
