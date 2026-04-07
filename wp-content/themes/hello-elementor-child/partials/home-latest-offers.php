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

if ( function_exists( 'pera_latest_offers_enqueue_card_styles' ) ) {
	pera_latest_offers_enqueue_card_styles();
}
?>
<section class="section pera-home-latest-offers" aria-label="<?php echo esc_attr__( 'Latest offers', 'hello-elementor-child' ); ?>">
	<div class="container">
		<div class="section-header section-header--center">
			<h2><?php esc_html_e( 'Latest offers', 'hello-elementor-child' ); ?></h2>
		</div>

		<div class="pera-latest-offers-card-list pera-latest-offers-card-list--home" aria-label="<?php echo esc_attr__( 'Latest offers list', 'hello-elementor-child' ); ?>">
			<?php foreach ( $cards as $card ) : ?>
				<?php pera_latest_offers_render_card( $card ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
