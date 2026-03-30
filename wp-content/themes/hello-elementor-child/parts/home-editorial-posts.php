<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$editorial_query = new WP_Query( array(
  'post_type'           => 'post',
  'post_status'         => 'publish',
  'posts_per_page'      => 4,
  'ignore_sticky_posts' => true,
  'no_found_rows'       => true,
  'orderby'             => 'date',
  'order'               => 'DESC',
  'category_name'       => 'regional-guides,buyer-guides,investment-advice',
) );

$posts_page_url = get_permalink( (int) get_option( 'page_for_posts' ) );
if ( ! $posts_page_url ) {
  $posts_page_url = home_url( '/blog/' );
}

if ( ! $editorial_query->have_posts() ) {
  return;
}
?>

<section class="section home-editorial-posts" aria-label="Latest Istanbul property insights">
  <div class="container">
    <header class="section-header section-header--center">
      <h2>Latest Istanbul property insights</h2>
      <p class="lead">Regional guides, buyer guidance, and investment articles to help you navigate the Istanbul market with confidence.</p>
    </header>

    <div class="cards-grid">
      <?php
      while ( $editorial_query->have_posts() ) :
        $editorial_query->the_post();

        set_query_var( 'pera_post_card_args', array(
          'variant'       => 'grid',
          'thumb_size'    => 'medium_large',
          'show_excerpt'  => true,
          'excerpt_words' => 22,
          'show_readmore' => true,
        ) );

        get_template_part( 'parts/post-card' );
      endwhile;

      set_query_var( 'pera_post_card_args', null );
      wp_reset_postdata();
      ?>
    </div>

    <div class="hero-actions flex-center home-editorial-posts__actions">
      <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $posts_page_url ); ?>">
        View all insights
      </a>
    </div>
  </div>
</section>
