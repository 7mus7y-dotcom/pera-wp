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
		$config = array(
			'base' => 'USD', 'selected' => 'USD', 'supported' => $supported,
			'rates' => $usable ? $snapshot['rates'] : array( 'USD' => 1.0 ),
			'rounding' => array( 'USD' => 1, 'EUR' => 1000, 'GBP' => 1000 ),
			'asOf' => $usable ? $snapshot['provider_date'] : null,
			'snapshotId' => $usable ? $snapshot['snapshot_id'] : null,
			'state' => Pera_Currency_Rates::state( $snapshot ),
			'storageKey' => Pera_Currency_Preference::STORAGE_KEY,
		);
		$config['toast'] = array(
			'USD' => array(
				'title' => self::ui_text( 'Currency changed to USD', 'plugin.pera_currency.toast.title_usd' ),
				'body'  => self::ui_text( 'Prices are now displayed in US dollars.', 'plugin.pera_currency.toast.body_usd' ),
			),
			'EUR' => array(
				'title' => self::ui_text( 'Currency changed to EUR', 'plugin.pera_currency.toast.title_eur' ),
				'body'  => self::ui_text( 'Prices are now displayed in euros.', 'plugin.pera_currency.toast.body_eur' ),
			),
			'GBP' => array(
				'title' => self::ui_text( 'Currency changed to GBP', 'plugin.pera_currency.toast.title_gbp' ),
				'body'  => self::ui_text( 'Prices are now displayed in British pounds.', 'plugin.pera_currency.toast.body_gbp' ),
			),
		);
		return $config;
	}

	private static function ui_text( $source, $key ) {
		return function_exists( 'pera_ml_ui' ) ? pera_ml_ui( $source, $key ) : $source;
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
