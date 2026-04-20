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
<section class="section pera-home-latest-offers" aria-label="<?php echo esc_attr__( 'Latest opportunities in Istanbul', 'hello-elementor-child' ); ?>">
	<div class="container">
		<div class="section-header section-header--center">
			<h2><?php esc_html_e( 'Curated Opportunities in Istanbul', 'hello-elementor-child' ); ?></h2>
			<p><?php esc_html_e( 'Handpicked current offers from selected Istanbul projects.', 'hello-elementor-child' ); ?></p>
		</div>

		<div class="cards-slider-shell--nav">
			<button
				type="button"
				class="cards-slider-nav cards-slider-nav--prev"
				data-slider-target="home-latest-offers-slider"
				aria-label="<?php echo esc_attr__( 'Previous offers', 'hello-elementor-child' ); ?>"
			>
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-left' ); ?>"></use>
				</svg>
			</button>

			<div
				class="pera-latest-offers-card-list pera-latest-offers-card-list--home cards-slider cards-slider--snap home-editorial-posts__slider"
				id="home-latest-offers-slider"
				aria-label="<?php echo esc_attr__( 'Latest offers list', 'hello-elementor-child' ); ?>"
			>
				<?php foreach ( $cards as $card ) : ?>
					<?php pera_latest_offers_render_card( $card ); ?>
				<?php endforeach; ?>
			</div>

			<button
				type="button"
				class="cards-slider-nav cards-slider-nav--next"
				data-slider-target="home-latest-offers-slider"
				aria-label="<?php echo esc_attr__( 'Next offers', 'hello-elementor-child' ); ?>"
			>
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-right' ); ?>"></use>
				</svg>
			</button>
		</div>
	</div>
</section>
