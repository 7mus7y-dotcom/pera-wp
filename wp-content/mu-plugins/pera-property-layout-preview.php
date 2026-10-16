<?php
/**
 * Plugin Name: Pera property layout preview isolation
 * Description: Fail-closed admin preview; see docs/property-layout-preview.md.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// MU plugins run before regular plugins and themes. advanced-cache.php and CDN
// lookups run earlier still: an infrastructure bypass is required (see docs).
if ( isset( $_GET['layout_preview'] ) && ! defined( 'DONOTCACHEPAGE' ) ) {
    define( 'DONOTCACHEPAGE', true );
}

function pera_property_layout_preview_requested() {
    return isset( $_GET['layout_preview'] )
        && is_string( $_GET['layout_preview'] )
        && '1' === sanitize_text_field( wp_unslash( $_GET['layout_preview'] ) );
}

function pera_property_layout_preview_enabled() {
    return defined( 'PERA_PROPERTY_LAYOUT_PREVIEW_CACHE_BYPASS_VERIFIED' )
        && true === PERA_PROPERTY_LAYOUT_PREVIEW_CACHE_BYPASS_VERIFIED
        && pera_property_layout_preview_requested()
        && is_user_logged_in()
        && current_user_can( 'manage_options' )
        && is_singular( 'property' );
}

// Protect all query-bearing responses, including anonymous requests. Do this
// before template output and independently of whether the preview is enabled.
add_action( 'init', function () {
    if ( ! isset( $_GET['layout_preview'] ) ) return;
    nocache_headers();
    header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'CDN-Cache-Control: no-store', true );
    header( 'Surrogate-Control: no-store', true );
}, 0 );
