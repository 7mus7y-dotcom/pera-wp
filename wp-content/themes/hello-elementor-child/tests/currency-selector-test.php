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
$css_source    = file_get_contents( $theme . '/css/main.css' );
$plugin_source = file_get_contents( dirname( dirname( $theme ) ) . '/plugins/pera-currency/pera-currency.php' );
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
	selector_expect( false !== strpos( $html, 'data-pera-currency-option="USD" aria-current="true" class="is-active"' ), "{$context}: neutral SSR state is USD" );
	selector_expect( false === strpos( $html, 'role="listbox"' ) && false === strpos( $html, 'role="option"' ) && false === strpos( $html, 'aria-selected' ), "{$context}: selector uses disclosure button semantics" );
	selector_expect( false === strpos( $html, '<svg' ) && false === strpos( $html, 'icon-currency' ), "{$context}: currency icon is absent" );
	selector_expect( false === strpos( $html, '(selected)' ) && false === strpos( $html, 'data-pera-currency-selected-text' ) && false === strpos( $html, 'data-pera-currency-selected-label' ), "{$context}: selected label plumbing is absent" );
}

selector_expect( false !== strpos( $js_source, 'api().setSelected(' ), 'selection delegates to PeraCurrency.setSelected' );
selector_expect( false !== strpos( $js_source, "window.addEventListener('pera:currency-change', sync)" ), 'all instances synchronize on the shared event' );
selector_expect( false !== strpos( $js_source, "event.key === 'Escape'" ), 'Escape closes the disclosure' );
selector_expect( false !== strpos( $js_source, "option.setAttribute('aria-current', 'true')" ) && false !== strpos( $js_source, "option.removeAttribute('aria-current')" ), 'selection synchronizes aria-current' );
selector_expect( false === strpos( $js_source, 'aria-selected' ), 'JavaScript does not advertise listbox selection semantics' );
selector_expect( false === strpos( $js_source, 'currency-selected-text' ) && false === strpos( $js_source, 'currency-selected-label' ), 'JavaScript has no selected label plumbing' );
selector_expect( false === strpos( $js_source, 'localStorage') && false === strpos( $js_source, 'location.reload') && false === strpos( $js_source, 'location.href'), 'selector creates no persistence, reload, or navigation state' );
selector_expect( false !== strpos( $css_source, '.pera-currency-selector__list button.is-active { font-weight: 700; text-decoration: underline;' ), 'active option remains bold and underlined' );
selector_expect( false === strpos( $css_source, '.pera-currency-selector__icon' ), 'currency icon CSS is absent' );
selector_expect( false === strpos( $css_source, '.pera-currency-selector__code,' ), 'mobile CSS does not hide the currency code' );
selector_expect( false === strpos( $css_source, 'min-width: 30px; width: 30px;' ), 'mobile CSS has no icon-only fixed trigger' );
selector_expect( false !== strpos( $plugin_source, 'Version: 1.0.2' ) && false !== strpos( $plugin_source, "PERA_CURRENCY_VERSION', '1.0.2'" ), 'plugin header and asset version are 1.0.2' );

echo "Currency selector tests passed\n";
