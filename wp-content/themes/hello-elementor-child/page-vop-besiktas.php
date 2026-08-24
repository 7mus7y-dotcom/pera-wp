<?php
/*
Template Name: VOP Besiktas Collection
*/
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">

  <!-- =====================================================
       HERO – VOP BEŞİKTAŞ COLLECTION
       ====================================================== -->
<section class="hero" id="vop-besiktas-hero">
  <!-- LCP hero image -->
  <?php
    echo wp_get_attachment_image(
      55482,
      'full',
      false,
      [
        'class'         => 'hero-media',
        'alt'           => 'Beşiktaş Collection',
        'fetchpriority' => 'high',
        'loading'       => 'eager',
        'decoding'      => 'async',
      ]
    );
  ?>

  <div class="hero-overlay"></div>

  <div class="hero-content">
    <h1><?php echo esc_html( pera_ml_ui( 'Beşiktaş Collection', 'theme.template.page_vop_besiktas.be_ikta_collection' ) ); ?></h1>

    <p class="lead">
      <?php echo esc_html( pera_ml_ui( 'Three brand new boutique residences redefining modern living
      in the heart of Istanbul.', 'theme.template.page_vop_besiktas.three_brand_new_boutique_residences_redefining_modern_living_in_the_hear' ) ); ?>
    </p>

    <div class="hero-actions">
      <a href="#projects" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Explore projects', 'theme.template.page_vop_besiktas.explore_projects' ) ); ?></a>
      <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Contact us', 'theme.template.page_vop_besiktas.contact_us' ) ); ?></a>
    </div>
  </div>
</section>



  <!-- =====================================================
       ABOUT – LIFESTYLE ENGINEERING
       ====================================================== -->
  <section id="about" class="section">
    <div class="container two-col">
      <div>
        <h2><?php echo esc_html( pera_ml_ui( 'Lifestyle engineering', 'theme.template.page_vop_besiktas.lifestyle_engineering' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'Human-focused planning, solid construction and central Beşiktaş addresses.
          Each project is designed for both living and investment.', 'theme.template.page_vop_besiktas.human_focused_planning_solid_construction_and_central_be_ikta_addresses_' ) ); ?>
        </p>

        <div class="pillars">
          <div><?php echo esc_html( pera_ml_ui( 'Earthquake-resistant', 'theme.template.page_vop_besiktas.earthquake_resistant' ) ); ?></div>
          <div><?php echo esc_html( pera_ml_ui( 'Energy efficient', 'theme.template.page_vop_besiktas.energy_efficient' ) ); ?></div>
          <div><?php echo esc_html( pera_ml_ui( 'Smart layouts', 'theme.template.page_vop_besiktas.smart_layouts' ) ); ?></div>
          <div><?php echo esc_html( pera_ml_ui( 'Central Beşiktaş', 'theme.template.page_vop_besiktas.central_be_ikta' ) ); ?></div>
        </div>
      </div>

      <div>
        <img
          src="<?php echo content_url( '/uploads/vop/vop-concept-thumb.jpg' ); ?>"
          alt="<?php echo esc_attr( pera_ml_ui( 'VOP concept', 'theme.template.page_vop_besiktas.alt.vop_concept' ) ); ?>"
          loading="lazy"
          decoding="async"
        >
      </div>
    </div>
  </section>


  <!-- =====================================================
       NEIGHBOURHOOD
       ====================================================== -->
  <section id="neighborhood" class="section section-soft">
    <div class="container">
      <div class="grid-2">
        <!-- LEFT: TEXT -->
        <div>
          <h2><?php echo esc_html( pera_ml_ui( 'In the heart of Beşiktaş', 'theme.template.page_vop_besiktas.in_the_heart_of_be_ikta' ) ); ?></h2>
          <p>
            <?php echo esc_html( pera_ml_ui( 'All three residences sit within minutes of world-famous landmarks —
            ideal for the short rental market or as a vacation home (or both).', 'theme.template.page_vop_besiktas.all_three_residences_sit_within_minutes_of_world_famous_landmarks_ideal_' ) ); ?>
          </p>
        </div>

        <!-- RIGHT: IMAGE -->
        <div>
          <img
            src="<?php echo content_url( '/uploads/vop/vop-map.webp' ); ?>"
            alt="<?php echo esc_attr( pera_ml_ui( 'Map of Beşiktaş projects', 'theme.template.page_vop_besiktas.alt.map_of_be_ikta_projects' ) ); ?>"
            class="rounded"
            loading="lazy"
            decoding="async"
          >
        </div>
      </div>
    </div>
  </section>


  <!-- =====================================================
       PROJECTS – CARD GRID
       ====================================================== -->
  <section id="projects" class="section">
    <div class="container">
      <h2 class="center"><?php echo esc_html( pera_ml_ui( 'Our projects', 'theme.template.page_vop_besiktas.our_projects' ) ); ?></h2>

      <div class="cards">
          
        <!-- VOP – IHLAMUR -->
        <article class="project-card">
          <img
            src="<?php echo content_url( '/uploads/vop/vop-ihlamur.webp' ); ?>"
            alt="VOP Ihlamur"
            loading="lazy"
            decoding="async"
          >

          <div class="content">
            <h3>Ihlamur</h3>
            <p><?php echo esc_html( pera_ml_ui( 'Ihlamurdere Caddesi, Beşiktaş', 'theme.template.page_vop_besiktas.ihlamurdere_caddesi_be_ikta' ) ); ?></p>
            <ul>
              <li><?php echo esc_html( pera_ml_ui( '32 apartments + 2 shops', 'theme.template.page_vop_besiktas.32_apartments_2_shops' ) ); ?></li>
              <li><?php echo esc_html( pera_ml_ui( '1+1 to 4+1 duplex', 'theme.template.page_vop_besiktas.1_1_to_4_1_duplex' ) ); ?></li>
              <li><?php echo esc_html( pera_ml_ui( 'Next to Dünya Barış Park', 'theme.template.page_vop_besiktas.next_to_d_nya_bar_park' ) ); ?></li>
              <li><?php echo esc_html( pera_ml_ui( 'Delivery: Feb 2026', 'theme.template.page_vop_besiktas.delivery_feb_2026' ) ); ?></li>
            </ul>

            <div class="card-actions">
              <!-- Brochure -->
              <a
                href="<?php echo content_url( '/uploads/files/ihlamur-catalogue.pdf' ); ?>"
                target="_blank"
                class="btn btn--solid btn--black btn-card"
              >
                <svg class="icon" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-pdf"></use>
                </svg>
                <?php echo esc_html( pera_ml_ui( 'Brochure', 'theme.template.page_vop_besiktas.brochure' ) ); ?>
              </a>

              <!-- Location -->
              <a
                href="https://maps.app.goo.gl/V2gMd4763WsAQKU56"
                target="_blank"
                rel="noopener"
                class="btn btn--solid btn--blue btn-card"
              >
                <svg class="icon" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-map"></use>
                </svg>
                <?php echo esc_html( pera_ml_ui( 'Location', 'theme.template.page_vop_besiktas.location' ) ); ?>
              </a>
            </div>
          </div>
        </article>


        <!-- VOP – DIKILITAŞ -->
        <article class="project-card">
          <img
            src="<?php echo content_url( '/uploads/vop/vop-dikilitas.webp' ); ?>"
            alt="VOP Dikilitaş"
            loading="lazy"
            decoding="async"
          >

          <div class="content">
            <h3>Dikilitaş</h3>
            <p><?php echo esc_html( pera_ml_ui( 'Mukataacı Sokak No: 3', 'theme.template.page_vop_besiktas.mukataac_sokak_no_3' ) ); ?></p>
            <ul>
              <li><?php echo esc_html( pera_ml_ui( '29 boutique apartments', 'theme.template.page_vop_besiktas.29_boutique_apartments' ) ); ?></li>
              <li><?php echo esc_html( pera_ml_ui( 'Next to Dikilitaş Park', 'theme.template.page_vop_besiktas.next_to_dikilita_park' ) ); ?></li>
              <li><?php echo esc_html( pera_ml_ui( '1+1 – 4+1 duplex', 'theme.template.page_vop_besiktas.1_1_4_1_duplex' ) ); ?></li>
              <li><?php echo esc_html( pera_ml_ui( 'Delivery: Jan 2026', 'theme.template.page_vop_besiktas.delivery_jan_2026' ) ); ?></li>
            </ul>

            <div class="card-actions">
              <!-- Brochure -->
              <a
                href="<?php echo content_url( '/uploads/files/dikilitas-catalogue.pdf' ); ?>"
                target="_blank"
                class="btn btn--solid btn--black btn-card"
              >
                <svg class="icon" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-pdf"></use>
                </svg>
                <?php echo esc_html( pera_ml_ui( 'Brochure', 'theme.template.page_vop_besiktas.brochure' ) ); ?>
              </a>

              <!-- Location -->
              <a
                href="https://maps.app.goo.gl/zuT5Maw3akYxjhkM9"
                target="_blank"
                rel="noopener"
                class="btn btn--solid btn--blue btn-card"
              >
                <svg class="icon" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-map"></use>
                </svg>
                <?php echo esc_html( pera_ml_ui( 'Location', 'theme.template.page_vop_besiktas.location' ) ); ?>
              </a>
            </div>
          </div>
        </article>


        <!-- VOP – ABBASAĞA -->
        <article class="project-card">
          <img
            src="<?php echo content_url( '/uploads/vop/vop-abbasaga.webp' ); ?>"
            alt="VOP Abbasağa"
            loading="lazy"
            decoding="async"
          >

          <div class="content">
            <h3>Abbasağa</h3>
            <p><?php echo esc_html( pera_ml_ui( 'Zafer Sokak, Abbasağa', 'theme.template.page_vop_besiktas.zafer_sokak_abbasa_a' ) ); ?></p>
            <ul>
              <li><?php echo esc_html( pera_ml_ui( '28 apartments', 'theme.template.page_vop_besiktas.28_apartments' ) ); ?></li>
              <li><?php echo esc_html( pera_ml_ui( 'Opposite Dünya Barış Park', 'theme.template.page_vop_besiktas.opposite_d_nya_bar_park' ) ); ?></li>
              <li><?php echo esc_html( pera_ml_ui( 'Delivery: Feb 2026', 'theme.template.page_vop_besiktas.delivery_feb_2026' ) ); ?></li>
            </ul>

            <div class="card-actions">
              <!-- Brochure -->
              <a
                href="<?php echo content_url( '/uploads/files/abbasaga-catalogue.pdf' ); ?>"
                target="_blank"
                class="btn btn--solid btn--black btn-card"
              >
                <svg class="icon" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-pdf"></use>
                </svg>
                <?php echo esc_html( pera_ml_ui( 'Brochure', 'theme.template.page_vop_besiktas.brochure' ) ); ?>
              </a>

              <!-- Location -->
              <a
                href="https://maps.app.goo.gl/z6GVigSBdiLJt28C7"
                target="_blank"
                rel="noopener"
                class="btn btn--solid btn--blue btn-card"
              >
                <svg class="icon" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-map"></use>
                </svg>
                <?php echo esc_html( pera_ml_ui( 'Location', 'theme.template.page_vop_besiktas.location' ) ); ?>
              </a>
            </div>
          </div>
        </article>

      </div>
    </div>
  </section>


  <!-- =====================================================
       COMPARISON TABLE
       ====================================================== -->
  <section id="comparison" class="section section-soft">
    <div class="container">
      <h2 class="center"><?php echo esc_html( pera_ml_ui( 'Project overview', 'theme.template.page_vop_besiktas.project_overview' ) ); ?></h2>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th><?php echo esc_html( pera_ml_ui( 'Project', 'theme.template.page_vop_besiktas.project' ) ); ?></th>
              <th><?php echo esc_html( pera_ml_ui( 'Units', 'theme.template.page_vop_besiktas.units' ) ); ?></th>
              <th><?php echo esc_html( pera_ml_ui( 'Delivery', 'theme.template.page_vop_besiktas.delivery' ) ); ?></th>
              <th><?php echo esc_html( pera_ml_ui( 'Nearby', 'theme.template.page_vop_besiktas.nearby' ) ); ?></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Ihlamur</td>
              <td><?php echo esc_html( pera_ml_ui( '32 + 2 shops', 'theme.template.page_vop_besiktas.32_2_shops' ) ); ?></td>
              <td><?php echo esc_html( pera_ml_ui( 'Feb 2026', 'theme.template.page_vop_besiktas.feb_2026' ) ); ?></td>
              <td>Ihlamurdere Cad.</td>
            </tr>
            <tr>
              <td>Dikilitaş</td>
              <td><?php echo esc_html( pera_ml_ui( '29 units', 'theme.template.page_vop_besiktas.29_units' ) ); ?></td>
              <td><?php echo esc_html( pera_ml_ui( 'Jan 2026', 'theme.template.page_vop_besiktas.jan_2026' ) ); ?></td>
              <td>Dikilitaş Park</td>
            </tr>
            <tr>
              <td>Abbasağa</td>
              <td><?php echo esc_html( pera_ml_ui( '28 units', 'theme.template.page_vop_besiktas.28_units' ) ); ?></td>
              <td><?php echo esc_html( pera_ml_ui( 'Feb 2026', 'theme.template.page_vop_besiktas.feb_2026' ) ); ?></td>
              <td>Abbasağa Park</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>


  <!-- =====================================================
       LIFESTYLE
       ====================================================== -->
  <section id="lifestyle" class="section">
    <div class="container two-col">
      <div>
        <h2><?php echo esc_html( pera_ml_ui( 'Life in Beşiktaş', 'theme.template.page_vop_besiktas.life_in_be_ikta' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'Walk to cafés, parks and the Bosphorus. Close to Nişantaşı,
          transport links and universities.', 'theme.template.page_vop_besiktas.walk_to_caf_s_parks_and_the_bosphorus_close_to_ni_anta_transport_links_a' ) ); ?>
        </p>
        <p>
          <?php echo esc_html( pera_ml_ui( 'We combine central locations with boutique, low-unit buildings.', 'theme.template.page_vop_besiktas.we_combine_central_locations_with_boutique_low_unit_buildings' ) ); ?>
        </p>
      </div>

      <div>
        <?php
            echo wp_get_attachment_image(
              55701,
              'full',
              false,
              [
                'alt'      => 'Lifestyle in Beşiktaş',
                'loading'  => 'lazy',
                'decoding' => 'async',
              ]
            );?>

      </div>
    </div>
  </section>


  <!-- =====================================================
       CTA – CONTACT
       ====================================================== -->
  <section id="contact" class="section section-soft">
    <div class="container center">
      <h2><?php echo esc_html( pera_ml_ui( 'Request full details', 'theme.template.page_vop_besiktas.request_full_details' ) ); ?></h2>
      <p><?php echo esc_html( pera_ml_ui( 'Get floor plans, availability and current pricing.', 'theme.template.page_vop_besiktas.get_floor_plans_availability_and_current_pricing' ) ); ?></p>

      <div class="hero-actions">
        <a
          href="<?php echo esc_url( pera_get_whatsapp_url() ); ?>"
          class="btn btn--solid btn--black"
          target="_blank"
          rel="noopener" data-whatsapp="1" data-whatsapp-type="project_cta" data-track-channel="whatsapp" data-track-intent="high" data-track-source="template" data-track-context="vop_besiktas" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click"
        >
          <?php echo esc_html( pera_ml_ui( 'WhatsApp us', 'theme.template.page_vop_besiktas.whatsapp_us' ) ); ?>
        </a>

        <a
          href="mailto:info@peraproperty.com"
          class="btn btn-secondary"
        >
          <?php echo esc_html( pera_ml_ui( 'Email us', 'theme.template.page_vop_besiktas.email_us' ) ); ?>
        </a>
      </div>
    </div>
  </section>

</main>

<?php get_footer(); ?>
