<?php
/** Focused individual taxonomy-term translation admin regression tests. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['actions'] = array(); $GLOBALS['logged_in'] = true; $GLOBALS['can_edit'] = true; $GLOBALS['nonce_valid'] = true;
function add_action( $hook, $callback ) { $GLOBALS['actions'][] = $hook; }
function add_filter( $hook, $callback ) { $GLOBALS['actions'][] = $hook; }
function __( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return (string) $value; }
function wp_unslash( $value ) { return $value; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function current_user_can() { return $GLOBALS['can_edit']; }
function check_ajax_referer() { return $GLOBALS['nonce_valid']; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_taxonomy( $taxonomy ) { return 'unsupported' === $taxonomy ? false : (object) array( 'cap' => (object) array( 'edit_terms' => 'manage_categories' ) ); }
function get_term( $id, $taxonomy = '' ) { return isset( $GLOBALS['terms'][ $id ] ) ? $GLOBALS['terms'][ $id ] : null; }
function get_term_meta( $id, $field ) { return isset( $GLOBALS['term_meta'][ $id ][ $field ] ) ? $GLOBALS['term_meta'][ $id ][ $field ] : ''; }
function apply_filters( $tag, $value ) { return $value; }
class WP_Term { public $term_id; public $taxonomy; public $name; public $description; }
class WP_Error { private $code; public function __construct( $code ) { $this->code = $code; } public function get_error_code() { return $this->code; } }
function term_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }

require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translation-health.php';
require dirname( __DIR__ ) . '/admin/class-admin.php';

final class Term_Test_Registry {
	public function get( $language ) { if ( 'en' === $language ) return array( 'enabled' => true, 'source' => true ); return in_array( $language, array( 'zh', 'de' ), true ) ? array( 'enabled' => true, 'source' => false ) : null; }
	public function enabled() { return array( 'en' => array( 'name' => 'English', 'source' => true ), 'zh' => array( 'name' => 'Chinese', 'source' => false ), 'de' => array( 'name' => 'German', 'source' => false ) ); }
}
final class Term_Test_Storage {
	public $rows = array();
	public function get( $type, $id, $field, $language, $source ) { return isset( $this->rows[ $language ][ $field ] ) ? $this->rows[ $language ][ $field ] : null; }
}
final class Term_Test_Health {
	public $sources; public $status;
	public function term_sources() { return $this->sources; }
	public function term_status() { return $this->status; }
}
final class Term_Test_Orchestrator {
	public $calls = array();
	public function translate( array $row, $regenerate = false ) { $this->calls[] = array( $row, $regenerate ); return 'translated'; }
}

$admin = new Pera_ML_Admin( new Term_Test_Registry() ); $admin->hooks();
foreach ( Pera_ML_Fields::supported_taxonomies() as $taxonomy ) term_expect( true, in_array( $taxonomy . '_edit_form_fields', $GLOBALS['actions'], true ), "{$taxonomy} uses shared term-edit hook" );
term_expect( true, in_array( 'category_edit_form_fields', $GLOBALS['actions'], true ), 'category is registered through the shared contract' );
term_expect( false, in_array( 'unsupported_edit_form_fields', $GLOBALS['actions'], true ), 'unsupported taxonomy receives no panel hook' );
$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
term_expect( false, false !== strpos( $source, 'wp_update_term(' ), 'admin never mutates the canonical term' );
term_expect( true, false !== strpos( file_get_contents( dirname( __DIR__ ) . '/admin/translation-queue.js' ), "isTerm ? 'pera_ml_term_translation_queue' : 'pera_ml_translation_queue'" ), 'shared queue preserves the content action and selects the term action by object type' );

$term = new WP_Term(); $term->term_id = 102; $term->taxonomy = 'category'; $term->name = 'News'; $term->description = 'Updates'; $GLOBALS['terms'][102] = $term;
$storage = new Term_Test_Storage(); $health = new Pera_ML_Translation_Health( new stdClass(), $storage, new stdClass() );
$status = $health->term_status( $term, 'category', 'zh' );
term_expect( array( 'term_name', 'term_description' ), $status['missing'], 'missing canonical term fields are reported' );
$storage->rows['zh']['term_name'] = array( 'translated_text' => '新闻', 'is_stale' => true, 'status' => 'stale' );
$storage->rows['zh']['term_description'] = array( 'translated_text' => '更新', 'is_stale' => false, 'status' => 'current' );
$status = $health->term_status( $term, 'category', 'zh' );
term_expect( array( 'term_name' ), $status['stale'], 'stale canonical term fields are reported' ); term_expect( false, $status['complete'], 'stale translation is incomplete' );
$storage->rows['zh']['term_name'] = array( 'translated_text' => '新闻', 'is_stale' => false, 'status' => 'current' );
term_expect( true, $health->term_status( $term, 'category', 'zh' )['complete'], 'all-current translation is complete' );

$fake_health = new Term_Test_Health(); $fake_health->sources = array( 'term_name' => 'News', 'term_description' => 'Updates' ); $fake_health->status = array( 'applicable' => 2, 'current' => 0, 'existing' => 1, 'missing' => array( 'term_description' ), 'stale' => array( 'term_name' ), 'complete' => false );
term_expect( array( 'term_description', 'term_name' ), $admin->term_translation_queue( $term, 'category', 'zh', 'complete', $fake_health )['fields'], 'complete queues only missing and stale canonical fields' );
$fake_health->status = array( 'applicable' => 2, 'current' => 2, 'existing' => 2, 'missing' => array(), 'stale' => array(), 'complete' => true );
term_expect( array( 'term_name', 'term_description' ), $admin->term_translation_queue( $term, 'category', 'zh', 'regenerate', $fake_health )['fields'], 'regenerate queues every applicable canonical field' );
$orchestrator = new Term_Test_Orchestrator();
term_expect( 'invalid_field', $admin->translate_term_field( $term, 'category', 'zh', 'meta:arbitrary', 'regenerate', $fake_health, $orchestrator )->get_error_code(), 'arbitrary taxonomy meta is rejected' );
term_expect( 'translated', $admin->translate_term_field( $term, 'category', 'zh', 'term_name', 'regenerate', $fake_health, $orchestrator ), 'regenerate uses the shared orchestrator' );
term_expect( true, $orchestrator->calls[0][1], 'regenerate explicitly forces a current canonical field' );

$_POST = array( 'term_id' => 102, 'taxonomy' => 'category', 'language' => 'zh', 'nonce' => 'valid' );
term_expect( array( $term, 'category', 'zh' ), $admin->term_ajax_request(), 'valid term request passes' );
$GLOBALS['nonce_valid'] = false; term_expect( 'invalid_nonce', $admin->term_ajax_request()->get_error_code(), 'invalid nonce is rejected' );
$GLOBALS['nonce_valid'] = true; $GLOBALS['can_edit'] = false; term_expect( 'insufficient_capability', $admin->term_ajax_request()->get_error_code(), 'taxonomy edit capability is required' );
$GLOBALS['can_edit'] = true; $_POST['taxonomy'] = 'unsupported'; term_expect( 'invalid_taxonomy', $admin->term_ajax_request()->get_error_code(), 'unsupported taxonomy is rejected' );
$_POST['taxonomy'] = 'post_tag'; term_expect( 'invalid_term', $admin->term_ajax_request()->get_error_code(), 'term and taxonomy mismatch is rejected' );
$_POST['taxonomy'] = 'category'; $_POST['language'] = 'en'; term_expect( 'invalid_language', $admin->term_ajax_request()->get_error_code(), 'source language is rejected' );
$_POST['language'] = 'ar'; term_expect( 'invalid_language', $admin->term_ajax_request()->get_error_code(), 'disabled target language is rejected' );

echo "Pera ML taxonomy term admin tests passed\n";
