<?php
/** End-to-end generation contract for synthetic homepage FAQ leaves. */
define( 'ABSPATH', __DIR__ );
class WP_Error { private $code; public function __construct( $code ) { $this->code = $code; } public function get_error_code() { return $this->code; } }
class WP_Term {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_text_field( $value ) { return (string) $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function apply_filters( $hook, $value ) { return $value; }
function get_post_type() { return 'page'; }
function get_posts() { return array( 20 ); }
function get_post( $id ) { return 20 === (int) $id ? (object) array( 'ID' => 20, 'post_type' => 'page', 'post_title' => 'Home', 'post_content' => '', 'post_excerpt' => '' ) : null; }
function get_the_title() { return 'Home'; }
function get_terms() { return array(); }
function get_post_meta( $id, $key ) { return 'faq' === $key ? $GLOBALS['faq_generation_rows'] : ''; }
function generation_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }

$GLOBALS['faq_generation_rows'] = array( array( 'question' => 'Actual English question', 'answer' => 'Actual English answer' ) );
final class Pera_ML_Storage { public static function normalize_field_key( $field ) { return $field; } }
final class FAQ_Generation_Storage {
	public $rows = array( 'meta:homepage_faq_0_answer|de' => array( 'translated_text' => 'Old answer', 'is_stale' => true, 'status' => 'current' ) );
	public function get( $type, $id, $field, $language, $source ) { return isset( $this->rows[ $field . '|' . $language ] ) ? $this->rows[ $field . '|' . $language ] : null; }
}
final class FAQ_Generation_Status {
	public function preload() {}
	public function applicable_sources() { return array( 'meta:homepage_faq_0_question' => 'Actual English question', 'meta:homepage_faq_0_answer' => 'Actual English answer' ); }
	public function get() { return array( 'missing' => array( 'meta:homepage_faq_0_question' ), 'stale' => array( 'meta:homepage_faq_0_answer' ) ); }
}
final class FAQ_Generation_Translator {
	public $calls = array(); private $storage;
	public function __construct( $storage ) { $this->storage = $storage; }
	public function translate_and_store( $type, $id, $field, $language, $source ) {
		$this->calls[] = func_get_args(); $translation = false !== strpos( $field, 'question' ) ? 'Deutsche Frage' : 'Deutsche Antwort';
		$this->storage->rows[ $field . '|' . $language ] = array( 'translated_text' => $translation, 'is_stale' => false, 'status' => 'current' );
		return $translation;
	}
}
final class FAQ_Generation_UI { public function inventory() { return array(); } }
final class FAQ_Generation_UI_Registry {}
final class FAQ_Generation_Router { public function current_language() { return 'de'; } }

require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translation-health.php';
require dirname( __DIR__ ) . '/includes/class-translation-health-orchestrator.php';
$storage = new FAQ_Generation_Storage(); $status = new FAQ_Generation_Status(); $translator = new FAQ_Generation_Translator( $storage );
$health = new Pera_ML_Translation_Health( $status, $storage, new FAQ_Generation_UI() );
$inventory = $health->inventory();
$question_rows = array_values( array_filter( $inventory['rows'], static function ( $row ) { return 'meta:homepage_faq_0_question' === $row['field'] && 'de' === $row['language']; } ) );
generation_expect( 'missing', $question_rows[0]['status'], 'Translation Health reports the FAQ question missing' );

$orchestrator = new Pera_ML_Translation_Health_Orchestrator( $status, $storage, $translator, null, new FAQ_Generation_UI_Registry() );
generation_expect( 'Deutsche Frage', $orchestrator->translate( array( 'object_type' => 'page', 'object_id' => 20, 'field' => 'meta:homepage_faq_0_question', 'language' => 'de', 'status' => 'missing' ) ), 'missing question generation succeeds' );
generation_expect( 'Deutsche Antwort', $orchestrator->translate( array( 'object_type' => 'page', 'object_id' => 20, 'field' => 'meta:homepage_faq_0_answer', 'language' => 'de', 'status' => 'stale' ) ), 'stale answer regeneration succeeds' );
generation_expect( array( 'post', 20, 'meta:homepage_faq_0_question', 'de', 'Actual English question' ), $translator->calls[0], 'question generator receives exact structured key and canonical source' );
generation_expect( array( 'post', 20, 'meta:homepage_faq_0_answer', 'de', 'Actual English answer' ), $translator->calls[1], 'answer generator receives exact structured key and canonical source' );
$display = ( new Pera_ML_Fields( new FAQ_Generation_Router(), $storage, null ) )->homepage_faq( 20, $GLOBALS['faq_generation_rows'] );
generation_expect( 'Deutsche Frage', $display[0]['question'], 'stored question is returned on the frontend' );
generation_expect( 'Deutsche Antwort', $display[0]['answer'], 'regenerated answer is returned on the frontend' );
echo "Pera ML homepage FAQ generation tests passed\n";
