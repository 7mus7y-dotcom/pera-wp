<?php
/** Explicit, bounded monthly maintenance. Never writes property/ACF records. */
if ( ! defined( 'ABSPATH' ) ) exit;

function pera_nearby_update_error( $message ) {
    $status = get_option( 'pera_nearby_update_status', array() );
    $status['last_error'] = substr( $message, 0, 2000 ); $status['last_attempt'] = gmdate( 'c' );
    update_option( 'pera_nearby_update_status', $status, false );
}

function pera_nearby_update_status() {
    $active = pera_nearby_dataset_state(); $state = get_option( 'pera_nearby_dataset', array() );
    $status = get_option( 'pera_nearby_update_status', array() );
    $successful = $status['last_successful_import'] ?? ( $active['imported_at'] ?? null );
    if ( strtotime( $active['imported_at'] ?? '' ) > strtotime( $successful ?? '' ) ) $successful = $active['imported_at'];
    $next = new DateTimeImmutable( 'first day of this month 04:00:00', new DateTimeZone( 'UTC' ) );
    if ( $next->getTimestamp() <= time() ) $next = $next->modify( '+1 month' );
    return array( 'dataset_schema' => $active['metadata']['schema'] ?? null, 'classification_version' => $active['metadata']['classification_version'] ?? 1, 'active_version' => $active['metadata']['version'] ?? null,
        'body_sha256' => $active['metadata']['sha256'] ?? null,
        'source_timestamp' => $active['metadata']['source_timestamp'] ?? null,
        'last_successful_import' => $successful, 'poi_count' => $active['metadata']['count'] ?? 0,
        'next_scheduled_refresh' => $next->format( 'c' ), 'schedule_timezone' => 'UTC (07:00 Europe/Istanbul)',
        'previous_version' => $state['previous']['metadata']['version'] ?? null,
        'last_error' => $status['last_error'] ?? null, 'last_attempt' => $status['last_attempt'] ?? null,
        'import_progress' => get_option( 'pera_nearby_import_session', array() )['count'] ?? null,
        'updates_enabled' => defined( 'PERA_NEARBY_UPDATES_ENABLED' ) && PERA_NEARBY_UPDATES_ENABLED );
}

/** Full streaming preflight before staging: validates report, digest, unique sorted IDs and coverage. */
function pera_nearby_preflight( $path, $report_path ) {
    if ( ! is_file( $path ) || ! is_file( $report_path ) || filesize( $path ) > 104857600 || filesize( $report_path ) > 262144 ) throw new RuntimeException( 'Artifact/report unavailable or oversized.' );
    $report = json_decode( file_get_contents( $report_path ), true );
    if ( ! is_array( $report ) || ( $report['schema'] ?? null ) !== 1 || ( $report['passed'] ?? null ) !== true || ! in_array( $report['classification_version'] ?? null, array( 1, 2 ), true ) || ! is_string( $report['artifact_sha256'] ?? null ) || ! hash_equals( $report['artifact_sha256'], hash_file( 'sha256', $path ) ) ) throw new RuntimeException( 'Invalid report or artifact SHA-256.' );
    $policy = $report['policy'] ?? null;
    $expected_policy = json_decode( file_get_contents( dirname( __DIR__ ) . '/data/' . ( $report['classification_version'] === 1 ? 'nearby-poi-policy-v1.json' : 'nearby-poi-policy.json' ) ), true );
    if ( ! is_array( $expected_policy ) || $policy != $expected_policy ) throw new RuntimeException( 'Report policy differs from deployed acceptance policy.' );
    if ( ! is_array( $policy ) || ! is_array( $policy['districts'] ?? null ) || count( $policy['districts'] ) !== 11 || ! is_array( $policy['required_groups'] ?? null ) || ( $policy['minimum_ratio'] ?? 0 ) < 0.8 || ( $policy['maximum_ratio'] ?? 99 ) > 1.5 || ( $policy['min_total'] ?? 0 ) < 500 || ( $policy['max_total'] ?? PHP_INT_MAX ) > 500000 || ( $policy['district_min_pois'] ?? 0 ) < 10 || ( $policy['district_min_categories'] ?? 0 ) < 3 || ( $policy['max_source_age_days'] ?? 99 ) > 14 ) throw new RuntimeException( 'Acceptance policy below production safeguards.' );
    if ( filesize( $path ) > min( 104857600, $policy['max_artifact_bytes'] ?? 104857600 ) ) throw new RuntimeException( 'Artifact exceeds 100 MiB production budget.' );
    $categories = array( 'metro' => 'transport', 'marmaray' => 'transport', 'metrobus' => 'transport', 'rail' => 'transport', 'tram' => 'transport', 'funicular' => 'transport', 'bus' => 'transport', 'ferry' => 'transport', 'schools' => 'education', 'universities' => 'education', 'hospitals' => 'healthcare', 'clinics' => 'healthcare', 'pharmacies' => 'healthcare', 'parks' => 'parks', 'sport' => 'parks', 'malls' => 'shopping', 'supermarkets' => 'shopping' );
    $file = fopen( $path, 'rb' ); if ( ! $file ) throw new RuntimeException( 'Artifact unavailable.' );
    try {
        $header = fgets( $file, 65537 ); $metadata = json_decode( $header, true );
        if ( ! is_array( $metadata ) || strlen( $header ) > 65536 || ! in_array( $metadata['schema'] ?? null, array( 1, 2 ), true ) || ( $metadata['license'] ?? null ) !== 'ODbL-1.0' || ( $metadata['classification_version'] ?? null ) !== $report['classification_version'] || ( $metadata['schema'] ?? null ) !== $report['classification_version'] || ( $metadata['version'] ?? null ) !== $report['version'] || ( $metadata['sha256'] ?? null ) !== $report['body_sha256'] || ( $metadata['bounds'] ?? null ) !== array( 27.8, 40.7, 30.1, 41.7 ) ) throw new RuntimeException( 'Metadata/report mismatch.' );
        $source_time = strtotime( $metadata['source_timestamp'] ?? '' ); $generated = strtotime( $metadata['generated_at'] ?? '' );
        if ( ! $source_time || $source_time < time() - 14 * DAY_IN_SECONDS || $source_time > time() + DAY_IN_SECONDS || ! $generated || $generated < time() - 7 * DAY_IN_SECONDS || $generated > time() + DAY_IN_SECONDS ) throw new RuntimeException( 'Stale/future source or build timestamp.' );
        $offset = ftell( $file ); $digest = hash_init( 'sha256' ); $count = 0; $last_id = ''; $groups = array(); $districts = array();
        foreach ( $policy['districts'] as $name => $origin ) { if ( ! pera_nearby_coordinates( array( 'lat' => $origin[0] ?? null, 'lng' => $origin[1] ?? null ) ) ) throw new RuntimeException( 'Invalid district origin.' ); $districts[$name] = array( 'count' => 0, 'categories' => array() ); }
        while ( false !== ( $line = fgets( $file, 65537 ) ) ) {
            if ( strlen( $line ) > 65536 || substr( $line, -1 ) !== "\n" ) throw new RuntimeException( 'Truncated/oversized record.' );
            hash_update( $digest, $line ); $place = json_decode( $line, true );
            if ( ! pera_nearby_record_valid( $place, $metadata['schema'] ) || strcmp( $place['id'], $last_id ) <= 0 ) throw new RuntimeException( pera_nearby_place_text_error( $place ) ?? pera_nearby_transport_error( $place, $metadata['schema'] ) ?? 'Invalid, duplicate or unsorted OSM identity.' );
            $last_id = $place['id']; $p = $place['point'];
            if ( $p[0] < 40.7 || $p[0] > 41.7 || $p[1] < 27.8 || $p[1] > 30.1 ) throw new RuntimeException( 'Record outside metropolitan envelope.' );
            $groups[$place['group']] = ( $groups[$place['group']] ?? 0 ) + 1; $count++;
            if ( $count > 500000 ) throw new RuntimeException( 'Too many records.' );
            foreach ( $policy['districts'] as $name => $origin ) if ( pera_nearby_distance( $origin, $p ) <= 3500 ) {
                $districts[$name]['count']++; $districts[$name]['categories'][$categories[$place['group']]] = true;
            }
        }
        if ( ! feof( $file ) || $count !== ( $metadata['count'] ?? null ) || $count !== $report['count'] || ! hash_equals( $metadata['sha256'], hash_final( $digest ) ) || $count < $policy['min_total'] || $count > $policy['max_total'] ) throw new RuntimeException( 'Artifact count/checksum/size validation failed.' );
        foreach ( $policy['required_groups'] as $group => $minimum ) if ( ! isset( $categories[$group] ) || $minimum < 1 || ( $groups[$group] ?? 0 ) < $minimum ) throw new RuntimeException( 'Required subgroup under minimum.' );
        foreach ( array( 'metro', 'marmaray', 'metrobus', 'ferry' ) as $group ) if ( ( $groups[$group] ?? 0 ) < 5 ) throw new RuntimeException( 'Required transport type under minimum.' );
        foreach ( $districts as $name => $summary ) if ( $summary['count'] < $policy['district_min_pois'] || count( $summary['categories'] ) < $policy['district_min_categories'] || $summary['count'] !== ( $report['districts'][$name]['count'] ?? null ) ) throw new RuntimeException( 'District coverage validation failed: ' . $name );
        foreach ( $groups as $group => $number ) if ( $number !== ( $report['groups'][$group] ?? null ) ) throw new RuntimeException( 'Group count/report mismatch.' );
        $active = pera_nearby_dataset_state();
        // A receiver retry after activation validates the identical artifact, not a new transition.
        if ( $active && ( $active['metadata']['sha256'] ?? null ) === $metadata['sha256'] && ( $active['metadata']['version'] ?? null ) === $metadata['version'] && ( $active['validation']['artifact_sha256'] ?? null ) === $report['artifact_sha256'] ) {
            return array( 'metadata' => $metadata, 'offset' => $offset, 'validation' => $report );
        }
        if ( ( $active['metadata']['sha256'] ?? null ) !== ( $report['previous_body_sha256'] ?? null ) ) throw new RuntimeException( 'Accepted baseline differs from current production dataset; reconcile before retry.' );
        if ( $active ) {
            if ( $source_time < strtotime( $active['metadata']['source_timestamp'] ?? '' ) ) throw new RuntimeException( 'Source date regressed.' );
            $previous = $active['validation'] ?? null;
            if ( ! is_array( $previous ) ) throw new RuntimeException( 'Current dataset has no accepted baseline report; operator bootstrap/migration required.' );
            $ratios = array( array( $count, $previous['count'] ) );
            $migration = ( $previous['classification_version'] ?? 1 ) === 1 && $report['classification_version'] === 2;
            if ( $migration !== ( ( $report['classification_migration'] ?? false ) === true ) ) throw new RuntimeException( 'Classification migration must be explicitly reviewed.' );
            $old_transport = 0; $new_transport = 0;
            foreach ( $categories as $group => $category ) {
                if ( $category === 'transport' ) { $old_transport += $previous['groups'][$group] ?? 0; $new_transport += $groups[$group] ?? 0; }
                if ( ! $migration || $category !== 'transport' ) $ratios[] = array( $groups[$group] ?? 0, $previous['groups'][$group] ?? 0 );
            }
            if ( $migration ) $ratios[] = array( $new_transport, $old_transport );
            foreach ( $districts as $name => $summary ) $ratios[] = array( $summary['count'], $previous['districts'][$name]['count'] ?? 0 );
            foreach ( $ratios as $pair ) if ( $pair[1] >= 5 && ( $pair[0] / $pair[1] < 0.8 || $pair[0] / $pair[1] > 1.5 ) ) throw new RuntimeException( 'Suspicious change relative to active production baseline.' );
        }
        return array( 'metadata' => $metadata, 'offset' => $offset, 'validation' => $report );
    } finally { fclose( $file ); }
}

/** One resumable batch. Replay uses REPLACE on a prevalidated immutable unique-ID artifact. */
function pera_nearby_import_batch( $path, $report_path, $batch_size = 1000 ) {
    global $wpdb;
    if ( $batch_size < 1 || $batch_size > 2000 ) throw new RuntimeException( 'Batch size must be 1–2000.' );
    if ( filesize( $path ) > 104857600 || filesize( $report_path ) > 262144 ) throw new RuntimeException( 'Artifact/report exceeds import size budget.' );
    $lock = pera_nearby_lock( 'pera_nearby_import_lock', 300 );
    if ( ! $lock ) throw new RuntimeException( 'Another import/rollback/cleanup is running.' );
    $file = false;
    try {
        $path = realpath( $path ); $report_path = realpath( $report_path );
        $sha = hash_file( 'sha256', $path ); $report_sha = hash_file( 'sha256', $report_path );
        $session = get_option( 'pera_nearby_import_session', null );
        if ( ! $session ) {
            $report = json_decode( file_get_contents( $report_path ), true ); $current = pera_nearby_dataset_state();
            if ( ( $report['passed'] ?? null ) === true && hash_equals( $sha, $report['artifact_sha256'] ?? '' ) && ( $current['metadata']['sha256'] ?? null ) === ( $report['body_sha256'] ?? '' ) && ( $current['metadata']['version'] ?? null ) === ( $report['version'] ?? '' ) ) return array( 'complete' => true, 'count' => $current['metadata']['count'], 'version' => $current['metadata']['version'], 'already_active' => true );
            $checked = pera_nearby_preflight( $path, $report_path );
            $table = $wpdb->prefix . 'pera_poi_' . bin2hex( random_bytes( 12 ) );
            $extra = $checked['metadata']['schema'] === 2 ? 'transport text NOT NULL,' : '';
            if ( false === $wpdb->query( "CREATE TABLE `$table` (osm_id varchar(32) CHARACTER SET ascii NOT NULL PRIMARY KEY, poi_group varchar(20) CHARACTER SET ascii NOT NULL, lat double NOT NULL, lng double NOT NULL, names text NOT NULL, aliases text NOT NULL, $extra KEY geographic (poi_group,lat,lng)) ENGINE=InnoDB " . $wpdb->get_charset_collate() ) ) throw new RuntimeException( 'Cannot create staging table.' );
            $session = array_merge( $checked, array( 'table' => $table, 'path' => $path, 'report_path' => $report_path, 'artifact_sha256' => $sha, 'report_sha256' => $report_sha, 'count' => 0, 'started_at' => time() ) );
            if ( ! update_option( 'pera_nearby_import_session', $session, false ) ) { $wpdb->query( "DROP TABLE `$table`" ); throw new RuntimeException( 'Cannot persist import session.' ); }
        }
        if ( $session['path'] !== $path || $session['report_path'] !== $report_path || ! hash_equals( $session['artifact_sha256'], $sha ) || ! hash_equals( $session['report_sha256'], $report_sha ) ) throw new RuntimeException( 'Pending import identity changed; restore original files or abort.' );
        $table = $session['table'];
        if ( ! preg_match( '/^' . preg_quote( $wpdb->prefix, '/' ) . 'pera_poi_[a-f0-9]{24}$/D', $table ) ) throw new RuntimeException( 'Invalid staging table name.' );
        $file = fopen( $path, 'rb' ); if ( ! $file || fseek( $file, $session['offset'] ) !== 0 ) throw new RuntimeException( 'Cannot resume artifact.' );
        for ( $i = 0; $i < $batch_size && false !== ( $line = fgets( $file, 65537 ) ); $i++ ) {
            $place = json_decode( $line, true ); if ( ! pera_nearby_record_valid( $place, $session['metadata']['schema'] ) ) throw new RuntimeException( pera_nearby_place_text_error( $place ) ?? pera_nearby_transport_error( $place, $session['metadata']['schema'] ) ?? 'Artifact record invalid during resume.' );
            $names = wp_json_encode( $place['names'], JSON_UNESCAPED_UNICODE ); $aliases = wp_json_encode( $place['aliases'] ?? array(), JSON_UNESCAPED_UNICODE );
            if ( strlen( $names ) > 65535 || strlen( $aliases ) > 65535 ) throw new RuntimeException( 'Record exceeds database column capacity.' );
            $row = pera_nearby_import_row( $place, $session['metadata']['schema'] );
            if ( false === $wpdb->replace( $table, $row, array_fill( 0, count( $row ), '%s' ) ) ) throw new RuntimeException( 'Staging insert failed; resume or abort.' );
            $session['count']++;
        }
        $session['offset'] = ftell( $file );
        if ( (int) get_option( 'pera_nearby_import_lock' ) !== $lock || $lock <= time() ) throw new RuntimeException( 'Batch lease expired; checkpoint not advanced.' );
        if ( $session['count'] < $session['metadata']['count'] ) {
            if ( feof( $file ) ) throw new RuntimeException( 'Premature artifact EOF.' );
            if ( ! update_option( 'pera_nearby_import_session', $session, false ) ) throw new RuntimeException( 'Cannot save import checkpoint.' );
            return array( 'complete' => false, 'count' => $session['count'], 'version' => $session['metadata']['version'] );
        }
        if ( $session['count'] !== $session['metadata']['count'] || (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ) !== $session['count'] || $wpdb->last_error ) throw new RuntimeException( 'Staging count mismatch.' );
        $old = pera_nearby_dataset_state();
        // On a crash after activation but before session deletion, a retry must not replace previous.
        if ( ( $old['table'] ?? null ) !== $table ) {
            if ( ( $old['metadata']['sha256'] ?? null ) !== ( $session['validation']['previous_body_sha256'] ?? null ) ) throw new RuntimeException( 'Active baseline changed during import.' );
            $active = array( 'table' => $table, 'metadata' => $session['metadata'], 'validation' => $session['validation'], 'imported_at' => gmdate( 'c' ) );
            if ( ! update_option( 'pera_nearby_dataset', array( 'active' => $active, 'previous' => $old ), false ) ) throw new RuntimeException( 'Atomic activation failed.' );
        }
        delete_option( 'pera_nearby_import_session' );
        update_option( 'pera_nearby_update_status', array( 'last_error' => null, 'last_attempt' => gmdate( 'c' ), 'last_successful_import' => pera_nearby_dataset_state()['imported_at'] ?? gmdate( 'c' ) ), false );
        return array( 'complete' => true, 'count' => $session['count'], 'version' => $session['metadata']['version'] );
    } catch ( Throwable $error ) { pera_nearby_update_error( $error->getMessage() ); throw $error; }
    finally { if ( is_resource( $file ) ) fclose( $file ); pera_nearby_unlock( 'pera_nearby_import_lock', $lock ); }
}

function pera_nearby_abort_import() {
    global $wpdb;
    $lock = pera_nearby_lock( 'pera_nearby_import_lock', 60 ); if ( ! $lock ) throw new RuntimeException( 'Import is busy.' );
    try {
        $session = get_option( 'pera_nearby_import_session', array() ); $active = pera_nearby_dataset_state();
        if ( $session && ( $active['table'] ?? null ) !== $session['table'] && preg_match( '/^' . preg_quote( $wpdb->prefix, '/' ) . 'pera_poi_[a-f0-9]{24}$/D', $session['table'] ) ) {
            if ( false === $wpdb->query( "DROP TABLE IF EXISTS `{$session['table']}`" ) ) throw new RuntimeException( 'Cannot remove staging table.' );
        }
        delete_option( 'pera_nearby_import_session' );
    } finally { pera_nearby_unlock( 'pera_nearby_import_lock', $lock ); }
}

/** Safe automatic cleanup: neither active nor previous nor staging, and at least 7 days old. */
function pera_nearby_cleanup() {
    global $wpdb;
    $lock = pera_nearby_lock( 'pera_nearby_import_lock', 60 ); if ( ! $lock ) throw new RuntimeException( 'Dataset maintenance busy.' );
    try {
        $state = get_option( 'pera_nearby_dataset', array() ); $session = get_option( 'pera_nearby_import_session', array() );
        $keep = array_filter( array( $state['active']['table'] ?? null, $state['previous']['table'] ?? null, $session['table'] ?? null ) );
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT TABLE_NAME,CREATE_TIME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'pera_poi_' ) . '%' ), ARRAY_A );
        if ( $wpdb->last_error ) throw new RuntimeException( 'Cannot inspect dataset tables.' );
        $dropped = array();
        foreach ( $rows as $row ) if ( ! in_array( $row['TABLE_NAME'], $keep, true ) && preg_match( '/^' . preg_quote( $wpdb->prefix, '/' ) . 'pera_poi_[a-f0-9]{24}$/D', $row['TABLE_NAME'] ) && ! empty( $row['CREATE_TIME'] ) && strtotime( $row['CREATE_TIME'] ) < time() - 7 * DAY_IN_SECONDS ) {
            if ( false === $wpdb->query( "DROP TABLE `{$row['TABLE_NAME']}`" ) ) throw new RuntimeException( 'Dataset cleanup failed.' );
            $dropped[] = $row['TABLE_NAME'];
        }
        return $dropped;
    } finally { pera_nearby_unlock( 'pera_nearby_import_lock', $lock ); }
}

function pera_nearby_monitor() {
    if ( ! defined( 'PERA_NEARBY_UPDATES_ENABLED' ) || ! PERA_NEARBY_UPDATES_ENABLED ) return;
    $status = pera_nearby_update_status(); $session = get_option( 'pera_nearby_import_session', array() );
    $stale = ! $status['source_timestamp'] || strtotime( $status['source_timestamp'] ) < time() - 45 * DAY_IN_SECONDS;
    $interrupted = $session && ( $session['started_at'] ?? 0 ) < time() - 6 * HOUR_IN_SECONDS;
    if ( ! $stale && ! $interrupted && ! $status['last_error'] ) return;
    if ( get_transient( 'pera_nearby_notice_sent' ) ) return;
    $sent = wp_mail( get_option( 'admin_email' ), 'Pera nearby POI update needs attention', wp_json_encode( $status, JSON_PRETTY_PRINT ) . "\nStale source: " . (int) $stale . "\nInterrupted import: " . (int) $interrupted );
    if ( $sent ) set_transient( 'pera_nearby_notice_sent', true, DAY_IN_SECONDS );
}
add_action( 'pera_nearby_monitor', 'pera_nearby_monitor' );
add_action( 'init', function () {
    if ( defined( 'PERA_NEARBY_UPDATES_ENABLED' ) && PERA_NEARBY_UPDATES_ENABLED && ! wp_next_scheduled( 'pera_nearby_monitor' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pera_nearby_monitor' );
} );
