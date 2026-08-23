<?php
/** Focused structural translation tests for HTML-bearing non-post_content fields. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['rich_provider'] = null;
$GLOBALS['rich_errors'] = array();
function __( $value ) { return $value; }
function get_option( $key, $default = false ) { return $default; }
function apply_filters( $tag, $value ) {
	if ( 'pera_ml_provider' === $tag ) return $GLOBALS['rich_provider'];
	if ( 'pera_ml_rich_html_chunk_max_chars' === $tag && isset( $GLOBALS['rich_max_chars'] ) ) return $GLOBALS['rich_max_chars'];
	return $value;
}
function do_action( $tag ) { if ( 'pera_ml_translation_error' === $tag ) $GLOBALS['rich_errors'][] = func_get_args(); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function expect_rich( $condition, $label ) { if ( ! $condition ) { fwrite( STDERR, "FAIL $label\n" ); exit( 1 ); } }

require dirname( __DIR__ ) . '/includes/providers/interface-provider.php';
require dirname( __DIR__ ) . '/includes/providers/class-mock-provider.php';
require dirname( __DIR__ ) . '/includes/class-translator.php';

class Rich_HTML_Provider implements Pera_ML_Provider_Interface {
	public $calls = array();
	public $damage_above = PHP_INT_MAX;
	public $always_damage = false;
	public function id() { return 'rich-html-test'; }
	public function translate( $source, array $context ) {
		$this->calls[] = array( 'source' => $source, 'context' => $context );
		$count = preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $source );
		if ( ( $this->always_damage && $count ) || $count > $this->damage_above ) return preg_replace( '/PERAMLPROTECTED\d+TOKEN/', '', $source, 1 );
		$target = strtoupper( $context['target_language'] );
		return strtr( $source, array(
			'Intro prose with ' => $target . '_INTRO ', 'Second paragraph of ordinary prose.' => $target . '_SECOND.',
			'Transport includes ' => $target . '_TRANSPORT ', 'Elite ' => $target . '_ELITE ',
			'Opening sentence with ' => $target . '_PROTECTED_OPEN ', 'Next sentence contact ' => $target . '_PROTECTED_NEXT ',
			'One long sentence before ' => $target . '_LONG_ONE ', ' Another long sentence before ' => ' ' . $target . '_LONG_TWO ',
			'Plain opening sentence. ' => $target . '_PLAIN_ONE ', 'Plain closing sentence.' => $target . '_PLAIN_TWO.',
			'Next to Zeytinburnu Marmaray Station' => $target . '_STATION', 'One step away from:' => $target . '_PARENT',
			'Diverse cuisines and world-famous cafés and restaurants' => $target . '_CUISINES', 'Seaside promenade' => $target . '_SEASIDE',
			'Theatre and open-air cinema' => $target . '_THEATRE', 'Art galleries' => $target . '_GALLERIES',
			'Organic bazaar' => $target . '_BAZAAR', 'Concert area' => $target . '_CONCERT', 'After the amenities' => $target . '_AFTER',
			'Hospitals &amp; Education' => $target . '_HOSPITALS_EDUCATION', 'Yedikule Armenian Hospital' => $target . '_YEDIKULE',
			'Nearby university' => $target . '_UNIVERSITY',
			'Text with ' => 'نص مع ', 'Feriköy' => 'فيريكوي', 'Architectural Excellence' => 'التميز المعماري',
			'More text with ' => 'نص إضافي مع ', 'important wording' => 'صياغة مهمة', 'Life in Feriköy' => 'الحياة في فيريكوي',
			'Modern apartments near the metro.' => 'شقق حديثة بالقرب من المترو.', 'Plain title' => 'عنوان عادي',
			'Ideal for:' => 'مثالي لـ:', 'Text' => 'نص', 'Buyer one' => 'مشتري واحد', 'Buyer two' => 'مشتري اثنان',
			'Buyer three' => 'مشتري ثلاثة', 'Buyer four' => 'مشتري أربعة', 'Buyer five' => 'مشتري خمسة', 'Buyer six' => 'مشتري ستة',
			'Wellness center, clinic and medical residence,' => 'مركز عافية وعيادة وإقامة طبية،', 'Academic apartments.' => 'شقق أكاديمية.',
		) );
	}
}
final class Rich_HTML_Storage { public $puts = array(); public function put() { $this->puts[] = func_get_args(); } }
$registry = new class { public function get() { return array( 'name' => 'Arabic', 'source' => false, 'instructions' => 'Translate.' ); } };
$run = static function ( $source, $provider = null, $language = 'ar' ) use ( $registry ) {
	$GLOBALS['rich_provider'] = $provider ? $provider : new Rich_HTML_Provider();
	$GLOBALS['rich_errors'] = array();
	$storage = new Rich_HTML_Storage();
	$translator = new Pera_ML_Translator( $registry, $storage );
	$result = $translator->translate_and_store( 'post', 37728, 'meta:about_this_project', $language, $source, 'mock' );
	return array( $result, $GLOBALS['rich_provider'], $storage );
};

$mixed = 'Text with <a href="https://example.com" target="_blank" rel="noopener">Feriköy</a>.\n<h3>Architectural Excellence</h3>\nMore text with <strong>important wording</strong>.';
list( $result, $provider ) = $run( $mixed );
expect_rich( false !== strpos( $result, '<a href="https://example.com" target="_blank" rel="noopener">فيريكوي</a>' ), 'link and attributes are preserved exactly' );
expect_rich( false !== strpos( $result, '<h3>التميز المعماري</h3>' ) && false !== strpos( $result, '<strong>صياغة مهمة</strong>' ), 'heading and strong semantics are preserved' );
expect_rich( 2 === preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $provider->calls[0]['source'] ), 'inline link remains protected with its surrounding prose' );
expect_rich( 3 === count( $provider->calls ), 'free text and the standalone heading are translated as separate structural runs' );

$property = $mixed . '\n<h3>Life in Feriköy</h3><a href="https://example.com/area">Feriköy</a> ' . $mixed;
list( $result, $provider ) = $run( $property );
foreach ( $provider->calls as $call ) expect_rich( preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $call['source'] ) <= 9, 'property-style HTML is split below the generic ten-placeholder request' );
expect_rich( count( $provider->calls ) > 1, 'property-style HTML uses multiple balanced fragments' );

list( $result, $provider ) = $run( '<p>Modern apartments near the metro.</p>' );
expect_rich( '<p>شقق حديثة بالقرب من المترو.</p>' === $result, 'simple paragraph deterministically uses structural translation' );

list( $result, $provider ) = $run( 'Plain title with value < threshold and value > floor' );
expect_rich( 1 === preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $provider->calls[0]['source'] ), 'comparison text remains one generic protected fragment rather than HTML structure' );

$recovering = new Rich_HTML_Provider();
$recovering->damage_above = 3;
list( $result, $provider, $storage ) = $run( $mixed, $recovering );
expect_rich( ! is_wp_error( $result ) && count( $provider->calls ) > 1, 'damaged compound response recovers through smaller structural fragments' );
expect_rich( 1 === count( $storage->puts ), 'only the complete recovered translation is stored' );

$broken = new Rich_HTML_Provider();
$broken->always_damage = true;
list( $result, $provider, $storage ) = $run( '<p>Modern apartments near the metro.</p>', $broken );
expect_rich( '<p>شقق حديثة بالقرب من المترو.</p>' === $result, 'plain paragraph recovers locally after ordinary and strict structural damage' );
expect_rich( 3 === count( $provider->calls ) && false !== strpos( $provider->calls[1]['context']['instructions'], 'exactly once' ), 'plain leaf fallback runs only after the strict retry' );
expect_rich( 'Modern apartments near the metro.' === $provider->calls[2]['source'], 'plain leaf sends only inner text to the provider' );

$sibling_leaves = '<li>Wellness center, clinic and medical residence,</li><li>Academic apartments.</li>';
expect_rich( strlen( '<li>Wellness center, clinic and medical residence,</li>' ) > strlen( $sibling_leaves ) / 2, 'recovery fixture first leaf exceeds the synthetic half-length ceiling' );
$broken_siblings = new Rich_HTML_Provider();
$broken_siblings->always_damage = true;
list( $result, $provider, $storage ) = $run( $sibling_leaves, $broken_siblings );
expect_rich( '<li>مركز عافية وعيادة وإقامة طبية،</li><li>شقق أكاديمية.</li>' === $result, 'damaged sibling leaves are split and reconstructed locally in order' );
$protected_leaf_sources = array_map( static function ( $call ) { return $call['source']; }, $provider->calls );
expect_rich( in_array( 'PERAMLPROTECTED0TOKENWellness center, clinic and medical residence,PERAMLPROTECTED1TOKEN', $protected_leaf_sources, true ), 'long first leaf remains independently translatable during recursive recovery' );
expect_rich( in_array( 'PERAMLPROTECTED0TOKENAcademic apartments.PERAMLPROTECTED1TOKEN', $protected_leaf_sources, true ), 'short second leaf remains independently translatable during recursive recovery' );
expect_rich( in_array( 'Wellness center, clinic and medical residence,', $protected_leaf_sources, true ) && in_array( 'Academic apartments.', $protected_leaf_sources, true ), 'both leaves reach bare-text local recovery after placeholder loss' );
expect_rich( 1 === count( $storage->puts ), 'recovered sibling leaves are stored once' );

final class Rich_HTML_Bare_Text_Provider extends Rich_HTML_Provider {
	private $bare_response;
	public function __construct( $bare_response ) { $this->bare_response = $bare_response; }
	public function translate( $source, array $context ) {
		if ( 'Hello world' !== $source ) return parent::translate( $source, $context );
		$this->calls[] = array( 'source' => $source, 'context' => $context );
		return $this->bare_response;
	}
}

$markup_injecting = new Rich_HTML_Bare_Text_Provider( 'مرحبا</p><script>alert(1)</script>' );
$markup_injecting->always_damage = true;
list( $result, $provider, $storage ) = $run( '<p>Hello world</p>', $markup_injecting );
expect_rich( is_wp_error( $result ) && 'pera_ml_structure_changed' === $result->get_error_code(), 'raw markup introduced into bare translated text is rejected' );
expect_rich( 0 === count( $storage->puts ) && 1 === count( $GLOBALS['rich_errors'] ), 'raw-markup rejection stores no partial translation and emits one final error' );

$escaped_angles = new Rich_HTML_Bare_Text_Provider( 'مرحبا &lt;آمن&gt;' );
$escaped_angles->always_damage = true;
list( $result, $provider, $storage ) = $run( '<p>Hello world</p>', $escaped_angles );
expect_rich( '<p>مرحبا &lt;آمن&gt;</p>' === $result, 'escaped angle-bracket entities remain valid bare translated text' );
expect_rich( 1 === count( $storage->puts ), 'escaped angle-bracket translation is stored once' );

foreach ( array(
	array( '<li>Text</li>', '<li>نص</li>', 'plain list item' ),
	array( '<h3>Ideal for:</h3>', '<h3>مثالي لـ:</h3>', 'heading' ),
	array( '<li class="buyer-type">Text</li>', '<li class="buyer-type">نص</li>', 'opening-tag attributes' ),
	array( '<p>Text with <strong>important wording</strong>.</p>', '<p>نص مع <strong>صياغة مهمة</strong>.</p>', 'existing inline leaf' ),
) as $case ) {
	$damaged = new Rich_HTML_Provider();
	$damaged->always_damage = true;
	list( $result, $provider, $storage ) = $run( $case[0], $damaged );
	expect_rich( $case[1] === $result, $case[2] . ' reconstructs locally and preserves wrappers' );
	expect_rich( 1 === count( $storage->puts ), $case[2] . ' stores the complete result once' );
}

foreach ( array( '<li>Parent<div>Nested block</div></li>', '<li>Malformed <strong>markup</li>' ) as $unsafe ) {
	$damaged = new Rich_HTML_Provider();
	$damaged->always_damage = true;
	list( $result, $provider, $storage ) = $run( $unsafe, $damaged );
	expect_rich( is_wp_error( $result ) && 'pera_ml_structure_changed' === $result->get_error_code(), 'unsafe or malformed leaf remains rejected' );
	expect_rich( 0 === count( $storage->puts ), 'rejected leaf stores nothing' );
}

$plan_method = new ReflectionMethod( 'Pera_ML_Translator', 'top_level_structural_blocks' );
$plan_method->setAccessible( true );
$planning_translator = new Pera_ML_Translator( $registry, new Rich_HTML_Storage() );
$planning_cases = array(
	array( 'Introductory prose.<ul><li>One</li><li>Two</li></ul>', array( 'Introductory prose.', '<ul><li>One</li><li>Two</li></ul>' ), 'prose and EOF list' ),
	array( 'Introductory prose.<ul><li>One</li><li>Two</li></ul>Closing prose.', array( 'Introductory prose.', '<ul><li>One</li><li>Two</li></ul>', 'Closing prose.' ), 'prose, list, and closing prose' ),
	array( 'Intro text<h3>Heading</h3>More text<ul><li>One</li></ul>More text', array( 'Intro text', '<h3>Heading</h3>', 'More text', '<ul><li>One</li></ul>', 'More text' ), 'heading and list boundaries' ),
	array( 'Apartments near <strong>Taksim</strong> are popular.', array( 'Apartments near <strong>Taksim</strong> are popular.' ), 'inline strong' ),
	array( 'Visit <a href="https://example.com">Feriköy</a> for more information.', array( 'Visit <a href="https://example.com">Feriköy</a> for more information.' ), 'inline link' ),
);
foreach ( $planning_cases as $case ) {
	$blocks = $plan_method->invoke( $planning_translator, $case[0] );
	expect_rich( $case[1] === $blocks, $case[2] . ' produces the expected top-level fragments' );
	expect_rich( $case[0] === implode( '', $blocks ), $case[2] . ' preserves every source byte in order' );
}

$distances = 'Transportation is easy in Topkapi. Metro, bus, and road connections are close by. The E5 highway is directly accessible from the compound.<ul>';
foreach ( array( 'YTU University 2 mins', 'Sultanahmet 9 mins', 'Metro station 10 mins', 'City centre 12 mins', 'Airport 30 mins', 'Hospital 6 mins', 'Shopping centre 4 mins' ) as $distance ) $distances .= '<li>' . $distance . '</li>';
$distances .= '</ul>';
$about = 'This project combines homes, offices, and social spaces and creates:<ul class="checklist"><li>Business and the world of work,</li><li>Comfortable family life,</li><li>Green communal areas,</li><li>Convenient city access.</li></ul>The architectural setup and design was carried out for modern urban living. Apartments include <strong>important wording</strong> for buyers. Final project text.';
foreach ( array( array( $distances, 'property 42524 distances-style EOF list' ), array( $about, 'property 42524 about-this-project-style checklist' ) ) as $regression ) {
	list( $result, $provider, $storage ) = $run( $regression[0] );
	expect_rich( ! is_wp_error( $result ), $regression[1] . ' does not return a chunk-too-large error' );
	expect_rich( 1 === count( $storage->puts ), $regression[1] . ' is stored after complete translation' );
	expect_rich( 1 === substr_count( $result, '<ul' ) && 1 === substr_count( $result, '</ul>' ), $regression[1] . ' emits one balanced list' );
}

$compound = '<h3>Ideal for:</h3><ul><li>Buyer one</li><li>Buyer two</li><li>Buyer three</li><li>Buyer four</li><li>Buyer five</li><li>Buyer six</li></ul>';
$GLOBALS['rich_max_chars'] = 25;
$damaged = new Rich_HTML_Provider();
$damaged->always_damage = true;
list( $result, $provider, $storage ) = $run( $compound, $damaged );
expect_rich( ! is_wp_error( $result ) && 6 === substr_count( $result, '<li>') && 6 === substr_count( $result, '</li>' ), 'compound list subdivides and recovers all plain leaves' );
expect_rich( strpos( $result, 'مشتري واحد' ) < strpos( $result, 'مشتري ستة' ), 'compound list item order is unchanged' );
expect_rich( 1 === count( $storage->puts ), 'complete compound recovery is stored once' );

final class Rich_HTML_Leaf_Error_Provider extends Rich_HTML_Provider {
	public function translate( $source, array $context ) {
		if ( 'Buyer three' === $source ) return new WP_Error( 'provider_failure' );
		return parent::translate( $source, $context );
	}
}
$leaf_error = new Rich_HTML_Leaf_Error_Provider();
$leaf_error->always_damage = true;
list( $result, $provider, $storage ) = $run( $compound, $leaf_error );
expect_rich( is_wp_error( $result ) && 'provider_failure' === $result->get_error_code(), 'local leaf provider error propagates' );
expect_rich( 0 === count( $storage->puts ) && 1 === count( $GLOBALS['rich_errors'] ), 'leaf failure stores no partial HTML and emits only the final error action' );
unset( $GLOBALS['rich_max_chars'] );

$nested_distances = '<ul class="distances"><li>Next to Zeytinburnu Marmaray Station</li><li class="nearby">One step away from: <ul data-kind="amenities"><li>Diverse cuisines and world-famous cafés and restaurants</li><li>Seaside promenade</li><li>Theatre and open-air cinema</li><li>Art galleries</li><li>Organic bazaar</li><li>Concert area</li></ul> After the amenities</li></ul>';
$nested_distances .= "\n\n<ul class=\"institutions\"><li><strong>Hospitals &amp; Education</strong></li><li>Yedikule Armenian Hospital</li><li>Nearby university</li></ul>";
$GLOBALS['rich_max_chars'] = 120;
foreach ( array( 'zh', 'ar', 'de' ) as $language ) {
	list( $result, $provider, $storage ) = $run( $nested_distances, null, $language );
	$target = strtoupper( $language );
	expect_rich( ! is_wp_error( $result ), "$language nested distances field does not return chunk-too-large" );
	expect_rich( 3 === substr_count( $result, '<ul' ) && 3 === substr_count( $result, '</ul>' ), "$language emits both outer lists and the nested list exactly once" );
	expect_rich( 11 === substr_count( $result, '<li' ) && 11 === substr_count( $result, '</li>' ), "$language preserves every list item" );
	expect_rich( false !== strpos( $result, '<li class="nearby">' . $target . '_PARENT <ul data-kind="amenities">' ), "$language translates parent prose while preserving its wrapper, attributes, and whitespace" );
	expect_rich( false !== strpos( $result, '</ul> ' . $target . '_AFTER</li>' ), "$language translates prose after the nested list inside the parent item" );
	expect_rich( strpos( $result, $target . '_CUISINES' ) < strpos( $result, $target . '_CONCERT' ) && strpos( $result, $target . '_CONCERT' ) < strpos( $result, $target . '_HOSPITALS_EDUCATION' ), "$language translates nested children and preserves consecutive-list ordering" );
	expect_rich( 1 === substr_count( $result, '<li><strong>' . $target . '_HOSPITALS_EDUCATION</strong></li>' ), "$language translates the inline-rich list item while preserving strong markup" );
	$inline_rich_calls = array_filter( $provider->calls, static function ( $call ) { return false !== strpos( $call['source'], 'Hospitals &amp; Education' ) && preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $call['source'] ) >= 4; } );
	expect_rich( 1 === count( $inline_rich_calls ), "$language inline-rich list item reaches the protected rich-HTML provider path" );
	foreach ( array( 'One step away from:', 'Diverse cuisines', 'Seaside promenade', 'Concert area', 'After the amenities', 'Hospitals &amp; Education', 'Yedikule Armenian Hospital', 'Nearby university' ) as $english ) expect_rich( false === strpos( $result, $english ), "$language output does not echo representative source text: $english" );
	expect_rich( 1 === count( $storage->puts ), "$language stores the complete nested-list translation once" );
}

$GLOBALS['rich_max_chars'] = 30;
list( $result ) = $run( '<ul><li>Parent prose<ul><li>First child</ul><li>Second child</li></ul></li></ul>' );
expect_rich( is_wp_error( $result ) && 'pera_ml_chunk_too_large' === $result->get_error_code(), 'malformed oversized nested list fails safely' );
unset( $GLOBALS['rich_max_chars'] );

$property_29124 = "Intro prose with <a href=\"https://example.com/z\">Zeytinburnu</a>.\n\nSecond paragraph of ordinary prose.\n\nTransport includes <a href=\"https://example.com/m\">metro</a>, <a href=\"https://example.com/r\">high-speed rail</a>, and <a href=\"https://example.com/b\">Bosphorus road tunnel</a>.\n\nElite <a href=\"https://example.com/a\">Ataköy Marina</a> near <a href=\"https://example.com/t\">Taksim</a>, <a href=\"https://example.com/s\">Sultanahmet</a>, and <a href=\"https://example.com/n\">Nişantaşı</a>.";
foreach ( array( 'zh', 'ar', 'de' ) as $language ) {
	list( $result, $provider, $storage ) = $run( $property_29124, null, $language );
	expect_rich( ! is_wp_error( $result ) && count( $provider->calls ) > 1, "$language property 29124 inline prose subdivides into multiple calls" );
	foreach ( $provider->calls as $call ) expect_rich( preg_match_all( '/PERAMLPROTECTED\d+TOKEN/', $call['source'] ) <= 9, "$language property 29124 call stays within the token ceiling" );
	expect_rich( 8 === substr_count( $result, '<a ' ) && 8 === substr_count( $result, '</a>' ), "$language preserves every complete link wrapper" );
	foreach ( array( 'z', 'm', 'r', 'b', 'a', 't', 's', 'n' ) as $slug ) expect_rich( false !== strpos( $result, 'href="https://example.com/' . $slug . '"' ), "$language preserves link attribute $slug exactly" );
	expect_rich( 3 === substr_count( $result, "\n\n" ) && strpos( $result, strtoupper( $language ) . '_INTRO' ) < strpos( $result, strtoupper( $language ) . '_SECOND' ) && strpos( $result, strtoupper( $language ) . '_SECOND' ) < strpos( $result, strtoupper( $language ) . '_TRANSPORT' ), "$language preserves paragraph spacing, order, and translates prose" );
	expect_rich( 1 === count( $storage->puts ), "$language stores the complete property 29124 field once" );
}

$protected_prose = "Opening sentence with [gallery id=\"29124\"] and <strong>important wording</strong>.\nNext sentence contact team@example.com or https://example.com/path?q=1.";
$GLOBALS['rich_max_chars'] = 90;
$chunk_method = new ReflectionMethod( 'Pera_ML_Translator', 'build_structural_chunk_plan' );
$chunk_method->setAccessible( true );
$protected_plan = $chunk_method->invoke( $planning_translator, $protected_prose, 90, 9 );
expect_rich( ! is_wp_error( $protected_plan ) && 2 === count( $protected_plan ), 'protected prose is planned as two safe newline-delimited fragments' );
expect_rich( 1 === substr_count( $protected_plan[0]['text'], '[gallery id="29124"]' ) && false === strpos( $protected_plan[1]['text'], '[gallery id="29124"]' ), 'shortcode remains wholly inside one planned fragment' );
$protected_first = $planning_translator->protect( $protected_plan[0]['text'] );
$protected_second = $planning_translator->protect( $protected_plan[1]['text'] );
expect_rich( in_array( '[gallery id="29124"]', $protected_first['tokens'], true ), 'shortcode is captured intact by existing protection rules' );
expect_rich( in_array( 'team@example.com', $protected_second['tokens'], true ) && in_array( 'https://example.com/path?q=1.', $protected_second['tokens'], true ), 'email and bare URL are captured intact by existing protection rules' );
list( $result, $provider, $storage ) = $run( $protected_prose );
$protected_call_sources = array_map( static function ( $call ) { return $call['source']; }, $provider->calls );
expect_rich( in_array( $protected_first['text'], $protected_call_sources, true ) && in_array( $protected_second['text'], $protected_call_sources, true ), 'each complete protected construct reaches its provider call as one intact placeholder' );
expect_rich( false !== strpos( $result, '[gallery id="29124"]' ) && false !== strpos( $result, 'team@example.com' ) && false !== strpos( $result, 'https://example.com/path?q=1.' ), 'shortcode, email, and URL restore exactly after textual-run subdivision' );
expect_rich( false !== strpos( $result, 'AR_PROTECTED_OPEN' ) && false !== strpos( $result, 'AR_PROTECTED_NEXT' ) && 1 === count( $storage->puts ), 'protected prose fragments translate and the restored result is stored once' );
unset( $GLOBALS['rich_max_chars'] );

$GLOBALS['rich_max_chars'] = 100;
list( $result ) = $run( 'One long sentence before <a href="https://example.com/exact">linked words</a>. Another long sentence before <strong>important wording</strong>.' );
expect_rich( ! is_wp_error( $result ) && false !== strpos( $result, '<a href="https://example.com/exact">linked words</a>' ) && false !== strpos( $result, '<strong>صياغة مهمة</strong>' ), 'single paragraph splits at a sentence boundary without detaching inline pairs' );
$GLOBALS['rich_max_chars'] = 25;
list( $result ) = $run( 'Plain opening sentence. Plain closing sentence.' );
expect_rich( ! is_wp_error( $result ) && false !== strpos( $result, 'AR_PLAIN_ONE' ) && false !== strpos( $result, 'AR_PLAIN_TWO' ), 'oversized plain prose can use textual-run subdivision' );
list( $result ) = $run( '<a href="https://example.com/unsplittable">This entire linked value is longer than the configured character ceiling and has no safe outside boundary.</a>' );
expect_rich( is_wp_error( $result ) && 'pera_ml_chunk_too_large' === $result->get_error_code(), 'an oversized inline construct with no safe boundary still fails safely' );
unset( $GLOBALS['rich_max_chars'] );

echo "Pera ML rich HTML meta tests passed\n";
