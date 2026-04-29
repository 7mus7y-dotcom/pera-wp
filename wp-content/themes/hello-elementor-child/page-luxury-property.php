<?php
/**
 * Template Name: Luxury Property Landing Page
 * Description: Focused Meta ads landing page for Istanbul luxury property buyers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$whatsapp_url = 'https://wa.me/905320639978?text=Hello%20Pera%20Property%2C%20I%27m%20interested%20in%20luxury%20property%20in%20Istanbul.%20Can%20you%20send%20me%20a%20private%20shortlist%3F';
$luxury_term = get_term_by( 'slug', 'istanbul-luxury-property-for-sale', 'property_tags' );
$all_luxury_url = home_url( '/property_tags/istanbul-luxury-property-for-sale/' );

if ( $luxury_term instanceof WP_Term ) {
	$luxury_term_link = get_term_link( $luxury_term );

	if ( ! is_wp_error( $luxury_term_link ) ) {
		$all_luxury_url = $luxury_term_link;
	}
}
$hero_img_id = 0;

if ( $luxury_term instanceof WP_Term && function_exists( 'get_field' ) ) {
	$acf_ref      = $luxury_term->taxonomy . '_' . $luxury_term->term_id;
	$hero_image   = get_field( 'district_image', $acf_ref );

	if ( is_array( $hero_image ) && ! empty( $hero_image['ID'] ) ) {
		$hero_img_id = (int) $hero_image['ID'];
	} elseif ( is_numeric( $hero_image ) ) {
		$hero_img_id = (int) $hero_image;
	}
}

if ( ! $hero_img_id ) {
	$hero_img_id = 55482;
}

$luxury_query_args = array(
	'post_type'      => 'property',
	'post_status'    => 'publish',
	'posts_per_page' => 9,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'tax_query'      => array(
		array(
			'taxonomy' => 'property_tags',
			'field'    => 'slug',
			'terms'    => array( 'istanbul-luxury-property-for-sale' ),
		),
	),
);

$luxury_query = new WP_Query( $luxury_query_args );
?>

<main id="primary" class="site-main pera-lean">
	<section class="hero hero--left">
		<?php if ( $hero_img_id ) : ?>
			<div class="hero__media" aria-hidden="true">
				<?php
				echo wp_get_attachment_image(
					$hero_img_id,
					'full',
					false,
					array(
						'class'         => 'hero-media',
						'loading'       => 'eager',
						'decoding'      => 'async',
						'fetchpriority' => 'high',
					)
				);
				?>
				<div class="hero-overlay" aria-hidden="true"></div>
			</div>
		<?php endif; ?>

		<div class="hero-content">
			<h1>Luxury Property in Istanbul for Discerning Buyers</h1>
			<p class="text-light">Handpicked apartments, villas and branded residences in Istanbul&rsquo;s most desirable districts &mdash; selected for lifestyle, quality and long-term appeal.</p>
			<p>
				<a class="btn btn--solid btn--green js-meta-lead-cta" data-meta-event="Lead" data-meta-context="luxury_property_landing" href="<?php echo esc_url( $whatsapp_url ); ?>">Request a Private Shortlist</a>
				<a class="btn btn--solid btn--black" href="#selected-luxury-properties">View Selected Properties</a>
			</p>
		</div>
	</section>

	<section class="content-panel content-panel--overlap-hero">
		<div class="container">
			<div class="feature-card">
				<p class="pill pill--green pill--sm">Luxury buyer advisory</p>
				<h2>A Curated Route Into Istanbul&rsquo;s Prime Property Market</h2>
				<p>Since 2016, Pera Property has helped international buyers compare Istanbul&rsquo;s strongest lifestyle and investment opportunities &mdash; from Bosphorus-view apartments and branded residences to private villas in established districts.</p>
            	<div class="feature-card-body">
                	<ul class="checklist">
						<li>
	                      <svg class="icon icon-tick" aria-hidden="true">
	                        <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
	                      </svg>
						<strong>British-Turkish consultants</strong></li>
						<p class="text-sm">Local market access with international buyer perspective.</p>
					</ul>
					<ul class="checklist">
						<li>
	                      <svg class="icon icon-tick" aria-hidden="true">
	                        <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
	                      </svg>
						<strong>Buyer-side guidance</strong></li>
						<p class="text-sm">Shortlists shaped around your budget, lifestyle and objective.</p>
					</ul>
					<ul class="checklist">
						<li>
	                      <svg class="icon icon-tick" aria-hidden="true">
	                        <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
	                      </svg>
						<strong>Selected luxury homes only</strong></li>
						<p class="text-sm">No mass listings &mdash; only properties worth serious consideration.</p>
					</ul>
				</div>
			</div>
		</div>
	</section>

	<section id="selected-luxury-properties" class="section section-soft">
		<div class="container">
			<h2>Selected Luxury Properties in Istanbul</h2>
			<p>Explore a focused sample of current luxury listings. For broader inventory, view the full luxury tag archive.</p>

			<div class="cards-grid">
				<?php if ( $luxury_query->have_posts() ) : ?>
					<?php while ( $luxury_query->have_posts() ) : $luxury_query->the_post(); ?>
						<?php
						if ( function_exists( 'pera_render_property_card' ) ) {
							pera_render_property_card(
								array(
									'variant' => 'archive',
								)
							);
						}
						?>
					<?php endwhile; ?>
				<?php else : ?>
					<p>No luxury properties are available right now. Please check back shortly.</p>
				<?php endif; ?>
			</div>
			<?php wp_reset_postdata(); ?>

			<div class="section text-center">
				<a class="btn btn--solid btn--black" href="<?php echo esc_url( $all_luxury_url ); ?>">View All Luxury Properties</a>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<h2>Want a Private Shortlist Instead?</h2>
			<p>Tell us your preferred location, budget and purpose, and our Istanbul team will send you a focused shortlist instead of overwhelming you with unsuitable options.</p>
			<p><a class="btn btn--solid btn--green js-meta-lead-cta" data-meta-event="Lead" data-meta-context="luxury_property_landing" href="<?php echo esc_url( $whatsapp_url ); ?>">Request a Private Shortlist on WhatsApp</a></p>
		</div>
	</section>

	<section class="section section-soft">
		<div class="container">
			<h2>Why Buyers Choose Istanbul</h2>
			<div class="cards-grid">
				<div><strong>Bosphorus and sea-view lifestyle</strong><p>Exceptional waterfront settings and established lifestyle districts.</p></div>
				<div><strong>Strong prime-district resale appeal</strong><p>Enduring demand in centrally located, high-quality neighborhoods.</p></div>
				<div><strong>Branded residences and managed projects</strong><p>Professionally operated homes with service-oriented amenities.</p></div>
				<div><strong>Turkish citizenship eligibility on selected properties</strong><p>Eligible purchases can support citizenship pathways, subject to current regulations.</p></div>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<h2>Frequently Asked Questions</h2>
			<h3>What types of luxury property are available in Istanbul?</h3>
			<p>Luxury options include sea-view apartments, penthouses, branded residences, detached villas and limited boutique projects in prime districts.</p>
			<h3>Which areas are best for luxury property in Istanbul?</h3>
			<p>Buyer goals differ, but Beşiktaş, Nişantaşı, Etiler, Levent, Sarıyer, Üsküdar and Kadıköy are frequently shortlisted for premium homes.</p>
			<h3>Can luxury property qualify for Turkish citizenship?</h3>
			<p>Some properties may qualify for Turkish citizenship routes if they meet the prevailing legal and valuation requirements at the time of purchase.</p>
			<h3>Are villas or apartments better for luxury buyers?</h3>
			<p>It depends on priorities. Villas can offer greater space and privacy, while luxury apartments may provide central access, amenities and easier day-to-day management.</p>
			<h3>How does Pera Property help buyers?</h3>
			<p>Pera Property helps define criteria, pre-screens suitable options, arranges viewings and supports decision-making to reduce noise and avoid mismatched listings.</p>
		</div>
	</section>

	<section class="section section-soft">
		<div class="container">
			<h2>Speak with an Istanbul Luxury Property Specialist</h2>
			<p>Share your budget, preferred locations and buying objective. We will help you compare the best available options and avoid unsuitable listings.</p>
			<p><a class="btn btn--solid btn--green js-meta-lead-cta" data-meta-event="Lead" data-meta-context="luxury_property_landing" href="<?php echo esc_url( $whatsapp_url ); ?>">Message Pera Property on WhatsApp</a></p>
		</div>
	</section>
</main>

<?php
get_footer();
