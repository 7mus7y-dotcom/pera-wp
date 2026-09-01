<?php
/** Focused ML-PROP-010 contract/frontend regression tests. */
define( 'ABSPATH', __DIR__ );
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function apply_filters( $tag, $value ) { return $value; }
function add_filter() {}
function remove_filter() { return true; }
function get_post_type( $id ) { return 'team'; }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'team', 'post_name' => 'advisor' ); }
function get_field( $name, $id = false, $formatted = true ) {
	$GLOBALS['advisor_get_field_calls'][] = array( $name, $id, $formatted );
	return $GLOBALS['advisor_meta'][ $name ];
}
function get_post_meta( $id, $key ) { return $GLOBALS['advisor_meta'][ $key ]; }
function acf_format_value( $value ) { return $value; }
class WP_Post { public $ID = 42; public $post_type = 'team'; }
class WP_Term {}
function advisor_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); } }
final class Advisor_Storage {
	public $rows = array(); public $gets = array();
	public function get( $type, $id, $field, $language, $source ) {
		$this->gets[] = func_get_args(); $key = $language;
		if ( ! isset( $this->rows[ $key ] ) ) return null;
		$row = $this->rows[ $key ]; $row['is_stale'] = $row['source'] !== $source;
		return $row;
	}
}
final class Advisor_Vocabulary {}
require dirname( __DIR__ ) . '/includes/class-fields.php';

$GLOBALS['advisor_meta'] = array( 'position' => 'Senior Property Consultant', 'name' => 'Ada Example', 'number' => '+90 555 123 4567', 'email' => 'ada@example.test', 'photo' => 99, 'advisor' => 1 );
$GLOBALS['advisor_get_field_calls'] = array();
$storage = new Advisor_Storage();
$router = new class { public $language = 'en'; public function current_language() { return $this->language; } };
$fields = new Pera_ML_Fields( $router, $storage, new Advisor_Vocabulary() );
$approved = $fields->approved( 'team' );
advisor_expect( array( 'position' ), $approved, 'only Team.position is in the team translation contract' );
foreach ( array( 'name', 'number', 'phone', 'email', 'whatsapp', 'photo', 'advisor', 'ID' ) as $excluded ) advisor_expect( false, in_array( $excluded, $approved, true ), "{$excluded} remains canonical" );
foreach ( array( 'post', 'page', 'property' ) as $unrelated_type ) advisor_expect( false, in_array( 'position', $fields->approved( $unrelated_type ), true ), "position is not approved for {$unrelated_type}" );
foreach ( array( '_wp_attachment_image_alt', 'image_alt', 'gallery_image_alt', 'floor_plan_alt' ) as $media_alt ) advisor_expect( false, in_array( $media_alt, $fields->approved(), true ), "{$media_alt} is excluded by project decision" );
foreach ( array( 'position_zh', 'position_ar', 'position_de', 'zh_position', 'ar_position', 'de_position' ) as $language_field ) advisor_expect( false, in_array( $language_field, $fields->approved(), true ), "{$language_field} is not a language-specific ACF field" );

$current = static function ( $language ) { return array( 'source' => 'Senior Property Consultant', 'translated_text' => strtoupper( $language ) . ' position', 'status' => 'current' ); };
foreach ( array( 'zh', 'ar', 'de' ) as $language ) { $storage->rows[ $language ] = $current( $language ); $router->language = $language; advisor_expect( strtoupper( $language ) . ' position', $fields->acf_value( 'Senior Property Consultant', new WP_Post(), array( 'name' => 'position', 'type' => 'text' ) ), "current {$language} position renders" ); }
$router->language = 'de'; unset( $storage->rows['de'] ); advisor_expect( 'Senior Property Consultant', $fields->acf_value( 'Senior Property Consultant', new WP_Post(), array( 'name' => 'position', 'type' => 'text' ) ), 'missing falls back to English' );
$storage->rows['de'] = array( 'source' => 'Old title', 'translated_text' => 'Alt', 'status' => 'current' ); advisor_expect( 'Senior Property Consultant', $fields->acf_value( 'Senior Property Consultant', new WP_Post(), array( 'name' => 'position', 'type' => 'text' ) ), 'stale falls back to English' );
$storage->rows['de'] = array( 'source' => 'Senior Property Consultant', 'translated_text' => ' ', 'status' => 'current' ); advisor_expect( 'Senior Property Consultant', $fields->acf_value( 'Senior Property Consultant', new WP_Post(), array( 'name' => 'position', 'type' => 'text' ) ), 'blank falls back to English' );
$storage->rows['de'] = array( 'source' => 'Senior Property Consultant', 'translated_text' => 'Alt', 'status' => 'pending' ); advisor_expect( 'Senior Property Consultant', $fields->acf_value( 'Senior Property Consultant', new WP_Post(), array( 'name' => 'position', 'type' => 'text' ) ), 'non-current falls back to English' );
$router->language = 'en'; advisor_expect( 'Senior Property Consultant', $fields->acf_value( 'Senior Property Consultant', new WP_Post(), array( 'name' => 'position', 'type' => 'text' ) ), 'English is unchanged' );
advisor_expect( false, $storage->gets[0][4] !== 'Senior Property Consultant', 'lookup hashes the canonical raw ACF source' );
advisor_expect( true, in_array( array( 'position', 42, false ), $GLOBALS['advisor_get_field_calls'], true ), 'ACF lookup explicitly reads the raw canonical position source' );

$template = file_get_contents( dirname( __DIR__, 2 ) . '/../themes/hello-elementor-child/single-property.php' );
$about_template = file_get_contents( dirname( __DIR__, 2 ) . '/../themes/hello-elementor-child/page-about-new.php' );
advisor_expect( true, false !== strpos( $template, "get_field( 'advisors', \$property_id )" ), 'property relationship selects advisors' );
advisor_expect( 1, preg_match( "/'post_type'\s*=>\s*'team'/", $template ), 'fallback selects Team posts' );
advisor_expect( true, false !== strpos( $template, "get_field( 'position', \$advisor_id )" ), 'position belongs to the selected Team object' );
advisor_expect( true, false !== strpos( $about_template, "get_field( 'position' )" ), 'the public Team card uses the same ACF translation hook' );
echo "Pera ML advisor position tests passed\n";
