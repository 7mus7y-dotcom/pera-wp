<?php
/**
 * Admin shortcut from a taxonomy term edit screen to its public archive.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', 'pera_admin_render_term_view_link' );

/**
 * Render a canonical front-end permalink shortcut for the current public term.
 *
 * @return void
 */
function pera_admin_render_term_view_link() {
	global $pagenow;

	if ( 'term.php' !== $pagenow ) {
		return;
	}

	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
	$term_id  = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0;
	$tax_obj  = $taxonomy ? get_taxonomy( $taxonomy ) : null;

	if ( ! $term_id || ! $tax_obj || ! $tax_obj->public || ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}

	$term = get_term( $term_id, $taxonomy );

	if ( ! $term instanceof WP_Term ) {
		return;
	}

	$term_url = get_term_link( $term );

	if ( is_wp_error( $term_url ) || ! is_string( $term_url ) || '' === $term_url ) {
		return;
	}

	echo '<div class="notice notice-info pera-term-view-link"><p>';
	echo '<a class="button button-secondary" href="' . esc_url( $term_url ) . '" target="_blank" rel="noopener noreferrer">';
	echo esc_html__( 'View on site', 'hello-elementor-child' );
	echo '</a>';
	echo '</p></div>';
}
