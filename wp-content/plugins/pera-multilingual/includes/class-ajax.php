<?php
defined( 'ABSPATH' ) || exit;
final class Pera_ML_Ajax {
	private $registry; private $router;
	public function __construct( $registry, $router ) { $this->registry = $registry; $this->router = $router; }
	public function hooks() { add_action( 'wp_ajax_nopriv_pera_filter_properties_v2', array( $this, 'context' ), 0 ); add_action( 'wp_ajax_pera_filter_properties_v2', array( $this, 'context' ), 0 ); }
	public function validate_language( $raw ) { $code = sanitize_key( (string) $raw ); $enabled = $this->registry->enabled(); return isset( $enabled[ $code ] ) ? $code : 'en'; }
	public function context() {
		if ( ! check_ajax_referer( 'pera_ml_property_filter', 'pera_ml_nonce', false ) ) wp_send_json_error( array( 'message' => __( 'Invalid filter request.', 'pera-multilingual' ) ), 403 );
		$language = $this->validate_language( isset( $_POST['pera_ml_lang'] ) ? wp_unslash( $_POST['pera_ml_lang'] ) : 'en' );
		$this->router->set_request_language( $language );
	}
}
