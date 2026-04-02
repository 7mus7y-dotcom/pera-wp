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
<section class="section section-soft portfolio-citizenship-guidance" aria-label="Turkish Citizenship Guidance">
	<div class="container">
		<div class="portfolio-citizenship-guidance__panel">
			<div class="portfolio-citizenship-guidance__main">
				<p class="portfolio-citizenship-guidance__kicker">Turkish Citizenship Guidance</p>
				<h2 class="portfolio-citizenship-guidance__title">See how this portfolio can support your citizenship route</h2>
				<p class="portfolio-citizenship-guidance__text">
					Review these shortlisted properties alongside our citizenship guide to understand the minimum investment threshold, family eligibility, process and practical next steps.
				</p>
				<ul class="portfolio-citizenship-guidance__list" aria-label="Citizenship guide highlights">
					<li>Minimum investment threshold</li>
					<li>Family eligibility</li>
					<li>Process and timelines</li>
				</ul>
			</div>

			<div class="portfolio-citizenship-guidance__actions">
				<a class="btn btn--blue" href="<?php echo esc_url( $guide_url ); ?>">View Citizenship Guide</a>
				<?php if ( '' !== $advisor_contact_url ) : ?>
					<a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $advisor_contact_url ); ?>">Contact Your Advisor</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
