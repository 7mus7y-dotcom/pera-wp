<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_sanitize_whatsapp_number' ) ) {
	/**
	 * Sanitize WhatsApp numbers to digits only.
	 */
	function pera_sanitize_whatsapp_number( $value ): string {
		return preg_replace( '/\D+/', '', (string) $value );
	}
}

if ( ! function_exists( 'pera_register_site_settings' ) ) {
	/**
	 * Register site-wide Pera settings.
	 */
	function pera_register_site_settings(): void {
		register_setting(
			'pera_site_settings',
			'pera_whatsapp_number',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'pera_sanitize_whatsapp_number',
				'default'           => pera_get_default_whatsapp_number(),
			)
		);

		add_settings_section(
			'pera_site_settings_contact',
			__( 'Contact settings', 'hello-elementor-child' ),
			'__return_false',
			'pera-site-settings'
		);

		add_settings_field(
			'pera_whatsapp_number',
			__( 'WhatsApp number', 'hello-elementor-child' ),
			'pera_render_whatsapp_number_field',
			'pera-site-settings',
			'pera_site_settings_contact'
		);
	}
}
add_action( 'admin_init', 'pera_register_site_settings' );

if ( ! function_exists( 'pera_add_site_settings_page' ) ) {
	/**
	 * Add Pera settings page for administrators.
	 */
	function pera_add_site_settings_page(): void {
		add_options_page(
			__( 'Pera Site Settings', 'hello-elementor-child' ),
			__( 'Pera Site Settings', 'hello-elementor-child' ),
			'manage_options',
			'pera-site-settings',
			'pera_render_site_settings_page'
		);
	}
}
add_action( 'admin_menu', 'pera_add_site_settings_page' );

if ( ! function_exists( 'pera_render_whatsapp_number_field' ) ) {
	/**
	 * Render WhatsApp number setting input.
	 */
	function pera_render_whatsapp_number_field(): void {
		?>
		<input
			type="text"
			class="regular-text"
			id="pera_whatsapp_number"
			name="pera_whatsapp_number"
			value="<?php echo esc_attr( pera_get_whatsapp_number() ); ?>"
			inputmode="numeric"
			pattern="[0-9]*"
		>
		<p class="description">
			<?php esc_html_e( 'Enter the international WhatsApp number using digits only, for example 905452054356.', 'hello-elementor-child' ); ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'pera_render_site_settings_page' ) ) {
	/**
	 * Render Pera settings page.
	 */
	function pera_render_site_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'hello-elementor-child' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'pera_site_settings' );
				do_settings_sections( 'pera-site-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
