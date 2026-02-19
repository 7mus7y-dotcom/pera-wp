<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =======================================================
   GLOBAL: INLINE SVG SPRITE (icons.svg)
   ======================================================= */
add_action( 'wp_footer', function () {
  $sprite_path = get_stylesheet_directory() . '/logos-icons/icons.svg';

  if ( ! file_exists( $sprite_path ) ) {
    return;
  }

  $svg = file_get_contents( $sprite_path );
  if ( ! $svg ) {
    return;
  }

  $svg = preg_replace( '/<\?xml[^>]*\?>/i', '', $svg );

  if ( preg_match( '/<svg\b[^>]*>/i', $svg, $match ) ) {
    $svg_tag = $match[0];
    $new_tag = $svg_tag;

    if ( stripos( $new_tag, 'style=' ) !== false ) {
      $new_tag = preg_replace(
        '/style=(["\'])(.*?)\1/i',
        'style=$1$2;position:absolute;width:0;height:0;overflow:hidden$1',
        $new_tag,
        1
      );
    } else {
      $new_tag = rtrim( substr( $new_tag, 0, -1 ) ) . ' style="position:absolute;width:0;height:0;overflow:hidden">';
    }

    if ( stripos( $new_tag, 'aria-hidden=' ) === false ) {
      $new_tag = rtrim( substr( $new_tag, 0, -1 ) ) . ' aria-hidden="true">';
    }

    if ( stripos( $new_tag, 'focusable=' ) === false ) {
      $new_tag = rtrim( substr( $new_tag, 0, -1 ) ) . ' focusable="false">';
    }

    $svg = preg_replace( '/<svg\b[^>]*>/i', $new_tag, $svg, 1 );
  }

  echo $svg;
}, 20 );
