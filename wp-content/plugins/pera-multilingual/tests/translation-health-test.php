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
	if ( 'post_tag' === $args['taxonomy'] ) return array( (object) array( 'term_id'=>103, 'name'=>'Investment', 'description'=>'Canonical tag description' ) );
	return array();
}
$GLOBALS['term_meta'] = array(
	42 => array( 'archive_subtitle'=>'Subtitle', 'archive_body_content'=>'Body', 'seo_faq_v2'=>"Question|Answer", 'arbitrary_private_copy'=>'Ignore me' ),
	102 => array( 'seo_title'=>'Category SEO', 'archive_h1'=>'Buyer guides H1', 'archive_subtitle'=>'   ', 'seo_social_image'=>99, 'featured_guide_links'=>array( 7 ) ),
);
function get_term_meta( $id, $key ) { return isset( $GLOBALS['term_meta'][ $id ][ $key ] ) ? $GLOBALS['term_meta'][ $id ][ $key ] : ''; }
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
		if ( 102 === $id && 'meta:seo_title' === $field && 'zh' === $language ) return array( 'translated_text'=>'分类 SEO', 'is_stale'=>false, 'status'=>'current' );
		if ( 102 === $id && 'meta:archive_h1' === $field && 'zh' === $language ) return array( 'translated_text'=>'旧标题', 'is_stale'=>true, 'status'=>'stale' );
		return null;
	}
}
final class Health_Languages { public function enabled() { return array( 'en'=>array('source'=>true), 'zh'=>array('source'=>false), 'ar'=>array('source'=>false), 'de'=>array('source'=>false) ); } }
require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translation-health.php';
$fields_service = new Pera_ML_Fields( null, null, null );
health_expect( array( 'seo_title', 'seo_meta_description', 'seo_faq_v2', 'homepage_hero_subtext', 'homepage_listing_intro', 'homepage_bottom_seo_text' ), $fields_service->approved( 'page' ), 'page health meta is readable through the frontend field contract' );
foreach ( array( 'district', 'region', 'property_type', 'property_tags', 'special' ) as $taxonomy ) health_expect( true, in_array( 'meta:seo_faq_v2', Pera_ML_Fields::taxonomy_fields( $taxonomy ), true ), $taxonomy . ' FAQ is in the taxonomy contract' );
health_expect( true, in_array( 'meta:seo_faq_v2', Pera_ML_Fields::taxonomy_fields( 'category' ), true ), 'category FAQ uses the shared structured FAQ field' );
health_expect( true, in_array( 'category', Pera_ML_Fields::supported_taxonomies(), true ), 'category is in the supported taxonomy inventory' );
health_expect( true, in_array( 'post_tag', Pera_ML_Fields::supported_taxonomies(), true ), 'post_tag is in the shared supported taxonomy inventory' );
$category_contract = array( 'term_name', 'term_description', 'meta:seo_title', 'meta:seo_meta_description', 'meta:archive_h1', 'meta:archive_subtitle', 'meta:archive_intro_content', 'meta:archive_bottom_content', 'meta:featured_links_heading', 'meta:featured_links_intro', 'meta:archive_cta_heading', 'meta:archive_cta_text', 'meta:archive_whatsapp_message', 'meta:seo_faq_v2' );
health_expect( $category_contract, Pera_ML_Fields::taxonomy_fields( 'category' ), 'category has its deliberate visitor-facing text contract' );
health_expect( false, in_array( 'meta:seo_social_image', $category_contract, true ), 'category media is excluded' );
health_expect( false, in_array( 'meta:featured_guide_links', $category_contract, true ), 'category relationships are excluded' );
health_expect( array( 'term_name', 'term_description' ), Pera_ML_Fields::taxonomy_fields( 'post_tag' ), 'post_tag uses the existing name and description contract' );
$status = new Health_Status();
$inventory = ( new Pera_ML_Translation_Health( $status, new Health_Storage(), new Health_UI(), new Health_Languages() ) )->inventory();
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
health_expect( 2, $inventory['counts']['taxonomies']['zh']['current'], 'supported taxonomy and category meta current count' );
health_expect( 1, $inventory['counts']['taxonomies']['ar']['stale'], 'supported taxonomy meta stale count' );
health_expect( 10, $inventory['counts']['taxonomies']['de']['missing'], 'supported taxonomy missing counts include populated category fields' );
$category_rows = array_values( array_filter( $inventory['rows'], static function ( $row ) { return 'taxonomy:category' === $row['object_type']; } ) );
health_expect( 12, count( $category_rows ), 'populated category text fields create health rows for every target language' );
health_expect( array( 'term_name', 'term_name', 'term_name', 'term_description', 'term_description', 'term_description', 'meta:seo_title', 'meta:seo_title', 'meta:seo_title', 'meta:archive_h1', 'meta:archive_h1', 'meta:archive_h1' ), array_column( $category_rows, 'field' ), 'category health inventories each populated canonical field for every target language' );
health_expect( array( 'missing', 'missing', 'missing', 'missing', 'missing', 'missing', 'current', 'missing', 'missing', 'stale', 'missing', 'missing' ), array_column( $category_rows, 'status' ), 'category ACF translations distinguish missing, stale, and current states' );
$category_state = ( new Pera_ML_Translation_Health( $status, new Health_Storage(), new Health_UI(), new Health_Languages() ) )->term_status( get_terms( array( 'taxonomy' => 'category' ) )[0], 'category', 'zh' );
health_expect( 1, $category_state['current'], 'populated category ACF field can be current' );
health_expect( array( 'meta:archive_h1' ), $category_state['stale'], 'changed category ACF source is stale' );
health_expect( true, in_array( 'term_name', $category_state['missing'], true ), 'untranslated category source is missing' );
$tag_rows = array_values( array_filter( $inventory['rows'], static function ( $row ) { return 'taxonomy:post_tag' === $row['object_type']; } ) );
health_expect( 6, count( $tag_rows ), 'post_tag name and description create health rows for every target language' );
$faq_rows = array_filter( $inventory['rows'], static function ( $row ) { return 'meta:seo_faq_v2' === $row['field']; } );
health_expect( array( 'zh', 'ar', 'de' ), array_values( array_column( $faq_rows, 'language' ) ), 'canonical taxonomy FAQ creates one health row per target language' );
health_expect( array( 'missing', 'missing', 'missing' ), array_values( array_column( $faq_rows, 'status' ) ), 'untranslated taxonomy FAQ rows are missing' );
$GLOBALS['term_meta'][42]['seo_faq_v2'] = '   ';
$empty_inventory = ( new Pera_ML_Translation_Health( $status, new Health_Storage(), new Health_UI(), new Health_Languages() ) )->inventory();
$empty_faq_rows = array_filter( $empty_inventory['rows'], static function ( $row ) { return 'meta:seo_faq_v2' === $row['field']; } );
health_expect( 0, count( $empty_faq_rows ), 'empty taxonomy FAQ does not create missing rows' );
$fields = array_column( $inventory['rows'], 'field' );
health_expect( false, in_array( 'meta:arbitrary_private_copy', $fields, true ), 'unsupported arbitrary term meta is ignored' );
echo "Pera ML translation health tests passed\n";
