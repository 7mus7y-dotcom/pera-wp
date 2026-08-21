<?php
/** German registry, SEO, instructions, glossary and source-echo regression tests. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['saved_enabled_languages'] = null;
$GLOBALS['german_provider'] = null;
$GLOBALS['seo_filters'] = array();
$GLOBALS['seo_meta'] = array();
function add_action() {}
function add_filter( $hook ) { $GLOBALS['seo_filters'][] = $hook; }
function get_option( $key, $default = false ) { return 'pera_ml_enabled_languages' === $key && null !== $GLOBALS['saved_enabled_languages'] ? $GLOBALS['saved_enabled_languages'] : $default; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_:-]/', '', strtolower( $value ) ); }
function apply_filters( $tag, $value ) { return 'pera_ml_provider' === $tag && $GLOBALS['german_provider'] ? $GLOBALS['german_provider'] : $value; }
function __( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function do_action() {}
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return $value; }
function is_404() { return false; }
function is_admin() { return false; }
function is_singular() { return true; }
function get_queried_object_id() { return 42; }
function get_post_meta( $post_id, $key ) { return isset( $GLOBALS['seo_meta'][ $key ] ) ? $GLOBALS['seo_meta'][ $key ] : ''; }
function get_the_title() { return 'Canonical WordPress title'; }
function wp_unslash( $value ) { return $value; }
function home_url( $path = '/' ) { return 'https://example.test/' . ltrim( $path, '/' ); }
function expect_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
class WP_Error { private $code; public function __construct( $code ) { $this->code = $code; } public function get_error_code() { return $this->code; } }

require dirname( __DIR__ ) . '/includes/class-language-registry.php';
require dirname( __DIR__ ) . '/includes/providers/interface-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-mock-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-openai-provider.php';
require dirname( __DIR__ ) . '/includes/class-translator.php';
require dirname( __DIR__ ) . '/includes/class-seo.php';

$registry = new Pera_ML_Language_Registry();
$german = $registry->get( 'de' );
expect_same( 'German', $german['name'], 'German registry name' );
expect_same( 'Deutsch', $german['native_name'], 'German native name' );
expect_same( 'de', $german['prefix'], 'German URL prefix' );
expect_same( 'ltr', $german['direction'], 'German is LTR' );
expect_same( false, $german['source'], 'German is a target language' );
expect_same( 'de-DE', $german['hreflang'], 'German hreflang representation' );
expect_same( true, isset( $registry->enabled()['de'] ), 'German is enabled on a fresh configuration' );
$GLOBALS['saved_enabled_languages'] = array( 'en', 'zh', 'ar' );
expect_same( false, isset( $registry->enabled()['de'] ), 'saved administrator language selection is not overwritten' );
$GLOBALS['saved_enabled_languages'] = null;

final class German_Test_Provider implements Pera_ML_Provider_Interface {
	public $calls = array();
	public function id() { return 'german-test'; }
	public function translate( $source, array $context ) { $this->calls[] = $context; return str_replace( 'An excellent investment opportunity in central Istanbul', 'Eine ausgezeichnete Investitionsmöglichkeit in zentraler Lage in Istanbul', $source ); }
}
final class German_Test_Storage {
	public $puts = array(); public $gets = array(); public $translated_title = 'Deutscher Titel';
	public function put() { $this->puts[] = func_get_args(); return true; }
	public function get() { $this->gets[] = func_get_args(); return array( 'translated_text' => $this->translated_title ); }
}
$storage = new German_Test_Storage(); $GLOBALS['german_provider'] = new German_Test_Provider();
$translator = new Pera_ML_Translator( $registry, $storage );
$source = '<p>An excellent investment opportunity in central Istanbul. Beşiktaş remains Beşiktaş.</p>';
$translated = $translator->translate_and_store( 'post', 42, 'post_content', 'de', $source, 'test' );
expect_same( false, is_wp_error( $translated ), 'German Latin-script translation is not falsely rejected' );
expect_same( 1, count( $GLOBALS['german_provider']->calls ), 'German source-echo validator does not make a script-based retry' );
expect_same( true, false !== strpos( $GLOBALS['german_provider']->calls[0]['instructions'], 'natural, professional German' ), 'German provider instructions are supplied centrally' );
expect_same( true, false !== strpos( $GLOBALS['german_provider']->calls[0]['glossary'], 'Pera Property => PRESERVE' ), 'preserve glossary applies to German' );

final class German_Test_Router {
	public function current_language() { return 'de'; }
	public function is_translated() { return true; }
	public function url_for_language( $url, $language ) { return 'en' === $language ? 'https://example.test/post/' : 'https://example.test/' . $language . '/post/'; }
}
$_SERVER['REQUEST_URI'] = '/de/post/';
$seo = new Pera_ML_SEO( $registry, new German_Test_Router(), $storage );
$seo->hooks();
expect_same( array( 'pre_get_document_title', 'rank_math/frontend/canonical' ), $GLOBALS['seo_filters'], 'only supported SEO filters are registered' );
$GLOBALS['seo_meta']['seo_title'] = 'Canonical custom SEO title';
expect_same( 'Deutscher Titel', $seo->document_title( 'Original document title' ), 'German translated SEO title is returned' );
expect_same( array( 'post', 42, 'meta:seo_title', 'de', 'Canonical custom SEO title' ), end( $storage->gets ), 'custom SEO title is the canonical translation source' );
unset( $GLOBALS['seo_meta']['seo_title'] );
$seo->document_title( 'Original document title' );
expect_same( array( 'post', 42, 'meta:seo_title', 'de', 'Canonical WordPress title' ), end( $storage->gets ), 'WordPress title is the only SEO source fallback' );
ob_start(); $seo->alternates(); $alternates = ob_get_clean();
expect_same( true, false !== strpos( $alternates, 'hreflang="de-DE" href="https://example.test/de/post/"' ), 'German hreflang points to German route' );
expect_same( true, false !== strpos( $alternates, 'hreflang="x-default" href="https://example.test/post/"' ), 'x-default remains English' );

echo "Pera ML German support tests passed\n";
