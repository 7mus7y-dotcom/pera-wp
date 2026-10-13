<?php
/** Bounded monthly maintenance/cache contracts using simulated DB and synthetic records only. */
require __DIR__ . '/nearby-local-test.php';
function delete_option( $key ) { global $options; unset( $options[$key] ); return true; }
function wp_mail( ...$args ) { $GLOBALS['sent_mail'][] = $args; return $GLOBALS['mail_success'] ?? true; }
class Updating_Test_DB extends Local_Test_DB {
    public $replacements = 0, $fail_at = null, $table_dates = array();
    function replace( $table, $row, $formats ) {
        $this->replacements++;
        if ( $this->replacements === $this->fail_at ) return false;
        $this->tables[$table][$row['osm_id']] = $row; return 1;
    }
    function esc_like( $value ) { return addcslashes( $value, '_%' ); }
    function get_results( $sql, $format ) {
        if ( strpos( $sql, 'information_schema' ) !== false ) {
            $this->last_error = ''; $rows = array();
            foreach ( $this->table_dates as $name => $date ) $rows[] = array( 'TABLE_NAME' => $name, 'CREATE_TIME' => $date );
            return $rows;
        }
        return parent::get_results( $sql, $format );
    }
    function query( $sql ) {
        if ( preg_match( '/^DROP TABLE `([^`]+)`/', $sql, $m ) ) { unset( $this->tables[$m[1]] ); return 1; }
        return parent::query( $sql );
    }
}
$wpdb = new Updating_Test_DB(); $options = array(); $cache = array();
$points = array();
for ( $i = 0; $i < 4; $i++ ) $points[] = array( 'id' => 'node/' . ( $i + 1 ), 'group' => 'schools', 'point' => array( 41 + $i * .001, 29 ), 'names' => array( 'name' => 'Cache fixture ' . $i ) );
$first = import_records( $points );
$a = pera_nearby_cached( array( 41.0, 29.0 ) ); $queries = $wpdb->queries;
check( pera_nearby_cached( array( 41.0, 29.0 ) ) === $a && $wpdb->queries === $queries, 'Warm exact-coordinate cache uses zero POI SQL queries' );
$b = pera_nearby_cached( array( 41.003, 29.0 ) );
check( $a[0]['id'] === 'node/1' && $b[0]['id'] === 'node/4' && pera_nearby_cell( array( 41, 29 ) )['key'] === pera_nearby_cell( array( 41.003, 29 ) )['key'], 'Two properties in same cell preserve different nearest-three results' );
$new = $points; $new[0]['names']['name'] = 'New generation fixture'; import_records( $new );
check( pera_nearby_cached( array( 41.0, 29.0 ) )[0]['names']['name'] === 'New generation fixture', 'Generation change invalidates cache without flushing all transients' );
pera_nearby_rollback(); $queries = $wpdb->queries;
check( pera_nearby_cached( array( 41.0, 29.0 ) ) === $a && $wpdb->queries === $queries, 'Rollback selects correct previous-generation cached result' );

// Python builds these temporary acceptance fixtures; never upload them as OSM coverage evidence.
$directory = getenv( 'NEARBY_UPDATE_FIXTURES' );
if ( ! $directory ) throw new RuntimeException( 'Set NEARBY_UPDATE_FIXTURES to the temporary synthetic fixture directory.' );
$path = $directory . '/dataset.poi.ndjson'; $report = $directory . '/report.json';
$wpdb = new Updating_Test_DB(); $options = array(); $cache = array();
$one = pera_nearby_import_batch( $path, $report, 100 );
check( ! $one['complete'] && $one['count'] === 100 && ! pera_nearby_dataset_state(), 'First batch remains unpublished' );
$checkpoint = $options['pera_nearby_import_session'];
$wpdb->fail_at = $wpdb->replacements + 5;
try { pera_nearby_import_batch( $path, $report, 100 ); throw new LogicException( 'Expected interrupted batch' ); } catch ( RuntimeException $expected ) {}
check( $options['pera_nearby_import_session'] === $checkpoint && ! pera_nearby_dataset_state() && pera_nearby_update_status()['last_error'], 'Failed partial batch retains checkpoint and records error' );
$wpdb->fail_at = null;
try { pera_nearby_import( $path ); throw new LogicException( 'Expected overlapping manual import rejection' ); } catch ( RuntimeException $expected ) {}
$options['pera_nearby_import_lock'] = time() + 300;
try { pera_nearby_import_batch( $path, $report, 100 ); throw new LogicException( 'Expected duplicate job rejection' ); } catch ( RuntimeException $expected ) {}
unset( $options['pera_nearby_import_lock'] );
while ( ! ( $result = pera_nearby_import_batch( $path, $report, 100 ) )['complete'] ) {}
check( $result['count'] === 770 && ! get_option( 'pera_nearby_import_session' ), 'Replay safely completes 770 synthetic rows after interruption' );
$status = pera_nearby_update_status();
check( $status['active_version'] === 'synthetic_first' && $status['poi_count'] === 770 && $status['last_successful_import'] && ! $status['last_error'] && $status['next_scheduled_refresh'], 'Status contains version/count/success/schedule/error fields' );
check( pera_nearby_import_batch( $path, $report, 100 )['already_active'], 'Duplicate delivery of same accepted version is idempotent' );
check( pera_nearby_preflight( $path, $report )['metadata']['version'] === 'synthetic_first', 'Receiver preflight permits identical accepted version after activation' );
$active = pera_nearby_dataset_state();
$next_path = $directory . '/next.poi.ndjson'; $next_report = $directory . '/next-report.json';
$next = pera_nearby_import_batch( $next_path, $next_report, 100 );
check( ! $next['complete'] && pera_nearby_dataset_state() === $active, 'Old complete generation serves during staged replacement' );
$fail_update_option = 'pera_nearby_dataset';
try { pera_nearby_import_batch( $next_path, $next_report, 2000 ); throw new LogicException( 'Expected activation failure' ); } catch ( RuntimeException $expected ) {}
check( pera_nearby_dataset_state() === $active, 'Failed activation preserves prior dataset' );
$fail_update_option = null;
$complete = pera_nearby_import_batch( $next_path, $next_report, 2000 );
check( $complete['complete'] && pera_nearby_update_status()['previous_version'] === 'synthetic_first', 'New generation activates with coherent previous descriptor' );
// Simulate crash AFTER activation but BEFORE deleting the checkpoint.
$activated = pera_nearby_dataset_state();
$options['pera_nearby_import_session'] = array_merge( $checkpoint, array( 'path' => realpath( $next_path ), 'report_path' => realpath( $next_report ), 'artifact_sha256' => hash_file( 'sha256', $next_path ), 'report_sha256' => hash_file( 'sha256', $next_report ), 'table' => $activated['table'], 'metadata' => $activated['metadata'], 'validation' => $activated['validation'], 'offset' => filesize( $next_path ), 'count' => 770 ) );
check( pera_nearby_import_batch( $next_path, $next_report, 100 )['complete'] && pera_nearby_update_status()['previous_version'] === 'synthetic_first', 'Post-activation crash recovery never overwrites previous-generation rollback' );
pera_nearby_rollback(); check( pera_nearby_update_status()['active_version'] === 'synthetic_first', 'Accepted generation rollback restores version/status' );
// A staging session must protect itself from cleanup and abort must preserve active data.
$next = pera_nearby_import_batch( $next_path, $next_report, 10 );
$staging = $options['pera_nearby_import_session']['table'];
$orphan = 'wp_pera_poi_' . str_repeat( 'a', 24 ); $recent = 'wp_pera_poi_' . str_repeat( 'b', 24 );
$wpdb->tables[$orphan] = array(); $wpdb->tables[$recent] = array();
foreach ( array( $orphan, $staging, pera_nearby_dataset_state()['table'], $options['pera_nearby_dataset']['previous']['table'] ) as $table ) $wpdb->table_dates[$table] = gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS );
$wpdb->table_dates[$recent] = gmdate( 'Y-m-d H:i:s' );
check( pera_nearby_cleanup() === array( $orphan ) && isset( $wpdb->tables[$staging], $wpdb->tables[$recent] ), 'Cleanup protects active/previous/staging/recent tables' );
pera_nearby_abort_import(); check( ! get_option( 'pera_nearby_import_session' ) && ! isset( $wpdb->tables[$staging] ) && pera_nearby_update_status()['active_version'] === 'synthetic_first', 'Abort removes only staged generation' );
define( 'PERA_NEARBY_UPDATES_ENABLED', true );
pera_nearby_update_error( 'Synthetic failure notice test' ); pera_nearby_monitor(); pera_nearby_monitor();
check( count( $sent_mail ) === 1, 'Failure email is admin-only and rate limited' );
echo "Monthly batch, interruption/replay, failure, rollback, cache, status, cleanup and notification contracts passed (synthetic fixtures, simulated database).\n";
