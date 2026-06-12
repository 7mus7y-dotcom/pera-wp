<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$citizenship_guide_query = new WP_Query( array(
  'post_type'           => 'post',
  'post_status'         => 'publish',
  'posts_per_page'      => 4,
  'ignore_sticky_posts' => true,
  'no_found_rows'       => true,
  'orderby'             => 'date',
  'order'               => 'DESC',
  'category_name'       => 'citizenship',
) );

if ( ! $citizenship_guide_query->have_posts() ) {
  return;
}
?>

<section class="section citizenship-guide-posts" aria-label="Turkish citizenship guide series">
  <div class="container">
    <header class="section-header section-header--center">
      <p class="u-eyebrow">Turkish citizenship guide series</p>
      <h2>Continue reading our Turkish citizenship property guides</h2>
      <p class="lead">These guides explain the key property, valuation, DAB and Certificate of Conformity checks that matter before buying property for Turkish citizenship.</p>
    </header>

    <div class="citizenship-guide-posts__grid">
      <?php
      while ( $citizenship_guide_query->have_posts() ) :
        $citizenship_guide_query->the_post();

        set_query_var( 'pera_post_card_args', array(
          'variant'      => 'grid',
          'card_classes' => 'citizenship-guide-posts__card',
        ) );

        get_template_part( 'parts/post-card' );
      endwhile;

      set_query_var( 'pera_post_card_args', null );
      wp_reset_postdata();
      ?>
    </div>
  </div>
</section>
