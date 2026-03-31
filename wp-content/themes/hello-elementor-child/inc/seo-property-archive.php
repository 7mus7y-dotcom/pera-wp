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

if ( ! function_exists( 'pera_property_archive_schema_title' ) ) {
  function pera_property_archive_schema_title(): string {
    if ( is_tax( pera_get_property_archive_taxonomies() ) ) {
      $term = get_queried_object();
      if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
        $manual = pera_get_property_archive_term_manual_seo_title( $term );
        if ( $manual !== '' ) {
          return $manual;
        }

        $generated = pera_get_property_archive_generated_title( $term );
        if ( $generated !== '' ) {
          return $generated;
        }

        return pera_seo_normalize_meta_text( (string) $term->name );
      }
    }

    return 'Property for sale in Istanbul | Pera Property';
  }
}

if ( ! function_exists( 'pera_property_archive_schema_description' ) ) {
  function pera_property_archive_schema_description( bool $is_filtered, bool $has_seo_plugin ): string {
    if ( $is_filtered || $has_seo_plugin ) {
      return '';
    }

    if ( is_tax( pera_get_property_archive_taxonomies() ) ) {
      $term = get_queried_object();
      if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
        return '';
      }

      $manual = pera_get_property_archive_term_manual_meta_description( $term );
      if ( $manual !== '' ) {
        return $manual;
      }

      return pera_get_property_archive_term_excerpt_fallback( $term );
    }

    if ( is_post_type_archive( 'property' ) ) {
      return pera_get_property_archive_generated_description();
    }

    return '';
  }
}

if ( ! function_exists( 'pera_property_archive_schema_breadcrumb_items' ) ) {
  function pera_property_archive_schema_breadcrumb_items( string $canonical, string $title ): array {
    $items = array(
      array(
        '@type'    => 'ListItem',
        'position' => 1,
        'name'     => 'Home',
        'item'     => home_url( '/' ),
      ),
      array(
        '@type'    => 'ListItem',
        'position' => 2,
        'name'     => 'Property',
        'item'     => get_post_type_archive_link( 'property' ) ?: home_url( '/property/' ),
      ),
    );

    if ( is_tax( pera_get_property_archive_taxonomies() ) ) {
      $term = get_queried_object();
      if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
        $items[] = array(
          '@type'    => 'ListItem',
          'position' => 3,
          'name'     => $title !== '' ? $title : pera_seo_normalize_meta_text( (string) $term->name ),
          'item'     => $canonical,
        );
      }
    }

    return $items;
  }
}

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

  $meta_desc = pera_property_archive_schema_description( $is_filtered, $has_seo_plugin );

  echo "\n<!-- Pera SEO: Property archive -->\n";

  if ( $meta_desc !== '' ) {
    echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '">' . "\n";
  }

  echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";

  // Archive/taxonomy schema is intentionally owned by this module so page-type
  // schema logic stays close to canonical/robots ownership for the same context.
  // We intentionally defer ItemList for this phase to avoid fragile coupling to
  // loop internals on paginated and filtered archive requests.
  if ( ! $is_filtered && $canonical !== '' ) {
    $title = pera_property_archive_schema_title();
    $graph = array(
      '@context' => 'https://schema.org',
      '@graph'   => array(),
    );

    $collection_page = array(
      '@type' => 'CollectionPage',
      '@id'   => $canonical . '#collection-page',
      'url'   => $canonical,
      'name'  => $title,
    );

    if ( $meta_desc !== '' ) {
      $collection_page['description'] = $meta_desc;
    }

    $graph['@graph'][] = $collection_page;

    $graph['@graph'][] = array(
      '@type'           => 'BreadcrumbList',
      '@id'             => $canonical . '#breadcrumb',
      'itemListElement' => pera_property_archive_schema_breadcrumb_items( $canonical, $title ),
    );

    echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
  }

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
