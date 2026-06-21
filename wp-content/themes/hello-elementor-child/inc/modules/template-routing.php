<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =======================================================
   TEMPLATE ROUTING
   ======================================================= */

add_filter( 'template_include', 'pera_force_property_archive_template', 20 );

add_filter( 'template_include', 'pera_force_property_map_template', 20 );

/**
 * Force the public Property Map landing page to use its theme template.
 *
 * This keeps the map page resilient if the WordPress page loses its assigned
 * template, while still falling back safely when the template file is missing.
 */
function pera_force_property_map_template( $template ) {
    if ( is_admin() || ! is_page( 'view-all-our-property-for-sale-in-istanbul-on-this-map' ) ) {
        return $template;
    }

    $custom = get_stylesheet_directory() . '/page-property-map.php';

    if ( file_exists( $custom ) ) {
        return $custom;
    }

    return $template;
}
