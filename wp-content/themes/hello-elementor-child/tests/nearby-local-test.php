<?php
/** Simulated wpdb contracts, not a MySQL performance or real coverage claim. */
require __DIR__ . '/nearby-amenities-test.php';
define( 'ARRAY_A', 'ARRAY_A' );
$test_provider = 'local';
class Local_Test_DB {
    public $prefix = 'wp_', $options = 'wp_options', $last_error = '', $tables = array(), $parameters, $queries = 0, $peak_batch = 0, $fail_insert = false;
    function prepare( $sql, ...$args ) { $this->parameters = $args; return $sql; }
    function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    function query( $sql ) {
        $this->last_error = '';
        if ( preg_match( '/CREATE TABLE `([^`]+)`/', $sql, $m ) ) { $this->tables[$m[1]] = array(); return 1; }
        if ( preg_match( '/DROP TABLE IF EXISTS `([^`]+)`/', $sql, $m ) ) { unset( $this->tables[$m[1]] ); return 1; }
        if ( strpos( $sql, 'DELETE FROM wp_options' ) === 0 ) { global $options; if ( ( $options[$this->parameters[0]] ?? null ) == $this->parameters[1] ) unset( $options[$this->parameters[0]] ); return 1; }
        throw new RuntimeException( 'Unexpected SQL ' . $sql );
    }
    function insert( $table, $row, $formats ) {
        if ( $this->fail_insert || isset( $this->tables[$table][$row['osm_id']] ) ) return false;
        $this->tables[$table][$row['osm_id']] = $row; return 1;
    }
    function get_var( $sql ) {
        preg_match( '/FROM `([^`]+)`/', $sql, $m );
        if ( ! isset( $this->tables[$m[1]] ) ) { $this->last_error = 'missing table'; return null; }
        $this->last_error = ''; return count( $this->tables[$m[1]] );
    }
    function get_results( $sql, $format ) {
        $this->queries++; $this->last_error = '';
        preg_match( '/FROM `([^`]+)`/', $sql, $m );
        if ( ! isset( $this->tables[$m[1]] ) ) { $this->last_error = 'missing table'; return array(); }
        list( $lat, $lat_again, $lng, $group, $south, $north, $west, $east, $offset ) = $this->parameters;
        $rows = array_filter( $this->tables[$m[1]], function ( $r ) use ( $group, $south, $north, $west, $east ) {
            return $r['poi_group'] === $group && $r['lat'] >= $south && $r['lat'] <= $north && ( $west <= $east ? $r['lng'] >= $west && $r['lng'] <= $east : $r['lng'] >= $west || $r['lng'] <= $east );
        } );
        usort( $rows, function ( $a, $b ) use ( $lat, $lng ) { return pera_nearby_distance( array( $lat, $lng ), array( $a['lat'], $a['lng'] ) ) <=> pera_nearby_distance( array( $lat, $lng ), array( $b['lat'], $b['lng'] ) ); } );
        $rows = array_slice( $rows, $offset, 256 ); $this->peak_batch = max( $this->peak_batch, count( $rows ) ); return $rows;
    }
}
$wpdb = new Local_Test_DB(); $options = array(); $events = array(); $requests = array();
function artifact( array $records, array $override = array() ) {
    $body = ''; foreach ( $records as $record ) $body .= json_encode( $record, JSON_UNESCAPED_UNICODE ) . "\n";
    $meta = array_merge( array( 'schema' => 1, 'license' => 'ODbL-1.0', 'source' => 'synthetic fixture', 'generated_at' => gmdate( 'c' ), 'version' => 'test', 'count' => count( $records ), 'sha256' => hash( 'sha256', $body ) ), $override );
    $file = tempnam( sys_get_temp_dir(), 'poi-test-' ); file_put_contents( $file, json_encode( $meta ) . "\n" . $body ); return $file;
}
function import_records( $records, $meta = array() ) { $file = artifact( $records, $meta ); try { return pera_nearby_import( $file ); } finally { unlink( $file ); } }
function must_fail_import( $records, $meta = array() ) {
    global $options, $wpdb; $before = $options['pera_nearby_dataset'] ?? null; $tables = count( $wpdb->tables );
    try { import_records( $records, $meta ); throw new LogicException( 'Expected failure' ); } catch ( RuntimeException $expected ) {}
    check( ( $options['pera_nearby_dataset'] ?? null ) === $before && count( $wpdb->tables ) === $tables, 'Failed import retains pointer and cleans staging' );
}
check( pera_nearby_cached( array( 41, 29 ) ) === array() && ! $events && ! $requests, 'Missing local dataset neither fetches nor schedules' );
check( pera_nearby_refresh( array( 41, 29 ) ) === 'failed' && ! $requests, 'Previously queued refresh cannot make an external request by default' );
$fixture = array( 'id' => 'node/1', 'group' => 'schools', 'point' => array( 41.06, 28.99 ), 'names' => array( 'name' => 'Synthetic school', 'name:tr' => 'Örnek okul', 'name:ar' => 'مدرسة' ) );
$first = import_records( array( $fixture ) );
check( count( pera_nearby_cached( $fixture['point'] ) ) === 1, 'Published local record returned' );
$invalid = $fixture; $invalid['point'][0] = 91; must_fail_import( array( $invalid ) );
$invalid = $fixture; $invalid['names']['name'] = array(); must_fail_import( array( $invalid ) );
$invalid = $fixture; $invalid['id'] = array(); must_fail_import( array( $invalid ) );
must_fail_import( array( $fixture, $fixture ) );
must_fail_import( array( $fixture ), array( 'sha256' => str_repeat( '0', 64 ) ) );
must_fail_import( array( $fixture ), array( 'count' => 10 ) );
must_fail_import( array( $fixture ), array( 'schema' => 2 ) );
$wpdb->fail_insert = true; must_fail_import( array( $fixture ) ); $wpdb->fail_insert = false;
$empty = import_records( array() ); check( ! pera_nearby_cached( $fixture['point'] ), 'Valid empty artifact is explicit, not fabricated' );
pera_nearby_rollback(); check( pera_nearby_dataset_state() === $first, 'Rollback restores prior generation atomically' );
$source = tempnam( sys_get_temp_dir(), 'bad-poi-' ); file_put_contents( $source, 'broken' );
try { pera_nearby_import( $source ); throw new LogicException( 'Expected invalid header' ); } catch ( RuntimeException $expected ) {} finally { unlink( $source ); }
$records = array(); $id = 10;
// Synthetic offsets around real district coordinates; they establish geography contracts only.
foreach ( array( 'Sisli' => array( 41.060, 28.987 ), 'Besiktas' => array( 41.043, 29.009 ), 'Kadikoy' => array( 40.990, 29.025 ), 'Zeytinburnu' => array( 40.994, 28.903 ) ) as $district => $point ) {
    for ( $i = 0; $i < 5; $i++ ) { $p = $fixture; $p['id'] = 'node/' . $id++; $p['point'] = array( $point[0] + $i * .001, $point[1] ); $p['names']['name'] = $district . ' fixture ' . $i; $records[] = $p; }
    $duplicate = end( $records ); $duplicate['id'] = 'way/' . $id++; $records[] = $duplicate;
    $outside = $fixture; $outside['id'] = 'node/' . $id++; $outside['point'] = array( $point[0] + .04, $point[1] ); $records[] = $outside;
}
import_records( $records );
foreach ( array( array( 41.060, 28.987 ), array( 41.043, 29.009 ), array( 40.990, 29.025 ), array( 40.994, 28.903 ) ) as $point ) {
    $groups = pera_nearby_groups( pera_nearby_cached( $point ), $point );
    check( count( $groups['schools'] ) === 3 && $groups['schools'][0]['distance'] === 0.0 && $groups['schools'][1]['distance'] <= $groups['schools'][2]['distance'], 'Representative district nearest-three contract' );
    $box = pera_nearby_bounds( $point ); check( $box[0] < $point[0] && $box[1] > $point[0] && $box[2] < $point[1] && $box[3] > $point[1], 'Bounding box contains origin' );
}
check( pera_nearby_bounds( array( 90, 0 ) )[2] === -180 && pera_nearby_bounds( array( 0, 179.999 ) )[3] > 180, 'Polar and antimeridian bounds' );
foreach ( array( 'en', 'tr', 'ar' ) as $language ) {
    ob_start(); pera_render_nearby_amenities( array( 'lat' => 41.06, 'lng' => 28.987 ) ); $html = ob_get_clean();
    check( substr_count( $html, 'role="tab"' ) === 5 && strpos( $html, $language === 'ar' ? 'مدرسة' : ( $language === 'tr' ? 'Örnek okul' : 'Sisli fixture 0' ) ) !== false, 'Local output preserves tabs and multilingual names' );
}
check( ! $requests && ! $events, 'Local rendering and refresh make no external HTTP or cron jobs' );
$table = pera_nearby_dataset_state()['table']; unset( $wpdb->tables[$table] );
check( ! pera_nearby_cached( array( 41, 29 ) ), 'Missing table fails gracefully' );
ob_start(); pera_render_nearby_amenities( array( 'lat' => 41, 'lng' => 29 ) ); check( strpos( ob_get_clean(), '(SAW)' ) !== false, 'Airports remain independently available' );
check( $wpdb->peak_batch <= 256, 'Query result memory bounded to 256 records' );
echo "Local provider/import/rollback contracts passed (simulated wpdb, synthetic district fixtures).\n";

// Generate a large query stream without storing the full dataset in the test process.
class Streaming_Test_DB extends Local_Test_DB {
    function get_results( $sql, $format ) {
        $this->queries++; $this->last_error = '';
        $group = $this->parameters[3]; $offset = $this->parameters[8];
        if ( $group !== 'bus' ) return array();
        $rows = array();
        for ( $i = $offset; $i < min( 100002, $offset + 256 ); $i++ ) $rows[] = array( 'osm_id' => 'node/' . ( $i + 1 ), 'lat' => 41 + min( $i, 2 ) * .000001, 'lng' => 29, 'names' => json_encode( array( 'name' => $i < 100000 ? 'Duplicate fixture' : 'Distinct fixture ' . $i ) ) );
        $this->peak_batch = max( $this->peak_batch, count( $rows ) ); return $rows;
    }
}
$wpdb = new Streaming_Test_DB();
$before = memory_get_usage( true ); $started = microtime( true );
$places = pera_nearby_cached( array( 41, 29 ) );
$extra = memory_get_peak_usage( true ) - $before;
check( count( $places ) === 3 && $wpdb->peak_batch === 256 && $extra < 16 * 1024 * 1024, '100k streamed candidates retain three places under 16 MiB incremental peak' );
echo json_encode( array( 'simulated_candidates' => 100002, 'queries' => $wpdb->queries, 'max_batch' => $wpdb->peak_batch, 'incremental_peak_bytes' => $extra, 'seconds' => round( microtime( true ) - $started, 3 ), 'boundary' => 'Generated wpdb rows; excludes MySQL cost and real OSM coverage.' ) ) . "\n";
