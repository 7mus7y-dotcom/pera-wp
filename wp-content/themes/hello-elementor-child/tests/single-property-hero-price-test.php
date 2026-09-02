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

function expect_hero_price( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$theme = dirname( __DIR__ );
require_once $theme . '/inc/v2-units-index.php';

$mode_for = function( bool $is_project, bool $is_resale, int $min, int $max ): string {
	return ( $is_project && ! $is_resale )
		? 'from'
		: ( $max > 0 && $max !== $min ? 'range' : 'single' );
};

$range_mode = $mode_for( false, true, 450000, 600000 );
$range_html = pera_property_display_price_html(
	pera_property_display_price( 450000, 600000, $range_mode )
);
expect_hero_price( false !== strpos( $range_html, 'data-price-mode="range"' ), 'resale hero uses range mode for distinct bounds' );
expect_hero_price( false !== strpos( $range_html, 'data-usd-min="450000"' ), 'resale hero range exposes canonical minimum' );
expect_hero_price( false !== strpos( $range_html, 'data-usd-max="600000"' ), 'resale hero range exposes canonical maximum' );

$single_mode = $mode_for( false, true, 450000, 450000 );
$single_html = pera_property_display_price_html(
	pera_property_display_price( 450000, 450000, $single_mode )
);
expect_hero_price( false !== strpos( $single_html, 'data-price-mode="single"' ), 'equal resale hero bounds use single mode' );
expect_hero_price( false === strpos( $single_html, 'data-usd-max' ), 'single resale hero omits maximum attribute' );

$from_mode = $mode_for( true, false, 450000, 600000 );
$from_html = pera_property_display_price_html(
	pera_property_display_price( 450000, 600000, $from_mode )
);
expect_hero_price( false !== strpos( $from_html, 'data-price-mode="from"' ), 'project hero retains from mode' );
expect_hero_price( false === strpos( $from_html, 'data-usd-max' ), 'project hero money node remains minimum-only' );

$single_template = file_get_contents( $theme . '/single-property.php' );
expect_hero_price(
	false !== strpos( $single_template, '$hero_price_max > 0 && $hero_price_max !== $hero_price_min' ),
	'single-property template selects range mode from canonical distinct bounds'
);

echo "Single-property hero price tests passed\n";
