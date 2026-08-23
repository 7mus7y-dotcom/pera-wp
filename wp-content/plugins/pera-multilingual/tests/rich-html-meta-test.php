<?php
/** Focused structural translation tests for HTML-bearing non-post_content fields. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['rich_provider'] = null;
$GLOBALS['rich_errors'] = array();
function __( $value ) { return $value; }
function get_option( $key, $default = false ) { return $default; }
function apply_filters( $tag, $value ) { return 'pera_ml_provider' === $tag ? $GLOBALS['rich_provider'] : $value; }
function do_action( $tag ) { if ( 'pera_ml_translation_error' === $tag ) $GLOBALS['rich_errors'][] = func_get_args(); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function expect_rich( $condition, $label ) { if ( ! $condition ) { fwrite( STDERR, "FAIL $label\n" ); exit( 1 ); } }

require dirname( __DIR__ ) . '/includes/providers/interface-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-mock-provider.php';
require dirname( __DIR__ ) . '/includes/class-translator.php';

final class Rich_HTML_Provider implements Pera_ML_Provider_Interface {
	public $calls = array();
	public $damage_above = PHP_INT_MAX;
	public $always_damage = false;
	public function id() { return 'rich-html-test'; }
	public function translate( $source, array $context ) {
		$this->calls[] = array( 'source' => $source, 'context' => $context );
		$count = preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $source );
		if ( $this->always_damage || $count > $this->damage_above ) return preg_replace( '/PERAMLPROTECTED\d+TOKEN/', '', $source, 1 );
		return strtr( $source, array(
			'Text with ' => 'نص مع ', 'Feriköy' => 'فيريكوي', 'Architectural Excellence' => 'التميز المعماري',
			'More text with ' => 'نص إضافي مع ', 'important wording' => 'صياغة مهمة', 'Life in Feriköy' => 'الحياة في فيريكوي',
			'Modern apartments near the metro.' => 'شقق حديثة بالقرب من المترو.', 'Plain title' => 'عنوان عادي',
		) );
	}
}
final class Rich_HTML_Storage { public $puts = array(); public function put() { $this->puts[] = func_get_args(); } }
$registry = new class { public function get() { return array( 'name' => 'Arabic', 'source' => false, 'instructions' => 'Translate.' ); } };
$run = static function ( $source, $provider = null ) use ( $registry ) {
	$GLOBALS['rich_provider'] = $provider ? $provider : new Rich_HTML_Provider();
	$GLOBALS['rich_errors'] = array();
	$storage = new Rich_HTML_Storage();
	$translator = new Pera_ML_Translator( $registry, $storage );
	$result = $translator->translate_and_store( 'post', 37728, 'meta:about_this_project', 'ar', $source, 'mock' );
	return array( $result, $GLOBALS['rich_provider'], $storage );
};

$mixed = 'Text with <a href="https://example.com" target="_blank" rel="noopener">Feriköy</a>.\n<h3>Architectural Excellence</h3>\nMore text with <strong>important wording</strong>.';
list( $result, $provider ) = $run( $mixed );
expect_rich( false !== strpos( $result, '<a href="https://example.com" target="_blank" rel="noopener">فيريكوي</a>' ), 'link and attributes are preserved exactly' );
expect_rich( false !== strpos( $result, '<h3>التميز المعماري</h3>' ) && false !== strpos( $result, '<strong>صياغة مهمة</strong>' ), 'heading and strong semantics are preserved' );
expect_rich( 6 === preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $provider->calls[0]['source'] ), 'mixed HTML is structurally protected' );

$property = $mixed . '\n<h3>Life in Feriköy</h3><a href="https://example.com/area">Feriköy</a> ' . $mixed;
list( $result, $provider ) = $run( $property );
foreach ( $provider->calls as $call ) expect_rich( preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $call['source'] ) <= 9, 'property-style HTML is split below the generic ten-placeholder request' );
expect_rich( count( $provider->calls ) > 1, 'property-style HTML uses multiple balanced fragments' );

list( $result, $provider ) = $run( '<p>Modern apartments near the metro.</p>' );
expect_rich( '<p>شقق حديثة بالقرب من المترو.</p>' === $result, 'simple paragraph deterministically uses structural translation' );

list( $result, $provider ) = $run( 'Plain title with value < threshold and value > floor' );
expect_rich( 1 === preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $provider->calls[0]['source'] ), 'comparison text remains one generic protected fragment rather than HTML structure' );

$recovering = new Rich_HTML_Provider();
$recovering->damage_above = 3;
list( $result, $provider, $storage ) = $run( $mixed, $recovering );
expect_rich( ! is_wp_error( $result ) && count( $provider->calls ) > 1, 'damaged compound response recovers through smaller structural fragments' );
expect_rich( 1 === count( $storage->puts ), 'only the complete recovered translation is stored' );

$broken = new Rich_HTML_Provider();
$broken->always_damage = true;
list( $result, $provider, $storage ) = $run( '<p>Modern apartments near the metro.</p>', $broken );
expect_rich( is_wp_error( $result ) && 'pera_ml_structure_changed' === $result->get_error_code(), 'unrecoverable structural damage returns the structure error' );
expect_rich( 0 === count( $storage->puts ) && 1 === count( $GLOBALS['rich_errors'] ), 'failure stores nothing and emits one final error action' );

echo "Pera ML rich HTML meta tests passed\n";
