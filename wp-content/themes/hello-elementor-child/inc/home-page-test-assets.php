<?php
/**
 * TEMP FILE — Homepage test template assets
 * Safe to delete once home-page-test.php is approved
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

add_action( 'wp_enqueue_scripts', function () {
  if ( ! is_page_template( 'home-page-test.php' ) ) {
    return;
  }

  wp_enqueue_style(
    'pera-slider-css',
    get_stylesheet_directory_uri() . '/css/slider.css',
    array( 'pera-main-css' ),
    pera_get_asset_version( '/css/slider.css' )
  );

  wp_enqueue_style(
    'pera-property-css',
    get_stylesheet_directory_uri() . '/css/property.css',
    array( 'pera-main-css' ),
    pera_get_asset_version( '/css/property.css' )
  );

  wp_enqueue_style(
    'pera-property-card',
    get_stylesheet_directory_uri() . '/css/property-card.css',
    array( 'pera-main-css', 'pera-slider-css' ),
    pera_get_asset_version( '/css/property-card.css' )
  );

  wp_enqueue_script(
    'pera-favourites',
    get_stylesheet_directory_uri() . '/js/favourites.js',
    array(),
    pera_get_asset_version( '/js/favourites.js' ),
    true
  );

  wp_localize_script(
    'pera-favourites',
    'peraFavourites',
    array(
      'ajax_url'     => admin_url( 'admin-ajax.php' ),
      'nonce'        => wp_create_nonce( 'pera_favourites' ),
      'is_logged_in' => is_user_logged_in(),
    )
  );

  wp_enqueue_script(
    'pera-home-hero-search',
    get_stylesheet_directory_uri() . '/js/home-hero-search.js',
    array(),
    pera_get_asset_version( '/js/home-hero-search.js' ),
    true
  );
}, 25 );
