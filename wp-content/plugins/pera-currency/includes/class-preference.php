<?php

final class Pera_Currency_Preference {
	const STORAGE_KEY = 'pera_currency';

	public static function selected() {
		if ( empty( $_COOKIE[ self::STORAGE_KEY ] ) ) {
			return 'USD';
		}
		$value = strtoupper( sanitize_text_field( wp_unslash( $_COOKIE[ self::STORAGE_KEY ] ) ) );
		return isset( Pera_Currency_Rates::supported()[ $value ] ) ? $value : 'USD';
	}
}
