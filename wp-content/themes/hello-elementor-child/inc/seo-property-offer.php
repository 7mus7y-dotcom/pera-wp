<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Single Property Product / Offer schema.
 *
 * Adds commercial intent only when a real numeric USD price can be resolved
 * from public V2 unit data. Price-on-request listings intentionally output no
 * Offer schema rather than fabricating commerce values.
 */

if ( ! function_exists( 'pera_property_offer_schema_numeric_price' ) ) {
  function pera_property_offer_schema_numeric_price( $value ): int {
    if ( is_int( $value ) || is_float( $value ) ) {
      return max( 0, (int) round( (float) $value ) );
    }

    if ( ! is_string( $value ) ) {
      return 0;
    }

    $value = trim( $value );
    if ( $value === '' ) {
      return 0;
    }

    $normalized = preg_replace( '/[^0-9.]/', '', $value );
    if ( ! is_string( $normalized ) || $normalized === '' || ! is_numeric( $normalized ) ) {
      return 0;
    }

    return max( 0, (int) round( (float) $normalized ) );
  }
}

if ( ! function_exists( 'pera_property_offer_schema_extract_unit_price' ) ) {
  function pera_property_offer_schema_extract_unit_price( ?array $unit ): int {
    if ( ! is_array( $unit ) ) {
      return 0;
    }

    $candidate_keys = array(
      'price_min',
      'v2_price_usd_min',
      'v2_price_min',
      'price',
      'property_price',
      'sale_price',
    );

    foreach ( $candidate_keys as $key ) {
      if ( array_key_exists( $key, $unit ) ) {
        $price = pera_property_offer_schema_numeric_price( $unit[ $key ] );
        if ( $price > 0 ) {
          return $price;
        }
      }
    }

    return 0;
  }
}

if ( ! function_exists( 'pera_property_offer_schema_get_price' ) ) {
  function pera_property_offer_schema_get_price( int $post_id ): int {
    if ( $post_id <= 0 ) {
      return 0;
    }

    if ( function_exists( 'pera_property_get_selected_unit_context' ) ) {
      $unit_context = pera_property_get_selected_unit_context( $post_id );
      $selected_unit = isset( $unit_context['selected_unit'] ) && is_array( $unit_context['selected_unit'] )
        ? $unit_context['selected_unit']
        : null;

      $selected_price = pera_property_offer_schema_extract_unit_price( $selected_unit );
      if ( $selected_price > 0 ) {
        return $selected_price;
      }
    }

    $prices = array();

    if ( function_exists( 'pera_property_get_v2_units' ) ) {
      foreach ( pera_property_get_v2_units( $post_id ) as $unit ) {
        if ( ! is_array( $unit ) ) {
          continue;
        }

        $price = pera_property_offer_schema_extract_unit_price( $unit );
        if ( $price > 0 ) {
          $prices[] = $price;
        }
      }
    }

    if ( ! empty( $prices ) ) {
      return min( $prices );
    }

    if ( function_exists( 'get_field' ) ) {
      foreach ( array( 'price', 'property_price', 'sale_price', 'price_usd' ) as $field_name ) {
        $price = pera_property_offer_schema_numeric_price( get_field( $field_name, $post_id ) );
        if ( $price > 0 ) {
          return $price;
        }
      }
    }

    return 0;
  }
}

if ( ! function_exists( 'pera_property_offer_schema_print' ) ) {
  function pera_property_offer_schema_print(): void {
    if ( ! is_singular( 'property' ) ) {
      return;
    }

    $post_id = (int) get_queried_object_id();
    if ( $post_id <= 0 ) {
      return;
    }

    $price = pera_property_offer_schema_get_price( $post_id );
    if ( $price <= 0 ) {
      return;
    }

    $url = function_exists( 'pera_property_canonical_url' )
      ? pera_property_canonical_url( $post_id )
      : get_permalink( $post_id );

    if ( ! is_string( $url ) || $url === '' ) {
      return;
    }

    $name = function_exists( 'pera_property_get_public_title' )
      ? pera_property_get_public_title( $post_id )
      : wp_strip_all_tags( get_the_title( $post_id ) );

    if ( $name === '' ) {
      return;
    }

    $description = function_exists( 'pera_property_get_meta_description' )
      ? pera_property_get_meta_description( $post_id )
      : '';

    $images = function_exists( 'pera_property_get_schema_images' )
      ? pera_property_get_schema_images( $post_id )
      : array();

    $schema = array(
      '@context' => 'https://schema.org',
      '@type'    => 'Product',
      '@id'      => esc_url_raw( $url ) . '#product',
      'name'     => $name,
      'url'      => esc_url_raw( $url ),
      'offers'   => array(
        '@type'         => 'Offer',
        '@id'           => esc_url_raw( $url ) . '#offer',
        'url'           => esc_url_raw( $url ),
        'price'         => (string) $price,
        'priceCurrency' => 'USD',
        'availability'  => 'https://schema.org/InStock',
        'itemCondition' => 'https://schema.org/NewCondition',
      ),
    );

    if ( $description !== '' ) {
      $schema['description'] = $description;
    }

    if ( ! empty( $images ) ) {
      $schema['image'] = array_values( array_map( 'esc_url_raw', $images ) );
    }

    echo "\n" . '<script type="application/ld+json" class="pera-property-offer-schema">' . "\n";
    echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    echo "\n" . '</script>' . "\n";
  }

  add_action( 'wp_head', 'pera_property_offer_schema_print', 21 );
}
