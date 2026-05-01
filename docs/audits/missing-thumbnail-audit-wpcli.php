<?php
/**
 * One-off WP-CLI audit script for missing-thumbnail false positives.
 *
 * Usage:
 *   wp eval-file docs/audits/missing-thumbnail-audit-wpcli.php -- <id1,id2,id3>
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "Run with: wp eval-file docs/audits/missing-thumbnail-audit-wpcli.php -- <id1,id2,id3>\n";
	return;
}

$ids_arg = isset( $args[0] ) ? (string) $args[0] : '';
$ids     = array_values( array_filter( array_map( 'intval', preg_split( '/[,\s]+/', $ids_arg ) ) ) );

if ( empty( $ids ) ) {
	WP_CLI::error( 'Provide comma-separated attachment IDs, e.g. -- 101,202,303' );
}

$registered = wp_get_registered_image_subsizes();

foreach ( $ids as $id ) {
	$meta = wp_get_attachment_metadata( $id );
	if ( ! is_array( $meta ) ) {
		WP_CLI::log( "ID {$id}: missing/invalid _wp_attachment_metadata" );
		continue;
	}

	$orig_w = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
	$orig_h = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

	$eligible = array();
	foreach ( $registered as $name => $s ) {
		if ( 'medium_large' === (string) $name ) { continue; }
		$tw = ! empty( $s['width'] ) ? (int) $s['width'] : 0;
		$th = ! empty( $s['height'] ) ? (int) $s['height'] : 0;
		if ( 0 === $tw && 0 === $th ) { continue; }
		if ( $orig_w > 0 && $tw > 0 && $tw > $orig_w ) { continue; }
		if ( $orig_h > 0 && $th > 0 && $th > $orig_h ) { continue; }
		$eligible[] = (string) $name;
	}

	$existing = array_keys( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array() );
	$missing  = array_values( array_diff( $eligible, $existing ) );

	$generatable = array();
	foreach ( $registered as $name => $s ) {
		$tw   = isset( $s['width'] ) ? (int) $s['width'] : 0;
		$th   = isset( $s['height'] ) ? (int) $s['height'] : 0;
		$crop = ! empty( $s['crop'] );
		$generatable[ $name ] = (bool) image_resize_dimensions( $orig_w, $orig_h, $tw, $th, $crop );
	}

	WP_CLI::log( wp_json_encode(
		array(
			'id'                 => $id,
			'original'           => array( 'width' => $orig_w, 'height' => $orig_h ),
			'metadata_sizes'     => $existing,
			'registered_subsizes'=> $registered,
			'detector_eligible'  => $eligible,
			'detector_missing'   => $missing,
			'generatable'        => $generatable,
		),
		JSON_PRETTY_PRINT
	) );
}
