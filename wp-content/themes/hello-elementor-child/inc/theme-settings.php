<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_client_autoreply_enabled' ) ) {
	/**
	 * Global client auto-reply toggle for enquiry forms.
	 */
	function pera_client_autoreply_enabled(): bool {
		$value = get_option( 'pera_enable_client_autoreply', '1' );

		return (string) $value === '1';
	}
}

if ( is_admin() ) {
	/**
	 * Register minimal theme settings for enquiry behaviour.
	 */
	function pera_theme_settings_register(): void {
		register_setting(
			'pera_theme_settings',
			'pera_enable_client_autoreply',
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ): string {
					return ! empty( $value ) ? '1' : '0';
				},
				'default'           => '1',
			)
		);

	}
	add_action( 'admin_init', 'pera_theme_settings_register' );
}
