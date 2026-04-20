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

<section class="section home-editorial-posts" aria-label="Latest Istanbul property insights">
  <div class="container">
    <header class="section-header section-header--center">
      <h2>Latest Istanbul property insights</h2>
      <p class="lead">Regional guides, buyer guidance, and investment articles to help you navigate the Istanbul market with confidence.</p>
    </header>

    <div class="cards-slider-shell--nav">
      <button
        type="button"
        class="cards-slider-nav cards-slider-nav--prev"
        data-slider-target="home-editorial-posts-slider"
        aria-label="Previous editorial posts"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-left' ); ?>"></use>
        </svg>
      </button>

      <div class="cards-slider cards-slider--snap home-editorial-posts__slider" id="home-editorial-posts-slider" aria-label="Latest editorial posts">
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

        <article class="slider-card post-card post-card--cta home-editorial-posts__cta" aria-label="More editorial content links">
          <div class="post-card-body">
            <h3 class="post-card-title">Want to see more?</h3>
            <p class="post-card-excerpt">Explore more guides, insights, and market articles.</p>

            <div class="home-editorial-posts__cta-actions">
              <a class="btn btn--solid btn--blue" href="<?php echo esc_url( $posts_page_url ); ?>">See all blog posts</a>
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $investment_url ); ?>">See investment advice</a>
              <a class="btn btn--ghost btn--green" href="<?php echo esc_url( $regional_url ); ?>">See regional guides</a>
            </div>
          </div>
        </article>
      </div>

      <button
        type="button"
        class="cards-slider-nav cards-slider-nav--next"
        data-slider-target="home-editorial-posts-slider"
        aria-label="Next editorial posts"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-right' ); ?>"></use>
        </svg>
      </button>
    </div>
  </div>
</section>
