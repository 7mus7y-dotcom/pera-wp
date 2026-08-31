<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

/** Focused regressions for pass 1 of the multilingual property-template remediation. */

function expect_property_ml( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$theme       = dirname( __DIR__ );
$units       = file_get_contents( $theme . '/inc/v2-units-index.php' );
$enquiry     = file_get_contents( $theme . '/parts/enquiry-form.php' );
$single      = file_get_contents( $theme . '/single-property.php' );
$archive     = file_get_contents( $theme . '/archive-property.php' );
$ajax        = file_get_contents( $theme . '/inc/ajax-property-archive.php' );
$related     = file_get_contents( $theme . '/parts/related-taxonomy-card.php' );
$card        = file_get_contents( $theme . '/parts/property-card-v2.php' );
$favourites  = file_get_contents( $theme . '/js/favourites.js' );

$price_ui = array(
	"pera_ml_ui( 'Price range', 'theme.property_price_range.title' )",
	"pera_ml_ui( 'Pricing', 'theme.property_price_range.resale_title' )",
	"pera_ml_ui( 'Indicative prices by unit type. Availability may change. Contact us for specific pricing and floor plans.', 'theme.property_price_range.indicative_prices_disclaimer' )",
	"pera_ml_ui( 'Final pricing is subject to negotiation with the seller and contract.', 'theme.property_price_range.resale_final_pricing_disclaimer' )",
	"pera_ml_ui( 'Indicative prices by unit type. Availability may change. Contact us for specific pricing and floor plans. Final pricing subject to negotiation with the developer', 'theme.property_price_range.project_final_pricing_disclaimer' )",
);
foreach ( $price_ui as $call ) {
	expect_property_ml( false !== strpos( $units, $call ), 'price copy uses its semantic Pera ML identity' );
}
expect_property_ml( false !== strpos( $units, 'esc_html( $pricing_title )' ), 'translated price title retains safe output' );
expect_property_ml( false !== strpos( $units, 'esc_html( $pricing_subtitle )' ), 'translated price disclaimer retains safe output' );

expect_property_ml( false !== strpos( $enquiry, "pera_ml_ui( 'I agree for Pera Property to contact me regarding this enquiry and to process my personal data in accordance with the %s.', 'theme.enquiry_form.consent_with_privacy_policy' )" ), 'consent is a complete translated format' );
expect_property_ml( false !== strpos( $enquiry, "pera_ml_ui( 'Privacy Policy', 'theme.enquiry_form.privacy_policy' )" ), 'privacy label uses Pera ML' );
expect_property_ml( false !== strpos( $enquiry, "pera_ml_url( home_url( '/privacy-policy/' ) )" ), 'privacy URL preserves translated routing' );
expect_property_ml( false !== strpos( $enquiry, 'wp_kses( $consent_html' ), 'translated consent HTML is allowlist sanitized' );
expect_property_ml( false !== strpos( $single, "'submit_label'   => pera_ml_ui( 'Request details', 'theme.template.single_property.request_details' )" ), 'property submit override uses Pera ML' );

$count_identity = "pera_ml_ui( '%d properties found', 'theme.template.archive_property.properties_found' )";
expect_property_ml( false !== strpos( $archive, $count_identity ), 'SSR count uses canonical identity' );
expect_property_ml( false !== strpos( $ajax, $count_identity ), 'AJAX count reuses SSR identity' );
expect_property_ml( false === strpos( $ajax, "\$found . ' properties found'" ), 'AJAX has no raw English count concatenation' );

foreach ( array( 'Related districts', 'Related regions', 'Related tags' ) as $heading ) {
	expect_property_ml( false !== strpos( $archive, "pera_ml_ui( '{$heading}'" ), "{$heading} uses Pera ML" );
}
foreach ( array( 'View district', 'View region', 'View tag' ) as $action ) {
	expect_property_ml( false !== strpos( $related, "pera_ml_ui( '{$action}'" ), "{$action} has a complete semantic identity" );
}
expect_property_ml( false === strpos( $related, "pera_ml_ui( 'View %s'" ), 'related action does not translate an interpolated noun fragment' );

foreach ( array( $single, $card ) as $button_template ) {
	expect_property_ml( false !== strpos( $button_template, 'data-label-add=' ), 'favourite button supplies translated add label' );
	expect_property_ml( false !== strpos( $button_template, 'data-label-remove=' ), 'favourite button supplies translated remove label' );
}
expect_property_ml( false !== strpos( $favourites, 'button.dataset.labelRemove' ), 'favourites JS consumes supplied remove label' );
expect_property_ml( false !== strpos( $favourites, 'button.dataset.labelAdd' ), 'favourites JS consumes supplied add label' );
expect_property_ml( false === strpos( $favourites, "'Remove from favourites'" ), 'favourites JS has no hard-coded remove label' );
expect_property_ml( false === strpos( $favourites, "'Add to favourites'" ), 'favourites JS has no hard-coded add label' );
expect_property_ml( false !== strpos( $ajax, 'pera_render_property_card(' ), 'AJAX cards use the labelled shared card renderer' );

expect_property_ml( false === strpos( $single, '<summary>Q:' ), 'FAQ presentation prefix is removed' );

echo "Multilingual property-template pass 1 tests passed\n";
