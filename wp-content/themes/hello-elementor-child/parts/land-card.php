<?php
/**
 * Land values in the shared property-card layout.
 */

defined( 'ABSPATH' ) || exit;

$post_id  = get_the_ID();
$title    = get_the_title();
$url      = get_permalink();
$image_id = get_post_thumbnail_id( $post_id );
$district = pera_get_deepest_term( $post_id, 'district' );
$area     = (float) pera_land_text_field( $post_id, 'land_plot_area_sqm' );
$price    = pera_land_public_price( $post_id );
$price_label = $price
	? $price['currency'] . ' ' . pera_land_format_number( $price['amount'] )
	: pera_ml_ui( 'Price on request', 'theme.template.single_land.price_on_request' );
$under_offer = 'under_offer' === pera_land_text_field( $post_id, 'land_availability' );
$planning_label = pera_land_planning_is_verified( $post_id )
	? pera_ml_ui( 'Planning verified', 'theme.template.single_land.planning_verified' )
	: pera_ml_ui( 'Planning status pending verification', 'theme.template.single_land.planning_pending' );
?>
<article <?php post_class( 'property-card property-card--archive' ); ?>>
	<div class="property-card__inner pera-card-shell">
		<div class="property-card__media">
			<?php if ( $under_offer ) : ?>
				<div class="property-card__badge">
					<span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Under offer', 'theme.template.single_land.under_offer' ) ); ?></span>
				</div>
			<?php endif; ?>
			<a href="<?php echo esc_url( $url ); ?>" class="property-card__media-link" aria-label="<?php echo esc_attr( $title ); ?>">
				<?php if ( $image_id ) : ?>
					<?php echo wp_get_attachment_image( $image_id, 'pera-card', false, array( 'decoding' => 'async' ) ); ?>
				<?php else : ?>
					<span class="property-card__media-placeholder" aria-hidden="true">
						<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/logo-white.svg' ); ?>" alt="" loading="lazy" class="property-card__media-placeholder-logo">
					</span>
				<?php endif; ?>
			</a>
		</div>

		<div class="property-card__body">
			<?php if ( $district ) : ?>
				<div class="property-card__location"><span class="pill pill--subtle"><?php echo esc_html( pera_ml_term( $district ) ); ?></span></div>
			<?php endif; ?>
			<h3 class="property-card__title">
				<a href="<?php echo esc_url( $url ); ?>" class="property-card__title-link"><?php echo esc_html( $title ); ?></a>
			</h3>
			<p class="property-card__excerpt"><?php echo esc_html( $planning_label ); ?></p>
		</div>

		<div class="property-card__footer">
			<div class="property-card__footer-row property-card__footer-row--top">
				<span class="property-card__price"><?php echo esc_html( $price_label ); ?></span>
				<?php if ( $area > 0 ) : ?>
					<span class="property-card__size property-card__meta-sm"><?php echo esc_html( pera_land_format_number( $area ) . ' m²' ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $price && $area > 0 ) : ?>
				<div class="property-card__footer-row property-card__footer-row--bottom">
					<span class="property-card__meta-sm"><?php echo esc_html( $price['currency'] . ' ' . pera_land_format_number( round( $price['amount'] / $area, 2 ) ) . ' / m²' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	</div>
</article>
