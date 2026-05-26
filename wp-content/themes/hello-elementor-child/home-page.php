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
<section class="hero hero--center" aria-label="Homepage hero search">
    <?php if ( $hero_img_url ) : ?>
      <img class="hero-media" src="<?php echo esc_url( $hero_img_url ); ?>" alt="" aria-hidden="true">
    <?php endif; ?>
    <div class="hero-overlay" aria-hidden="true"></div>

    <div class="hero-content">

      <h1>Find Property in Istanbul</h1>

      <div class="lead">
        <p>
          <?php echo $homepage_hero_subtext !== ''
            ? esc_html( $homepage_hero_subtext )
            : 'Explore the best property for sale in Istanbul, including modern apartments, luxury residences, and high-yield investment opportunities across the city’s most desirable districts.'; ?>
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
            <div class="filter-group__label">Bedrooms</div>

            <div class="filter-pill-row flex-center" role="radiogroup" aria-label="Bedrooms">

              <label class="pill pill--outline filter-pill pill--active">
                <input type="radio" name="v2_beds" value="" checked>
                <span>
                  <svg class="icon icon-bed" aria-hidden="true">
                    <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-bed' ); ?>"></use>
                  </svg>
                  Any
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
            <div class="filter-group__label">Location</div>

            <div class="filter-pill-row flex-center">
              <button
                type="button"
                class="pill pill--outline filter-pill filter-pill--all pill--active"
                data-clear-group="district"
              >
                <span>Any</span>
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
                  <span><?php echo esc_html( $term->name ); ?></span>
                </label>
              <?php endforeach; ?>

              <a class="pill pill--outline" href="<?php echo esc_url( $archive_base_url ); ?>">
                More areas
              </a>
            </div>
          </div>

          <!-- BUDGET (V2 uses min_price/max_price; overlap logic on v2_price_usd_min/max) -->
          <div class="filter-group text-center">
            <div class="filter-group__label">Budget (USD)</div>

            <input type="hidden" name="min_price" id="hero-min-price" value="">
            <input type="hidden" name="max_price" id="hero-max-price" value="">

            <div class="filter-pill-row flex-center" role="radiogroup" aria-label="Budget presets">
              <button type="button" class="pill pill--outline filter-pill pill--active" data-budget="">Any</button>
              <button type="button" class="pill pill--outline filter-pill" data-budget="0,250000">Up to $250k</button>
              <button type="button" class="pill pill--outline filter-pill" data-budget="250000,500000">$250k–$500k</button>
              <button type="button" class="pill pill--outline filter-pill" data-budget="500000,1000000">$500k–$1m</button>
              <button type="button" class="pill pill--outline filter-pill" data-budget="1000000,">$1m+</button>
            </div>
          </div>

          <!-- ACTIONS -->
          <div class="filter-row filter-row--footer flex-center" style="margin-top: 16px;">
            <div class="form-actions flex-center">
              <button type="submit" class="btn btn--solid btn--green">Search</button>
              <a class="btn btn btn--solid btn--blue" href="<?php echo esc_url( $archive_base_url . '#results' ); ?>">
                All filters
              </a>
            </div>
          </div>

        </form>

      </div>
    </div>
  </section>

<?php if ( current_user_can( 'manage_options' ) ) : ?>
  <section class="section section-soft" aria-labelledby="home-buyer-routes-title">
    <div class="container">

      <header class="section-header section-header--center">
        <span class="pill pill--brand pill--sm">Buyer routes</span>
        <h2 id="home-buyer-routes-title">Start your Istanbul property journey</h2>
        <p class="lead">
          Choose the route that best matches your reason for buying in Istanbul.
        </p>
      </header>

      <div class="cards-slider cards-slider--wide cards-slider--snap cards-slider--grid-lg" aria-label="Buyer routes">
        <article class="card-shell slider-card">
          <span class="pill pill--brand pill--sm">Citizenship</span>
          <h3>Citizenship by Investment</h3>
          <p class="muted">Approved real estate routes for buyers planning to apply for Turkish citizenship through property investment.</p>
          <div class="hero-actions">
            <a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url( '/citizenship-by-investment/' ) ); ?>">Explore citizenship</a>
            <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Ask an advisor</a>
          </div>
        </article>

        <article class="card-shell slider-card">
          <span class="pill pill--brand pill--sm">Investment</span>
          <h3>Istanbul Investment Property</h3>
          <p class="muted">Districts, projects and market insight for buyers focused on capital growth, rental demand and long-term value.</p>
          <div class="hero-actions">
            <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url( '/category/investment-advice/' ) ); ?>">Read investment advice</a>
            <a class="btn btn--ghost btn--green" href="<?php echo esc_url( home_url( '/property/#results' ) ); ?>">View properties</a>
          </div>
        </article>

        <article class="card-shell slider-card">
          <span class="pill pill--brand pill--sm">Luxury</span>
          <h3>Luxury Homes &amp; Branded Residences</h3>
          <p class="muted">Bosphorus homes, branded residences and premium Istanbul addresses for lifestyle-led and high-value buyers.</p>
          <div class="hero-actions">
            <a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url( '/property_tags/istanbul-luxury-property-for-sale/' ) ); ?>">View luxury homes</a>
            <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Request shortlist</a>
          </div>
        </article>

        <article class="card-shell slider-card">
          <span class="pill pill--brand pill--sm">Buyer guide</span>
          <h3>First-Time Foreign Buyers</h3>
          <p class="muted">Practical guidance on title deed transfer, legal checks, taxes and safe property purchasing in Istanbul.</p>
          <div class="hero-actions">
            <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url( '/buyers-guide/' ) ); ?>">Read buyer guide</a>
            <a class="btn btn--ghost btn--green" href="<?php echo esc_url( home_url( '/book-a-consultancy/' ) ); ?>">Book consultancy</a>
          </div>
        </article>
      </div>
    </div>
  </section>
<?php endif; ?>


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
      <h2>Latest property for sale in Istanbul</h2>
      <p class="lead">The newest apartments, villas and investment opportunities recently added to our website.</p>
    </div>

    <div class="cards-slider cards-slider--features cards-slider--snap cards-slider--grid-lg" aria-label="Featured properties">
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
                      <span class="property-card__catalogue-kicker-default">FULL CATALOGUE</span>
                      <span class="property-card__catalogue-kicker-hover" aria-hidden="true">→ Browse all listings</span>
                    </span>
                    <h3>Browse all property for sale in Istanbul</h3>
                    <p class="text-sm">Apartments • Villas • Projects</p>
                    <div class="hero-actions">
                      <a class="btn btn--solid btn--blue" href="/property/">See all listings</a>
                      <a class="btn btn--ghost btn--blue" href="/property/#results">Advanced search</a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <?php $featured_index++; ?>
        <?php endwhile; ?>
      <?php else : ?>
        <p class="no-results">No featured properties available at the moment.</p>
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
        Explore a wide range of <strong>property for sale in Istanbul</strong>, from centrally located apartments to carefully selected investment opportunities across the city. Our portfolio includes both ready properties and off-market deals, allowing buyers to compare options based on location, budget, and long-term potential. Below, you can view some of our latest opportunities, chosen for their value, positioning, and investment appeal.
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
              <h2>Best districts to buy property in Istanbul</h2>
              <p class="lead">
                Compare central and lifestyle-led areas where international buyers search for apartments for sale in Istanbul, with direct access to district listings and practical local guides.
              </p>
              <p class="text-soft">
                If you are planning to buy Istanbul investment property, start with districts that match your goals for rental demand, resale potential, and day-to-day lifestyle. The districts below are among the most searched by Pera Property clients.
              </p>
            </div>
        
            <div class="cards-slider cards-slider--wide cards-slider--snap cards-slider--grid-lg" aria-label="Featured districts in Istanbul for property buyers">
        
          <!-- Beşiktaş -->
          <article class="card-shell slider-card">
            <span class="pill pill--brand pill--sm">Central</span>
        
            <h3 style="margin-top: 10px;">Beşiktaş property</h3>
        
            <p class="muted" style="margin: 0;">
              Beşiktaş is a top choice for property for sale in Istanbul, offering Bosphorus access, established neighbourhoods, and strong long-term demand.
            </p>
        
            <div class="property-facilities__pills" style="margin-top: 12px;">
              <span class="pill pill--outline">Bosphorus</span>
              <span class="pill pill--outline">Universities</span>
              <span class="pill pill--outline">City life</span>
            </div>
        
            <div class="hero-actions" style="margin-top: 14px;">
              <a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url('/district/istanbul/besiktas/#results') ); ?>">View listings</a>
              <a class="btn btn--ghost btn--blue" href="https://www.peraproperty.com/besiktas-from-bronze-age-to-ottoman-palaces_51249/">
                Area guide
              </a>
            </div>
          </article>
        
          <!-- Şişli -->
          <article class="card-shell slider-card">
            <span class="pill pill--brand pill--sm">Business &amp; Lifestyle</span>
        
            <h3 style="margin-top: 10px;">Şişli property</h3>
        
            <p class="muted" style="margin: 0;">
              Şişli property attracts buyers seeking central Istanbul property close to business districts, shopping streets, and metro connections.
            </p>
        
            <div class="property-facilities__pills" style="margin-top: 12px;">
              <span class="pill pill--outline">Nişantaşı</span>
              <span class="pill pill--outline">Metro</span>
              <span class="pill pill--outline">Urban living</span>
            </div>
        
            <div class="hero-actions" style="margin-top: 14px;">
              <a class="btn btn--solid btn--blue" href="<?php echo esc_url( home_url('/district/istanbul/sisli/#results') ); ?>">View listings</a>
              <a class="btn btn--ghost btn--blue" href="https://www.peraproperty.com/sisli-the-heart-of-modern-istanbul_51392/">
                Area guide
              </a>
            </div>
          </article>
        
          <!-- Kadıköy -->
          <article class="card-shell slider-card">
            <span class="pill pill--brand pill--sm">Residential &amp; Cultural</span>
        
            <h3 style="margin-top: 10px;">Kadıköy property</h3>
        
            <p class="muted" style="margin: 0;">
              Kadıköy property is popular with buyers who want a residential environment, walkable neighbourhoods, and steady local demand on the Anatolian side.
            </p>
        
            <div class="property-facilities__pills" style="margin-top: 12px;">
              <span class="pill pill--outline">Walkable streets</span>
              <span class="pill pill--outline">Local demand</span>
              <span class="pill pill--outline">Anatolian side</span>
            </div>
        
            <div class="hero-actions" style="margin-top: 14px;">
              <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url('/district/istanbul/kadikoy/#results') ); ?>">View listings</a>
              <a class="btn btn--ghost btn--green" href="<?php echo esc_url( home_url('/kadikoy-regional-guide-a-vibrant-hub-on-istanbuls-asian-side_51561/') ); ?>">
                Area guide
              </a>
            </div>
          </article>
        
        </div>


    <div class="hero-actions flex-center" style="margin-top: 18px;">
      <a class="btn btn--solid btn--blue" href="/property/">Browse all Istanbul property listings</a>
      <a class="btn btn--solid btn--green" href="/contact-us/">Get district advice</a>
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
      <h2>Buyer journey</h2>
      <p>
        A clear, structured process from initial consultation to ownership —
        designed to reduce risk and remove uncertainty.
      </p>
    </header>

    <div class="info-steps">

      <!-- STEP 1 -->
      <article class="info-step">
        <div class="info-step-icon">
          <span class="info-step-number">1</span>
        </div>
        <div class="info-step-body">
          <h3 class="info-step-title">Strategy &amp; shortlist</h3>
          <p class="info-step-text">
            We define your objectives — lifestyle, rental yield, or capital growth —
            and curate suitable projects and resale opportunities across Istanbul.
          </p>
        </div>
      </article>

      <!-- STEP 2 -->
      <article class="info-step">
        <div class="info-step-icon">
          <span class="info-step-number">2</span>
        </div>
        <div class="info-step-body">
          <h3 class="info-step-title">Viewings &amp; due diligence</h3>
          <p class="info-step-text">
            We coordinate viewings (in person or remotely), explain pricing,
            and guide legal and technical checks with trusted professionals.
          </p>
        </div>
      </article>

      <!-- STEP 3 -->
      <article class="info-step">
        <div class="info-step-icon">
          <span class="info-step-number">3</span>
        </div>
        <div class="info-step-body">
          <h3 class="info-step-title">Negotiation &amp; purchase</h3>
          <p class="info-step-text">
            We manage negotiations, payment milestones, and the purchase process
            through to title deed registration.
          </p>
        </div>
      </article>

      <!-- STEP 4 -->
      <article class="info-step">
        <div class="info-step-icon">
          <span class="info-step-number">4</span>
        </div>
        <div class="info-step-body">
          <h3 class="info-step-title">After-sales support</h3>
          <p class="info-step-text">
            Beyond completion, we assist with rentals, property management,
            resale strategy, and ongoing advisory support as your plans evolve.
          </p>
        </div>
      </article>

    </div>

    <div class="hero-actions flex-center" style="margin-top: 16px;">
      <a class="btn btn--solid btn--green" href="/contact-us/">Speak to an advisor</a>
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
          <h2>Own property in Istanbul?</h2>
          <p>
            Whether you plan to sell your Istanbul property or rent it out, Pera Property supports local and overseas owners with clear pricing advice, qualified demand and practical, hands-on execution.
          </p>
        </header>

        <ul class="checklist">
          <li>
            <svg class="icon icon-tick" aria-hidden="true">
              <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
            </svg>
            Realistic Istanbul property valuation and pricing strategy
          </li>
          <li>
            <svg class="icon icon-tick" aria-hidden="true">
              <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
            </svg>
            Professional marketing and qualified buyer or tenant enquiries
          </li>
          <li>
            <svg class="icon icon-tick" aria-hidden="true">
              <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
            </svg>
            End-to-end support through negotiation, contracts and handover
          </li>
        </ul>

        <p style="margin-top: 12px; margin-bottom: 0;">
          <a href="/sell-your-istanbul-real-estate/">Sell your property in Istanbul with local experts</a>
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
            <p>– Director @ Pera Property</p>
          </div>
        </div>

        <p class="muted" style="margin-top: 10px; margin-bottom: 0;">
          Need full support as an owner? Explore <a href="<?php echo esc_url( home_url( '/rent-your-istanbul-real-estate/' ) ); ?>">property management in Istanbul</a> or request a valuation.
        </p>

        <div class="hero-actions flex-center">
          <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url( '/sell-your-istanbul-real-estate/' ) ); ?>">Get a Free Valuation</a>
          <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/rent-your-istanbul-real-estate/' ) ); ?>">Explore Property Management</a>
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
          <h2>ABOUT OUR COMPANY</h2>

          <p>
            Pera Property is a consultancy-led real estate agency focused exclusively on Istanbul.
            We work with both new developments and resale properties, advising clients from initial
            strategy through to title deed. Prefer a structured, free strategy session first?
            <a href="/book-a-consultancy/">Book a consultancy</a> to validate your plan before viewing properties.
          </p>

          <p>
            <em>
              Our impartial, whole-of-market approach ensures each client reaches the optimal outcome
              based on their goals — not sales pressure.
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
            <p>– Director @ Pera Property</p>
          </div>
        </div>

      </div>

      <!-- RIGHT SIDE: HOW WE WORK -->
      <div class="content-panel-right">

        <div class="section-header">
          <h3>How we help you buy in Istanbul</h3>
          <p>
            A clear, structured process designed to reduce risk and remove uncertainty.
          </p>
        </div>

        <ol class="process-steps">
          <li>
            <strong>Understand your objectives</strong>

          </li>

          <li>
            <strong>Shortlist the right options</strong>
          
          </li>

          <li>
            <strong>Guide you through to completion</strong>
         
          </li>
          <li>
            <strong>Manage your investment for as long as you need it</strong>
         
          </li>
        </ol>

        <div class="hero-actions" style="margin-top: 16px;">
          <a class="btn btn--solid btn--green" href="/contact-us/">Speak to an advisor</a>
          <a class="btn btn--ghost btn--blue" href="/book-a-consultancy/">Book a consultancy</a>
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
      <h2>Property for Sale in Istanbul: Investment &amp; Lifestyle Opportunities</h2>
    </div>

    <p class="text-soft">
      At Pera Property, we advise buyers on how to navigate <strong>property for sale in Istanbul</strong> with a clear strategy first — then shortlist options that match lifestyle goals, rental expectations, and budget. From central homes to investment-led developments, the market offers opportunities on both the European and Asian sides, each with different upside depending on your priorities.
    </p>

    <?php if ( trim( wp_strip_all_tags( $homepage_bottom_seo_text ) ) !== '' ) : ?>
      <?php echo wp_kses_post( $homepage_bottom_seo_text ); ?>
    <?php else : ?>
      <p class="text-soft">
        For clients focused on long-term value, <strong>apartments for sale in Istanbul</strong> in districts such as <a href="<?php echo esc_url( home_url('/district/istanbul/besiktas/') ); ?>">Beşiktaş</a> and <a href="<?php echo esc_url( home_url('/district/istanbul/sisli/') ); ?>">Şişli</a> are often preferred for access to business hubs and daily convenience, while <a href="<?php echo esc_url( home_url('/district/istanbul/kadikoy/') ); ?>">Kadıköy</a> suits buyers who want a stronger residential and cultural profile.
      </p>
    <?php endif; ?>

    <p class="text-soft">
      If you plan to <strong>buy property in Istanbul</strong>, we recommend assessing location fundamentals, developer track record, exit liquidity, and realistic rental performance before committing. New projects can offer modern amenities and appreciation potential, while selected resale stock may provide faster income stability.
    </p>

    <p class="text-soft">
      Istanbul is also a major destination for buyers interested in residency and citizenship options. Through the <a href="<?php echo esc_url( home_url('/citizenship-by-investment/') ); ?>">Turkish Citizenship by Investment</a> program, eligible property purchases can qualify investors for a Turkish passport, making real estate not only a lifestyle decision but also a strategic investment.
    </p>
  </div>
</section>

<?php if ( ! empty( $homepage_faq_items ) ) : ?>
  <section class="section">
    <div class="container">
      <div class="section-header">
        <h2>FAQs About Buying Property in Istanbul</h2>
      </div>

      <?php foreach ( $homepage_faq_items as $faq_item ) : ?>
        <h3><?php echo esc_html( $faq_item['question'] ); ?></h3>
        <p><?php echo wp_kses_post( $faq_item['answer'] ); ?></p>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
<?php endif; ?>

</main>

<?php get_footer();
