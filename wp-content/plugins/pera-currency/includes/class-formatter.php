<?php

final class Pera_Currency_Formatter {
	public static function valid_amount( $amount ) {
		return ( is_int( $amount ) || is_float( $amount ) || ( is_string( $amount ) && preg_match( '/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $amount ) ) )
			&& is_finite( (float) $amount ) && (float) $amount >= 0;
	}

	public static function round_half_up( $amount, $increment ) {
		return round( $amount / $increment, 0, PHP_ROUND_HALF_UP ) * $increment;
	}

	public static function text( $amount, $currency ) {
		$supported = Pera_Currency_Rates::supported();
		if ( ! isset( $supported[ $currency ] ) || ! self::valid_amount( $amount ) ) {
			return '';
		}
		return $supported[ $currency ]['symbol'] . number_format( (float) $amount, 0, '.', ',' );
	}
}
