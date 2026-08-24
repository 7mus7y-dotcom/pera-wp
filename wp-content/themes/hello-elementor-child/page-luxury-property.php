<?php
/**
 * Template Name: Luxury Property Landing Page
 * Description: Focused Meta ads landing page for Istanbul luxury property buyers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$whatsapp_url = pera_get_whatsapp_url( pera_ml_ui( 'Hello Pera Property, I\'m interested in luxury property in Istanbul. Can you send me a private shortlist?', 'theme.template.page_luxury_property.whatsapp_prefill' ) );
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
			<h1><?php echo esc_html( pera_ml_ui( 'Luxury Property in Istanbul for Discerning Buyers', 'theme.template.page_luxury_property.luxury_property_in_istanbul_for_discerning_buyers' ) ); ?></h1>
			<p class="text-light"><?php echo esc_html( pera_ml_ui( 'Handpicked apartments, villas and branded residences in Istanbul’s most desirable districts — selected for lifestyle, quality and long-term appeal.', 'theme.template.page_luxury_property.handpicked_apartments_villas_and_branded_residences_in_istanbul_s_most_d' ) ); ?></p>
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
					href="<?php echo esc_url( $whatsapp_url ); ?>"><?php echo esc_html( pera_ml_ui( 'Request a Private Shortlist', 'theme.template.page_luxury_property.request_a_private_shortlist' ) ); ?></a>
				<a class="btn btn--solid btn--black" href="#selected-luxury-properties"><?php echo esc_html( pera_ml_ui( 'View Selected Properties', 'theme.template.page_luxury_property.view_selected_properties' ) ); ?></a>
				
			</div>
		</div>
	</section>
	
	<section class="content-panel content-panel--overlap-hero">
		<div class="container">
			<div class="feature-card">
				<p class="pill pill--green pill--sm"><?php echo esc_html( pera_ml_ui( 'Luxury buyer advisory', 'theme.template.page_luxury_property.luxury_buyer_advisory' ) ); ?></p>
				<h2><?php echo esc_html( pera_ml_ui( 'A Curated Route Into Istanbul’s Prime Property Market', 'theme.template.page_luxury_property.a_curated_route_into_istanbul_s_prime_property_market' ) ); ?></h2>
				<p><?php echo esc_html( pera_ml_ui( 'Since 2016, Pera Property has helped international buyers compare Istanbul’s strongest lifestyle and investment opportunities — from Bosphorus-view apartments and branded residences to private villas in established districts.', 'theme.template.page_luxury_property.since_2016_pera_property_has_helped_international_buyers_compare_istanbu' ) ); ?></p>
					<div class="grid-2">
					  <article class="card-shell">
					    <h3><?php echo esc_html( pera_ml_ui( 'British-Turkish Consultants', 'theme.template.page_luxury_property.british_turkish_consultants' ) ); ?></h3>
					    <p class="text-sm u-mb-0"><?php echo esc_html( pera_ml_ui( 'Local market access with an international buyer perspective.', 'theme.template.page_luxury_property.local_market_access_with_an_international_buyer_perspective' ) ); ?></p>
					  </article>
					
					  <article class="card-shell">
					    <h3><?php echo esc_html( pera_ml_ui( 'Buyer-Side Guidance', 'theme.template.page_luxury_property.buyer_side_guidance' ) ); ?></h3>
					    <p class="text-sm u-mb-0"><?php echo esc_html( pera_ml_ui( 'Shortlists shaped around your budget, lifestyle and objectives.', 'theme.template.page_luxury_property.shortlists_shaped_around_your_budget_lifestyle_and_objectives' ) ); ?></p>
					  </article>
					
					  <article class="card-shell">
					    <h3><?php echo esc_html( pera_ml_ui( 'Selected Luxury Homes Only', 'theme.template.page_luxury_property.selected_luxury_homes_only' ) ); ?></h3>
					    <p class="text-sm u-mb-0"><?php echo esc_html( pera_ml_ui( 'No mass listings—only properties worth serious consideration.', 'theme.template.page_luxury_property.no_mass_listings_only_properties_worth_serious_consideration' ) ); ?></p>
					  </article>
					
					  <article class="card-shell">
					    <h3><?php echo esc_html( pera_ml_ui( 'Property Management', 'theme.template.page_luxury_property.property_management' ) ); ?></h3>
					    <p class="text-sm u-mb-0"><?php echo esc_html( pera_ml_ui( 'Complete management of your home, including cleaning, bills, taxes and everyday practical matters, so you can concentrate on enjoying your property.', 'theme.template.page_luxury_property.complete_management_of_your_home_including_cleaning_bills_taxes_and_ever' ) ); ?></p>
					  </article>
					</div>
			</div>
		</div>
	</section>

	<section id="selected-luxury-properties" class="section section-soft">
		<div class="container">
			<h2><?php echo esc_html( pera_ml_ui( 'Selected Luxury Properties in Istanbul', 'theme.template.page_luxury_property.selected_luxury_properties_in_istanbul' ) ); ?></h2>
			<p><?php echo esc_html( pera_ml_ui( 'Explore a focused sample of current luxury listings. For broader inventory, view the full luxury tag archive.', 'theme.template.page_luxury_property.explore_a_focused_sample_of_current_luxury_listings_for_broader_inventor' ) ); ?></p>

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
					<p><?php echo esc_html( pera_ml_ui( 'No luxury properties are available right now. Please check back shortly.', 'theme.template.page_luxury_property.no_luxury_properties_are_available_right_now_please_check_back_shortly' ) ); ?></p>
				<?php endif; ?>
			</div>
			<?php wp_reset_postdata(); ?>

			<div class="section text-center">
				<a class="btn btn--solid btn--black" href="<?php echo esc_url( $all_luxury_url ); ?>"><?php echo esc_html( pera_ml_ui( 'View All Luxury Properties', 'theme.template.page_luxury_property.view_all_luxury_properties' ) ); ?></a>
			</div>
		</div>
	</section>
	
	<section class="section property-map-final"><div class="container content-panel-box">
        <h2><?php echo esc_html( pera_ml_ui( 'Want a Private Shortlist Instead?', 'theme.template.page_luxury_property.want_a_private_shortlist_instead' ) ); ?></h2>
        <p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Tell us your preferred location, budget and purpose, and our Istanbul team will send you a focused shortlist instead of overwhelming you with unsuitable options.', 'theme.template.page_luxury_property.tell_us_your_preferred_location_budget_and_purpose_and_our_istanbul_team' ) ); ?></p>
			<div class="hero-actions">
            <a class="btn btn--solid btn--green" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener" data-whatsapp="1" data-whatsapp-type="property_map_final" data-track-channel="whatsapp" data-track-intent="high" data-track-source="page" data-track-context="property_map_final" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click" data-map-track="final_whatsapp"><?php echo esc_html( pera_ml_ui( 'Message us on WhatsApp', 'theme.template.page_luxury_property.message_us_on_whatsapp' ) ); ?></a>
            <a class="btn btn--ghost btn--green" href="<?php echo esc_url( home_url( '/book-a-consultancy/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Book a consultancy', 'theme.template.page_luxury_property.book_a_consultancy' ) ); ?></a>
        </div>
    </div>
  </section>

	<?php
	$luxury_guide_id = 59250;
	$luxury_guide    = get_post( $luxury_guide_id );

	$luxury_guide_url   = $luxury_guide ? get_permalink( $luxury_guide ) : home_url( '/luxury-property-in-istanbul-the-complete-guide-to-prime-real-estate_59250/' );
	$luxury_guide_title = $luxury_guide ? get_the_title( $luxury_guide ) : pera_ml_ui( 'Luxury Property in Istanbul: The Complete Guide to Prime Real Estate', 'theme.template.page_luxury_property.guide_title_fallback' );
	?>

	<section class="section section-soft">
		<div class="container">
			<h2><?php echo esc_html( pera_ml_ui( 'Best Areas for Luxury Property in Istanbul', 'theme.template.page_luxury_property.best_areas_for_luxury_property_in_istanbul' ) ); ?></h2>

			<p><?php echo esc_html( pera_ml_ui( 'Istanbul’s luxury property market is highly location-specific. The strongest areas depend on whether the buyer is prioritising Bosphorus views, central business access, privacy, family living, branded residence services or long-term resale strength.', 'theme.template.page_luxury_property.istanbul_s_luxury_property_market_is_highly_location_specific_the_strong' ) ); ?></p>

			<div class="grid-2">
				<article class="card-shell guide-grid-card">
					<h3><?php echo esc_html( $luxury_guide_title ); ?></h3>
					<p class="pill pill--green pill--sm"><?php echo esc_html( pera_ml_ui( 'Full guide', 'theme.template.page_luxury_property.full_guide' ) ); ?></p>
					<p><?php echo esc_html( pera_ml_ui( 'Compare Istanbul’s prime districts, property types, Bosphorus-view homes, villa markets and buyer considerations in our full luxury property guide.', 'theme.template.page_luxury_property.compare_istanbul_s_prime_districts_property_types_bosphorus_view_homes_v' ) ); ?></p>
					<p><a href="<?php echo esc_url( $luxury_guide_url ); ?>"><?php echo esc_html( pera_ml_ui( 'Read the complete guide', 'theme.template.page_luxury_property.read_the_complete_guide' ) ); ?></a></p>
				</article>

				<article class="card-shell">
					<h3><?php echo esc_html( pera_ml_ui( 'Beşiktaş, Etiler and Levent', 'theme.template.page_luxury_property.be_ikta_etiler_and_levent' ) ); ?></h3>
					<p><?php echo esc_html( pera_ml_ui( 'These districts are ideal for buyers who want central access, prestige and proximity to Istanbul’s business and lifestyle core. Levent and Etiler are particularly attractive for branded residences, high-end apartments and buyers who need quick access to Maslak, Zincirlikuyu and the Bosphorus corridor.', 'theme.template.page_luxury_property.these_districts_are_ideal_for_buyers_who_want_central_access_prestige_an' ) ); ?></p>
					<p><a href="/district/istanbul/besiktas/"><?php echo esc_html( pera_ml_ui( 'View Beşiktaş properties', 'theme.template.page_luxury_property.view_be_ikta_properties' ) ); ?></a></p>
				</article>

				<article class="card-shell">
					<h3><?php echo esc_html( pera_ml_ui( 'Nişantaşı and Şişli', 'theme.template.page_luxury_property.ni_anta_and_i_li' ) ); ?></h3>
					<p><?php echo esc_html( pera_ml_ui( 'Nişantaşı is one of Istanbul’s most established luxury apartment markets, known for walkability, boutiques, restaurants, private healthcare and historic apartment buildings. It suits buyers who want an urban, city-centre lifestyle rather than a compound or suburban villa setting.', 'theme.template.page_luxury_property.ni_anta_is_one_of_istanbul_s_most_established_luxury_apartment_markets_k' ) ); ?></p>
					<p>
						<a href="/district/istanbul/sisli/"><?php echo esc_html( pera_ml_ui( 'View Şişli properties', 'theme.template.page_luxury_property.view_i_li_properties' ) ); ?></a><br>
						<a href="/buying-property-in-nisantasi-inside-istanbuls-most-prestigious-residential-market_59156/"><?php echo esc_html( pera_ml_ui( 'Read the Nişantaşı buyer guide', 'theme.template.page_luxury_property.read_the_ni_anta_buyer_guide' ) ); ?></a>
					</p>
				</article>

				<article class="card-shell">
					<h3><?php echo esc_html( pera_ml_ui( 'Sarıyer, Zekeriyaköy and the Northern Bosphorus', 'theme.template.page_luxury_property.sar_yer_zekeriyak_y_and_the_northern_bosphorus' ) ); ?></h3>
					<p><?php echo esc_html( pera_ml_ui( 'Sarıyer and the northern districts are often preferred by villa buyers, families and clients looking for more privacy, greenery and larger living spaces. These areas are especially relevant for buyers comparing gated communities, forest-side homes and luxury villas.', 'theme.template.page_luxury_property.sar_yer_and_the_northern_districts_are_often_preferred_by_villa_buyers_f' ) ); ?></p>
					<p>
						<a href="/district/istanbul/sariyer/"><?php echo esc_html( pera_ml_ui( 'View Sarıyer properties', 'theme.template.page_luxury_property.view_sar_yer_properties' ) ); ?></a><br>
						<a href="/buying-property-in-sariyer-istanbul-explore-coastal-charm_50776/"><?php echo esc_html( pera_ml_ui( 'Read the Sarıyer guide', 'theme.template.page_luxury_property.read_the_sar_yer_guide' ) ); ?></a>
					</p>
				</article>

				<article class="card-shell">
					<h3><?php echo esc_html( pera_ml_ui( 'Üsküdar, Kandilli and the Asian Bosphorus', 'theme.template.page_luxury_property.sk_dar_kandilli_and_the_asian_bosphorus' ) ); ?></h3>
					<p><?php echo esc_html( pera_ml_ui( 'The Asian Bosphorus offers a different luxury profile: waterfront mansions, historic neighbourhoods, calmer residential streets and strong views back toward the European side. Üsküdar, Kandilli, Çengelköy and nearby Bosphorus villages are attractive for buyers who want character and view quality.', 'theme.template.page_luxury_property.the_asian_bosphorus_offers_a_different_luxury_profile_waterfront_mansion' ) ); ?></p>
					<p>
						<a href="/district/istanbul/uskudar/"><?php echo esc_html( pera_ml_ui( 'View Üsküdar properties', 'theme.template.page_luxury_property.view_sk_dar_properties' ) ); ?></a><br>
						<a href="/a-regional-guide-to-uskudar_51808/"><?php echo esc_html( pera_ml_ui( 'Read the Üsküdar guide', 'theme.template.page_luxury_property.read_the_sk_dar_guide' ) ); ?></a>
					</p>
				</article>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<h2><?php echo esc_html( pera_ml_ui( 'Types of Luxury Property Available in Istanbul', 'theme.template.page_luxury_property.types_of_luxury_property_available_in_istanbul' ) ); ?></h2>

			<p><?php echo esc_html( pera_ml_ui( 'Luxury property in Istanbul covers several different buyer profiles. Some clients want a lock-up-and-leave branded residence, while others prefer a Bosphorus-view apartment, a city-centre penthouse or a private villa with garden space.', 'theme.template.page_luxury_property.luxury_property_in_istanbul_covers_several_different_buyer_profiles_some' ) ); ?></p>

			<div class="grid-2">
				<article class="card-shell">
					<h3><?php echo esc_html( pera_ml_ui( 'Luxury Apartments', 'theme.template.page_luxury_property.luxury_apartments' ) ); ?></h3>
					<p><?php echo esc_html( pera_ml_ui( 'Luxury apartments in Istanbul are usually concentrated in central districts such as Beşiktaş, Şişli, Nişantaşı, Levent, Etiler and parts of Kadıköy. They are popular with buyers who want convenience, security, walkability and strong rental demand.', 'theme.template.page_luxury_property.luxury_apartments_in_istanbul_are_usually_concentrated_in_central_distri' ) ); ?></p>
				</article>

				<article class="card-shell">
					<h3><?php echo esc_html( pera_ml_ui( 'Branded Residences', 'theme.template.page_luxury_property.branded_residences' ) ); ?></h3>
					<p><?php echo esc_html( pera_ml_ui( 'Branded and managed residences appeal to international buyers because they often provide security, concierge-style services, professional site management and amenities such as gyms, pools, parking and social facilities.', 'theme.template.page_luxury_property.branded_and_managed_residences_appeal_to_international_buyers_because_th' ) ); ?></p>
				</article>

				<article class="card-shell">
					<h3><?php echo esc_html( pera_ml_ui( 'Bosphorus View Homes', 'theme.template.page_luxury_property.bosphorus_view_homes' ) ); ?></h3>
					<p><?php echo esc_html( pera_ml_ui( 'Bosphorus-view property remains one of Istanbul’s most recognisable luxury segments. View quality, building condition, title deed status, parking, floor level and immediate surroundings can all have a major effect on value.', 'theme.template.page_luxury_property.bosphorus_view_property_remains_one_of_istanbul_s_most_recognisable_luxu' ) ); ?></p>
					<p><a href="/bosphorus-sea-view-apartments-and-villas-in-istanbul_6262/"><?php echo esc_html( pera_ml_ui( 'Read about Bosphorus sea-view apartments and villas', 'theme.template.page_luxury_property.read_about_bosphorus_sea_view_apartments_and_villas' ) ); ?></a></p>
				</article>

				<article class="card-shell">
					<h3><?php echo esc_html( pera_ml_ui( 'Private Villas', 'theme.template.page_luxury_property.private_villas' ) ); ?></h3>
					<p><?php echo esc_html( pera_ml_ui( 'Villas are most relevant for buyers seeking privacy, garden space, family living and larger internal layouts. Sarıyer, Zekeriyaköy, Beykoz, Çekmeköy and selected northern districts are often considered by villa buyers.', 'theme.template.page_luxury_property.villas_are_most_relevant_for_buyers_seeking_privacy_garden_space_family_' ) ); ?></p>
					<p><a href="/a-guide-to-istanbuls-villa-communities-where-to-find-luxury-and-space_52516/"><?php echo esc_html( pera_ml_ui( 'Read our guide to Istanbul’s villa communities', 'theme.template.page_luxury_property.read_our_guide_to_istanbul_s_villa_communities' ) ); ?></a></p>
				</article>
			</div>
		</div>
	</section>

	<section class="section section-soft">
		<div class="container">
			<h2><?php echo esc_html( pera_ml_ui( 'Investment and Rental Potential', 'theme.template.page_luxury_property.investment_and_rental_potential' ) ); ?></h2>

			<p><?php echo esc_html( pera_ml_ui( 'The strongest luxury property investments in Istanbul are usually not defined by price alone. Location quality, scarcity, building management, transport access, view quality and tenant depth are often more important than headline square metre size.', 'theme.template.page_luxury_property.the_strongest_luxury_property_investments_in_istanbul_are_usually_not_de' ) ); ?></p>

			<p><?php echo esc_html( pera_ml_ui( 'Prime districts can attract executives, expatriates, corporate tenants, families and international buyers looking for a reliable Istanbul base. For investors, the aim is usually a combination of capital preservation, rental liquidity and long-term resale appeal rather than chasing the highest theoretical yield.', 'theme.template.page_luxury_property.prime_districts_can_attract_executives_expatriates_corporate_tenants_fam' ) ); ?></p>

			<ul class="checklist">
				<li><?php echo esc_html( pera_ml_ui( 'Central apartments can suit executive and expatriate rental demand.', 'theme.template.page_luxury_property.central_apartments_can_suit_executive_and_expatriate_rental_demand' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Branded residences may appeal to buyers who value management and amenities.', 'theme.template.page_luxury_property.branded_residences_may_appeal_to_buyers_who_value_management_and_ameniti' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Bosphorus-view homes are limited in supply and often have strong long-term recognition.', 'theme.template.page_luxury_property.bosphorus_view_homes_are_limited_in_supply_and_often_have_strong_long_te' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Villas can perform well when privacy, land, garden space and family use are priorities.', 'theme.template.page_luxury_property.villas_can_perform_well_when_privacy_land_garden_space_and_family_use_ar' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'District choice should be matched to the buyer’s lifestyle, rental strategy and exit plan.', 'theme.template.page_luxury_property.district_choice_should_be_matched_to_the_buyer_s_lifestyle_rental_strate' ) ); ?></li>
			</ul>

			<p>
				<?php echo esc_html( pera_ml_ui( 'For wider market context, see our', 'theme.template.page_luxury_property.for_wider_market_context_see_our' ) ); ?>
				<a href="/property-for-sale-in-istanbul-the-complete-2026-buyers-guide_58742/"><?php echo esc_html( pera_ml_ui( '2026 Istanbul property buyer’s guide', 'theme.template.page_luxury_property.2026_istanbul_property_buyer_s_guide' ) ); ?></a>
				<?php echo esc_html( pera_ml_ui( 'and our', 'theme.template.page_luxury_property.and_our' ) ); ?>
				<a href="/istanbul-property-market-in-february-2026-best-districts-for-price-growth-and-yield_58617/"><?php echo esc_html( pera_ml_ui( 'district price growth and yield analysis', 'theme.template.page_luxury_property.district_price_growth_and_yield_analysis' ) ); ?></a>.
			</p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<h2><?php echo esc_html( pera_ml_ui( 'Why Buyers Choose Istanbul', 'theme.template.page_luxury_property.why_buyers_choose_istanbul' ) ); ?></h2>
			<div class="grid-2">
				<div><strong><?php echo esc_html( pera_ml_ui( 'Bosphorus and sea-view lifestyle', 'theme.template.page_luxury_property.bosphorus_and_sea_view_lifestyle' ) ); ?></strong><p><?php echo esc_html( pera_ml_ui( 'Exceptional waterfront settings and established lifestyle districts.', 'theme.template.page_luxury_property.exceptional_waterfront_settings_and_established_lifestyle_districts' ) ); ?></p></div>
				<div><strong><?php echo esc_html( pera_ml_ui( 'Strong prime-district resale appeal', 'theme.template.page_luxury_property.strong_prime_district_resale_appeal' ) ); ?></strong><p><?php echo esc_html( pera_ml_ui( 'Enduring demand in centrally located, high-quality neighborhoods.', 'theme.template.page_luxury_property.enduring_demand_in_centrally_located_high_quality_neighborhoods' ) ); ?></p></div>
				<div><strong><?php echo esc_html( pera_ml_ui( 'Branded residences and managed projects', 'theme.template.page_luxury_property.branded_residences_and_managed_projects' ) ); ?></strong><p><?php echo esc_html( pera_ml_ui( 'Professionally operated homes with service-oriented amenities.', 'theme.template.page_luxury_property.professionally_operated_homes_with_service_oriented_amenities' ) ); ?></p></div>
				<div><strong><?php echo esc_html( pera_ml_ui( 'Turkish citizenship eligibility on selected properties', 'theme.template.page_luxury_property.turkish_citizenship_eligibility_on_selected_properties' ) ); ?></strong><p><?php echo esc_html( pera_ml_ui( 'Eligible purchases can support citizenship pathways, subject to current regulations.', 'theme.template.page_luxury_property.eligible_purchases_can_support_citizenship_pathways_subject_to_current_r' ) ); ?></p></div>
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
					<p class="u-eyebrow"><?php echo esc_html( pera_ml_ui( 'Luxury property insights', 'theme.template.page_luxury_property.luxury_property_insights' ) ); ?></p>
					<h2 id="luxury-property-guides-heading"><?php echo esc_html( pera_ml_ui( 'Luxury Property Guides and Market Insights', 'theme.template.page_luxury_property.luxury_property_guides_and_market_insights' ) ); ?></h2>
					<p class="lead"><?php echo esc_html( pera_ml_ui( 'Explore our latest guides to Istanbul’s prime districts, luxury homes, villa communities, Bosphorus property and premium market trends.', 'theme.template.page_luxury_property.explore_our_latest_guides_to_istanbul_s_prime_districts_luxury_homes_vil' ) ); ?></p>
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
					<a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $luxury_posts_url ); ?>"><?php echo esc_html( pera_ml_ui( 'View All Luxury Property Guides', 'theme.template.page_luxury_property.view_all_luxury_property_guides' ) ); ?></a>
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
			<h2><?php echo esc_html( pera_ml_ui( 'Frequently Asked Questions', 'theme.template.page_luxury_property.frequently_asked_questions' ) ); ?></h2>

			<div class="faq-accordion">
				<details class="faq-item" open>
					<summary><?php echo esc_html( pera_ml_ui( 'What types of luxury property are available in Istanbul?', 'theme.template.page_luxury_property.what_types_of_luxury_property_are_available_in_istanbul' ) ); ?></summary>
					<div class="faq-answer">
						<p><?php echo esc_html( pera_ml_ui( 'Luxury options include sea-view apartments, penthouses, branded residences, detached villas and limited boutique projects in prime districts.', 'theme.template.page_luxury_property.luxury_options_include_sea_view_apartments_penthouses_branded_residences' ) ); ?></p>
					</div>
				</details>

				<details class="faq-item">
					<summary><?php echo esc_html( pera_ml_ui( 'Which areas are best for luxury property in Istanbul?', 'theme.template.page_luxury_property.which_areas_are_best_for_luxury_property_in_istanbul' ) ); ?></summary>
					<div class="faq-answer">
						<p><?php echo esc_html( pera_ml_ui( 'Buyer goals differ, but Beşiktaş, Nişantaşı, Etiler, Levent, Sarıyer, Üsküdar and Kadıköy are frequently shortlisted for premium homes.', 'theme.template.page_luxury_property.buyer_goals_differ_but_be_ikta_ni_anta_etiler_levent_sar_yer_sk_dar_and_' ) ); ?></p>
					</div>
				</details>

				<details class="faq-item">
					<summary><?php echo esc_html( pera_ml_ui( 'Can luxury property qualify for Turkish citizenship?', 'theme.template.page_luxury_property.can_luxury_property_qualify_for_turkish_citizenship' ) ); ?></summary>
					<div class="faq-answer">
						<p><?php echo esc_html( pera_ml_ui( 'Some properties may qualify for Turkish citizenship routes if they meet the prevailing legal and valuation requirements at the time of purchase.', 'theme.template.page_luxury_property.some_properties_may_qualify_for_turkish_citizenship_routes_if_they_meet_' ) ); ?></p>
					</div>
				</details>

				<details class="faq-item">
					<summary><?php echo esc_html( pera_ml_ui( 'Are villas or apartments better for luxury buyers?', 'theme.template.page_luxury_property.are_villas_or_apartments_better_for_luxury_buyers' ) ); ?></summary>
					<div class="faq-answer">
						<p><?php echo esc_html( pera_ml_ui( 'It depends on priorities. Villas can offer greater space and privacy, while luxury apartments may provide central access, amenities and easier day-to-day management.', 'theme.template.page_luxury_property.it_depends_on_priorities_villas_can_offer_greater_space_and_privacy_whil' ) ); ?></p>
					</div>
				</details>

				<details class="faq-item">
					<summary><?php echo esc_html( pera_ml_ui( 'How does Pera Property help buyers?', 'theme.template.page_luxury_property.how_does_pera_property_help_buyers' ) ); ?></summary>
					<div class="faq-answer">
						<p><?php echo esc_html( pera_ml_ui( 'Pera Property helps define criteria, pre-screens suitable options, arranges viewings and supports decision-making to reduce noise and avoid mismatched listings.', 'theme.template.page_luxury_property.pera_property_helps_define_criteria_pre_screens_suitable_options_arrange' ) ); ?></p>
					</div>
				</details>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
