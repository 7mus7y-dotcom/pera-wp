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

function expect_display_price( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/inc/v2-units-index.php';

$single = pera_property_display_price( 450000, 450000, 'single' );
$single_html = pera_property_display_price_html( $single );
expect_display_price( '$450,000' === $single['text'], 'inactive-plugin fallback is canonical USD' );
expect_display_price( false !== strpos( $single_html, 'data-pera-money' ), 'money contract marker exists' );
expect_display_price( false !== strpos( $single_html, 'data-usd-min="450000"' ), 'canonical minimum is exposed' );
expect_display_price( false !== strpos( $single_html, 'data-price-mode="single"' ), 'single semantic is exposed' );
expect_display_price( false === strpos( $single_html, 'data-usd-max' ), 'single omits maximum' );
expect_display_price( false !== strpos( $single_html, '<bdi' ) && false !== strpos( $single_html, 'dir="ltr"' ), 'money is bidi isolated' );

$range = pera_property_display_price( 450000, 600000, 'range' );
$range_html = pera_property_display_price_html( $range );
expect_display_price( '$450,000–$600,000' === $range['text'], 'range fallback remains USD' );
expect_display_price( false !== strpos( $range_html, 'data-usd-max="600000"' ), 'range exposes canonical maximum' );
expect_display_price( false !== strpos( $range_html, 'data-price-mode="range"' ), 'range semantic is exposed' );

$from = pera_property_display_price( 450000, 600000, 'from' );
$from_html = pera_property_display_price_html( $from );
expect_display_price( '$450,000' === $from['text'], 'from node contains money only' );
expect_display_price( false === strpos( $from_html, 'From' ), 'translated From prose remains outside money node' );
expect_display_price( false === strpos( $from_html, 'EUR' ) && false === strpos( $from_html, 'GBP' ), 'SSR embeds no selected currency' );

echo "Property display-price tests passed\n";
