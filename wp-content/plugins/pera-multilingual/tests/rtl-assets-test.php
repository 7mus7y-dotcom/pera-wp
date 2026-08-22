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
function wp_style_is( $handle, $status ) { return 'pera-main-css' === $handle && 'enqueued' === $status && $GLOBALS['pera_test_main_enqueued']; }
function wp_enqueue_style( $handle, $source, $dependencies, $version ) { $GLOBALS['pera_test_styles'][] = compact( 'handle', 'source', 'dependencies', 'version' ); }
function expect_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label\n" ); exit( 1 ); } }

class RTLAssetsRouter { function current_language() { return 'ar'; } }
class RTLAssetsRegistry { function get() { return array( 'direction' => 'rtl' ); } }

require dirname( __DIR__ ) . '/includes/class-content.php';
$content = new Pera_ML_Content( new RTLAssetsRegistry(), new RTLAssetsRouter(), null );
$content->hooks();
expect_same( 110, $GLOBALS['pera_test_actions'][0][2], 'RTL enqueue runs after theme cleanup' );

$GLOBALS['pera_test_main_enqueued'] = true;
$content->rtl_style();
expect_same( array( 'pera-main-css' ), $GLOBALS['pera_test_styles'][0]['dependencies'], 'RTL CSS follows enqueued theme CSS' );

$GLOBALS['pera_test_main_enqueued'] = false;
$content->rtl_style();
expect_same( array(), $GLOBALS['pera_test_styles'][1]['dependencies'], 'RTL CSS remains loadable without theme CSS' );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/rtl.css' );
expect_same( true, false !== strpos( $css, 'body.pera-ml-rtl .offcanvas-nav' ), 'drawer override is RTL-scoped' );
expect_same( true, false !== strpos( $css, 'transform: translateX(-100%)' ), 'drawer hides to physical left' );
expect_same( true, false !== strpos( $css, 'overflow-x: clip' ), 'RTL overflow is clipped' );
echo "Pera ML RTL asset tests passed\n";
