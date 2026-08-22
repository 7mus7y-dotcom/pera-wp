<?php
/** Focused standalone storage tests: php tests/storage-test.php */

define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['pera_ml_test_cache'] = array();
$GLOBALS['deleted_cache_keys'] = array();
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function get_post_type( $id ) { return 'post'; }
function current_time() { return '2026-08-19 00:00:00'; }
function apply_filters( $tag, $value ) { return $value; }
function wp_cache_get( $key, $group ) { $key = $group . ':' . $key; return array_key_exists( $key, $GLOBALS['pera_ml_test_cache'] ) ? $GLOBALS['pera_ml_test_cache'][ $key ] : false; }
function wp_cache_set( $key, $value, $group ) { $GLOBALS['pera_ml_test_cache'][ $group . ':' . $key ] = $value; return true; }
function wp_cache_delete( $key, $group ) { unset( $GLOBALS['pera_ml_test_cache'][ $group . ':' . $key ] ); $GLOBALS['deleted_cache_keys'][] = $group . ':' . $key; return true; }
function expect_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }

final class Pera_ML_Test_WPDB {
	public $prefix = 'wp_';
	public $queries = array();
	public $rows = array();
	public function prepare( $query ) {
		$args = func_get_args(); array_shift( $args );
		if ( 1 === count( $args ) && is_array( $args[0] ) ) $args = $args[0];
		return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $query ), $args );
	}
	public function get_row( $query ) {
		$this->queries[] = $query;
		if ( ! preg_match( "/object_type='([^']+)' AND object_id=(\d+) AND field_key='([^']+)' AND language='([^']+)'/", $query, $matches ) ) return null;
		$key = $matches[1] . '|' . $matches[2] . '|' . $matches[3] . '|' . $matches[4];
		return isset( $this->rows[ $key ] ) ? $this->rows[ $key ] : null;
	}
	public function get_results( $query ) { $this->queries[] = $query; return array( array( 'field_key' => 'post_title', 'language' => 'zh' ), array( 'field_key' => 'post_content', 'language' => 'ar' ) ); }
	public function query( $query ) {
		$this->queries[] = $query;
		if ( 0 === strpos( $query, 'INSERT INTO ' ) && preg_match( '/VALUES \((.*?)\) ON DUPLICATE/s', $query, $matches ) ) {
			$values = str_getcsv( $matches[1], ',', "'" );
			$key = $values[0] . '|' . $values[1] . '|' . $values[2] . '|' . $values[3];
			$this->rows[ $key ] = array(
				'object_type' => $values[0], 'object_id' => (int) $values[1], 'field_key' => $values[2], 'language' => $values[3],
				'source_hash' => $values[4], 'source_text' => $values[5], 'translated_text' => $values[6],
			);
			return 1;
		}
		return 2;
	}
}

$wpdb = new Pera_ML_Test_WPDB();
require dirname( __DIR__ ) . '/includes/class-storage.php';
require dirname( __DIR__ ) . '/includes/class-fields.php';
$storage = new Pera_ML_Storage();

foreach ( array( 'meta:seo_title', 'meta:seo_meta_description', 'meta:seo_faq_v2' ) as $index => $field ) {
	$translation = 'translated-' . $index;
	expect_same( true, $storage->put( 'post', 42, $field, 'zh', 'source-' . $index, $translation ), $field . ' write succeeds' );
	$row = $storage->get( 'post', 42, $field, 'zh', 'source-' . $index );
	expect_same( $field, $row['field_key'], $field . ' retains its structured key' );
	expect_same( $translation, $row['translated_text'], $field . ' round trip returns its translation' );
}

$faq = $storage->get( 'post', 42, 'meta:seo_faq_v2', 'zh', 'source-2' );
expect_same( 'translated-2', $faq['translated_text'], 'SEO FAQ structured-key regression' );

foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'meta:seo_title', 'meta:seo_meta_description', 'meta:seo_faq_v2' ) as $field ) {
	$storage->put( 'post', 43, $field, 'de', 'German source ' . $field, 'German translation ' . $field );
	$row = $storage->get( 'post', 43, $field, 'de', 'German source ' . $field );
	expect_same( 'German translation ' . $field, $row['translated_text'], 'German field round trip: ' . $field );
}

$legacy_key = 'post|99|metaseo_faq_v2|ar';
$wpdb->rows[ $legacy_key ] = array( 'field_key' => 'metaseo_faq_v2', 'source_hash' => hash( 'sha256', 'legacy source' ), 'translated_text' => 'ترجمة قديمة' );
$legacy = $storage->get( 'post', 99, 'meta:seo_faq_v2', 'ar', 'legacy source' );
expect_same( 'ترجمة قديمة', $legacy['translated_text'], 'legacy collapsed key remains readable' );

$router = new class { public $language = 'zh'; public function current_language() { return $this->language; } };
$fields = new Pera_ML_Fields( $router, $storage, new stdClass() );
expect_same( 'translated-2', $fields->get( 42, 'seo_faq_v2', 'source-2' ), 'pera_ml_field path retrieves Chinese ACF value from canonical storage' );
$storage->put( 'post', 42, 'meta:seo_faq_v2', 'ar', 'source-2', 'ترجمة عربية' );
$router->language = 'ar';
expect_same( 'ترجمة عربية', $fields->get( 42, 'seo_faq_v2', 'source-2' ), 'pera_ml_field path retrieves Arabic ACF value from canonical storage' );
$storage->put( 'post', 42, 'meta:seo_faq_v2', 'de', 'source-2', 'Deutsche FAQ' );
$router->language = 'de';
expect_same( 'Deutsche FAQ', $fields->get( 42, 'seo_faq_v2', 'source-2' ), 'German translated metadata uses canonical storage' );

$GLOBALS['deleted_cache_keys'] = array();
$result = $storage->mark_object_stale( 'post', 42 );
expect_same( 2, $result, 'stale rows are updated' );
expect_same( 2, count( $GLOBALS['deleted_cache_keys'] ), 'stale row cache entries are evicted' );
expect_same( true, false !== strpos( end( $wpdb->queries ), "status<>'stale'" ), 'only non-stale rows are updated' );

echo "Pera ML storage tests passed\n";
