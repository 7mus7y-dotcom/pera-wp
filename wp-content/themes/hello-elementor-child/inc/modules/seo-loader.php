<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Conditionally load SEO modules (must load before wp_head fires)
 */
add_action( 'wp', function () {

  if ( is_admin() ) return;

  $inc = trailingslashit( get_stylesheet_directory() ) . 'inc/';

  // 1) Single Property
  if ( is_singular( 'property' ) ) {
    require_once $inc . 'seo-property.php';
    return;
  }
  
    $property_taxonomies = array(
    'district',
    'region',
    'property_type',
    'property_tags',
    'special',
  );
  $property_taxonomies = array_filter( $property_taxonomies, 'taxonomy_exists' );

  if ( ! empty( $property_taxonomies ) && is_tax( $property_taxonomies ) ) {
    require_once $inc . 'seo-property-archive.php';
    return;
  }

  // 2) Property Archive (your search page)
  if ( is_post_type_archive( 'property' ) ) {
    require_once $inc . 'seo-property-archive.php';
    return;
  }

  // 3) Everything else (pages, posts, taxonomies, etc.)
  require_once $inc . 'seo-all.php';

}, 1 );
