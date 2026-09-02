<?php
/** Standalone static/rendering checks for the Phase 3 selector. */

function selector_expect( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function pera_ml_ui( $source ) { return $source; }
function get_stylesheet_directory_uri() { return 'https://example.test/theme'; }

$theme         = dirname( __DIR__ );
$helper_source = file_get_contents( $theme . '/inc/theme-helpers.php' );
$header_source = file_get_contents( $theme . '/header.php' );
$js_source     = file_get_contents( $theme . '/js/currency-selector.js' );
$start         = strpos( $helper_source, 'function pera_render_currency_selector' );
$function      = substr( $helper_source, $start );

selector_expect( false !== $start, 'currency renderer exists' );
selector_expect( false !== strpos( $header_source, "pera_render_header_language_switcher( 'desktop' ); ?>\n\n      <?php pera_render_currency_selector( 'header' )" ), 'desktop order is language then currency' );
selector_expect( false !== strpos( $header_source, "pera_render_header_language_switcher( 'mobile' ); ?>\n\n    <?php pera_render_currency_selector( 'offcanvas' )" ), 'off-canvas order is language then currency then navigation' );

eval( '?><?php ' . $function );
ob_start();
pera_render_currency_selector( 'header' );
selector_expect( '' === ob_get_clean(), 'plugin-off selector is absent without a fatal' );

eval( 'final class Pera_Currency_Assets {}' );
eval( 'function pera_currency_get_supported() { return array("USD" => array(), "EUR" => array(), "GBP" => array()); }' );

foreach ( array( 'header', 'offcanvas' ) as $context ) {
	ob_start();
	pera_render_currency_selector( $context );
	$html = ob_get_clean();
	selector_expect( 3 === substr_count( $html, 'data-pera-currency-option=' ), "{$context}: exactly three options render" );
	selector_expect( false !== strpos( $html, '>USD<' ) && false !== strpos( $html, '>EUR<' ) && false !== strpos( $html, '>GBP<' ), "{$context}: USD, EUR, and GBP render" );
	selector_expect( false === strpos( $html, '>TRY<' ), "{$context}: unsupported option is absent" );
	selector_expect( false !== strpos( $html, 'data-pera-currency-option="USD" aria-selected="true" class="is-active"' ), "{$context}: neutral SSR state is USD" );
}

selector_expect( false !== strpos( $js_source, 'api().setSelected(' ), 'selection delegates to PeraCurrency.setSelected' );
selector_expect( false !== strpos( $js_source, "window.addEventListener('pera:currency-change', sync)" ), 'all instances synchronize on the shared event' );
selector_expect( false !== strpos( $js_source, "event.key === 'Escape'" ), 'Escape closes the disclosure' );
selector_expect( false === strpos( $js_source, 'localStorage') && false === strpos( $js_source, 'location.reload') && false === strpos( $js_source, 'location.href'), 'selector creates no persistence, reload, or navigation state' );

echo "Currency selector tests passed\n";
