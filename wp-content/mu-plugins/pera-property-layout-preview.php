<?php
/**
 * Plugin Name: Pera property layout preview isolation
 * Description: Reusable administrator-only property preview gate; no experiment is active.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Run before regular plugins/themes, without waiting for authentication or the
// main query. Earlier advanced-cache.php/CDN lookups need their own bypass.
if ( array_key_exists( 'layout_preview', $_GET ) && ! defined( 'DONOTCACHEPAGE' ) ) {
    define( 'DONOTCACHEPAGE', true );
}

/** Whether the query contains the exact opt-in value (never sanitize into it). */
function pera_property_layout_preview_requested() {
    return isset( $_GET['layout_preview'] )
        && is_string( $_GET['layout_preview'] )
        && '1' === $_GET['layout_preview'];
}

/** Call after the main query, e.g. from a property template. */
function pera_property_layout_preview_enabled() {
    return pera_property_layout_preview_requested()
        && is_user_logged_in()
        && current_user_can( 'manage_options' )
        && is_singular( 'property' );
}

/** Protect every query-bearing response, even denied or malformed previews. */
function pera_property_layout_preview_nocache() {
    if ( ! array_key_exists( 'layout_preview', $_GET ) || headers_sent() ) {
        return;
    }

    nocache_headers();
    header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'CDN-Cache-Control: no-store', true );
    header( 'Surrogate-Control: no-store', true );
}
add_action( 'init', 'pera_property_layout_preview_nocache', 0 );
