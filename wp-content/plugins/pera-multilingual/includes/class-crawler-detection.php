<?php
defined( 'ABSPATH' ) || exit;

/** Browser navigation policy with conservative crawler bypass and background FCrDNS. */
final class Pera_ML_Crawler_Detection {
	const EVENT = 'pera_ml_verify_crawler_queue';
	const QUEUE = 'pera_ml_crawler_queue';
	const CACHE = 'pera_ml_crawler_cache';
	const LOCK = 'pera_ml_crawler_worker_lock';
	const QUEUE_LIMIT = 64;
	const CACHE_LIMIT = 256;

	public function hooks() { add_action( self::EVENT, array( $this, 'work' ) ); }
	private function enabled() { return defined( 'PERA_ML_GEO_CRAWLER_MODE' ) && 'dns' === PERA_ML_GEO_CRAWLER_MODE; }

	public function audience() {
		if ( ! $this->enabled() ) return '';
		// This is only an additional bypass, never verified crawler identity.
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$crawler_hint = (bool) preg_match( '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|mediapartners|inspectiontool/i', $agent );
		$ip = Pera_ML_Local_GeoIP::client_ip();
		// Without an explicitly restored client-IP parameter, a proxy peer is not the visitor.
		if ( defined( 'PERA_ML_GEO_COUNTRY_SOURCE' ) && 'cloudflare' === PERA_ML_GEO_COUNTRY_SOURCE && ! defined( 'PERA_ML_GEO_CLIENT_IP_SERVER_VAR' ) ) return '';
		if ( '' === $ip ) return '';
		$key = hash( 'sha256', $ip ); $now = time();
		$cache = get_option( self::CACHE, array() );
		$entry = is_array( $cache ) && isset( $cache[ $key ] ) ? $cache[ $key ] : null;
		$fresh = is_array( $entry ) && isset( $entry['expires'], $entry['result'] ) && $entry['expires'] > $now;
		if ( $fresh ) {
			if ( 'bot' === $entry['result'] ) return 'bot';
		}
		if ( ! $fresh ) $this->queue( $ip, $key, $now );
		// Neither missing PTR nor a pending/failed DNS lookup blocks a browser's
		// first navigation. These request hints are policy inputs, NOT identity proof.
		return ! $crawler_hint && $this->browser_navigation( $agent ) ? 'visitor' : '';
	}

	private function browser_navigation( $agent ) {
		if ( 0 !== strpos( $agent, 'Mozilla/' ) ) return false;
		// Explicit non-document fetch metadata must never fall back to Accept.
		if ( isset( $_SERVER['HTTP_SEC_FETCH_MODE'] ) || isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) {
			return isset( $_SERVER['HTTP_SEC_FETCH_MODE'], $_SERVER['HTTP_SEC_FETCH_DEST'] )
				&& 'navigate' === $_SERVER['HTTP_SEC_FETCH_MODE'] && 'document' === $_SERVER['HTTP_SEC_FETCH_DEST'];
		}
		// Older browsers may omit Fetch Metadata. A normal HTML navigation is enough.
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) && is_string( $_SERVER['HTTP_ACCEPT'] ) ? $_SERVER['HTTP_ACCEPT'] : '';
		return (bool) preg_match( '#(?:^|,)\s*text/html(?:\s*[;,]|\s*$)#i', $accept );
	}

	private function queue( $ip, $key, $now ) {
		$queue = get_option( self::QUEUE, array() ); $queue = is_array( $queue ) ? $queue : array();
		$queue = array_filter( $queue, static function ( $item ) use ( $now ) { return is_array( $item ) && isset( $item['queued'] ) && $item['queued'] > $now - 600; } );
		if ( ! isset( $queue[ $key ] ) && count( $queue ) < self::QUEUE_LIMIT ) {
			$queue[ $key ] = array( 'ip' => $ip, 'queued' => $now );
			update_option( self::QUEUE, $queue, false );
		}
		if ( $queue && ! wp_next_scheduled( self::EVENT ) ) wp_schedule_single_event( $now + 5, self::EVENT );
	}

	/** Runs only in cron/CLI, never from audience(). At most five DNS jobs per event. */
	public function work() {
		if ( ! Pera_ML_Geo_Redirect::enabled() || ! $this->enabled() ) return;
		$lock = (int) get_option( self::LOCK, 0 );
		if ( $lock && $lock < time() - 300 ) delete_option( self::LOCK );
		if ( ! add_option( self::LOCK, time(), '', false ) ) return;
		try {
			$queue = get_option( self::QUEUE, array() ); $queue = is_array( $queue ) ? $queue : array();
			$batch = array_slice( $queue, 0, 5, true );
			$cache = get_option( self::CACHE, array() ); $cache = is_array( $cache ) ? $cache : array();
			$cache = array_filter( $cache, static function ( $entry ) { return isset( $entry['expires'] ) && $entry['expires'] > time(); } );
			foreach ( $batch as $key => $item ) {
				$result = '';
				if ( isset( $item['ip'], $item['queued'] ) && $item['queued'] > time() - 600 ) $result = self::verify( $item['ip'] );
				unset( $cache[ $key ] );
				$cache[ $key ] = array( 'result' => $result, 'expires' => time() + ( 'bot' === $result ? 86400 : ( 'visitor' === $result ? 21600 : 300 ) ) );
			}
			$queue = get_option( self::QUEUE, array() ); $queue = is_array( $queue ) ? $queue : array();
			foreach ( $batch as $key => $item ) unset( $queue[ $key ] );
			update_option( self::QUEUE, $queue, false );
			update_option( self::CACHE, array_slice( $cache, -self::CACHE_LIMIT, null, true ), false );
			if ( $queue && ! wp_next_scheduled( self::EVENT ) ) wp_schedule_single_event( time() + 10, self::EVENT );
		} finally {
			delete_option( self::LOCK );
		}
	}

	/** Callbacks are injectable for deterministic tests; production uses the host resolver. */
	public static function verify( $ip, $reverse = null, $forward = null ) {
		if ( ! is_string( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) return '';
		$reverse = $reverse ?: static function ( $address ) { return @gethostbyaddr( $address ); };
		$forward = $forward ?: static function ( $name ) { return @dns_get_record( $name, DNS_A | DNS_AAAA ); };
		try {
			$name = $reverse( $ip );
			if ( ! is_string( $name ) ) return '';
			$name = strtolower( rtrim( $name, '.' ) );
			if ( strlen( $name ) > 253 || ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/D', $name ) ) return '';
			$records = $forward( $name );
			if ( ! is_array( $records ) ) return '';
			$confirmed = false;
			foreach ( $records as $record ) {
				$address = isset( $record['ip'] ) ? $record['ip'] : ( isset( $record['ipv6'] ) ? $record['ipv6'] : '' );
				if ( is_string( $address ) && filter_var( $address, FILTER_VALIDATE_IP ) && inet_pton( $address ) === inet_pton( $ip ) ) { $confirmed = true; break; }
			}
			if ( ! $confirmed ) return '';
			foreach ( array( 'googlebot.com', 'google.com', 'googleusercontent.com', 'search.msn.com', 'crawl.yahoo.net', 'yandex.ru', 'yandex.net', 'yandex.com', 'baidu.com', 'baidu.jp', 'duckduckgo.com', 'applebot.apple.com', 'petalsearch.com', 'sogou.com', 'sogoucdn.com', 'naver.com' ) as $suffix ) {
				if ( $name === $suffix || substr( $name, -strlen( $suffix ) - 1 ) === '.' . $suffix ) return 'bot';
			}
			return 'visitor';
		} catch ( Throwable $error ) {
			return '';
		}
	}
}
