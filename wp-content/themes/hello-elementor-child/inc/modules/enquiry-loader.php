<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * functions.php (or your existing loader section)
 * Conditionally load /inc/enquiry.php only on:
 * - page-citizenship.php
 * - page-rent-with-pera.php
 * - page-sell-with-pera.php
 * - single-property.php
 */
/**
 * Conditionally load enquiry handler early enough for init hook.
 * Location: functions.php
 */
add_action( 'init', function () {

  if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) === 'pera_get_enquiry_nonces' ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

  // Always load if this is a relevant POST (so submissions work even if template checks fail)
  if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ( isset( $_POST['sr_action'] ) || isset( $_POST['pera_citizenship_action'] ) || isset( $_POST['fav_enquiry_action'] ) ) ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

  // Otherwise load only on relevant front-end views
  if ( is_admin() ) {
    return;
  }

  // Single property
  if ( is_singular( 'property' ) || is_singular( 'bodrum-property' ) ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

  // Pages by template (only works if these are actual template filenames in your theme)
  if (
    is_page_template( 'page-citizenship.php' ) ||
    is_page_template( 'page-rent-with-pera.php' ) ||
    is_page_template( 'page-sell-with-pera.php' ) ||
    is_page_template( 'page-book-a-consultancy.php' ) ||
    is_page_template( 'page-favourites.php' )
  ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

  // Safety fallback: if your pages are not using those exact filenames, load by slug as well
  if ( is_page( array( 'citizenship-by-investment', 'rent-with-pera', 'sell-with-pera', 'my-favourites' ) ) ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

}, 1 );
