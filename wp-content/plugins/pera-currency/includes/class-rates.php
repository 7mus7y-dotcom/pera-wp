<?php

final class Pera_Currency_Rates {
	const OPTION = 'pera_currency_rates';
	const CACHE = 'pera_currency_rates_cache';
	const LOCK = 'pera_currency_refresh_lock';
	const CRON = 'pera_currency_refresh_rates';
	const SCHEDULE = 'pera_currency_twelve_hours';
	const FRESH_FOR = 43200;
	const USABLE_FOR = 604800;
	private static $runtime = null;

	public static function supported() {
		return array(
			'USD' => array( 'symbol' => '$', 'rounding' => 1 ),
			'EUR' => array( 'symbol' => '€', 'rounding' => 1000 ),
			'GBP' => array( 'symbol' => '£', 'rounding' => 1000 ),
		);
	}

	public static function bootstrap() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON, array( __CLASS__, 'refresh' ) );
	}

	public static function cron_schedules( $schedules ) {
		$schedules[ self::SCHEDULE ] = array( 'interval' => self::FRESH_FOR, 'display' => 'Every 12 hours' );
		return $schedules;
	}

	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, self::SCHEDULE, self::CRON );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
		delete_transient( self::LOCK );
		delete_transient( self::CACHE );
		self::$runtime = null;
	}

	public static function make_snapshot( $rates, $provider, $provider_date, $fetched_at = null ) {
		$keys = array_keys( self::supported() );
		if ( array_keys( $rates ) !== $keys ) {
			return new WP_Error( 'pera_currency_incomplete', 'Rate snapshot is incomplete.' );
		}
		$normalized = array();
		foreach ( $keys as $code ) {
			$value = $rates[ $code ];
			if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value <= 0 || (float) $value < 0.01 || (float) $value > 100 ) {
				return new WP_Error( 'pera_currency_rate', 'Rate snapshot contains an invalid rate.' );
			}
			$normalized[ $code ] = (float) $value;
		}
		if ( 1.0 !== $normalized['USD'] ) {
			return new WP_Error( 'pera_currency_base', 'USD rate must equal one.' );
		}
		$identity = array( 'base' => 'USD', 'rates' => $normalized, 'provider' => (string) $provider, 'provider_date' => (string) $provider_date );
		return $identity + array( 'fetched_at' => null === $fetched_at ? time() : (int) $fetched_at, 'snapshot_id' => hash( 'sha256', wp_json_encode( $identity ) ) );
	}

	public static function refresh( $provider = null ) {
		if ( get_transient( self::LOCK ) ) {
			return new WP_Error( 'pera_currency_locked', 'A rate refresh is already running.' );
		}
		set_transient( self::LOCK, 1, 60 );
		$provider = $provider ?: new Pera_Currency_ECB_Provider();
		$snapshot = $provider->fetch_rates();
		if ( ! is_wp_error( $snapshot ) && self::validate_snapshot( $snapshot ) ) {
			update_option( self::OPTION, $snapshot, false );
			set_transient( self::CACHE, $snapshot, self::FRESH_FOR );
			self::$runtime = $snapshot;
		} elseif ( ! is_wp_error( $snapshot ) ) {
			$snapshot = new WP_Error( 'pera_currency_snapshot', 'Provider returned an invalid snapshot.' );
		}
		delete_transient( self::LOCK );
		return $snapshot;
	}

	public static function snapshot() {
		if ( null !== self::$runtime ) return self::$runtime;
		$value = get_transient( self::CACHE );
		if ( ! self::validate_snapshot( $value ) ) $value = get_option( self::OPTION, null );
		self::$runtime = self::validate_snapshot( $value ) ? $value : null;
		return self::$runtime;
	}

	public static function validate_snapshot( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['snapshot_id'] ) || empty( $snapshot['fetched_at'] ) || empty( $snapshot['rates'] ) ) return false;
		$rebuilt = self::make_snapshot( $snapshot['rates'], $snapshot['provider'], $snapshot['provider_date'], $snapshot['fetched_at'] );
		return ! is_wp_error( $rebuilt ) && hash_equals( $rebuilt['snapshot_id'], $snapshot['snapshot_id'] );
	}

	public static function state( $snapshot = null, $now = null ) {
		$snapshot = null === $snapshot ? self::snapshot() : $snapshot;
		if ( ! self::validate_snapshot( $snapshot ) ) return 'unavailable';
		$age = ( null === $now ? time() : $now ) - $snapshot['fetched_at'];
		if ( $age <= self::FRESH_FOR ) return 'fresh';
		return $age <= self::USABLE_FOR ? 'stale' : 'expired';
	}

	public static function debug_status() {
		$s = self::snapshot();
		return array( 'state' => self::state( $s ), 'provider' => $s['provider'] ?? null, 'provider_date' => $s['provider_date'] ?? null, 'fetched_at' => $s['fetched_at'] ?? null, 'snapshot_id' => $s['snapshot_id'] ?? null, 'rates' => $s['rates'] ?? array( 'USD' => 1.0 ) );
	}
}
