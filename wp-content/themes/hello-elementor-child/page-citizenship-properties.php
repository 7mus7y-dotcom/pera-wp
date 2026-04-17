<?php
/**
 * Template Name: Turkish Citizenship Properties
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$cards = function_exists( 'pera_latest_offers_collect_cards' )
	? pera_latest_offers_collect_cards(
		12,
		120,
		array(
			'tax_query' => array(
				array(
					'taxonomy' => 'specials',
					'field'    => 'slug',
					'terms'    => array( 'citizenship' ),
				),
			),
		)
	)
	: array();

if ( function_exists( 'pera_latest_offers_enqueue_card_styles' ) ) {
	pera_latest_offers_enqueue_card_styles();
}
?>

<main id="primary" class="site-main">
	<section class="section pera-citizenship-properties">
		<div class="container">
			<header class="section-header section-header--center">
				<h1><?php the_title(); ?></h1>
				<p><?php esc_html_e( 'Browse latest property offers tagged for Turkish citizenship eligibility.', 'hello-elementor-child' ); ?></p>
			</header>

			<?php if ( ! empty( $cards ) ) : ?>
				<div class="pera-latest-offers-card-list">
					<?php foreach ( $cards as $card ) : ?>
						<?php pera_latest_offers_render_card( $card ); ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No citizenship-tagged property offers are available right now. Please check back soon.', 'hello-elementor-child' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
