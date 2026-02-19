<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =======================================================
   LOGIN SCREEN (wp-login.php): login.css + BRANDING
   ======================================================= */
add_action( 'login_enqueue_scripts', function () {

  $css_rel  = '/css/login.css';
  $css_url  = get_stylesheet_directory_uri() . $css_rel;

  // Cache-bust using file modified time (falls back to theme version)
  $ver = pera_get_asset_version( $css_rel );

  wp_enqueue_style( 'pera-login', $css_url, array(), $ver );

  // Optional: load your theme font if your login.css relies on it
  // wp_enqueue_style( 'pera-fonts', get_stylesheet_directory_uri() . '/css/fonts.css', array(), $ver );
}, 20 );

add_filter( 'login_headerurl', function () {
  return home_url( '/' );
} );

add_filter( 'login_headertext', function () {
  return 'Pera Property – Client Login';
} );

add_filter( 'login_redirect', function ( $redirect_to, $requested_redirect_to, $user ) {
  if ( ! $user || is_wp_error( $user ) ) {
    return $redirect_to;
  }

  if ( user_can( $user, 'manage_options' ) ) {
    if ( ! empty( $redirect_to ) ) {
      return $redirect_to;
    }

    if ( ! empty( $requested_redirect_to ) ) {
      return $requested_redirect_to;
    }

    return admin_url();
  }

  if ( ! empty( $requested_redirect_to ) ) {
    return $requested_redirect_to;
  }

  if ( ! empty( $redirect_to ) ) {
    return $redirect_to;
  }

  return home_url( '/my-favourites/' );
}, 10, 3 );


add_action('login_enqueue_scripts', function () {

  $bg = wp_get_attachment_image_url(55484, 'full');

  if ($bg) {
    wp_add_inline_style('pera-login', "
      body.login {
        background-image: url('{$bg}');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
      }
      body.login:before {
        content:'';
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.55); /* brand overlay */
        z-index: -1;
      }
    ");
  }

}, 30);

/* =======================================================
   Allow registration
   ======================================================= */

add_action('login_init', function () {
  if (!empty($_GET['action'])) {
    error_log('WP-LOGIN ACTION: ' . sanitize_text_field($_GET['action']));
  }
});
