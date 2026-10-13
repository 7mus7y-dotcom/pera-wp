<?php
/** Cached, demand-driven OSM nearby places. No HTTP during rendering. */
if ( ! defined( 'ABSPATH' ) ) exit;

function pera_nearby_coordinates( $map ) {
    if ( ! is_array( $map ) || ! isset( $map['lat'], $map['lng'] ) || ! is_numeric( $map['lat'] ) || ! is_numeric( $map['lng'] ) ) return null;
    $lat = (float) $map['lat']; $lng = (float) $map['lng'];
    return is_finite( $lat ) && is_finite( $lng ) && abs( $lat ) <= 90 && abs( $lng ) <= 180 ? array( $lat, $lng ) : null;
}

function pera_nearby_cell( array $coordinates ) {
    // A shared ~1km cell. Query coverage includes the maximum cell offset.
    $center = array( round( $coordinates[0], 2 ), round( $coordinates[1], 2 ) );
    return array( 'center' => $center, 'key' => 'pera_poi_v1_' . md5( implode( ',', $center ) ) );
}

function pera_nearby_distance( array $a, array $b ) {
    $dlat = deg2rad( $b[0] - $a[0] ); $dlng = deg2rad( $b[1] - $a[1] );
    $h = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $a[0] ) ) * cos( deg2rad( $b[0] ) ) * sin( $dlng / 2 ) ** 2;
    return 6371000 * 2 * asin( sqrt( min( 1, max( 0, $h ) ) ) );
}

function pera_nearby_group( array $tags ) {
    $identity = implode( ' ', array_intersect_key( $tags, array_flip( array( 'name', 'name:tr', 'network', 'operator', 'brand', 'route_names' ) ) ) );
    $station = in_array( $tags['railway'] ?? '', array( 'station', 'halt' ), true ) || ( ( $tags['public_transport'] ?? '' ) === 'station' && ( ( $tags['train'] ?? '' ) === 'yes' || ( $tags['subway'] ?? '' ) === 'yes' || ( $tags['station'] ?? '' ) === 'subway' ) );
    $bus = ( $tags['highway'] ?? '' ) === 'bus_stop' || ( $tags['bus'] ?? '' ) === 'yes';
    if ( $station && preg_match( '/marmaray/iu', $identity ) ) return 'marmaray';
    if ( ( $bus || $station ) && preg_match( '/metrob[üu]s|metrobus/iu', $identity ) ) return 'metrobus';
    if ( $station && ( ( $tags['station'] ?? '' ) === 'subway' || ( $tags['subway'] ?? '' ) === 'yes' || ( $tags['route_subway'] ?? '' ) === 'yes' ) ) return 'metro';
    if ( $station ) return 'rail';
    if ( $bus ) return 'bus';
    if ( ( $tags['amenity'] ?? '' ) === 'ferry_terminal' ) return 'ferry';
    $mapping = array( 'school' => 'schools', 'kindergarten' => 'schools', 'college' => 'universities', 'university' => 'universities', 'hospital' => 'hospitals', 'clinic' => 'clinics', 'doctors' => 'clinics', 'pharmacy' => 'pharmacies' );
    if ( isset( $mapping[ $tags['amenity'] ?? '' ] ) ) return $mapping[ $tags['amenity'] ];
    if ( in_array( $tags['leisure'] ?? '', array( 'park', 'garden', 'nature_reserve' ), true ) ) return 'parks';
    if ( in_array( $tags['leisure'] ?? '', array( 'sports_centre', 'stadium', 'pitch', 'fitness_centre' ), true ) ) return 'sport';
    if ( ( $tags['shop'] ?? '' ) === 'mall' ) return 'malls';
    if ( ( $tags['shop'] ?? '' ) === 'supermarket' ) return 'supermarkets';
    return null;
}

function pera_nearby_normalize( array $elements ) {
    $routes = array(); $places = array();
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) || ! is_array( $element['tags'] ?? null ) ) continue;
        $element['tags'] = array_filter( $element['tags'], 'is_string' );
        if ( ( $element['type'] ?? '' ) !== 'relation' || empty( $element['tags']['route'] ) ) continue;
        foreach ( is_array( $element['members'] ?? null ) ? $element['members'] : array() as $member ) {
            if ( ! is_array( $member ) || ! in_array( $member['type'] ?? '', array( 'node', 'way', 'relation' ), true ) || ! is_scalar( $member['ref'] ?? null ) || ! preg_match( '/^[1-9][0-9]*$/', (string) $member['ref'] ) ) continue;
            $id = ( $member['type'] ?? '' ) . '/' . ( $member['ref'] ?? '' );
            $routes[$id]['route_names'] = ( $routes[$id]['route_names'] ?? '' ) . ' ' . implode( ' ', array_intersect_key( $element['tags'], array_flip( array( 'name', 'network', 'operator' ) ) ) );
            if ( $element['tags']['route'] === 'subway' ) $routes[$id]['route_subway'] = 'yes';
        }
    }
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) continue;
        $type = $element['type'] ?? ''; $id = $element['id'] ?? null;
        if ( ! in_array( $type, array( 'node', 'way', 'relation' ), true ) || ! is_scalar( $id ) || ! preg_match( '/^[1-9][0-9]*$/', (string) $id ) ) continue;
        $tags = array_merge( is_array( $element['tags'] ?? null ) ? $element['tags'] : array(), $routes[$type . '/' . $id] ?? array() );
        $tags = array_filter( $tags, 'is_string' );
        $group = pera_nearby_group( $tags );
        $location = is_array( $element['center'] ?? null ) ? $element['center'] : $element;
        $point = pera_nearby_coordinates( array( 'lat' => $location['lat'] ?? null, 'lng' => $location['lon'] ?? null ) );
        if ( ! $group || ! $point || empty( $tags['name'] ) ) continue;
        $names = array_filter( $tags, function ( $value, $key ) { return is_string( $value ) && ( $key === 'name' || strpos( $key, 'name:' ) === 0 ); }, ARRAY_FILTER_USE_BOTH );
        if ( empty( $names['name'] ) ) continue;
        $places[$type . '/' . $id] = array( 'id' => $type . '/' . $id, 'point' => $point, 'names' => $names, 'group' => $group );
    }
    return array_values( $places );
}

function pera_nearby_query( array $center ) {
    $around = '(around:4300,' . implode( ',', $center ) . ')';
    $selectors = array( '[railway~"^(station|halt)$"]', '[public_transport="station"]', '[highway="bus_stop"]', '[public_transport="platform"][bus="yes"]', '[amenity~"^(ferry_terminal|school|kindergarten|college|university|hospital|clinic|doctors|pharmacy)$"]', '[leisure~"^(park|garden|nature_reserve|sports_centre|stadium|pitch|fitness_centre)$"]', '[shop~"^(mall|supermarket)$"]' );
    $query = '[out:json][timeout:20][maxsize:16777216];(';
    foreach ( $selectors as $selector ) $query .= 'nwr' . $selector . $around . ';';
    return $query . ')->.places;.places out body center;rel(bn.places)[route~"^(subway|train|bus)$"];out body;';
}

function pera_nearby_cached( array $coordinates ) {
    $cell = pera_nearby_cell( $coordinates ); $cached = get_transient( $cell['key'] );
    if ( ( ! is_array( $cached ) || ( $cached['updated'] ?? 0 ) < time() - 7 * DAY_IN_SECONDS ) && ! get_transient( $cell['key'] . '_retry' ) && ! wp_next_scheduled( 'pera_nearby_refresh', array( $cell['center'] ) ) ) {
        wp_schedule_single_event( time() + 10, 'pera_nearby_refresh', array( $cell['center'] ) );
    }
    return is_array( $cached ) ? ( $cached['places'] ?? array() ) : array();
}

/** Acquire a bounded option lock without deleting another worker's new lock. */
function pera_nearby_lock( $name, $ttl ) {
    global $wpdb;
    $previous = (int) get_option( $name, 0 );
    if ( $previous && $previous <= time() ) pera_nearby_unlock( $name, $previous );
    $expires = time() + $ttl;
    return add_option( $name, $expires, '', false ) ? $expires : false;
}

function pera_nearby_unlock( $name, $expires ) {
    global $wpdb;
    $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, (string) $expires ) );
    if ( $deleted ) wp_cache_delete( $name, 'options' );
}

/** Return an observable outcome; CLI uses the same gate without queuing retries. */
function pera_nearby_refresh( $center, $schedule_deferred = true ) {
    $center = pera_nearby_coordinates( array( 'lat' => $center[0] ?? null, 'lng' => $center[1] ?? null ) );
    if ( ! $center ) return 'invalid';
    $cell = pera_nearby_cell( $center ); $key = $cell['key'];
    if ( get_transient( $key . '_retry' ) ) return 'backoff';
    $cached = get_transient( $key );
    if ( is_array( $cached ) && ( $cached['updated'] ?? 0 ) >= time() - 7 * DAY_IN_SECONDS ) return 'fresh';
    // Cron and CLI share the same one-request-per-minute budget.
    if ( ! pera_nearby_lock( 'pera_nearby_provider_gate', 60 ) ) {
        if ( $schedule_deferred && ! wp_next_scheduled( 'pera_nearby_refresh', array( $center ) ) ) wp_schedule_single_event( time() + 65 + wp_rand( 0, 60 ), 'pera_nearby_refresh', array( $center ) );
        return 'deferred';
    }
    set_transient( $key . '_retry', true, HOUR_IN_SECONDS );
    $response = wp_remote_post( apply_filters( 'pera_nearby_overpass_endpoint', 'https://overpass-api.de/api/interpreter' ), array(
        'timeout' => 25, 'redirection' => 0, 'limit_response_size' => 8 * 1024 * 1024,
        'headers' => array( 'User-Agent' => 'PeraNearby/1.0 (' . home_url( '/' ) . ')', 'Accept' => 'application/json' ),
        'body' => array( 'data' => pera_nearby_query( $center ) ),
    ) );
    if ( is_wp_error( $response ) ) return 'failed';
    if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
        // Honour provider Retry-After, including an HTTP date, when longer than our backoff.
        $retry_after = (string) wp_remote_retrieve_header( $response, 'retry-after' );
        $delay = preg_match( '/^[0-9]+$/', $retry_after ) ? (int) $retry_after : ( (int) strtotime( $retry_after ) - time() );
        if ( $delay > HOUR_IN_SECONDS ) set_transient( $key . '_retry', true, min( $delay, PHP_INT_MAX - time() ) );
        return 'failed';
    }
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    // Overpass may return HTTP 200 with a timeout remark and partial results.
    if ( ! is_array( $data ) || ! isset( $data['elements'] ) || ! is_array( $data['elements'] ) || ! empty( $data['remark'] ) ) return 'failed';
    set_transient( $key, array( 'updated' => time(), 'places' => pera_nearby_normalize( $data['elements'] ) ), 30 * DAY_IN_SECONDS );
    delete_transient( $key . '_retry' );
    return 'success';
}
add_action( 'pera_nearby_refresh', 'pera_nearby_refresh' );

/** Versioned local reference data; no HTTP or dependency on the ordinary POI cache. */
function pera_nearby_airports( array $coordinates ) {
    static $dataset;
    if ( null === $dataset ) $dataset = require dirname( __DIR__ ) . '/data/nearby-airports.php';
    $key = 'pera_airports_' . $dataset['version'] . '_' . md5( implode( ',', $coordinates ) );
    $cached = get_transient( $key );
    if ( is_array( $cached ) ) return $cached;
    $airports = array();
    foreach ( $dataset['airports'] as $airport ) {
        $distance = pera_nearby_distance( $coordinates, $airport['point'] );
        // Keep these Istanbul reference airports relevant to the property's region.
        if ( $distance > 150000 ) continue;
        $airport['distance'] = $distance;
        $airport['url'] = 'https://www.openstreetmap.org/?mlat=' . $airport['point'][0] . '&mlon=' . $airport['point'][1] . '#map=12/' . implode( '/', $airport['point'] );
        $airports[] = $airport;
    }
    usort( $airports, function ( $a, $b ) { return $a['distance'] <=> $b['distance']; } );
    $airports = array_slice( $airports, 0, 3 );
    set_transient( $key, $airports, 30 * DAY_IN_SECONDS );
    return $airports;
}

function pera_nearby_categories() {
    return array(
        'transport' => array( pera_ml_ui( 'Transport', 'theme.nearby.transport' ), array( 'metro', 'marmaray', 'metrobus', 'rail', 'bus', 'ferry', 'airports' ) ),
        'education' => array( pera_ml_ui( 'Education', 'theme.nearby.education' ), array( 'schools', 'universities' ) ),
        'healthcare' => array( pera_ml_ui( 'Healthcare', 'theme.nearby.healthcare' ), array( 'hospitals', 'clinics', 'pharmacies' ) ),
        'parks' => array( pera_ml_ui( 'Parks & Sport', 'theme.nearby.parks_sport' ), array( 'parks', 'sport' ) ),
        'shopping' => array( pera_ml_ui( 'Shopping', 'theme.nearby.shopping' ), array( 'malls', 'supermarkets' ) ),
    );
}

function pera_nearby_group_labels() {
    return array(
        'metro' => pera_ml_ui( 'Metro', 'theme.nearby.metro' ),
        'marmaray' => pera_ml_ui( 'Marmaray', 'theme.nearby.marmaray' ),
        'metrobus' => pera_ml_ui( 'Metrobüs', 'theme.nearby.metrobus' ),
        'rail' => pera_ml_ui( 'Rail stations', 'theme.nearby.rail' ),
        'bus' => pera_ml_ui( 'Bus', 'theme.nearby.bus' ),
        'ferry' => pera_ml_ui( 'Ferry', 'theme.nearby.ferry' ),
        'airports' => pera_ml_ui( 'Airports', 'theme.nearby.airports' ),
        'schools' => pera_ml_ui( 'Schools', 'theme.nearby.schools' ),
        'universities' => pera_ml_ui( 'Universities & colleges', 'theme.nearby.universities' ),
        'hospitals' => pera_ml_ui( 'Hospitals', 'theme.nearby.hospitals' ),
        'clinics' => pera_ml_ui( 'Clinics', 'theme.nearby.clinics' ),
        'pharmacies' => pera_ml_ui( 'Pharmacies', 'theme.nearby.pharmacies' ),
        'parks' => pera_ml_ui( 'Parks & green spaces', 'theme.nearby.parks' ),
        'sport' => pera_ml_ui( 'Sports facilities', 'theme.nearby.sport' ),
        'malls' => pera_ml_ui( 'Shopping centres', 'theme.nearby.malls' ),
        'supermarkets' => pera_ml_ui( 'Supermarkets', 'theme.nearby.supermarkets' ),
    );
}

function pera_nearby_groups( array $places, array $coordinates ) {
    $groups = array();
    foreach ( $places as $place ) {
        // Ignore aerodromes retained in older ordinary-POI caches.
        if ( ( $place['group'] ?? '' ) === 'airports' ) continue;
        $distance = pera_nearby_distance( $coordinates, $place['point'] );
        if ( $distance > 3500 ) continue;
        $place['distance'] = $distance; $groups[$place['group']][] = $place;
    }
    foreach ( $groups as &$group ) {
        usort( $group, function ( $a, $b ) { return $a['distance'] <=> $b['distance']; } );
        // OSM often represents a station both as a node and a building/area.
        $unique = array();
        foreach ( $group as $place ) {
            $duplicate = false;
            foreach ( $unique as $existing ) if ( $existing['names']['name'] === $place['names']['name'] && pera_nearby_distance( $existing['point'], $place['point'] ) < 100 ) $duplicate = true;
            if ( ! $duplicate ) $unique[] = $place;
            if ( count( $unique ) === 3 ) break;
        }
        $group = $unique;
    }
    unset( $group );
    return $groups;
}

function pera_render_nearby_amenities( $map ) {
    $coordinates = pera_nearby_coordinates( $map );
    if ( ! $coordinates ) return;
    $groups = pera_nearby_groups( pera_nearby_cached( $coordinates ), $coordinates );
    $airports = pera_nearby_airports( $coordinates );
    if ( $airports ) $groups['airports'] = $airports;
    if ( ! $groups ) return;
    $categories = pera_nearby_categories(); $labels = pera_nearby_group_labels(); $active = null;
    foreach ( $categories as $key => $category ) if ( array_intersect( $category[1], array_keys( $groups ) ) ) { $active = $key; break; }
    $id = wp_unique_id( 'nearby-' );
    $language = function_exists( 'pera_ml_current_language' ) ? pera_ml_current_language() : substr( get_locale(), 0, 2 );
    wp_enqueue_script( 'pera-component-tabs', get_stylesheet_directory_uri() . '/js/component-tabs.js', array(), pera_get_asset_version( '/js/component-tabs.js' ), true );
    ?>
    <section class="card-shell mt-md" data-component-tabs aria-labelledby="<?php echo esc_attr( $id ); ?>-heading">
        <h2 class="inline-row" id="<?php echo esc_attr( $id ); ?>-heading"><svg class="icon" aria-hidden="true" focusable="false"><use href="#icon-map"></use></svg><?php echo esc_html( pera_ml_ui( 'Nearby amenities', 'theme.nearby.heading' ) ); ?></h2>
        <p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Approximate straight-line distances, not walking or driving distances. Local places: within 3.5 km. Airports: within 150 km.', 'theme.nearby.distance_notice' ) ); ?></p>
        <div class="section-cta justify-start component-tabs" role="tablist" aria-label="<?php echo esc_attr( pera_ml_ui( 'Amenity categories', 'theme.nearby.categories' ) ); ?>">
        <?php foreach ( $categories as $key => $category ) : ?>
            <button type="button" class="pill pill--lg <?php echo $active === $key ? 'pill--active' : ''; ?>" role="tab" id="<?php echo esc_attr( $id . '-' . $key ); ?>-tab" aria-controls="<?php echo esc_attr( $id . '-' . $key ); ?>-panel" aria-selected="<?php echo $active === $key ? 'true' : 'false'; ?>" tabindex="<?php echo $active === $key ? '0' : '-1'; ?>"><?php echo esc_html( $category[0] ); ?></button>
        <?php endforeach; ?>
        </div>
        <?php foreach ( $categories as $key => $category ) : ?>
        <div role="tabpanel" id="<?php echo esc_attr( $id . '-' . $key ); ?>-panel" aria-labelledby="<?php echo esc_attr( $id . '-' . $key ); ?>-tab" tabindex="0" <?php if ( $active !== $key ) echo 'hidden'; ?>>
            <?php if ( ! array_intersect( $category[1], array_keys( $groups ) ) ) : ?>
                <p class="text-soft"><?php echo esc_html( pera_ml_ui( 'No nearby places recorded in this category.', 'theme.nearby.empty' ) ); ?></p>
            <?php else : ?>
            <div class="grid-2">
            <?php foreach ( $category[1] as $group ) : if ( empty( $groups[$group] ) ) continue; ?>
                <div class="card-shell p-sm"><h3><?php echo esc_html( $labels[$group] ); ?></h3><ul class="poi-list" role="list">
                <?php foreach ( $groups[$group] as $place ) :
                    $name = $place['names']['name:' . $language] ?? $place['names']['name'];
                    $distance = $place['distance'] < 1000
                        ? sprintf( pera_ml_ui( '%s m', 'theme.nearby.metres' ), number_format_i18n( round( $place['distance'] ) ) )
                        : sprintf( pera_ml_ui( '%s km', 'theme.nearby.kilometres' ), number_format_i18n( $place['distance'] / 1000, 1 ) ); ?>
                    <li><a href="<?php echo esc_url( $place['url'] ?? 'https://www.openstreetmap.org/' . $place['id'] ); ?>"><bdi><?php echo esc_html( $name ); ?></bdi></a> <span class="text-soft text-nowrap"><bdi><?php echo esc_html( $distance ); ?></bdi></span></li>
                <?php endforeach; ?>
                </ul></div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Map data:', 'theme.nearby.attribution' ) ); ?> © <a href="https://www.openstreetmap.org/copyright"><?php echo esc_html( pera_ml_ui( 'OpenStreetMap contributors', 'theme.nearby.contributors' ) ); ?></a> (ODbL).</p>
        <?php if ( $airports ) : ?>
        <p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Airport reference coordinates:', 'theme.nearby.airport_source' ) ); ?> <a href="https://github.com/davidmegginson/ourairports-data/blob/132fc8c3471c96cfffe3ece8a89f9f252f670243/airports.csv"><?php echo esc_html( pera_ml_ui( 'OurAirports (public domain)', 'theme.nearby.ourairports' ) ); ?></a></p>
        <?php endif; ?>
    </section>
    <?php
}

if ( defined( 'WP_CLI' ) && WP_CLI ) require_once __DIR__ . '/nearby-amenities-cli.php';
