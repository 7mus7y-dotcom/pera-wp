<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Dequeue parent Hello Elementor CSS that constrains .site-main width
 * (safe: leaves your child + main.css intact)
 */
add_action( 'wp_enqueue_scripts', function () {

  // Parent Hello Elementor handles commonly used:
  // - hello-elementor
  // - hello-elementor-style
  // - hello-elementor-theme-style
  // (Dequeue whichever are actually enqueued on your site.)

  wp_dequeue_style( 'hello-elementor' );
  wp_deregister_style( 'hello-elementor' );

  wp_dequeue_style( 'hello-elementor-style' );
  wp_deregister_style( 'hello-elementor-style' );

  wp_dequeue_style( 'hello-elementor-theme-style' );
  wp_deregister_style( 'hello-elementor-theme-style' );

}, 20 );


/**
 * Remove Gutenberg block styles on frontend
 * Safe for lean / non-block themes
 */
add_action( 'wp_enqueue_scripts', function () {

    if ( is_admin() ) {
        return;
    }

    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'wc-block-style' ); // WooCommerce blocks (safe even if WC inactive)

}, 100 );

/**
 * Remove Gutenberg global styles and SVG filters on frontend.
 * Safe for Elementor-driven theme output.
 */
add_action( 'init', function () {
    if ( is_admin() ) {
        return;
    }

    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
    remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
    remove_action( 'wp_head', 'wp_global_styles_render_svg_filters' );
}, 20 );

/**
 * Remove RSS feeds, RSD, WLW and oEmbed links from <head>
 * (frontend cleanup for performance + SEO hygiene)
 */
add_action( 'init', function () {

    if ( is_admin() ) {
        return;
    }

    // RSS feeds
    remove_action( 'wp_head', 'feed_links', 2 );
    remove_action( 'wp_head', 'feed_links_extra', 3 );

    // RSD + WLW
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );

    // oEmbed
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );

}, 20 );

    /* =======================================================
    DEFER SCRIPTS
    ======================================================= */


add_filter( 'style_loader_tag', function ( $html, $handle ) {

  // Only optimise homepage
  if ( ! ( is_front_page() || is_page_template( 'home-page.php' ) ) ) {
    return $html;
  }

  $defer_styles = [
    'pera-slider-css',
    'pera-property-card',
  ];

  if ( ! in_array( $handle, $defer_styles, true ) ) {
    return $html;
  }

  $original = $html;

  $html = preg_replace(
    '/rel=(["\'])stylesheet\1/i',
    'rel=$1stylesheet$1 media="print" onload="this.media=\'all\'"',
    $html,
    1
  );

  $html .= '<noscript>' . $original . '</noscript>';

  return $html;

}, 10, 2 );

add_filter( 'script_loader_tag', function ( $tag, $handle ) {
  $defer_scripts = array(
    'pera-favourites',
    'pera-home-hero-search',
  );

  if ( ! in_array( $handle, $defer_scripts, true ) ) {
    return $tag;
  }

  if ( false !== strpos( $tag, ' defer' ) ) {
    return $tag;
  }

  return str_replace( ' src=', ' defer src=', $tag );
}, 10, 2 );

/**
 * Front-end cache header hardening for auth-sensitive header UI.
 *
 * Why:
 * - Home contains server-rendered staff/admin UI (admin bar + CRM controls).
 * - Long-lived browser disk cache can replay anonymous HTML after login.
 *
 * Policy:
 * - Logged-in front-end responses: private + no-store/no-cache.
 * - Homepage for guests: short browser lifetime + Vary: Cookie to avoid
 *   auth-state cache confusion while keeping guest performance reasonable.
 */
add_action( 'send_headers', function (): void {
  if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
    return;
  }

  if ( headers_sent() ) {
    return;
  }

  $is_homepage = is_front_page() || is_page_template( 'home-page.php' ) || ( is_home() && ! is_front_page() );

  if ( is_user_logged_in() ) {
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
      define( 'DONOTCACHEPAGE', true );
    }

    $nocache = wp_get_nocache_headers();
    foreach ( $nocache as $name => $value ) {
      if ( ! empty( $name ) && null !== $value ) {
        header( $name . ': ' . $value, true );
      }
    }

    header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'Pragma: no-cache', true );
    header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT', true );
    header( 'Vary: Cookie', false );
    return;
  }

  if ( $is_homepage ) {
    // Keep guest caching but prevent month-long stale homepage HTML reuse.
    header( 'Cache-Control: public, max-age=300, must-revalidate', true );
    header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 300 ) . ' GMT', true );
    header( 'Vary: Cookie', false );
  }
}, 20 );
