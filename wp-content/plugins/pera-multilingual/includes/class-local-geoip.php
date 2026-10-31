<?php
defined( 'ABSPATH' ) || exit;

/** Local MMDB only; no network or external IP service is used here. */
final class Pera_ML_Local_GeoIP {
	public static function client_ip() {
		$key = defined( 'PERA_ML_GEO_CLIENT_IP_SERVER_VAR' ) ? PERA_ML_GEO_CLIENT_IP_SERVER_VAR : 'REMOTE_ADDR';
		if ( ! is_string( $key ) || ! preg_match( '/^[A-Z][A-Z0-9_]*$/D', $key ) || 0 === strpos( $key, 'HTTP_' ) ) return '';
		$ip = isset( $_SERVER[ $key ] ) ? $_SERVER[ $key ] : '';
		if ( ! is_string( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return '';
		// Normalize mapped IPv4 before the public-address check, MMDB lookup and DNS matching.
		$packed = inet_pton( $ip );
		if ( 16 === strlen( $packed ) && substr( $packed, 0, 12 ) === str_repeat( "\0", 10 ) . "\xff\xff" ) $ip = inet_ntop( substr( $packed, 12 ) );
		return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ? inet_ntop( inet_pton( $ip ) ) : '';
	}

	public static function private_database_path() {
		$config = defined( 'PERA_ML_GEO_MMDB_PATH' ) ? PERA_ML_GEO_MMDB_PATH : '';
		if ( ! is_string( $config ) || '/' !== substr( $config, 0, 1 ) ) return '';
		$path = realpath( $config );
		if ( false === $path || ! is_file( $path ) || ! is_readable( $path ) ) return '';
		foreach ( array( ABSPATH, isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : '' ) as $root ) {
			$root = is_string( $root ) && '' !== $root ? realpath( $root ) : false;
			if ( $root && ( $path === $root || 0 === strpos( $path, rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) ) return '';
		}
		return $path;
	}

	public function country() {
		$ip = self::client_ip(); $path = self::private_database_path();
		if ( '' === $ip || '' === $path ) return '';
		$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
		if ( ! class_exists( 'MaxMind\\Db\\Reader' ) && is_readable( $autoload ) ) require_once $autoload;
		if ( ! class_exists( 'MaxMind\\Db\\Reader' ) ) return '';
		$reader = null;
		try {
			$reader = new MaxMind\Db\Reader( $path );
			$metadata = $reader->metadata();
			$max_age = defined( 'PERA_ML_GEO_MMDB_MAX_AGE' ) ? (int) PERA_ML_GEO_MMDB_MAX_AGE : 30 * 86400;
			if ( ! in_array( $metadata->databaseType, array( 'GeoLite2-Country', 'GeoIP2-Country' ), true ) || $max_age <= 0 || $metadata->buildEpoch > time() + 86400 || time() - $metadata->buildEpoch > $max_age ) return '';
			$record = $reader->get( $ip );
			// Never substitute registered_country: it may differ from the visitor's location.
			return isset( $record['country']['iso_code'] ) && is_string( $record['country']['iso_code'] ) ? $record['country']['iso_code'] : '';
		} catch ( Throwable $error ) {
			return ''; // Missing/corrupt/unsupported DB and address-not-found are safe no-results.
		} finally {
			if ( $reader ) { try { $reader->close(); } catch ( Throwable $error ) { /* No frontend failure on a damaged handle. */ } }
		}
	}
}
