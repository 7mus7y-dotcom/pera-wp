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

if ( ! function_exists( 'pera_property_archive_has_query_string' ) ) {
  function pera_property_archive_has_query_string(): bool {
    return ! empty( $_GET );
  }
}

if ( ! function_exists( 'pera_property_archive_is_clean_main_archive' ) ) {
  /**
   * True only for the first, unfiltered /property/ archive page.
   */
  function pera_property_archive_is_clean_main_archive(): bool {
    return is_post_type_archive( 'property' ) && ! is_tax() && ! is_search() && ! is_paged() && empty( $_GET );
  }
}

if ( ! function_exists( 'pera_property_archive_settings_field' ) ) {
  /**
   * Safely read an ACF field from the private property archive SEO settings page.
   *
   * @return mixed|string
   */
  function pera_property_archive_settings_field( string $field_name ) {
    if ( ! function_exists( 'get_field' ) || ! function_exists( 'pera_get_property_archive_settings_page_id' ) ) {
      return '';
    }

    $page_id = pera_get_property_archive_settings_page_id();
    if ( ! $page_id ) {
      return '';
    }

    $value = get_field( $field_name, $page_id );

    return is_string( $value ) ? trim( $value ) : $value;
  }
}

if ( ! function_exists( 'pera_property_archive_settings_text_field' ) ) {
  /**
   * Read a scalar settings value and normalize it for safe SEO text output.
   */
  function pera_property_archive_settings_text_field( string $field_name ): string {
    $value = pera_property_archive_settings_field( $field_name );

    if ( ! is_scalar( $value ) ) {
      return '';
    }

    return pera_seo_normalize_meta_text( (string) $value );
  }
}

if ( ! function_exists( 'pera_property_archive_schema_title' ) ) {
  function pera_property_archive_schema_title(): string {
    if ( is_tax( pera_get_indexable_property_archive_taxonomies() ) ) {
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

    if ( pera_property_archive_is_clean_main_archive() ) {
      $manual = pera_property_archive_settings_text_field( 'seo_title' );
      if ( $manual !== '' ) {
        return $manual;
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

    if ( is_tax( pera_get_indexable_property_archive_taxonomies() ) ) {
      $term = get_queried_object();
      if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
        return '';
      }

      $manual = pera_get_property_archive_term_manual_meta_description( $term );
      if ( $manual !== '' ) {
        return $manual;
      }

      $fallback = pera_get_property_archive_term_excerpt_fallback( $term );
      if ( $fallback !== '' ) {
        return $fallback;
      }

      return function_exists( 'pera_get_property_archive_generated_term_description' )
        ? pera_get_property_archive_generated_term_description( $term )
        : '';
    }

    if ( is_post_type_archive( 'property' ) ) {
      if ( pera_property_archive_is_clean_main_archive() ) {
        $manual = pera_property_archive_settings_text_field( 'seo_meta_description' );
        if ( $manual !== '' ) {
          return $manual;
        }
      }

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

    if ( is_tax( pera_get_indexable_property_archive_taxonomies() ) ) {
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

if ( ! function_exists( 'pera_property_archive_schema_item_list_elements' ) ) {
  /**
   * Build ItemList entries from the same query builder used by archive-property.php.
   *
   * @return array<int,array<string,mixed>>
   */
  function pera_property_archive_schema_item_list_elements(): array {
    if ( ! function_exists( 'pera_property_archive_build_args_from_context' ) ) {
      return array();
    }

    $paged = function_exists( 'pera_property_archive_get_paged' )
      ? (int) pera_property_archive_get_paged()
      : max( 1, (int) get_query_var( 'paged' ) );

    $taxonomy_context = function_exists( 'pera_get_property_tax_archive_context' )
      ? pera_get_property_tax_archive_context()
      : array();

    $normalize_tax_slugs = static function ( $raw ): array {
      if ( is_array( $raw ) ) {
        $decoded = array_map(
          static function ( $value ): string {
            return rawurldecode( wp_unslash( (string) $value ) );
          },
          $raw
        );
      } elseif ( $raw !== null && $raw !== '' ) {
        $decoded = array( rawurldecode( wp_unslash( (string) $raw ) ) );
      } else {
        $decoded = array();
      }

      $flat = array();
      foreach ( $decoded as $item ) {
        foreach ( explode( ',', $item ) as $piece ) {
          $slug = sanitize_title( trim( $piece ) );
          if ( $slug !== '' ) {
            $flat[ $slug ] = true;
          }
        }
      }

      return array_keys( $flat );
    };

    $current_keyword = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
    $current_keyword = trim( $current_keyword );
    $current_keyword_is_post_id = ( $current_keyword !== '' ) && preg_match( '/^\d+$/', $current_keyword );
    $current_keyword_post_id    = $current_keyword_is_post_id ? absint( $current_keyword ) : 0;

    $qs_min = ( isset( $_GET['min_price'] ) && $_GET['min_price'] !== '' ) ? absint( $_GET['min_price'] ) : 0;
    $qs_max = ( isset( $_GET['max_price'] ) && $_GET['max_price'] !== '' ) ? absint( $_GET['max_price'] ) : 0;

    $ctx = array(
      'paged'                      => max( 1, $paged ),
      'current_district'           => $normalize_tax_slugs( $_GET['district'] ?? array() ),
      'current_tag'                => $normalize_tax_slugs( $_GET['property_tags'] ?? array() ),
      'current_type'               => isset( $_GET['property_type'] ) ? sanitize_title( wp_unslash( (string) $_GET['property_type'] ) ) : '',
      'selected_beds'              => isset( $_GET['v2_beds'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['v2_beds'] ) ) : '',
      'current_keyword'            => $current_keyword,
      'current_keyword_is_post_id' => $current_keyword_is_post_id,
      'current_keyword_post_id'    => $current_keyword_post_id,
      'taxonomy_context'           => is_array( $taxonomy_context ) ? $taxonomy_context : array(),
      'has_price_qs'               => ( $qs_min > 0 || $qs_max > 0 ),
      'qs_min'                     => $qs_min,
      'qs_max'                     => $qs_max,
      'sort'                       => isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sort'] ) ) : 'date_desc',
    );

    $args = pera_property_archive_build_args_from_context(
      $ctx,
      array(
        'no_found_rows'  => true,
        'fields'         => 'ids',
      )
    );
    $args['no_found_rows'] = true;
    $args['fields']        = 'ids';

    $query = new WP_Query( $args );
    if ( empty( $query->posts ) || ! is_array( $query->posts ) ) {
      return array();
    }

    $posts_per_page = isset( $args['posts_per_page'] ) ? max( 1, (int) $args['posts_per_page'] ) : 12;
    $position       = ( max( 1, $paged ) - 1 ) * $posts_per_page;
    $items          = array();

    foreach ( $query->posts as $post_id ) {
      $post_id    = (int) $post_id;
      $permalink  = get_permalink( $post_id );
      $post_title = trim( (string) get_the_title( $post_id ) );

      if ( ! is_string( $permalink ) || $permalink === '' ) {
        continue;
      }

      $position++;
      $entry = array(
        '@type'    => 'ListItem',
        'position' => $position,
        'url'      => $permalink,
      );

      if ( $post_title !== '' ) {
        $entry['name'] = $post_title;
      }

      $items[] = $entry;
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
    if ( is_tax( pera_get_indexable_property_archive_taxonomies() ) ) {
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

    if ( pera_property_archive_is_clean_main_archive() ) {
      $manual = pera_property_archive_resolve_image_from_acf_value( pera_property_archive_settings_field( 'seo_social_image' ) );
      if ( $manual['url'] !== '' ) {
        return $manual;
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
  if ( is_tax( pera_get_indexable_property_archive_taxonomies() ) ) {
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

  if ( pera_property_archive_has_query_string() ) {
    return 'Search results | ' . $title;
  }

  // Main /property/ TITLE precedence:
  // 1) private settings page ACF seo_title,
  // 2) generated page-1 title, 3) existing/default title for pagination.
  $paged = function_exists( 'pera_property_archive_get_paged' )
    ? pera_property_archive_get_paged()
    : max( 1, (int) get_query_var( 'paged' ) );

  if ( $paged > 1 ) {
    return $title;
  }

  if ( ! empty( $_GET ) ) {
    return $title;
  }

  $manual = pera_property_archive_settings_text_field( 'seo_title' );
  if ( $manual !== '' ) {
    return $manual;
  }

  return 'Property for Sale in Istanbul | Apartments & Investment Opportunities';
}, 20 );

add_action( 'wp_head', function () {

  // Run only on property archive ownership contexts (archive + taxonomies).
  $is_property_context = function_exists( 'pera_is_property_archive_context' )
    ? pera_is_property_archive_context()
    : is_post_type_archive( 'property' );

  if ( ! $is_property_context ) {
    return;
  }

  $has_query_string = pera_property_archive_has_query_string();
  $is_filtered = pera_property_archive_is_filtered_request() || $has_query_string;

  $canonical = function_exists( 'pera_property_archive_canonical_url' )
    ? pera_property_archive_canonical_url()
    : '';

  if ( $has_query_string ) {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $canonical = home_url( strtok( $request_uri, '?' ) );
  }

  if ( $canonical === '' ) {
    $paged = function_exists( 'pera_property_archive_get_paged' )
      ? pera_property_archive_get_paged()
      : max( 1, (int) get_query_var( 'paged' ) );

  if ( is_tax() && ! $has_query_string ) {      $qo = get_queried_object();
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
  $is_clean_main_property_archive = pera_property_archive_is_clean_main_archive();

  echo "\n<!-- Pera SEO: Property archive -->\n";

  if ( $meta_desc !== '' ) {
    echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '">' . "\n";
  }

  echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";

  // Social ownership for property archive/taxonomy contexts.
  // Keep tags enabled for filtered pages but prevent canonical pollution via og:url.
  if ( ! $has_seo_plugin && $canonical !== '' ) {
    $social_title = pera_property_archive_schema_title();
    $social_image = pera_property_archive_social_image();
    $social_desc  = $meta_desc;

    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $social_title ) . '">' . "\n";
    $og_url = $has_query_string
      ? home_url( add_query_arg( array(), isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/' ) )
      : $canonical;

    echo '<meta property="og:url" content="' . esc_url( $og_url ) . '">' . "\n";

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
  $is_indexable_property_tax_archive = is_tax( pera_get_indexable_property_archive_taxonomies() );

  if (
    ! $is_filtered
    && ( ! is_tax() || $is_indexable_property_tax_archive )
    && $canonical !== ''
    && (
      function_exists( 'pera_schema_should_emit_type' )
        ? pera_schema_should_emit_type(
            'CollectionPage',
            array(
              'context'                        => 'property_archive',
              'plugin_likely_outputs_same_type' => $has_seo_plugin,
            )
          )
        : true
    )
  ) {
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

    if (
      function_exists( 'pera_schema_should_emit_type' )
        ? pera_schema_should_emit_type(
            'BreadcrumbList',
            array(
              'context'                        => 'property_archive',
              'plugin_likely_outputs_same_type' => $has_seo_plugin,
            )
          )
        : true
    ) {
      $graph['@graph'][] = array(
        '@type'           => 'BreadcrumbList',
        '@id'             => $canonical . '#breadcrumb',
        'itemListElement' => pera_property_archive_schema_breadcrumb_items( $canonical, $title ),
      );
    }

    $qo = get_queried_object();
    $is_property_tax_archive = $qo instanceof WP_Term
      && ! is_wp_error( $qo )
      && in_array( (string) $qo->taxonomy, pera_get_indexable_property_archive_taxonomies(), true );
    $should_emit_item_list = $is_clean_main_property_archive || $is_property_tax_archive;

    if (
      $should_emit_item_list
      && (
        function_exists( 'pera_schema_should_emit_type' )
          ? pera_schema_should_emit_type(
              'ItemList',
              array(
                'context' => 'district_archive',
              )
            )
          : true
      )
    ) {
      $item_list_elements = pera_property_archive_schema_item_list_elements();
      if ( ! empty( $item_list_elements ) ) {
        $graph['@graph'][] = array(
          '@type'           => 'ItemList',
          '@id'             => $canonical . '#itemlist',
          'itemListElement' => $item_list_elements,
        );
      }
    }

    echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
  }

  if (
    ! $has_seo_plugin
    && ! $is_filtered
    && $is_clean_main_property_archive
    && $canonical !== ''
  ) {
    $faq_json = pera_property_archive_settings_field( 'seo_faq_schema_json' );
    $faq_json = is_scalar( $faq_json ) ? trim( (string) $faq_json ) : '';

    if ( $faq_json !== '' ) {
      $faq_schema = json_decode( $faq_json, true );
      if ( json_last_error() === JSON_ERROR_NONE && is_array( $faq_schema ) ) {
        $encoded_faq_schema = wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( is_string( $encoded_faq_schema ) && $encoded_faq_schema !== '' ) {
          echo '<script type="application/ld+json">' . $encoded_faq_schema . '</script>' . "\n";
        }
      }
    }
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

  $is_filtered = pera_property_archive_is_filtered_request() || pera_property_archive_has_query_string();

  if ( is_tax( 'special' ) ) {
    $robots['noindex'] = true;
    $robots['follow']  = true;
    return $robots;
  }

  if ( $is_filtered ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  return $robots;
} );
