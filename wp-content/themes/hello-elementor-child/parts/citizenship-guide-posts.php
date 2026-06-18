<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$citizenship_guide_args = wp_parse_args(
  isset( $args ) && is_array( $args ) ? $args : array(),
  array(
    'category_slug'      => 'citizenship',
    'section_aria_label' => 'Turkish citizenship guide posts',
    'eyebrow'            => 'Turkish citizenship guide series',
    'heading'            => 'Continue reading our Turkish citizenship property guides',
    'intro'              => 'These guides explain the key property, valuation, DAB and Certificate of Conformity checks that matter before buying property for Turkish citizenship.',
    'slider_id'          => 'citizenship-guide-posts-slider',
    'prev_aria_label'    => 'Previous citizenship guide posts',
    'next_aria_label'    => 'Next citizenship guide posts',
  )
);

$citizenship_guide_category_slug = sanitize_title( (string) $citizenship_guide_args['category_slug'] );

if ( '' === $citizenship_guide_category_slug ) {
  return;
}

$citizenship_guide_query = new WP_Query( array(
  'category_name'       => $citizenship_guide_category_slug,
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

<section class="section home-editorial-posts" aria-label="<?php echo esc_attr( $citizenship_guide_args['section_aria_label'] ); ?>">
  <div class="container">
    <header class="section-header section-header--center">
      <p class="u-eyebrow"><?php echo esc_html( $citizenship_guide_args['eyebrow'] ); ?></p>
      <h2><?php echo esc_html( $citizenship_guide_args['heading'] ); ?></h2>
      <p class="lead"><?php echo esc_html( $citizenship_guide_args['intro'] ); ?></p>
    </header>

    <div class="cards-slider-shell--nav">
      <button
        type="button"
        class="cards-slider-nav cards-slider-nav--prev"
        data-slider-target="<?php echo esc_attr( $citizenship_guide_args['slider_id'] ); ?>"
        aria-label="<?php echo esc_attr( $citizenship_guide_args['prev_aria_label'] ); ?>"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-left' ); ?>"></use>
        </svg>
      </button>

      <div class="cards-slider cards-slider--snap home-editorial-posts__slider" id="<?php echo esc_attr( $citizenship_guide_args['slider_id'] ); ?>" aria-label="<?php echo esc_attr( $citizenship_guide_args['section_aria_label'] ); ?>">
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
        data-slider-target="<?php echo esc_attr( $citizenship_guide_args['slider_id'] ); ?>"
        aria-label="<?php echo esc_attr( $citizenship_guide_args['next_aria_label'] ); ?>"
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
