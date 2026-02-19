<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =======================================================
   6. REGISTER 450px card size
   ======================================================= */


add_action( 'after_setup_theme', function () {
  add_image_size( 'pera-card', 800, 450, true ); // 16:9 crop, good for cards
});


/* =======================================================
   6. REGISTER MENUS
   ======================================================= */
add_action( 'after_setup_theme', function() {
    register_nav_menus( array(
        'footer_menu'   => __( 'Footer Menu', 'hello-elementor-child' ),
        'guidance'      => __( 'Guidance Menu', 'hello-elementor-child' ),
        'main_menu_v1'  => __( 'Main Menu v1', 'hello-elementor-child' ),
    ) );
});

/**
 * Enable Excerpt field on Pages (for SEO meta descriptions).
 */
add_action( 'init', function () {
  add_post_type_support( 'page', 'excerpt' );
}, 20 );
