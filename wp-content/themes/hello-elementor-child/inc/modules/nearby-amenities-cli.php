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
