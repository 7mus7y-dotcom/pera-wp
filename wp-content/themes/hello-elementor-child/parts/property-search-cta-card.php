<?php
/**
 * Property archive personalised-search CTA card.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$consultancy_url = home_url( '/book-a-consultancy/' );
if ( function_exists( 'pera_ml_url' ) ) {
	$consultancy_url = pera_ml_url( $consultancy_url );
}

$whatsapp_url = pera_get_whatsapp_url(
	pera_ml_ui(
		'Hello Pera Property, I would like help finding the right Istanbul property.',
		'theme.property_archive.search_cta.whatsapp_message'
	)
);
$heading_id = 'property-search-cta-heading';
?>
<aside class="property-search-cta pera-card-shell" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<div class="property-search-cta__inner">
		<div class="property-search-cta__content">
			<span class="pill pill--subtle property-search-cta__pill"><?php echo esc_html( pera_ml_ui( 'Personal property search', 'theme.property_archive.search_cta.pill' ) ); ?></span>
			<h2 id="<?php echo esc_attr( $heading_id ); ?>" class="property-search-cta__heading"><?php echo esc_html( pera_ml_ui( 'Can’t find what you’re looking for?', 'theme.property_archive.search_cta.heading' ) ); ?></h2>
			<p><?php echo esc_html( pera_ml_ui( 'Not every suitable property appears online. Tell us what you need and our Istanbul team will prepare a personalised shortlist.', 'theme.property_archive.search_cta.body' ) ); ?></p>
		</div>
		<div class="property-search-cta__actions">
			<a class="btn btn--solid btn--blue" href="<?php echo esc_url( $consultancy_url ); ?>" data-track-channel="consultancy" data-track-intent="high" data-track-source="partial" data-track-context="property_archive_search_cta"><?php echo esc_html( pera_ml_ui( 'Tell us what you’re looking for', 'theme.property_archive.search_cta.consultancy_button' ) ); ?></a>
			<a class="btn btn--ghost btn--green" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" data-whatsapp="1" data-whatsapp-type="service_cta" data-track-channel="whatsapp" data-track-intent="high" data-track-source="partial" data-track-context="property_archive_search_cta" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click"><?php echo esc_html( pera_ml_ui( 'Chat on WhatsApp', 'theme.property_archive.search_cta.whatsapp_button' ) ); ?></a>
		</div>
	</div>
</aside>
