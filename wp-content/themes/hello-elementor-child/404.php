<?php
/**
 * 404 Template
 * Uses ONLY existing classes from main.css (no new CSS, no JS).
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

get_header();
?>

<main id="primary" class="site-main">

  <!-- HERO -->
  <section class="hero hero-legal">
    <div class="container">
      <div class="hero-content">

        <p class="pill pill--green pill--sm"><?php echo esc_html( pera_ml_ui( 'Page not found', 'theme.template.404.page_not_found' ) ); ?></p>

        <h1>404</h1>

        <p class="lead">
          <?php echo esc_html( pera_ml_ui( 'Sorry — the page you’re looking for doesn’t exist, or it has moved.', 'theme.template.404.sorry_the_page_you_re_looking_for_doesn_t_exist_or_it_has_moved' ) ); ?>
        </p>

        <div class="hero-actions">
          <a class="btn btn--solid btn--black" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Back to Home', 'theme.template.404.back_to_home' ) ); ?></a>
          <a class="btn btn--solid btn--black" href="<?php echo esc_url( home_url( '/property/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'View Properties', 'theme.template.404.view_properties' ) ); ?></a>
          <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Contact Us', 'theme.template.404.contact_us' ) ); ?></a>
        </div>

      </div>
    </div>
  </section>

  <!-- CONTENT PANEL (single card wrapper, then grid inside) -->
  <section class="content-panel content-panel--overlap-hero">
    <div class="container">

      <div class="content-panel-box">
        <div class="content-panel-grid">

          <!-- Left: Helpful links + search -->
          <div>

            <div class="section-header">
              <h2><?php echo esc_html( pera_ml_ui( 'Where you can go next', 'theme.template.404.where_you_can_go_next' ) ); ?></h2>
              <p><?php echo esc_html( pera_ml_ui( 'These are some of the most visited areas of the site.', 'theme.template.404.these_are_some_of_the_most_visited_areas_of_the_site' ) ); ?></p>
            </div>

            <ul class="checklist checklist--circle">
              <li>
                <a href="<?php echo esc_url( home_url( '/property/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Browse all listings', 'theme.template.404.browse_all_listings' ) ); ?></a>
              </li>

              <li>
                <a href="<?php echo esc_url( home_url( '/category/buyer-guides' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Buyer’s guide', 'theme.template.404.buyer_s_guide' ) ); ?></a>
              </li>

              <li>
                <a href="<?php echo esc_url( home_url( '/category/regional-guides/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Istanbul area guides', 'theme.template.404.istanbul_area_guides' ) ); ?></a>
              </li>

              <li>
                <a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'About Pera Property', 'theme.template.404.about_pera_property' ) ); ?></a>
              </li>
            </ul>

          </div>

         
        </div><!-- /.content-panel-grid -->
      </div><!-- /.content-panel-box -->

    </div><!-- /.container -->
  </section>

</main>

<?php get_footer(); ?>
