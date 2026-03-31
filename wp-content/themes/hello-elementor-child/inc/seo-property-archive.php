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
  // Taxonomy TITLE precedence:
  // 1) ACF seo_title, 2) raw term meta seo_title,
  // 3) taxonomy-generated title formula, 4) existing/default title.
  if ( is_tax( pera_get_property_archive_taxonomies() ) ) {
    $term = get_queried_object();
    if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
      return $title;
    }

    $manual = pera_get_property_archive_term_manual_seo_title( $term );
    if ( $manual !== '' ) {
      return $manual;
    }

    $generated = pera_get_property_archive_generated_title( $term );
    if ( $generated !== '' ) {
      return $generated;
    }

    return $title;
  }

  if ( ! is_post_type_archive( 'property' ) || is_tax() || is_search() ) {
    return $title;
  }

  // Main /property/ TITLE precedence:
  // 1) safe existing manual source (deferred in Phase 2 if unavailable),
  // 2) generated page-1 title, 3) existing/default title for pagination.
  $paged = function_exists( 'pera_property_archive_get_paged' )
    ? pera_property_archive_get_paged()
    : max( 1, (int) get_query_var( 'paged' ) );

  if ( $paged > 1 ) {
    return $title;
  }

  return 'Property for sale in Istanbul | Pera Property';
}, 20 );

add_action( 'wp_head', function () {

  // Run only on property archive ownership contexts (archive + taxonomies).
  $is_property_context = function_exists( 'pera_is_property_archive_context' )
    ? pera_is_property_archive_context()
    : is_post_type_archive( 'property' );

  if ( ! $is_property_context ) {
    return;
  }

  $is_filtered = pera_property_archive_is_filtered_request();

  $canonical = function_exists( 'pera_property_archive_canonical_url' )
    ? pera_property_archive_canonical_url()
    : '';

  if ( $canonical === '' ) {
    $paged = function_exists( 'pera_property_archive_get_paged' )
      ? pera_property_archive_get_paged()
      : max( 1, (int) get_query_var( 'paged' ) );

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
    $canonical = ( $paged > 1 ) ? $base . 'page/' . $paged . '/' : $base;
  }

  $has_seo_plugin =
    defined( 'WPSEO_VERSION' ) ||
    defined( 'RANK_MATH_VERSION' ) ||
    class_exists( 'WPSEO_Frontend' ) ||
    class_exists( 'RankMath\\Frontend\\Frontend' );

  $meta_desc = '';

  if ( ! $is_filtered && ! $has_seo_plugin ) {
    if ( is_tax( pera_get_property_archive_taxonomies() ) ) {
      $term = get_queried_object();

      if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
        // Taxonomy META DESCRIPTION precedence:
        // 1) ACF seo_meta_description, 2) raw term meta seo_meta_description,
        // 3) term excerpt fallback chain, 4) empty.
        $meta_desc = pera_get_property_archive_term_manual_meta_description( $term );

        if ( $meta_desc === '' ) {
          $meta_desc = pera_get_property_archive_term_excerpt_fallback( $term );
        }
      }
    } elseif ( is_post_type_archive( 'property' ) ) {
      // Main /property/ META DESCRIPTION precedence:
      // 1) safe existing manual source (deferred if unavailable),
      // 2) generated archive description, 3) empty.
      $meta_desc = pera_get_property_archive_generated_description();
    }
  }

  echo "\n<!-- Pera SEO: Property archive -->\n";

  if ( $meta_desc !== '' ) {
    echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '">' . "\n";
  }

  echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
  echo "<!-- /Pera SEO: Property archive -->\n\n";

}, 30 );

add_filter( 'wp_robots', function ( array $robots ): array {

  if ( is_admin() ) return $robots;

  $is_property_context = function_exists( 'pera_is_property_archive_context' )
    ? pera_is_property_archive_context()
    : is_post_type_archive( 'property' );

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
