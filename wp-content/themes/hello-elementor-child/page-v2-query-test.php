<?php
/*
Template Name: V2 Query Test
*/

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>

<main class="container" style="padding:40px 0;">
  <h1>V2 Unit Query Test</h1>

  <?php
  // Example URL: /v2-query-test/?v2_beds=2
  $v2_beds = isset( $_GET['v2_beds'] ) ? (int) $_GET['v2_beds'] : 0;

  $args = [
    'post_type'      => 'property',
    'posts_per_page' => -1,
  ];

  // Correct helper function name:
  $meta_query = pera_v2_meta_query_for_v2_bedrooms( $v2_beds );

  if ( ! empty( $meta_query ) ) {
    $args['meta_query'] = $meta_query;
  }

  echo '<p><strong>Bedrooms filter:</strong> ' . esc_html( $v2_beds > 0 ? (string) $v2_beds : 'none' ) . '</p>';

  if ( ! empty( $meta_query ) ) {
    echo '<pre style="background:#f7f7f7;padding:12px;border:1px solid #eee;">' . esc_html( print_r( $meta_query, true ) ) . '</pre>';
  }

  $q = new WP_Query( $args );

  if ( $q->have_posts() ) {

    echo '<pre>';

    while ( $q->have_posts() ) {
      $q->the_post();
    
      $post_id = get_the_ID();
    
      $project_name = (string) get_field( 'project_name', $post_id );
      $label        = $project_name !== '' ? $project_name : get_the_title();
    
      $idx = get_post_meta( $post_id, 'v2_index_flat', true );
    
      echo $post_id . ' — ' . $label . ' — v2_index_flat: ' . $idx . PHP_EOL;
    }
    
    echo '</pre>';
    wp_reset_postdata();

  } else {
    echo '<p>No matches found.</p>';
  }
  ?>
</main>

<?php get_footer(); ?>
