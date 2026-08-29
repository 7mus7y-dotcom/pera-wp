<?php
/** Focused homepage FAQ, footer and logo multilingual regressions. */
define( 'ABSPATH', __DIR__ );
function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function get_post_type() { return 'page'; }
function get_post_meta() { return array(); }
function frontend_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
final class Frontend_Router { public function current_language() { return 'zh'; } }
final class Frontend_Storage {
	public function get( $type, $id, $field, $language, $source ) {
		$rows = array(
			'meta:homepage_faq_0_question' => array( 'translated_text' => '翻译问题', 'is_stale' => false, 'status' => 'current' ),
			'meta:homepage_faq_0_answer' => array( 'translated_text' => '<p>翻译答案</p>', 'is_stale' => false, 'status' => 'current' ),
			'meta:homepage_faq_1_question' => array( 'translated_text' => '旧问题', 'is_stale' => true, 'status' => 'current' ),
			'meta:homepage_faq_1_answer' => array( 'translated_text' => '旧答案', 'is_stale' => false, 'status' => 'stale' ),
		);
		return isset( $rows[ $field ] ) ? $rows[ $field ] : null;
	}
}
require dirname( __DIR__ ) . '/includes/class-fields.php';
$fields = new Pera_ML_Fields( new Frontend_Router(), new Frontend_Storage(), null );
$canonical = array(
	array( 'question' => 'English question', 'answer' => '<p>English answer</p>' ),
	array( 'question' => 'Changed English question', 'answer' => 'English stale-status answer' ),
	array( 'question' => 'Missing English question', 'answer' => 'Missing English answer' ),
);
$translated = $fields->homepage_faq( 20, $canonical, 'zh' );
frontend_expect( '翻译问题', $translated[0]['question'], 'translated FAQ question' );
frontend_expect( '<p>翻译答案</p>', $translated[0]['answer'], 'translated FAQ answer' );
frontend_expect( 'Changed English question', $translated[1]['question'], 'changed English source rejects old hash translation' );
frontend_expect( 'English stale-status answer', $translated[1]['answer'], 'non-current translation falls back' );
frontend_expect( $canonical[2], $translated[2], 'missing translations fall back leaf-by-leaf' );
frontend_expect( $canonical, $fields->homepage_faq( 20, $canonical, 'en' ), 'English FAQ remains canonical' );
frontend_expect( false, in_array( 'faq', $fields->approved( 'page' ), true ), 'FAQ remains outside scalar page contract' );

$theme = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child';
$header = file_get_contents( $theme . '/header.php' );
$helper = file_get_contents( $theme . '/inc/theme-helpers.php' );
$footer = file_get_contents( $theme . '/footer.php' );
frontend_expect( true, false !== strpos( $header, "'home_url'   => function_exists( 'pera_ml_home_url' )" ), 'desktop logo explicitly uses multilingual home' );
frontend_expect( true, false !== strpos( $helper, "function_exists( 'pera_ml_home_url' ) ? pera_ml_home_url()" ), 'mobile logo default uses multilingual home' );
frontend_expect( 0, preg_match_all( '/href="https:\/\/www\\.peraproperty\\.com\/(?:about-us|join-our-team|contact-us|sell-your|rent-your|privacy-policy)/', $footer ), 'footer has no canonical hardcoded internal links' );
frontend_expect( true, substr_count( $footer, 'pera_ml_ui(' ) >= 30, 'footer visitor copy remains routed through UI discovery calls' );
echo "Pera ML homepage FAQ/footer/logo tests passed\n";
