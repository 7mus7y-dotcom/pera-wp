<?php
/**
 * SEO helper functions used by templates.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

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

if ( ! function_exists( 'pera_property_archive_is_filtered_request' ) ) {
  function pera_property_archive_is_filtered_request( ?array $query = null ): bool {
    $query = $query ?? $_GET;

    $is_property_context = is_post_type_archive( 'property' )
      || is_tax( array( 'region', 'district', 'property_type', 'bedrooms', 'property_tags' ) );

    if ( ! $is_property_context ) {
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
    $taxes = array( 'region', 'district', 'property_type', 'bedrooms', 'property_tags' );
    $taxes = array_values( array_filter( $taxes, 'taxonomy_exists' ) );

    $is_property_context = is_post_type_archive( 'property' )
      || ( ! empty( $taxes ) && is_tax( $taxes ) );

    if ( ! $is_property_context ) {
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
