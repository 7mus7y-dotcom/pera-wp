<?php
/** Standalone contract tests; never contacts OSM or a WordPress database. */
define( 'ABSPATH', __DIR__ );
define( 'WP_CLI', true );
define( 'DISABLE_WP_CRON', true );
class WP_CLI {
    public static $commands = array(); public static $output;
    static function add_command( $name, $callback ) { self::$commands[$name] = $callback; }
    static function log( $value ) { self::$output = $value; }
    static function error( $message ) { throw new InvalidArgumentException( $message ); }
}
function get_posts( $args ) { return $args['paged'] === 1 ? array( 1, 2, 3 ) : array(); }
function get_field( $name, $id ) { global $warm_maps; if ( isset( $warm_maps ) ) return $warm_maps[$id] ?? null; return $id === 1 ? array( 'lat' => 41, 'lng' => 29 ) : ( $id === 2 ? null : array( 'lat' => 'invalid', 'lng' => 29 ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 );
$cache = array(); $events = array(); $options = array(); $requests = array(); $response = array( 'code' => 200, 'body' => '{"elements":[]}' );
function add_action( ...$args ) {}
function get_transient( $key ) { global $cache; return isset( $cache[$key] ) && $cache[$key][1] > time() ? $cache[$key][0] : false; }
function set_transient( $key, $value, $ttl ) { global $cache; $cache[$key] = array( $value, time() + $ttl ); }
function delete_transient( $key ) { global $cache; unset( $cache[$key] ); }
function wp_next_scheduled( $hook, $args ) { global $events; return $events[serialize( $args )] ?? false; }
function wp_schedule_single_event( $time, $hook, $args ) { global $events; $events[serialize( $args )] = $time; }
function update_option( $key, $value, $autoload = null ) { global $options; $options[$key] = $value; return true; }
function get_option( $key, $default = false ) { global $options; return $options[$key] ?? $default; }
function add_option( $key, $value, ...$rest ) { global $options; if ( isset( $options[$key] ) ) return false; $options[$key] = $value; return true; }
function wp_cache_delete( ...$args ) {}
function wp_rand( $a, $b ) { return $a; }
function apply_filters( $key, $value ) { return $key === 'pera_nearby_provider' ? ( $GLOBALS['test_provider'] ?? 'overpass' ) : $value; }
function home_url( $path ) { return 'https://example.test' . $path; }
function wp_remote_post( $url, $args ) { global $response, $requests; $requests[] = $args; return $response; }
function is_wp_error( $value ) { return $value === 'error'; }
function wp_remote_retrieve_response_code( $value ) { return $value['code']; }
function wp_remote_retrieve_header( $value, $header ) { return $value['headers'][$header] ?? ''; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
function pera_ml_ui( $source, $key ) {
    global $language, $native_ui;
    if ( isset( $native_ui ) ) return $native_ui->get( $source, $key, $language );
    $translations = array(
        'theme.nearby.heading' => array( 'tr' => 'Yakındaki olanaklar', 'ar' => 'المرافق القريبة' ),
        'theme.nearby.transport' => array( 'tr' => 'Ulaşım', 'ar' => 'النقل' ),
        'theme.nearby.education' => array( 'tr' => 'Eğitim', 'ar' => 'التعليم' ),
        'theme.nearby.healthcare' => array( 'tr' => 'Sağlık', 'ar' => 'الرعاية الصحية' ),
        'theme.nearby.parks_sport' => array( 'tr' => 'Parklar ve spor', 'ar' => 'الحدائق والرياضة' ),
        'theme.nearby.shopping' => array( 'tr' => 'Alışveriş', 'ar' => 'التسوق' ),
        'theme.nearby.metro' => array( 'tr' => 'Metro', 'ar' => 'المترو' ),
        'theme.nearby.metrobus' => array( 'tr' => 'Metrobüs', 'ar' => 'المتروبوس' ),
        'theme.nearby.bus' => array( 'tr' => 'Otobüs', 'ar' => 'الحافلات' ),
        'theme.nearby.airports' => array( 'tr' => 'Havalimanları', 'ar' => 'المطارات' ),
        'theme.nearby.distance_notice' => array( 'tr' => 'Yaklaşık kuş uçuşu mesafeler; yürüyüş veya sürüş mesafeleri değildir. Yerel olanaklar: 3,5 km içinde. Havalimanları: 150 km içinde.', 'ar' => 'مسافات تقريبية بخط مستقيم وليست مسافات مشي أو قيادة. المرافق المحلية ضمن ٣٫٥ كم والمطارات ضمن ١٥٠ كم.' ),
        'theme.nearby.empty' => array( 'tr' => 'Bu kategoride kayıtlı yakın yer bulunmuyor.', 'ar' => 'لا توجد أماكن قريبة مسجلة في هذه الفئة.' ),
        'theme.nearby.metres' => array( 'tr' => '%s m', 'ar' => '%s م' ),
        'theme.nearby.kilometres' => array( 'tr' => '%s km', 'ar' => '%s كم' ),
    );
    return $translations[$key][$language] ?? $source;
}
function pera_ml_current_language() { global $language; return $language ?: 'en'; }
function wp_unique_id( $prefix ) { static $id = 0; return $prefix . ++$id; }
function wp_enqueue_script( ...$args ) { global $scripts; $scripts[$args[0]] = $args; }
function get_stylesheet_directory_uri() { return '..'; }
function pera_get_asset_version( $path ) { return 1; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function number_format_i18n( $value, $decimals = 0 ) { return number_format( $value, $decimals ); }
class Nearby_Test_DB {
    public $options = 'wp_options'; public $posts = 'wp_posts';
    private $arguments;
    function prepare( $sql, ...$args ) { $this->arguments = $args; return $sql; }
    function query( $sql ) {
        global $options;
        list( $key, $expected ) = $this->arguments;
        if ( (string) ( $options[$key] ?? '' ) !== $expected ) return 0;
        unset( $options[$key] ); return 1;
    }
    function get_col( $sql ) {
        global $warm_maps;
        check( strpos( $sql, 'ID > %d ORDER BY ID ASC LIMIT %d' ) !== false && $this->arguments[0] === 'property' && $this->arguments[1] === 'publish', 'Published-only keyset SQL' );
        $ids = array_keys( $warm_maps ); sort( $ids );
        return array_slice( array_values( array_filter( $ids, function ( $id ) { return $id > $this->arguments[2]; } ) ), 0, $this->arguments[3] );
    }
}
$wpdb = new Nearby_Test_DB();
require dirname( __DIR__ ) . '/inc/modules/nearby-amenities.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$coordinates = array( 41.01, 29.01 ); $map = array( 'lat' => 41.01, 'lng' => 29.01 ); $key = pera_nearby_cell( $coordinates )['key'];
foreach ( array( null, array(), array( 'lat' => '', 'lng' => 2 ), array( 'lat' => 91, 'lng' => 2 ), array( 'lat' => 1, 'lng' => INF ), array( 'lat' => 'bad', 'lng' => 2 ) ) as $invalid ) check( pera_nearby_coordinates( $invalid ) === null, 'Invalid coordinates rejected' );
check( pera_nearby_coordinates( array( 'lat' => 0, 'lng' => 0 ) ) === array( 0.0, 0.0 ), 'Zero is a valid coordinate' );
check( pera_nearby_cell( array( 41.011, 29.011 ) )['key'] === $key, 'Nearby properties share cell' );
check( abs( pera_nearby_distance( array( 0, 0 ), array( 0, 1 ) ) - 111195 ) < 2, 'Haversine distance' );
check( pera_nearby_group( array( 'railway' => 'station', 'name' => 'Marmaray' ) ) === 'marmaray', 'Marmaray explicit classification' );
check( pera_nearby_group( array( 'highway' => 'bus_stop', 'network' => 'Metrobüs' ) ) === 'metrobus', 'Metrobus classification' );
check( pera_nearby_group( array( 'railway' => 'station', 'name' => 'Unclassified' ) ) === 'rail', 'Generic rail not assumed Metro' );
check( pera_nearby_cached( $coordinates ) === array() && count( $requests ) === 0 && count( $events ) === 1, 'Miss schedules without HTTP' );
pera_nearby_cached( $coordinates ); check( count( $events ) === 1, 'Refresh deduplicated' );
pera_nearby_refresh( $coordinates ); check( count( $requests ) === 1 && get_transient( $key )['places'] === array(), 'Successful empty cached' );
check( $requests[0]['timeout'] === 25 && $requests[0]['limit_response_size'] === 8388608, 'Bounded HTTP' );
$events = array(); pera_nearby_cached( $coordinates ); check( ! $events, 'Fresh empty cache not refreshed' );
$elements = array(
    array( 'type' => 'node', 'id' => 1, 'lat' => 41.011, 'lon' => 29.01, 'tags' => array( 'railway' => 'station', 'name' => 'Original', 'name:tr' => 'Yerel', 'name:ar' => 'محطة' ) ),
    array( 'type' => 'relation', 'id' => 2, 'tags' => array( 'route' => 'subway', 'name' => 'M2' ), 'members' => array( array( 'type' => 'node', 'ref' => 1 ) ) ),
    array( 'type' => 'way', 'id' => 3, 'center' => array( 'lat' => 41.02, 'lon' => 29.01 ), 'tags' => array( 'amenity' => 'school', 'name' => 'School <unsafe>' ) ),
    array( 'type' => 'node', 'id' => 4, 'lat' => 41.01, 'lon' => 29.01, 'tags' => array( 'shop' => 'mall' ) ),
);
// Overpass uses lon; normalize that explicitly, unlike the ACF map's lng.
$places = pera_nearby_normalize( $elements );
check( count( $places ) === 2 && $places[0]['group'] === 'metro', 'Route membership, centers and unnamed exclusion' );
set_transient( $key, array( 'updated' => time() - 8 * DAY_IN_SECONDS, 'places' => $places ), 30 * DAY_IN_SECONDS );
$options = array(); $events = array(); $response = 'error'; pera_nearby_refresh( $coordinates );
check( get_transient( $key )['places'] === $places && get_transient( $key . '_retry' ), 'Failure keeps stale and backs off' );
$n = count( $requests ); pera_nearby_refresh( $coordinates ); check( count( $requests ) === $n, 'Backoff prevents another request' );
foreach ( array( array( 'code' => 429, 'body' => '' ), array( 'code' => 200, 'body' => '{"remark":"timeout","elements":[]}' ), array( 'code' => 200, 'body' => 'broken' ) ) as $response ) {
    $options = array(); delete_transient( $key . '_retry' ); pera_nearby_refresh( $coordinates );
    check( get_transient( $key )['places'] === $places, 'Provider error/partial/invalid body preserves stale' );
}
foreach ( array( '7200', gmdate( 'D, d M Y H:i:s \G\M\T', time() + 7200 ) ) as $retry_after ) {
    $options = array(); delete_transient( $key . '_retry' );
    $response = array( 'code' => 429, 'body' => '', 'headers' => array( 'retry-after' => $retry_after ) );
    check( pera_nearby_refresh( $coordinates, false ) === 'failed' && $cache[$key . '_retry'][1] >= time() + 7199, 'Provider Retry-After is respected for seconds and HTTP dates' );
}
$options = array( 'pera_nearby_provider_gate' => time() + 60 ); delete_transient( $key . '_retry' ); $events = array(); $n = count( $requests ); pera_nearby_refresh( $coordinates );
check( count( $requests ) === $n && count( $events ) === 1, 'Site-wide gate defers request' );
$options = array( 'pera_nearby_provider_gate' => time() - 1 ); $response = array( 'code' => 200, 'body' => json_encode( array( 'elements' => $elements ) ) ); pera_nearby_refresh( $coordinates );
check( get_transient( $key )['updated'] >= time() - 1 && ! get_transient( $key . '_retry' ), 'Expired gate and successful refresh' );
$many = array(); for ( $i = 0; $i < 5; $i++ ) $many[] = array( 'id' => 'node/' . $i, 'point' => array( 41.01 + $i * .001, 29.01 ), 'names' => array( 'name' => 'Place ' . $i ), 'group' => 'bus' );
$many[] = array( 'id' => 'node/99', 'point' => array( 42, 29 ), 'names' => array( 'name' => 'Far' ), 'group' => 'bus' );
$groups = pera_nearby_groups( $many, $coordinates ); check( count( $groups['bus'] ) === 3 && $groups['bus'][0]['distance'] === 0.0, 'Nearest three, radius filter' );
foreach ( array( 'en', 'tr', 'ar' ) as $language ) {
    ob_start(); pera_render_nearby_amenities( $map ); $html = ob_get_clean();
    check( substr_count( $html, 'role="tab"' ) === 5 && substr_count( $html, 'role="tabpanel"' ) === 5, 'Five connected tabs' );
    check( strpos( $html, 'School &lt;unsafe&gt;' ) !== false, 'OSM names escaped' );
    check( strpos( $html, $language === 'en' ? 'Original' : ( $language === 'tr' ? 'Yerel' : 'محطة' ) ) !== false, 'Reliable local name selected' );
    if ( getenv( 'NEARBY_FIXTURE_DIR' ) ) file_put_contents( getenv( 'NEARBY_FIXTURE_DIR' ) . '/' . $language . '.html', $html );
}
$far_map = array( 'lat' => 35, 'lng' => 35 ); $far_key = pera_nearby_cell( array( 35, 35 ) )['key'];
$school = $places[1]; $school['point'] = array( 35, 35 );
set_transient( $far_key, array( 'updated' => time(), 'places' => array( $school ) ), DAY_IN_SECONDS );
ob_start(); pera_render_nearby_amenities( $far_map ); $html = ob_get_clean(); check( preg_match( '/education-tab[^>]+aria-selected="true"/', $html ), 'First nonempty category selected outside airport coverage' );
set_transient( $far_key, array( 'updated' => time(), 'places' => array() ), DAY_IN_SECONDS );
ob_start(); pera_render_nearby_amenities( $far_map ); pera_render_nearby_amenities( null ); check( ob_get_clean() === '', 'Empty/missing component hidden outside airport coverage' );
set_transient( $key, array( 'updated' => time(), 'places' => array() ), DAY_IN_SECONDS );
$n = count( $requests ); ob_start(); pera_render_nearby_amenities( $map ); $html = ob_get_clean();
check( strpos( $html, 'Airport (IST)' ) !== false && strpos( $html, '(SAW)' ) !== false && count( $requests ) === $n, 'Airports available despite empty ordinary cache, without HTTP' );
$before = array( $cache, $events, $options, $requests );
WP_CLI::$commands['pera nearby audit']();
check( json_decode( WP_CLI::$output, true ) === array( 'published' => 3, 'valid' => 1, 'missing_or_invalid' => 2 ), 'Read-only audit counts' );
check( array( $cache, $events, $options, $requests ) === $before, 'Audit does not fetch, schedule or write caches' );
check( strpos( pera_nearby_query( $coordinates ), '.places out body center;' ) !== false, 'Query includes node coordinates and area centers' );
// Airport cache is exact-coordinate and independent of geographically shared POI cells.
$n = count( $requests ); $airports = pera_nearby_airports( $coordinates ); $cache_before = $cache;
check( count( $airports ) === 2 && $airports[0]['distance'] > 3500 && $airports[0]['distance'] < $airports[1]['distance'], 'Two relevant airports beyond ordinary radius, sorted nearest first' );
check( pera_nearby_airports( $coordinates ) === $airports && $cache === $cache_before && count( $requests ) === $n, 'Airport cache hit makes no HTTP or repeated writes' );
$at_airport = pera_nearby_airports( array( 41.274874, 28.732136 ) );
check( $at_airport[0]['id'] === 'LTFM' && $at_airport[0]['distance'] === 0.0, 'Verified airport reference point distance' );
check( ! pera_nearby_airports( array( 35, 35 ) ), 'Airport relevance radius' );
check( strpos( pera_nearby_query( $coordinates ), 'aeroway' ) === false && strpos( pera_nearby_query( $coordinates ), 'around:4300' ) !== false, 'Separate airports do not expand ordinary query' );
check( ! pera_nearby_groups( array( array( 'group' => 'airports', 'point' => $coordinates ) ), $coordinates ), 'Old cached aerodromes ignored' );
$malformed = array( null, 'invalid', array( 'type' => 'node', 'id' => -1 ), array( 'type' => 'node', 'id' => 5, 'tags' => array( 'name' => array( 'bad' ), 'amenity' => 'school' ), 'lat' => 41, 'lon' => 29 ) );
check( pera_nearby_normalize( $malformed ) === array(), 'Malformed provider data rejected without warnings' );

// Resumable warming under DISABLE_WP_CRON: same rate gate, no sleep or cron reliance.
$cache = array(); $events = array(); $options = array(); $requests = array();
$warm_maps = array( 10 => array( 'lat' => 41.0609, 'lng' => 28.99 ), 11 => array( 'lat' => 41.061, 'lng' => 28.992 ),
    12 => null, 13 => array( 'lat' => 41.11, 'lng' => 29.02 ), 14 => array( 'lat' => 41.2, 'lng' => 29.05 ) );
$original_maps = $warm_maps;
$settings = array( 'limit' => 100, 'max_cells' => 1, 'resume' => true );
$response = array( 'code' => 200, 'body' => '{"elements":[]}' );
$first = pera_nearby_warm( $settings );
check( $first['successful'] === 1 && $first['duplicate_properties'] === 1 && $first['invalid_properties'] === 1 && $first['next_after_id'] === 12 && $first['reason'] === 'batch_cell_limit', 'Bounded warm pass deduplicates and checkpoints handled IDs' );
$blocked = pera_nearby_warm( $settings );
check( $blocked['deferred'] === 1 && $blocked['next_after_id'] === 12 && count( $requests ) === 1 && ! $events, 'Shared gate retains cursor without queuing or fetching' );
$options['pera_nearby_provider_gate'] = time() - 1; $response = 'error';
$failed = pera_nearby_warm( $settings );
check( $failed['failed'] === 1 && $failed['next_after_id'] === 13, 'Failed cell reported, backoff applied, checkpoint progresses' );
$options['pera_nearby_provider_gate'] = time() - 1; $response = array( 'code' => 200, 'body' => '{"elements":[]}' );
$last = pera_nearby_warm( $settings ); check( $last['successful'] === 1 && $last['complete'] && $last['next_after_id'] === 14, 'Resume completes final cell' );
$n = count( $requests ); $again = pera_nearby_warm( $settings + array( 'restart' => true ) );
check( $again['skipped'] === 3 && $again['duplicate_properties'] === 1 && count( $requests ) === $n, 'Restart skips fresh and backoff cells across prior batches' );
$cache = array(); $snapshot = array( $cache, $options, $events, $requests );
$preview = pera_nearby_warm( array( 'limit' => 2, 'max_cells' => 10, 'after_id' => 0, 'dry_run' => true ) );
check( $preview['planned'] === 1 && $preview['duplicate_properties'] === 1 && ! $preview['complete'] && array( $cache, $options, $events, $requests ) === $snapshot, 'Dry-run bounded scan has no requests, locks, cache/cursor writes' );
$options['pera_nearby_warm_lock'] = time() + 300;
check( pera_nearby_warm( $settings )['reason'] === 'warm_job_busy', 'Concurrent warm jobs excluded' );
unset( $options['pera_nearby_warm_lock'] );
$command = WP_CLI::$commands['pera nearby warm'];
$command( array(), array( 'dry-run' => true, 'after-id' => 12, 'limit' => 2 ) );
check( json_decode( WP_CLI::$output, true )['next_after_id'] === 13, 'CLI explicit checkpoint and bounded cell budget' );
foreach ( array( array( 'limit' => 501 ), array( 'max-cells' => 11 ), array( 'after-id' => -1 ), array( 'resume' => true, 'after-id' => 1 ), array( 'restart' => true ) ) as $arguments ) {
    try { $command( array(), $arguments ); throw new RuntimeException( 'CLI validation did not reject invalid options' ); } catch ( InvalidArgumentException $expected ) {}
}
check( $warm_maps === $original_maps, 'Warming never changes property records' );

// Realistic, synthetic Istanbul fixtures for layout testing, never production data.
if ( getenv( 'NEARBY_FIXTURE_DIR' ) ) {
    $sample_coordinates = array( 41.06, 28.99 ); $sample_places = array();
    $sample_names = array( 'metro' => array( 'Şişli–Mecidiyeköy', 'Osmanbey', 'Taksim' ), 'metrobus' => array( 'Mecidiyeköy', 'Çağlayan' ),
        'bus' => array( 'Şişli Camii', 'Mecidiyeköy Viyadük', 'Şişli–Mecidiyeköy interchange sample stop with a deliberately long name' ),
        'schools' => array( 'Sample Şişli Primary School', 'Sample International School' ), 'universities' => array( 'Sample University Campus' ),
        'hospitals' => array( 'Sample Şişli Hospital' ), 'clinics' => array( 'Sample Family Health Centre' ), 'pharmacies' => array( 'Sample Pharmacy' ),
        'parks' => array( 'Maçka Demokrasi Parkı', 'Sample Neighbourhood Park' ), 'sport' => array( 'Sample Sports Centre' ),
        'malls' => array( 'İstanbul Cevahir' ), 'supermarkets' => array( 'Sample Supermarket' ) );
    $id = 100;
    foreach ( $sample_names as $group => $names ) foreach ( $names as $index => $name ) $sample_places[] = array( 'id' => 'node/' . $id++, 'point' => array( 41.061 + $index * .005, 28.99 + $index * .005 ), 'names' => array( 'name' => $name ), 'group' => $group );
    $sample_key = pera_nearby_cell( $sample_coordinates )['key'];
    set_transient( $sample_key, array( 'updated' => time(), 'places' => $sample_places ), DAY_IN_SECONDS );
    foreach ( array( 'en', 'tr', 'ar' ) as $language ) {
        ob_start(); pera_render_nearby_amenities( array( 'lat' => 41.06, 'lng' => 28.99 ) ); $html = ob_get_clean();
        file_put_contents( getenv( 'NEARBY_FIXTURE_DIR' ) . '/' . $language . '.html', $html );
    }
}
check( $scripts['pera-component-tabs'][2] === array() && $scripts['pera-component-tabs'][4] === true, 'Dependency-free component JS is enqueued for footer rendering' );

// Exercise registration and fallback through the real Pera ML classes, in memory.
function current_time( ...$args ) { return '2026-10-13 12:00:00'; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
$plugin = dirname( __DIR__, 3 ) . '/plugins/pera-multilingual';
require $plugin . '/includes/class-ui.php';
require $plugin . '/includes/class-ui-registry.php';
require $plugin . '/includes/class-theme-ui-discovery.php';
$registry = new Pera_ML_UI_Registry();
$discovery = new Pera_ML_Theme_UI_Discovery( $registry );
$discovered = $discovery->run( array( dirname( __DIR__ ) . '/inc/modules/nearby-amenities.php' ) );
check( $discovered['discovered'] >= 30 && $discovered['dynamic_skipped'] === 0, 'All nearby UI copy is literal and discoverable' );
check( in_array( dirname( __DIR__ ) . '/inc', Pera_ML_Theme_UI_Discovery::approved_directories( dirname( __DIR__ ) ), true ), 'Component is already within approved registration scope' );
$storage = new class {
    public $row;
    function get( ...$args ) { return $this->row; }
};
$translator = new class { function translate_and_store() { throw new RuntimeException( 'Translation provider must not run during UI rendering' ); } };
$router = new class { function current_language() { return 'ar'; } };
$native_ui = new Pera_ML_UI( $router, $storage, $translator, $registry );
$language = 'ar';
check( pera_ml_ui( 'Nearby amenities', 'theme.nearby.heading' ) === 'Nearby amenities', 'Real Pera UI falls back to English without manual translation setup' );
$storage->row = array( 'translated_text' => 'المرافق القريبة', 'status' => 'current', 'is_stale' => false );
check( pera_ml_ui( 'Nearby amenities', 'theme.nearby.heading' ) === 'المرافق القريبة', 'Real Pera UI reads current translation' );
$storage->row['is_stale'] = true;
check( pera_ml_ui( 'Nearby amenities', 'theme.nearby.heading' ) === 'Nearby amenities', 'Revised distance copy and stale UI translations fall back safely' );
echo "Nearby amenities contract tests passed (provider, airports, renderer, audit, resumable warming, real UI registration/fallback).\n";
