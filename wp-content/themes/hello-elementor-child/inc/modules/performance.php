<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =======================================================
   REMOVE HELLO ELEMENTOR PARENT CSS
   ======================================================= */
add_action( 'wp_enqueue_scripts', function () {

    wp_dequeue_style( 'hello-elementor' );
    wp_deregister_style( 'hello-elementor' );

    wp_dequeue_style( 'hello-elementor-style' );
    wp_deregister_style( 'hello-elementor-style' );

    wp_dequeue_style( 'hello-elementor-theme-style' );
    wp_deregister_style( 'hello-elementor-theme-style' );

}, 20 );

/* =======================================================
   FRONTEND HEAD CLEANUP
   ======================================================= */
add_action( 'init', function () {

    if ( is_admin() ) {
        return;
    }

    /* ---------------------------------------
     * Replace WP core canonical with custom canonical
     * --------------------------------------- */
    remove_action( 'wp_head', 'rel_canonical' );
    remove_action( 'wp_head', 'wp_site_icon', 99 );
    add_filter( 'get_site_icon_url', '__return_false' );

    /* ---------------------------------------
     * Gutenberg / global styles cleanup
     * --------------------------------------- */
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
    remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
    remove_action( 'wp_head', 'wp_global_styles_render_svg_filters' );

    /* ---------------------------------------
     * RSS / RSD / WLW cleanup
     * --------------------------------------- */
    remove_action( 'wp_head', 'feed_links', 2 );
    remove_action( 'wp_head', 'feed_links_extra', 3 );
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'rest_output_link_wp_head' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    remove_action( 'wp_head', 'wp_generator' );

    /* ---------------------------------------
     * oEmbed cleanup
     * --------------------------------------- */
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );

    /* ---------------------------------------
     * Emoji cleanup
     * --------------------------------------- */
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );

}, 20 );

add_filter( 'wp_robots', function ( array $robots ): array {

    if ( is_admin() ) {
        return $robots;
    }

    if ( ! empty( $robots['noindex'] ) || ! empty( $robots['nofollow'] ) ) {
        return $robots;
    }

    if ( ! isset( $robots['index'] ) && ! isset( $robots['noindex'] ) ) {
        $robots['index'] = true;
    }

    if ( ! isset( $robots['follow'] ) && ! isset( $robots['nofollow'] ) ) {
        $robots['follow'] = true;
    }

    if ( empty( $robots['max-image-preview'] ) ) {
        $robots['max-image-preview'] = 'large';
    }

    return $robots;

}, 99 );

/* =======================================================
   GUTENBERG / BLOCK CSS CLEANUP
   ======================================================= */
add_action( 'wp_enqueue_scripts', function () {

    if ( is_admin() ) return;

    wp_dequeue_style( 'wp-block-library' );
    wp_deregister_style( 'wp-block-library' );

    wp_dequeue_style( 'wp-block-library-theme' );
    wp_deregister_style( 'wp-block-library-theme' );

    wp_dequeue_style( 'wc-block-style' );
    wp_deregister_style( 'wc-block-style' );

}, 100 );

/* =======================================================
   REMOVE WP EMBED
   ======================================================= */
add_action( 'wp_enqueue_scripts', function () {

    if ( is_admin() ) return;

    wp_deregister_script( 'wp-embed' );

}, 100 );

/* =======================================================
   DEFER NON-CRITICAL CSS (HOMEPAGE ONLY)
   ======================================================= */
add_filter( 'style_loader_tag', function ( $html, $handle ) {

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

/* =======================================================
   DEFER SELECT JS
   ======================================================= */
add_filter( 'script_loader_tag', function ( $tag, $handle ) {

    $defer_scripts = [
        'pera-favourites',
        'pera-home-hero-search',
    ];

    if ( ! in_array( $handle, $defer_scripts, true ) ) {
        return $tag;
    }

    if ( strpos( $tag, ' defer' ) !== false ) {
        return $tag;
    }

    return str_replace( ' src=', ' defer src=', $tag );

}, 10, 2 );

/* =======================================================
   CACHE HEADER HARDENING
   ======================================================= */
add_action( 'send_headers', function (): void {

    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    // Detect meaningful filters (not UTMs etc)
    $has_filters = function_exists('pera_property_archive_is_filtered_request')
        ? pera_property_archive_is_filtered_request()
        : ! empty($_GET);

    if ( $has_filters ) {
        nocache_headers();
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
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
        header( 'Cache-Control: public, max-age=300, must-revalidate', true );
        header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 300 ) . ' GMT', true );
        header( 'Vary: Cookie', false );
        header( 'Vary: Accept-Encoding', false );
    }

}, 20 );
