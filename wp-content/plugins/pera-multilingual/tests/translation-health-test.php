<?php
/** Translation-health count and canonical taxonomy-contract regression tests. */
define( 'ABSPATH', __DIR__ );
function health_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
function apply_filters( $hook, $value ) { return $value; }
function get_posts() { return array(); }
function is_wp_error() { return false; }
function get_terms( $args ) { return 'region' === $args['taxonomy'] ? array( (object) array( 'term_id'=>42, 'name'=>'Region', 'description'=>'' ) ) : array(); }
$GLOBALS['term_meta'] = array( 'archive_subtitle'=>'Subtitle', 'archive_body_content'=>'Body', 'arbitrary_private_copy'=>'Ignore me' );
function get_term_meta( $id, $key ) { return isset( $GLOBALS['term_meta'][ $key ] ) ? $GLOBALS['term_meta'][ $key ] : ''; }
final class Health_UI { public function inventory() { return array(); } }
final class Health_Status {}
final class Health_Storage {
	public function get( $type, $id, $field, $language, $source ) {
		if ( 'meta:archive_subtitle' === $field && 'zh' === $language ) return array( 'translated_text'=>'当前', 'is_stale'=>false, 'status'=>'current' );
		if ( 'meta:archive_subtitle' === $field && 'ar' === $language ) return array( 'translated_text'=>'قديم', 'is_stale'=>true, 'status'=>'current' );
		return null;
	}
}
require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translation-health.php';
$inventory = ( new Pera_ML_Translation_Health( new Health_Status(), new Health_Storage(), new Health_UI() ) )->inventory();
health_expect( 1, $inventory['counts']['taxonomies']['zh']['current'], 'supported taxonomy meta current count' );
health_expect( 1, $inventory['counts']['taxonomies']['ar']['stale'], 'supported taxonomy meta stale count' );
health_expect( 3, $inventory['counts']['taxonomies']['de']['missing'], 'supported name and meta missing counts' );
$fields = array_column( $inventory['rows'], 'field' );
health_expect( false, in_array( 'meta:arbitrary_private_copy', $fields, true ), 'unsupported arbitrary term meta is ignored' );
echo "Pera ML translation health tests passed\n";
