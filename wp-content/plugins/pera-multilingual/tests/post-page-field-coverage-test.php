<?php
/** Focused regressions for normal post/page visitor-facing meta coverage. */
define( 'ABSPATH', __DIR__ );

class WP_Post {
	public $ID; public $post_type; public $post_title = ''; public $post_content = ''; public $post_excerpt = '';
	public function __construct( $id, $post_type ) { $this->ID = $id; $this->post_type = $post_type; }
}
function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function get_post_type( $id ) { return 10 === (int) $id ? 'post' : 'page'; }
function get_post( $id ) { return isset( $GLOBALS['coverage_posts'][ $id ] ) ? $GLOBALS['coverage_posts'][ $id ] : null; }
function get_post_meta( $id, $key ) { return isset( $GLOBALS['coverage_meta'][ $id ][ $key ] ) ? $GLOBALS['coverage_meta'][ $id ][ $key ] : ''; }
function coverage_expect( $expected, $actual, $label ) {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}

final class Coverage_Router { public $language = 'zh'; public function current_language() { return $this->language; } }
final class Coverage_Storage {
	public function get( $type, $id, $field, $language, $source ) {
		if ( 10 === $id && 'meta:post_subtitle' === $field && 'zh' === $language && 'Canonical subtitle' === $source ) return array( 'translated_text' => '翻译副标题' );
		return null; // Missing and stale rows are rejected by the real storage read path.
	}
}

require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translation-status.php';

$router = new Coverage_Router();
$fields = new Pera_ML_Fields( $router, new Coverage_Storage(), null );
coverage_expect( true, in_array( 'post_subtitle', $fields->approved( 'post' ), true ), 'post subtitle remains in the post contract' );
coverage_expect( '翻译副标题', $fields->get( 10, 'post_subtitle', 'Canonical subtitle' ), 'post subtitle uses its translated display value' );
coverage_expect( 'Canonical subtitle', $fields->get( 10, 'post_subtitle', 'Canonical subtitle', 'en' ), 'English post subtitle stays canonical' );
coverage_expect( 'Changed canonical subtitle', $fields->get( 10, 'post_subtitle', 'Changed canonical subtitle', 'zh' ), 'missing or stale post subtitle falls back to canonical source' );

$homepage_fields = array( 'homepage_hero_subtext', 'homepage_listing_intro', 'homepage_bottom_seo_text' );
foreach ( $homepage_fields as $field ) coverage_expect( true, in_array( $field, $fields->approved( 'page' ), true ), "{$field} is in the page contract" );
foreach ( array( 'faq', 'name', 'position', 'description' ) as $field ) coverage_expect( false, in_array( $field, $fields->approved( 'page' ), true ), "structured or team field {$field} is not in the page contract" );

$GLOBALS['coverage_posts'] = array( 20 => new WP_Post( 20, 'page' ) );
$GLOBALS['coverage_meta'] = array( 20 => array(
	'homepage_hero_subtext'      => 'Canonical hero copy',
	'homepage_listing_intro'     => '<p>Canonical listing copy</p>',
	'homepage_bottom_seo_text'   => '<p>Canonical SEO copy</p>',
	'faq'                        => array( array( 'question' => 'Structured question' ) ),
) );
$status_sources = ( new Pera_ML_Translation_Status( null ) )->applicable_sources( 20, 'page' );
foreach ( $homepage_fields as $field ) coverage_expect( true, isset( $status_sources[ 'meta:' . $field ] ), "Translation Health discovers {$field} from the page contract" );
coverage_expect( false, isset( $status_sources['meta:faq'] ), 'Translation Health does not treat the FAQ repeater as scalar meta' );

$theme = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child';
$post_card = file_get_contents( $theme . '/parts/post-card.php' );
coverage_expect( true, false !== strpos( $post_card, "pera_ml_field( \$post_id, 'post_subtitle', \$post_subtitle )" ), 'post card routes subtitle display through the field helper' );

echo "Pera ML post/page field coverage tests passed\n";
