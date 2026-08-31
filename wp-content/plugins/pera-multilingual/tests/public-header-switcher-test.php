<?php
/** Focused standalone regression tests for the two public header switchers. */

define( 'ABSPATH', __DIR__ );

function public_switcher_expect( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function home_url( $path = '/' ) { return 'https://example.test/' . ltrim( $path, '/' ); }
function pera_ml_ui( $source ) { return $source; }
function wp_unslash( $value ) { return $value; }

final class Public_Switcher_Test_Registry {
	public function enabled() {
		return array(
			'en' => array( 'native_name' => 'English', 'compact_name' => 'EN', 'hreflang' => 'en' ),
			'zh' => array( 'native_name' => '中文', 'compact_name' => '中文', 'hreflang' => 'zh-CN' ),
			'ar' => array( 'native_name' => 'العربية', 'compact_name' => 'AR', 'hreflang' => 'ar' ),
			'de' => array( 'native_name' => 'Deutsch', 'compact_name' => 'DE', 'hreflang' => 'de-DE' ),
		);
	}
}

final class Public_Switcher_Test_Router {
	public function current_language() { return 'zh'; }
	public function url_for_language( $url, $code ) {
		$path = preg_replace( '#^/(?:zh|ar|de)(?=/|$)#', '', (string) parse_url( $url, PHP_URL_PATH ) );
		return 'https://example.test/' . ( 'en' === $code ? '' : $code . '/' ) . ltrim( $path, '/' );
	}
}

final class Pera_ML_Plugin {
	private static $instance;
	public static function instance() {
		if ( ! self::$instance ) self::$instance = new self();
		return self::$instance;
	}
	public function registry() { return new Public_Switcher_Test_Registry(); }
	public function router() { return new Public_Switcher_Test_Router(); }
}

$theme          = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child';
$helper_source  = file_get_contents( $theme . '/inc/theme-helpers.php' );
$header_source  = file_get_contents( $theme . '/header.php' );
$function_start = strpos( $helper_source, 'function pera_render_header_language_switcher' );
$function_source = substr( $helper_source, $function_start );

public_switcher_expect( false !== $function_start, 'shared header switcher renderer exists' );
public_switcher_expect( 2 === substr_count( $header_source, 'pera_render_header_language_switcher(' ), 'exactly two frontend switcher locations remain' );
public_switcher_expect( false !== strpos( $header_source, "pera_render_header_language_switcher( 'desktop' )" ), 'desktop header switcher uses the shared renderer' );
public_switcher_expect( false !== strpos( $header_source, "pera_render_header_language_switcher( 'mobile' )" ), 'mobile off-canvas switcher uses the shared renderer' );
public_switcher_expect( false === strpos( $function_source, 'is_user_logged_in' ), 'shared frontend renderer has no login gate' );
public_switcher_expect( false === strpos( $function_source, 'current_user_can' ), 'shared frontend renderer has no capability gate' );
public_switcher_expect( false === strpos( $function_source, 'manage_options' ), 'shared frontend renderer has no administrator requirement' );

eval( '?><?php ' . $function_source );
$_SERVER['REQUEST_URI'] = '/zh/current-property/';

foreach ( array( 'logged-out visitor', 'logged-in normal visitor', 'administrator' ) as $visitor ) {
	foreach ( array( 'desktop', 'mobile' ) as $context ) {
		ob_start();
		pera_render_header_language_switcher( $context );
		$html = ob_get_clean();
		public_switcher_expect( false !== strpos( $html, 'header-language-switcher--' . $context ), "{$visitor}: {$context} switcher renders" );
		public_switcher_expect( 4 === substr_count( $html, '<a' ), "{$visitor}: enabled language list remains English, Chinese, Arabic, and German" );
		public_switcher_expect( false !== strpos( $html, 'href="https://example.test/de/current-property/"' ), "{$visitor}: links use the route-preserving router" );
		public_switcher_expect( false !== strpos( $html, 'lang="zh"' ) && false !== strpos( $html, 'aria-current="page"' ), "{$visitor}: current language remains selected" );
	}
}

echo "Pera ML public header switcher tests passed\n";
