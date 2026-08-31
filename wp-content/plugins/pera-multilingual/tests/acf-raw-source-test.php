<?php
/** Frontend ACF raw-source regression tests: php tests/acf-raw-source-test.php */
define( 'ABSPATH', __DIR__ );
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function apply_filters( $tag, $value ) { return $value; }
function add_filter( $tag ) { $GLOBALS['active_acf_filter'] = true; }
function remove_filter( $tag ) { $GLOBALS['active_acf_filter'] = false; return true; }
function get_post_type( $id ) { return isset( $GLOBALS['post_types'][ $id ] ) ? $GLOBALS['post_types'][ $id ] : false; }
function get_field( $name, $id, $format_value = true ) {
	$GLOBALS['raw_reads'][] = array( $name, $id, $format_value );
	return $GLOBALS['raw_acf'][ $id ][ $name ];
}
function acf_format_value( $value, $post_id, $field ) {
	if ( ! empty( $GLOBALS['active_acf_filter'] ) ) { fwrite( STDERR, "FAIL translated formatting recursed into the Pera ML filter\n" ); exit( 1 ); }
	$GLOBALS['format_calls'][] = $field['name'];
	if ( 'wysiwyg' === $field['type'] ) return '<p>' . $value . '</p>';
	if ( 'textarea' === $field['type'] ) return str_replace( "\n", '<br />', $value );
	return $value;
}
function expect_acf( $expected, $actual, $label ) {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}
class WP_Post { public $ID; public $post_type; public function __construct( $id, $type ) { $this->ID = $id; $this->post_type = $type; } }
class WP_Term {}
final class Raw_Source_Storage {
	public $rows = array(); public $gets = array();
	public function get( $type, $id, $field, $language, $source ) {
		$this->gets[] = array( $type, $id, $field, $language, $source );
		$key = $id . '|' . $field . '|' . $language;
		if ( ! isset( $this->rows[ $key ] ) ) return null;
		$row = $this->rows[ $key ];
		$row['is_stale'] = $row['source'] !== $source;
		return $row;
	}
}
final class Raw_Source_Vocabulary { public function translate_for_field( $field, $value, $language ) { return $value; } }
require dirname( __DIR__ ) . '/includes/class-fields.php';

$id = 59052;
$GLOBALS['post_types'][ $id ] = 'property';
$GLOBALS['raw_acf'][ $id ] = array(
	'project_summary' => "Canonical summary\nsecond line",
	'property_district_analysis' => "Canonical district\nanalysis",
	'project_name' => 'Canonical project name',
);
$storage = new Raw_Source_Storage();
$router = new class { public $language = 'zh'; public function current_language() { return $this->language; } };
$fields = new Pera_ML_Fields( $router, $storage, new Raw_Source_Vocabulary() );
$fields->hooks();
$post = new WP_Post( $id, 'property' );
$current = static function ( $source, $translated ) { return array( 'source' => $source, 'translated_text' => $translated, 'status' => 'current' ); };
$storage->rows[ "$id|meta:project_summary|zh" ] = $current( $GLOBALS['raw_acf'][ $id ]['project_summary'], '翻译摘要' );
$storage->rows[ "$id|meta:property_district_analysis|zh" ] = $current( $GLOBALS['raw_acf'][ $id ]['property_district_analysis'], "翻译地区\n分析" );
$storage->rows[ "$id|meta:project_name|zh" ] = $current( $GLOBALS['raw_acf'][ $id ]['project_name'], '翻译项目名称' );

expect_acf( '<p>翻译摘要</p>', $fields->acf_value( '<p>Canonical summary<br />second line</p>', $post, array( 'name' => 'project_summary', 'type' => 'wysiwyg' ) ), 'property 59052-style WYSIWYG uses its current raw-source translation and applies WYSIWYG formatting' );
expect_acf( "翻译地区<br />分析", $fields->acf_value( "Canonical district<br />analysis", $post, array( 'name' => 'property_district_analysis', 'type' => 'textarea' ) ), 'textarea uses raw source and retains its ACF formatting layer' );
expect_acf( '翻译项目名称', $fields->acf_value( 'Canonical project name', $post, array( 'name' => 'project_name', 'type' => 'text' ) ), 'normal text uses raw source translation' );
expect_acf( $GLOBALS['raw_acf'][ $id ]['project_summary'], $storage->gets[0][4], 'formatted English is never used for the storage staleness check' );

$GLOBALS['raw_acf'][ $id ]['project_summary'] = 'Changed canonical summary';
expect_acf( '<p>Changed canonical summary</p>', $fields->acf_value( '<p>Changed canonical summary</p>', $post, array( 'name' => 'project_summary', 'type' => 'wysiwyg' ) ), 'changed raw canonical source rejects the old translation' );
$GLOBALS['raw_acf'][ $id ]['project_summary'] = "Canonical summary\nsecond line";
$storage->rows[ "$id|meta:project_summary|zh" ]['status'] = 'pending';
expect_acf( '<p>Canonical summary<br />second line</p>', $fields->acf_value( '<p>Canonical summary<br />second line</p>', $post, array( 'name' => 'project_summary', 'type' => 'wysiwyg' ) ), 'pending translation falls back to already formatted canonical value' );
$storage->rows[ "$id|meta:project_summary|zh" ] = $current( $GLOBALS['raw_acf'][ $id ]['project_summary'], '   ' );
expect_acf( '<p>Canonical summary<br />second line</p>', $fields->acf_value( '<p>Canonical summary<br />second line</p>', $post, array( 'name' => 'project_summary', 'type' => 'wysiwyg' ) ), 'blank translation falls back to canonical value' );
unset( $storage->rows[ "$id|meta:project_summary|zh" ] );
expect_acf( '<p>Canonical summary<br />second line</p>', $fields->acf_value( '<p>Canonical summary<br />second line</p>', $post, array( 'name' => 'project_summary', 'type' => 'wysiwyg' ) ), 'missing translation falls back to canonical value' );
$router->language = 'en';
expect_acf( 'English already formatted', $fields->acf_value( 'English already formatted', $post, array( 'name' => 'project_summary', 'type' => 'wysiwyg' ) ), 'English frontend output is unchanged' );
expect_acf( false, $GLOBALS['raw_reads'][0][2], 'canonical source is fetched with ACF formatting disabled' );
echo "Pera ML ACF raw-source tests passed\n";
