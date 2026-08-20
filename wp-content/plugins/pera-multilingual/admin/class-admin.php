<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Admin {
	private $registry; private $translation_forms = array();
	public function __construct( $registry ) { $this->registry = $registry; }
	public function hooks() { add_action( 'admin_menu', array( $this, 'menu' ) ); add_action( 'admin_init', array( $this, 'settings' ) ); add_action( 'add_meta_boxes', array( $this, 'meta_box' ) ); add_action( 'admin_footer-post.php', array( $this, 'translation_forms' ) ); add_action( 'admin_footer-post-new.php', array( $this, 'translation_forms' ) ); add_action( 'admin_post_pera_ml_translate_object', array( $this, 'translate_object' ) ); add_action( 'admin_notices', array( $this, 'translation_notice' ) ); }
	public function translation_notice() {
		if ( empty( $_GET['pera_ml_notice'] ) || empty( $_GET['post'] ) ) return;
		$key = $this->notice_key( get_current_user_id(), absint( $_GET['post'] ), sanitize_key( wp_unslash( $_GET['pera_ml_notice'] ) ) );
		$notice = get_transient( $key ); if ( ! is_array( $notice ) ) return; delete_transient( $key );
		$failures = isset( $notice['failures'] ) && is_array( $notice['failures'] ) ? $notice['failures'] : array();
		echo '<div class="notice ' . ( $failures ? 'notice-warning' : 'notice-success' ) . ' is-dismissible"><p>' . ( $failures ? esc_html( sprintf( __( 'Translation saved with field failures: %s', 'pera-multilingual' ), implode( '; ', $failures ) ) ) : esc_html__( 'Object translation saved.', 'pera-multilingual' ) ) . '</p></div>';
	}
	public function meta_box() { foreach ( get_post_types( array( 'public' => true ) ) as $type ) add_meta_box( 'pera-ml-translate', __( 'Pera Multilingual', 'pera-multilingual' ), array( $this, 'meta_box_html' ), $type, 'side' ); }
	public function meta_box_html( $post ) { foreach ( $this->registry->enabled() as $code => $language ) { if ( 'en' === $code ) continue; $form_id = 'pera-ml-translate-' . (int) $post->ID . '-' . sanitize_html_class( $code ); $this->translation_forms[ $form_id ] = array( 'post_id' => (int) $post->ID, 'language' => $code ); echo '<p><button class="button" type="submit" form="' . esc_attr( $form_id ) . '">' . esc_html( sprintf( __( 'Translate this object → %s', 'pera-multilingual' ), $language['name'] ) ) . '</button></p>'; } }
	public function translation_forms() { foreach ( $this->translation_forms as $form_id => $data ) { echo '<form hidden id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pera_ml_translate_object"><input type="hidden" name="post_id" value="' . (int) $data['post_id'] . '"><input type="hidden" name="language" value="' . esc_attr( $data['language'] ) . '">'; wp_nonce_field( 'pera_ml_translate_' . $data['post_id'] . '_' . $data['language'] ); echo '</form>'; } }
	public function translate_object() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) wp_die( esc_html__( 'Translation requests must use POST.', 'pera-multilingual' ), 405 );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; $language = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : '';
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_die( esc_html__( 'You cannot translate this object.', 'pera-multilingual' ), 403 );
		check_admin_referer( 'pera_ml_translate_' . $post_id . '_' . $language ); $config = $this->registry->get( $language ); if ( ! $config || empty( $config['enabled'] ) || ! empty( $config['source'] ) ) wp_die( esc_html__( 'Invalid target language.', 'pera-multilingual' ), 400 );
		$post = get_post( $post_id ); $sources = array( 'post_title' => $post->post_title, 'post_content' => $post->post_content, 'post_excerpt' => $post->post_excerpt );
		foreach ( Pera_ML_Plugin::instance()->fields()->approved() as $field ) { $value = get_post_meta( $post_id, $field, true ); if ( is_string( $value ) && '' !== trim( $value ) ) $sources[ 'meta:' . $field ] = $value; }
		$failures = array(); foreach ( $sources as $field => $source ) { if ( '' === trim( $source ) ) continue; $result = Pera_ML_Plugin::instance()->translator()->translate_and_store( 'post', $post_id, $field, $language, $source ); if ( is_wp_error( $result ) ) $failures[] = $field; }
		foreach ( array( 'district', 'region', 'property_type', 'property_tags', 'special' ) as $taxonomy ) { $terms = wp_get_post_terms( $post_id, $taxonomy ); if ( is_wp_error( $terms ) ) { $failures[] = $taxonomy . ':terms'; continue; } foreach ( $terms as $term ) {
			$name = Pera_ML_Plugin::instance()->vocabulary()->translate( $term->name, $language );
			$result = $name !== $term->name ? Pera_ML_Plugin::instance()->storage()->put( 'term', $term->term_id, 'term_name', $language, $term->name, $name, 'vocabulary' ) : Pera_ML_Plugin::instance()->translator()->translate_and_store( 'term', $term->term_id, 'term_name', $language, $term->name ); if ( is_wp_error( $result ) ) $failures[] = $taxonomy . ':' . $term->term_id . ':name';
			if ( '' !== trim( $term->description ) ) { $result = Pera_ML_Plugin::instance()->translator()->translate_and_store( 'term', $term->term_id, 'term_description', $language, $term->description ); if ( is_wp_error( $result ) ) $failures[] = $taxonomy . ':' . $term->term_id . ':description'; }
			if ( 'district' === $taxonomy ) foreach ( array( 'district_archive_subtitle', 'district_archive_body' ) as $field ) { $source = get_term_meta( $term->term_id, $field, true ); if ( is_string( $source ) && '' !== trim( $source ) ) { $result = Pera_ML_Plugin::instance()->translator()->translate_and_store( 'term', $term->term_id, 'meta:' . $field, $language, $source ); if ( is_wp_error( $result ) ) $failures[] = $taxonomy . ':' . $term->term_id . ':' . $field; } }
		} }
		$notice_id = wp_generate_password( 12, false, false ); set_transient( $this->notice_key( get_current_user_id(), $post_id, $notice_id ), array( 'language' => $language, 'failures' => array_slice( $failures, 0, 50 ) ), 5 * MINUTE_IN_SECONDS );
		$redirect = add_query_arg( 'pera_ml_notice', $notice_id, get_edit_post_link( $post_id, 'url' ) ); wp_safe_redirect( $redirect ); exit;
	}
	private function notice_key( $user_id, $post_id, $notice_id ) { return 'pera_ml_notice_' . absint( $user_id ) . '_' . absint( $post_id ) . '_' . sanitize_key( $notice_id ); }
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
