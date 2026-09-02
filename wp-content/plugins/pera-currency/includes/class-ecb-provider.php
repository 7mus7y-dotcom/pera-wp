<?php

final class Pera_Currency_ECB_Provider implements Pera_Currency_Provider_Interface {
	const ENDPOINT = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

	public function fetch_rates() {
		$response = wp_safe_remote_get(
			self::ENDPOINT,
			array( 'timeout' => 5, 'redirection' => 0, 'limit_response_size' => 200000 )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'pera_currency_http', 'ECB returned a non-200 response.' );
		}
		return self::parse( wp_remote_retrieve_body( $response ) );
	}

	public static function parse( $body, $fetched_at = null ) {
		if ( ! is_string( $body ) || '' === trim( $body ) || ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'pera_currency_parse', 'ECB response cannot be parsed.' );
		}
		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( false === $xml ) {
			return new WP_Error( 'pera_currency_parse', 'ECB response cannot be parsed.' );
		}
		$dated = $xml->xpath( '//*[local-name()="Cube"][@time]' );
		if ( empty( $dated[0] ) ) {
			return new WP_Error( 'pera_currency_date', 'ECB provider date is missing.' );
		}
		$date = (string) $dated[0]['time'];
		$dt   = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $date || $dt->getTimestamp() > time() + DAY_IN_SECONDS ) {
			return new WP_Error( 'pera_currency_date', 'ECB provider date is invalid.' );
		}
		$eur_rates = array();
		foreach ( $dated[0]->xpath( './*[local-name()="Cube"][@currency]' ) as $cube ) {
			$code = strtoupper( (string) $cube['currency'] );
			if ( in_array( $code, array( 'USD', 'GBP' ), true ) ) {
				$eur_rates[ $code ] = (string) $cube['rate'];
			}
		}
		foreach ( array( 'USD', 'GBP' ) as $code ) {
			if ( ! isset( $eur_rates[ $code ] ) || ! is_numeric( $eur_rates[ $code ] ) ) {
				return new WP_Error( 'pera_currency_incomplete', 'ECB response is incomplete.' );
			}
			$value = (float) $eur_rates[ $code ];
			if ( ! is_finite( $value ) || $value <= 0 || $value < 0.1 || $value > 10 ) {
				return new WP_Error( 'pera_currency_rate', 'ECB response contains an implausible rate.' );
			}
		}
		$rates = array( 'USD' => 1.0, 'EUR' => 1 / (float) $eur_rates['USD'], 'GBP' => (float) $eur_rates['GBP'] / (float) $eur_rates['USD'] );
		return Pera_Currency_Rates::make_snapshot( $rates, 'ecb', $date, $fetched_at );
	}
}
