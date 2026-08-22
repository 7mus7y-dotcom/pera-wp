<?php
/** Property-specific translation contract tests: php tests/property-translation-test.php */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function apply_filters( $tag, $value ) { return $value; }
function add_filter( $tag ) { $GLOBALS['acf_filters'][] = $tag; }
function update_meta_cache() {}
function get_post( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null; }
function get_post_type( $id ) { $post = get_post( $id ); return $post ? $post->post_type : false; }
function get_post_meta( $id, $key ) { return isset( $GLOBALS['meta'][ $id ][ $key ] ) ? $GLOBALS['meta'][ $id ][ $key ] : ''; }
function expect_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
class WP_Post { public $ID; public $post_type; public function __construct( $id, $type ) { $this->ID = $id; $this->post_type = $type; } }
class WP_Term {}
final class Property_WPDB {
	public $prefix = 'wp_'; public $rows = array();
	public function prepare( $query, $args ) { return array( $query, $args ); }
	public function get_results() { return $this->rows; }
}
final class Property_Storage {
	public $rows = array();
	public function get( $type, $id, $field, $language, $source ) { $key = "$id|$field|$language"; if ( ! isset( $this->rows[ $key ] ) || $this->rows[ $key ]['source'] !== $source ) return null; return array( 'translated_text' => $this->rows[ $key ]['translated'] ); }
}
require dirname( __DIR__ ) . '/includes/class-vocabulary.php';
require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translation-status.php';

$facility_values = array(
	'24 7 security', '5 star hotel', 'Art room', 'Basketball courts', 'Bicycle Path', 'Botanic park', 'Café', 'Car rental', 'Central Satellite System', 'Child play areas', 'Child swimming pool', 'Commercial space', 'Concierge', 'Creche', 'Elevator', 'Football courts', 'Forest View', 'Games Room', 'Generator', 'Guest bedrooms', 'Guest Rooms', 'Gym', 'Helipad', 'Hobby room', 'House Keeping', 'Indoor Cinema', 'Indoor swimming pool', 'Kids club', 'Table tennis', 'Lake', 'Lake View', 'Landscaped gardens', 'Lobby', 'Mall', 'Meeting Area', 'Meeting rooms', 'Metro station', 'Music room', 'Open air cinema', 'Ornamental fountains', 'Outdoor exercise area', 'Outdoor Parking', 'Outdoor swimming pool', 'Party room', 'Playstation room', 'Private garden', 'Promenade', 'Reception', 'Recreation areas', 'Restaurant', 'Rooftop restaurant', 'Rooftop terrace', 'Sauna', 'Sea View', 'Skateboarding park', 'Smart home system', 'Spa', 'Sports hall', 'Squash', 'Sunbathing terraces', 'Tennis Court', 'Turkish baths', 'Underfloor heating', 'Underground parking', 'University', 'Valet parking', 'Vehicle Charger Station', 'Vitamin Bar', 'Volleyball courts', 'Walking parkour', 'Water depot', 'Water Park', 'Water Sports', 'Yacht Marina',
);
$vocabulary = new Pera_ML_Vocabulary();
expect_same( 74, count( $facility_values ), 'the production facility fixture contains every canonical value' );
foreach ( $facility_values as $facility ) foreach ( array( 'zh', 'ar', 'de' ) as $language ) {
	expect_same( false, $facility === $vocabulary->translate_for_field( 'facilities', $facility, $language ), $facility . ' has a distinct ' . strtoupper( $language ) . ' controlled translation' );
}
expect_same( 'Unknown production facility', $vocabulary->translate_for_field( 'facilities', 'Unknown production facility', 'de' ), 'unknown facility falls back unchanged' );
expect_same( 'Empfangsbereich', $vocabulary->translate_for_field( 'facilities', 'Reception', 'de' ), 'Reception uses its facility-specific translation' );
expect_same( 'Reception', $vocabulary->translate( 'Reception', 'de' ), 'facility vocabulary does not affect global translation' );

$GLOBALS['posts'][80] = (object) array( 'ID' => 80, 'post_type' => 'property', 'post_title' => '', 'post_content' => '', 'post_excerpt' => '' );
$eight = array( 'project_name', 'custom_text', 'project_summary', 'about_this_project', 'distances', 'custom_video_text', 'seo_title', 'property_faq_text' );
$GLOBALS['meta'][80] = array_fill_keys( Pera_ML_Fields::property_fields(), '' );
foreach ( $eight as $field ) $GLOBALS['meta'][80][ $field ] = 'English ' . $field;
$GLOBALS['meta'][80]['facilities'] = array( 'Indoor swimming pool', 'Gym' );
$GLOBALS['meta'][80]['target_buyer_type'] = array( 'Investor' );
$GLOBALS['meta'][80]['property_key_advantages'] = array( 'Sea View' );
$GLOBALS['meta'][80]['price_list_kd'] = array( array( 'floor_kd' => 'First', 'price_kd' => 100 ) );
$GLOBALS['meta'][80]['bedrooms'] = 3;
$GLOBALS['meta'][80]['gallery'] = array( 10, 11 );
$wpdb = new Property_WPDB();
$status = new Pera_ML_Translation_Status( new stdClass() );
$sources = $status->applicable_sources( 80, 'property' );
expect_same( array_map( static function ( $field ) { return 'meta:' . $field; }, $eight ), array_keys( $sources ), 'only eight populated property meta fields are applicable' );
expect_same( 8, count( $sources ), 'empty optional property fields do not count as missing' );
foreach ( array( 'facilities', 'target_buyer_type', 'property_key_advantages', 'price_list_kd', 'bedrooms', 'gallery' ) as $excluded ) expect_same( false, isset( $sources[ 'meta:' . $excluded ] ), $excluded . ' is excluded from provider inventory' );

$storage = new Property_Storage(); $router = new class { public $language = 'zh'; public function current_language() { return $this->language; } }; $fields = new Pera_ML_Fields( $router, $storage, new Pera_ML_Vocabulary() );
expect_same( false, in_array( 'target_buyer_type', $fields->approved( 'post' ), true ), 'controlled buyer types are absent from the legacy post provider contract' );
expect_same( false, in_array( 'property_key_advantages', $fields->approved( 'post' ), true ), 'controlled advantages are absent from the legacy post provider contract' );
$fields->hooks();
expect_same( true, in_array( 'acf/format_value/name=facilities', $GLOBALS['acf_filters'], true ), 'facilities ACF formatter is registered without joining the provider field registry' );
foreach ( array( 'zh' => '中文', 'ar' => 'عربي', 'de' => 'Deutsch' ) as $language => $translation ) {
	$storage->rows[ '80|meta:project_name|' . $language ] = array( 'source' => 'English project_name', 'translated' => $translation );
	$router->language = $language;
	expect_same( $translation, $fields->acf_value( 'English project_name', new WP_Post( 80, 'property' ), array( 'name' => 'project_name' ) ), strtoupper( $language ) . ' property ACF formatting returns translation' );
}
expect_same( 'English project_name', $GLOBALS['meta'][80]['project_name'], 'canonical English scalar ACF data remains unchanged' );
$router->language = 'zh';
expect_same( array( '室内游泳池', '健身房' ), $fields->acf_value( $GLOBALS['meta'][80]['facilities'], new WP_Post( 80, 'property' ), array( 'name' => 'facilities' ) ), 'facility labels are formatted through controlled vocabulary' );
expect_same( array( 'Investor' => '投资者', 4 => 'Unlisted buyer type' ), $fields->acf_value( array( 'Investor' => 'Investor', 4 => 'Unlisted buyer type' ), new WP_Post( 80, 'property' ), array( 'name' => 'target_buyer_type' ) ), 'controlled arrays preserve keys and unknown values' );
$router->language = 'ar';
expect_same( array( 'إطلالة بحرية', 'جاهز للتسليم' ), $fields->acf_value( array( 'Sea View', 'Key Ready' ), new WP_Post( 80, 'property' ), array( 'name' => 'property_key_advantages' ) ), 'advantage labels are formatted in Arabic' );
$router->language = 'de';
expect_same( array( 'Investor', 'Familie' ), $fields->acf_value( array( 'Investor', 'Family' ), new WP_Post( 80, 'property' ), array( 'name' => 'target_buyer_type' ) ), 'buyer labels are formatted in German' );
expect_same( array( 'Swimming Pool' ), $fields->acf_value( array( 'Swimming Pool' ), new WP_Post( 80, 'post' ), array( 'name' => 'facilities' ) ), 'controlled rendering is property-specific' );
expect_same( array( 10, 11 ), $fields->acf_value( array( 10, 11 ), new WP_Post( 80, 'property' ), array( 'name' => 'gallery' ) ), 'other property arrays remain untouched' );
$router->language = 'en';
expect_same( $GLOBALS['meta'][80]['facilities'], $fields->acf_value( $GLOBALS['meta'][80]['facilities'], new WP_Post( 80, 'property' ), array( 'name' => 'facilities' ) ), 'English formatting returns the original array' );
expect_same( array( 'Indoor swimming pool', 'Gym' ), $GLOBALS['meta'][80]['facilities'], 'canonical checkbox storage remains English' );

$wpdb->rows = array();
$cache_status = new Pera_ML_Translation_Status( new stdClass() );
expect_same( 3, $cache_status->get( 80, 'zh', 'post' )['applicable'], 'default post status uses the post contract' );
expect_same( 8, $cache_status->get( 80, 'zh', 'property' )['applicable'], 'post cache entry cannot contaminate property status for the same object' );

$wpdb->rows = array( array( 'object_id' => 80, 'field_key' => 'meta:project_name', 'language' => 'zh', 'source_hash' => hash( 'sha256', 'Old project name' ), 'translated_text' => '旧名称', 'status' => 'current' ) );
$status = new Pera_ML_Translation_Status( new stdClass() ); $status->preload( array( 80 ), array( 'zh' ), 'property' );
expect_same( array( 'meta:project_name' ), $status->get( 80, 'zh', 'property' )['stale'], 'changed English property source makes its translation stale' );

$GLOBALS['posts'][81] = (object) array( 'ID' => 81, 'post_type' => 'post', 'post_title' => 'Blog title', 'post_content' => 'Blog body', 'post_excerpt' => '' );
$GLOBALS['meta'][81] = array();
expect_same( array( 'post_content', 'post_title' ), array_keys( $status->applicable_sources( 81, 'post' ) ), 'normal blog-post status contract is unchanged' );
echo "Pera ML property translation tests passed\n";
