<?php
/** Standalone SEO FAQ translation tests: php tests/faq-translation-test.php */
define( 'ABSPATH', __DIR__ );

$faq_test_provider = null;
function apply_filters( $tag, $value ) { global $faq_test_provider; return 'pera_ml_provider' === $tag && $faq_test_provider ? $faq_test_provider : $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_:-]/', '', strtolower( $value ) ); }
function __( $value ) { return $value; }
function get_option( $key, $default = null ) { return $default; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function do_action() {}
class WP_Error { private $code; public function __construct( $code ) { $this->code = $code; } public function get_error_code() { return $this->code; } }
function expect_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }

require dirname( __DIR__ ) . '/includes/providers/interface-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-mock-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-openai-provider.php';
require dirname( __DIR__ ) . '/includes/class-vocabulary.php';
require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translator.php';

final class FAQ_Test_Provider implements Pera_ML_Provider_Interface {
	public $html_mode = false;
	public function id() { return 'faq-test'; }
	public function translate( $source, array $context ) {
		if ( $this->html_mode ) {
			$source = str_replace( array( 'First', 'Second' ), array( '第一项', '第二项' ), $source );
			return str_replace( array( 'PERAMLPROTECTED1TOKEN', 'PERAMLPROTECTED3TOKEN' ), array( "\xE2\x80\x83 PERAMLPROTECTED1TOKEN", "\xC2\xA0\xE2\x80\x83 PERAMLPROTECTED3TOKEN" ), $source );
		}
		$prefix = 'zh' === $context['target_language'] ? '中' : 'ع';
		return $prefix . $source;
	}
}
final class FAQ_Test_Storage {
	public $rows = array();
	public function put( $type, $id, $field, $language, $source, $translation ) { $this->rows[ "$type|$id|$field|$language" ] = array( 'source' => $source, 'translated_text' => $translation ); return true; }
	public function get( $type, $id, $field, $language, $source = '' ) { $key = "$type|$id|$field|$language"; return isset( $this->rows[ $key ] ) ? $this->rows[ $key ] : null; }
}
final class FAQ_Test_Router { public $language = 'zh'; public function current_language() { return $this->language; } }

$faq_test_provider = new FAQ_Test_Provider();
$registry = new class { public function get( $language ) { return in_array( $language, array( 'zh', 'ar' ), true ) ? array( 'name' => $language, 'source' => false ) : null; } };
$storage = new FAQ_Test_Storage();
$translator = new Pera_ML_Translator( $registry, $storage );
$source = 'Minimum investment? | It is $400,000.' . "\n" . 'When can I apply? | Apply today.' . "\n" . 'This malformed row';

$zh = $translator->translate_and_store( 'post', 77, 'meta:seo_faq_v2', 'zh', $source, 'test' );
$ar = $translator->translate_and_store( 'post', 77, 'meta:seo_faq_v2', 'ar', $source, 'test' );
$zh_lines = explode( "\n", $zh );
expect_same( 3, count( $zh_lines ), 'two FAQ entries and malformed row retain their order' );
expect_same( 1, substr_count( $zh_lines[0], '|' ), 'first FAQ has exactly one separator' );
expect_same( 1, substr_count( $zh_lines[1], '|' ), 'second FAQ has exactly one separator' );
expect_same( true, strpos( $zh_lines[0], '$400,000' ) !== false, 'protected currency amount remains exact' );
expect_same( 'This malformed row', $zh_lines[2], 'malformed line safely remains English' );
expect_same( true, strpos( $zh_lines[0], '中Minimum investment?' ) === 0, 'FAQ order starts with first Chinese question' );

$router = new FAQ_Test_Router();
$fields = new Pera_ML_Fields( $router, $storage, new Pera_ML_Vocabulary() );
expect_same( true, in_array( 'seo_faq_v2', $fields->approved(), true ), 'FAQ field is in the approved post-meta inventory' );
expect_same( $zh, $fields->get( 77, 'seo_faq_v2', $source ), 'Chinese FAQ retrieval' );
$router->language = 'ar';
expect_same( $ar, $fields->get( 77, 'seo_faq_v2', $source ), 'Arabic FAQ retrieval' );
expect_same( $source, $fields->get( 77, 'seo_faq_v2', $source, 'en' ), 'English request returns canonical FAQ' );
expect_same( 'Fallback English', $fields->get( 78, 'seo_faq_v2', 'Fallback English', 'zh' ), 'missing translation falls back to English' );

$theme_dir = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child/';
$schema_source = file_get_contents( $theme_dir . 'inc/schema.php' );
expect_same( true, strpos( $schema_source, "pera_ml_field( \$post_id, 'seo_faq_v2', \$post_faq_value )" ) !== false, 'FAQPage raw-meta fallback uses multilingual field retrieval' );
expect_same( true, strpos( $schema_source, 'pera_get_single_post_faq_v2_items( $post_id )' ) !== false, 'FAQPage schema receives parsed post SEO FAQ rows' );
expect_same( true, strpos( $schema_source, 'pera_render_faq_schema(' ) !== false, 'FAQPage renderer receives post SEO FAQ rows' );
$template_source = file_get_contents( $theme_dir . 'single-post.php' );
expect_same( true, strpos( $template_source, "pera_ml_field( get_the_ID(), 'seo_faq_v2', \$post_faq_value )" ) !== false, 'visible FAQ raw-meta fallback uses multilingual field retrieval' );
expect_same( true, strpos( $template_source, 'pera_render_faq_html( $post_faq_items' ) !== false, 'visible FAQ renderer receives translated post SEO FAQ rows' );

$faq_test_provider->html_mode = true;
$html_source = '<ul><li>First</li><li>Second</li></ul>';
$clean_html = $translator->translate_and_store( 'post', 77, 'post_content', 'zh', $html_source, 'test' );
expect_same( '<ul><li>第一项</li><li>第二项</li></ul>', $clean_html, 'Unicode list indentation is removed after token restoration' );
expect_same( $clean_html, $storage->rows['post|77|post_content|zh']['translated_text'], 'clean list HTML is stored' );
expect_same( 0, preg_match( '/<ul>[\s\x{00A0}\x{2000}-\x{200B}\x{202F}\x{3000}]+<li>/u', $clean_html ), 'opening list has no whitespace-only node' );
expect_same( 0, preg_match( '/<\/li>[\s\x{00A0}\x{2000}-\x{200B}\x{202F}\x{3000}]+<li>/u', $clean_html ), 'list items have no whitespace-only node' );

echo "Pera ML SEO FAQ translation tests passed\n";
