<?php
/** Standalone UI generation guard and provider-prompt tests. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['ui_guard_provider'] = null;
$GLOBALS['ui_guard_errors'] = array();
$GLOBALS['ui_guard_request'] = array();
function __( $value ) { return $value; }
function get_option( $key, $default = false ) { return 'pera_ml_openai_api_key' === $key ? 'test-key' : $default; }
function apply_filters( $tag, $value ) { return 'pera_ml_provider' === $tag && $GLOBALS['ui_guard_provider'] ? $GLOBALS['ui_guard_provider'] : $value; }
function do_action( $tag ) { if ( 'pera_ml_translation_error' === $tag ) $GLOBALS['ui_guard_errors'][] = func_get_args(); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function sanitize_text_field( $value ) { return $value; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_safe_remote_post( $url, $args ) {
	$GLOBALS['ui_guard_request'] = $args;
	$body = array( 'output' => array( array( 'content' => array( array( 'type' => 'output_text', 'text' => 'Перевод' ) ) ) ) );
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $body ) );
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function expect_ui_guard( $condition, $label ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); }
}

require dirname( __DIR__ ) . '/includes/providers/interface-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-mock-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-openai-provider.php';
require dirname( __DIR__ ) . '/includes/class-translator.php';

final class UI_Guard_Provider implements Pera_ML_Provider_Interface {
	public $response;
	public $calls = array();
	public function __construct( $response ) { $this->response = $response; }
	public function id() { return 'ui-guard'; }
	public function translate( $source, array $context ) { $this->calls[] = compact( 'source', 'context' ); return $this->response; }
}
final class UI_Guard_Storage {
	public $puts = array();
	public $current = 'Хороший существующий перевод';
	public function put() { $this->puts[] = func_get_args(); $this->current = func_get_arg( 5 ); return true; }
}
$registry = new class { public function get() { return array( 'name' => 'Russian', 'source' => false, 'instructions' => 'Translate naturally.' ); } };
$translate = static function ( $type, $field, $response, $source = 'Discuss home care' ) use ( $registry ) {
	$GLOBALS['ui_guard_provider'] = new UI_Guard_Provider( $response );
	$storage = new UI_Guard_Storage();
	$translator = new Pera_ML_Translator( $registry, $storage );
	$result = $translator->translate_and_store( $type, 123, $field, 'ru', $source, 'mock' );
	return array( $result, $GLOBALS['ui_guard_provider'], $storage );
};

list( $result, $provider, $storage ) = $translate( 'ui', 'cta.discuss_home_care', 'Обсудить уход на дому' );
expect_ui_guard( 'Обсудить уход на дому' === $result && 1 === count( $storage->puts ), 'normal UI translation is stored' );
expect_ui_guard( 'ui' === $provider->calls[0]['context']['object_type'], 'UI object type reaches provider' );
expect_ui_guard( 'cta.discuss_home_care' === $provider->calls[0]['context']['field'], 'UI field reaches provider' );

$expanded = implode( ' ', array_fill( 0, 220, 'подробное объяснение' ) );
$GLOBALS['ui_guard_errors'] = array();
list( $result, $provider, $storage ) = $translate( 'ui', 'cta.discuss_home_care', $expanded );
expect_ui_guard( is_wp_error( $result ) && 'pera_ml_ui_translation_expanded' === $result->get_error_code(), 'huge UI response is rejected' );
expect_ui_guard( 0 === count( $storage->puts ), 'rejected UI response is not stored current' );
expect_ui_guard( 'Хороший существующий перевод' === $storage->current, 'rejected regeneration does not overwrite existing translation' );
expect_ui_guard( 1 === count( $GLOBALS['ui_guard_errors'] ), 'rejection invokes translation error action' );

$legitimate = str_repeat( 'Очень важное сообщение для посетителя. ', 5 );
list( $result, $provider, $storage ) = $translate( 'ui', 'notice.short', $legitimate, 'Important notice' );
expect_ui_guard( ! is_wp_error( $result ) && 1 === count( $storage->puts ), 'reasonable multilingual UI expansion is accepted' );

list( $result, $provider, $storage ) = $translate( 'post', 'post_content', $expanded );
expect_ui_guard( ! is_wp_error( $result ) && 1 === count( $storage->puts ), 'non-UI long-form response is not subject to UI guard' );

$openai = new Pera_ML_OpenAI_Provider();
$openai->translate( 'Discuss home care', array( 'target_language' => 'ru', 'target_name' => 'Russian', 'instructions' => 'Keep placeholders.', 'glossary' => 'home care = уход на дому', 'object_type' => 'ui', 'field' => 'cta.discuss_home_care' ) );
$ui_prompt = json_decode( $GLOBALS['ui_guard_request']['body'], true )['instructions'];
expect_ui_guard( false !== strpos( $ui_prompt, 'visitor-facing UI string' ) && false !== strpos( $ui_prompt, 'a short source must produce a short translation' ), 'UI prompt includes concise UI-only instruction' );
expect_ui_guard( false !== strpos( $ui_prompt, 'Preserve every HTML tag' ) && false !== strpos( $ui_prompt, 'Keep placeholders.' ) && false !== strpos( $ui_prompt, 'home care = уход на дому' ), 'UI prompt retains existing protection, language, and glossary instructions' );

$openai->translate( 'Long post copy', array( 'target_language' => 'ru', 'target_name' => 'Russian', 'instructions' => '', 'glossary' => '', 'object_type' => 'post', 'field' => 'post_content' ) );
$post_prompt = json_decode( $GLOBALS['ui_guard_request']['body'], true )['instructions'];
expect_ui_guard( false === strpos( $post_prompt, 'visitor-facing UI string' ), 'post prompt does not receive UI-only instruction' );

echo "Pera ML UI translation robustness tests passed\n";
