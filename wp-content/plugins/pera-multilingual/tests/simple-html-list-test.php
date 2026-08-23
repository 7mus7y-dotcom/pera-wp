<?php
/** Focused tests for locally reconstructed simple HTML lists. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['list_provider'] = null;
$GLOBALS['list_errors'] = array();
function __( $value ) { return $value; }
function get_option( $key, $default = false ) { return $default; }
function apply_filters( $tag, $value ) { return 'pera_ml_provider' === $tag ? $GLOBALS['list_provider'] : $value; }
function do_action( $tag ) { if ( 'pera_ml_translation_error' === $tag ) $GLOBALS['list_errors'][] = func_get_args(); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function expect_list( $condition, $label ) { if ( ! $condition ) { fwrite( STDERR, "FAIL $label\n" ); exit( 1 ); } }
function expect_list_same( $expected, $actual, $label ) { expect_list( $expected === $actual, $label . ': ' . var_export( $actual, true ) ); }

require dirname( __DIR__ ) . '/includes/providers/interface-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-mock-provider.php';
require dirname( __DIR__ ) . '/includes/class-translator.php';

final class Simple_List_Provider implements Pera_ML_Provider_Interface {
	public $calls = array();
	public $responses = array();
	public function id() { return 'simple-list-test'; }
	public function translate( $source, array $context ) {
		$this->calls[] = array( 'source' => $source, 'context' => $context );
		return empty( $this->responses ) ? $source : array_shift( $this->responses );
	}
}
final class Simple_List_Storage {
	public $puts = array();
	public function put() { $this->puts[] = func_get_args(); return true; }
}

$registry = new class { public function get() { return array( 'name' => 'Arabic', 'source' => false, 'instructions' => 'Translate naturally.' ); } };
$run = static function ( $source, array $responses = array() ) use ( $registry ) {
	$GLOBALS['list_provider'] = new Simple_List_Provider();
	$GLOBALS['list_provider']->responses = $responses;
	$GLOBALS['list_errors'] = array();
	$storage = new Simple_List_Storage();
	$translator = new Pera_ML_Translator( $registry, $storage );
	$result = $translator->translate_and_store( 'post', 59415, 'meta:distances', 'ar', $source, 'mock' );
	return array( $result, $GLOBALS['list_provider'], $storage );
};

$unordered = "<ul>\n  <li>Walking distance</li>\n  <li>5 minutes</li>\n</ul>\n";
list( $result, $provider, $storage ) = $run( $unordered, array( 'مسافة سير', '5 دقائق' ) );
expect_list_same( "<ul>\n  <li>مسافة سير</li>\n  <li>5 دقائق</li>\n</ul>\n", $result, 'unordered list is reconstructed' );
expect_list_same( array( 'Walking distance', '5 minutes' ), array_column( $provider->calls, 'source' ), 'only item content reaches provider' );
expect_list_same( 1, count( $storage->puts ), 'successful list is stored once' );

$ordered = '<ol class="steps"><li>First point</li><li>Second point</li></ol>';
list( $result, $provider ) = $run( $ordered, array( 'النقطة الأولى', 'النقطة الثانية' ) );
expect_list_same( '<ol class="steps"><li>النقطة الأولى</li><li>النقطة الثانية</li></ol>', $result, 'ordered tag, attributes, and order are preserved' );

$distances = '<ul><li>Marmaray Yunus Station – Walking distance</li><li>Kadıköy – Approximately 25 minutes via Marmaray</li></ul>';
list( $result, $provider ) = $run( $distances, array( 'محطة مرمراي يونس – على مسافة سير', 'كاديكوي – حوالي 25 دقيقة عبر مرمراي' ) );
expect_list_same( 2, count( $provider->calls ), 'realistic distances translate item by item' );
expect_list( false === strpos( $provider->calls[0]['source'], 'PERAMLPROTECTED' ), 'list wrappers never become placeholders' );
expect_list_same( 2, substr_count( $result, '<li>' ), 'realistic list retains item count' );

$failure = new WP_Error( 'provider_unavailable' );
list( $result, $provider, $storage ) = $run( $unordered, array( 'مسافة سير', $failure ) );
expect_list_same( $failure, $result, 'second item provider error fails whole field' );
expect_list_same( 0, count( $storage->puts ), 'partial list is never stored' );

$nested = '<ul><li>Parent<ul><li>Child</li></ul></li></ul>';
list( $result, $provider ) = $run( $nested );
expect_list_same( 1, count( $provider->calls ), 'nested list uses the structural HTML path' );
expect_list( false !== strpos( $provider->calls[0]['source'], 'PERAMLPROTECTED'), 'nested list structurally protects markup' );

$inline = '<ul><li><strong>Metro:</strong> 5 minutes</li></ul>';
list( $result, $provider ) = $run( $inline );
expect_list_same( 1, count( $provider->calls ), 'inline-rich list uses the structural HTML path' );
expect_list( substr_count( $provider->calls[0]['source'], 'PERAMLPROTECTED' ) >= 4, 'inline structural translation retains protection' );

$malformed = '<ul><li>Broken</ul>';
list( $result, $provider ) = $run( $malformed );
expect_list_same( 1, count( $provider->calls ), 'malformed list falls back to generic path' );
expect_list( false !== strpos( $provider->calls[0]['source'], 'PERAMLPROTECTED'), 'malformed markup is not parsed as simple' );

echo "Pera ML simple HTML list tests passed\n";
