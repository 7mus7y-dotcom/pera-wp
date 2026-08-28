<?php
/** Translation-health count and canonical taxonomy-contract regression tests. */
define( 'ABSPATH', __DIR__ );
function health_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
function apply_filters( $hook, $value ) { return $value; }
function get_posts() { return array( 1, 2, 3, 4 ); }
function get_post( $id ) { $types = array( 1=>'post', 2=>'post', 3=>'page', 4=>'property' ); return (object) array( 'ID'=>$id, 'post_type'=>$types[ $id ] ); }
function get_the_title( $id ) { return 'Object ' . $id; }
function is_wp_error() { return false; }
function get_terms( $args ) { return 'region' === $args['taxonomy'] ? array( (object) array( 'term_id'=>42, 'name'=>'Region', 'description'=>'' ) ) : array(); }
$GLOBALS['term_meta'] = array( 'archive_subtitle'=>'Subtitle', 'archive_body_content'=>'Body', 'seo_faq_v2'=>"Question|Answer", 'arbitrary_private_copy'=>'Ignore me' );
function get_term_meta( $id, $key ) { return isset( $GLOBALS['term_meta'][ $key ] ) ? $GLOBALS['term_meta'][ $key ] : ''; }
final class Health_UI { public function inventory() { return array(); } }
final class Health_Status {
	public $preloads = array(); public $gets = 0;
	public function preload( $ids, $languages, $post_type ) { $this->preloads[] = array( $ids, $languages, $post_type ); }
	public function applicable_sources( $id ) { return array( 'post_title' => 3 === $id ? 'Page' : ( 2 === $id ? '   ' : 'Title' ), 'post_content' => 2 === $id ? '' : 'Content' ); }
	public function get() { $this->gets++; return array( 'missing'=>array( 'post_title', 'post_content' ), 'stale'=>array() ); }
}
final class Health_Storage {
	public function get( $type, $id, $field, $language, $source ) {
		if ( 'meta:archive_subtitle' === $field && 'zh' === $language ) return array( 'translated_text'=>'当前', 'is_stale'=>false, 'status'=>'current' );
		if ( 'meta:archive_subtitle' === $field && 'ar' === $language ) return array( 'translated_text'=>'قديم', 'is_stale'=>true, 'status'=>'current' );
		return null;
	}
}
require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translation-health.php';
$fields_service = new Pera_ML_Fields( null, null, null );
health_expect( array( 'seo_title', 'seo_meta_description', 'seo_faq_v2' ), $fields_service->approved( 'page' ), 'page health meta is readable through the frontend field contract' );
foreach ( array( 'district', 'region', 'property_type', 'property_tags', 'special' ) as $taxonomy ) health_expect( true, in_array( 'meta:seo_faq_v2', Pera_ML_Fields::taxonomy_fields( $taxonomy ), true ), $taxonomy . ' FAQ is in the taxonomy contract' );
health_expect( false, in_array( 'meta:seo_faq_v2', Pera_ML_Fields::taxonomy_fields( 'category' ), true ), 'FAQ is not added outside the supported taxonomy contract' );
$status = new Health_Status();
$inventory = ( new Pera_ML_Translation_Health( $status, new Health_Storage(), new Health_UI() ) )->inventory();
health_expect( array( 'post', 'page', 'property' ), array_column( $status->preloads, 2 ), 'status is preloaded once per non-empty post-type group' );
health_expect( array( 1, 2 ), $status->preloads[0][0], 'post IDs are grouped into one preload' );
health_expect( 9, $status->gets, 'preloaded request-local status is read only for objects with canonical copy' );
$empty_rows = array_filter( $inventory['rows'], static function ( $row ) { return 2 === $row['object_id']; } );
health_expect( 0, count( $empty_rows ), 'whitespace and empty canonical content is not offered' );
health_expect( 1, $inventory['counts']['taxonomies']['zh']['current'], 'supported taxonomy meta current count' );
health_expect( 1, $inventory['counts']['taxonomies']['ar']['stale'], 'supported taxonomy meta stale count' );
health_expect( 4, $inventory['counts']['taxonomies']['de']['missing'], 'supported name and meta missing counts' );
$faq_rows = array_filter( $inventory['rows'], static function ( $row ) { return 'meta:seo_faq_v2' === $row['field']; } );
health_expect( array( 'zh', 'ar', 'de' ), array_values( array_column( $faq_rows, 'language' ) ), 'canonical taxonomy FAQ creates one health row per target language' );
health_expect( array( 'missing', 'missing', 'missing' ), array_values( array_column( $faq_rows, 'status' ) ), 'untranslated taxonomy FAQ rows are missing' );
$GLOBALS['term_meta']['seo_faq_v2'] = '   ';
$empty_inventory = ( new Pera_ML_Translation_Health( $status, new Health_Storage(), new Health_UI() ) )->inventory();
$empty_faq_rows = array_filter( $empty_inventory['rows'], static function ( $row ) { return 'meta:seo_faq_v2' === $row['field']; } );
health_expect( 0, count( $empty_faq_rows ), 'empty taxonomy FAQ does not create missing rows' );
$fields = array_column( $inventory['rows'], 'field' );
health_expect( false, in_array( 'meta:arbitrary_private_copy', $fields, true ), 'unsupported arbitrary term meta is ignored' );
echo "Pera ML translation health tests passed\n";
