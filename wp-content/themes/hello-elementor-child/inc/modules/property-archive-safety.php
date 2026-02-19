<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Hotfix: prevent taxonomy query vars from arriving as arrays in WP core.
 *
 * Multi-select filters may submit district[] / property_tags[] in the URL.
 * Those names match taxonomy query vars, and WP core expects scalar strings
 * while parsing the main query. Unsetting array values here prevents fatals
 * without affecting template/AJAX filtering that reads from $_GET.
 */
add_filter( 'request', function ( $vars ) {
  // Strip any array-valued taxonomy query-vars for the "property" object type.
  // Prevents WP core parse_tax_query() from fatalling when it encounters array-shaped vars.
  foreach ( $vars as $k => $v ) {
    if ( ! is_array( $v ) ) {
      continue;
    }
    if ( taxonomy_exists( $k ) && is_object_in_taxonomy( 'property', $k ) ) {
      unset( $vars[ $k ] );
    }
  }

  return $vars;
}, 1 );

/**
 * Canonicalize legacy array taxonomy params on the property archive URL.
 *
 * This runs in functions.php (template_redirect, priority 1) so it executes
 * before template output and also on HEAD requests.
 */
add_action( 'template_redirect', function () {
  if ( is_admin() ) {
    return;
  }

  $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
  $request_path = wp_parse_url( $request_uri, PHP_URL_PATH );

  $property_path = wp_parse_url( home_url( '/property/' ), PHP_URL_PATH );
  $is_exact_property_path = is_string( $request_path )
    && is_string( $property_path )
    && trailingslashit( $request_path ) === trailingslashit( $property_path );

  $is_property_archive_page = is_page_template( 'archive-property.php' ) || $is_exact_property_path;

  if ( ! $is_property_archive_page || empty( $_GET ) ) {
    return;
  }

  $taxonomy_keys = get_object_taxonomies( 'property', 'names' );
  $taxonomy_keys = is_array( $taxonomy_keys ) ? $taxonomy_keys : array();
  $taxonomy_keys = array_values( array_unique( array_merge( array( 'district', 'property_tags' ), $taxonomy_keys ) ) );

  $canonical_query = $_GET;
  $needs_redirect  = false;

  foreach ( $taxonomy_keys as $taxonomy_key ) {
    if ( ! isset( $_GET[ $taxonomy_key ] ) || ! is_array( $_GET[ $taxonomy_key ] ) ) {
      continue;
    }

    $normalized_values = array();

    foreach ( $_GET[ $taxonomy_key ] as $raw_value ) {
      $value = sanitize_title( wp_unslash( (string) $raw_value ) );

      if ( $value === '' || in_array( $value, $normalized_values, true ) ) {
        continue;
      }

      $normalized_values[] = $value;
    }

    if ( ! empty( $normalized_values ) ) {
      $canonical_query[ $taxonomy_key ] = implode( ',', $normalized_values );
    } else {
      unset( $canonical_query[ $taxonomy_key ] );
    }

    $needs_redirect = true;
  }

  if ( ! $needs_redirect ) {
    return;
  }

  $base_url      = trailingslashit( home_url( '/property/' ) );
  $canonical_url = add_query_arg( $canonical_query, $base_url );

  wp_safe_redirect( $canonical_url, 301 );
  exit;
}, 1 );
