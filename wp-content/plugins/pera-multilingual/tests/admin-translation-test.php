<?php
/** Standalone admin orchestration tests: php tests/admin-translation-test.php */
define( 'ABSPATH', __DIR__ );
function __( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return $value; }
function get_current_user_id() { return 7; }
function is_user_logged_in() { return ! empty( $GLOBALS['logged_in'] ); }
function current_user_can() { return ! empty( $GLOBALS['can_edit'] ); }
function check_ajax_referer() { return ! empty( $GLOBALS['nonce_valid'] ); }
function get_post( $post_id ) { return isset( $GLOBALS['posts'][ $post_id ] ) ? $GLOBALS['posts'][ $post_id ] : null; }
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

final class Admin_Test_Registry {
	public function get( $language ) { return in_array( $language, array( 'zh', 'ar', 'de' ), true ) ? array( 'enabled' => true, 'source' => false ) : null; }
	public function enabled() { return array( 'en' => array( 'source' => true ), 'zh' => array( 'source' => false ), 'ar' => array( 'source' => false ), 'de' => array( 'source' => false ) ); }
}
final class Admin_Test_Status {
	public $sources = array();
	public $statuses = array();
	public $invalidations = array();
	public function applicable_sources() { return $this->sources; }
	public function get( $post_id, $language ) { return $this->statuses[ $language ]; }
	public function invalidate( $post_id, $language ) { $this->invalidations[] = $post_id . ':' . $language; }
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

$contract_admin = new Pera_ML_Admin( new Admin_Test_Registry() );
$columns = $contract_admin->post_columns( array( 'title' => 'Title' ) );
expect_same( 'DE', $columns['pera_ml_de'], 'Posts list automatically receives the German status column' );
$GLOBALS['logged_in'] = true; $GLOBALS['can_edit'] = true; $GLOBALS['nonce_valid'] = true;
$GLOBALS['posts'][59726] = (object) array( 'post_type' => 'post' );
$_POST = array( 'post_id' => 59726, 'language' => 'zh', 'nonce' => 'valid' );
$request_method = new ReflectionMethod( 'Pera_ML_Admin', 'ajax_request' ); $request_method->setAccessible( true );
expect_same( array( 59726, 'zh' ), $request_method->invoke( $contract_admin ), 'authenticated field request validation succeeds' );
$GLOBALS['nonce_valid'] = false; expect_same( 'invalid_nonce', $request_method->invoke( $contract_admin )->get_error_code(), 'invalid nonce rejected' );
$GLOBALS['nonce_valid'] = true; $GLOBALS['can_edit'] = false; expect_same( 'insufficient_capability', $request_method->invoke( $contract_admin )->get_error_code(), 'insufficient capability rejected' );
$GLOBALS['can_edit'] = true; $_POST['language'] = 'de'; expect_same( array( 59726, 'de' ), $request_method->invoke( $contract_admin ), 'German AJAX request validation succeeds' );
$_POST['language'] = 'xx'; expect_same( 'invalid_language', $request_method->invoke( $contract_admin )->get_error_code(), 'invalid language rejected' );

$status = new Admin_Test_Status();
$status->sources = array( 'post_content' => 'Body', 'post_title' => 'Title', 'meta:seo_title' => 'SEO' );
$status->statuses['zh'] = array( 'applicable' => 3, 'current' => 1, 'existing' => 2, 'missing' => array( 'post_content' ), 'stale' => array( 'meta:seo_title' ), 'complete' => false );
$status->statuses['ar'] = array( 'applicable' => 3, 'current' => 2, 'existing' => 2, 'missing' => array( 'post_title' ), 'stale' => array(), 'complete' => false );
$status->statuses['de'] = array( 'applicable' => 3, 'current' => 1, 'existing' => 1, 'missing' => array( 'post_content', 'post_title' ), 'stale' => array(), 'complete' => false );
expect_same( array( 'post_content', 'meta:seo_title' ), $contract_admin->translation_queue( 59726, 'zh', 'complete', $status )['fields'], 'missing/stale queue only contains work and content is first' );
expect_same( array_keys( $status->sources ), $contract_admin->translation_queue( 59726, 'zh', 'regenerate', $status )['fields'], 'regenerate queues every applicable field' );
expect_same( array( 'post_title' ), $contract_admin->translation_queue( 59726, 'ar', 'complete', $status )['fields'], 'Chinese and Arabic queues are independent' );
expect_same( array( 'post_content', 'post_title' ), $contract_admin->translation_queue( 59726, 'de', 'complete', $status )['fields'], 'German queue is independent from Chinese and Arabic' );

$single = new Admin_Test_Translator();
expect_same( 'zh:Body', $contract_admin->translate_field( 59726, 'zh', 'post_content', $single, $status ), 'post_content field request succeeds' );
expect_same( array( 'post_content' ), $single->calls, 'post_content request translates only post_content' );
expect_same( 'invalid_field', $contract_admin->translate_field( 59726, 'zh', 'meta:not_approved', $single, $status )->get_error_code(), 'arbitrary field is rejected' );
expect_same( array( 'post_content' ), $single->calls, 'provider is not called for invalid field' );
$before_status_calls = count( $single->calls ); $contract_admin->translation_queue( 59726, 'zh', 'complete', $status );
expect_same( $before_status_calls, count( $single->calls ), 'provider is never called for status-only queue request' );

$GLOBALS['transients']['pera_ml_notice_7_59726_result'] = array( 'successes' => 2, 'failures' => array( 'post_content' ) );
$_GET = array( 'pera_ml_notice' => 'result', 'post' => '59726' );
ob_start();
$admin->translation_notice();
$notice = ob_get_clean();
expect_same( true, false !== strpos( $notice, '2 fields succeeded; 1 fields failed' ), 'notice reports counts' );
expect_same( true, false !== strpos( $notice, 'Failed fields: post_content' ), 'notice identifies content without error details' );

echo "Pera ML admin translation tests passed\n";
