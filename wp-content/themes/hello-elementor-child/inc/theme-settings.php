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

		add_settings_section(
			'pera_theme_settings_enquiries',
			__( 'Enquiries', 'hello-elementor-child' ),
			'__return_false',
			'pera-theme-settings'
		);

		add_settings_field(
			'pera_enable_client_autoreply',
			__( 'Enable client auto-reply emails', 'hello-elementor-child' ),
			'pera_theme_settings_render_client_autoreply_field',
			'pera-theme-settings',
			'pera_theme_settings_enquiries'
		);
	}
	add_action( 'admin_init', 'pera_theme_settings_register' );

	/**
	 * Add Appearance -> Pera Theme Settings page.
	 */
	function pera_theme_settings_add_page(): void {
		add_theme_page(
			__( 'Pera Theme Settings', 'hello-elementor-child' ),
			__( 'Theme Settings', 'hello-elementor-child' ),
			'manage_options',
			'pera-theme-settings',
			'pera_theme_settings_render_page'
		);
	}
	add_action( 'admin_menu', 'pera_theme_settings_add_page' );

	/**
	 * Render checkbox field for client auto-replies.
	 */
	function pera_theme_settings_render_client_autoreply_field(): void {
		$value = get_option( 'pera_enable_client_autoreply', '1' );
		?>
		<label for="pera_enable_client_autoreply">
			<input type="hidden" name="pera_enable_client_autoreply" value="0">
			<input type="checkbox" id="pera_enable_client_autoreply" name="pera_enable_client_autoreply" value="1" <?php checked( (string) $value, '1' ); ?>>
			<?php esc_html_e( 'When enabled, enquiry forms send an automatic confirmation email to the client. Admin notification emails are not affected.', 'hello-elementor-child' ); ?>
		</label>
		<?php
	}

	/**
	 * Render settings page.
	 */
	function pera_theme_settings_render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pera Theme Settings', 'hello-elementor-child' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'pera_theme_settings' );
				do_settings_sections( 'pera-theme-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
