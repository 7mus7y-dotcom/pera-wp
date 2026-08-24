<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$editorial_query = new WP_Query( array(
  'post_type'           => 'post',
  'post_status'         => 'publish',
  'posts_per_page'      => 5,
  'ignore_sticky_posts' => true,
  'no_found_rows'       => true,
  'orderby'             => 'date',
  'order'               => 'DESC',
  'category_name'       => 'regional-guides,buyer-guides,investment-advice',
) );

if ( ! $editorial_query->have_posts() ) {
  return;
}

$posts_page_url = get_permalink( (int) get_option( 'page_for_posts' ) );
if ( ! $posts_page_url ) {
  $posts_page_url = home_url( '/blog/' );
}

$investment_category = get_category_by_slug( 'investment-advice' );
$regional_category   = get_category_by_slug( 'regional-guides' );

$investment_url = ( $investment_category && ! is_wp_error( $investment_category ) )
  ? get_category_link( $investment_category->term_id )
  : home_url( '/category/investment-advice/' );

$regional_url = ( $regional_category && ! is_wp_error( $regional_category ) )
  ? get_category_link( $regional_category->term_id )
  : home_url( '/category/regional-guides/' );
?>

<section class="section home-editorial-posts" aria-label="<?php echo esc_attr( pera_ml_ui( 'Latest Istanbul property insights', 'theme.home_editorial.section_label' ) ); ?>">
  <div class="container">
    <header class="section-header section-header--center">
      <h2><?php echo esc_html( pera_ml_ui( 'Latest Istanbul property insights', 'theme.home_editorial.heading' ) ); ?></h2>
      <p class="lead"><?php echo esc_html( pera_ml_ui( 'Regional guides, buyer guidance, and investment articles to help you navigate the Istanbul market with confidence.', 'theme.home_editorial.intro' ) ); ?></p>
    </header>

    <div class="cards-slider-shell--nav">
      <button
        type="button"
        class="cards-slider-nav cards-slider-nav--prev"
        data-slider-target="home-editorial-posts-slider"
        aria-label="<?php echo esc_attr( pera_ml_ui( 'Previous editorial posts', 'theme.home_editorial.previous' ) ); ?>"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-left' ); ?>"></use>
        </svg>
      </button>

      <div class="cards-slider cards-slider--snap home-editorial-posts__slider" id="home-editorial-posts-slider" aria-label="<?php echo esc_attr( pera_ml_ui( 'Latest editorial posts', 'theme.home_editorial.slider_label' ) ); ?>">
        <?php
        while ( $editorial_query->have_posts() ) :
          $editorial_query->the_post();

          set_query_var( 'pera_post_card_args', array(
            'variant'      => 'grid',
            'card_classes' => 'slider-card',
          ) );

          get_template_part( 'parts/post-card' );
        endwhile;

        set_query_var( 'pera_post_card_args', null );
        wp_reset_postdata();
        ?>

        <article class="slider-card post-card post-card--cta home-editorial-posts__cta" aria-label="<?php echo esc_attr( pera_ml_ui( 'More editorial content links', 'theme.home_editorial.more_links_label' ) ); ?>">
          <div class="post-card-body">
            <h3 class="post-card-title"><?php echo esc_html( pera_ml_ui( 'Want to see more?', 'theme.home_editorial.more_heading' ) ); ?></h3>
            <p class="post-card-excerpt"><?php echo esc_html( pera_ml_ui( 'Explore more guides, insights, and market articles.', 'theme.home_editorial.more_intro' ) ); ?></p>

            <div class="home-editorial-posts__cta-actions">
              <a class="btn btn--solid btn--blue" href="<?php echo esc_url( $posts_page_url ); ?>"><?php echo esc_html( pera_ml_ui( 'See all blog posts', 'theme.home_editorial.all_posts' ) ); ?></a>
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $investment_url ); ?>"><?php echo esc_html( pera_ml_ui( 'See investment advice', 'theme.home_editorial.investment_advice' ) ); ?></a>
              <a class="btn btn--ghost btn--green" href="<?php echo esc_url( $regional_url ); ?>"><?php echo esc_html( pera_ml_ui( 'See regional guides', 'theme.home_editorial.regional_guides' ) ); ?></a>
            </div>
          </div>
        </article>
      </div>

      <button
        type="button"
        class="cards-slider-nav cards-slider-nav--next"
        data-slider-target="home-editorial-posts-slider"
        aria-label="<?php echo esc_attr( pera_ml_ui( 'Next editorial posts', 'theme.home_editorial.next' ) ); ?>"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-right' ); ?>"></use>
        </svg>
      </button>
    </div>
  </div>
</section>
