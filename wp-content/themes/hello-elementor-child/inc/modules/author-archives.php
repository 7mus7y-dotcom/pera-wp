<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Retire public author routes after Pera ML has restored the public URI, but
 * before the main query, canonical redirects, geo redirects or SEO output.
 * Keep the rewrite rules: removing them would turn retired URLs into 404s.
 */
function pera_author_archives_maybe_retire( $wp ): void {
	if ( is_admin() || is_user_logged_in()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		|| ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		|| isset( $wp->query_vars['rest_route'] )
	) {
		return;
	}

	// Parsed vars cover pretty URLs (including pagination/feeds), query URLs,
	// unknown authors and every language prefix supported by the ML router.
	if ( ! isset( $wp->query_vars['author'] ) && ! isset( $wp->query_vars['author_name'] ) ) {
		return;
	}

	status_header( 410 );
	nocache_headers();
	header( 'Content-Type: text/plain; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	echo "Gone\n";
	exit;
}
add_action( 'parse_request', 'pera_author_archives_maybe_retire', 1 );

// Core author output can request links independently of our templates.
add_filter( 'the_author_posts_link', static function ( $link ) {
	return is_admin() ? $link : esc_html( get_the_author() );
} );

function pera_author_archives_unlink( $html ) {
	if ( is_admin() ) return $html;
	return preg_replace( '#<a\b[^>]*>(.*?)</a>#is', '$1', $html );
}
add_filter( 'wp_list_authors', 'pera_author_archives_unlink' );
add_filter( 'render_block_core/post-author', 'pera_author_archives_unlink' );
add_filter( 'render_block_core/post-author-name', 'pera_author_archives_unlink' );
