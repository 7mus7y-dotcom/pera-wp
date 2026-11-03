<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', __DIR__ );

$sold_out_fixture = array();

function get_field( $name, $post_id ) {
	global $sold_out_fixture;
	return 'sold_out' === $name && $post_id > 0 ? $sold_out_fixture : null;
}

function expect_sold_status( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$theme = dirname( __DIR__ );
require_once $theme . '/inc/property-card-helpers.php';

$sold_out_fixture = array( 'Sold out' );
expect_sold_status( pera_property_is_sold_out( 42 ), 'selected ACF checkbox is sold' );

$sold_out_fixture = array();
expect_sold_status( ! pera_property_is_sold_out( 42 ), 'empty ACF checkbox is available' );

$sold_out_fixture = array( 'Unexpected value' );
expect_sold_status( ! pera_property_is_sold_out( 42 ), 'unrelated checkbox value is not sold' );
expect_sold_status( ! pera_property_is_sold_out( 0 ), 'invalid post ID is not sold' );

$card   = file_get_contents( $theme . '/parts/property-card-v2.php' );
$single = file_get_contents( $theme . '/single-property.php' );

expect_sold_status( false !== strpos( $card, "pera_ml_ui( 'Sold!', 'theme.property.sold' )" ), 'shared property card renders translated Sold pill' );
expect_sold_status( false !== strpos( $card, 'pill pill--red pill--sm' ), 'card uses existing red small pill styling' );
expect_sold_status( false !== strpos( $single, "pera_ml_ui( 'Sold!', 'theme.property.sold' )" ), 'single-property hero renders translated Sold pill' );
expect_sold_status( false !== strpos( $single, 'pill pill--red' ), 'single hero uses existing red pill styling' );

echo "Property sold-status tests passed\n";
