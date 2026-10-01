<?php
/** Public Russian launch gate: internal workflows stay enabled while discovery stays hidden. */

define( 'ABSPATH', __DIR__ );
function get_option( $key, $default = false ) { return $default; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_:-]/', '', strtolower( $value ) ); }
function apply_filters( $hook, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function is_404() { return false; }
function is_admin() { return false; }
function is_singular() { return false; }
function wp_unslash( $value ) { return $value; }
function home_url( $path = '/' ) { return 'https://example.test/' . ltrim( $path, '/' ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return $value; }
function gate_expect( $condition, $label ) { if ( ! $condition ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); } }

final class Public_Gate_Router {
	public function is_translated() { return false; }
	public function url_for_language( $url, $code ) { return 'en' === $code ? 'https://example.test/page/' : 'https://example.test/' . $code . '/page/'; }
}
final class Public_Gate_Storage {}

require dirname( __DIR__ ) . '/includes/class-language-registry.php';
require dirname( __DIR__ ) . '/includes/class-seo.php';

$registry = new Pera_ML_Language_Registry();
gate_expect( isset( $registry->enabled()['ru'] ), 'Russian remains enabled internally' );
gate_expect( ! isset( $registry->publicly_available()['ru'] ), 'Russian is hidden from public discovery by default' );

$_SERVER['REQUEST_URI'] = '/page/';
$seo = new Pera_ML_SEO( $registry, new Public_Gate_Router(), new Public_Gate_Storage() );
ob_start();
$seo->alternates();
$alternates = ob_get_clean();
gate_expect( false === strpos( $alternates, 'hreflang="ru"' ), 'public hreflang omits Russian while gated' );
gate_expect( false !== strpos( $alternates, 'hreflang="de-DE"' ), 'public hreflang retains launched languages' );
gate_expect( false !== strpos( $alternates, 'hreflang="x-default"' ), 'public hreflang retains x-default' );

echo "Pera ML public Russian gate tests passed\n";
