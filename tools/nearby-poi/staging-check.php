<?php
/** Run with WP-CLI eval-file against disposable/staging WordPress+MariaDB only. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) exit( 2 );
if ( ! defined( 'PERA_POI_STAGING_CHECK' ) || ! PERA_POI_STAGING_CHECK ) throw new RuntimeException( 'Define PERA_POI_STAGING_CHECK=true on the disposable acceptance installation.' );
global $wpdb; // eval-file runs inside a method, not the WordPress global scope.
$output = getenv( 'POI_DB_REPORT' );
if ( ! $output ) throw new RuntimeException( 'Set POI_DB_REPORT to a local report path.' );
$metrics = array();
// eval-file evaluates this source inside WP-CLI; __DIR__ is not this script's directory.
$code_root = getenv( 'POI_CODE_ROOT' );
file_put_contents( $output, wp_json_encode( array( 'schema' => 1, 'passed' => false, 'error' => 'Acceptance interrupted before completion.' ) ) . "\n" );
try {
if ( ! $code_root || ! is_file( $code_root . '/wp-content/themes/hello-elementor-child/inc/modules/nearby-amenities.php' ) ) throw new RuntimeException( 'Set POI_CODE_ROOT to the checked-out repository root.' );
function pera_ml_ui( $text, $key ) { return $text; }
function pera_get_asset_version( $path ) { return 'acceptance'; }
require $code_root . '/wp-content/themes/hello-elementor-child/inc/modules/nearby-amenities.php';
$http_calls = 0;
add_filter( 'pre_http_request', function () use ( &$http_calls ) { $http_calls++; return new WP_Error( 'acceptance_http', 'Unexpected rendering HTTP' ); } );
$artifact = getenv( 'POI_ARTIFACT' ); $report = getenv( 'POI_REPORT' );
$previous = getenv( 'POI_BASELINE' ); $previous_report = getenv( 'POI_BASELINE_REPORT' );
if ( $previous && is_file( $previous ) ) {
    $active = pera_nearby_import( $previous );
    $active['validation'] = json_decode( file_get_contents( $previous_report ), true );
    $active['imported_at'] = gmdate( 'c' );
    update_option( 'pera_nearby_dataset', array( 'active' => $active, 'previous' => null ), false );
}
$time = microtime( true );
while ( ! ( $import = pera_nearby_import_batch( $artifact, $report, 1000 ) )['complete'] ) {}
$import_seconds = microtime( true ) - $time;
$active = pera_nearby_dataset_state(); $table = $active['table'];
$wpdb->query( "ANALYZE TABLE `$table`" );
$policy = json_decode( file_get_contents( $code_root . '/wp-content/themes/hello-elementor-child/inc/data/nearby-poi-policy.json' ), true );
$metrics = array();
foreach ( $policy['districts'] as $district => $point ) {
    // Independent reference: geographic candidate read without calculated-distance SQL ordering,
    // followed by the original PHP radius/sort/dedup grouping. Only staging uses this full candidate array.
    $bounds = pera_nearby_bounds( $point ); $reference = array(); $after = '';
    do {
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT osm_id,poi_group,lat,lng,names FROM `$table` WHERE lat BETWEEN %s AND %s AND lng BETWEEN %s AND %s AND osm_id > %s ORDER BY osm_id LIMIT 256", json_encode( $bounds[0] ), json_encode( $bounds[1] ), json_encode( $bounds[2] ), json_encode( $bounds[3] ), $after ), ARRAY_A );
        if ( $wpdb->last_error ) throw new RuntimeException( $wpdb->last_error );
        foreach ( $rows as $row ) {
            $after = $row['osm_id']; $reference[] = array( 'id' => $row['osm_id'], 'group' => $row['poi_group'], 'point' => array( (float) $row['lat'], (float) $row['lng'] ), 'names' => json_decode( $row['names'], true ) );
        }
    } while ( count( $rows ) === 256 );
    $expected = pera_nearby_groups( $reference, $point ); unset( $reference );
    $times = array(); $warm_times = array(); $plans = array();
    for ( $i = 0; $i < 10; $i++ ) {
        $key = 'pera_poi_local_c1_' . md5( $table . serialize( $point ) ); delete_transient( $key );
        $sql = array();
        $probe = function ( $query ) use ( &$sql, $table ) { if ( strpos( $query, "FROM `$table`" ) !== false && strpos( $query, 'SELECT osm_id' ) === 0 ) $sql[] = $query; return $query; };
        add_filter( 'query', $probe );
        $start = microtime( true ); $places = pera_nearby_local_places( $point ); $times[] = microtime( true ) - $start;
        $cold_queries = count( $sql ); $first_sql = $sql[0] ?? null; $sql = array();
        $start = microtime( true ); $warm = pera_nearby_local_places( $point ); $warm_times[] = microtime( true ) - $start;
        $warm_queries = count( $sql ); remove_filter( 'query', $probe );
        if ( $places !== $warm || $warm_queries !== 0 ) throw new RuntimeException( 'Cache mismatch/unexpected warm POI query.' );
        $actual = pera_nearby_groups( $places, $point );
        foreach ( $expected as $group => $nearest ) {
            $ids = array_column( $nearest, 'id' ); $actual_ids = array_column( $actual[$group] ?? array(), 'id' );
            if ( $ids !== $actual_ids ) throw new RuntimeException( 'Nearest-three mismatch at ' . $district . '/' . $group . ' (including possible exact-distance ties requiring review).' );
        }
        if ( $i === 0 && $first_sql ) $plans = $wpdb->get_results( 'EXPLAIN ' . $first_sql, ARRAY_A );
    }
    sort( $times ); sort( $warm_times );
    $metrics[$district] = array( 'cold_p50_seconds' => $times[4], 'cold_p95_seconds' => $times[9],
        'warm_p50_seconds' => $warm_times[4], 'cold_poi_queries' => $cold_queries, 'warm_poi_queries' => $warm_queries,
        'php_peak_bytes' => memory_get_peak_usage( true ), 'nearest_three_matches_reference' => true, 'explain' => $plans );
    if ( $times[9] > 0.5 || memory_get_peak_usage( true ) > 128 * 1024 * 1024 ) throw new RuntimeException( 'Staging performance budget exceeded at ' . $district );
    if ( ! $plans || ! in_array( 'geographic', array_column( $plans, 'key' ), true ) ) throw new RuntimeException( 'Geographic index not used; inspect real query plan.' );
    ob_start(); pera_render_nearby_amenities( array( 'lat' => $point[0], 'lng' => $point[1] ) ); $html = ob_get_clean();
    if ( substr_count( $html, 'role="tab"' ) !== 5 ) throw new RuntimeException( 'Existing tab output changed.' );
}
if ( $http_calls ) throw new RuntimeException( 'Rendering made external HTTP calls.' );
$result = array( 'schema' => 1, 'passed' => true, 'version' => $active['metadata']['version'],
    'database_version' => $wpdb->get_var( 'SELECT VERSION()' ), 'import_seconds' => $import_seconds,
    'http_calls' => $http_calls, 'districts' => $metrics,
    'boundary' => 'Real dataset on disposable WordPress/MariaDB; production plugins, traffic and cPanel resources still need acceptance.' );
file_put_contents( $output, wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n" );
WP_CLI::log( 'WordPress/MariaDB real-artifact acceptance passed.' );
} catch ( Throwable $error ) {
    file_put_contents( $output, wp_json_encode( array( 'schema' => 1, 'passed' => false,
        'error' => $error->getMessage(), 'districts' => $metrics ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n" );
    throw $error;
}
