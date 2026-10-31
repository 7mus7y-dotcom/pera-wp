<?php
/** Actual maintained reader + MaxMind's synthetic Country test DB (never live visitor data). */
define( 'ABSPATH', dirname( __DIR__, 4 ) );
$mode = $argv[1] ?? 'normal';
$source = dirname( __DIR__ ) . '/includes/class-local-geoip.php';
if ( 'autoload-missing' === $mode ) {
	$copy = tempnam( sys_get_temp_dir(), 'pera-no-reader-' );
	copy( $source, $copy ); require $copy; unlink( $copy );
} else {
	require $source;
}
$fixture = getenv( 'PERA_ML_TEST_MMDB' );
if ( ! $fixture || ! is_file( $fixture ) ) { fwrite( STDERR, "Synthetic MMDB fixture is required; see CI setup\n" ); exit( 1 ); }
$temporary = null;
if ( 'corrupt' === $mode ) { $temporary = tempnam( sys_get_temp_dir(), 'pera-corrupt-' ); file_put_contents( $temporary, 'not a database' ); }
define( 'PERA_ML_GEO_MMDB_PATH', $temporary ?: ( 'missing' === $mode ? $fixture . '.missing' : $fixture ) );
define( 'PERA_ML_GEO_MMDB_MAX_AGE', 'stale' === $mode ? 1 : 100 * 365 * 86400 );
if ( 'webroot' === $mode ) $_SERVER['DOCUMENT_ROOT'] = dirname( $fixture );
function check_geo( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label: " . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
$_SERVER['REMOTE_ADDR'] = '81.2.69.160';
$geo = new Pera_ML_Local_GeoIP();
if ( 'normal' === $mode ) {
	check_geo( 'GB', $geo->country(), 'real IPv4 Country lookup' );
	$_SERVER['REMOTE_ADDR'] = '2001:218::'; check_geo( 'JP', $geo->country(), 'real IPv6 Country lookup' );
	$_SERVER['REMOTE_ADDR'] = '::ffff:81.2.69.160'; check_geo( 'GB', $geo->country(), 'mapped IPv4 canonicalization' );
	foreach ( array( '', 'garbage', '10.0.0.1', '127.0.0.1', '::1', 'fc00::1', '192.0.2.1' ) as $ip ) {
		$_SERVER['REMOTE_ADDR'] = $ip; check_geo( '', $geo->country(), 'invalid/private/reserved IP fails closed' );
	}
	$_SERVER['REMOTE_ADDR'] = '81.2.69.160'; $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:218::'; $_SERVER['HTTP_CF_CONNECTING_IP'] = '2001:218::';
	check_geo( 'GB', $geo->country(), 'forwarded headers cannot change client' );
	$_SERVER['REMOTE_ADDR'] = '1.1.1.1'; check_geo( '', $geo->country(), 'no matching synthetic record' );
} else {
	check_geo( '', $geo->country(), "$mode database fails closed" );
}
if ( $temporary ) unlink( $temporary );
echo "Local GeoIP $mode tests passed\n";
