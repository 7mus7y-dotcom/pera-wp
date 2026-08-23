<?php
/** Focused generic-field protected-structure retry tests. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['generic_retry_provider'] = null;
$GLOBALS['translation_errors'] = array();
function __( $value ) { return $value; }
function get_option( $key, $default = false ) { return $default; }
function apply_filters( $tag, $value ) { return 'pera_ml_provider' === $tag ? $GLOBALS['generic_retry_provider'] : $value; }
function do_action( $tag ) { if ( 'pera_ml_translation_error' === $tag ) $GLOBALS['translation_errors'][] = func_get_args(); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function expect_same( $expected, $actual, $label ) {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}

require dirname( __DIR__ ) . '/includes/providers/interface-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-mock-provider.php';
require dirname( __DIR__ ) . '/includes/class-translator.php';

final class Generic_Retry_Provider implements Pera_ML_Provider_Interface {
	public $calls = array();
	private $responses;
	public function __construct( array $responses ) { $this->responses = $responses; }
	public function id() { return 'generic-retry-test'; }
	public function translate( $source, array $context ) {
		$this->calls[] = array( 'source' => $source, 'context' => $context );
		return array_shift( $this->responses );
	}
}
final class Generic_Retry_Storage {
	public $puts = array();
	public function put() { $this->puts[] = func_get_args(); return true; }
}

$registry = new class { public function get( $language ) { return array( 'name' => 'Arabic', 'source' => false, 'instructions' => 'Translate naturally.' ); } };
$run = static function ( $source, array $responses ) use ( $registry ) {
	$GLOBALS['translation_errors'] = array();
	$GLOBALS['generic_retry_provider'] = new Generic_Retry_Provider( $responses );
	$storage = new Generic_Retry_Storage();
	$translator = new Pera_ML_Translator( $registry, $storage );
	$result = $translator->translate_and_store( 'post', 59471, 'meta:property_editorial_intro', 'ar', $source, 'mock' );
	return array( $result, $GLOBALS['generic_retry_provider'], $storage );
};

list( $result, $provider, $storage ) = $run( '<p>Hello world</p>', array( 'PERAMLPROTECTED0TOKENمرحبا بالعالمPERAMLPROTECTED1TOKEN' ) );
expect_same( '<p>مرحبا بالعالم</p>', $result, 'valid first response is restored' );
expect_same( 1, count( $provider->calls ), 'valid first response uses one provider call' );
expect_same( 1, count( $storage->puts ), 'valid first response is stored' );

list( $result, $provider, $storage ) = $run( '<p>Hello world</p>', array( 'مرحبا بالعالم', 'PERAMLPROTECTED0TOKENمرحبا بالعالمPERAMLPROTECTED1TOKEN' ) );
expect_same( '<p>مرحبا بالعالم</p>', $result, 'strict retry restores dropped placeholders' );
expect_same( 2, count( $provider->calls ), 'dropped placeholders cause exactly one retry' );
expect_same( $provider->calls[0]['source'], $provider->calls[1]['source'], 'retry uses identical protected source' );
expect_same( true, false !== strpos( $provider->calls[1]['context']['instructions'], 'Do not remove, translate, reformat, split, duplicate, or add spaces inside these placeholders.' ), 'retry includes strict placeholder instructions' );
expect_same( 1, count( $storage->puts ), 'recovered translation is stored' );
expect_same( 0, count( $GLOBALS['translation_errors'] ), 'recoverable first failure is not logged' );

list( $result, $provider, $storage ) = $run( '<p>Hello world</p>', array( 'مرحبا', 'مرحبا أيضاً', 'مرحبا بالعالم' ) );
expect_same( '<p>مرحبا بالعالم</p>', $result, 'persistent wrapper loss recovers by translating plain leaf text' );
expect_same( 3, count( $provider->calls ), 'local leaf recovery follows the ordinary and strict attempts' );
expect_same( 'Hello world', $provider->calls[2]['source'], 'local leaf recovery does not expose its wrapper' );
expect_same( 1, count( $storage->puts ), 'locally recovered translation is stored' );
expect_same( 0, count( $GLOBALS['translation_errors'] ), 'recoverable wrapper failures are not logged' );

list( $result, $provider, $storage ) = $run( 'Modern apartments in Istanbul', array( 'شقق حديثة في إسطنبول' ) );
expect_same( 'شقق حديثة في إسطنبول', $result, 'plain text retains existing behavior' );
expect_same( 1, count( $provider->calls ), 'plain text does not retry' );

$provider_error = new WP_Error( 'provider_unavailable' );
list( $result, $provider, $storage ) = $run( '<p>Hello world</p>', array( $provider_error ) );
expect_same( $provider_error, $result, 'first provider error is returned unchanged' );
expect_same( 1, count( $provider->calls ), 'provider error does not trigger structure retry' );
expect_same( 1, count( $GLOBALS['translation_errors'] ), 'provider error retains normal logging' );

list( $result, $provider, $storage ) = $run( '<code>Hello world</code>', array(
	'PERAMLPROTECTED0TOKENمرحباPERAMLPROTECTED1TOKENPERAMLPROTECTED1TOKEN',
	'PERAMLPROTECTED0TOKENمرحباPERAMLPROTECTED1TOKENPERAMLPROTECTED9TOKEN',
) );
expect_same( 'pera_ml_structure_changed', $result->get_error_code(), 'duplicated and unknown placeholders remain rejected' );
expect_same( 2, count( $provider->calls ), 'invalid placeholders receive only the single strict retry' );
expect_same( 0, count( $storage->puts ), 'invalid placeholder response is not stored' );

echo "Pera ML generic structure retry tests passed\n";
