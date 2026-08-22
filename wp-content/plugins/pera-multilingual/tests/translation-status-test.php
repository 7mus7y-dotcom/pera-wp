<?php
/** Focused standalone status tests: php tests/translation-status-test.php */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function apply_filters( $tag, $value ) { return $value; }
function update_meta_cache() { $GLOBALS['meta_cache_calls']++; }
function get_post( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null; }
function get_post_meta( $id, $key ) { return isset( $GLOBALS['meta'][ $id ][ $key ] ) ? $GLOBALS['meta'][ $id ][ $key ] : ''; }
function expect_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
final class Status_WPDB {
	public $prefix = 'wp_'; public $rows = array(); public $queries = 0;
	public function prepare( $query, $args ) { return array( $query, $args ); }
	public function get_results( $prepared ) { $this->queries++; $args = $prepared[1]; $languages = array_slice( $args, 1 + count( $GLOBALS['posts'] ) ); return array_values( array_filter( $this->rows, static function ( $row ) use ( $languages ) { return in_array( $row['language'], $languages, true ); } ) ); }
}
function row( $id, $field, $language, $source, $status = 'current' ) { return array( 'object_id' => $id, 'field_key' => $field, 'language' => $language, 'source_hash' => hash( 'sha256', $source ), 'translated_text' => 'translated', 'status' => $status ); }
$GLOBALS['posts'] = array(
	1 => (object) array( 'post_content' => 'Body', 'post_title' => 'Title', 'post_excerpt' => '' ),
	2 => (object) array( 'post_content' => 'Body 2', 'post_title' => 'Title 2', 'post_excerpt' => 'Excerpt' ),
);
$GLOBALS['meta'] = array( 1 => array(), 2 => array( 'seo_title' => 'SEO', 'seo_meta_description' => '', 'seo_faq_v2' => '' ) );
$GLOBALS['meta_cache_calls'] = 0; $wpdb = new Status_WPDB();
require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translation-status.php';
$status = new Pera_ML_Translation_Status( new stdClass() );
$status->preload( array( 1, 2 ), array( 'zh', 'ar' ) );
expect_same( 1, $wpdb->queries, 'all visible objects and languages use one translation query' );
expect_same( 1, $GLOBALS['meta_cache_calls'], 'post meta is preloaded once' );
expect_same( array( 'post_content', 'post_title' ), $status->get( 1, 'zh' )['missing'], 'no translations are missing' );
expect_same( 2, $status->get( 1, 'zh' )['applicable'], 'empty optional English fields are excluded' );

$wpdb->rows = array(
	row( 1, 'post_content', 'zh', 'Body' ), row( 1, 'post_title', 'zh', 'Title' ),
	row( 1, 'post_content', 'ar', 'Body' ),
	row( 2, 'post_content', 'zh', 'Body 2', 'stale' ), row( 2, 'post_title', 'zh', 'old title' ),
	row( 2, 'post_excerpt', 'zh', 'Excerpt' ), row( 2, 'meta:seo_title', 'zh', 'SEO' ),
	row( 2, 'metaseo_title', 'ar', 'SEO' ),
);
$status = new Pera_ML_Translation_Status( new stdClass() ); $status->preload( array( 1, 2 ), array( 'zh', 'ar' ) );
expect_same( true, $status->get( 1, 'zh' )['complete'], 'complete Chinese translation' );
expect_same( array( 'post_title' ), $status->get( 1, 'ar' )['missing'], 'Chinese and Arabic are independent' );
$zh = $status->get( 2, 'zh' );
expect_same( array( 'post_content', 'post_title' ), $zh['stale'], 'explicit and hash-mismatch rows are stale' );
expect_same( 2, $zh['current'], 'current count excludes stale rows' );
expect_same( array(), $zh['missing'], 'stale rows exist and are not missing' );
expect_same( false, $zh['complete'], 'stale coverage is not complete/current' );
$ar = $status->get( 2, 'ar' );
expect_same( 1, $ar['current'], 'legacy collapsed structured meta key is recognized' );
expect_same( true, in_array( 'meta:seo_title', array_diff( array_keys( array( 'post_content'=>1, 'post_title'=>1, 'post_excerpt'=>1, 'meta:seo_title'=>1 ) ), $ar['missing'] ), true ), 'legacy row fulfills canonical structured key' );
expect_same( 2, $wpdb->queries, 'status calculation made no provider calls and only one query per preload' );
echo "Pera ML translation status tests passed\n";
