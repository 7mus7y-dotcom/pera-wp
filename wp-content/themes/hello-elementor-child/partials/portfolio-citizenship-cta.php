<?php
/**
 * Portfolio token citizenship guidance panel.
 *
 * @var string $advisor_contact_url Optional internal advisor/contact URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advisor_contact_url = isset( $advisor_contact_url ) ? trim( (string) $advisor_contact_url ) : '';
$guide_url           = home_url( '/citizenship-by-investment/' );
?>
<section class="section section-soft portfolio-citizenship-guidance" aria-label="<?php echo esc_attr( pera_ml_ui( 'Turkish Citizenship Guidance', 'theme.portfolio_citizenship.section_label' ) ); ?>">
	<div class="container">
		<div class="portfolio-citizenship-guidance__panel">
			<div class="portfolio-citizenship-guidance__main">
				<p class="portfolio-citizenship-guidance__kicker"><?php echo esc_html( pera_ml_ui( 'Turkish Citizenship Guidance', 'theme.portfolio_citizenship.kicker' ) ); ?></p>
				<h2 class="portfolio-citizenship-guidance__title"><?php echo esc_html( pera_ml_ui( 'See how this portfolio can support your citizenship route', 'theme.portfolio_citizenship.heading' ) ); ?></h2>
				<p class="portfolio-citizenship-guidance__text"><?php echo esc_html( pera_ml_ui( 'Review these shortlisted properties alongside our citizenship guide to understand the minimum investment threshold, family eligibility, process and practical next steps.', 'theme.portfolio_citizenship.intro' ) ); ?></p>
				<ul class="portfolio-citizenship-guidance__list" aria-label="<?php echo esc_attr( pera_ml_ui( 'Citizenship guide highlights', 'theme.portfolio_citizenship.highlights_label' ) ); ?>">
					<li><?php echo esc_html( pera_ml_ui( 'Minimum investment threshold', 'theme.portfolio_citizenship.minimum_investment' ) ); ?></li>
					<li><?php echo esc_html( pera_ml_ui( 'Family eligibility', 'theme.portfolio_citizenship.family_eligibility' ) ); ?></li>
					<li><?php echo esc_html( pera_ml_ui( 'Process and timelines', 'theme.portfolio_citizenship.process_timelines' ) ); ?></li>
				</ul>
			</div>

			<div class="portfolio-citizenship-guidance__actions">
				<a class="btn btn--solid btn--blue" href="<?php echo esc_url( $guide_url ); ?>"><?php echo esc_html( pera_ml_ui( 'View Citizenship Guide', 'theme.portfolio_citizenship.view_guide' ) ); ?></a>
				<?php if ( '' !== $advisor_contact_url ) : ?>
					<a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $advisor_contact_url ); ?>"><?php echo esc_html( pera_ml_ui( 'Contact Your Advisor', 'theme.portfolio_citizenship.contact_advisor' ) ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
