<?php
/** Standalone RTL asset regression tests: php tests/rtl-assets-test.php */
define( 'ABSPATH', __DIR__ );
define( 'PERA_ML_URL', 'https://example.test/pera-multilingual/' );
define( 'PERA_ML_VERSION', 'test' );

$GLOBALS['pera_test_main_enqueued'] = false;
$GLOBALS['pera_test_styles'] = array();
$GLOBALS['pera_test_actions'] = array();

function add_filter() {}
function add_shortcode() {}
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['pera_test_actions'][] = array( $hook, $callback, $priority ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_style_is( $handle, $status ) { return 'pera-main-css' === $handle && 'enqueued' === $status && $GLOBALS['pera_test_main_enqueued']; }
function wp_enqueue_style( $handle, $source, $dependencies, $version ) { $GLOBALS['pera_test_styles'][] = compact( 'handle', 'source', 'dependencies', 'version' ); }
function expect_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\n" ); exit( 1 ); } }
function css_selector_has_declaration( $css, $selector, $declaration ) {
	$css = preg_replace( '!/\\*.*?\\*/!s', '', $css );
	$pattern = '/([^{}]+)\\{([^{}]*)\\}/';
	preg_match_all( $pattern, $css, $rules, PREG_SET_ORDER );
	foreach ( $rules as $rule ) {
		if ( false !== strpos( $rule[1], $selector ) && preg_match( '/(?:^|;)\\s*' . preg_quote( $declaration, '/' ) . '\\s*(?:;|$)/', trim( $rule[2] ) ) ) {
			return true;
		}
	}
	return false;
}
function css_exact_selector_has_declaration( $css, $selector, $declaration ) {
	$css = preg_replace( '!/\\*.*?\\*/!s', '', $css );
	$pattern = '/([^{}]+)\\{([^{}]*)\\}/';
	preg_match_all( $pattern, $css, $rules, PREG_SET_ORDER );
	foreach ( $rules as $rule ) {
		$selectors = array_map( 'trim', explode( ',', $rule[1] ) );
		if ( in_array( $selector, $selectors, true ) && preg_match( '/(?:^|;)\\s*' . preg_quote( $declaration, '/' ) . '\\s*(?:;|$)/', trim( $rule[2] ) ) ) {
			return true;
		}
	}
	return false;
}

class RTLAssetsRouter {
	private $language;
	function __construct( $language = 'ar' ) { $this->language = $language; }
	function current_language() { return $this->language; }
}
class RTLAssetsRegistry {
	function get( $language = 'ar' ) {
		return 'ar' === $language
			? array( 'code' => 'ar', 'direction' => 'rtl' )
			: array( 'code' => $language, 'direction' => 'ltr' );
	}
}

require dirname( __DIR__ ) . '/includes/class-content.php';
$content = new Pera_ML_Content( new RTLAssetsRegistry(), new RTLAssetsRouter(), null );
$content->hooks();
expect_same( 110, $GLOBALS['pera_test_actions'][0][2], 'RTL enqueue runs after theme cleanup' );
expect_same( 'lang="ar" dir="rtl"', $content->language_attributes( '' ), 'Arabic document retains semantic RTL direction' );
expect_same( array( 'base', 'pera-ml-lang-ar', 'pera-ml-rtl' ), $content->body_classes( array( 'base' ) ), 'Arabic body receives RTL content class' );

$english_content = new Pera_ML_Content( new RTLAssetsRegistry(), new RTLAssetsRouter( 'en' ), null );
expect_same( 'lang="en" dir="ltr"', $english_content->language_attributes( '' ), 'non-Arabic document remains LTR' );
expect_same( array( 'base', 'pera-ml-lang-en' ), $english_content->body_classes( array( 'base' ) ), 'non-Arabic body does not receive RTL class' );

$GLOBALS['pera_test_main_enqueued'] = true;
$content->rtl_style();
expect_same( array( 'pera-main-css' ), $GLOBALS['pera_test_styles'][0]['dependencies'], 'RTL CSS follows enqueued theme CSS' );

$GLOBALS['pera_test_main_enqueued'] = false;
$content->rtl_style();
expect_same( array(), $GLOBALS['pera_test_styles'][1]['dependencies'], 'RTL CSS remains loadable without theme CSS' );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/rtl.css' );
$theme_css = file_get_contents( dirname( __DIR__, 3 ) . '/themes/hello-elementor-child/css/main.css' );
expect_same( true, false !== strpos( $css, "body.pera-ml-rtl {\n\tdirection: ltr;" ), 'Arabic body preserves LTR structural geometry' );
expect_same( true, false !== strpos( $css, '.property-hero__title' ), 'property hero text opts into RTL' );
expect_same( true, false !== strpos( $css, '.property-overview__main h2' ), 'property summary text opts into RTL' );
expect_same( true, false !== strpos( $css, '.property-editorial-card h2' ), 'property editorial text opts into RTL' );
expect_same( true, false !== strpos( $css, '.property-further-reading__title' ), 'further-reading text opts into RTL' );
expect_same( true, css_selector_has_declaration( $css, '.article-body', 'direction: rtl' ), 'normal post body text opts into RTL' );
expect_same( true, css_selector_has_declaration( $css, '.hero--post h1', 'direction: rtl' ), 'normal post hero title opts into RTL' );
expect_same( true, css_selector_has_declaration( $css, '.hero--post .post-breadcrumbs__item', 'direction: rtl' ), 'normal post breadcrumbs opt into RTL' );
expect_same( true, css_selector_has_declaration( $css, '.hero--post .article-meta-item', 'direction: rtl' ), 'normal post metadata opts into RTL' );
expect_same( true, css_selector_has_declaration( $css, '.faq-item > summary', 'direction: rtl' ), 'normal post FAQ questions opt into RTL' );
expect_same( true, css_selector_has_declaration( $css, '.post-adjacent-nav__title', 'direction: rtl' ), 'normal post navigation text opts into RTL' );
expect_same( true, css_selector_has_declaration( $css, '.article-sidebar .sidebar-text', 'direction: rtl' ), 'normal post sidebar copy opts into RTL' );
expect_same( true, css_selector_has_declaration( $css, '.article-sidebar .post-card-title', 'direction: rtl' ), 'related post-card text opts into RTL' );
expect_same( true, css_selector_has_declaration( $css, 'pre', 'direction: ltr' ), 'normal post code retains LTR ordering' );
expect_same( false, css_exact_selector_has_declaration( $css, 'body.pera-ml-rtl.single-post .article-layout', 'direction: rtl' ), 'article grid is not assigned RTL' );
expect_same( false, css_exact_selector_has_declaration( $css, 'body.pera-ml-rtl.single-post .article-main', 'direction: rtl' ), 'article column is not assigned RTL' );
expect_same( false, css_exact_selector_has_declaration( $css, 'body.pera-ml-rtl.single-post .article-sidebar', 'direction: rtl' ), 'sidebar column is not assigned RTL' );
expect_same( false, css_exact_selector_has_declaration( $css, 'body.pera-ml-rtl.single-post .slider-track', 'direction: rtl' ), 'slider track is not assigned RTL' );
expect_same( false, false !== strpos( $css, 'body.pera-ml-rtl main' ), 'RTL is not applied to the main structural wrapper' );
expect_same( false, false !== strpos( $css, 'body.pera-ml-rtl .offcanvas-nav {' ), 'redundant drawer geometry override is removed' );
expect_same( true, false !== strpos( $css, '.offcanvas-nav a' ), 'offcanvas translated text opts into RTL' );
expect_same( true, false !== strpos( $theme_css, 'transform: translateX(100%);' ), 'closed offcanvas remains translated fully out of view' );
expect_same( true, false !== strpos( $theme_css, 'body.is-nav-open .offcanvas-nav' ), 'open offcanvas state remains available' );
expect_same( true, false !== strpos( $css, 'overflow-x: clip' ), 'RTL overflow is clipped' );
echo "Pera ML RTL asset tests passed\n";
