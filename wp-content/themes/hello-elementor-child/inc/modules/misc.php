<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_footer', 'pera_floating_whatsapp_button' );
add_action( 'after_switch_theme', 'pera_register_forgot_password_page' );

/* =======================================================
    GOOGLE MAPS
======================================================= */

add_filter('acf/fields/google_map/api', function ($api) {
  if ( defined('PERA_GOOGLE_MAPS_KEY') && PERA_GOOGLE_MAPS_KEY ) {
    $api['key'] = PERA_GOOGLE_MAPS_KEY;
  }
  return $api;
});

/**
 * Remove language switcher from wp-login.php
 */
add_filter( 'login_display_language_dropdown', '__return_false' );
