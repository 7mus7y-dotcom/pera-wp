<?php
/** Standalone contract tests; never contacts OSM or a WordPress database. */
define( 'ABSPATH', __DIR__ );
define( 'WP_CLI', true );
class WP_CLI {
    public static $commands = array(); public static $output;
    static function add_command( $name, $callback ) { self::$commands[$name] = $callback; }
    static function log( $value ) { self::$output = $value; }
}
function get_posts( $args ) { return $args['paged'] === 1 ? array( 1, 2, 3 ) : array(); }
function get_field( $name, $id ) { return $id === 1 ? array( 'lat' => 41, 'lng' => 29 ) : ( $id === 2 ? null : array( 'lat' => 'invalid', 'lng' => 29 ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 );
$cache = array(); $events = array(); $options = array(); $requests = array(); $response = array( 'code' => 200, 'body' => '{"elements":[]}' );
function add_action( ...$args ) {}
function get_transient( $key ) { global $cache; return isset( $cache[$key] ) && $cache[$key][1] > time() ? $cache[$key][0] : false; }
function set_transient( $key, $value, $ttl ) { global $cache; $cache[$key] = array( $value, time() + $ttl ); }
function delete_transient( $key ) { global $cache; unset( $cache[$key] ); }
function wp_next_scheduled( $hook, $args ) { global $events; return $events[serialize( $args )] ?? false; }
function wp_schedule_single_event( $time, $hook, $args ) { global $events; $events[serialize( $args )] = $time; }
function get_option( $key, $default = false ) { global $options; return $options[$key] ?? $default; }
function add_option( $key, $value, ...$rest ) { global $options; if ( isset( $options[$key] ) ) return false; $options[$key] = $value; return true; }
function wp_cache_delete( ...$args ) {}
function wp_rand( $a, $b ) { return $a; }
function apply_filters( $key, $value ) { return $value; }
function home_url( $path ) { return 'https://example.test' . $path; }
function wp_remote_post( $url, $args ) { global $response, $requests; $requests[] = $args; return $response; }
function is_wp_error( $value ) { return $value === 'error'; }
function wp_remote_retrieve_response_code( $value ) { return $value['code']; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
function pera_ml_ui( $source, $key ) { global $language; return $language === 'ar' ? 'ترجمة ' . $source : ( $language === 'tr' ? 'Çeviri ' . $source : $source ); }
function pera_ml_current_language() { global $language; return $language ?: 'en'; }
function wp_unique_id( $prefix ) { static $id = 0; return $prefix . ++$id; }
function wp_enqueue_script( ...$args ) {}
function get_stylesheet_directory_uri() { return '..'; }
function pera_get_asset_version( $path ) { return 1; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function number_format_i18n( $value, $decimals = 0 ) { return number_format( $value, $decimals ); }
class Nearby_Test_DB {
    public $options = 'wp_options';
    private $expected;
    function prepare( $sql, $key, $value ) { $this->expected = $value; return $sql; }
    function query( $sql ) { global $options; if ( (string) ( $options['pera_nearby_provider_gate'] ?? '' ) !== $this->expected ) return 0; unset( $options['pera_nearby_provider_gate'] ); return 1; }
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
set_transient( $key, array( 'updated' => time(), 'places' => array( $places[1] ) ), DAY_IN_SECONDS );
ob_start(); pera_render_nearby_amenities( $map ); $html = ob_get_clean(); check( strpos( $html, '-education-tab" aria-controls=' ) !== false && preg_match( '/education-tab[^>]+aria-selected="true"/', $html ), 'First nonempty category selected' );
set_transient( $key, array( 'updated' => time(), 'places' => array() ), DAY_IN_SECONDS );
ob_start(); pera_render_nearby_amenities( $map ); pera_render_nearby_amenities( null ); check( ob_get_clean() === '', 'Empty/missing component hidden' );
$before = array( $cache, $events, $options, $requests );
WP_CLI::$commands['pera nearby audit']();
check( json_decode( WP_CLI::$output, true ) === array( 'published' => 3, 'valid' => 1, 'missing_or_invalid' => 2 ), 'Read-only audit counts' );
check( array( $cache, $events, $options, $requests ) === $before, 'Audit does not fetch, schedule or write caches' );
check( strpos( pera_nearby_query( $coordinates ), '.places out body center;' ) !== false, 'Query includes node coordinates and area centers' );
echo "Nearby amenities contract tests passed.\n";
