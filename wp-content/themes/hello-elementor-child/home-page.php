<?php
/**
 * Template Name: Home page (Final 2025 version)
 * Custom About Us page using lean header/footer + main.css hero
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

get_header();
?>

<main id="primary" class="site-main 2025-home-page">
<?php
$archive_base_url = get_post_type_archive_link('property'); // /property/
$hero_img_id      = 55484;
$hero_img_url     = wp_get_attachment_image_url($hero_img_id, 'pera-card');

// V2 beds options (single-select radio; your V2 archive expects v2_beds scalar)
$beds_options = array( 1, 2, 3, 4, 5, 6 );

$front_page_id = (int) get_option( 'page_on_front' );
if ( $front_page_id <= 0 ) {
  $front_page_id = (int) get_queried_object_id();
}

$homepage_hero_subtext = '';
$homepage_listing_intro = '';
$homepage_bottom_seo_text = '';

if ( function_exists( 'get_field' ) ) {
  $field_context = $front_page_id > 0 ? $front_page_id : get_queried_object_id();

  $homepage_hero_subtext = trim( (string) get_field( 'homepage_hero_subtext', $field_context ) );
  $homepage_listing_intro = (string) get_field( 'homepage_listing_intro', $field_context );
  $homepage_bottom_seo_text = (string) get_field( 'homepage_bottom_seo_text', $field_context );
}

// Budget presets still work in V2 because your V2 SSR/AJAX reads min_price/max_price
?>
<section class="hero hero--center" aria-label="<?php echo esc_attr( pera_ml_ui( 'Homepage hero search', 'theme.template.home_page.aria_label.homepage_hero_search' ) ); ?>">
    <?php if ( $hero_img_url ) : ?>
      <img class="hero-media" src="<?php echo esc_url( $hero_img_url ); ?>" alt="" aria-hidden="true">
    <?php endif; ?>
    <div class="hero-overlay" aria-hidden="true"></div>

    <div class="hero-content">

      <h1><?php echo esc_html( pera_ml_ui( 'Find Property in Istanbul', 'theme.template.home_page.find_property_in_istanbul' ) ); ?></h1>

      <div class="lead">
        <p>
          <?php echo $homepage_hero_subtext !== ''
            ? esc_html( $homepage_hero_subtext )
            : esc_html( pera_ml_ui( 'Explore the best property for sale in Istanbul, including modern apartments, luxury residences, and high-yield investment opportunities across the city’s most desirable districts.', 'theme.template.home_page.hero_subtext_fallback' ) ); ?>
        </p>
      </div>

      <div class="width-restricter centered">

        <form
          method="get"
          class="hero-search-lite glass glass--strong glass--card"
          action="<?php echo esc_url( $archive_base_url ); ?>"
        >

          <!-- BEDROOMS (V2: v2_beds radio integer; NOT bedrooms[] taxonomy) -->
          <div class="filter-group text-center">
            <div class="filter-group__label"><?php echo esc_html( pera_ml_ui( 'Bedrooms', 'theme.template.home_page.bedrooms' ) ); ?></div>

            <div class="filter-pill-row flex-center" role="radiogroup" aria-label="<?php echo esc_attr( pera_ml_ui( 'Bedrooms', 'theme.template.home_page.aria_label.bedrooms' ) ); ?>">

              <label class="pill pill--outline filter-pill pill--active">
                <input type="radio" name="v2_beds" value="" checked>
                <span>
                  <svg class="icon icon-bed" aria-hidden="true">
                    <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-bed' ); ?>"></use>
                  </svg>
                  <?php echo esc_html( pera_ml_ui( 'Any', 'theme.template.home_page.any' ) ); ?>
                </span>
              </label>

              <?php foreach ( $beds_options as $b ) : ?>
                <label class="pill pill--outline filter-pill">
                  <input type="radio" name="v2_beds" value="<?php echo esc_attr( $b ); ?>">
                  <span>
                    <svg class="icon icon-bed" aria-hidden="true">
                      <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-bed' ); ?>"></use>
                    </svg>
                    <?php echo esc_html( $b ); ?>
                  </span>
                </label>
              <?php endforeach; ?>

            </div>
          </div>

          <!-- LOCATION (district[] taxonomy; V2 supports district[] IN) -->
          <div class="filter-group text-center">
            <div class="filter-group__label"><?php echo esc_html( pera_ml_ui( 'Location', 'theme.template.home_page.location' ) ); ?></div>

            <div class="filter-pill-row flex-center">
              <button
                type="button"
                class="pill pill--outline filter-pill filter-pill--all pill--active"
                data-clear-group="district"
              >
                <span><?php echo esc_html( pera_ml_ui( 'Any', 'theme.template.home_page.any' ) ); ?></span>
              </button>

              <?php
              $top_district_slugs = array( 'besiktas', 'sisli', 'kadikoy', 'uskudar', 'sariyer' );

              foreach ( $top_district_slugs as $slug ) :
                $term = get_term_by( 'slug', $slug, 'district' );
                if ( ! $term || is_wp_error( $term ) ) {
                  continue;
                }
                ?>
                <label class="pill pill--outline filter-pill">
                  <input type="checkbox" name="district[]" value="<?php echo esc_attr( $term->slug ); ?>">
                  <span><?php echo esc_html( function_exists( 'pera_ml_term' ) ? pera_ml_term( $term ) : $term->name ); ?></span>
                </label>
              <?php endforeach; ?>

              <a class="pill pill--outline" href="<?php echo esc_url( $archive_base_url ); ?>">
                <?php echo esc_html( pera_ml_ui( 'More areas', 'theme.template.home_page.more_areas' ) ); ?>
              </a>
            </div>
          </div>

          <!-- BUDGET (V2 uses min_price/max_price; overlap logic on v2_price_usd_min/max) -->
          <div class="filter-group text-center">
            <div class="filter-group__label"><?php echo esc_html( pera_ml_ui( 'Budget (USD)', 'theme.template.home_page.budget_usd' ) ); ?></div>

            <input type="hidden" name="min_price" id="hero-min-price" value="">
            <input type="hidden" name="max_price" id="hero-max-price" value="">

            <div class="filter-pill-row flex-center" role="radiogroup" aria-label="<?php echo esc_attr( pera_ml_ui( 'Budget presets', 'theme.template.home_page.aria_label.budget_presets' ) ); ?>">
              <button type="button" class="pill pill--outline filter-pill pill--active" data-budget=""><?php echo esc_html( pera_ml_ui( 'Any', 'theme.template.home_page.any' ) ); ?></button>
              <button type="button" class="pill pill--outline filter-pill" data-budget="0,250000"><?php echo esc_html( pera_ml_ui( 'Up to $250k', 'theme.template.home_page.up_to_250k' ) ); ?></button>
              <button type="button" class="pill pill--outline filter-pill" data-budget="250000,500000"><?php echo esc_html( pera_ml_ui( '$250k–$500k', 'theme.template.home_page.250k_500k' ) ); ?></button>
              <button type="button" class="pill pill--outline filter-pill" data-budget="500000,1000000"><?php echo esc_html( pera_ml_ui( '$500k–$1m', 'theme.template.home_page.500k_1m' ) ); ?></button>
              <button type="button" class="pill pill--outline filter-pill" data-budget="1000000,"><?php echo esc_html( pera_ml_ui( '$1m+', 'theme.template.home_page.1m' ) ); ?></button>
            </div>
          </div>

          <!-- ACTIONS -->
          <div class="filter-row filter-row--footer flex-center" style="margin-top: 16px;">
            <div class="form-actions flex-center">
              <button type="submit" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Search', 'theme.template.home_page.search' ) ); ?></button>
              <a class="btn btn btn--solid btn--blue" href="<?php echo esc_url( $archive_base_url . '#results' ); ?>">
                <?php echo esc_html( pera_ml_ui( 'All filters', 'theme.template.home_page.all_filters' ) ); ?>
              </a>
            </div>
          </div>

        </form>

      </div>
    </div>
  </section>

  <section class="section section-soft" aria-labelledby="home-buyer-routes-title">
    <div class="container">

      <header class="section-header section-header--center">
        <span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Buyer routes', 'theme.template.home_page.buyer_routes' ) ); ?></span>
        <h2 id="home-buyer-routes-title"><?php echo esc_html( pera_ml_ui( 'Start your Istanbul property journey', 'theme.template.home_page.start_your_istanbul_property_journey' ) ); ?></h2>
        <p class="lead">
          <?php echo esc_html( pera_ml_ui( 'Choose the route that best matches your reason for buying in Istanbul.', 'theme.template.home_page.choose_the_route_that_best_matches_your_reason_for_buying_in_istanbul' ) ); ?>
        </p>
      </header>

      <div class="cards-slider cards-slider--wide cards-slider--snap cards-slider--grid-2" aria-label="<?php echo esc_attr( pera_ml_ui( 'Buyer routes', 'theme.template.home_page.aria_label.buyer_routes' ) ); ?>">
        <article class="card-shell slider-card">
          <span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Citizenship', 'theme.template.home_page.citizenship' ) ); ?></span>
          <h3><?php echo esc_html( pera_ml_ui( 'Citizenship by Investment', 'theme.template.home_page.citizenship_by_investment' ) ); ?></h3>
          <p class="muted"><?php echo esc_html( pera_ml_ui( 'Approved real estate routes for buyers planning to apply for Turkish citizenship through property investment.', 'theme.template.home_page.approved_real_estate_routes_for_buyers_planning_to_apply_for_turkish_cit' ) ); ?></p>
          <div class="hero-actions">
            <a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url( '/citizenship-by-investment/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Explore citizenship', 'theme.template.home_page.explore_citizenship' ) ); ?></a>
            <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/turkish-citizenship-properties/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Request shortlist', 'theme.template.home_page.request_shortlist' ) ); ?></a>
          </div>
        </article>

        <article class="card-shell slider-card">
          <span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Investment', 'theme.template.home_page.investment' ) ); ?></span>
          <h3><?php echo esc_html( pera_ml_ui( 'Istanbul Investment Property', 'theme.template.home_page.istanbul_investment_property' ) ); ?></h3>
          <p class="muted"><?php echo esc_html( pera_ml_ui( 'Districts, projects and market insight for buyers focused on capital growth, rental demand and long-term value.', 'theme.template.home_page.districts_projects_and_market_insight_for_buyers_focused_on_capital_grow' ) ); ?></p>
          <div class="hero-actions">
            <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url( '/category/investment-advice/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Read investment advice', 'theme.template.home_page.read_investment_advice' ) ); ?></a>
            <a class="btn btn--ghost btn--green" href="<?php echo esc_url( home_url( '/property_tags/istanbul-investment-property-for-sale/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'View properties', 'theme.template.home_page.view_properties' ) ); ?></a>
          </div>
        </article>

        <article class="card-shell slider-card">
          <span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Luxury', 'theme.template.home_page.luxury' ) ); ?></span>
          <h3><?php echo esc_html( pera_ml_ui( 'Luxury Homes & Branded Residences', 'theme.template.home_page.luxury_homes_and_branded_residences' ) ); ?></h3>
          <p class="muted"><?php echo esc_html( pera_ml_ui( 'Bosphorus homes, branded residences and premium Istanbul addresses for lifestyle-led and high-value buyers.', 'theme.template.home_page.bosphorus_homes_branded_residences_and_premium_istanbul_addresses_for_li' ) ); ?></p>
          <div class="hero-actions">
            <a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url( '/istanbul-luxury-property/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'View luxury homes', 'theme.template.home_page.view_luxury_homes' ) ); ?></a>
            <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Request shortlist', 'theme.template.home_page.request_shortlist' ) ); ?></a>
          </div>
        </article>

        <article class="card-shell slider-card">
          <span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Buyer guide', 'theme.template.home_page.buyer_guide' ) ); ?></span>
          <h3><?php echo esc_html( pera_ml_ui( 'First-Time Foreign Buyers', 'theme.template.home_page.first_time_foreign_buyers' ) ); ?></h3>
          <p class="muted"><?php echo esc_html( pera_ml_ui( 'Practical guidance on title deed transfer, legal checks, taxes and safe property purchasing in Istanbul.', 'theme.template.home_page.practical_guidance_on_title_deed_transfer_legal_checks_taxes_and_safe_pr' ) ); ?></p>
          <div class="hero-actions">
            <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url( '/category/buyer-guides/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Read buyer guide', 'theme.template.home_page.read_buyer_guide' ) ); ?></a>
            <a class="btn btn--ghost btn--green" href="<?php echo esc_url( home_url( '/book-a-consultancy/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Book consultancy', 'theme.template.home_page.book_consultancy' ) ); ?></a>
          </div>
        </article>
      </div>
    </div>
  </section>


<?php
/* ======================================================
   FEATURED OPPORTUNITIES (HOME)
   Uses existing parts/property-card-v2
   ====================================================== */
$featured_count = 6;
$featured_query = new WP_Query( array(
  'post_type'           => 'property',
  'post_status'         => 'publish',
  'posts_per_page'      => $featured_count,
  'orderby'             => 'date',
  'order'               => 'DESC',
  'ignore_sticky_posts' => true,
) );
?>

<section class="section home-featured-properties">
  <div class="container">

    <div class="section-header section-header--center">
      <h2><?php echo esc_html( pera_ml_ui( 'Latest property for sale in Istanbul', 'theme.template.home_page.latest_property_for_sale_in_istanbul' ) ); ?></h2>
      <p class="lead"><?php echo esc_html( pera_ml_ui( 'The newest apartments, villas and investment opportunities recently added to our website.', 'theme.template.home_page.the_newest_apartments_villas_and_investment_opportunities_recently_added' ) ); ?></p>
    </div>

    <div class="cards-slider cards-slider--features cards-slider--snap cards-slider--grid-lg" aria-label="<?php echo esc_attr( pera_ml_ui( 'Featured properties', 'theme.template.home_page.aria_label.featured_properties' ) ); ?>">
      <?php if ( $featured_query->have_posts() ) : ?>
        <?php $featured_index = 0; ?>
        <?php while ( $featured_query->have_posts() ) : $featured_query->the_post(); ?>

          <div class="slider-card">
            <?php if ( $featured_index < 5 ) : ?>
              <?php
                pera_render_property_card( array(
                  'variant' => 'archive',
                ) );
              ?>
            <?php else : ?>
              <div class="property-card property-card--archive property-card--catalogue">
                <div class="property-card__inner property-card__inner--catalogue">
                  <div class="property-card__catalogue-body">
                    <span class="pill pill--brand pill--sm property-card__catalogue-kicker">
                      <span class="property-card__catalogue-kicker-default"><?php echo esc_html( pera_ml_ui( 'FULL CATALOGUE', 'theme.template.home_page.full_catalogue' ) ); ?></span>
                      <span class="property-card__catalogue-kicker-hover" aria-hidden="true"><?php echo esc_html( pera_ml_ui( '→ Browse all listings', 'theme.template.home_page.browse_all_listings' ) ); ?></span>
                    </span>
                    <h3><?php echo esc_html( pera_ml_ui( 'Browse all property for sale in Istanbul', 'theme.template.home_page.browse_all_property_for_sale_in_istanbul' ) ); ?></h3>
                    <p class="text-sm"><?php echo esc_html( pera_ml_ui( 'Apartments • Villas • Projects', 'theme.template.home_page.apartments_villas_projects' ) ); ?></p>
                    <div class="hero-actions">
                      <a class="btn btn--solid btn--blue" href="/property/"><?php echo esc_html( pera_ml_ui( 'See all listings', 'theme.template.home_page.see_all_listings' ) ); ?></a>
                      <a class="btn btn--ghost btn--blue" href="/property/#results"><?php echo esc_html( pera_ml_ui( 'Advanced search', 'theme.template.home_page.advanced_search' ) ); ?></a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <?php $featured_index++; ?>
        <?php endwhile; ?>
      <?php else : ?>
        <p class="no-results"><?php echo esc_html( pera_ml_ui( 'No featured properties available at the moment.', 'theme.template.home_page.no_featured_properties_available_at_the_moment' ) ); ?></p>
      <?php endif; ?>
    </div>

  </div>
</section>

<?php wp_reset_postdata(); ?>

<section class="section section-soft">
  <div class="container">
    <?php if ( trim( wp_strip_all_tags( $homepage_listing_intro ) ) !== '' ) : ?>
      <?php echo wp_kses_post( $homepage_listing_intro ); ?>
    <?php else : ?>
      <p class="text-soft">
        <?php echo esc_html( pera_ml_ui( 'Explore a wide range of', 'theme.template.home_page.explore_a_wide_range_of' ) ); ?> <strong><?php echo esc_html( pera_ml_ui( 'property for sale in Istanbul', 'theme.template.home_page.property_for_sale_in_istanbul' ) ); ?></strong><?php echo esc_html( pera_ml_ui( ', from centrally located apartments to carefully selected investment opportunities across the city. Our portfolio includes both ready properties and off-market deals, allowing buyers to compare options based on location, budget, and long-term potential. Below, you can view some of our latest opportunities, chosen for their value, positioning, and investment appeal.', 'theme.template.home_page.from_centrally_located_apartments_to_carefully_selected_investment_oppor' ) ); ?>
      </p>
    <?php endif; ?>
  </div>
</section>

<?php
/* ======================================================
   SPECIAL OFFERS (HOME)
   ====================================================== */
/* get_template_part( 'parts/home-special-offers' ); */

get_template_part( 'partials/home-latest-offers' );
?>



<!-- ======================================================
     FEATURED DISTRICTS (LOCATION GATEWAY)
     ====================================================== -->
<section class="section">
          <div class="container">
        
            <div class="section-header section-header--center">
              <h2><?php echo esc_html( pera_ml_ui( 'Best districts to buy property in Istanbul', 'theme.template.home_page.best_districts_to_buy_property_in_istanbul' ) ); ?></h2>
              <p class="lead">
                <?php echo esc_html( pera_ml_ui( 'Compare central and lifestyle-led areas where international buyers search for apartments for sale in Istanbul, with direct access to district listings and practical local guides.', 'theme.template.home_page.compare_central_and_lifestyle_led_areas_where_international_buyers_searc' ) ); ?>
              </p>
              <p class="text-soft">
                <?php echo esc_html( pera_ml_ui( 'If you are planning to buy Istanbul investment property, start with districts that match your goals for rental demand, resale potential, and day-to-day lifestyle. The districts below are among the most searched by Pera Property clients.', 'theme.template.home_page.if_you_are_planning_to_buy_istanbul_investment_property_start_with_distr' ) ); ?>
              </p>
            </div>
        
            <div class="pera-latest-offers-card-list pera-latest-offers-card-list--home cards-slider cards-slider--snap home-editorial-posts__slider home-districts-slider" aria-label="<?php echo esc_attr( pera_ml_ui( 'Featured districts in Istanbul for property buyers', 'theme.template.home_page.aria_label.featured_districts_in_istanbul_for_property_buyers' ) ); ?>">
        
          <!-- Beşiktaş -->
          <article class="card-shell slider-card">
            <span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Central', 'theme.template.home_page.central' ) ); ?></span>
        
            <h3 style="margin-top: 10px;"><?php echo esc_html( pera_ml_ui( 'Beşiktaş property', 'theme.template.home_page.be_ikta_property' ) ); ?></h3>
        
            <p class="muted" style="margin: 0;">
              <?php echo esc_html( pera_ml_ui( 'Beşiktaş is a top choice for property for sale in Istanbul, offering Bosphorus access, established neighbourhoods, and strong long-term demand.', 'theme.template.home_page.be_ikta_is_a_top_choice_for_property_for_sale_in_istanbul_offering_bosph' ) ); ?>
            </p>
        
            <div class="property-facilities__pills" style="margin-top: 12px;">
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'Bosphorus', 'theme.template.home_page.bosphorus' ) ); ?></span>
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'Universities', 'theme.template.home_page.universities' ) ); ?></span>
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'City life', 'theme.template.home_page.city_life' ) ); ?></span>
            </div>
        
            <div class="hero-actions" style="margin-top: 14px;">
              <a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url('/district/istanbul/besiktas/#results') ); ?>"><?php echo esc_html( pera_ml_ui( 'View listings', 'theme.template.home_page.view_listings' ) ); ?></a>
              <a class="btn btn--ghost btn--blue" href="https://www.peraproperty.com/besiktas-from-bronze-age-to-ottoman-palaces_51249/">
                <?php echo esc_html( pera_ml_ui( 'Area guide', 'theme.template.home_page.area_guide' ) ); ?>
              </a>
            </div>
          </article>
        
          <!-- Şişli -->
          <article class="card-shell slider-card">
            <span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Business & Lifestyle', 'theme.template.home_page.business_and_lifestyle' ) ); ?></span>
        
            <h3 style="margin-top: 10px;"><?php echo esc_html( pera_ml_ui( 'Şişli property', 'theme.template.home_page.i_li_property' ) ); ?></h3>
        
            <p class="muted" style="margin: 0;">
              <?php echo esc_html( pera_ml_ui( 'Şişli property attracts buyers seeking central Istanbul property close to business districts, shopping streets, and metro connections.', 'theme.template.home_page.i_li_property_attracts_buyers_seeking_central_istanbul_property_close_to' ) ); ?>
            </p>
        
            <div class="property-facilities__pills" style="margin-top: 12px;">
              <span class="pill pill--outline">Nişantaşı</span>
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'Metro', 'theme.template.home_page.metro' ) ); ?></span>
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'Urban living', 'theme.template.home_page.urban_living' ) ); ?></span>
            </div>
        
            <div class="hero-actions" style="margin-top: 14px;">
              <a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url('/district/istanbul/sisli/#results') ); ?>"><?php echo esc_html( pera_ml_ui( 'View listings', 'theme.template.home_page.view_listings' ) ); ?></a>
              <a class="btn btn--ghost btn--blue" href="https://www.peraproperty.com/sisli-the-heart-of-modern-istanbul_51392/">
                <?php echo esc_html( pera_ml_ui( 'Area guide', 'theme.template.home_page.area_guide' ) ); ?>
              </a>
            </div>
          </article>
        
          <!-- Kadıköy -->
          <article class="card-shell slider-card">
            <span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Residential & Cultural', 'theme.template.home_page.residential_and_cultural' ) ); ?></span>
        
            <h3 style="margin-top: 10px;"><?php echo esc_html( pera_ml_ui( 'Kadıköy property', 'theme.template.home_page.kad_k_y_property' ) ); ?></h3>
        
            <p class="muted" style="margin: 0;">
              <?php echo esc_html( pera_ml_ui( 'Kadıköy property is popular with buyers who want a residential environment, walkable neighbourhoods, and steady local demand on the Anatolian side.', 'theme.template.home_page.kad_k_y_property_is_popular_with_buyers_who_want_a_residential_environme' ) ); ?>
            </p>
        
            <div class="property-facilities__pills" style="margin-top: 12px;">
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'Walkable streets', 'theme.template.home_page.walkable_streets' ) ); ?></span>
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'Local demand', 'theme.template.home_page.local_demand' ) ); ?></span>
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'Anatolian side', 'theme.template.home_page.anatolian_side' ) ); ?></span>
            </div>
        
            <div class="hero-actions" style="margin-top: 14px;">
              <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url('/district/istanbul/kadikoy/#results') ); ?>"><?php echo esc_html( pera_ml_ui( 'View listings', 'theme.template.home_page.view_listings' ) ); ?></a>
              <a class="btn btn--ghost btn--green" href="<?php echo esc_url( home_url('/kadikoy-regional-guide-a-vibrant-hub-on-istanbuls-asian-side_51561/') ); ?>">
                <?php echo esc_html( pera_ml_ui( 'Area guide', 'theme.template.home_page.area_guide' ) ); ?>
              </a>
            </div>
          </article>

          <!-- Sarıyer -->
          <article class="card-shell slider-card">
            <span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'Bosphorus & Luxury', 'theme.template.home_page.bosphorus_and_luxury' ) ); ?></span>

            <h3 style="margin-top: 10px;"><?php echo esc_html( pera_ml_ui( 'Sarıyer property', 'theme.template.home_page.sar_yer_property' ) ); ?></h3>

            <p class="muted" style="margin: 0;">
              <?php echo esc_html( pera_ml_ui( 'Sarıyer property appeals to buyers seeking Bosphorus lifestyle, green residential neighbourhoods, luxury compounds, and long-term prestige on Istanbul’s European side.', 'theme.template.home_page.sar_yer_property_appeals_to_buyers_seeking_bosphorus_lifestyle_green_res' ) ); ?>
            </p>

            <div class="property-facilities__pills" style="margin-top: 12px;">
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'Bosphorus', 'theme.template.home_page.bosphorus' ) ); ?></span>
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'Luxury homes', 'theme.template.home_page.luxury_homes' ) ); ?></span>
              <span class="pill pill--outline"><?php echo esc_html( pera_ml_ui( 'European side', 'theme.template.home_page.european_side' ) ); ?></span>
            </div>

            <div class="hero-actions" style="margin-top: 14px;">
              <a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url('/district/istanbul/sariyer/#results') ); ?>"><?php echo esc_html( pera_ml_ui( 'View listings', 'theme.template.home_page.view_listings' ) ); ?></a>
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url('/istanbul-luxury-property/') ); ?>">
                <?php echo esc_html( pera_ml_ui( 'Luxury guide', 'theme.template.home_page.luxury_guide' ) ); ?>
              </a>
            </div>
          </article>
        
        </div>


    <div class="hero-actions flex-center" style="margin-top: 18px;">
      <a class="btn btn--solid btn--blue" href="/property/"><?php echo esc_html( pera_ml_ui( 'Browse all Istanbul property listings', 'theme.template.home_page.browse_all_istanbul_property_listings' ) ); ?></a>
      <a class="btn btn--solid btn--green" href="/contact-us/"><?php echo esc_html( pera_ml_ui( 'Get district advice', 'theme.template.home_page.get_district_advice' ) ); ?></a>
    </div>

  </div>
</section>  

<?php
/* ======================================================
   HOME EDITORIAL POSTS
   ====================================================== */
get_template_part( 'parts/home-editorial-posts' );
?>

<!-- ======================================================
     BUYER JOURNEY
     ====================================================== -->

<section class="section">
  <div class="container">

    <header class="section-header section-header--center">
      <h2><?php echo esc_html( pera_ml_ui( 'Buyer journey', 'theme.template.home_page.buyer_journey' ) ); ?></h2>
      <p>
        <?php echo esc_html( pera_ml_ui( 'A clear, structured process from initial consultation to ownership —
        designed to reduce risk and remove uncertainty.', 'theme.template.home_page.a_clear_structured_process_from_initial_consultation_to_ownership_design' ) ); ?>
      </p>
    </header>

    <div class="info-steps">

      <!-- STEP 1 -->
      <article class="info-step">
        <div class="info-step-icon">
          <span class="info-step-number">1</span>
        </div>
        <div class="info-step-body">
          <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Strategy & shortlist', 'theme.template.home_page.strategy_and_shortlist' ) ); ?></h3>
          <p class="info-step-text">
            <?php echo esc_html( pera_ml_ui( 'We define your objectives — lifestyle, rental yield, or capital growth —
            and curate suitable projects and resale opportunities across Istanbul.', 'theme.template.home_page.we_define_your_objectives_lifestyle_rental_yield_or_capital_growth_and_c' ) ); ?>
          </p>
        </div>
      </article>

      <!-- STEP 2 -->
      <article class="info-step">
        <div class="info-step-icon">
          <span class="info-step-number">2</span>
        </div>
        <div class="info-step-body">
          <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Viewings & due diligence', 'theme.template.home_page.viewings_and_due_diligence' ) ); ?></h3>
          <p class="info-step-text">
            <?php echo esc_html( pera_ml_ui( 'We coordinate viewings (in person or remotely), explain pricing,
            and guide legal and technical checks with trusted professionals.', 'theme.template.home_page.we_coordinate_viewings_in_person_or_remotely_explain_pricing_and_guide_l' ) ); ?>
          </p>
        </div>
      </article>

      <!-- STEP 3 -->
      <article class="info-step">
        <div class="info-step-icon">
          <span class="info-step-number">3</span>
        </div>
        <div class="info-step-body">
          <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Negotiation & purchase', 'theme.template.home_page.negotiation_and_purchase' ) ); ?></h3>
          <p class="info-step-text">
            <?php echo esc_html( pera_ml_ui( 'We manage negotiations, payment milestones, and the purchase process
            through to title deed registration.', 'theme.template.home_page.we_manage_negotiations_payment_milestones_and_the_purchase_process_throu' ) ); ?>
          </p>
        </div>
      </article>

      <!-- STEP 4 -->
      <article class="info-step">
        <div class="info-step-icon">
          <span class="info-step-number">4</span>
        </div>
        <div class="info-step-body">
          <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'After-sales support', 'theme.template.home_page.after_sales_support' ) ); ?></h3>
          <p class="info-step-text">
            <?php echo esc_html( pera_ml_ui( 'Beyond completion, we assist with rentals, property management,
            resale strategy, and ongoing advisory support as your plans evolve.', 'theme.template.home_page.beyond_completion_we_assist_with_rentals_property_management_resale_stra' ) ); ?>
          </p>
        </div>
      </article>

    </div>

    <div class="hero-actions flex-center" style="margin-top: 16px;">
      <a class="btn btn--solid btn--green" href="/contact-us/"><?php echo esc_html( pera_ml_ui( 'Speak to an advisor', 'theme.template.home_page.speak_to_an_advisor' ) ); ?></a>
    </div>

  </div>
</section>


<!-- ======================================================
     SELL WITH PERA (HOMEPAGE)
     ====================================================== -->
<section class="content" id="sell-with-pera">
  <div class="content-panel-box">
    <div class="content-panel-grid">

      <div class="content-panel-left">
        <header class="section-header">
          <h2><?php echo esc_html( pera_ml_ui( 'Own property in Istanbul?', 'theme.template.home_page.own_property_in_istanbul' ) ); ?></h2>
          <p>
            <?php echo esc_html( pera_ml_ui( 'Whether you plan to sell your Istanbul property or rent it out, Pera Property supports local and overseas owners with clear pricing advice, qualified demand and practical, hands-on execution.', 'theme.template.home_page.whether_you_plan_to_sell_your_istanbul_property_or_rent_it_out_pera_prop' ) ); ?>
          </p>
        </header>

        <ul class="checklist checklist--circle">
          <li><?php echo esc_html( pera_ml_ui( 'Realistic Istanbul property valuation and pricing strategy', 'theme.template.home_page.realistic_istanbul_property_valuation_and_pricing_strategy' ) ); ?></li>
          <li><?php echo esc_html( pera_ml_ui( 'Professional marketing and qualified buyer or tenant enquiries', 'theme.template.home_page.professional_marketing_and_qualified_buyer_or_tenant_enquiries' ) ); ?></li>
          <li><?php echo esc_html( pera_ml_ui( 'End-to-end support through negotiation, contracts and handover', 'theme.template.home_page.end_to_end_support_through_negotiation_contracts_and_handover' ) ); ?></li>
        </ul>

        <p style="margin-top: 12px; margin-bottom: 0;">
          <a href="/sell-your-istanbul-real-estate/"><?php echo esc_html( pera_ml_ui( 'Sell your property in Istanbul with local experts', 'theme.template.home_page.sell_your_property_in_istanbul_with_local_experts' ) ); ?></a>
        </p>
      </div>

      <div class="content-panel-right">
        <div class="signoff-card">
          <div class="signoff-avatar">
            <?php
              echo wp_get_attachment_image(
                55492,
                'thumbnail',
                false,
                array(
                  'class'   => 'signoff-avatar-img',
                  'loading' => 'lazy',
                  'alt'     => 'D Koray Dillioglu',
                )
              );
            ?>
          </div>
          <div class="signoff-text">
            <h5>D Koray Dillioglu</h5>
            <p><?php echo esc_html( pera_ml_ui( '– Director @ Pera Property', 'theme.template.home_page.director_pera_property' ) ); ?></p>
          </div>
        </div>

        <p class="muted" style="margin-top: 10px; margin-bottom: 0;">
          <?php echo esc_html( pera_ml_ui( 'Need full support as an owner? Explore', 'theme.template.home_page.need_full_support_as_an_owner_explore' ) ); ?> <a href="<?php echo esc_url( home_url( '/rent-your-istanbul-real-estate/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'property management in Istanbul', 'theme.template.home_page.property_management_in_istanbul' ) ); ?></a> <?php echo esc_html( pera_ml_ui( 'or request a valuation.', 'theme.template.home_page.or_request_a_valuation' ) ); ?>
        </p>

        <div class="hero-actions flex-center">
          <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url( '/sell-your-istanbul-real-estate/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Get a Free Valuation', 'theme.template.home_page.get_a_free_valuation' ) ); ?></a>
          <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/rent-your-istanbul-real-estate/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Explore Property Management', 'theme.template.home_page.explore_property_management' ) ); ?></a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ABOUT + HOW WE WORK -->
<section class="section">
  <div class="container">
    <div class="content-panel-grid">

      <!-- LEFT SIDE: ABOUT + SIGNOFF -->
      <div class="content-panel-left">

        <div class="section-header">
          <h2><?php echo esc_html( pera_ml_ui( 'ABOUT OUR COMPANY', 'theme.template.home_page.about_our_company' ) ); ?></h2>

          <p>
            <?php echo esc_html( pera_ml_ui( 'Pera Property is a consultancy-led real estate agency focused exclusively on Istanbul.
            We work with both new developments and resale properties, advising clients from initial
            strategy through to title deed. Prefer a structured, free strategy session first?', 'theme.template.home_page.pera_property_is_a_consultancy_led_real_estate_agency_focused_exclusivel' ) ); ?>
            <a href="/book-a-consultancy/"><?php echo esc_html( pera_ml_ui( 'Book a consultancy', 'theme.template.home_page.book_a_consultancy' ) ); ?></a> <?php echo esc_html( pera_ml_ui( 'to validate your plan before viewing properties.', 'theme.template.home_page.to_validate_your_plan_before_viewing_properties' ) ); ?>
          </p>

          <p>
            <em>
              <?php echo esc_html( pera_ml_ui( 'Our impartial, whole-of-market approach ensures each client reaches the optimal outcome
              based on their goals — not sales pressure.', 'theme.template.home_page.our_impartial_whole_of_market_approach_ensures_each_client_reaches_the_o' ) ); ?>
            </em>
          </p>
        </div>

        <div class="signoff-card">
          <div class="signoff-avatar">
            <?php
              echo wp_get_attachment_image(
                55492,
                'thumbnail',
                false,
                array(
                  'class'   => 'signoff-avatar-img',
                  'loading' => 'lazy',
                  'alt'     => 'D Koray Dillioglu',
                )
              );
            ?>
          </div>

          <div class="signoff-text">
            <h5>D Koray Dillioglu</h5>
            <p><?php echo esc_html( pera_ml_ui( '– Director @ Pera Property', 'theme.template.home_page.director_pera_property' ) ); ?></p>
          </div>
        </div>

      </div>

      <!-- RIGHT SIDE: HOW WE WORK -->
      <div class="content-panel-right">

        <div class="section-header">
          <h3><?php echo esc_html( pera_ml_ui( 'How we help you buy in Istanbul', 'theme.template.home_page.how_we_help_you_buy_in_istanbul' ) ); ?></h3>
          <p>
            <?php echo esc_html( pera_ml_ui( 'A clear, structured process designed to reduce risk and remove uncertainty.', 'theme.template.home_page.a_clear_structured_process_designed_to_reduce_risk_and_remove_uncertaint' ) ); ?>
          </p>
        </div>

        <ol class="process-steps">
          <li>
            <strong><?php echo esc_html( pera_ml_ui( 'Understand your objectives', 'theme.template.home_page.understand_your_objectives' ) ); ?></strong>

          </li>

          <li>
            <strong><?php echo esc_html( pera_ml_ui( 'Shortlist the right options', 'theme.template.home_page.shortlist_the_right_options' ) ); ?></strong>
          
          </li>

          <li>
            <strong><?php echo esc_html( pera_ml_ui( 'Guide you through to completion', 'theme.template.home_page.guide_you_through_to_completion' ) ); ?></strong>
         
          </li>
          <li>
            <strong><?php echo esc_html( pera_ml_ui( 'Manage your investment for as long as you need it', 'theme.template.home_page.manage_your_investment_for_as_long_as_you_need_it' ) ); ?></strong>
         
          </li>
        </ol>

        <div class="hero-actions" style="margin-top: 16px;">
          <a class="btn btn--solid btn--green" href="/contact-us/"><?php echo esc_html( pera_ml_ui( 'Speak to an advisor', 'theme.template.home_page.speak_to_an_advisor' ) ); ?></a>
          <a class="btn btn--ghost btn--blue" href="/book-a-consultancy/"><?php echo esc_html( pera_ml_ui( 'Book a consultancy', 'theme.template.home_page.book_a_consultancy' ) ); ?></a>
        </div>

      </div>
    </div>
  </div>
</section>

<?php if ( is_front_page() ) : ?>
<?php
$homepage_faq_items = array();

if ( function_exists( 'get_field' ) ) {
  $faq_rows = $front_page_id > 0
    ? get_field( 'faq', $front_page_id )
    : get_field( 'faq' );

  if ( is_array( $faq_rows ) ) {
    foreach ( $faq_rows as $faq_row ) {
      $question = isset( $faq_row['question'] ) ? trim( (string) $faq_row['question'] ) : '';
      $answer   = isset( $faq_row['answer'] ) ? trim( (string) $faq_row['answer'] ) : '';

      if ( $question === '' || $answer === '' ) {
        continue;
      }

      $homepage_faq_items[] = array(
        'question' => $question,
        'answer'   => $answer,
      );
    }
  }
}
?>
<section class="section section-soft">
  <div class="container">
    <div class="section-header">
      <h2><?php echo esc_html( pera_ml_ui( 'Property for Sale in Istanbul: Investment & Lifestyle Opportunities', 'theme.template.home_page.property_for_sale_in_istanbul_investment_and_lifestyle_opportunities' ) ); ?></h2>
    </div>

    <p class="text-soft">
      <?php echo esc_html( pera_ml_ui( 'At Pera Property, we advise buyers on how to navigate', 'theme.template.home_page.at_pera_property_we_advise_buyers_on_how_to_navigate' ) ); ?> <strong><?php echo esc_html( pera_ml_ui( 'property for sale in Istanbul', 'theme.template.home_page.property_for_sale_in_istanbul' ) ); ?></strong> <?php echo esc_html( pera_ml_ui( 'with a clear strategy first — then shortlist options that match lifestyle goals, rental expectations, and budget. From central homes to investment-led developments, the market offers opportunities on both the European and Asian sides, each with different upside depending on your priorities.', 'theme.template.home_page.with_a_clear_strategy_first_then_shortlist_options_that_match_lifestyle_' ) ); ?>
    </p>

    <?php if ( trim( wp_strip_all_tags( $homepage_bottom_seo_text ) ) !== '' ) : ?>
      <?php echo wp_kses_post( $homepage_bottom_seo_text ); ?>
    <?php else : ?>
      <p class="text-soft">
        <?php echo esc_html( pera_ml_ui( 'For clients focused on long-term value,', 'theme.template.home_page.for_clients_focused_on_long_term_value' ) ); ?> <strong><?php echo esc_html( pera_ml_ui( 'apartments for sale in Istanbul', 'theme.template.home_page.apartments_for_sale_in_istanbul' ) ); ?></strong> <?php echo esc_html( pera_ml_ui( 'in districts such as', 'theme.template.home_page.in_districts_such_as' ) ); ?> <a href="<?php echo esc_url( home_url('/district/istanbul/besiktas/') ); ?>">Beşiktaş</a> <?php echo esc_html( pera_ml_ui( 'and', 'theme.template.home_page.and' ) ); ?> <a href="<?php echo esc_url( home_url('/district/istanbul/sisli/') ); ?>">Şişli</a> <?php echo esc_html( pera_ml_ui( 'are often preferred for access to business hubs and daily convenience, while', 'theme.template.home_page.are_often_preferred_for_access_to_business_hubs_and_daily_convenience_wh' ) ); ?> <a href="<?php echo esc_url( home_url('/district/istanbul/kadikoy/') ); ?>">Kadıköy</a> <?php echo esc_html( pera_ml_ui( 'suits buyers who want a stronger residential and cultural profile.', 'theme.template.home_page.suits_buyers_who_want_a_stronger_residential_and_cultural_profile' ) ); ?>
      </p>
    <?php endif; ?>

    <p class="text-soft">
      <?php echo esc_html( pera_ml_ui( 'If you plan to', 'theme.template.home_page.if_you_plan_to' ) ); ?> <strong><?php echo esc_html( pera_ml_ui( 'buy property in Istanbul', 'theme.template.home_page.buy_property_in_istanbul' ) ); ?></strong><?php echo esc_html( pera_ml_ui( ', we recommend assessing location fundamentals, developer track record, exit liquidity, and realistic rental performance before committing. New projects can offer modern amenities and appreciation potential, while selected resale stock may provide faster income stability.', 'theme.template.home_page.we_recommend_assessing_location_fundamentals_developer_track_record_exit' ) ); ?>
    </p>

    <p class="text-soft">
      <?php echo esc_html( pera_ml_ui( 'Istanbul is also a major destination for buyers interested in residency and citizenship options. Through the', 'theme.template.home_page.istanbul_is_also_a_major_destination_for_buyers_interested_in_residency_' ) ); ?> <a href="<?php echo esc_url( home_url('/citizenship-by-investment/') ); ?>"><?php echo esc_html( pera_ml_ui( 'Turkish Citizenship by Investment', 'theme.template.home_page.turkish_citizenship_by_investment' ) ); ?></a> <?php echo esc_html( pera_ml_ui( 'program, eligible property purchases can qualify investors for a Turkish passport, making real estate not only a lifestyle decision but also a strategic investment.', 'theme.template.home_page.program_eligible_property_purchases_can_qualify_investors_for_a_turkish_' ) ); ?>
    </p>
  </div>
</section>

<?php if ( ! empty( $homepage_faq_items ) ) : ?>
  <section class="faq-section section">
    <div class="container">
      <h2><?php echo esc_html( pera_ml_ui( 'FAQs About Buying Property in Istanbul', 'theme.template.home_page.faqs_about_buying_property_in_istanbul' ) ); ?></h2>

      <div class="faq-accordion">
        <?php foreach ( $homepage_faq_items as $faq_index => $faq_item ) : ?>
          <details class="faq-item"<?php echo $faq_index === 0 ? ' open' : ''; ?>>
            <summary><?php echo esc_html( $faq_item['question'] ); ?></summary>
            <div class="faq-answer">
              <?php echo wp_kses_post( wpautop( $faq_item['answer'] ) ); ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>
<?php endif; ?>

</main>

<?php get_footer();
