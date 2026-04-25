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

  // Phase 1 ownership: all property archive/taxonomy SEO belongs to
  // seo-property-archive.php via one shared taxonomy source of truth.
  $property_taxonomies = function_exists( 'pera_get_property_archive_taxonomies' )
    ? pera_get_property_archive_taxonomies( false )
    : array( 'district', 'region', 'property_type', 'property_tags', 'special' );

  if ( is_tax() ) {
    $qo = get_queried_object();
    $taxonomy = ( $qo instanceof WP_Term && ! is_wp_error( $qo ) ) ? (string) $qo->taxonomy : '';

    if ( $taxonomy !== '' && in_array( $taxonomy, $property_taxonomies, true ) ) {
      require_once $inc . 'seo-property-archive.php';
      return;
    }
  }

  // 2) Property Archive (your search page)
  if ( is_post_type_archive( 'property' ) ) {
    require_once $inc . 'seo-property-archive.php';
    return;
  }

  // 3) Everything else (pages, posts, taxonomies, etc.)
  require_once $inc . 'seo-all.php';

}, 1 );
