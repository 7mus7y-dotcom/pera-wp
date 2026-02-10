<?php
/**
 * Disable Hello Elementor parent theme front-end CSS (and optional meta description).
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Stop Hello Elementor from enqueueing its CSS via its own filters.
 * (This prevents reset.css + theme.css from being enqueued.)
 */
add_filter( 'hello_elementor_enqueue_style', '__return_false' );
add_filter( 'hello_elementor_enqueue_theme_style', '__return_false' );

/**
 * Dequeue any Hello styles that may still be enqueued (e.g. header-footer.css).
 * We do this at a later priority so it wins.
 */
add_action( 'wp_enqueue_scripts', function () {
  wp_dequeue_style( 'hello-elementor' );
  wp_dequeue_style( 'hello-elementor-theme-style' );
  wp_dequeue_style( 'hello-elementor-header-footer' );
}, 50 );

/**
 * Optional: remove Hello's excerpt-based meta description to prevent duplicates.
 * (Recommended if you use RankMath/Yoast/other SEO plugin.)
 */
add_action( 'after_setup_theme', function () {
  if ( has_action( 'wp_head', 'hello_elementor_add_description_meta_tag' ) ) {
    remove_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );
  }
}, 20 );
