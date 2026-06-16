<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards = function_exists( 'pera_latest_offers_collect_homepage_cards' )
	? pera_latest_offers_collect_homepage_cards( 6 )
	: array();

if ( empty( $cards ) ) {
	return;
}

?>
<section class="section section-soft citizenship-latest-offers" id="citizenship-property-offers" aria-label="<?php echo esc_attr__( 'Citizenship property offers in Istanbul', 'hello-elementor-child' ); ?>">
	<div class="container">
		<header class="section-header section-header--center">
			<p class="section-kicker"><?php esc_html_e( 'Citizenship property shortlist', 'hello-elementor-child' ); ?></p>
			<h2><?php esc_html_e( 'Current Istanbul property offers for citizenship buyers', 'hello-elementor-child' ); ?></h2>
			<p>
				<?php esc_html_e( 'A selected sample of Istanbul property offers that may suit buyers applying for Turkish citizenship through real estate investment. We review each option for citizenship suitability, valuation logic, title deed status, location quality and resale potential.', 'hello-elementor-child' ); ?>
			</p>
		</header>

		<div class="cards-slider-shell--nav citizenship-latest-offers__slider-shell">
			<button
				type="button"
				class="cards-slider-nav cards-slider-nav--prev"
				data-slider-target="citizenship-latest-offers-slider"
				aria-label="<?php echo esc_attr__( 'Previous citizenship property offers', 'hello-elementor-child' ); ?>"
			>
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-left' ); ?>"></use>
				</svg>
			</button>

			<div
				class="pera-latest-offers-card-list pera-latest-offers-card-list--citizenship cards-slider cards-slider--snap home-editorial-posts__slider"
				id="citizenship-latest-offers-slider"
				aria-label="<?php echo esc_attr__( 'Citizenship property offers list', 'hello-elementor-child' ); ?>"
			>
				<?php foreach ( $cards as $card ) : ?>
					<?php pera_latest_offers_render_card( $card ); ?>
				<?php endforeach; ?>
			</div>

			<button
				type="button"
				class="cards-slider-nav cards-slider-nav--next"
				data-slider-target="citizenship-latest-offers-slider"
				aria-label="<?php echo esc_attr__( 'Next citizenship property offers', 'hello-elementor-child' ); ?>"
			>
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-right' ); ?>"></use>
				</svg>
			</button>
		</div>

		<div class="section-cta citizenship-latest-offers__cta">
			<a href="<?php echo esc_url( home_url( '/turkish-citizenship-properties/?view=cards' ) ); ?>" class="btn btn--solid btn--blue">
				<?php esc_html_e( 'View all citizenship properties', 'hello-elementor-child' ); ?>
			</a>
			<a href="#citizenship-callback" class="btn btn--solid btn--green">
				<?php esc_html_e( 'Request a private shortlist', 'hello-elementor-child' ); ?>
			</a>
		</div>
	</div>
</section>
