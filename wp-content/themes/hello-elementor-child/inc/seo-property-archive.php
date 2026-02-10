<?php
/**
 * SEO: Property archive (V2 live)
 *
 * Rules:
 * - /property/ and /property/page/N/ are indexable.
 * - Filtered URLs (?s=, ?district[]=, ?min_price=, etc.) are noindex,follow.
 * - Canonical always points to the clean URL for the current page number
 *   (taxonomy-aware: /district/slug/ page is canonicalised to itself).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'pre_get_document_title', function( $title ) {
  if ( ! is_tax( 'district' ) ) {
    return $title;
  }

  $term = get_queried_object();
  if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
    return $title;
  }

  return pera_get_district_archive_title( $term );
}, 20 );

add_filter( 'pre_get_document_title', function( $title ) {
  if ( ! is_tax( 'region' ) ) {
    return $title;
  }

  $term = get_queried_object();
  if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
    return $title;
  }

  return pera_get_region_archive_title( $term );
}, 20 );

add_filter( 'pre_get_document_title', function( $title ) {
  if ( ! is_tax( 'property_tags' ) ) {
    return $title;
  }

  $term = get_queried_object();
  if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
    return $title;
  }

  return pera_get_property_tags_archive_title( $term );
}, 20 );

add_filter( 'pre_get_document_title', function( $title ) {
  if ( ! is_post_type_archive( 'property' ) ) {
    return $title;
  }

  if ( is_tax() || is_search() ) {
    return $title;
  }

  $paged = max( 1, (int) get_query_var( 'paged' ) );
  if ( get_query_var( 'page' ) ) {
    $paged = max( $paged, (int) get_query_var( 'page' ) );
  }

  if ( $paged > 1 ) {
    return $title;
  }

  return 'Property for Sale in Istanbul';
}, 20 );

add_action( 'wp_head', function () {

  // Run only on property archive + property taxonomies
  $taxes = array( 'district', 'region', 'property_type', 'bedrooms', 'property_tags' );
  $taxes = array_values( array_filter( $taxes, 'taxonomy_exists' ) );

  $is_property_context =
    is_post_type_archive( 'property' ) ||
    ( ! empty( $taxes ) && is_tax( $taxes ) );

  if ( ! $is_property_context ) {
    return;
  }

  // -------------------------------
  // Detect “filtered” state (robust for arrays + scalars)
  // -------------------------------
  $is_filtered = pera_property_archive_is_filtered_request();

  // -------------------------------
  // Canonical base (taxonomy-aware)
  // -------------------------------
  $canonical = function_exists( 'pera_property_archive_canonical_url' )
    ? pera_property_archive_canonical_url()
    : '';

  if ( $canonical === '' ) {
    $paged = function_exists( 'pera_property_archive_get_paged' )
      ? pera_property_archive_get_paged()
      : max( 1, (int) get_query_var( 'paged' ) );

    if ( get_query_var( 'page' ) ) {
      $paged = max( $paged, (int) get_query_var( 'page' ) );
    }

    if ( is_tax() ) {
      $qo = get_queried_object();
      $base = ( $qo instanceof WP_Term && ! is_wp_error( $qo ) ) ? get_term_link( $qo ) : '';
      if ( is_wp_error( $base ) ) {
        $base = '';
      }
    } else {
      $base = get_post_type_archive_link( 'property' );
    }

    if ( ! $base ) {
      $base = home_url( '/property/' );
    }

    $base = trailingslashit( $base );

    $canonical = ( $paged > 1 )
      ? $base . 'page/' . $paged . '/'
      : $base;
  }

  // -------------------------------
  // Optional meta description (taxonomy only, unfiltered only)
  // Avoid duplicates if Yoast/RankMath are active.
  // -------------------------------
  $has_seo_plugin =
    defined( 'WPSEO_VERSION' ) ||
    defined( 'RANK_MATH_VERSION' ) ||
    class_exists( 'WPSEO_Frontend' ) ||
    class_exists( 'RankMath\\Frontend\\Frontend' );

  $meta_desc = '';

  if ( is_tax() && ! $is_filtered && ! $has_seo_plugin ) {
    $qo = get_queried_object();

    if ( $qo instanceof WP_Term && ! is_wp_error( $qo ) ) {

      // Prefer term excerpt stored in term meta, then fallback to term_description()
      $meta_desc = get_term_meta( $qo->term_id, 'term_excerpt', true );
      if ( ! $meta_desc ) $meta_desc = get_term_meta( $qo->term_id, 'excerpt', true );
      if ( ! $meta_desc ) $meta_desc = get_term_meta( $qo->term_id, 'pera_term_excerpt', true );
      if ( ! $meta_desc ) $meta_desc = term_description( $qo->term_id, $qo->taxonomy );

      $meta_desc = wp_strip_all_tags( (string) $meta_desc );
      $meta_desc = trim( preg_replace( '/\s+/', ' ', $meta_desc ) );
    }
  }

  // -------------------------------
  // Output
  // -------------------------------
  echo "\n<!-- Pera SEO: Property archive -->\n";

  if ( $meta_desc !== '' ) {
    echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '">' . "\n";
  }

  echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
  echo "<!-- /Pera SEO: Property archive -->\n\n";

}, 30 );

add_filter( 'wp_robots', function ( array $robots ): array {

  if ( is_admin() ) return $robots;

  $property_taxonomies = array(
    'district',
    'region',
    'property_type',
    'property_tags',
    'special',
  );
  $property_taxonomies = array_filter( $property_taxonomies, 'taxonomy_exists' );

  $is_property_context = is_post_type_archive( 'property' );

  if ( ! empty( $property_taxonomies ) && is_tax( $property_taxonomies ) ) {
    $is_property_context = true;
  }

  if ( ! $is_property_context ) {
    return $robots;
  }

  $is_filtered = pera_property_archive_is_filtered_request();

  if ( $is_filtered ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  return $robots;
} );
