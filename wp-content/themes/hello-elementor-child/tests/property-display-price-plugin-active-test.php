<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', __DIR__ );

function add_action() {}
function number_format_i18n( $number ) { return number_format( $number ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function pera_currency_format_range( $min, $max, $currency ) {
	if ( 'USD' !== $currency ) {
		fwrite( STDERR, "FAIL SSR formatter requested visitor currency\n" );
		exit( 1 );
	}
	return array(
		'min' => '$' . number_format( $min ),
		'max' => null === $max ? '' : '$' . number_format( $max ),
		'valid' => true,
	);
}

final class Pera_Currency_Assets {
	public static $enqueue_count = 0;
	public static function enqueue() { self::$enqueue_count++; }
}

require_once dirname( __DIR__ ) . '/inc/v2-units-index.php';

$price = pera_property_display_price( 450000, 600000, 'range' );
$html = pera_property_display_price_html( $price );
pera_property_display_price( 700000, 900000, 'range' );
pera_property_display_price_enqueue_assets();

if ( 1 !== Pera_Currency_Assets::$enqueue_count
	|| '$450,000–$600,000' !== $price['text']
	|| false === strpos( $html, 'data-usd-min="450000"' )
	|| false === strpos( $html, 'data-usd-max="600000"' )
	|| false === strpos( $html, 'data-price-mode="range"' ) ) {
	fwrite( STDERR, "FAIL active-plugin USD fallback or once-per-request enqueue contract\n" );
	exit( 1 );
}

echo "Property display-price active-plugin tests passed\n";
