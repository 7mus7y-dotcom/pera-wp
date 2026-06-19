<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_get_default_whatsapp_number' ) ) {
	/**
	 * Return the hard-coded WhatsApp fallback currently used by the theme.
	 */
	function pera_get_default_whatsapp_number(): string {
		return '905452054356';
	}
}

if ( ! function_exists( 'pera_get_whatsapp_number' ) ) {
	/**
	 * Return the configured site-wide WhatsApp number, falling back to the theme default.
	 */
	function pera_get_whatsapp_number(): string {
		$number = get_option( 'pera_whatsapp_number', '' );
		$number = preg_replace( '/\D+/', '', (string) $number );

		return '' !== $number ? $number : pera_get_default_whatsapp_number();
	}
}

if ( ! function_exists( 'pera_get_whatsapp_url' ) ) {
	/**
	 * Build a WhatsApp URL for the configured site-wide number.
	 */
	function pera_get_whatsapp_url( $message = '' ): string {
		$url     = 'https://wa.me/' . pera_get_whatsapp_number();
		$message = (string) $message;

		if ( '' !== $message ) {
			$url .= '?text=' . rawurlencode( $message );
		}

		return esc_url_raw( $url );
	}
}
