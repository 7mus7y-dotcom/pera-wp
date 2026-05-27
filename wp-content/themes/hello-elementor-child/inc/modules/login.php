<?php
if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! function_exists( 'pera_get_login_background_image_url' ) ) {
  /**
   * Return the branded wp-login background image URL.
   *
   * Source of truth: current wp-login customization uses media attachment ID 55484.
   */
  function pera_get_login_background_image_url(): string {
    $background_attachment_id = 55484;
    $background_image_url     = wp_get_attachment_image_url( $background_attachment_id, 'full' );

    return is_string( $background_image_url ) ? $background_image_url : '';
  }
}

if ( ! function_exists( 'pera_get_logged_in_auth_redirect_url' ) ) {
  /**
   * Resolve the post-auth destination for users who should not view auth forms.
   */
  function pera_get_logged_in_auth_redirect_url(): string {
    return current_user_can( 'manage_options' )
      ? admin_url()
      : home_url( '/client-portal/' );
  }
}

if ( ! function_exists( 'pera_maybe_redirect_logged_in_auth_pages' ) ) {
  /**
   * Redirect logged-in users away from login/reset pages to their role-appropriate dashboard.
   */
  function pera_maybe_redirect_logged_in_auth_pages(): void {
    if ( ! is_user_logged_in() ) {
      return;
    }

    wp_safe_redirect( pera_get_logged_in_auth_redirect_url() );
    exit;
  }
}

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

add_filter( 'login_message', function ( $message ) {
  if ( ! function_exists( 'pera_get_site_logo_markup' ) ) {
    return $message;
  }

  $brand = sprintf(
    '<div class="pera-login-branding">%s</div>',
    pera_get_site_logo_markup( array(
      'link_class' => 'site-logo logo-pera',
      'aria_label' => 'Pera Property',
      'title'      => 'Pera Property',
      'home_url'   => home_url( '/' ),
      'show_since' => true,
    ) )
  );

  return $brand . $message;
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

add_action( 'template_redirect', function () {
  // Keep template-level auth pages from rendering for logged-in users.
  if ( is_page( array( 'client-login', 'client-forgot-password' ) ) ) {
    pera_maybe_redirect_logged_in_auth_pages();
  }
} );

add_action( 'login_init', function () {
  // Mirror the same behavior on wp-login.php only for plain and explicit login actions.
  $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

  if ( is_user_logged_in() && ( '' === $action || 'login' === $action ) ) {
    pera_maybe_redirect_logged_in_auth_pages();
  }
} );


add_action('login_enqueue_scripts', function () {

  $bg = pera_get_login_background_image_url();

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
