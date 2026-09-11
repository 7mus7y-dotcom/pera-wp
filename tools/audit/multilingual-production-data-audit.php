<?php
/**
 * Read-only production-data audit for visitor-facing internal URLs.
 *
 * Run from the WordPress root:
 *   wp eval-file tools/audit/multilingual-production-data-audit.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this script with WP-CLI via wp eval-file.\n" );
	exit( 1 );
}

/** Return whether a stored URL is an auditable visitor-facing internal URL. */
function pera_ml_audit_is_internal_visitor_url( $url ) {
	$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
	if ( '' === $url || '#' === $url[0] || 0 === strpos( $url, '//' ) ) {
		return false;
	}
	if ( preg_match( '#^(?:mailto|tel|javascript|data):#i', $url ) ) {
		return false;
	}

	$path = '';
	if ( '/' === $url[0] ) {
		$path = $url;
	} else {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || ! in_array( strtolower( $parts['host'] ), array( 'peraproperty.com', 'www.peraproperty.com' ), true ) ) {
			return false;
		}
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
	}

	if ( preg_match( '#^/(?:wp-admin|wp-json|wp-content/uploads)(?:/|$)#i', $path ) ) {
		return false;
	}
	return ! preg_match( '#\.(?:avif|gif|jpe?g|png|svg|webp|pdf|mp4|webm|mp3|woff2?)(?:$|[?#])#i', $path );
}

/** Print one tab-separated finding. */
function pera_ml_audit_print( $section, array $values ) {
	echo $section . "\t" . implode( "\t", array_map( 'strval', $values ) ) . "\n";
}

echo "SECTION\tPOST_ID\tPOST_TYPE\tTITLE_OR_FIELD\tURL\n";
$post_types = get_post_types( array( 'public' => true ), 'names' );
$query      = new WP_Query(
	array(
		'post_type'              => array_values( $post_types ),
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'orderby'                => 'ID',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	)
);

foreach ( $query->posts as $post_id ) {
	$post = get_post( $post_id );
	if ( $post && preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $post->post_content, $matches ) ) {
		foreach ( array_unique( $matches[2] ) as $url ) {
			if ( pera_ml_audit_is_internal_visitor_url( $url ) ) {
				pera_ml_audit_print( 'CONTENT', array( $post_id, $post->post_type, get_the_title( $post_id ), $url ) );
			}
		}
	}
}

/** Recursively inspect raw ACF values while retaining their field path. */
function pera_ml_audit_acf_value( $post, $path, $value ) {
	if ( is_string( $value ) && pera_ml_audit_is_internal_visitor_url( $value ) ) {
		pera_ml_audit_print( 'ACF', array( $post->ID, $post->post_type, $path, $value ) );
		return;
	}
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $child ) {
		$child_path = $path . ( is_int( $key ) ? '[' . $key . ']' : '.' . $key );
		pera_ml_audit_acf_value( $post, $child_path, $child );
	}
}

if ( function_exists( 'get_field_objects' ) ) {
	foreach ( $query->posts as $post_id ) {
		$post   = get_post( $post_id );
		$fields = get_field_objects( $post_id, false, false );
		foreach ( is_array( $fields ) ? $fields : array() as $name => $field ) {
			pera_ml_audit_acf_value( $post, (string) $name, isset( $field['value'] ) ? $field['value'] : null );
		}
	}
} else {
	echo "NOTICE\tACF is not active; ACF inspection was skipped.\n";
}

echo "SECTION\tMENU\tITEM_ID\tLABEL\tURL\n";
foreach ( wp_get_nav_menus() as $menu ) {
	foreach ( (array) wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) ) as $item ) {
		if ( 'custom' === $item->type && pera_ml_audit_is_internal_visitor_url( $item->url ) ) {
			pera_ml_audit_print( 'MENU', array( $menu->name, $item->ID, $item->title, $item->url ) );
		}
	}
}
