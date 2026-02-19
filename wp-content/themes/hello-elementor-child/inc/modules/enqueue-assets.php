<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =======================================================
   GLOBAL: ENQUEUE CORE STYLES & SCRIPTS (ALL PAGES)
   ======================================================= */
add_action( 'wp_enqueue_scripts', function () {

  /* =========================
     0) ALWAYS
  ========================= */

  // main.css everywhere
  wp_enqueue_style(
    'pera-main-css',
    get_stylesheet_directory_uri() . '/css/main.css',
    array(),
    pera_get_asset_version( '/css/main.css' )
  );

  // main.js everywhere
  wp_enqueue_script(
    'pera-main-js',
    get_stylesheet_directory_uri() . '/js/main.js',
    array(),
    pera_get_asset_version( '/js/main.js' ),
    true
  );

  /* =========================
     1) VIEW FLAGS
  ========================= */

  $is_home = is_front_page() || is_page_template( 'home-page.php' );

    $is_property_archive = is_post_type_archive( 'property' ) || is_tax( array(
      'property_type',
      'region',
      'district',
      'special',
      'property_tags',
    ) );

  $is_single_property = is_singular( 'property' );
  $is_portfolio_token = function_exists( 'pera_portfolio_token_is_request' ) && pera_portfolio_token_is_request();

  $is_blog_page    = is_page_template( 'page-posts.php' ) || is_page( 'blog' );
  $is_single_post  = is_singular( 'post' );
  $is_blog_archive = function_exists( 'pera_is_blog_archive' ) ? pera_is_blog_archive() : false;

  // Specific templates
  $is_contact_page = is_page_template( 'page-contact.php' );
  $is_about_new    = is_page_template( 'page-about-new.php' );
  $is_favourites_page = is_page_template( 'page-favourites.php' );
  $is_property_map = is_page_template( 'page-property-map.php' );
  $is_enquiry_page = is_page_template( 'page-citizenship.php' ) ||
    is_page_template( 'page-rent-with-pera.php' ) ||
    is_page_template( 'page-sell-with-pera.php' ) ||
    is_page_template( 'page-book-a-consultancy.php' ) ||
    $is_favourites_page ||
    is_singular( 'property' ) ||
    is_singular( 'bodrum-property' );

/* =========================
   2) slider.css
   Rule: home, single-property, single-post, contact, about-new, single-bodrum-property
   NOT on property archives / general archives
========================= */

$is_single_bodrum_property = is_singular( 'bodrum-property' );

$needs_slider = (
  $is_home ||
  $is_single_property ||
  $is_single_bodrum_property ||
  $is_single_post ||
  $is_contact_page ||
  $is_about_new
);

if ( $needs_slider ) {
  wp_enqueue_style(
    'pera-slider-css',
    get_stylesheet_directory_uri() . '/css/slider.css',
    array( 'pera-main-css' ),
    pera_get_asset_version( '/css/slider.css' )
  );
}


  /* =========================
     3) property.css
     Rule: property archive OR single property OR home
  ========================= */

  if ( $is_property_archive || $is_portfolio_token ) {
    pera_enqueue_property_archive_assets( $needs_slider );
  }

  if ( $is_single_property || $is_single_bodrum_property || $is_home ) {
    wp_enqueue_style(
      'pera-property-css',
      get_stylesheet_directory_uri() . '/css/property.css',
      array( 'pera-main-css' ),
      pera_get_asset_version( '/css/property.css' )
    );
  }

  /* =========================
     4) property-card.css
     Rule: home OR property archive OR single property OR single post
  ========================= */

  if ( $is_home || $is_single_property || $is_single_post || $is_favourites_page || $is_property_map ) {

    $deps = array( 'pera-main-css' );
    if ( $needs_slider ) {
      $deps[] = 'pera-slider-css';
    }

    wp_enqueue_style(
      'pera-property-card',
      get_stylesheet_directory_uri() . '/css/property-card.css',
      $deps,
      pera_get_asset_version( '/css/property-card.css' )
    );
  }

  /* =========================
     5) blog.css
     Rule: blog page OR single post OR blog archive
  ========================= */

  if ( $is_blog_page || $is_single_post || $is_blog_archive ) {

    $deps = array( 'pera-main-css' );
    if ( $needs_slider ) {
      $deps[] = 'pera-slider-css';
    }

    wp_enqueue_style(
      'pera-blog-css',
      get_stylesheet_directory_uri() . '/css/blog.css',
      $deps,
      pera_get_asset_version( '/css/blog.css' )
    );
  }

  /* =========================
     6) posts.css
     Rule: blog page OR single post OR blog archive OR single property
  ========================= */

  if ( $is_blog_page || $is_single_post || $is_blog_archive || $is_single_property ) {

    $deps = array( 'pera-main-css' );
    if ( $needs_slider ) {
      $deps[] = 'pera-slider-css';
    }

    wp_enqueue_style(
      'pera-posts-css',
      get_stylesheet_directory_uri() . '/css/posts.css',
      $deps,
      pera_get_asset_version( '/css/posts.css' )
    );
  }

  /* =========================
     7) favourites.js
     Rule: home OR property archive OR single property
  ========================= */

  if ( $is_home || $is_property_archive || $is_single_property || $is_single_post || $is_favourites_page ) {
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
  }

  if ( $is_enquiry_page ) {
    wp_enqueue_script(
      'pera-enquiry-nonce',
      get_stylesheet_directory_uri() . '/js/enquiry-nonce.js',
      array(),
      pera_get_asset_version( '/js/enquiry-nonce.js' ),
      true
    );

    wp_localize_script(
      'pera-enquiry-nonce',
      'peraEnquiryNonce',
      array(
        'ajax_url'          => admin_url( 'admin-ajax.php' ),
        'action'            => 'pera_get_enquiry_nonces',
        'issued_at'         => time(),
        'max_age_seconds'   => 900,
      )
    );
  }

  /* =========================
     8) property-map.js (Property Map template only)
  ========================= */

  if ( $is_property_map ) {
    $google_maps_url = 'https://maps.googleapis.com/maps/api/js';
    if ( defined( 'PERA_GOOGLE_MAPS_KEY' ) && PERA_GOOGLE_MAPS_KEY ) {
      $google_maps_url = add_query_arg(
        array( 'key' => rawurlencode( PERA_GOOGLE_MAPS_KEY ) ),
        $google_maps_url
      );
    }

    wp_enqueue_script(
      'pera-google-maps',
      $google_maps_url,
      array(),
      null,
      true
    );

    wp_enqueue_script(
      'pera-property-map',
      get_stylesheet_directory_uri() . '/js/property-map.js',
      array( 'pera-google-maps' ),
      pera_get_asset_version( '/js/property-map.js' ),
      true
    );
  }

}, 20 );

/**
 * home-page-dev JS (hero search logic)
 */
 
 add_action( 'wp_enqueue_scripts', function () {

  if ( ! is_page_template( 'home-page.php' ) ) {
    return;
  }

  wp_enqueue_script(
    'pera-home-hero-search',
    get_stylesheet_directory_uri() . '/js/home-hero-search.js',
    array(),
    pera_get_asset_version( '/js/home-hero-search.js' ),
    true
  );

}, 40 );
