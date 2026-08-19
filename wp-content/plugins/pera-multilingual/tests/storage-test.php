<?php
/** Focused standalone stale-cache test: php tests/storage-test.php */

define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['deleted_cache_keys'] = array();
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time() { return '2026-08-19 00:00:00'; }
function wp_cache_delete( $key, $group ) { $GLOBALS['deleted_cache_keys'][] = $group . ':' . $key; return true; }

final class Pera_ML_Test_WPDB {
	public $prefix = 'wp_';
	public $queries = array();
	public function prepare( $query ) { $args = func_get_args(); array_shift( $args ); return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $query ), $args ); }
	public function get_results( $query ) { $this->queries[] = $query; return array( array( 'field_key' => 'post_title', 'language' => 'zh' ), array( 'field_key' => 'post_content', 'language' => 'ar' ) ); }
	public function query( $query ) { $this->queries[] = $query; return 2; }
}

$wpdb = new Pera_ML_Test_WPDB();
require dirname( __DIR__ ) . '/includes/class-storage.php';
$storage = new Pera_ML_Storage();
$result = $storage->mark_object_stale( 'post', 42 );
if ( 2 !== $result || 2 !== count( $GLOBALS['deleted_cache_keys'] ) || false === strpos( $wpdb->queries[1], "status<>'stale'" ) ) {
	fwrite( STDERR, "FAIL stale rows were not updated and evicted\n" ); exit( 1 );
}
echo "Pera ML storage tests passed\n";
