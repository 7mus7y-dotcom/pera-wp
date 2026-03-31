<?php
/**
 * SEO helper functions used by templates.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'pera_get_property_archive_taxonomies' ) ) {
  /**
   * Canonical property archive taxonomy list shared by loader + SEO modules.
   *
   * Centralising this list keeps ownership/routing coherent across:
   * - module loading
   * - context detection
   * - canonical + robots handling
   */
  function pera_get_property_archive_taxonomies( bool $existing_only = true ): array {
    $taxonomies = array(
      'district',
      'region',
      'property_type',
      'property_tags',
      'bedrooms',
      'special',
    );

    if ( ! $existing_only ) {
      return $taxonomies;
    }

    return array_values( array_filter( $taxonomies, 'taxonomy_exists' ) );
  }
}

if ( ! function_exists( 'pera_is_property_archive_context' ) ) {
  /**
   * True when current request is owned by the property archive SEO module.
   */
  function pera_is_property_archive_context(): bool {
    $taxonomies = pera_get_property_archive_taxonomies();
    return is_post_type_archive( 'property' )
      || ( ! empty( $taxonomies ) && is_tax( $taxonomies ) );
  }
}

if ( ! function_exists( 'pera_get_district_archive_location_name' ) ) {
  function pera_get_district_archive_location_name( WP_Term $term ): string {
    $name = trim( (string) $term->name );

    if ( $name === '' ) {
      return 'Istanbul';
    }

    $normalized = function_exists( 'mb_strtolower' )
      ? mb_strtolower( $name, 'UTF-8' )
      : strtolower( $name );

    if ( in_array( $normalized, array( 'istanbul', 'i̇stanbul' ), true ) ) {
      return 'Istanbul';
    }

    return $name . ', Istanbul';
  }
}

if ( ! function_exists( 'pera_get_district_archive_heading' ) ) {
  function pera_get_district_archive_heading( WP_Term $term ): string {
    $location = pera_get_district_archive_location_name( $term );
    return sprintf( 'Property for sale in %s', $location );
  }
}

if ( ! function_exists( 'pera_get_district_archive_title' ) ) {
  function pera_get_district_archive_title( WP_Term $term ): string {
    $location = pera_get_district_archive_location_name( $term );
    return sprintf( 'Property for sale in %s | Pera Property', $location );
  }
}

if ( ! function_exists( 'pera_get_region_archive_location_name' ) ) {
  function pera_get_region_archive_location_name( WP_Term $term ): string {
    $name = trim( (string) $term->name );

    if ( $name === '' ) {
      return 'Istanbul';
    }

    $normalized = function_exists( 'mb_strtolower' )
      ? mb_strtolower( $name, 'UTF-8' )
      : strtolower( $name );

    if ( in_array( $normalized, array( 'istanbul', 'i̇stanbul' ), true ) ) {
      return 'Istanbul';
    }

    return $name . ', Istanbul';
  }
}

if ( ! function_exists( 'pera_get_region_archive_heading' ) ) {
  function pera_get_region_archive_heading( WP_Term $term ): string {
    $location = pera_get_region_archive_location_name( $term );
    return sprintf( 'Property for sale in %s', $location );
  }
}

if ( ! function_exists( 'pera_get_region_archive_title' ) ) {
  function pera_get_region_archive_title( WP_Term $term ): string {
    $location = pera_get_region_archive_location_name( $term );
    return sprintf( 'Property for sale in %s | Pera Property', $location );
  }
}

if ( ! function_exists( 'pera_get_property_tags_archive_heading' ) ) {
  function pera_get_property_tags_archive_heading( WP_Term $term ): string {
    $tag = trim( (string) $term->name );

    if ( $tag === '' ) {
      return 'Property for sale in Istanbul';
    }

    return sprintf( 'Property for sale in Istanbul - %s', $tag );
  }
}

if ( ! function_exists( 'pera_get_property_tags_archive_title' ) ) {
  function pera_get_property_tags_archive_title( WP_Term $term ): string {
    $tag = trim( (string) $term->name );

    if ( $tag === '' ) {
      return 'Property for sale in Istanbul | Pera Property';
    }

    return sprintf( 'Property for sale in Istanbul - %s | Pera Property', $tag );
  }
}

if ( ! function_exists( 'pera_seo_normalize_meta_text' ) ) {
  function pera_seo_normalize_meta_text( string $value ): string {
    $value = wp_strip_all_tags( $value );
    $value = preg_replace( '/\s+/u', ' ', $value );
    return trim( (string) $value );
  }
}

if ( ! function_exists( 'pera_get_property_archive_term_acf_field' ) ) {
  /**
   * Resolve an ACF term field safely across common ACF term reference formats.
   */
  function pera_get_property_archive_term_acf_field( WP_Term $term, string $field_name ): string {
    if ( ! function_exists( 'get_field' ) ) {
      return '';
    }

    $field_name = trim( $field_name );
    if ( $field_name === '' ) {
      return '';
    }

    $candidates = array(
      $term,
      $term->taxonomy . '_' . (int) $term->term_id,
      'term_' . (int) $term->term_id,
      (int) $term->term_id,
    );

    foreach ( $candidates as $candidate ) {
      $value = get_field( $field_name, $candidate );
      if ( is_string( $value ) ) {
        $value = pera_seo_normalize_meta_text( $value );
        if ( $value !== '' ) {
          return $value;
        }
      }
    }

    return '';
  }
}

if ( ! function_exists( 'pera_get_property_archive_term_manual_seo_title' ) ) {
  function pera_get_property_archive_term_manual_seo_title( WP_Term $term ): string {
    $manual = pera_get_property_archive_term_acf_field( $term, 'seo_title' );

    if ( $manual === '' ) {
      $manual = pera_seo_normalize_meta_text( (string) get_term_meta( $term->term_id, 'seo_title', true ) );
    }

    return $manual;
  }
}

if ( ! function_exists( 'pera_get_property_archive_term_manual_meta_description' ) ) {
  function pera_get_property_archive_term_manual_meta_description( WP_Term $term ): string {
    $manual = pera_get_property_archive_term_acf_field( $term, 'seo_meta_description' );

    if ( $manual === '' ) {
      $manual = pera_seo_normalize_meta_text( (string) get_term_meta( $term->term_id, 'seo_meta_description', true ) );
    }

    return $manual;
  }
}

if ( ! function_exists( 'pera_get_property_archive_term_excerpt_fallback' ) ) {
  /**
   * Existing term-description fallback chain for taxonomy archive meta descriptions.
   */
  function pera_get_property_archive_term_excerpt_fallback( WP_Term $term ): string {
    $value = (string) get_term_meta( $term->term_id, 'term_excerpt', true );
    if ( $value === '' ) $value = (string) get_term_meta( $term->term_id, 'excerpt', true );
    if ( $value === '' ) $value = (string) get_term_meta( $term->term_id, 'pera_term_excerpt', true );
    if ( $value === '' ) $value = (string) term_description( $term->term_id, $term->taxonomy );

    return pera_seo_normalize_meta_text( $value );
  }
}

if ( ! function_exists( 'pera_get_property_archive_generated_title' ) ) {
  function pera_get_property_archive_generated_title( WP_Term $term ): string {
    $name = pera_seo_normalize_meta_text( (string) $term->name );
    $taxonomy = (string) $term->taxonomy;

    if ( $taxonomy === 'district' ) {
      return pera_get_district_archive_title( $term );
    }

    if ( $taxonomy === 'region' ) {
      return pera_get_region_archive_title( $term );
    }

    if ( $taxonomy === 'property_tags' ) {
      return pera_get_property_tags_archive_title( $term );
    }

    if ( $taxonomy === 'property_type' ) {
      return $name !== ''
        ? sprintf( '%s property for sale in Istanbul | Pera Property', $name )
        : 'Property for sale in Istanbul | Pera Property';
    }

    if ( $taxonomy === 'bedrooms' ) {
      if ( $name !== '' ) {
        if ( preg_match( '/\d+/', $name, $matches ) ) {
          return sprintf( '%s bedroom property for sale in Istanbul | Pera Property', $matches[0] );
        }

        return sprintf( '%s bedroom property for sale in Istanbul | Pera Property', $name );
      }

      return 'Property for sale in Istanbul | Pera Property';
    }

    if ( $taxonomy === 'special' ) {
      return $name !== ''
        ? sprintf( '%s property for sale in Istanbul | Pera Property', $name )
        : 'Property for sale in Istanbul | Pera Property';
    }

    return '';
  }
}

if ( ! function_exists( 'pera_get_property_archive_generated_description' ) ) {
  function pera_get_property_archive_generated_description(): string {
    return 'Discover property for sale in Istanbul, from luxury apartments to investment opportunities, with guidance from local experts.';
  }
}

if ( ! function_exists( 'pera_property_archive_is_filtered_request' ) ) {
  function pera_property_archive_is_filtered_request( ?array $query = null ): bool {
    $query = $query ?? $_GET;

    if ( ! pera_is_property_archive_context() ) {
      return false;
    }

    $filter_keys = array(
      's',
      'property_type',
      'district',
      'min_price',
      'max_price',
      'property_tags',
      'v2_beds',
      'bedrooms',
      'sort',
      'region',
      'special',
    );

    foreach ( $filter_keys as $key ) {
      if ( ! isset( $query[ $key ] ) ) {
        continue;
      }

      $value = $query[ $key ];

      if ( is_array( $value ) ) {
        foreach ( $value as $item ) {
          if ( trim( (string) $item ) !== '' ) {
            return true;
          }
        }
      } else {
        if ( trim( (string) $value ) !== '' ) {
          return true;
        }
      }
    }

    return false;
  }
}

if ( ! function_exists( 'pera_property_archive_get_paged' ) ) {
  function pera_property_archive_get_paged(): int {
    $paged = (int) get_query_var( 'paged' );

    if ( $paged < 1 ) {
      $paged = (int) get_query_var( 'page' );
    }

    if ( $paged < 1 && isset( $_GET['paged'] ) ) {
      $paged = absint( $_GET['paged'] );
    }

    if ( $paged < 1 && isset( $_SERVER['REQUEST_URI'] ) ) {
      if ( preg_match( '~/page/(\d+)/?~', (string) $_SERVER['REQUEST_URI'], $m ) ) {
        $paged = (int) $m[1];
      }
    }

    return max( 1, $paged );
  }
}

if ( ! function_exists( 'pera_property_archive_canonical_url' ) ) {
  function pera_property_archive_canonical_url(): string {
    if ( ! pera_is_property_archive_context() ) {
      return '';
    }

    if ( function_exists( 'pera_property_archive_base_url' ) ) {
      $base = pera_property_archive_base_url();
    } else {
      if ( is_tax() ) {
        $qo = get_queried_object();
        $base = ( $qo instanceof WP_Term && ! is_wp_error( $qo ) ) ? get_term_link( $qo ) : '';
      } else {
        $base = get_post_type_archive_link( 'property' );
      }
    }

    if ( ! $base || is_wp_error( $base ) ) {
      return '';
    }

    $base = trailingslashit( (string) $base );
    $paged = pera_property_archive_get_paged();

    if ( $paged > 1 ) {
      return trailingslashit( $base . 'page/' . $paged );
    }

    return $base;
  }
}
