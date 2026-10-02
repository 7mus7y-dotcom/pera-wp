<?php
/** Citizenship template UI strings share one discoverable multilingual FAQ source. */
define( 'ABSPATH', __DIR__ );

$GLOBALS['citizenship_test_language'] = 'en';
$GLOBALS['citizenship_ui_calls'] = array();
function add_action() {}
function add_filter() {}
function pera_ml_ui( $source, $key ) {
	$GLOBALS['citizenship_ui_calls'][] = array( $source, $key );
	return 'en' === $GLOBALS['citizenship_test_language'] ? $source : '[' . $GLOBALS['citizenship_test_language'] . '] ' . $source;
}
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wpautop( $value ) { return '<p>' . $value . '</p>'; }
function wp_kses_post( $value ) { return $value; }
function citizenship_expect( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$theme_dir = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child/';
require $theme_dir . 'inc/seo-all.php';

$english = pera_seo_all_citizenship_faq_items();
citizenship_expect( 14, count( $english ), 'canonical citizenship FAQ row count is preserved' );
citizenship_expect( 'Q: Can I buy multiple properties to qualify?', $english[0]['question'], 'English question remains canonical' );
citizenship_expect( true, 0 === strpos( $english[0]['answer'], 'Yes. You can combine multiple eligible properties' ), 'English answer remains canonical' );

$GLOBALS['citizenship_test_language'] = 'test';
$GLOBALS['citizenship_ui_calls'] = array();
$translated = pera_seo_all_citizenship_faq_items();
citizenship_expect( '[test] Q: Can I buy multiple properties to qualify?', $translated[0]['question'], 'FAQ question uses the UI translation layer' );
citizenship_expect( true, 0 === strpos( $translated[0]['answer'], '[test] Yes. You can combine multiple eligible properties' ), 'FAQ answer uses the UI translation layer' );
citizenship_expect( 28, count( $GLOBALS['citizenship_ui_calls'] ), 'every FAQ question and answer is translated once' );

$keys = array_column( $GLOBALS['citizenship_ui_calls'], 1 );
citizenship_expect( 28, count( array_unique( $keys ) ), 'FAQ semantic keys are unique' );
foreach ( $keys as $key ) {
	citizenship_expect( true, 0 === strpos( $key, 'theme.template.page_citizenship.faq_' ), 'FAQ key uses the generic citizenship template namespace' );
	citizenship_expect( false, false !== strpos( $key, '.ru' ), 'FAQ key is not language-specific' );
}

ob_start();
require $theme_dir . 'partials/faq-citizenship.php';
$visible_faq = ob_get_clean();
citizenship_expect( true, false !== strpos( $visible_faq, esc_html( $translated[0]['question'] ) ), 'visible accordion consumes the translated canonical FAQ items' );
citizenship_expect( true, false !== strpos( $visible_faq, $translated[0]['answer'] ), 'visible accordion uses the translated canonical answer' );

$seo_source = file_get_contents( $theme_dir . 'inc/seo-all.php' );
$partial_source = file_get_contents( $theme_dir . 'partials/faq-citizenship.php' );
$page_source = file_get_contents( $theme_dir . 'page-citizenship.php' );
citizenship_expect( true, substr_count( $seo_source, '$faq_items = pera_seo_all_citizenship_faq_items();' ) >= 1, 'FAQ schema consumes the canonical translated FAQ function' );
citizenship_expect( true, false !== strpos( $partial_source, '? pera_seo_all_citizenship_faq_items()' ), 'visible FAQ consumes the same canonical translated FAQ function' );
citizenship_expect( true, false !== strpos( $page_source, "pera_ml_ui( 'Chat on WhatsApp', 'theme.template.page_citizenship.chat_on_whatsapp' )" ), 'final WhatsApp CTA uses a literal discoverable UI registration' );
citizenship_expect( 0, preg_match( '/[\x{0400}-\x{04FF}]/u', $seo_source . $page_source ), 'citizenship implementation contains no Russian-specific copy' );

echo "Pera ML citizenship template multilingual tests passed\n";
