<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Single Property SEO / Social meta
 * Uses:
 * - WP excerpt => meta description
 * - ACF main_image (image array) => og:image / twitter:image
 */

if ( ! function_exists('pera_property_get_social_image') ) {
  function pera_property_get_social_image( int $post_id ): array {

    $url = '';
    $alt = '';

    if ( function_exists('get_field') ) {
      $main_image = get_field('main_image', $post_id);

      if ( is_array($main_image) ) {
        if ( ! empty($main_image['url']) ) $url = (string) $main_image['url'];
        if ( ! empty($main_image['alt']) ) $alt = (string) $main_image['alt'];

        if ( empty($url) && ! empty($main_image['ID']) ) {
          $resolved = wp_get_attachment_image_url((int) $main_image['ID'], 'full');
          if ( $resolved ) $url = (string) $resolved;
        }

        if ( empty($alt) && ! empty($main_image['ID']) ) {
          $resolved_alt = get_post_meta((int) $main_image['ID'], '_wp_attachment_image_alt', true);
          if ( is_string($resolved_alt) && $resolved_alt !== '' ) $alt = $resolved_alt;
        }
      }
    }

    if ( empty($url) ) {
      $thumb_id = get_post_thumbnail_id($post_id);
      if ( $thumb_id ) {
        $url = (string) wp_get_attachment_image_url((int) $thumb_id, 'full');
        $thumb_alt = get_post_meta((int) $thumb_id, '_wp_attachment_image_alt', true);
        if ( is_string($thumb_alt) && $thumb_alt !== '' ) $alt = $thumb_alt;
      }
    }

    return array(
      'url' => $url ? esc_url($url) : '',
      'alt' => $alt ? trim($alt) : '',
    );
  }
}

if ( ! function_exists('pera_property_get_meta_description') ) {
  function pera_property_get_meta_description( int $post_id ): string {

    $desc = wp_strip_all_tags( get_the_excerpt($post_id) );
    $desc = trim( preg_replace('/\s+/', ' ', $desc) );

    if ( function_exists('mb_substr') ) $desc = mb_substr($desc, 0, 160);
    else $desc = substr($desc, 0, 160);

    return $desc;
  }
}

if ( ! function_exists( 'pera_property_get_district_name' ) ) {
  function pera_property_get_district_name( int $post_id ): string {
    if ( $post_id < 1 ) {
      return '';
    }

    if ( function_exists( 'pera_get_deepest_term' ) ) {
      $term = pera_get_deepest_term( $post_id, 'district' );
      if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
        $name = trim( (string) $term->name );
        if ( strcasecmp( $name, 'Istanbul' ) === 0 ) {
          return '';
        }

        return $name;
      }
    }

    $terms = get_the_terms( $post_id, 'district' );
    if ( is_array( $terms ) && ! empty( $terms ) ) {
      $term = $terms[0];
      if ( $term instanceof WP_Term ) {
        $name = trim( (string) $term->name );
        if ( strcasecmp( $name, 'Istanbul' ) === 0 ) {
          return '';
        }

        return $name;
      }
    }

    return '';
  }
}

if ( ! function_exists( 'pera_property_get_v2_units' ) ) {
  function pera_property_get_v2_units( int $post_id ): array {
    if ( function_exists( 'get_field' ) ) {
      $rows = get_field( 'v2_units', $post_id );
      if ( is_array( $rows ) ) {
        return $rows;
      }
    }

    return array();
  }
}

if ( ! function_exists( 'pera_property_find_unit_row' ) ) {
  function pera_property_find_unit_row( int $post_id, string $unit_key ): ?array {
    $rows = pera_property_get_v2_units( $post_id );
    if ( empty( $rows ) ) {
      return null;
    }

    $unit_key = trim( (string) $unit_key );

    // 1) If numeric, treat as BED COUNT (primary meaning of ?unit_key=2 etc.)
    if ( $unit_key !== '' && ctype_digit( $unit_key ) ) {
      $beds = (int) $unit_key;

      foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) continue;

        $b = isset( $row['v2_bedrooms'] ) ? (int) $row['v2_bedrooms'] : 0;
        if ( $b === $beds ) {
          return $row;
        }
      }

      // Optional fallback (only if you want to support legacy "row index" links)
      $row_index = $beds - 1;
      if ( $row_index >= 0 && isset( $rows[ $row_index ] ) && is_array( $rows[ $row_index ] ) ) {
        return $rows[ $row_index ];
      }
    }

    // 2) Fallback: match v2_index_key exactly (if unit_key is a full index key string)
    foreach ( $rows as $row ) {
      if ( ! is_array( $row ) ) continue;

      if ( isset( $row['v2_index_key'] ) && (string) $row['v2_index_key'] === $unit_key ) {
        return $row;
      }
    }

    return null;
  }
}

if ( ! function_exists( 'pera_property_extract_bedrooms' ) ) {
  function pera_property_extract_bedrooms( array $row ): ?int {
    $keys = array( 'v2_bedrooms', 'beds', 'bedrooms', 'v2_beds' );
    foreach ( $keys as $key ) {
      if ( isset( $row[ $key ] ) && $row[ $key ] !== '' ) {
        if ( is_numeric( $row[ $key ] ) ) {
          return (int) $row[ $key ];
        }
        if ( preg_match( '/\d+/', (string) $row[ $key ], $matches ) ) {
          return (int) $matches[0];
        }
      }
    }

    if ( isset( $row['v2_index_key'] ) && is_string( $row['v2_index_key'] ) ) {
      if ( preg_match( '/\d+/', $row['v2_index_key'], $matches ) ) {
        return (int) $matches[0];
      }
    }

    return null;
  }
}

add_filter( 'pre_get_document_title', function ( string $title ): string {
  if ( ! is_singular( 'property' ) ) {
    return $title;
  }

  $post_id = (int) get_queried_object_id();
  if ( ! $post_id ) {
    return $title;
  }

  $district = pera_property_get_district_name( $post_id );
  $location = $district !== '' ? $district . ', Istanbul' : 'Istanbul';

  $unit_key = isset( $_GET['unit_key'] ) ? trim( (string) $_GET['unit_key'] ) : '';
  if ( $unit_key !== '' ) {
    $row = pera_property_find_unit_row( $post_id, $unit_key );
    if ( $row ) {
      $bedrooms = pera_property_extract_bedrooms( $row );
      if ( $bedrooms && $bedrooms > 0 ) {
        return sprintf(
          '%d bedroom apartment for sale in %s | Pera Property',
          $bedrooms,
          $location
        );
      }
    }
  }

  return sprintf( 'Property for sale in %s | Pera Property', $location );
}, 9 );

add_action('wp_head', function () {

  if ( ! is_singular('property') ) return;

  $post_id = (int) get_queried_object_id();
  if ( ! $post_id ) return;

  $title = wp_strip_all_tags( get_the_title($post_id) );
  $url   = get_permalink($post_id);

  $desc    = pera_property_get_meta_description($post_id);
  $img     = pera_property_get_social_image($post_id);
  $img_url = $img['url'];
  $img_alt = $img['alt'] ?: $title;

  echo "\n<!-- Pera: Single Property SEO / Social -->\n";

  if ( $desc !== '' ) {
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
  }

  // edited out as WP handles this part. echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";

  // Open Graph
  echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo('name') ) . '">' . "\n";
  echo '<meta property="og:type" content="article">' . "\n";
  echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
  echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";

  if ( $desc !== '' ) {
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
  }
  if ( $img_url ) {
    echo '<meta property="og:image" content="' . esc_url($img_url) . '">' . "\n";
    echo '<meta property="og:image:alt" content="' . esc_attr($img_alt) . '">' . "\n";
  }

  // Twitter
  echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
  echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";

  if ( $desc !== '' ) {
    echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";
  }
  if ( $img_url ) {
    echo '<meta name="twitter:image" content="' . esc_url($img_url) . '">' . "\n";
    echo '<meta name="twitter:image:alt" content="' . esc_attr($img_alt) . '">' . "\n";
  }

  echo "<!-- /Pera: Single Property SEO / Social -->\n\n";

}, 12);

/**
 * Property robots/canonical rules:
 * - Base URL => indexable (no noindex)
 * - Unit/detail params (e.g. ?unit_key=2) => noindex,follow + canonical to base permalink
 */
if ( ! function_exists( 'pera_property_has_unit_params' ) ) {
  function pera_property_has_unit_params(): bool {
    if ( ! is_singular( 'property' ) ) {
      return false;
    }

    $unit_params = array( 'unit_key' );

    foreach ( $unit_params as $param ) {
      if ( isset( $_GET[ $param ] ) && (string) $_GET[ $param ] !== '' ) {
        return true;
      }
    }

    return false;
  }
}

add_filter( 'wp_robots', function ( array $robots ): array {
  if ( pera_property_has_unit_params() ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  return $robots;
} );

add_filter( 'get_canonical_url', function ( ?string $canonical, $post ): ?string {
  if ( ! pera_property_has_unit_params() ) {
    return $canonical;
  }

  $post_id = 0;
  if ( is_object( $post ) && isset( $post->ID ) ) {
    $post_id = (int) $post->ID;
  } elseif ( is_numeric( $post ) ) {
    $post_id = (int) $post;
  } else {
    $post_id = (int) get_queried_object_id();
  }

  if ( $post_id > 0 ) {
    return get_permalink( $post_id );
  }

  return $canonical;
}, 10, 2 );
