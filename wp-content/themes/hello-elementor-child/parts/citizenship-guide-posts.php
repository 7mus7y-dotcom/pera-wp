<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$citizenship_guide_query = new WP_Query( array(
  'category_name'       => 'citizenship',
  'posts_per_page'      => 4,
  'post_type'           => 'post',
  'post_status'         => 'publish',
  'ignore_sticky_posts' => true,
  'no_found_rows'       => true,
  'orderby'             => 'date',
  'order'               => 'DESC',
) );

if ( $citizenship_guide_query->have_posts() ) :
?>

<section class="section home-editorial-posts" aria-label="Turkish citizenship guide posts">
  <div class="container">
    <header class="section-header section-header--center">
      <p class="u-eyebrow">Turkish citizenship guide series</p>
      <h2>Continue reading our Turkish citizenship property guides</h2>
      <p class="lead">These guides explain the key property, valuation, DAB and Certificate of Conformity checks that matter before buying property for Turkish citizenship.</p>
    </header>

    <div class="cards-slider-shell--nav">
      <button
        type="button"
        class="cards-slider-nav cards-slider-nav--prev"
        data-slider-target="citizenship-guide-posts-slider"
        aria-label="Previous citizenship guide posts"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-left' ); ?>"></use>
        </svg>
      </button>

      <div class="cards-slider cards-slider--snap home-editorial-posts__slider" id="citizenship-guide-posts-slider" aria-label="Turkish citizenship guide posts">
        <?php
        while ( $citizenship_guide_query->have_posts() ) :
          $citizenship_guide_query->the_post();

          set_query_var( 'pera_post_card_args', array(
            'variant'      => 'grid',
            'card_classes' => 'slider-card',
          ) );

          get_template_part( 'parts/post-card' );
        endwhile;
        ?>
      </div>

      <button
        type="button"
        class="cards-slider-nav cards-slider-nav--next"
        data-slider-target="citizenship-guide-posts-slider"
        aria-label="Next citizenship guide posts"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-right' ); ?>"></use>
        </svg>
      </button>
    </div>
  </div>
</section>

<?php
endif;

set_query_var( 'pera_post_card_args', null );
wp_reset_postdata();
