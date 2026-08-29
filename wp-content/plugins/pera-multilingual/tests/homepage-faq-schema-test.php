<?php
/** Homepage FAQ schema uses the same multilingual row path as visible output. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['schema_actions'] = array(); $GLOBALS['schema_language'] = 'de'; $GLOBALS['schema_seo_plugin'] = false;
function add_action( $hook, $callback ) { $GLOBALS['schema_actions'][ $hook ][] = $callback; }
function add_filter() {}
function is_admin() { return false; }
function is_front_page() { return true; }
function pera_schema_has_active_seo_plugin() { return $GLOBALS['schema_seo_plugin']; }
function pera_schema_should_emit_type() { return true; }
function get_option( $key ) { return 'page_on_front' === $key ? 20 : null; }
function get_queried_object_id() { return 20; }
function get_field( $field, $id = 0 ) { return 'faq' === $field ? array( array( 'question' => 'English question one', 'answer' => 'English answer one' ), array( 'question' => 'English question two', 'answer' => 'English answer two' ) ) : null; }
function pera_ml_homepage_faq( $id, array $rows ) {
	if ( 'en' === $GLOBALS['schema_language'] ) return $rows;
	$rows[0]['question'] = 'Deutsche Frage eins'; // Its missing answer deliberately remains canonical English.
	$rows[1]['answer'] = 'Deutsche Antwort zwei'; // Its missing question deliberately remains canonical English.
	return $rows;
}
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function schema_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }

require dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child/inc/seo-all.php';
$callbacks = $GLOBALS['schema_actions']['wp_head']; $homepage_schema = $callbacks[ count( $callbacks ) - 1 ];
$render = static function () use ( $homepage_schema ) { unset( $GLOBALS['pera_schema_faq_emitted'] ); ob_start(); $homepage_schema(); return ob_get_clean(); };
$decode = static function ( $output ) { preg_match( '#<script[^>]*>(.*)</script>#s', $output, $match ); return json_decode( $match[1], true ); };

$de = $decode( $render() );
schema_expect( 'Deutsche Frage eins', $de['mainEntity'][0]['name'], 'translated question is emitted in FAQ schema' );
schema_expect( 'English answer one', $de['mainEntity'][0]['acceptedAnswer']['text'], 'missing translated answer falls back in FAQ schema' );
schema_expect( 'English question two', $de['mainEntity'][1]['name'], 'missing translated question falls back in FAQ schema' );
schema_expect( 'Deutsche Antwort zwei', $de['mainEntity'][1]['acceptedAnswer']['text'], 'translated answer is emitted in FAQ schema' );
$GLOBALS['schema_language'] = 'en'; $en = $decode( $render() );
schema_expect( 'English question one', $en['mainEntity'][0]['name'], 'English schema remains canonical' );
schema_expect( 'English answer two', $en['mainEntity'][1]['acceptedAnswer']['text'], 'English schema answer remains canonical' );
$GLOBALS['schema_seo_plugin'] = true;
schema_expect( '', $render(), 'active SEO plugin continues to suppress theme FAQ schema' );
echo "Pera ML homepage FAQ schema tests passed\n";
