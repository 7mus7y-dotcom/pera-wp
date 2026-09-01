<?php
/** Focused taxonomy SEO/meta and rendered-name regressions. */

define( 'ABSPATH', __DIR__ );
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function apply_filters( $tag, $value ) { return $value; }
function taxonomy_seo_expect( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}
class WP_Term { public $term_id = 42; public $taxonomy = 'category'; public $name = 'Guides'; public $description = 'Canonical description'; }
class Pera_ML_Storage { public static function normalize_field_key( $field ) { return $field; } }
class Taxonomy_Seo_Router { public function current_language() { return 'zh'; } }
class Taxonomy_Seo_Storage {
	public $rows = array();
	public function get( $type, $id, $field, $language, $source ) {
		$key = "{$type}|{$id}|{$field}|{$language}";
		return isset( $this->rows[ $key ] ) ? $this->rows[ $key ] : null;
	}
}
class Taxonomy_Seo_Vocabulary { public function translate( $source, $language ) { return $source; } }

require dirname( __DIR__ ) . '/includes/class-fields.php';
$storage = new Taxonomy_Seo_Storage();
$fields  = new Pera_ML_Fields( new Taxonomy_Seo_Router(), $storage, new Taxonomy_Seo_Vocabulary() );
$term    = new WP_Term();
taxonomy_seo_expect( true, in_array( 'meta:pera_term_excerpt', Pera_ML_Fields::taxonomy_fields( 'category' ), true ), 'live category excerpt is discoverable' );

foreach ( array( 'seo_title' => 'Canonical title', 'seo_meta_description' => 'Canonical meta description' ) as $field => $source ) {
	$key = "term|42|meta:{$field}|zh";
	$storage->rows[ $key ] = array( 'translated_text' => "Translated {$field}", 'status' => 'current', 'is_stale' => false );
	taxonomy_seo_expect( "Translated {$field}", $fields->term_meta( $term, $field, $source ), "current {$field} translation is used" );
	$storage->rows[ $key ]['is_stale'] = true;
	taxonomy_seo_expect( $source, $fields->term_meta( $term, $field, $source ), "stale {$field} falls back to canonical" );
	$storage->rows[ $key ] = array( 'translated_text' => '   ', 'status' => 'current', 'is_stale' => false );
	taxonomy_seo_expect( $source, $fields->term_meta( $term, $field, $source ), "blank {$field} falls back to canonical" );
	$storage->rows[ $key ] = array( 'translated_text' => 'Pending', 'status' => 'pending', 'is_stale' => false );
	taxonomy_seo_expect( $source, $fields->term_meta( $term, $field, $source ), "non-current {$field} falls back to canonical" );
}

$theme = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child/inc/';
$helpers = file_get_contents( $theme . 'seo-helpers.php' );
$seo_all = file_get_contents( $theme . 'seo-all.php' );
$schema = file_get_contents( $theme . 'schema.php' );
$property = file_get_contents( $theme . 'seo-property.php' );
$archive = file_get_contents( $theme . 'seo-property-archive.php' );
taxonomy_seo_expect( true, false !== strpos( $helpers, "pera_ml_term_meta( \$term, 'meta:seo_title', \$manual )" ), 'property taxonomy title uses term-meta helper' );
taxonomy_seo_expect( true, false !== strpos( $helpers, "pera_ml_term_meta( \$term, 'meta:seo_meta_description', \$manual )" ), 'property taxonomy meta description uses term-meta helper' );
taxonomy_seo_expect( true, false !== strpos( $seo_all, "pera_ml_term_meta( \$term, 'meta:' . \$field_name, \$value )" ), 'blog taxonomy SEO fields use term-meta helper' );
foreach ( array( $helpers, $schema, $property ) as $source ) taxonomy_seo_expect( true, false !== strpos( $source, 'pera_ml_term' ), 'rendered taxonomy path invokes term helper' );
taxonomy_seo_expect( true, false !== strpos( $archive, 'pera_seo_term_name' ), 'archive schema uses the translated term-name resolver' );
taxonomy_seo_expect( true, false !== strpos( $helpers, "sanitize_title( (string) \$term->slug )" ), 'slug lookup remains canonical' );
$field_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-fields.php' );
foreach ( array( 'wp_robots', 'wp_head', 'rel="canonical"', 'hreflang=' ) as $hook ) taxonomy_seo_expect( false, false !== strpos( $field_source, $hook ), "{$hook} behavior is outside taxonomy field changes" );

echo "Pera ML taxonomy SEO remediation tests passed\n";
