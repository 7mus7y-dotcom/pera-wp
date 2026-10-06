<?php
/** Read-only audit. Run with wp eval-file; arguments are passed after --. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( "Run this script with wp eval-file.\n" );
}
// The frontend SEO loader only runs on singular property requests, not eval-file.
require_once dirname( __DIR__ ) . '/inc/property-faq.php';

if ( ! function_exists( 'get_field' ) || ! function_exists( 'pera_property_get_faq_items' ) ) {
    WP_CLI::error( 'Load ACF and the active Pera child theme (do not use --skip-themes/--skip-plugins).' );
}

// eval-file exposes positional arguments as $args, not PHP's process argv.
$options = array( 'status' => 'publish,draft', 'limit' => '100', 'offset' => '0' );
foreach ( isset( $args ) ? $args : array() as $argument ) {
    if ( ! preg_match( '/^--(status|limit|offset)=(.+)$/', $argument, $match ) ) {
        WP_CLI::error( 'Supported arguments: --status=publish,draft --limit=100 --offset=0' );
    }
    $options[ $match[1] ] = $match[2];
}
$statuses = array_unique( explode( ',', $options['status'] ) );
if ( array_diff( $statuses, array( 'publish', 'draft' ) ) ) {
    WP_CLI::error( 'Status must be publish, draft, or publish,draft.' );
}
if ( ! ctype_digit( $options['limit'] ) || (int) $options['limit'] < 1 || ! ctype_digit( $options['offset'] ) ) {
    WP_CLI::error( 'Limit must be a positive integer; offset must be a non-negative integer.' );
}
$plain = static function ( $value ) {
    if ( ! is_scalar( $value ) ) return '';
    $value = strip_shortcodes( (string) $value );
    $value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' );
    return trim( (string) preg_replace( '/[\s\x{00a0}]+/u', ' ', $value ) );
};
$query = new WP_Query( array(
    'post_type' => 'property',
    'post_status' => $statuses,
    'posts_per_page' => (int) $options['limit'],
    'offset' => (int) $options['offset'],
    'orderby' => 'ID',
    'order' => 'ASC',
    'no_found_rows' => true,
) );
$output = fopen( 'php://stdout', 'w' );
fputcsv( $output, array( 'ID', 'Title', 'Status', 'Findings' ), ',', '"', '' );
foreach ( $query->posts as $property ) {
    $id = (int) $property->ID;
    $issues = array();
    foreach ( array( 'property_editorial_intro', 'property_district_analysis', 'property_investment_potential' ) as $field ) {
        $text = $plain( get_field( $field, $id ) );
        $words = $text === '' ? 0 : count( preg_split( '/\s+/u', $text ) );
        if ( $words < 30 ) $issues[] = $field . ( $words === 0 ? ': missing' : ': weak (' . $words . ' words; <30)' );
    }
    if ( $plain( get_field( 'property_buyer_suitability', $id ) ) === '' ) $issues[] = 'property_buyer_suitability: missing';
    $faq_count = count( pera_property_get_faq_items( $id ) );
    if ( $faq_count < 3 ) $issues[] = 'property_faq_text: ' . $faq_count . ' parsed rows (<3)';
    if ( $plain( $property->post_excerpt ) === '' ) $issues[] = 'excerpt: missing';

    // Check the actual main_image field, without substituting featured images or title alt text.
    $image = get_field( 'main_image', $id );
    $image_id = is_array( $image ) ? (int) ( $image['ID'] ?? $image['id'] ?? 0 ) : ( is_numeric( $image ) ? (int) $image : 0 );
    $image_url = is_array( $image ) ? (string) ( $image['url'] ?? '' ) : ( is_string( $image ) && ! is_numeric( $image ) ? $image : '' );
    if ( $image_url === '' && $image_id > 0 ) $image_url = (string) wp_get_attachment_image_url( $image_id, 'full' );
    if ( $image_url === '' ) {
        $issues[] = 'main_image: missing/unresolvable';
    } else {
        $alt = is_array( $image ) ? $plain( $image['alt'] ?? '' ) : '';
        if ( $alt === '' && $image_id > 0 ) $alt = $plain( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
        if ( $alt === '' ) $issues[] = 'main_image alt: missing (URL-only images require manual verification)';
    }
    foreach ( array( 'district', 'region', 'property_type' ) as $taxonomy ) {
        $terms = wp_get_post_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) $issues[] = $taxonomy . ': missing';
    }
    $units = get_field( 'v2_units', $id );
    $special = wp_get_post_terms( $id, 'special', array( 'fields' => 'slugs' ) );
    $pricing_relevant = ( is_array( $units ) && ! empty( $units ) ) ||
        ( is_array( $special ) && array_intersect( array( 'project', 'resales' ), $special ) );
    if ( $pricing_relevant ) {
        if ( ! is_array( $units ) || empty( $units ) ) {
            $issues[] = 'v2_units: missing (review pricing applicability)';
        } else {
            foreach ( $units as $index => $unit ) {
                $price = is_array( $unit ) ? ( $unit['v2_price_usd_min'] ?? null ) : null;
                if ( ! is_numeric( $price ) || (float) $price <= 0 ) $issues[] = 'v2_units row ' . ( $index + 1 ) . ': missing positive numeric v2_price_usd_min (review price-on-request)';
            }
        }
    }
    // post_heading is the relationship used by the single-property further-reading section.
    $relationship_exists = function_exists( 'get_field_object' ) && get_field_object( 'post_heading', $id, false, false );
    if ( $relationship_exists || metadata_exists( 'post', $id, 'post_heading' ) ) {
        $reading = get_field( 'post_heading', $id );
        $reading_ids = array();
        foreach ( is_array( $reading ) ? $reading : array() as $item ) {
            $reading_id = is_object( $item ) ? (int) ( $item->ID ?? 0 ) : ( is_numeric( $item ) ? (int) $item : 0 );
            if ( $reading_id > 0 && get_post( $reading_id ) ) $reading_ids[] = $reading_id;
        }
        if ( empty( $reading_ids ) ) $issues[] = 'post_heading: missing further reading relationship';
    }
    fputcsv( $output, array( $id, $plain( $property->post_title ), $property->post_status, $issues ? implode( '; ', $issues ) : 'OK' ), ',', '"', '' );
}
fclose( $output );
