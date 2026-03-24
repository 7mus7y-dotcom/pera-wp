<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Pera SEO / Social meta (OG + Twitter) – No Yoast
 * - Applies to all public pages EXCEPT single Property CPT (handled separately)
 * - Adds <meta description>, canonical, OG, Twitter
 * - Adds document title filters (so <title> is correct)
 * - Adds "noindex,follow" via wp_robots for:
 *    - WP search results
 *    - Property archive URLs that are filtered via querystring (to avoid index bloat)
 */

/* =======================================================
   CONFIG
======================================================= */

/**
 * Optional: fallback share image (attachment ID).
 * Put a site-wide share image in Media Library and set its attachment ID here.
 * If 0, no fallback image is used.
 */
if ( ! defined('PERA_SEO_DEFAULT_OG_IMAGE_ID') ) {
  define('PERA_SEO_DEFAULT_OG_IMAGE_ID', 0);
}

/**
 * SEO safety net:
 * Ensure core title-tag support exists even if parent theme changes or is removed.
 */
add_action( 'after_setup_theme', function () {
  if ( ! current_theme_supports( 'title-tag' ) ) {
    add_theme_support( 'title-tag' );
  }
}, 5 );

/**
 * Safety: prevent parent theme (Hello Elementor) from outputting its own meta description
 * to avoid duplicates. No-op if the function doesn't exist.
 */
add_action( 'after_setup_theme', function () {
  if ( has_action( 'wp_head', 'hello_elementor_add_description_meta_tag' ) ) {
    remove_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );
  }
}, 20 );


/* =======================================================
   HELPERS
======================================================= */

if ( ! function_exists('pera_seo_all_get_description') ) {
  function pera_seo_all_get_description( int $post_id ): string {

    $post_type = get_post_type( $post_id );

    if ( $post_type === 'post' ) {
      $manual_desc = '';

      // Prefer an ACF field when available for editor-controlled post snippets.
      if ( function_exists( 'get_field' ) ) {
        $manual_desc = (string) get_field( 'seo_meta_description', $post_id );
      }

      // Native fallback meta key for environments without ACF field registration.
      if ( $manual_desc === '' ) {
        $manual_desc = (string) get_post_meta( $post_id, 'seo_meta_description', true );
      }

      $manual_desc = pera_seo_all_normalize_description( $manual_desc );
      if ( $manual_desc !== '' ) {
        return $manual_desc;
      }
    }

    $desc = pera_seo_all_normalize_description( (string) get_the_excerpt( $post_id ) );

    if ( $desc === '' ) {
      $content = get_post_field( 'post_content', $post_id );
      $content = (string) apply_filters( 'the_content', $content );
      $desc = pera_seo_all_normalize_description( $content );
    }

    return $desc;
  }
}

if ( ! function_exists('pera_seo_all_normalize_description') ) {
  /**
   * Clean and softly trim a description to search/social-friendly length.
   */
  function pera_seo_all_normalize_description( string $raw, int $max_len = 160 ): string {
    $desc = wp_strip_all_tags( $raw );
    $desc = trim( preg_replace( '/\s+/', ' ', $desc ) );

    if ( $desc === '' ) {
      return '';
    }

    if ( function_exists( 'mb_strlen' ) && mb_strlen( $desc ) <= $max_len ) {
      return $desc;
    }

    if ( ! function_exists( 'mb_strlen' ) && strlen( $desc ) <= $max_len ) {
      return $desc;
    }

    if ( function_exists( 'mb_substr' ) ) {
      $cut = mb_substr( $desc, 0, $max_len + 1 );
      $cut = preg_replace( '/\s+\S*$/u', '', $cut );
      $cut = trim( (string) $cut );
      return $cut !== '' ? $cut : mb_substr( $desc, 0, $max_len );
    }

    $cut = substr( $desc, 0, $max_len + 1 );
    $cut = preg_replace( '/\s+\S*$/', '', $cut );
    $cut = trim( (string) $cut );
    return $cut !== '' ? $cut : substr( $desc, 0, $max_len );
  }
}

if ( ! function_exists('pera_seo_all_get_image') ) {
  function pera_seo_all_get_image( int $post_id ): array {
    $url = '';
    $alt = '';
    $width  = 0;
    $height = 0;

    $thumb_id = get_post_thumbnail_id( $post_id );

    if ( $thumb_id ) {
      $url = (string) wp_get_attachment_image_url( (int) $thumb_id, 'full' );
      $image_meta = wp_get_attachment_metadata( (int) $thumb_id );
      if ( is_array( $image_meta ) ) {
        $width  = isset( $image_meta['width'] ) ? (int) $image_meta['width'] : 0;
        $height = isset( $image_meta['height'] ) ? (int) $image_meta['height'] : 0;
      }

      $alt_meta = get_post_meta( (int) $thumb_id, '_wp_attachment_image_alt', true );
      if ( is_string($alt_meta) && $alt_meta !== '' ) {
        $alt = $alt_meta;
      }
    }

    return array(
      'url' => $url ? esc_url($url) : '',
      'alt' => $alt ? trim($alt) : '',
      'width' => $width,
      'height' => $height,
      'attachment_id' => $thumb_id ? (int) $thumb_id : 0,
    );
  }
}

if ( ! function_exists('pera_seo_default_image') ) {
  function pera_seo_default_image(): array {
    $id = (int) PERA_SEO_DEFAULT_OG_IMAGE_ID;
    if ( ! $id ) return array('url' => '', 'alt' => '');

    $url = (string) wp_get_attachment_image_url( $id, 'full' );
    $alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );

    return array(
      'url' => $url ? esc_url($url) : '',
      'alt' => $alt ? trim($alt) : '',
    );
  }
}

/**
 * Canonical fallback (keeps scheme/host consistent).
 */
if ( ! function_exists('pera_seo_all_canonical_fallback') ) {
  function pera_seo_all_canonical_fallback(): string {
    $req = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $req = $req ?: '/';
    $req = preg_replace('/#.*/', '', $req);
    return esc_url( home_url( $req ) );
  }
}

/**
 * Detect “filtered property archive” requests (querystring facets & sort).
 * These should almost always be NOINDEX to avoid index bloat.
 */
if ( ! function_exists('pera_is_filtered_property_archive') ) {
  function pera_is_filtered_property_archive(): bool {
    return function_exists( 'pera_property_archive_is_filtered_request' )
      ? pera_property_archive_is_filtered_request( $_GET )
      : false;
  }
}

/**
 * For property contexts, return the stable base URL you want to canonical to
 * when filters are present.
 */
if ( ! function_exists('pera_property_archive_base_url') ) {
  function pera_property_archive_base_url(): string {
    $qo = get_queried_object();

    // Taxonomy archive canonical should usually be itself (term link),
    // but for FILTERED taxonomy archive URLs, we still canonical to the term page.
    if ( is_tax() && $qo && ! is_wp_error($qo) ) {
      $term_link = get_term_link( $qo );
      if ( ! is_wp_error($term_link) && $term_link ) return (string) $term_link;
    }

    return (string) get_post_type_archive_link( 'property' );
  }
}

/* =======================================================
   ROBOTS RULES (wp_robots)
======================================================= */

add_filter( 'wp_robots', function ( array $robots ): array {

  if ( is_admin() ) return $robots;

  // Exclude your single-property SEO module
  if ( is_singular('property') ) return $robots;

  // WP search results: noindex
  if ( is_search() ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  // Filtered property archive URLs: noindex (prevents index bloat)
  if ( pera_is_filtered_property_archive() ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  return $robots;
} );

/* =======================================================
   HEAD META (description, canonical, OG, Twitter)
======================================================= */

add_action( 'wp_head', function () {

  if ( is_admin() ) return;

  // Exclude your single-property SEO module
  if ( is_singular('property') ) return;

  // Usually not worth indexing/sharing like normal pages
  if ( is_404() ) return;

  $site_name = (string) get_bloginfo('name');
  $title     = wp_strip_all_tags( wp_get_document_title() );

  // Determine a sensible "context id" for excerpt/image
  $post_id = (int) get_queried_object_id();

  // Blog posts index (Settings -> Reading -> Posts page)
  if ( is_home() && ! is_front_page() ) {
    $posts_page_id = (int) get_option('page_for_posts');
    if ( $posts_page_id ) $post_id = $posts_page_id;
  }

  // ---------- Canonical ----------
  $canonical = '';
  $taxes = array( 'region', 'district', 'property_type', 'bedrooms', 'property_tags' );
  $taxes = array_values( array_filter( $taxes, 'taxonomy_exists' ) );

  $property_context = is_post_type_archive( 'property' )
    || ( ! empty( $taxes ) && is_tax( $taxes ) );

  if ( $property_context ) {
    $canonical = function_exists( 'pera_property_archive_canonical_url' )
      ? pera_property_archive_canonical_url()
      : '';

    if ( $canonical === '' ) {
      $canonical = pera_seo_all_canonical_fallback();
    }
  } else {
    if ( function_exists('wp_get_canonical_url') ) {
      $canonical = wp_get_canonical_url( $post_id ?: null );
    }
    if ( ! $canonical ) {
      $canonical = pera_seo_all_canonical_fallback();
    }
  }

  // ---------- Description ----------
  $desc = '';

  if ( $post_id ) {
    $desc = pera_seo_all_get_description( $post_id );
  } else {
    // Term archives: use term description if available
    if ( is_category() || is_tag() || is_tax() ) {
      $term = get_queried_object();
      if ( $term && ! is_wp_error($term) && ! empty($term->description) ) {
        $desc = wp_strip_all_tags( (string) $term->description );
        $desc = trim( preg_replace('/\s+/', ' ', $desc) );
        if ( function_exists('mb_substr') ) $desc = mb_substr($desc, 0, 160);
        else $desc = substr($desc, 0, 160);
      }
    }
  }

  // If filtered property archive and we have no desc, use a short stable one
  if ( $desc === '' && pera_is_filtered_property_archive() ) {
    $desc = 'Browse Istanbul property listings filtered by district, type, bedrooms and budget.';
  }

  // ---------- Image ----------
  $img_url = '';
  $img_alt = $title;
  $img_width = 0;
  $img_height = 0;
  $img_attachment_id = 0;

  if ( $post_id ) {
    $img = pera_seo_all_get_image( $post_id );
    $img_url = $img['url'];
    $img_alt = $img['alt'] ?: $title;
    $img_width = isset( $img['width'] ) ? (int) $img['width'] : 0;
    $img_height = isset( $img['height'] ) ? (int) $img['height'] : 0;
    $img_attachment_id = isset( $img['attachment_id'] ) ? (int) $img['attachment_id'] : 0;
  }

  // Default fallback share image (optional)
  if ( ! $img_url ) {
    $fallback = pera_seo_default_image();
    if ( ! empty($fallback['url']) ) {
      $img_url = $fallback['url'];
      $img_alt = $fallback['alt'] ?: $title;
    }
  }

  $og_type = is_singular() ? 'article' : 'website';

  echo "\n<!-- Pera: SEO / Social -->\n";

  if ( $desc !== '' ) {
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
  }

  // Canonical ownership note:
  // - standard single posts use WordPress core rel_canonical() as the single owner.
  // - this module owns canonical output for non-post contexts.
  if ( ! is_singular( 'post' ) ) {
    echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
  }

  // Open Graph
  echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
  echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
  echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
  echo '<meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";

  if ( $desc !== '' ) {
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
  }

  if ( $img_url ) {
    echo '<meta property="og:image" content="' . esc_url($img_url) . '">' . "\n";
    echo '<meta property="og:image:alt" content="' . esc_attr($img_alt) . '">' . "\n";
    if ( $img_width > 0 && $img_height > 0 ) {
      echo '<meta property="og:image:width" content="' . esc_attr( (string) $img_width ) . '">' . "\n";
      echo '<meta property="og:image:height" content="' . esc_attr( (string) $img_height ) . '">' . "\n";
    }
  }

  // Twitter
  echo '<meta name="twitter:card" content="' . ( $img_url ? 'summary_large_image' : 'summary' ) . '">' . "\n";
  echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";

  if ( $desc !== '' ) {
    echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";
  }

  if ( $img_url ) {
    echo '<meta name="twitter:image" content="' . esc_url($img_url) . '">' . "\n";
    echo '<meta name="twitter:image:alt" content="' . esc_attr($img_alt) . '">' . "\n";
  }

  if ( is_singular( 'post' ) && $post_id > 0 ) {
    $author_id      = (int) get_post_field( 'post_author', $post_id );
    $author_name    = $author_id > 0 ? trim( (string) get_the_author_meta( 'display_name', $author_id ) ) : '';
    $publisher     = (string) get_bloginfo( 'name' );
    $publisher_url = (string) home_url( '/' );
    $publisher_logo = '';
    $custom_logo_id = (int) get_theme_mod( 'custom_logo' );

    if ( $custom_logo_id > 0 ) {
      $publisher_logo = (string) wp_get_attachment_image_url( $custom_logo_id, 'full' );
    }

    $schema = array(
      '@context'         => 'https://schema.org',
      '@type'            => 'BlogPosting',
      'mainEntityOfPage' => array(
        '@type' => 'WebPage',
        '@id'   => $canonical,
      ),
      'headline'         => get_the_title( $post_id ),
      'datePublished'    => get_post_time( DATE_W3C, true, $post_id ),
      'dateModified'     => get_post_modified_time( DATE_W3C, true, $post_id ),
      'publisher'        => array(
        '@type' => 'Organization',
        'name'  => $publisher,
        'url'   => $publisher_url,
      ),
    );

    if ( $desc !== '' ) {
      $schema['description'] = $desc;
    }

    if ( $author_name !== '' ) {
      $schema['author'] = array(
        '@type' => 'Person',
        'name'  => $author_name,
      );
      if ( $author_id > 0 ) {
        $schema['author']['url'] = get_author_posts_url( $author_id );
      }
    }

    if ( $publisher_logo !== '' ) {
      $schema['publisher']['logo'] = array(
        '@type' => 'ImageObject',
        'url'   => $publisher_logo,
      );
    }

    if ( $img_attachment_id > 0 && $img_url !== '' ) {
      $schema_image = array(
        '@type' => 'ImageObject',
        'url'   => $img_url,
      );
      if ( $img_width > 0 && $img_height > 0 ) {
        $schema_image['width'] = $img_width;
        $schema_image['height'] = $img_height;
      }
      $schema['image'] = $schema_image;
    }

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
  }
  
  if ( is_tax() || is_category() || is_tag() ) {

  $term = get_queried_object();

  if ( $term instanceof WP_Term ) {

    $taxonomy = (string) $term->taxonomy;
    $term_id  = (int) $term->term_id;

    // Use your saved term meta, with safe fallbacks
    $desc = function_exists('pera_get_term_excerpt')
      ? pera_get_term_excerpt( $term_id, $taxonomy, 28 )
      : '';

    $img  = function_exists('pera_get_term_featured_image_url')
      ? pera_get_term_featured_image_url( $term_id, $taxonomy, 'full' )
      : '';

    if ( $desc !== '' ) {
      $desc = wp_strip_all_tags( $desc );
      $desc = trim( preg_replace('/\s+/', ' ', $desc ) );

      echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
      echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
      echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
    }

    if ( $img !== '' ) {
      echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
      echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
    }
  }
}

  echo "<!-- /Pera: SEO / Social -->\n\n";

}, 12 );
