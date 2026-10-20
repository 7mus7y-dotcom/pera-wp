<?php
defined( 'ABSPATH' ) || exit;
final class Pera_ML_Ajax {
	private $registry; private $router;
	private static $archive_context = false;
	public function __construct( $registry, $router ) { $this->registry = $registry; $this->router = $router; }
	public function hooks() {
		foreach ( array( 'wp_ajax_', 'wp_ajax_nopriv_' ) as $prefix ) {
			add_action( $prefix . 'pera_filter_properties_v2', array( $this, 'context' ), 0 );
			add_action( $prefix . 'pera_blog_search', array( $this, 'blog_context' ), 0 );
		}
	}
	/** Only nonce-validated public archive rendering may translate in admin-ajax. */
	public static function has_archive_context() { return wp_doing_ajax() && self::$archive_context; }
	public function blog_context() { $this->apply_context( 'pera_blog_search', 'nonce' ); }
	public function validate_language( $raw ) { $code = sanitize_key( is_string( $raw ) ? $raw : '' ); $enabled = $this->registry->enabled(); return isset( $enabled[ $code ] ) ? $code : 'en'; }
	public function context() { $this->apply_context( 'pera_ml_property_filter', 'pera_ml_nonce' ); }
	private function apply_context( $nonce_action, $nonce_key ) {
		self::$archive_context = false;
		if ( ! check_ajax_referer( $nonce_action, $nonce_key, false ) ) wp_send_json_error( array( 'message' => __( 'Invalid filter request.', 'pera-multilingual' ) ), 403 );
		$language = $this->validate_language( isset( $_POST['pera_ml_lang'] ) ? wp_unslash( $_POST['pera_ml_lang'] ) : 'en' );
		$this->router->set_request_language( $language );
		self::$archive_context = true;
	}
}
