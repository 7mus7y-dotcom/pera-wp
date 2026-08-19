<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Admin {
	private $registry;
	public function __construct( $registry ) { $this->registry = $registry; }
	public function hooks() { add_action( 'admin_menu', array( $this, 'menu' ) ); add_action( 'admin_init', array( $this, 'settings' ) ); }
	public function menu() { add_options_page( __( 'Pera Multilingual', 'pera-multilingual' ), __( 'Pera Multilingual', 'pera-multilingual' ), 'manage_options', 'pera-multilingual', array( $this, 'page' ) ); }
	public function settings() {
		register_setting( 'pera_ml', 'pera_ml_enabled_languages', array( 'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize_languages' ) ) );
		register_setting( 'pera_ml', 'pera_ml_provider', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_provider' ), 'default' => 'openai' ) );
		register_setting( 'pera_ml', 'pera_ml_automatic', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) );
		register_setting( 'pera_ml', 'pera_ml_openai_api_key', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_secret' ), 'show_in_rest' => false ) );
		register_setting( 'pera_ml', 'pera_ml_openai_model', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'gpt-4.1-mini' ) );
		register_setting( 'pera_ml', 'pera_ml_glossary_text', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_glossary' ) ) );
	}
	public function sanitize_languages( $value ) { $allowed = array_keys( $this->registry->all() ); $value = is_array( $value ) ? array_intersect( array_map( 'sanitize_key', $value ), $allowed ) : array(); $value[] = 'en'; return array_values( array_unique( $value ) ); }
	public function sanitize_provider( $value ) { return in_array( $value, array( 'openai', 'mock' ), true ) ? $value : 'openai'; }
	public function sanitize_secret( $value ) { if ( '' === trim( (string) $value ) ) return get_option( 'pera_ml_openai_api_key', '' ); return trim( sanitize_text_field( $value ) ); }
	public function sanitize_glossary( $value ) {
		$text = sanitize_textarea_field( $value ); $entries = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) { $parts = array_map( 'trim', explode( '=>', $line, 2 ) ); if ( '' !== $parts[0] ) $entries[] = array( 'source' => $parts[0], 'translation' => isset( $parts[1] ) ? $parts[1] : '' ); }
		update_option( 'pera_ml_glossary', $entries ); return $text;
	}
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$languages = $this->registry->all(); $enabled = get_option( 'pera_ml_enabled_languages', array( 'en', 'zh', 'ar' ) );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Pera Multilingual', 'pera-multilingual' ); ?></h1>
		<p><?php esc_html_e( 'Translated requests resolve the original English WordPress object. Frontend requests only read saved translations; they never call a provider.', 'pera-multilingual' ); ?></p>
		<form method="post" action="options.php"><?php settings_fields( 'pera_ml' ); ?>
		<table class="form-table" role="presentation"><tr><th><?php esc_html_e( 'Enabled languages', 'pera-multilingual' ); ?></th><td>
		<?php foreach ( $languages as $code => $language ) : ?><label style="display:block"><input type="checkbox" name="pera_ml_enabled_languages[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, (array) $enabled, true ) ); disabled( 'en' === $code ); ?>> <?php echo esc_html( $language['name'] . ' — ' . $language['native_name'] ); ?></label><?php endforeach; ?></td></tr>
		<tr><th><label for="pera-ml-provider"><?php esc_html_e( 'Provider', 'pera-multilingual' ); ?></label></th><td><select id="pera-ml-provider" name="pera_ml_provider"><option value="openai" <?php selected( get_option( 'pera_ml_provider', 'openai' ), 'openai' ); ?>>OpenAI</option><option value="mock" <?php selected( get_option( 'pera_ml_provider' ), 'mock' ); ?>><?php esc_html_e( 'Deterministic mock (testing)', 'pera-multilingual' ); ?></option></select></td></tr>
		<tr><th><?php esc_html_e( 'Automatic translation', 'pera-multilingual' ); ?></th><td><label><input type="checkbox" name="pera_ml_automatic" value="1" <?php checked( get_option( 'pera_ml_automatic', false ) ); ?>> <?php esc_html_e( 'Enable for future background jobs (never runs during page rendering)', 'pera-multilingual' ); ?></label></td></tr>
		<tr><th><label for="pera-ml-key"><?php esc_html_e( 'OpenAI API key', 'pera-multilingual' ); ?></label></th><td><input id="pera-ml-key" class="regular-text" type="password" autocomplete="new-password" name="pera_ml_openai_api_key" value="" placeholder="<?php echo get_option( 'pera_ml_openai_api_key' ) ? esc_attr__( 'Saved — leave blank to retain', 'pera-multilingual' ) : ''; ?>"><p class="description"><?php esc_html_e( 'Prefer PERA_ML_OPENAI_API_KEY in wp-config.php. Keys are never exposed to frontend code.', 'pera-multilingual' ); ?></p></td></tr>
		<tr><th><label for="pera-ml-model"><?php esc_html_e( 'OpenAI model', 'pera-multilingual' ); ?></label></th><td><input id="pera-ml-model" class="regular-text" name="pera_ml_openai_model" value="<?php echo esc_attr( get_option( 'pera_ml_openai_model', 'gpt-4.1-mini' ) ); ?>"></td></tr>
		<tr><th><label for="pera-ml-glossary"><?php esc_html_e( 'Glossary / protected terms', 'pera-multilingual' ); ?></label></th><td><textarea id="pera-ml-glossary" class="large-text code" rows="10" name="pera_ml_glossary_text" placeholder="Tapu => PRESERVE&#10;Title Deed => ..."><?php echo esc_textarea( get_option( 'pera_ml_glossary_text', "Pera Property => PRESERVE\nBeyoğlu => PRESERVE\nBeşiktaş => PRESERVE\nKadıköy => PRESERVE" ) ); ?></textarea><p class="description"><?php esc_html_e( 'One “source => required translation” rule per line. Use PRESERVE to protect a term.', 'pera-multilingual' ); ?></p></td></tr></table>
		<?php submit_button(); ?></form></div><?php
	}
}
