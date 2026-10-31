<?php
/** Offline deployment/update check; never loads WordPress or exposes an HTTP endpoint. */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }
$path = $argv[1] ?? '';
$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! is_readable( $autoload ) ) { fwrite( STDERR, "Install the locked Composer reader first.\n" ); exit( 1 ); }
require $autoload;
$reader = null;
try {
	$reader = new MaxMind\Db\Reader( $path ); $metadata = $reader->metadata();
	if ( ! in_array( $metadata->databaseType, array( 'GeoLite2-Country', 'GeoIP2-Country' ), true ) || $metadata->buildEpoch > time() + 86400 || time() - $metadata->buildEpoch > 30 * 86400 ) throw new RuntimeException( 'Expected a Country database built within 30 days.' );
	$reader->get( '8.8.8.8' ); $reader->get( '2001:4860:4860::8888' );
	echo "Country database metadata and IPv4/IPv6 reads passed.\n";
} catch ( Throwable $error ) {
	fwrite( STDERR, "Country database validation failed; retain the previous active database.\n" ); exit( 1 );
} finally {
	if ( $reader ) $reader->close();
}
