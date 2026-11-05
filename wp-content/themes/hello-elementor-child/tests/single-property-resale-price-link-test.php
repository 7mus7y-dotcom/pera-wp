<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

function expect_resale_price_link_label( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$template = file_get_contents( dirname( __DIR__ ) . '/single-property.php' );

expect_resale_price_link_label( false !== strpos( $template, "\$hero_price_link_label = \$is_resale" ), 'hero price link label branches on the existing resale flag' );
expect_resale_price_link_label( false !== strpos( $template, "pera_ml_ui( 'See all options', 'theme.template.single_property.see_all_options' )" ), 'resale listings use the multilingual See all options label' );
expect_resale_price_link_label( false !== strpos( $template, "pera_ml_ui( 'See all prices', 'theme.template.single_property.see_all_prices' )" ), 'non-resale listings retain the multilingual See all prices label' );
expect_resale_price_link_label( false !== strpos( $template, 'href="#property-price-range"' ), 'price link keeps its existing target' );

echo "Single-property resale price-link tests passed\n";
