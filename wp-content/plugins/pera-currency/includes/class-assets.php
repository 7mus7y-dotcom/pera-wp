<?php

final class Pera_Currency_Assets {
	public static function bootstrap() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		wp_register_script( 'pera-currency', plugins_url( 'assets/js/currency.js', PERA_CURRENCY_FILE ), array(), PERA_CURRENCY_VERSION, true );
		wp_register_style( 'pera-currency', plugins_url( 'assets/css/currency.css', PERA_CURRENCY_FILE ), array(), PERA_CURRENCY_VERSION );
	}

	public static function config() {
		$snapshot = Pera_Currency_Rates::snapshot();
		$usable   = in_array( Pera_Currency_Rates::state( $snapshot ), array( 'fresh', 'stale' ), true );
		$supported = Pera_Currency_Rates::supported();
		return array(
			'base' => 'USD', 'selected' => 'USD', 'supported' => $supported,
			'rates' => $usable ? $snapshot['rates'] : array( 'USD' => 1.0 ),
			'rounding' => array( 'USD' => 1, 'EUR' => 1000, 'GBP' => 1000 ),
			'asOf' => $usable ? $snapshot['provider_date'] : null,
			'snapshotId' => $usable ? $snapshot['snapshot_id'] : null,
			'state' => Pera_Currency_Rates::state( $snapshot ),
			'storageKey' => Pera_Currency_Preference::STORAGE_KEY,
		);
	}

	public static function enqueue() {
		static $enqueued = false;
		if ( $enqueued ) return;
		$enqueued = true;
		self::register();
		wp_enqueue_script( 'pera-currency' );
		wp_enqueue_style( 'pera-currency' );
		wp_add_inline_script( 'pera-currency', 'window.PeraCurrencyConfig=' . wp_json_encode( self::config() ) . ';', 'before' );
	}
}
