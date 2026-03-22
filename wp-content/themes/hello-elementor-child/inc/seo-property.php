<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Single Property SEO / Social meta
 * Uses public fields only and must not expose internal project_name values.
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

if ( ! function_exists( 'pera_property_normalize_whitespace' ) ) {
  function pera_property_normalize_whitespace( string $value ): string {
    $value = wp_strip_all_tags( $value );
    $value = preg_replace( '/\s+/u', ' ', $value );
    return trim( (string) $value );
  }
}

if ( ! function_exists( 'pera_property_mb_strtolower' ) ) {
  function pera_property_mb_strtolower( string $value ): string {
    return function_exists( 'mb_strtolower' )
      ? mb_strtolower( $value, 'UTF-8' )
      : strtolower( $value );
  }
}

if ( ! function_exists( 'pera_property_string_contains' ) ) {
  function pera_property_string_contains( string $haystack, string $needle ): bool {
    if ( $needle === '' ) {
      return false;
    }

    if ( function_exists( 'mb_stripos' ) ) {
      return mb_stripos( $haystack, $needle, 0, 'UTF-8' ) !== false;
    }

    return stripos( $haystack, $needle ) !== false;
  }
}

if ( ! function_exists( 'pera_property_limit_text' ) ) {
  function pera_property_limit_text( string $value, int $limit = 160 ): string {
    $value = pera_property_normalize_whitespace( $value );
    if ( $value === '' || $limit < 1 ) {
      return '';
    }

    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
    if ( $length <= $limit ) {
      return $value;
    }

    $slice = function_exists( 'mb_substr' )
      ? mb_substr( $value, 0, $limit, 'UTF-8' )
      : substr( $value, 0, $limit );

    $slice = preg_replace( '/[\s\p{P}]+$/u', '', (string) $slice );
    return trim( (string) $slice ) . '…';
  }
}

if ( ! function_exists( 'pera_property_get_public_title' ) ) {
  function pera_property_get_public_title( int $post_id ): string {
    return pera_property_normalize_whitespace( get_the_title( $post_id ) );
  }
}

if ( ! function_exists( 'pera_property_get_meta_excerpt' ) ) {
  function pera_property_get_meta_excerpt( int $post_id ): string {
    return pera_property_normalize_whitespace( get_the_excerpt( $post_id ) );
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

if ( ! function_exists( 'pera_property_get_region_name' ) ) {
  function pera_property_get_region_name( int $post_id ): string {
    $terms = get_the_terms( $post_id, 'region' );
    if ( ! is_array( $terms ) || empty( $terms ) ) {
      return '';
    }

    $term = reset( $terms );
    if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
      return '';
    }

    $name = trim( (string) $term->name );
    if ( $name === '' || strcasecmp( $name, 'Istanbul' ) === 0 ) {
      return '';
    }

    return $name;
  }
}

if ( ! function_exists( 'pera_property_get_type_name' ) ) {
  function pera_property_get_type_name( int $post_id ): string {
    $terms = get_the_terms( $post_id, 'property_type' );
    if ( ! is_array( $terms ) || empty( $terms ) ) {
      return '';
    }

    $term = reset( $terms );
    if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
      return '';
    }

    return pera_property_normalize_whitespace( (string) $term->name );
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

    if ( $unit_key !== '' && ctype_digit( $unit_key ) ) {
      $beds = (int) $unit_key;

      foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) continue;

        $b = isset( $row['v2_bedrooms'] ) ? (int) $row['v2_bedrooms'] : 0;
        if ( $b === $beds ) {
          return $row;
        }
      }

      $row_index = $beds - 1;
      if ( $row_index >= 0 && isset( $rows[ $row_index ] ) && is_array( $rows[ $row_index ] ) ) {
        return $rows[ $row_index ];
      }
    }

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

if ( ! function_exists( 'pera_property_extract_size_text' ) ) {
  function pera_property_extract_size_text( array $row ): string {
    $size_min = 0.0;
    $size_max = 0.0;

    $size_keys = array(
      'size_min' => array( 'size_min', 'v2_gross_size_min', 'gross_size_min' ),
      'size_max' => array( 'size_max', 'v2_gross_size_max', 'gross_size_max' ),
    );

    foreach ( $size_keys['size_min'] as $key ) {
      if ( isset( $row[ $key ] ) && is_numeric( $row[ $key ] ) ) {
        $size_min = (float) $row[ $key ];
        break;
      }
    }

    foreach ( $size_keys['size_max'] as $key ) {
      if ( isset( $row[ $key ] ) && is_numeric( $row[ $key ] ) ) {
        $size_max = (float) $row[ $key ];
        break;
      }
    }

    if ( $size_min > 0 && $size_max <= 0 ) {
      $size_max = $size_min;
    }

    if ( $size_min <= 0 && $size_max > 0 ) {
      $size_min = $size_max;
    }

    if ( $size_min <= 0 ) {
      return '';
    }

    if ( function_exists( 'pera_v2_units_format_size_text' ) ) {
      return (string) pera_v2_units_format_size_text( $size_min, $size_max );
    }

    $fmt = function( float $size ): string {
      return number_format_i18n( (int) round( $size ) ) . ' m²';
    };

    if ( $size_max > 0 && (int) round( $size_max ) !== (int) round( $size_min ) ) {
      return $fmt( $size_min ) . '–' . $fmt( $size_max );
    }

    return $fmt( $size_min );
  }
}

if ( ! function_exists( 'pera_property_get_ready_label' ) ) {
  function pera_property_get_ready_label( int $post_id ): string {
    if ( ! function_exists( 'get_field' ) ) {
      return '';
    }

    $completion_raw = get_field( 'completion_date', $post_id );
    if ( empty( $completion_raw ) ) {
      return '';
    }

    $completion_raw = trim( (string) $completion_raw );
    $ready_date = null;

    if ( preg_match( '/^\d{8}$/', $completion_raw ) ) {
      $ready_date = DateTime::createFromFormat( 'Ymd', $completion_raw, wp_timezone() );
    } elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $completion_raw ) ) {
      $ready_date = DateTime::createFromFormat( 'Y-m-d', $completion_raw, wp_timezone() );
    } else {
      try {
        $ready_date = new DateTime( $completion_raw, wp_timezone() );
      } catch ( Exception $e ) {
        $ready_date = null;
      }
    }

    if ( ! ( $ready_date instanceof DateTime ) ) {
      return '';
    }

    $today = new DateTime( 'today', wp_timezone() );
    return ( $ready_date <= $today )
      ? 'Key-ready'
      : 'Ready on ' . $ready_date->format( 'm/y' );
  }
}

if ( ! function_exists( 'pera_property_get_selected_unit_context' ) ) {
  function pera_property_get_selected_unit_context( int $post_id ): array {
    static $cache = array();

    if ( isset( $cache[ $post_id ] ) ) {
      return $cache[ $post_id ];
    }

    $unit_key = isset( $_GET['unit_key'] ) ? absint( $_GET['unit_key'] ) : 0;

    if ( ! function_exists( 'pera_units_get_display_data' ) ) {
      $v2_helper_path = get_stylesheet_directory() . '/inc/v2-units-index.php';
      if ( file_exists( $v2_helper_path ) ) {
        require_once $v2_helper_path;
      }
    }

    $special_slugs = wp_get_post_terms( $post_id, 'special', array( 'fields' => 'slugs' ) );
    $special_slugs = is_array( $special_slugs ) ? $special_slugs : array();
    $is_project = in_array( 'project', $special_slugs, true );
    $is_resale = in_array( 'resales', $special_slugs, true );

    $context = array(
      'unit_key'      => $unit_key,
      'selected_unit' => null,
      'price_text'    => '',
      'size_text'     => '',
      'is_project'    => ( $is_project && ! $is_resale ),
    );

    if ( function_exists( 'pera_units_get_display_data' ) ) {
      $display = pera_units_get_display_data(
        $post_id,
        array(
          'context'    => 'single',
          'unit_key'   => $unit_key,
          'is_project' => $context['is_project'],
        )
      );

      if ( is_array( $display ) ) {
        $context['selected_unit'] = isset( $display['selected_unit'] ) && is_array( $display['selected_unit'] )
          ? $display['selected_unit']
          : null;
        $context['price_text'] = isset( $display['price_text'] ) ? pera_property_normalize_whitespace( (string) $display['price_text'] ) : '';
      }
    }

    if ( is_array( $context['selected_unit'] ) ) {
      $context['size_text'] = pera_property_extract_size_text( $context['selected_unit'] );
    }

    $cache[ $post_id ] = $context;
    return $cache[ $post_id ];
  }
}

if ( ! function_exists( 'pera_property_get_bedroom_label' ) ) {
  function pera_property_get_bedroom_label( int $post_id, ?array $selected_unit = null ): string {
    if ( is_array( $selected_unit ) ) {
      $bedrooms = pera_property_extract_bedrooms( $selected_unit );
      if ( $bedrooms && $bedrooms > 0 ) {
        return sprintf( '%d Bedroom', $bedrooms );
      }
    }

    $terms = get_the_terms( $post_id, 'bedrooms' );
    if ( ! is_array( $terms ) || empty( $terms ) ) {
      return '';
    }

    $term = reset( $terms );
    if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
      return '';
    }

    $label = pera_property_normalize_whitespace( (string) $term->name );
    if ( $label === '' ) {
      return '';
    }

    if ( preg_match( '/\d+/', $label, $matches ) ) {
      return sprintf( '%d Bedroom', (int) $matches[0] );
    }

    return $label;
  }
}

if ( ! function_exists( 'pera_property_get_location_label' ) ) {
  function pera_property_get_location_label( int $post_id ): string {
    $district = pera_property_get_district_name( $post_id );
    if ( $district !== '' ) {
      return $district . ', Istanbul';
    }

    $region = pera_property_get_region_name( $post_id );
    if ( $region !== '' ) {
      return $region . ', Istanbul';
    }

    return 'Istanbul';
  }
}

if ( ! function_exists( 'pera_property_build_seo_title' ) ) {
  function pera_property_build_seo_title( int $post_id ): string {
    $public_title = pera_property_get_public_title( $post_id );
    if ( $public_title === '' ) {
      $public_title = 'Property';
    }

    $title_lower = pera_property_mb_strtolower( $public_title );
    $district = pera_property_get_district_name( $post_id );
    $type = pera_property_get_type_name( $post_id );
    $unit_context = pera_property_get_selected_unit_context( $post_id );
    $selected_unit = is_array( $unit_context['selected_unit'] ) ? $unit_context['selected_unit'] : null;
    $bedroom_label = pera_property_get_bedroom_label( $post_id, $selected_unit );

    $parts = array( $public_title );

    $needs_type_prefix = $type !== ''
      && $bedroom_label !== ''
      && ! pera_property_string_contains( $title_lower, pera_property_mb_strtolower( $type ) )
      && ! pera_property_string_contains( $title_lower, pera_property_mb_strtolower( $bedroom_label ) );

    if ( $needs_type_prefix ) {
      array_unshift( $parts, trim( $bedroom_label . ' ' . $type ) );
      $public_title = implode( ' ', array_unique( array_filter( $parts ) ) );
      $parts = array( $public_title );
      $title_lower = pera_property_mb_strtolower( $public_title );
    }

    $location_phrase = '';
    if ( $district !== '' ) {
      $district_lower = pera_property_mb_strtolower( $district );
      if ( ! pera_property_string_contains( $title_lower, $district_lower ) || ! pera_property_string_contains( $title_lower, 'istanbul' ) ) {
        $location_phrase = 'for sale in ' . $district . ', Istanbul';
      }
    } elseif ( ! pera_property_string_contains( $title_lower, 'istanbul' ) ) {
      $location_phrase = 'for sale in Istanbul';
    }

    if ( $location_phrase !== '' ) {
      $parts[] = $location_phrase;
    }

    $seo_title = implode( ' ', array_filter( $parts ) );
    $seo_title = preg_replace( '/\s+/', ' ', (string) $seo_title );
    $seo_title = trim( (string) $seo_title );

    return $seo_title . ' | Pera Property';
  }
}

if ( ! function_exists('pera_property_get_meta_description') ) {
  function pera_property_get_meta_description( int $post_id ): string {
    $excerpt = pera_property_get_meta_excerpt( $post_id );
    if ( $excerpt !== '' ) {
      $word_count = str_word_count( wp_strip_all_tags( $excerpt ) );
      if ( $word_count >= 8 || ( function_exists( 'mb_strlen' ) ? mb_strlen( $excerpt, 'UTF-8' ) : strlen( $excerpt ) ) >= 70 ) {
        return pera_property_limit_text( $excerpt, 160 );
      }
    }

    $title = pera_property_get_public_title( $post_id );
    $type = pera_property_get_type_name( $post_id );
    $location = pera_property_get_location_label( $post_id );
    $unit_context = pera_property_get_selected_unit_context( $post_id );
    $selected_unit = is_array( $unit_context['selected_unit'] ) ? $unit_context['selected_unit'] : null;
    $bedroom_label = pera_property_get_bedroom_label( $post_id, $selected_unit );
    $size_text = isset( $unit_context['size_text'] ) ? (string) $unit_context['size_text'] : '';
    $price_text = isset( $unit_context['price_text'] ) ? (string) $unit_context['price_text'] : '';
    $ready_label = pera_property_get_ready_label( $post_id );

    $subject = 'this property';
    if ( $bedroom_label !== '' && $type !== '' ) {
      $subject = strtolower( $bedroom_label . ' ' . $type );
    } elseif ( $type !== '' ) {
      $subject = strtolower( $type );
    } elseif ( $title !== '' ) {
      $subject = $title;
    }

    $description = 'Explore ' . $subject . ' for sale in ' . $location;

    $details = array();
    if ( $size_text !== '' ) {
      $details[] = 'with ' . $size_text . ' of space';
    }
    if ( $price_text !== '' ) {
      $details[] = 'priced ' . $price_text;
    }
    if ( $ready_label !== '' ) {
      $details[] = strtolower( $ready_label );
    }

    if ( ! empty( $details ) ) {
      $description .= ' ' . implode( ', ', $details );
    }

    $description .= '.';

    return pera_property_limit_text( $description, 160 );
  }
}

if ( ! function_exists( 'pera_property_is_singular_context' ) ) {
  function pera_property_is_singular_context(): bool {
    return ! is_admin() && is_singular( 'property' );
  }
}

if ( ! function_exists( 'pera_property_has_unit_params' ) ) {
  function pera_property_has_unit_params(): bool {
    if ( ! pera_property_is_singular_context() ) {
      return false;
    }

    return isset( $_GET['unit_key'] ) && trim( (string) $_GET['unit_key'] ) !== '';
  }
}

if ( ! function_exists( 'pera_property_canonical_url' ) ) {
  function pera_property_canonical_url( int $post_id ): string {
    if ( $post_id < 1 ) {
      return '';
    }

    return (string) get_permalink( $post_id );
  }
}

add_filter( 'pre_get_document_title', function ( string $title ): string {
  if ( ! pera_property_is_singular_context() ) {
    return $title;
  }

  $post_id = (int) get_queried_object_id();
  if ( ! $post_id ) {
    return $title;
  }

  return pera_property_build_seo_title( $post_id );
}, 9 );

add_filter( 'get_canonical_url', function ( ?string $canonical, $post ): ?string {
  if ( ! pera_property_is_singular_context() ) {
    return $canonical;
  }

  $post_id = 0;
  if ( is_object( $post ) && isset( $post->ID ) ) {
    $post_id = (int) $post->ID;
  } elseif ( is_numeric( $post ) ) {
    $post_id = (int) $post;
  }

  if ( $post_id < 1 ) {
    $post_id = (int) get_queried_object_id();
  }

  if ( $post_id > 0 ) {
    return pera_property_canonical_url( $post_id );
  }

  return $canonical;
}, 10, 2 );

add_filter( 'wp_robots', function ( array $robots ): array {
  if ( pera_property_has_unit_params() ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  return $robots;
} );

add_action('wp_head', function () {
  if ( ! pera_property_is_singular_context() ) return;

  $post_id = (int) get_queried_object_id();
  if ( ! $post_id ) return;

  $title = pera_property_build_seo_title( $post_id );
  $url   = pera_property_canonical_url( $post_id );

  $desc    = pera_property_get_meta_description($post_id);
  $img     = pera_property_get_social_image($post_id);
  $img_url = $img['url'];
  $img_alt = $img['alt'] ?: pera_property_get_public_title( $post_id );

  echo "\n<!-- Pera: Single Property SEO / Social -->\n";

  if ( $desc !== '' ) {
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
  }

  echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo('name') ) . '">' . "\n";
  echo '<meta property="og:type" content="website">' . "\n";
  echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
  echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";

  if ( $desc !== '' ) {
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
  }
  if ( $img_url ) {
    echo '<meta property="og:image" content="' . esc_url($img_url) . '">' . "\n";
    echo '<meta property="og:image:alt" content="' . esc_attr($img_alt) . '">' . "\n";
  }

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
