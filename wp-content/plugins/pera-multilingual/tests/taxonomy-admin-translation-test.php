<?php
/** Focused individual taxonomy-term admin UX tests. */
define( 'ABSPATH', __DIR__ );
function __( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return (string) $value; }
function wp_unslash( $value ) { return $value; }
function apply_filters( $tag, $value ) { return $value; }
function is_user_logged_in() { return ! empty( $GLOBALS['logged_in'] ); }
function current_user_can( $capability ) { $GLOBALS['checked_capability'] = $capability; return ! empty( $GLOBALS['can_edit'] ); }
function check_ajax_referer() { return ! empty( $GLOBALS['nonce_valid'] ); }
function get_taxonomy( $taxonomy ) { return 'category' === $taxonomy ? (object) array( 'cap' => (object) array( 'edit_terms' => 'manage_categories' ) ) : null; }
function get_term( $id, $taxonomy = '' ) { return isset( $GLOBALS['terms'][ $id ] ) && ( ! $taxonomy || $GLOBALS['terms'][ $id ]->taxonomy === $taxonomy ) ? $GLOBALS['terms'][ $id ] : null; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Term { public $term_id; public $taxonomy; public $name; public $description; }
class WP_Error { private $code; public function __construct( $code ) { $this->code = $code; } public function get_error_code() { return $this->code; } }
function term_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }

require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/admin/class-admin.php';
final class Term_Registry { public function get( $code ) { return in_array( $code, array( 'zh', 'ar', 'de' ), true ) ? array( 'enabled' => true, 'source' => false ) : null; } public function enabled() { return array( 'en' => array( 'name' => 'English', 'source' => true ), 'zh' => array( 'name' => 'Chinese', 'source' => false ), 'ar' => array( 'name' => 'Arabic', 'source' => false ), 'de' => array( 'name' => 'German', 'source' => false ) ); } }
final class Term_Health { public $status; public function term_sources() { return array( 'term_name' => 'News', 'term_description' => 'Updates' ); } public function term_status() { return $this->status; } }
final class Term_Orchestrator { public $row; public function translate( array $row ) { $this->row = $row; return 'stored'; } }

$source = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin.php' );
term_expect( true, in_array( 'category', Pera_ML_Fields::supported_taxonomies(), true ), 'category is supported' );
term_expect( true, false !== strpos( $source, "foreach ( Pera_ML_Fields::supported_taxonomies() as \$taxonomy ) add_action( \$taxonomy . '_edit_form_fields'" ), 'only the shared supported-taxonomy contract registers panels' );
term_expect( true, false !== strpos( $source, "if ( 'en' === \$code ) continue" ), 'English remains excluded from translation targets' );
term_expect( true, false !== strpos( $source, 'foreach ( $this->target_languages() as $code => $language )' ), 'enabled target languages render in the term panel' );

$term = new WP_Term(); $term->term_id = 102; $term->taxonomy = 'category'; $term->name = 'News'; $term->description = 'Updates'; $GLOBALS['terms'][102] = $term;
$health = new Term_Health(); $health->status = array( 'applicable' => 2, 'current' => 1, 'existing' => 1, 'missing' => array( 'term_description' ), 'stale' => array(), 'complete' => false );
$admin = new Pera_ML_Admin( new Term_Registry() );
term_expect( array( 'term_description' ), $admin->term_translation_queue( $term, 'category', 'ar', 'complete', $health )['fields'], 'missing field is reported by canonical health state' );
term_expect( array( 'term_name', 'term_description' ), $admin->term_translation_queue( $term, 'category', 'ar', 'regenerate', $health )['fields'], 'regenerate uses the same canonical inventory' );
$orchestrator = new Term_Orchestrator();
term_expect( 'stored', $admin->translate_term_field( $term, 'category', 'ar', 'term_description', 'complete', $health, $orchestrator ), 'approved missing field delegates to orchestrator' );
term_expect( 'taxonomy:category', $orchestrator->row['object_type'], 'orchestrator receives taxonomy identity' );
term_expect( 'invalid_field', $admin->translate_term_field( $term, 'category', 'ar', 'meta:arbitrary', 'complete', $health, $orchestrator )->get_error_code(), 'arbitrary taxonomy meta is rejected before orchestration' );
term_expect( 'stored', $admin->translate_term_field( $term, 'category', 'ar', 'term_name', 'regenerate', $health, $orchestrator ), 'regenerate delegates canonical current field' );
term_expect( 'regenerate', $orchestrator->row['status'], 'regenerate intent is explicit to the shared orchestrator' );

$GLOBALS['logged_in'] = true; $GLOBALS['can_edit'] = true; $GLOBALS['nonce_valid'] = true;
$_POST = array( 'term_id' => 102, 'taxonomy' => 'category', 'language' => 'de', 'nonce' => 'valid' );
$request = new ReflectionMethod( 'Pera_ML_Admin', 'term_ajax_request' ); $request->setAccessible( true );
term_expect( array( $term, 'category', 'de' ), $request->invoke( $admin ), 'valid term request passes' );
term_expect( 'manage_categories', $GLOBALS['checked_capability'], 'taxonomy edit capability is enforced' );
$GLOBALS['nonce_valid'] = false; term_expect( 'invalid_nonce', $request->invoke( $admin )->get_error_code(), 'invalid nonce is rejected' );
$GLOBALS['nonce_valid'] = true; $GLOBALS['can_edit'] = false; term_expect( 'insufficient_capability', $request->invoke( $admin )->get_error_code(), 'missing taxonomy capability is rejected' );
$_POST['taxonomy'] = 'nav_menu'; $GLOBALS['can_edit'] = true; term_expect( 'insufficient_capability', $request->invoke( $admin )->get_error_code(), 'unsupported taxonomy is rejected' );
term_expect( false, false !== strpos( $source, 'wp_update_term(' ), 'term translation never modifies canonical WordPress terms' );
term_expect( true, false !== strpos( $source, "in_array( \$post->post_type, array( 'post', 'property', 'team' ), true )" ), 'existing post/property/team UI support is unchanged' );
echo "Pera ML taxonomy admin translation tests passed\n";
