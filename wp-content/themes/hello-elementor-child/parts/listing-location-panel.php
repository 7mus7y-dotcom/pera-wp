<?php
/** Shared map and nearby amenities for Property and Land listings. */

defined( 'ABSPATH' ) || exit;

$map         = $args['map'] ?? null;
$has_map     = ! empty( $args['has_map'] );
$heading_id  = 'property-location-heading';
$nearby_html = '';
if ( function_exists( 'pera_render_nearby_amenities' ) ) {
	ob_start();
	pera_render_nearby_amenities( $map, $heading_id );
	$nearby_html = trim( ob_get_clean() );
}
if ( ! $has_map && $nearby_html === '' ) {
	return;
}

$map_title = $args['map_title'] ?? pera_ml_ui( 'Location map', 'theme.template.single_property.title.location_map' );
$heading   = $nearby_html === '' ? ( $args['map_only_heading'] ?? '' ) : '';
if ( $heading === '' ) {
	$heading = pera_ml_ui( 'Nearby amenities', 'theme.nearby.heading' );
}
?>
<div class="card-shell property-location__panel" role="region" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<h2 class="inline-row property-location__heading" id="<?php echo esc_attr( $heading_id ); ?>"><svg class="icon" aria-hidden="true" focusable="false"><use href="#icon-map"></use></svg><?php echo esc_html( $heading ); ?></h2>
	<div class="property-location__grid<?php if ( $has_map && $nearby_html !== '' ) echo ' property-location__grid--paired'; ?>">
		<?php if ( $has_map ) : ?>
			<?php
			$address   = (string) ( $map['address'] ?? '' );
			$zoom      = (int) ( $args['zoom'] ?? 15 );
			$gmaps_url = 'https://www.google.com/maps?q=' . rawurlencode( (float) $map['lat'] . ',' . (float) $map['lng'] );
			$embed_url = $gmaps_url . '&z=' . $zoom . '&output=embed';
			?>
			<aside class="property-location__aside">
				<div class="property-map-card">
					<?php if ( $address !== '' ) : ?>
						<p class="property-map-card__address text-soft"><?php echo esc_html( $address ); ?></p>
					<?php endif; ?>
					<div class="media-frame media-frame--image-fill">
						<iframe class="media-embed--map" src="<?php echo esc_url( $embed_url ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="<?php echo esc_attr( $map_title ); ?>"></iframe>
					</div>
					<div class="property-map-card__actions">
						<a class="btn btn--solid btn--green" href="<?php echo esc_url( $gmaps_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( pera_ml_ui( 'Open in Google Maps', 'theme.template.single_property.open_in_google_maps' ) ); ?></a>
					</div>
				</div>
			</aside>
		<?php endif; ?>

		<?php if ( $nearby_html !== '' ) : ?>
			<div class="property-location__nearby"><?php echo $nearby_html; // Trusted markup from the POI renderer. ?></div>
		<?php endif; ?>
	</div>
</div>
