<?php
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
class WP_Error { public $code; public function __construct( $code ) { $this->code = $code; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_SLASHES ); }
$GLOBALS['options'] = $GLOBALS['transients'] = array();
function get_option( $k, $d = null ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['options'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function set_transient( $k, $v ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); }
function sanitize_text_field( $v ) { return preg_replace( '/[^A-Za-z]/', '', $v ); }
function wp_unslash( $v ) { return $v; }
function wp_safe_remote_get() { return $GLOBALS['http_response']; }
function wp_remote_retrieve_response_code( $v ) { return $v['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $v ) { return $v['body'] ?? ''; }
require dirname( __DIR__ ) . '/includes/interface-provider.php';
require dirname( __DIR__ ) . '/includes/class-formatter.php';
require dirname( __DIR__ ) . '/includes/class-preference.php';
require dirname( __DIR__ ) . '/includes/class-rates.php';
require dirname( __DIR__ ) . '/includes/class-ecb-provider.php';
require dirname( __DIR__ ) . '/includes/functions.php';
function ok( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
$now = time();
$snapshot = Pera_Currency_Rates::make_snapshot( array( 'USD' => 1, 'EUR' => .86, 'GBP' => .75 ), 'test', '2026-09-02', $now );
update_option( Pera_Currency_Rates::OPTION, $snapshot );
foreach ( json_decode( file_get_contents( __DIR__ . '/fixtures/golden.json' ), true ) as $fixture ) {
	$s = Pera_Currency_Rates::make_snapshot( array( 'USD' => 1, 'EUR' => $fixture['currency'] === 'EUR' ? $fixture['rate'] : .86, 'GBP' => $fixture['currency'] === 'GBP' ? $fixture['rate'] : .75 ), 'test', '2026-09-02', $now );
	$GLOBALS['transients'][ Pera_Currency_Rates::CACHE ] = $s;
	$ref = new ReflectionProperty( Pera_Currency_Rates::class, 'runtime' ); $ref->setAccessible( true ); $ref->setValue( null, null );
	ok( pera_currency_format( $fixture['usd'], $fixture['currency'] ) === $fixture['expected'], 'golden ' . $fixture['currency'] );
}
foreach ( json_decode( file_get_contents( __DIR__ . '/fixtures/ranges.json' ), true ) as $fixture ) {
	$max    = array_key_exists( 'max', $fixture ) ? $fixture['max'] : null;
	$result = pera_currency_format_range( $fixture['min'], $max, 'EUR' );
	ok( $result['valid'] === $fixture['valid'], 'range validity: ' . $fixture['name'] );
	if ( $fixture['valid'] ) {
		ok( $result['min'] === $fixture['minText'] && $result['max'] === $fixture['maxText'], 'range text: ' . $fixture['name'] );
	}
}
ok( array_keys( pera_currency_get_supported() ) === array( 'USD', 'EUR', 'GBP' ), 'exact allowlist' );
ok( null === pera_currency_convert( -1 ) && null === pera_currency_convert( '1,000' ) && null === pera_currency_convert( INF ), 'invalid amounts' );
ok( null === pera_currency_get_rate( 'CAD' ), 'unsupported currency' );
ok( Pera_Currency_Rates::state( $snapshot, $now + 10 ) === 'fresh', 'fresh state' );
ok( Pera_Currency_Rates::state( $snapshot, $now + 86400 ) === 'stale', 'stale state' );
ok( Pera_Currency_Rates::state( $snapshot, $now + 700000 ) === 'expired', 'expired state' );
$id = Pera_Currency_Rates::make_snapshot( $snapshot['rates'], 'test', '2026-09-02', $now );
ok( $id['snapshot_id'] === $snapshot['snapshot_id'], 'deterministic identifier' );
$provider_date = gmdate( 'Y-m-d', $now );
$xml = '<?xml version="1.0"?><Envelope><Cube><Cube time="' . $provider_date . '"><Cube currency="USD" rate="1.2"/><Cube currency="GBP" rate="0.9"/></Cube></Cube></Envelope>';
$ecb = Pera_Currency_ECB_Provider::parse( $xml, $now );
ok( ! is_wp_error( $ecb ) && abs( $ecb['rates']['EUR'] - ( 1 / 1.2 ) ) < 0.000001 && abs( $ecb['rates']['GBP'] - .75 ) < 0.000001, 'ECB normalization' );
$xml_with_date = static function ( $date ) use ( $xml, $provider_date ) {
	return str_replace( 'time="' . $provider_date . '"', 'time="' . $date . '"', $xml );
};
ok( ! is_wp_error( Pera_Currency_ECB_Provider::parse( $xml_with_date( gmdate( 'Y-m-d', $now - DAY_IN_SECONDS ) ), $now ) ), 'previous business day provider date' );
ok( ! is_wp_error( Pera_Currency_ECB_Provider::parse( $xml_with_date( gmdate( 'Y-m-d', $now - ( 3 * DAY_IN_SECONDS ) ) ), $now ) ), 'weekend-gap provider date' );
ok( ! is_wp_error( Pera_Currency_ECB_Provider::parse( $xml_with_date( gmdate( 'Y-m-d', $now - ( 6 * DAY_IN_SECONDS ) ) ), $now ) ), 'holiday-gap provider date within limit' );
ok( ! is_wp_error( Pera_Currency_ECB_Provider::parse( $xml_with_date( gmdate( 'Y-m-d', $now - Pera_Currency_ECB_Provider::MAX_PROVIDER_AGE ) ), $now ) ), 'provider date at age limit' );
ok( is_wp_error( Pera_Currency_ECB_Provider::parse( $xml_with_date( gmdate( 'Y-m-d', $now - ( 8 * DAY_IN_SECONDS ) ) ), $now ) ), 'provider date older than limit' );
ok( is_wp_error( Pera_Currency_ECB_Provider::parse( $xml_with_date( gmdate( 'Y-m-d', $now + ( 2 * DAY_IN_SECONDS ) ) ), $now ) ), 'future provider date' );
ok( is_wp_error( Pera_Currency_ECB_Provider::parse( $xml_with_date( '2026-02-30' ), $now ) ), 'malformed provider date' );
ok( is_wp_error( Pera_Currency_ECB_Provider::parse( str_replace( 'currency="GBP"', 'currency="JPY"', $xml ), $now ) ), 'partial rejection' );
$GLOBALS['http_response'] = array( 'response' => array( 'code' => 503 ), 'body' => '' );
ok( is_wp_error( ( new Pera_Currency_ECB_Provider() )->fetch_rates() ), 'HTTP failure' );
$GLOBALS['http_response'] = new WP_Error( 'timeout' );
ok( is_wp_error( ( new Pera_Currency_ECB_Provider() )->fetch_rates() ), 'transport failure' );
$GLOBALS['transients'][ Pera_Currency_Rates::CACHE ] = false; $GLOBALS['options'][ Pera_Currency_Rates::OPTION ] = $snapshot; $ref->setValue( null, null );
ok( Pera_Currency_Rates::snapshot()['snapshot_id'] === $snapshot['snapshot_id'], 'LKG restore' );
echo "PHP currency tests passed\n";
