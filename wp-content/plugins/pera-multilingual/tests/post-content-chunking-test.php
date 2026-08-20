<?php
/** Standalone structural post_content chunk tests: php tests/post-content-chunking-test.php */
define( 'ABSPATH', __DIR__ );
function __( $value ) { return $value; }
function get_option( $key, $default = false ) { return $default; }
function do_action() {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function apply_filters( $tag, $value ) {
	if ( 'pera_ml_provider' === $tag ) return $GLOBALS['chunk_test_provider'];
	return $value;
}
class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function expect_chunk( $condition, $label ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL $label\n" ); exit( 1 ); }
}

require dirname( __DIR__ ) . '/includes/providers/interface-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-mock-provider.php';
require dirname( __DIR__ ) . '/includes/class-translator.php';

final class Chunk_Test_Provider implements Pera_ML_Provider_Interface {
	public $calls = array();
	public $maximum_tokens = 0;
	private $language;
	private $damaged = false;
	private $persistent_damage;
	public function __construct( $language, $persistent_damage = false ) { $this->language = $language; $this->persistent_damage = $persistent_damage; }
	public function id() { return 'chunk-test'; }
	public function translate( $source, array $context ) {
		$this->calls[] = array( 'source' => $source, 'instructions' => $context['instructions'] );
		preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $source, $protected_tokens );
		$this->maximum_tokens = max( $this->maximum_tokens, count( $protected_tokens[0] ) );
		$parts = preg_split( '/(PERAMLPROTECTED\d+TOKEN)/', $source, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as &$part ) {
			if ( preg_match( '/^PERAMLPROTECTED\d+TOKEN$/', $part ) ) continue;
			$part = preg_replace( '/[A-Za-z]+(?:[ \t]+[A-Za-z]+)*/', 'zh' === $this->language ? '翻译内容' : 'محتوى مترجم', $part );
		}
		unset( $part );
		$response = implode( '', $parts );
		if ( ( ! $this->damaged || $this->persistent_damage ) && false !== strpos( $source, 'Affected item' ) ) {
			$this->damaged = true;
			// Drop the placeholder immediately before the affected item's text: its opening <li>.
			preg_match( '/(PERAMLPROTECTED\d+TOKEN)Affected item/', $source, $lost_token );
			$response = str_replace( $lost_token[1], '', $response );
		}
		return $response;
	}
}
final class Chunk_Test_Storage {
	public $puts = array();
	public function put() { $this->puts[] = func_get_args(); return true; }
}
function chunk_html_is_balanced( $html ) {
	$stack = array();
	$void = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' );
	preg_match_all( '/<\/?([A-Za-z0-9]+)\b[^>]*>/', $html, $tags );
	foreach ( $tags[0] as $index => $token ) {
		$tag = strtolower( $tags[1][ $index ] );
		if ( in_array( $tag, $void, true ) || preg_match( '/\/\s*>$/', $token ) ) continue;
		if ( '</' === substr( $token, 0, 2 ) ) {
			if ( empty( $stack ) || array_pop( $stack ) !== $tag ) return false;
		} else {
			$stack[] = $tag;
		}
	}
	return empty( $stack );
}

$registry = new class { public function get( $language ) { return array( 'name' => $language, 'source' => false ); } };
$many_siblings = '';
for ( $i = 0; $i < 48; $i++ ) {
	$many_siblings .= '<p>Sibling paragraph ' . $i . ' with <a href="https://example.com/sibling/' . $i . '">linked text</a>.</p>' . "\n";
}
$storage = new Chunk_Test_Storage();
$GLOBALS['chunk_test_provider'] = new Chunk_Test_Provider( 'zh' );
$translator = new Pera_ML_Translator( $registry, $storage );
$siblings_result = $translator->translate_and_store( 'post', 100, 'post_content', 'zh', $many_siblings, 'mock' );
expect_chunk( ! is_wp_error( $siblings_result ), 'many sibling blocks translate successfully' );
expect_chunk( count( $GLOBALS['chunk_test_provider']->calls ) > 1, 'many siblings are split into bounded chunks' );
expect_chunk( count( $GLOBALS['chunk_test_provider']->calls ) <= 6, '48 sibling blocks do not cause approximately 48 calls' );
expect_chunk( $GLOBALS['chunk_test_provider']->maximum_tokens <= 50, 'sibling chunks respect protected-token limit' );
$observed_calls = array( 'siblings' => count( $GLOBALS['chunk_test_provider']->calls ) );
$observed_maximum_tokens = $GLOBALS['chunk_test_provider']->maximum_tokens;

$fixture = "<div class=\"article-wrapper\"><!-- wp:paragraph --><p>Opening <a href=\"https://example.com/path?q=1\">linked paragraph</a> [gallery id=\"7\"] text.</p><!-- /wp:paragraph -->\n";
for ( $i = 0; $i < 45; $i++ ) {
	$fixture .= '<div class="card" data-card="' . $i . '"><h2>Card heading ' . $i . '</h2><p>Card body ' . $i . ' with <a href="https://example.com/card/' . $i . '">anchor text</a>.</p></div>' . "\n";
}
$fixture .= "<ul class=\"affected\"><li>Affected item<ul><li>Nested child</li><li>Second child</li></ul></li><li>Sibling item</li></ul>\n";
$fixture .= '<blockquote>Closing quotation</blockquote><table><tbody><tr><th>Label</th><td>Value</td></tr></tbody></table></div>';
preg_match_all( '/<!--.*?-->|<[^>]+>|\[[A-Za-z][^\]]*\]|https?:\/\/[^\s<"\']+/', $fixture, $original_tokens );
expect_chunk( count( $original_tokens[0] ) > 300, 'fixture contains hundreds of protected tokens' );
$chunk_method = new ReflectionMethod( Pera_ML_Translator::class, 'bounded_structural_chunks' );
$wrapper_plan = $chunk_method->invoke( new Pera_ML_Translator( $registry, new Chunk_Test_Storage() ), $fixture );
foreach ( $wrapper_plan as $piece ) if ( $piece['translate'] ) expect_chunk( chunk_html_is_balanced( $piece['text'] ), 'each oversized-wrapper provider fragment contains balanced HTML' );

foreach ( array( 'zh', 'ar' ) as $language ) {
	$storage = new Chunk_Test_Storage();
	$GLOBALS['chunk_test_provider'] = new Chunk_Test_Provider( $language );
	$translator = new Pera_ML_Translator( $registry, $storage );
	$result = $translator->translate_and_store( 'post', 58969, 'post_content', $language, $fixture, 'mock' );
	expect_chunk( ! is_wp_error( $result ), "$language long translation succeeds" . ( is_wp_error( $result ) ? ': ' . $result->get_error_code() . ' calls=' . count( $GLOBALS['chunk_test_provider']->calls ) : '' ) );
	$calls = $GLOBALS['chunk_test_provider']->calls;
	$affected_calls = array_values( array_filter( $calls, static function ( $call ) { return false !== strpos( $call['source'], 'Affected item' ); } ) );
	expect_chunk( 2 === count( $affected_calls ), "$language retries only affected list chunk" );
	expect_chunk( count( $calls ) === count( array_unique( array_map( static function ( $call ) { return $call['source']; }, $calls ) ) ) + 1, "$language successful chunks called once" );
	expect_chunk( count( $calls ) <= 12, "$language giant wrapper uses a bounded number of calls" );
	expect_chunk( $GLOBALS['chunk_test_provider']->maximum_tokens <= 50, "$language giant wrapper chunks respect protected-token limit" );
	expect_chunk( 0 === count( array_filter( $calls, static function ( $call ) { return false !== strpos( $call['source'], 'article-wrapper' ); } ) ), "$language outer wrapper tags stay outside provider requests" );
	$observed_calls[ $language ] = count( $calls );
	$observed_maximum_tokens = max( $observed_maximum_tokens, $GLOBALS['chunk_test_provider']->maximum_tokens );
	expect_chunk( false !== strpos( $affected_calls[1]['instructions'], 'exactly once' ), "$language retry uses stronger preservation instruction" );
	preg_match_all( '/<!--.*?-->|<[^>]+>/', $fixture, $before_tags );
	preg_match_all( '/<!--.*?-->|<[^>]+>/', $result, $after_tags );
	expect_chunk( $before_tags[0] === $after_tags[0], "$language preserves every tag and comment in order" );
	expect_chunk( false !== strpos( $result, '<ul><li>' ), "$language nested list remains structurally intact" );
	expect_chunk( 1 === count( $storage->puts ), "$language stores final content once" );
}

$large_list = '<ul class="large-list">';
for ( $i = 0; $i < 40; $i++ ) $large_list .= '<li>Large list item ' . $i . '</li>';
$large_list .= '</ul>';
$storage = new Chunk_Test_Storage();
$GLOBALS['chunk_test_provider'] = new Chunk_Test_Provider( 'ar' );
$translator = new Pera_ML_Translator( $registry, $storage );
$large_list_result = $translator->translate_and_store( 'post', 101, 'post_content', 'ar', $large_list, 'mock' );
expect_chunk( ! is_wp_error( $large_list_result ), 'single oversized list translates successfully' );
expect_chunk( count( $GLOBALS['chunk_test_provider']->calls ) > 1, 'single oversized list is split between complete list items' );
expect_chunk( $GLOBALS['chunk_test_provider']->maximum_tokens <= 50, 'oversized list provider calls respect hard token maximum' );
expect_chunk( false === strpos( implode( '', array_map( static function ( $call ) { return $call['source']; }, $GLOBALS['chunk_test_provider']->calls ) ), 'large-list' ), 'list wrapper stays outside provider requests' );
preg_match_all( '/<[^>]+>/', $large_list, $large_list_before_tags );
preg_match_all( '/<[^>]+>/', $large_list_result, $large_list_after_tags );
expect_chunk( $large_list_before_tags[0] === $large_list_after_tags[0], 'oversized list reassembly preserves all tags' );
$list_plan = $chunk_method->invoke( $translator, $large_list );
foreach ( $list_plan as $piece ) if ( $piece['translate'] ) expect_chunk( chunk_html_is_balanced( $piece['text'] ), 'each oversized-list provider fragment contains balanced HTML' );
$observed_maximum_tokens = max( $observed_maximum_tokens, $GLOBALS['chunk_test_provider']->maximum_tokens );

$gutenberg_wrappers = array(
	'group-attributes' => array( "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n", '<div class="wp-block-group">', "</div>\n<!-- /wp:group -->" ),
	'columns' => array( " \n<!-- wp:columns -->\n", '<div class="wp-block-columns">', "</div>\n<!-- /wp:columns -->\n " ),
);
foreach ( $gutenberg_wrappers as $label => $wrapper ) {
	$gutenberg_source = $wrapper[0] . $wrapper[1];
	for ( $i = 0; $i < 20; $i++ ) $gutenberg_source .= '<p>Gutenberg child ' . $i . ' with <a href="https://example.com/gutenberg/' . $i . '">link</a>.</p>';
	$gutenberg_source .= $wrapper[2];
	$storage = new Chunk_Test_Storage();
	$GLOBALS['chunk_test_provider'] = new Chunk_Test_Provider( 'zh' );
	$translator = new Pera_ML_Translator( $registry, $storage );
	$gutenberg_result = $translator->translate_and_store( 'post', 200, 'post_content', 'zh', $gutenberg_source, 'mock' );
	expect_chunk( ! is_wp_error( $gutenberg_result ), "$label oversized Gutenberg wrapper subdivides without error" );
	expect_chunk( 1 === substr_count( $gutenberg_result, trim( $wrapper[0] ) ), "$label opening Gutenberg comment survives exactly once" );
	expect_chunk( 1 === substr_count( $gutenberg_result, trim( $wrapper[2] ) ), "$label closing Gutenberg comment survives exactly once" );
	expect_chunk( substr( $gutenberg_result, 0, strlen( $wrapper[0] ) ) === $wrapper[0], "$label preserves whitespace around opening comment" );
	expect_chunk( substr( $gutenberg_result, -strlen( $wrapper[2] ) ) === $wrapper[2], "$label preserves whitespace around closing comment" );
	expect_chunk( $GLOBALS['chunk_test_provider']->maximum_tokens <= 50, "$label chunks respect protected-token limit" );
	$gutenberg_plan = $chunk_method->invoke( $translator, $gutenberg_source );
	foreach ( $gutenberg_plan as $piece ) if ( $piece['translate'] ) expect_chunk( chunk_html_is_balanced( $piece['text'] ), "$label child provider fragments contain balanced HTML" );
	$observed_maximum_tokens = max( $observed_maximum_tokens, $GLOBALS['chunk_test_provider']->maximum_tokens );
}

$indivisible = '<p>' . str_repeat( '<a href="https://example.com">linked</a>', 30 ) . '</p>';
$storage = new Chunk_Test_Storage();
$GLOBALS['chunk_test_provider'] = new Chunk_Test_Provider( 'zh' );
$translator = new Pera_ML_Translator( $registry, $storage );
$indivisible_result = $translator->translate_and_store( 'post', 102, 'post_content', 'zh', $indivisible, 'mock' );
expect_chunk( is_wp_error( $indivisible_result ) && 'pera_ml_chunk_too_large' === $indivisible_result->get_error_code(), 'indivisible oversized block fails with specific internal error' );
expect_chunk( 0 === count( $GLOBALS['chunk_test_provider']->calls ), 'indivisible oversized block is not sent to provider' );
expect_chunk( 0 === count( $storage->puts ), 'indivisible oversized block is not stored' );

$storage = new Chunk_Test_Storage();
$GLOBALS['chunk_test_provider'] = new Chunk_Test_Provider( 'zh' );
$translator = new Pera_ML_Translator( $registry, $storage );
$simple = $translator->translate_and_store( 'post', 12, 'post_content', 'zh', '<p>Simple post content.</p>', 'mock' );
expect_chunk( '<p>翻译内容.</p>' === $simple, 'simple post content translates normally' );
expect_chunk( 1 === count( $GLOBALS['chunk_test_provider']->calls ), 'simple content needs one provider call' );

$storage = new Chunk_Test_Storage();
$GLOBALS['chunk_test_provider'] = new Chunk_Test_Provider( 'ar', true );
$translator = new Pera_ML_Translator( $registry, $storage );
$failed = $translator->translate_and_store( 'post', 13, 'post_content', 'ar', $fixture, 'mock' );
expect_chunk( is_wp_error( $failed ) && 'pera_ml_structure_changed' === $failed->get_error_code(), 'persistent token loss fails after one chunk retry' );
expect_chunk( 0 === count( $storage->puts ), 'failed reassembly is never stored' );

echo 'Pera ML post_content chunking tests passed (calls: siblings=' . $observed_calls['siblings'] . ', zh=' . $observed_calls['zh'] . ', ar=' . $observed_calls['ar'] . '; max protected tokens/call=' . $observed_maximum_tokens . ")\n";
