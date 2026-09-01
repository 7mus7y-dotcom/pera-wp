<?php
/** Translation-health count and canonical taxonomy-contract regression tests. */
define( 'ABSPATH', __DIR__ );
function health_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
function apply_filters( $hook, $value ) { return $value; }
function get_posts() { return array( 1, 2, 3, 4, 5, 6 ); }
function get_post( $id ) { $types = array( 1=>'post', 2=>'post', 3=>'page', 4=>'property', 5=>'team', 6=>'team' ); return (object) array( 'ID'=>$id, 'post_type'=>$types[ $id ] ); }
function get_the_title( $id ) { return 'Object ' . $id; }
function is_wp_error() { return false; }
function get_terms( $args ) {
	if ( 'region' === $args['taxonomy'] ) return array( (object) array( 'term_id'=>42, 'name'=>'Region', 'description'=>'' ) );
	if ( 'category' === $args['taxonomy'] ) return array( (object) array( 'term_id'=>102, 'name'=>'Buyer guides', 'description'=>'Canonical category description' ) );
	return array();
}
$GLOBALS['term_meta'] = array( 'archive_subtitle'=>'Subtitle', 'archive_body_content'=>'Body', 'seo_faq_v2'=>"Question|Answer", 'arbitrary_private_copy'=>'Ignore me' );
function get_term_meta( $id, $key ) { return isset( $GLOBALS['term_meta'][ $key ] ) ? $GLOBALS['term_meta'][ $key ] : ''; }
final class Health_UI { public function inventory() { return array(); } }
final class Health_Status {
	public $preloads = array(); public $gets = 0;
	public function preload( $ids, $languages, $post_type ) { $this->preloads[] = array( $ids, $languages, $post_type ); }
	public function applicable_sources( $id ) {
		if ( 5 === $id ) return array( 'meta:position' => 'Senior Property Consultant' );
		if ( 6 === $id ) return array();
		return array( 'post_title' => 3 === $id ? 'Page' : ( 2 === $id ? '   ' : 'Title' ), 'post_content' => 2 === $id ? '' : 'Content' );
	}
	public function get( $id ) { $this->gets++; return array( 'missing' => 5 === $id ? array( 'meta:position' ) : array( 'post_title', 'post_content' ), 'stale'=>array() ); }
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
health_expect( array( 'seo_title', 'seo_meta_description', 'seo_faq_v2', 'homepage_hero_subtext', 'homepage_listing_intro', 'homepage_bottom_seo_text' ), $fields_service->approved( 'page' ), 'page health meta is readable through the frontend field contract' );
foreach ( array( 'district', 'region', 'property_type', 'property_tags', 'special' ) as $taxonomy ) health_expect( true, in_array( 'meta:seo_faq_v2', Pera_ML_Fields::taxonomy_fields( $taxonomy ), true ), $taxonomy . ' FAQ is in the taxonomy contract' );
health_expect( false, in_array( 'meta:seo_faq_v2', Pera_ML_Fields::taxonomy_fields( 'category' ), true ), 'FAQ is not added outside the supported taxonomy contract' );
health_expect( true, in_array( 'category', Pera_ML_Fields::supported_taxonomies(), true ), 'category is in the supported taxonomy inventory' );
health_expect( array( 'term_name', 'term_description' ), Pera_ML_Fields::taxonomy_fields( 'category' ), 'category uses the existing name and description contract' );
$status = new Health_Status();
$inventory = ( new Pera_ML_Translation_Health( $status, new Health_Storage(), new Health_UI() ) )->inventory();
health_expect( array( 'post', 'page', 'property', 'team' ), array_column( $status->preloads, 2 ), 'status is preloaded once per non-empty post-type group' );
health_expect( array( 1, 2 ), $status->preloads[0][0], 'post IDs are grouped into one preload' );
health_expect( 12, $status->gets, 'preloaded request-local status is read only for objects with canonical copy' );
$empty_rows = array_filter( $inventory['rows'], static function ( $row ) { return 2 === $row['object_id']; } );
health_expect( 0, count( $empty_rows ), 'whitespace and empty canonical content is not offered' );
$position_rows = array_values( array_filter( $inventory['rows'], static function ( $row ) { return 5 === $row['object_id'] && 'meta:position' === $row['field']; } ) );
health_expect( array( 'zh', 'ar', 'de' ), array_column( $position_rows, 'language' ), 'populated Team position is inventoried for every target language' );
health_expect( array( 'missing', 'missing', 'missing' ), array_column( $position_rows, 'status' ), 'untranslated Team position is reported missing' );
$empty_position_rows = array_filter( $inventory['rows'], static function ( $row ) { return 6 === $row['object_id']; } );
health_expect( 0, count( $empty_position_rows ), 'empty Team position does not generate translation work' );
health_expect( 1, $inventory['counts']['taxonomies']['zh']['current'], 'supported taxonomy meta current count' );
health_expect( 1, $inventory['counts']['taxonomies']['ar']['stale'], 'supported taxonomy meta stale count' );
health_expect( 6, $inventory['counts']['taxonomies']['de']['missing'], 'supported name and meta missing counts include category fields' );
$category_rows = array_values( array_filter( $inventory['rows'], static function ( $row ) { return 'taxonomy:category' === $row['object_type']; } ) );
health_expect( 6, count( $category_rows ), 'category name and description create health rows for every target language' );
health_expect( array( 'term_name', 'term_name', 'term_name', 'term_description', 'term_description', 'term_description' ), array_column( $category_rows, 'field' ), 'category health uses existing taxonomy field keys' );
health_expect( array( 'missing', 'missing', 'missing', 'missing', 'missing', 'missing' ), array_column( $category_rows, 'status' ), 'missing category translations are reported naturally' );
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
