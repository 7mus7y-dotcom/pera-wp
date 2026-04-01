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

if ( ! function_exists( 'pera_property_archive_resolve_image_from_acf_value' ) ) {
  /**
   * Normalize common ACF image return formats (array/id/url).
   *
   * @return array{url:string,alt:string}
   */
  function pera_property_archive_resolve_image_from_acf_value( $value ): array {
    $url = '';
    $alt = '';

    if ( is_array( $value ) ) {
      if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
        $url = $value['url'];
      }

      if ( isset( $value['alt'] ) && is_string( $value['alt'] ) ) {
        $alt = $value['alt'];
      }

      if ( $url === '' && ! empty( $value['ID'] ) ) {
        $candidate = wp_get_attachment_image_url( (int) $value['ID'], 'full' );
        if ( $candidate ) {
          $url = (string) $candidate;
        }
      }

      if ( $alt === '' && ! empty( $value['ID'] ) ) {
        $candidate_alt = get_post_meta( (int) $value['ID'], '_wp_attachment_image_alt', true );
        if ( is_string( $candidate_alt ) ) {
          $alt = $candidate_alt;
        }
      }
    } elseif ( is_numeric( $value ) ) {
      $attachment_id = (int) $value;
      if ( $attachment_id > 0 ) {
        $candidate = wp_get_attachment_image_url( $attachment_id, 'full' );
        if ( $candidate ) {
          $url = (string) $candidate;
        }

        $candidate_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        if ( is_string( $candidate_alt ) ) {
          $alt = $candidate_alt;
        }
      }
    } elseif ( is_string( $value ) ) {
      $url = trim( $value );
    }

    return array(
      'url' => $url !== '' ? esc_url( $url ) : '',
      'alt' => $alt !== '' ? pera_seo_normalize_meta_text( $alt ) : '',
    );
  }
}

if ( ! function_exists( 'pera_property_archive_taxonomy_manual_social_image' ) ) {
  /**
   * Optional manual term social image from existing ACF term fields.
   *
   * We only read existing field names; no new storage is introduced here.
   *
   * @return array{url:string,alt:string}
   */
  function pera_property_archive_taxonomy_manual_social_image( WP_Term $term ): array {
    if ( ! function_exists( 'get_field' ) ) {
      return array( 'url' => '', 'alt' => '' );
    }

    $field_names = array(
      'seo_social_image',
      'social_image',
      'seo_og_image',
      'og_image',
      'twitter_image',
    );

    $term_refs = array(
      $term,
      $term->taxonomy . '_' . (int) $term->term_id,
      'term_' . (int) $term->term_id,
      (int) $term->term_id,
    );

    foreach ( $field_names as $field_name ) {
      foreach ( $term_refs as $ref ) {
        $image = pera_property_archive_resolve_image_from_acf_value( get_field( $field_name, $ref ) );
        if ( $image['url'] !== '' ) {
          return $image;
        }
      }
    }

    return array( 'url' => '', 'alt' => '' );
  }
}

if ( ! function_exists( 'pera_property_archive_social_image' ) ) {
  /**
   * Resolve social image for property archive/taxonomy contexts.
   *
   * Taxonomy precedence:
   * 1) Existing manual term social image field (if present),
   * 2) Existing term featured image helper,
   * 3) Site default social image.
   *
   * /property/ archive precedence:
   * 1) Site default social image (manual archive override deferred).
   *
   * @return array{url:string,alt:string}
   */
  function pera_property_archive_social_image(): array {
    if ( is_tax( pera_get_property_archive_taxonomies() ) ) {
      $term = get_queried_object();
      if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
        $manual = pera_property_archive_taxonomy_manual_social_image( $term );
        if ( $manual['url'] !== '' ) {
          return $manual;
        }

        if ( function_exists( 'pera_get_term_featured_image_url' ) ) {
          $url = (string) pera_get_term_featured_image_url( (int) $term->term_id, (string) $term->taxonomy, 'full' );
          if ( $url !== '' ) {
            return array(
              'url' => esc_url( $url ),
              'alt' => pera_seo_normalize_meta_text( (string) $term->name ),
            );
          }
        }
      }
    }

    if ( function_exists( 'pera_seo_default_image' ) ) {
      $fallback = pera_seo_default_image();
      $url = isset( $fallback['url'] ) ? (string) $fallback['url'] : '';
      $alt = isset( $fallback['alt'] ) ? (string) $fallback['alt'] : '';

      if ( $url !== '' ) {
        return array(
          'url' => esc_url( $url ),
          'alt' => $alt !== '' ? pera_seo_normalize_meta_text( $alt ) : '',
        );
      }
    }

    return array( 'url' => '', 'alt' => '' );
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

  // Social ownership for property archive/taxonomy contexts.
  // Skip filtered URLs (aligned with noindex strategy) and SEO plugin stacks.
  if ( ! $is_filtered && ! $has_seo_plugin && $canonical !== '' ) {
    $social_title = pera_property_archive_schema_title();
    $social_image = pera_property_archive_social_image();
    $social_desc  = $meta_desc;

    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $social_title ) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";

    if ( $social_desc !== '' ) {
      echo '<meta property="og:description" content="' . esc_attr( $social_desc ) . '">' . "\n";
    }

    if ( $social_image['url'] !== '' ) {
      echo '<meta property="og:image" content="' . esc_url( $social_image['url'] ) . '">' . "\n";
    }

    echo '<meta name="twitter:card" content="' . ( $social_image['url'] !== '' ? 'summary_large_image' : 'summary' ) . '">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( $social_title ) . '">' . "\n";

    if ( $social_desc !== '' ) {
      echo '<meta name="twitter:description" content="' . esc_attr( $social_desc ) . '">' . "\n";
    }

    if ( $social_image['url'] !== '' ) {
      echo '<meta name="twitter:image" content="' . esc_url( $social_image['url'] ) . '">' . "\n";
    }
  }

  // Archive/taxonomy schema is intentionally owned by this module so page-type
  // schema logic stays close to canonical/robots ownership for the same context.
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

    // ItemList intentionally deferred: archive-property.php builds a dedicated
    // $property_query after get_header(), so wp_head cannot safely prove the
    // rendered card set is the main query on first render.

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
