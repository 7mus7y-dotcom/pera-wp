<?php
/** Transport v2, current-schema compatibility and atomic migration; simulated wpdb only. */
require __DIR__ . '/nearby-updates-test.php';

function transport_place( $tags, $routes = array(), $id = 9001 ) {
    return pera_nearby_normalize( array( array( 'type' => 'node', 'id' => $id, 'lat' => 41.06, 'lon' => 28.99, 'tags' => array_merge( array( 'name' => 'Synthetic station' ), $tags ), 'routes' => $routes ) ) )[0] ?? null;
}
function transport_route( $mode, $ref, $extra = array() ) {
    return array( 'id' => 'relation/42', 'role' => 'stop', 'tags' => array_merge( array( 'type' => 'route', 'route' => $mode, 'ref' => $ref ), $extra ) );
}
$metro = transport_place( array( 'railway' => 'station' ), array( transport_route( 'subway', 'M2' ), transport_route( 'subway', 'M7' ) ) );
check( $metro['transport']['lines'] === array( 'M2', 'M7' ) && pera_nearby_record_valid( $metro, 2 ) && ! pera_nearby_record_valid( $metro, 1 ), 'Multiple direct M-lines require schema-v2 evidence' );
$tram = transport_place( array( 'railway' => 'tram_stop' ), array( transport_route( 'tram', 'T5' ) ) );
$funicular = transport_place( array( 'railway' => 'station' ), array( transport_route( 'funicular', 'F2' ) ) );
check( $tram['group'] === 'tram' && $funicular['group'] === 'funicular', 'Tramway and Tünel/funicular do not fall under Rail' );
$bus = transport_place( array( 'highway' => 'bus_stop', 'network' => 'İETT', 'route_names' => 'Metrobüs connector' ), array( transport_route( 'bus', '500T', array( 'name' => 'Metrobüs destination', 'network' => 'İETT' ) ) ) );
check( $bus['group'] === 'bus', 'Ordinary İETT route names cannot prove Metrobüs identity' );
check( transport_place( array( 'railway' => 'station', 'subway' => 'yes', 'construction:railway' => 'station' ) ) === null, 'Future station excluded' );
$future_route = transport_route( 'subway', 'M11', array( 'proposed' => 'yes' ) );
$operational = transport_place( array( 'railway' => 'station', 'subway' => 'yes' ), array( transport_route( 'subway', 'M2' ), $future_route ) );
check( $operational['transport']['lines'] === array( 'M2' ), 'Proposed route does not appear as an operational M-line' );
$bad = $metro; $bad['transport']['lines'][] = 'M99';
check( ! pera_nearby_record_valid( $bad, 2 ), 'Importer rejects line identifiers unsupported by evidence' );
must_fail_import( array( $bad ), array( 'schema' => 2, 'classification_version' => 2 ) );
$joined = $metro;
$second = transport_place( array( 'railway' => 'station', 'subway' => 'yes', 'ref' => 'M11' ), array(), 9002 );
$joined['aliases'] = array( 'node/9002' );
$joined['transport']['sources'][] = $second['transport']['sources'][0];
$joined['transport']['lines'] = pera_nearby_transport_lines( $joined['transport'], 'metro' );
import_records( array( $joined ), array( 'schema' => 2, 'classification_version' => 2 ) );
$merged_places = pera_nearby_local_places( $joined['point'] );
check( count( $merged_places ) === 1 && $merged_places[0]['transport']['lines'] === array( 'M11', 'M2', 'M7' ), 'Merged alias proof and all verified lines survive DB reads' );

foreach ( array( 'metrobus', 'marmaray' ) as $group ) foreach ( array( 499, 500, 501 ) as $metres ) {
    $place = array( 'id' => 'node/1', 'group' => $group, 'point' => array( rad2deg( $metres / 6371000 ), 0 ), 'names' => array( 'name' => 'Boundary fixture' ) );
    $origin = array( 0, 0 );
    check( ( count( pera_nearby_keep_nearest( array(), $place, $origin ) ) === 1 ) === ( $metres <= 500 ), 'Local provider inclusive 500 m boundary: ' . $group . '/' . $metres );
    check( isset( pera_nearby_groups( array( $place ), $origin )[$group] ) === ( $metres <= 500 ), 'Renderer grouping inclusive 500 m boundary: ' . $group . '/' . $metres );
}
check( pera_nearby_radius( 'metro' ) === 3500 && pera_nearby_radius( 'bus' ) === 3500 && pera_nearby_radius( 'tram' ) === 3500, 'General transport radii preserved' );

class Transport_Query_DB extends Updating_Test_DB {
    public $read_sql = array();
    function get_results( $sql, $format ) { $this->read_sql[] = $sql; return parent::get_results( $sql, $format ); }
}
$wpdb = new Transport_Query_DB(); $options = array(); $cache = array();
$legacy_path = $directory . '/legacy.poi.ndjson'; $legacy_report = $directory . '/legacy-report.json';
while ( ! ( $result = pera_nearby_import_batch( $legacy_path, $legacy_report, 100 ) )['complete'] ) {}
$legacy = pera_nearby_dataset_state();
check( $legacy['metadata']['schema'] === 1, 'Active production schema-v1 artifact imports and remains readable' );
$point = array( 41.060, 28.987 );
$legacy_places = pera_nearby_local_places( $point ); $queries = $wpdb->queries;
check( $legacy_places && pera_nearby_local_places( $point ) === $legacy_places && $wpdb->queries === $queries, 'Schema-v1 cache remains functional' );
check( ! preg_match( '/names,transport/', implode( "\n", $wpdb->read_sql ) ), 'No transport-column query against old tables' );
check( ! in_array( 'metrobus', array_column( $legacy_places, 'group' ), true ), 'Unverifiable legacy BRT entries are ordinary Bus until replacement' );
$old_tunel = array( 'id' => 'node/1', 'group' => 'rail', 'point' => $point, 'names' => array( 'name' => 'Tünel' ) );
check( pera_nearby_legacy_transport( $old_tunel )['group'] === 'funicular', 'Known legacy Tünel name is not presented as Rail; no F-line invented' );

$migration_path = $directory . '/migration.poi.ndjson'; $migration_report = $directory . '/migration-report.json';
$staged = pera_nearby_import_batch( $migration_path, $migration_report, 100 );
check( ! $staged['complete'] && pera_nearby_dataset_state() === $legacy && pera_nearby_local_places( $point ) === $legacy_places, 'V1 serves while v2 generation stages' );
$checkpoint = $options['pera_nearby_import_session']; $wpdb->fail_at = $wpdb->replacements + 5;
try { pera_nearby_import_batch( $migration_path, $migration_report, 100 ); throw new LogicException( 'Expected interrupted migration' ); } catch ( RuntimeException $expected ) {}
check( $options['pera_nearby_import_session'] === $checkpoint && pera_nearby_dataset_state() === $legacy, 'Interrupted v2 migration retains v1 active pointer/checkpoint' );
$wpdb->fail_at = null;
while ( ! ( $result = pera_nearby_import_batch( $migration_path, $migration_report, 100 ) )['complete'] ) {}
$new = pera_nearby_dataset_state(); $places = pera_nearby_local_places( $point );
check( $new['metadata']['schema'] === 2 && count( $places ) <= 51 && $wpdb->peak_batch <= 256, 'V2 activation uses bounded local batches/results' );
$queries = $wpdb->queries; check( pera_nearby_local_places( $point ) === $places && $wpdb->queries === $queries, 'V2 warm cache makes no POI SQL queries' );
check( $options['pera_nearby_dataset']['previous'] === $legacy, 'V2 keeps the old schema descriptor for rollback' );
check( pera_nearby_preflight( $migration_path, $migration_report )['metadata']['schema'] === 2, 'Identical v2 delivery retry remains idempotent' );
check( in_array( 'tram', array_column( $places, 'group' ), true ) && in_array( 'funicular', array_column( $places, 'group' ), true ), 'New subgroups survive serialization/import/query' );
ob_start(); pera_render_nearby_amenities( array( 'lat' => $point[0], 'lng' => $point[1] ) ); $html = ob_get_clean();
check( strpos( $html, '(M2)' ) !== false && strpos( $html, '(T1)' ) !== false && strpos( $html, '(F2)' ) !== false && strpos( $html, 'Tramway' ) !== false && strpos( $html, 'Funicular' ) !== false, 'Existing UI renders only verified lines and translated subgroup labels' );
check( strpos( $html, 'OpenStreetMap contributors' ) !== false && substr_count( $html, 'role="tab"' ) === 5, 'Attribution and tab accessibility preserved' );
pera_nearby_rollback(); $queries = $wpdb->queries;
check( pera_nearby_dataset_state() === $legacy && pera_nearby_local_places( $point ) === $legacy_places && $wpdb->queries === $queries, 'Rollback selects old table and old cache without reading v2 columns' );
ob_start(); pera_render_nearby_amenities( array( 'lat' => $point[0], 'lng' => $point[1] ) ); $html = ob_get_clean();
check( strpos( $html, '(M2)' ) === false && strpos( $html, '(F2)' ) === false, 'Old schema never receives fabricated verified line badges' );
pera_nearby_rollback(); check( pera_nearby_dataset_state() === $new, 'Rollback can toggle complete schemas safely' );
echo "Transport proof, radii, current-schema compatibility, bounded queries/cache, interrupted migration and cross-schema rollback contracts passed.\n";
