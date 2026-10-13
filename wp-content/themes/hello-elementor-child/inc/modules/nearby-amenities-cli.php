<?php
/** Maintenance commands only: never writes property posts, metadata or taxonomies. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) return;

WP_CLI::add_command( 'pera nearby audit', function () {
    $counts = array( 'published' => 0, 'valid' => 0, 'missing_or_invalid' => 0 ); $page = 1;
    do {
        $ids = get_posts( array( 'post_type' => 'property', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 500, 'paged' => $page++, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
        foreach ( $ids as $id ) {
            $counts['published']++;
            $map = function_exists( 'get_field' ) ? get_field( 'map', $id ) : get_post_meta( $id, 'map', true );
            $counts[ pera_nearby_coordinates( $map ) ? 'valid' : 'missing_or_invalid' ]++;
        }
    } while ( count( $ids ) === 500 );
    WP_CLI::log( wp_json_encode( $counts ) );
} );

/** Read-only keyset pagination remains stable when published properties change. */
function pera_nearby_warm_property_ids( $after_id, $limit ) {
    global $wpdb;
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
        'property', 'publish', $after_id, $limit
    ) ) );
}

/**
 * Warm shared cells in a bounded pass; never sleep or bypass provider limits.
 * The checkpoint advances only after a property is handled; deferred work resumes.
 */
function pera_nearby_warm( array $settings ) {
    $dry_run = ! empty( $settings['dry_run'] );
    $lock = $dry_run ? false : pera_nearby_lock( 'pera_nearby_warm_lock', 5 * 60 );
    $result = array( 'properties_scanned' => 0, 'invalid_properties' => 0, 'duplicate_properties' => 0,
        'successful' => 0, 'failed' => 0, 'skipped' => 0, 'deferred' => 0, 'planned' => 0,
        'next_after_id' => 0, 'complete' => false, 'reason' => '', 'cells' => array() );
    if ( ! $dry_run && ! $lock ) {
        $result['next_after_id'] = ! empty( $settings['resume'] ) ? (int) get_option( 'pera_nearby_warm_cursor', 0 ) : (int) ( $settings['after_id'] ?? 0 );
        $result['reason'] = 'warm_job_busy'; return $result;
    }
    try {
        $cursor = ! empty( $settings['resume'] ) && empty( $settings['restart'] ) ? (int) get_option( 'pera_nearby_warm_cursor', 0 ) : (int) ( $settings['after_id'] ?? 0 );
        $result['next_after_id'] = $cursor;
        $limit = $settings['limit']; $max_cells = $settings['max_cells'];
        $ids = pera_nearby_warm_property_ids( $cursor, $limit );
        $seen = array(); $attempts = 0; $blocked = false;
        foreach ( $ids as $id ) {
            $result['properties_scanned']++;
            $map = function_exists( 'get_field' ) ? get_field( 'map', $id ) : get_post_meta( $id, 'map', true );
            $coordinates = pera_nearby_coordinates( $map );
            if ( ! $coordinates ) {
                $result['invalid_properties']++;
            } else {
                $cell = pera_nearby_cell( $coordinates ); $key = $cell['key'];
                if ( isset( $seen[$key] ) ) {
                    $result['duplicate_properties']++;
                } else {
                    $cached = get_transient( $key );
                    $status = is_array( $cached ) && ( $cached['updated'] ?? 0 ) >= time() - 7 * DAY_IN_SECONDS ? 'fresh' : ( get_transient( $key . '_retry' ) ? 'backoff' : '' );
                    if ( $status === '' ) {
                        if ( $attempts >= $max_cells ) {
                            $result['deferred']++; $result['reason'] = 'batch_cell_limit';
                            $result['cells'][] = array( 'key' => $key, 'center' => $cell['center'], 'status' => 'batch_cell_limit' );
                            $blocked = true; break;
                        }
                        if ( $dry_run ) {
                            $status = 'would_refresh'; $result['planned']++; $attempts++;
                        } else {
                            // No new cron job: preserve this property's checkpoint on throttling.
                            $status = pera_nearby_refresh( $cell['center'], false );
                            if ( $status === 'deferred' ) {
                                $result['deferred']++; $result['reason'] = 'provider_rate_limit'; $blocked = true;
                            } elseif ( $status === 'success' ) { $result['successful']++; $attempts++; }
                            elseif ( $status === 'failed' ) { $result['failed']++; $attempts++; }
                        }
                    }
                    if ( in_array( $status, array( 'fresh', 'backoff' ), true ) ) $result['skipped']++;
                    $result['cells'][] = array( 'key' => $key, 'center' => $cell['center'], 'status' => $status );
                    if ( $blocked ) break;
                    $seen[$key] = true;
                }
            }
            $result['next_after_id'] = $id;
        }
        $result['complete'] = ! $blocked && count( $ids ) < $limit;
        if ( ! $dry_run && ! empty( $settings['resume'] ) ) update_option( 'pera_nearby_warm_cursor', $result['next_after_id'], false );
        return $result;
    } finally {
        if ( $lock ) pera_nearby_unlock( 'pera_nearby_warm_lock', $lock );
    }
}

/**
 * Warm Nearby Amenities caches for published properties with valid map coordinates.
 *
 * ## OPTIONS
 *
 * [--limit=<number>]
 * : Maximum property IDs examined (1-500; default 100).
 *
 * [--max-cells=<number>]
 * : Maximum provider attempts (1-10; default 1). Global rate gate always applies.
 *
 * [--after-id=<id>]
 * : Explicit last handled property ID. Resume using next_after_id in JSON output.
 *
 * [--resume]
 * : Persist and continue the maintenance cursor in a non-autoloaded option.
 *
 * [--restart]
 * : Start a new --resume pass from ID zero, e.g. after freshness expires.
 *
 * [--dry-run]
 * : Report eligible cells without HTTP, cache writes, locks or cursor updates.
 */
function pera_nearby_warm_command( $args, $assoc_args ) {
    foreach ( array( 'limit' => array( 1, 500, 100 ), 'max-cells' => array( 1, 10, 1 ), 'after-id' => array( 0, PHP_INT_MAX, 0 ) ) as $name => $bounds ) {
        $value = $assoc_args[$name] ?? $bounds[2];
        if ( ! preg_match( '/^[0-9]+$/', (string) $value ) || $value < $bounds[0] || $value > $bounds[1] ) WP_CLI::error( 'Invalid --' . $name . '.' );
    }
    $resume = isset( $assoc_args['resume'] ); $restart = isset( $assoc_args['restart'] );
    if ( ( $resume && isset( $assoc_args['after-id'] ) ) || ( $restart && ! $resume ) ) WP_CLI::error( 'Use --after-id or --resume; --restart requires --resume.' );
    $result = pera_nearby_warm( array( 'limit' => (int) ( $assoc_args['limit'] ?? 100 ), 'max_cells' => (int) ( $assoc_args['max-cells'] ?? 1 ),
        'after_id' => (int) ( $assoc_args['after-id'] ?? 0 ), 'resume' => $resume, 'restart' => $restart, 'dry_run' => isset( $assoc_args['dry-run'] ) ) );
    WP_CLI::log( wp_json_encode( $result ) );
}
WP_CLI::add_command( 'pera nearby warm', 'pera_nearby_warm_command' );

/** Import a compact, validated artifact: wp pera nearby import /absolute/path/dataset.ndjson */
WP_CLI::add_command( 'pera nearby import', function ( $args, $assoc_args ) {
    try {
        if ( count( $args ) !== 1 || ! is_file( $args[0] ) ) throw new RuntimeException( 'Provide one local dataset file.' );
        if ( isset( $assoc_args['report'] ) ) {
            if ( ! is_file( $assoc_args['report'] ) ) throw new RuntimeException( 'Acceptance report unavailable.' );
            $result = pera_nearby_import_batch( $args[0], $assoc_args['report'], (int) ( $assoc_args['batch-size'] ?? 1000 ) );
        } else { $result = pera_nearby_import( $args[0] ); }
        WP_CLI::log( wp_json_encode( $result ) );
    } catch ( Throwable $error ) { pera_nearby_update_error( $error->getMessage() ); WP_CLI::error( $error->getMessage() ); }
} );
WP_CLI::add_command( 'pera nearby rollback', function () {
    try { WP_CLI::log( wp_json_encode( pera_nearby_rollback() ) ); }
    catch ( Throwable $error ) { WP_CLI::error( $error->getMessage() ); }
} );
WP_CLI::add_command( 'pera nearby dataset', function () {
    global $wpdb;
    $active = pera_nearby_dataset_state();
    if ( ! $active ) WP_CLI::error( 'No valid local dataset installed.' );
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$active['table']}`" );
    if ( $wpdb->last_error || (int) $count !== $active['metadata']['count'] ) WP_CLI::error( 'Dataset table missing or count differs.' );
    WP_CLI::log( wp_json_encode( $active ) );
} );

WP_CLI::add_command( 'pera nearby status', function () { WP_CLI::log( wp_json_encode( pera_nearby_update_status() ) ); } );
WP_CLI::add_command( 'pera nearby abort-import', function () {
    try { pera_nearby_abort_import(); WP_CLI::log( 'Pending staging generation removed; active dataset preserved.' ); }
    catch ( Throwable $error ) { WP_CLI::error( $error->getMessage() ); }
} );
WP_CLI::add_command( 'pera nearby cleanup', function () {
    try { WP_CLI::log( wp_json_encode( pera_nearby_cleanup() ) ); }
    catch ( Throwable $error ) { WP_CLI::error( $error->getMessage() ); }
} );
WP_CLI::add_command( 'pera nearby record-error', function ( $args ) {
    if ( count( $args ) !== 1 ) WP_CLI::error( 'Provide one error summary.' );
    pera_nearby_update_error( $args[0] ); pera_nearby_monitor();
} );
WP_CLI::add_command( 'pera nearby benchmark', function () {
    global $wpdb;
    $policy = json_decode( file_get_contents( dirname( __DIR__ ) . '/data/nearby-poi-policy.json' ), true );
    $active = pera_nearby_dataset_state(); if ( ! $active ) WP_CLI::error( 'Import an accepted real dataset on staging first.' );
    $measurements = array();
    foreach ( $policy['districts'] as $name => $point ) {
        $key = 'pera_poi_local_c2_' . md5( $active['table'] . serialize( $point ) ); delete_transient( $key );
        $queries = $wpdb->num_queries; $time = microtime( true ); $before = memory_get_usage( true );
        $places = pera_nearby_local_places( $point ); $cold = microtime( true ) - $time; $cold_queries = $wpdb->num_queries - $queries;
        $queries = $wpdb->num_queries; $time = microtime( true ); $warm = pera_nearby_local_places( $point );
        $measurements[$name] = array( 'cold_seconds' => $cold, 'cold_queries' => $cold_queries, 'warm_seconds' => microtime( true ) - $time,
            'warm_queries' => $wpdb->num_queries - $queries, 'memory_delta_bytes' => memory_get_usage( true ) - $before,
            'php_process_peak_bytes' => memory_get_peak_usage( true ), 'output_count' => count( $places ), 'cache_output_equal' => $places === $warm );
    }
    WP_CLI::log( wp_json_encode( array( 'version' => $active['metadata']['version'], 'districts' => $measurements ) ) );
} );

WP_CLI::add_command( 'pera nearby validate-import', function ( $args, $assoc_args ) {
    try {
        if ( count( $args ) !== 1 || empty( $assoc_args['report'] ) ) throw new RuntimeException( 'Provide a local artifact and --report path.' );
        $active = pera_nearby_dataset_state();
        $report = json_decode( file_get_contents( $assoc_args['report'] ), true );
        if ( ( $report['passed'] ?? null ) === true && ( $active['metadata']['version'] ?? null ) === ( $report['version'] ?? '' ) && ( $active['metadata']['sha256'] ?? null ) === ( $report['body_sha256'] ?? '' ) && hash_equals( hash_file( 'sha256', $args[0] ), $report['artifact_sha256'] ?? '' ) ) {
            WP_CLI::log( 'Identical accepted dataset already active.' ); return;
        }
        pera_nearby_preflight( $args[0], $assoc_args['report'] ); WP_CLI::log( 'Production artifact preflight passed.' );
    } catch ( Throwable $error ) { pera_nearby_update_error( $error->getMessage() ); WP_CLI::error( $error->getMessage() ); }
} );
