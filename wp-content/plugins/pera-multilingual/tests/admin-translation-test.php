<?php
/** Standalone admin orchestration tests: php tests/admin-translation-test.php */
define( 'ABSPATH', __DIR__ );
function __( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return $value; }
function get_current_user_id() { return 7; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function apply_filters( $tag, $value ) { return 'pera_ml_admin_retry_delay' === $tag ? 0 : $value; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function get_transient( $key ) { return isset( $GLOBALS['transients'][ $key ] ) ? $GLOBALS['transients'][ $key ] : false; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function expect_same( $expected, $actual, $label ) {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}

require dirname( __DIR__ ) . '/admin/class-admin.php';

final class Admin_Test_Translator {
	public $calls = array();
	public $rows = array();
	public $failures = array();
	public $failure_code = 'pera_ml_structure_changed';
	public function translate_and_store( $type, $id, $field, $language, $source ) {
		$this->calls[] = $field;
		if ( ! empty( $this->failures[ $field ] ) ) {
			$this->failures[ $field ]--;
			return new WP_Error( $this->failure_code );
		}
		$this->rows[ $field ] = $language . ':' . $source;
		return $this->rows[ $field ];
	}
}

$admin = new Pera_ML_Admin( new stdClass() );
$sources = array( 'post_content' => 'Body', 'post_title' => 'Title', 'post_excerpt' => 'Excerpt' );
$translator = new Admin_Test_Translator();
$all = $admin->translate_fields( 59726, 'ar', $sources, $translator );
expect_same( array( 'successes' => 3, 'failures' => array() ), $all, 'all fields succeed' );
expect_same( array_keys( $sources ), $translator->calls, 'post_content is attempted first' );

$transient = new Admin_Test_Translator();
$transient->failures['post_content'] = 1;
$transient->failure_code = 'pera_ml_provider_transient';
$recovered = $admin->translate_fields( 59726, 'ar', array( 'post_content' => 'Body' ), $transient );
expect_same( array( 'successes' => 1, 'failures' => array() ), $recovered, 'transient failure receives one successful retry' );
expect_same( 2, count( $transient->calls ), 'transient retry is bounded to a second attempt' );

$translator = new Admin_Test_Translator();
$translator->failures['post_content'] = 1;
$partial = $admin->translate_fields( 59726, 'ar', $sources, $translator );
expect_same( array( 'successes' => 2, 'failures' => array( 'post_content' ) ), $partial, 'content failure is explicit' );
expect_same( array( 'post_title', 'post_excerpt' ), array_keys( $translator->rows ), 'successful rows remain after sibling failure' );

$retry = $admin->translate_fields( 59726, 'ar', $sources, $translator );
expect_same( array( 'successes' => 3, 'failures' => array() ), $retry, 'later object run fills missing content' );
expect_same( array( 'post_title', 'post_excerpt', 'post_content' ), array_keys( $translator->rows ), 'later run does not delete successful rows' );
expect_same( 'ar:Title', $translator->rows['post_title'], 'existing successful title remains stored' );

$GLOBALS['transients']['pera_ml_notice_7_59726_result'] = array( 'successes' => 2, 'failures' => array( 'post_content' ) );
$_GET = array( 'pera_ml_notice' => 'result', 'post' => '59726' );
ob_start();
$admin->translation_notice();
$notice = ob_get_clean();
expect_same( true, false !== strpos( $notice, '2 fields succeeded; 1 fields failed' ), 'notice reports counts' );
expect_same( true, false !== strpos( $notice, 'Failed fields: post_content' ), 'notice identifies content without error details' );

echo "Pera ML admin translation tests passed\n";
