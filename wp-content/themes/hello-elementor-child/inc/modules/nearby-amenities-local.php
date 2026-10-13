<?php
/** Shared, indexed OSM dataset. All mutations are explicit maintenance actions. */
if ( ! defined( 'ABSPATH' ) ) exit;

function pera_nearby_provider() {
    return apply_filters( 'pera_nearby_provider', 'local' );
}

function pera_nearby_dataset_state() {
    global $wpdb;
    $state = get_option( 'pera_nearby_dataset', array() );
    $active = $state['active'] ?? null;
    if ( ! is_array( $active ) || ! is_string( $active['table'] ?? null ) || ! preg_match( '/^' . preg_quote( $wpdb->prefix, '/' ) . 'pera_poi_[a-f0-9]{24}$/D', $active['table'] ?? '' ) || ( $active['metadata']['schema'] ?? null ) !== 1 ) return null;
    return $active;
}

/** Conservative spherical bounding box, including poles and antimeridian. */
function pera_nearby_bounds( array $point, $radius = 3500 ) {
    $angle = $radius / 6371000;
    $lat = deg2rad( $point[0] );
    $south = max( -M_PI / 2, $lat - $angle ); $north = min( M_PI / 2, $lat + $angle );
    $delta = $south <= -M_PI / 2 || $north >= M_PI / 2 ? 180 : rad2deg( asin( min( 1, sin( $angle ) / cos( $lat ) ) ) );
    return array( rad2deg( $south ), rad2deg( $north ), $point[1] - $delta, $point[1] + $delta );
}

/** Keep only nearest three distinct facilities; bounded memory regardless of dataset size. */
function pera_nearby_keep_nearest( array $best, array $place, array $point ) {
    $place['distance'] = pera_nearby_distance( $point, $place['point'] );
    if ( $place['distance'] > 3500 ) return $best;
    foreach ( $best as $key => $old ) {
        if ( $old['id'] === $place['id'] || ( $old['names']['name'] === $place['names']['name'] && pera_nearby_distance( $old['point'], $place['point'] ) < 100 ) ) {
            if ( $old['distance'] <= $place['distance'] ) return $best;
            unset( $best[$key] );
        }
    }
    $best[] = $place;
    usort( $best, function ( $a, $b ) { return ( $a['distance'] <=> $b['distance'] ) ?: strcmp( $a['id'], $b['id'] ); } );
    return array_slice( $best, 0, 3 );
}

function pera_nearby_local_places( array $point ) {
    global $wpdb;
    $active = pera_nearby_dataset_state();
    if ( ! $active ) return array();
    $cache_key = 'pera_poi_local_c1_' . md5( $active['table'] . serialize( $point ) );
    $cached = get_transient( $cache_key );
    if ( is_array( $cached ) && count( $cached ) <= 45 && count( array_filter( $cached, 'pera_nearby_valid_place' ) ) === count( $cached ) ) return $cached;
    $table = $active['table']; $bounds = pera_nearby_bounds( $point ); $places = array();
    $longitude = 'lng BETWEEN %s AND %s'; $west = $bounds[2]; $east = $bounds[3];
    if ( $west < -180 ) { $longitude = '(lng >= %s OR lng <= %s)'; $west += 360; }
    elseif ( $east > 180 ) { $longitude = '(lng >= %s OR lng <= %s)'; $east -= 360; }
    foreach ( pera_nearby_local_groups() as $group ) {
        $best = array(); $offset = 0;
        do {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT osm_id,lat,lng,names, POW(SIN(RADIANS(lat - %s)/2),2) + COS(RADIANS(%s))*COS(RADIANS(lat))*POW(SIN(RADIANS(lng - %s)/2),2) AS proximity FROM `$table` WHERE poi_group = %s AND lat BETWEEN %s AND %s AND $longitude ORDER BY proximity,osm_id LIMIT 256 OFFSET %d",
                json_encode( (float) $point[0] ), json_encode( (float) $point[0] ), json_encode( (float) $point[1] ), $group, json_encode( $bounds[0] ), json_encode( $bounds[1] ), json_encode( $west ), json_encode( $east ), $offset
            ), ARRAY_A );
            if ( $wpdb->last_error ) return array(); // Missing/corrupt store fails gracefully; airports remain independent.
            foreach ( $rows as $row ) {
                $place = array( 'id' => $row['osm_id'], 'point' => array( (float) $row['lat'], (float) $row['lng'] ), 'group' => $group, 'names' => json_decode( $row['names'], true ) );
                if ( pera_nearby_valid_place( $place ) ) $best = pera_nearby_keep_nearest( $best, $place, $point );
                if ( count( $best ) === 3 ) break;
            }
            $offset += 256;
        } while ( count( $best ) < 3 && count( $rows ) === 256 );
        $places = array_merge( $places, $best );
    }
    if ( strlen( wp_json_encode( $places ) ) <= 131072 ) set_transient( $cache_key, $places, 7 * DAY_IN_SECONDS );
    return $places;
}

function pera_nearby_local_groups() {
    return array( 'metro', 'marmaray', 'metrobus', 'rail', 'bus', 'ferry', 'schools', 'universities', 'hospitals', 'clinics', 'pharmacies', 'parks', 'sport', 'malls', 'supermarkets' );
}

function pera_nearby_valid_place( $place ) {
    if ( ! is_array( $place ) || ! is_string( $place['id'] ?? null ) || ! preg_match( '~^(node|way|relation)/[1-9][0-9]{0,18}$~D', $place['id'] ?? '' ) || ! in_array( $place['group'] ?? '', pera_nearby_local_groups(), true ) || ! is_array( $place['point'] ?? null ) || count( $place['point'] ) !== 2 || ! pera_nearby_coordinates( array( 'lat' => $place['point'][0] ?? null, 'lng' => $place['point'][1] ?? null ) ) || ! is_array( $place['names'] ?? null ) || empty( $place['names']['name'] ) || ! is_string( $place['names']['name'] ) ) return false;
    foreach ( $place['names'] as $key => $value ) if ( ! is_string( $value ) || strlen( $value ) > 4096 || ! preg_match( '/^name(?::[a-zA-Z0-9_-]+)?$/D', $key ) ) return false;
    if ( isset( $place['aliases'] ) && ! is_array( $place['aliases'] ) ) return false;
    foreach ( $place['aliases'] ?? array() as $alias ) if ( ! is_string( $alias ) || ! preg_match( '~^(node|way|relation)/[1-9][0-9]{0,18}$~D', $alias ) ) return false;
    return true;
}

/** Stream into an unpublished generation. Failed validation never changes the active pointer. */
function pera_nearby_import( $path ) {
    if ( get_option( 'pera_nearby_import_session' ) ) throw new RuntimeException( 'A resumable import is pending; resume or abort it first.' );
    global $wpdb;
    $lock = pera_nearby_lock( 'pera_nearby_import_lock', 3600 );
    if ( ! $lock ) throw new RuntimeException( 'Another import/rollback is running.' );
    $table = $wpdb->prefix . 'pera_poi_' . bin2hex( random_bytes( 12 ) ); $file = false; $published = false;
    try {
        $file = fopen( $path, 'rb' );
        if ( ! $file ) throw new RuntimeException( 'Cannot open dataset.' );
        $metadata = json_decode( fgets( $file, 65537 ), true );
        if ( ! is_array( $metadata ) || ( $metadata['schema'] ?? null ) !== 1 || ( $metadata['license'] ?? '' ) !== 'ODbL-1.0' || empty( $metadata['source'] ) || empty( $metadata['generated_at'] ) || empty( $metadata['version'] ) || ! is_int( $metadata['count'] ?? null ) || $metadata['count'] < 0 || ! preg_match( '/^[a-f0-9]{64}$/D', $metadata['sha256'] ?? '' ) ) throw new RuntimeException( 'Invalid dataset metadata.' );
        if ( false === $wpdb->query( "CREATE TABLE `$table` (osm_id varchar(32) CHARACTER SET ascii NOT NULL PRIMARY KEY, poi_group varchar(20) CHARACTER SET ascii NOT NULL, lat double NOT NULL, lng double NOT NULL, names text NOT NULL, aliases text NOT NULL, KEY geographic (poi_group,lat,lng)) ENGINE=InnoDB " . $wpdb->get_charset_collate() ) ) throw new RuntimeException( 'Cannot create dataset table.' );
        $hash = hash_init( 'sha256' ); $count = 0;
        while ( false !== ( $line = fgets( $file, 65537 ) ) ) {
            if ( strlen( $line ) > 65536 || substr( $line, -1 ) !== "\n" ) throw new RuntimeException( 'Oversized or truncated record.' );
            hash_update( $hash, $line ); $place = json_decode( $line, true );
            if ( ! pera_nearby_valid_place( $place ) ) throw new RuntimeException( 'Invalid POI at record ' . ( $count + 1 ) );
            $names_json = wp_json_encode( $place['names'], JSON_UNESCAPED_UNICODE );
            $aliases_json = wp_json_encode( $place['aliases'] ?? array(), JSON_UNESCAPED_UNICODE );
            if ( ! is_string( $names_json ) || ! is_string( $aliases_json ) || strlen( $names_json ) > 65535 || strlen( $aliases_json ) > 65535 ) throw new RuntimeException( 'Encoded record exceeds database column capacity.' );
            if ( false === $wpdb->insert( $table, array( 'osm_id' => $place['id'], 'poi_group' => $place['group'], 'lat' => json_encode( (float) $place['point'][0] ), 'lng' => json_encode( (float) $place['point'][1] ), 'names' => $names_json, 'aliases' => $aliases_json ), array( '%s', '%s', '%s', '%s', '%s', '%s' ) ) ) throw new RuntimeException( 'Insert failed (including duplicate OSM identifier).' );
            $count++;
        }
        if ( ! feof( $file ) || $count !== $metadata['count'] || ! hash_equals( $metadata['sha256'], hash_final( $hash ) ) ) throw new RuntimeException( 'Dataset count/checksum mismatch.' );
        // Refuse to publish after losing our lease; no partially filled generation can become visible.
        if ( (int) get_option( 'pera_nearby_import_lock' ) !== $lock || $lock <= time() ) throw new RuntimeException( 'Import lease expired; retry with a smaller artifact.' );
        $old = pera_nearby_dataset_state();
        $state = array( 'active' => array( 'table' => $table, 'metadata' => $metadata, 'imported_at' => gmdate( 'c' ) ), 'previous' => $old );
        if ( ! update_option( 'pera_nearby_dataset', $state, false ) ) throw new RuntimeException( 'Dataset publication failed.' );
        $published = true;
        update_option( 'pera_nearby_update_status', array( 'last_error' => null, 'last_attempt' => gmdate( 'c' ), 'last_successful_import' => $state['active']['imported_at'] ), false );
        return $state['active'];
    } finally {
        if ( is_resource( $file ) ) fclose( $file );
        if ( ! $published ) $wpdb->query( "DROP TABLE IF EXISTS `$table`" );
        pera_nearby_unlock( 'pera_nearby_import_lock', $lock );
    }
}

function pera_nearby_rollback() {
    if ( get_option( 'pera_nearby_import_session' ) ) throw new RuntimeException( 'Abort/resume the pending import before rollback.' );
    $lock = pera_nearby_lock( 'pera_nearby_import_lock', 60 );
    if ( ! $lock ) throw new RuntimeException( 'Import/rollback is running.' );
    try {
        global $wpdb;
        $state = get_option( 'pera_nearby_dataset', array() ); $previous = $state['previous'] ?? null;
        if ( ! is_array( $previous ) || ( $previous['metadata']['schema'] ?? null ) !== 1 || ! is_string( $previous['table'] ?? null ) || ! preg_match( '/^' . preg_quote( $wpdb->prefix, '/' ) . 'pera_poi_[a-f0-9]{24}$/D', $previous['table'] ?? '' ) || $wpdb->get_var( "SELECT COUNT(*) FROM `{$previous['table']}`" ) === null || $wpdb->last_error ) throw new RuntimeException( 'No usable rollback dataset.' );
        if ( ! update_option( 'pera_nearby_dataset', array( 'active' => $previous, 'previous' => $state['active'] ), false ) ) throw new RuntimeException( 'Rollback failed.' );
        return $previous;
    } finally { pera_nearby_unlock( 'pera_nearby_import_lock', $lock ); }
}

require_once __DIR__ . '/nearby-amenities-updates.php';
