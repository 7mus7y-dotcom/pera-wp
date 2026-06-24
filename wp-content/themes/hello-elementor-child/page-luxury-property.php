<?php
/**
 * Template Name: Luxury Property Landing Page
 * Description: Focused Meta ads landing page for Istanbul luxury property buyers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$whatsapp_url = pera_get_whatsapp_url( 'Hello Pera Property, I\'m interested in luxury property in Istanbul. Can you send me a private shortlist?' );
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
	'posts_per_page' => 12,
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
			<div class="hero-actions hero-actions--luxury">
				<a
					class="btn btn--solid btn--green js-meta-lead-cta"
					data-meta-event="Lead"
					data-meta-context="luxury_property_landing"
					data-whatsapp="1"
					data-whatsapp-type="luxury_shortlist"
					data-track-channel="whatsapp"
					data-track-intent="high"
					data-track-source="template"
					data-track-context="luxury_property_landing"
					data-track-ga4-event="whatsapp_click"
					data-track-crm-event="whatsapp_click"
					href="<?php echo esc_url( $whatsapp_url ); ?>">Request a Private Shortlist</a>
				<a class="btn btn--solid btn--black" href="#selected-luxury-properties">View Selected Properties</a>
				
			</div>
		</div>
	</section>
	
	<section class="content-panel content-panel--overlap-hero">
		<div class="container">
			<div class="feature-card">
				<p class="pill pill--green pill--sm">Luxury buyer advisory</p>
				<h2>A Curated Route Into Istanbul&rsquo;s Prime Property Market</h2>
				<p>Since 2016, Pera Property has helped international buyers compare Istanbul&rsquo;s strongest lifestyle and investment opportunities &mdash; from Bosphorus-view apartments and branded residences to private villas in established districts.</p>
					<div class="grid-2">
					  <article class="card-shell">
					    <h3>British-Turkish Consultants</h3>
					    <p class="text-sm u-mb-0">Local market access with an international buyer perspective.</p>
					  </article>
					
					  <article class="card-shell">
					    <h3>Buyer-Side Guidance</h3>
					    <p class="text-sm u-mb-0">Shortlists shaped around your budget, lifestyle and objectives.</p>
					  </article>
					
					  <article class="card-shell">
					    <h3>Selected Luxury Homes Only</h3>
					    <p class="text-sm u-mb-0">No mass listings—only properties worth serious consideration.</p>
					  </article>
					
					  <article class="card-shell">
					    <h3>Property Management</h3>
					    <p class="text-sm u-mb-0">Complete management of your home, including cleaning, bills, taxes and everyday practical matters, so you can concentrate on enjoying your property.</p>
					  </article>
					</div>
			</div>
		</div>
	</section>

	<section id="selected-luxury-properties" class="section section-soft">
		<div class="container">
			<h2>Selected Luxury Properties in Istanbul</h2>
			<p>Explore a focused sample of current luxury listings. For broader inventory, view the full luxury tag archive.</p>

			<div class="grid-3">
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
			<p>
				<a
				class="btn btn--solid btn--green js-meta-lead-cta"
				data-meta-event="Lead"
				data-meta-context="luxury_property_landing"
				data-whatsapp="1"
				data-whatsapp-type="luxury_shortlist"
				data-track-channel="whatsapp"
				data-track-intent="high"
				data-track-source="template"
				data-track-context="luxury_property_landing"
				data-track-ga4-event="whatsapp_click"
				data-track-crm-event="whatsapp_click"
				href="<?php echo esc_url( $whatsapp_url ); ?>">Request a Private Shortlist on WhatsApp</a>
				<a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url( '/book-a-consultancy/' ) ); ?>">
					<?php echo esc_html__( 'Book a Consultancy', 'hello-elementor-child' ); ?>
				</a>
			</p>
		</div>
	</section>

	<?php
	$luxury_guide_id = 59250;
	$luxury_guide    = get_post( $luxury_guide_id );

	$luxury_guide_url   = $luxury_guide ? get_permalink( $luxury_guide ) : home_url( '/luxury-property-in-istanbul-the-complete-guide-to-prime-real-estate_59250/' );
	$luxury_guide_title = $luxury_guide ? get_the_title( $luxury_guide ) : 'Luxury Property in Istanbul: The Complete Guide to Prime Real Estate';
	?>

	<section class="section section-soft">
		<div class="container">
			<h2>Best Areas for Luxury Property in Istanbul</h2>

			<p>Istanbul&rsquo;s luxury property market is highly location-specific. The strongest areas depend on whether the buyer is prioritising Bosphorus views, central business access, privacy, family living, branded residence services or long-term resale strength.</p>

			<div class="grid-2">
				<article class="card-shell guide-grid-card">
					<h3><?php echo esc_html( $luxury_guide_title ); ?></h3>
					<p class="pill pill--green pill--sm">Full guide</p>
					<p>Compare Istanbul&rsquo;s prime districts, property types, Bosphorus-view homes, villa markets and buyer considerations in our full luxury property guide.</p>
					<p><a href="<?php echo esc_url( $luxury_guide_url ); ?>">Read the complete guide</a></p>
				</article>

				<article class="card-shell">
					<h3>Beşiktaş, Etiler and Levent</h3>
					<p>These districts are ideal for buyers who want central access, prestige and proximity to Istanbul&rsquo;s business and lifestyle core. Levent and Etiler are particularly attractive for branded residences, high-end apartments and buyers who need quick access to Maslak, Zincirlikuyu and the Bosphorus corridor.</p>
					<p><a href="/district/istanbul/besiktas/">View Beşiktaş properties</a></p>
				</article>

				<article class="card-shell">
					<h3>Nişantaşı and Şişli</h3>
					<p>Nişantaşı is one of Istanbul&rsquo;s most established luxury apartment markets, known for walkability, boutiques, restaurants, private healthcare and historic apartment buildings. It suits buyers who want an urban, city-centre lifestyle rather than a compound or suburban villa setting.</p>
					<p>
						<a href="/district/istanbul/sisli/">View Şişli properties</a><br>
						<a href="/buying-property-in-nisantasi-inside-istanbuls-most-prestigious-residential-market_59156/">Read the Nişantaşı buyer guide</a>
					</p>
				</article>

				<article class="card-shell">
					<h3>Sarıyer, Zekeriyaköy and the Northern Bosphorus</h3>
					<p>Sarıyer and the northern districts are often preferred by villa buyers, families and clients looking for more privacy, greenery and larger living spaces. These areas are especially relevant for buyers comparing gated communities, forest-side homes and luxury villas.</p>
					<p>
						<a href="/district/istanbul/sariyer/">View Sarıyer properties</a><br>
						<a href="/buying-property-in-sariyer-istanbul-explore-coastal-charm_50776/">Read the Sarıyer guide</a>
					</p>
				</article>

				<article class="card-shell">
					<h3>Üsküdar, Kandilli and the Asian Bosphorus</h3>
					<p>The Asian Bosphorus offers a different luxury profile: waterfront mansions, historic neighbourhoods, calmer residential streets and strong views back toward the European side. Üsküdar, Kandilli, Çengelköy and nearby Bosphorus villages are attractive for buyers who want character and view quality.</p>
					<p>
						<a href="/district/istanbul/uskudar/">View Üsküdar properties</a><br>
						<a href="/a-regional-guide-to-uskudar_51808/">Read the Üsküdar guide</a>
					</p>
				</article>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<h2>Types of Luxury Property Available in Istanbul</h2>

			<p>Luxury property in Istanbul covers several different buyer profiles. Some clients want a lock-up-and-leave branded residence, while others prefer a Bosphorus-view apartment, a city-centre penthouse or a private villa with garden space.</p>

			<div class="grid-2">
				<article class="card-shell">
					<h3>Luxury Apartments</h3>
					<p>Luxury apartments in Istanbul are usually concentrated in central districts such as Beşiktaş, Şişli, Nişantaşı, Levent, Etiler and parts of Kadıköy. They are popular with buyers who want convenience, security, walkability and strong rental demand.</p>
				</article>

				<article class="card-shell">
					<h3>Branded Residences</h3>
					<p>Branded and managed residences appeal to international buyers because they often provide security, concierge-style services, professional site management and amenities such as gyms, pools, parking and social facilities.</p>
				</article>

				<article class="card-shell">
					<h3>Bosphorus View Homes</h3>
					<p>Bosphorus-view property remains one of Istanbul&rsquo;s most recognisable luxury segments. View quality, building condition, title deed status, parking, floor level and immediate surroundings can all have a major effect on value.</p>
					<p><a href="/bosphorus-sea-view-apartments-and-villas-in-istanbul_6262/">Read about Bosphorus sea-view apartments and villas</a></p>
				</article>

				<article class="card-shell">
					<h3>Private Villas</h3>
					<p>Villas are most relevant for buyers seeking privacy, garden space, family living and larger internal layouts. Sarıyer, Zekeriyaköy, Beykoz, Çekmeköy and selected northern districts are often considered by villa buyers.</p>
					<p><a href="/a-guide-to-istanbuls-villa-communities-where-to-find-luxury-and-space_52516/">Read our guide to Istanbul&rsquo;s villa communities</a></p>
				</article>
			</div>
		</div>
	</section>

	<section class="section section-soft">
		<div class="container">
			<h2>Investment and Rental Potential</h2>

			<p>The strongest luxury property investments in Istanbul are usually not defined by price alone. Location quality, scarcity, building management, transport access, view quality and tenant depth are often more important than headline square metre size.</p>

			<p>Prime districts can attract executives, expatriates, corporate tenants, families and international buyers looking for a reliable Istanbul base. For investors, the aim is usually a combination of capital preservation, rental liquidity and long-term resale appeal rather than chasing the highest theoretical yield.</p>

			<ul class="checklist">
				<li>Central apartments can suit executive and expatriate rental demand.</li>
				<li>Branded residences may appeal to buyers who value management and amenities.</li>
				<li>Bosphorus-view homes are limited in supply and often have strong long-term recognition.</li>
				<li>Villas can perform well when privacy, land, garden space and family use are priorities.</li>
				<li>District choice should be matched to the buyer&rsquo;s lifestyle, rental strategy and exit plan.</li>
			</ul>

			<p>
				For wider market context, see our
				<a href="/property-for-sale-in-istanbul-the-complete-2026-buyers-guide_58742/">2026 Istanbul property buyer&rsquo;s guide</a>
				and our
				<a href="/istanbul-property-market-in-february-2026-best-districts-for-price-growth-and-yield_58617/">district price growth and yield analysis</a>.
			</p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<h2>Why Buyers Choose Istanbul</h2>
			<div class="grid-2">
				<div><strong>Bosphorus and sea-view lifestyle</strong><p>Exceptional waterfront settings and established lifestyle districts.</p></div>
				<div><strong>Strong prime-district resale appeal</strong><p>Enduring demand in centrally located, high-quality neighborhoods.</p></div>
				<div><strong>Branded residences and managed projects</strong><p>Professionally operated homes with service-oriented amenities.</p></div>
				<div><strong>Turkish citizenship eligibility on selected properties</strong><p>Eligible purchases can support citizenship pathways, subject to current regulations.</p></div>
			</div>
		</div>
	</section>

	<?php
	$luxury_posts_tag = get_term_by( 'slug', 'luxury-istanbul', 'post_tag' );
	$luxury_posts_url = home_url( '/tag/luxury-istanbul/' );

	if ( $luxury_posts_tag instanceof WP_Term ) {
		$luxury_posts_term_link = get_term_link( $luxury_posts_tag );

		if ( ! is_wp_error( $luxury_posts_term_link ) ) {
			$luxury_posts_url = $luxury_posts_term_link;
		}
	}

	$luxury_posts_query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 6,
			'tax_query'           => array(
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => array( 'luxury-istanbul' ),
				),
			),
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	?>

	<?php if ( $luxury_posts_query->have_posts() ) : ?>
		<section class="section section-soft" aria-labelledby="luxury-property-guides-heading">
			<div class="container">
				<header class="section-header section-header--center">
					<p class="u-eyebrow">Luxury property insights</p>
					<h2 id="luxury-property-guides-heading">Luxury Property Guides and Market Insights</h2>
					<p class="lead">Explore our latest guides to Istanbul&rsquo;s prime districts, luxury homes, villa communities, Bosphorus property and premium market trends.</p>
				</header>

				<div class="grid-3">
					<?php while ( $luxury_posts_query->have_posts() ) : ?>
						<?php
						$luxury_posts_query->the_post();
						set_query_var(
							'pera_post_card_args',
							array(
								'variant'       => 'grid',
								'card_classes'  => '',
								'show_readmore' => true,
							)
						);
						get_template_part( 'parts/post-card' );
						?>
					<?php endwhile; ?>
				</div>

				<div class="section-cta">
					<a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $luxury_posts_url ); ?>">View All Luxury Property Guides</a>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
	set_query_var( 'pera_post_card_args', null );
	wp_reset_postdata();
	?>

	<section class="faq-section section">
		<div class="container">
			<h2>Frequently Asked Questions</h2>

			<div class="faq-accordion">
				<details class="faq-item" open>
					<summary>What types of luxury property are available in Istanbul?</summary>
					<div class="faq-answer">
						<p>Luxury options include sea-view apartments, penthouses, branded residences, detached villas and limited boutique projects in prime districts.</p>
					</div>
				</details>

				<details class="faq-item">
					<summary>Which areas are best for luxury property in Istanbul?</summary>
					<div class="faq-answer">
						<p>Buyer goals differ, but Beşiktaş, Nişantaşı, Etiler, Levent, Sarıyer, Üsküdar and Kadıköy are frequently shortlisted for premium homes.</p>
					</div>
				</details>

				<details class="faq-item">
					<summary>Can luxury property qualify for Turkish citizenship?</summary>
					<div class="faq-answer">
						<p>Some properties may qualify for Turkish citizenship routes if they meet the prevailing legal and valuation requirements at the time of purchase.</p>
					</div>
				</details>

				<details class="faq-item">
					<summary>Are villas or apartments better for luxury buyers?</summary>
					<div class="faq-answer">
						<p>It depends on priorities. Villas can offer greater space and privacy, while luxury apartments may provide central access, amenities and easier day-to-day management.</p>
					</div>
				</details>

				<details class="faq-item">
					<summary>How does Pera Property help buyers?</summary>
					<div class="faq-answer">
						<p>Pera Property helps define criteria, pre-screens suitable options, arranges viewings and supports decision-making to reduce noise and avoid mismatched listings.</p>
					</div>
				</details>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
