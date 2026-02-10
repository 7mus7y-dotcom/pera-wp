<?php
/**
 * Ensure assigned district terms always include ancestors for properties.
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

add_action( 'set_object_terms', 'pera_enforce_district_ancestors', 10, 6 );

/**
 * Enforce district ancestors for property assignments.
 *
 * @param int    $object_id  Object ID.
 * @param array  $terms      Term IDs or slugs.
 * @param array  $tt_ids     Term taxonomy IDs.
 * @param string $taxonomy   Taxonomy name.
 * @param bool   $append     Whether terms are appended to existing terms.
 * @param array  $old_tt_ids Old term taxonomy IDs.
 */
function pera_enforce_district_ancestors( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
  if ( $taxonomy !== 'district' ) {
    return;
  }

  if ( get_post_type( $object_id ) !== 'property' ) {
    return;
  }

  static $running = array();
  if ( ! empty( $running[ $object_id ] ) ) {
    return;
  }
  $running[ $object_id ] = true;

  $current = wp_get_object_terms( $object_id, 'district', array( 'fields' => 'ids' ) );
  if ( is_wp_error( $current ) ) {
    unset( $running[ $object_id ] );
    return;
  }

  $needed = array();
  foreach ( $current as $term_id ) {
    $term_id = (int) $term_id;
    if ( $term_id <= 0 ) {
      continue;
    }

    $needed[] = $term_id;
    $ancestors = get_ancestors( $term_id, 'district', 'taxonomy' );
    if ( ! empty( $ancestors ) ) {
      $needed = array_merge( $needed, $ancestors );
    }
  }

  $needed = array_values( array_filter( array_unique( array_map( 'intval', $needed ) ) ) );

  if ( count( $needed ) !== count( $current ) ) {
    wp_set_object_terms( $object_id, $needed, 'district', false );
  }

  unset( $running[ $object_id ] );
}

/**
 * Get the deepest assigned term for a post within a taxonomy.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy name.
 * @return WP_Term|null
 */
function pera_get_deepest_term( int $post_id, string $taxonomy ): ?WP_Term {
  $terms = wp_get_post_terms( $post_id, $taxonomy );
  if ( empty( $terms ) || is_wp_error( $terms ) ) {
    return null;
  }

  $pick_term  = null;
  $best_depth = -1;

  foreach ( $terms as $term ) {
    if ( ! $term || empty( $term->term_id ) ) {
      continue;
    }

    $depth     = 0;
    $parent_id = (int) $term->parent;

    while ( $parent_id > 0 ) {
      $parent_term = get_term( $parent_id, $taxonomy );
      if ( is_wp_error( $parent_term ) || ! $parent_term ) {
        break;
      }

      $depth++;
      if ( $depth > 10 ) {
        break;
      }
      $parent_id = (int) $parent_term->parent;
    }

    if ( $depth > $best_depth ) {
      $best_depth = $depth;
      $pick_term  = $term;
    }
  }

  if ( $pick_term ) {
    return $pick_term;
  }

  $fallback = reset( $terms );
  return $fallback ? $fallback : null;
}
