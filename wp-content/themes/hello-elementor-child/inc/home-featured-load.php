<?php
/**
 * Public, read-only pagination for homepage property discovery cards.
 * Matches the homepage's latest-first published Property query.
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

function pera_home_featured_load_more() {
  $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
  if ( $offset < 5 || $offset > 995 ) {
    wp_send_json_error( array( 'message' => 'Invalid property offset.' ), 400 );
  }

  $query = new WP_Query( array(
    'post_type'           => 'property',
    'post_status'         => 'publish',
    'posts_per_page'      => 6,
    'offset'              => $offset,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'ignore_sticky_posts' => true,
    'no_found_rows'       => false,
  ) );

  ob_start();
  while ( $query->have_posts() ) {
    $query->the_post();
    echo '<div class="slider-card">';
    pera_render_property_card( array( 'variant' => 'archive' ) );
    echo '</div>';
  }
  $html = ob_get_clean();
  wp_reset_postdata();

  wp_send_json_success( array(
    'html'        => $html,
    'next_offset' => $offset + (int) $query->post_count,
    'has_more'    => $offset + (int) $query->post_count < (int) $query->found_posts,
  ) );
}
add_action( 'wp_ajax_pera_home_featured_load_more', 'pera_home_featured_load_more' );
add_action( 'wp_ajax_nopriv_pera_home_featured_load_more', 'pera_home_featured_load_more' );
