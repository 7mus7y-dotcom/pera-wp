<?php
/**
 * Property archive pagination helper.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! function_exists( 'pera_render_property_pagination' ) ) {
  /**
   * Render pagination HTML for property archives.
   *
   * @param WP_Query $query
   * @param int      $paged
   * @param array    $add_args
   * @param string   $base_url Optional. Full base URL or path.
   * @return string Pagination HTML or empty string.
   */
  function pera_render_property_pagination( WP_Query $query, int $paged, array $add_args = array(), string $base_url = '' ): string {
    $total_pages = (int) $query->max_num_pages;
    if ( $total_pages <= 1 ) {
      return '';
    }

    $paged = max( 1, $paged );

    if ( isset( $add_args['paged'] ) ) {
      unset( $add_args['paged'] );
    }

    $base = $base_url !== '' ? $base_url : get_pagenum_link( 1 );

    if ( $base_url !== '' && ! preg_match( '#^https?://#i', $base ) ) {
      $base = home_url( trailingslashit( ltrim( $base, '/' ) ) );
    }

    $format = '';

    if ( get_option( 'permalink_structure' ) ) {
      $base   = trailingslashit( $base ) . '%_%';
      $format = 'page/%#%/';
    } else {
      $base = add_query_arg( 'paged', '%#%', $base );
    }

    return (string) paginate_links( array(
      'total'     => $total_pages,
      'current'   => $paged,
      'mid_size'  => 1,
      'end_size'  => 1,
      'prev_text' => 'Prev',
      'next_text' => 'Next',
      'type'      => 'list',
      'base'      => $base,
      'format'    => $format,
      'add_args'  => $add_args,
    ) );
  }
}
