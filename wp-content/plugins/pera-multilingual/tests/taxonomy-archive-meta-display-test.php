<?php
/** Focused regressions for approved taxonomy archive metadata display. */

define( 'ABSPATH', __DIR__ );

function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function apply_filters( $tag, $value ) { return $value; }
function expect_taxonomy_archive_meta( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

class WP_Term {
	public $term_id;
	public $taxonomy;
	public $name = '';
	public $description = '';
	public function __construct( $term_id, $taxonomy ) { $this->term_id = $term_id; $this->taxonomy = $taxonomy; }
}
class Pera_ML_Storage {
	public static function normalize_field_key( $field ) { return $field; }
}
class Taxonomy_Archive_Router {
	public $language = 'zh';
	public function current_language() { return $this->language; }
}
class Taxonomy_Archive_Storage {
	public $rows = array();
	public function get( $type, $id, $field, $language, $source ) {
		$key = "{$type}:{$id}:{$field}:{$language}";
		return isset( $this->rows[ $key ] ) ? $this->rows[ $key ] : null;
	}
}
class Taxonomy_Archive_Vocabulary {}

require dirname( __DIR__ ) . '/includes/class-fields.php';

$router  = new Taxonomy_Archive_Router();
$storage = new Taxonomy_Archive_Storage();
$fields  = new Pera_ML_Fields( $router, $storage, new Taxonomy_Archive_Vocabulary() );

$approved = array(
	'district'      => array( 'meta:district_archive_subtitle', 'meta:district_archive_body' ),
	'region'        => array( 'meta:archive_subtitle', 'meta:archive_body_content' ),
	'property_tags' => array( 'meta:archive_subtitle', 'meta:archive_body_content' ),
);

$term_id = 10;
foreach ( $approved as $taxonomy => $field_names ) {
	$term = new WP_Term( $term_id++, $taxonomy );
	foreach ( $field_names as $field_name ) {
		$source = "English {$taxonomy} {$field_name}";
		$key = "term:{$term->term_id}:{$field_name}:zh";
		$storage->rows[ $key ] = array(
			'translated_text' => "Translated {$taxonomy} {$field_name}",
			'status'          => 'current',
			'is_stale'        => 0,
		);
		expect_taxonomy_archive_meta( $storage->rows[ $key ]['translated_text'], $fields->term_meta( $term, $field_name, $source ), "{$taxonomy} {$field_name} uses its approved translation" );

		$router->language = 'en';
		expect_taxonomy_archive_meta( $source, $fields->term_meta( $term, $field_name, $source ), "{$taxonomy} {$field_name} preserves its canonical English source" );
		$router->language = 'zh';
	}
}

$district = new WP_Term( 20, 'district' );
$source   = 'Canonical district subtitle';
expect_taxonomy_archive_meta( $source, $fields->term_meta( $district, 'meta:district_archive_subtitle', $source ), 'missing translations fall back to the canonical source' );
$storage->rows['term:20:meta:district_archive_subtitle:zh'] = array( 'translated_text' => 'Stale subtitle', 'status' => 'stale', 'is_stale' => 1 );
expect_taxonomy_archive_meta( $source, $fields->term_meta( $district, 'meta:district_archive_subtitle', $source ), 'stale translations fall back to the canonical source' );

$expected_contract = array(
	'district'      => array( 'term_name', 'term_description', 'meta:seo_faq_v2', 'meta:district_archive_subtitle', 'meta:district_archive_body' ),
	'region'        => array( 'term_name', 'term_description', 'meta:seo_faq_v2', 'meta:archive_subtitle', 'meta:archive_body_content' ),
	'property_tags' => array( 'term_name', 'term_description', 'meta:seo_faq_v2', 'meta:archive_subtitle', 'meta:archive_body_content' ),
);
foreach ( $expected_contract as $taxonomy => $contract ) {
	expect_taxonomy_archive_meta( $contract, Pera_ML_Fields::taxonomy_fields( $taxonomy ), "{$taxonomy} taxonomy contract was not expanded" );
}

// Template wiring is kept deliberately narrow; helper behaviour above is tested independently.
$template = file_get_contents( dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child/archive-property.php' );
foreach ( array_merge( ...array_values( $approved ) ) as $field_name ) {
	expect_taxonomy_archive_meta( true, false !== strpos( $template, "pera_ml_term_meta( \$qo, '{$field_name}'," ), "archive template routes {$field_name} through pera_ml_term_meta" );
}

echo "Pera ML taxonomy archive metadata display tests passed\n";
