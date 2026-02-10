<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

const PERA_FAVOURITES_META_KEY = 'pera_favourites';

/**
 * Get favourites for a user.
 *
 * @param int $user_id
 * @return int[]
 */
function pera_get_user_favourites( int $user_id ): array {
  $favs = get_user_meta( $user_id, PERA_FAVOURITES_META_KEY, true );
  if ( ! is_array( $favs ) ) {
    $favs = array();
  }

  $favs = array_map( 'absint', $favs );
  $favs = array_filter( $favs );
  $favs = array_values( array_unique( $favs ) );

  return $favs;
}

/**
 * Persist favourites for a user.
 *
 * @param int   $user_id
 * @param int[] $favs
 */
function pera_save_user_favourites( int $user_id, array $favs ): void {
  $favs = array_map( 'absint', $favs );
  $favs = array_filter( $favs );
  $favs = array_values( array_unique( $favs ) );

  update_user_meta( $user_id, PERA_FAVOURITES_META_KEY, $favs );
}

/**
 * Validate a property post ID.
 *
 * @param int $post_id
 * @return bool
 */
function pera_is_valid_property_post( int $post_id ): bool {
  if ( $post_id <= 0 ) {
    return false;
  }

  if ( get_post_type( $post_id ) !== 'property' ) {
    return false;
  }

  $status = get_post_status( $post_id );
  if ( ! $status ) {
    return false;
  }

  if ( $status !== 'publish' && ! current_user_can( 'read_post', $post_id ) ) {
    return false;
  }

  return true;
}

/**
 * Render property cards for favourites.
 *
 * @param int[] $ids
 * @return array{html:string,count:int}
 */
function pera_render_favourite_cards( array $ids ): array {
  $ids = array_map( 'absint', $ids );
  $ids = array_filter( $ids );
  $ids = array_values( array_unique( $ids ) );

  if ( empty( $ids ) ) {
    return array(
      'html'  => '',
      'count' => 0,
    );
  }

  $query = new WP_Query(
    array(
      'post_type'      => 'property',
      'post_status'    => 'publish',
      'post__in'       => $ids,
      'orderby'        => 'post__in',
      'posts_per_page' => min( 48, count( $ids ) ),
    )
  );

  ob_start();

  if ( $query->have_posts() ) {
    while ( $query->have_posts() ) {
      $query->the_post();

      pera_render_property_card( array(
        'variant' => 'archive',
      ) );
    }
  }

  $html = ob_get_clean();
  wp_reset_postdata();

  return array(
    'html'  => $html,
    'count' => (int) $query->post_count,
  );
}

add_action( 'wp_ajax_pera_get_favourites', function () {
  check_ajax_referer( 'pera_favourites', 'nonce' );

  if ( ! is_user_logged_in() ) {
    wp_send_json_error( array( 'message' => 'Not logged in.' ), 401 );
  }

  $user_id = get_current_user_id();
  $favs = pera_get_user_favourites( $user_id );

  wp_send_json_success( array( 'favourites' => $favs ) );
} );

add_action( 'wp_ajax_pera_render_favourites', function () {
  check_ajax_referer( 'pera_favourites', 'nonce' );

  $raw_ids = isset( $_POST['ids'] ) ? $_POST['ids'] : array();

  if ( is_string( $raw_ids ) ) {
    $decoded = json_decode( $raw_ids, true );
    if ( is_array( $decoded ) ) {
      $raw_ids = $decoded;
    } else {
      $raw_ids = preg_split( '/[\\s,]+/', $raw_ids );
    }
  }

  if ( ! is_array( $raw_ids ) ) {
    $raw_ids = array();
  }

  $raw_ids = array_slice( $raw_ids, 0, 100 );
  $ids = array_map( 'absint', $raw_ids );

  $rendered = pera_render_favourite_cards( $ids );

  wp_send_json_success( $rendered );
} );

add_action( 'wp_ajax_nopriv_pera_render_favourites', function () {
  check_ajax_referer( 'pera_favourites', 'nonce' );

  $raw_ids = isset( $_POST['ids'] ) ? $_POST['ids'] : array();

  if ( is_string( $raw_ids ) ) {
    $decoded = json_decode( $raw_ids, true );
    if ( is_array( $decoded ) ) {
      $raw_ids = $decoded;
    } else {
      $raw_ids = preg_split( '/[\\s,]+/', $raw_ids );
    }
  }

  if ( ! is_array( $raw_ids ) ) {
    $raw_ids = array();
  }

  $raw_ids = array_slice( $raw_ids, 0, 100 );
  $ids = array_map( 'absint', $raw_ids );

  $rendered = pera_render_favourite_cards( $ids );

  wp_send_json_success( $rendered );
} );

add_action( 'wp_ajax_pera_toggle_favourite', function () {
  check_ajax_referer( 'pera_favourites', 'nonce' );

  if ( ! is_user_logged_in() ) {
    wp_send_json_error( array( 'message' => 'Not logged in.' ), 401 );
  }

  $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
  $action  = isset( $_POST['fav_action'] ) ? sanitize_key( $_POST['fav_action'] ) : 'toggle';

  if ( ! pera_is_valid_property_post( $post_id ) ) {
    wp_send_json_error( array( 'message' => 'Invalid property.' ), 400 );
  }

  $user_id = get_current_user_id();
  $favs = pera_get_user_favourites( $user_id );

  $has_fav = in_array( $post_id, $favs, true );

  if ( $action === 'add' ) {
    if ( ! $has_fav ) {
      $favs[] = $post_id;
    }
  } elseif ( $action === 'remove' ) {
    if ( $has_fav ) {
      $favs = array_values( array_diff( $favs, array( $post_id ) ) );
    }
  } else {
    if ( $has_fav ) {
      $favs = array_values( array_diff( $favs, array( $post_id ) ) );
    } else {
      $favs[] = $post_id;
    }
  }

  pera_save_user_favourites( $user_id, $favs );

  wp_send_json_success( array( 'favourites' => $favs ) );
} );

add_action( 'wp_ajax_pera_favourites_titles', function () {
  check_ajax_referer( 'pera_favourites', 'nonce' );

  $raw_ids = isset( $_POST['ids'] ) ? $_POST['ids'] : array();

  if ( is_string( $raw_ids ) ) {
    $decoded = json_decode( $raw_ids, true );
    if ( is_array( $decoded ) ) {
      $raw_ids = $decoded;
    } else {
      $raw_ids = preg_split( '/[\\s,]+/', $raw_ids );
    }
  }

  if ( ! is_array( $raw_ids ) ) {
    $raw_ids = array();
  }

  $raw_ids = array_slice( $raw_ids, 0, 50 );
  $ids = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );

  if ( empty( $ids ) ) {
    wp_send_json_success( array( 'items' => array() ) );
  }

  $query = new WP_Query(
    array(
      'post_type'      => 'property',
      'post_status'    => 'publish',
      'post__in'       => $ids,
      'orderby'        => 'post__in',
      'posts_per_page' => min( 50, count( $ids ) ),
    )
  );

  $items = array();

  if ( $query->have_posts() ) {
    while ( $query->have_posts() ) {
      $query->the_post();
      $items[] = array(
        'id'    => get_the_ID(),
        'title' => get_the_title(),
        'url'   => get_permalink(),
      );
    }
  }

  wp_reset_postdata();

  wp_send_json_success( array( 'items' => $items ) );
} );

add_action( 'wp_ajax_nopriv_pera_favourites_titles', function () {
  check_ajax_referer( 'pera_favourites', 'nonce' );

  $raw_ids = isset( $_POST['ids'] ) ? $_POST['ids'] : array();

  if ( is_string( $raw_ids ) ) {
    $decoded = json_decode( $raw_ids, true );
    if ( is_array( $decoded ) ) {
      $raw_ids = $decoded;
    } else {
      $raw_ids = preg_split( '/[\\s,]+/', $raw_ids );
    }
  }

  if ( ! is_array( $raw_ids ) ) {
    $raw_ids = array();
  }

  $raw_ids = array_slice( $raw_ids, 0, 50 );
  $ids = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );

  if ( empty( $ids ) ) {
    wp_send_json_success( array( 'items' => array() ) );
  }

  $query = new WP_Query(
    array(
      'post_type'      => 'property',
      'post_status'    => 'publish',
      'post__in'       => $ids,
      'orderby'        => 'post__in',
      'posts_per_page' => min( 50, count( $ids ) ),
    )
  );

  $items = array();

  if ( $query->have_posts() ) {
    while ( $query->have_posts() ) {
      $query->the_post();
      $items[] = array(
        'id'    => get_the_ID(),
        'title' => get_the_title(),
        'url'   => get_permalink(),
      );
    }
  }

  wp_reset_postdata();

  wp_send_json_success( array( 'items' => $items ) );
} );
