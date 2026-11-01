<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Retire public author routes after Pera ML has restored the public URI, but
 * before the main query, canonical redirects, geo redirects or SEO output.
 * Keep the rewrite rules: removing them would turn retired URLs into 404s.
 */
function pera_author_archives_maybe_retire( $wp ): void {
	if ( is_admin()
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
add_filter( 'the_author_posts_link', 'pera_author_archives_unlink' );

/** Match local WordPress author routes, including Pera ML prefixes. */
function pera_author_archives_is_url( $url ): bool {
	if ( ! is_string( $url ) || '' === $url || '#' === $url[0] ) return false;
	$parts = wp_parse_url( $url );
	$home = wp_parse_url( home_url( '/' ) );
	if ( ! is_array( $parts ) || ! is_array( $home ) ) return false;
	if ( isset( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) return false;
	if ( isset( $parts['host'] ) && ( strtolower( $parts['host'] ) !== strtolower( $home['host'] ) || ( $parts['port'] ?? null ) !== ( $home['port'] ?? null ) ) ) return false;

	$path = $parts['path'] ?? ( $home['path'] ?? '/' );
	// WordPress generates absolute links; also support root-relative and query links.
	if ( '' !== $path && '/' !== $path[0] ) return false;
	$base = rtrim( $home['path'] ?? '', '/' );
	if ( '' !== $base && $path !== $base && 0 !== strpos( $path, $base . '/' ) ) return false;
	$path = ltrim( substr( $path, strlen( $base ) ), '/' );
	if ( class_exists( 'Pera_ML_Plugin' ) ) {
		foreach ( Pera_ML_Plugin::instance()->registry()->enabled() as $language ) {
			$prefix = $language['prefix'];
			if ( '' !== $prefix && 0 === strpos( $path, $prefix . '/' ) ) {
				$path = substr( $path, strlen( $prefix ) + 1 );
				break;
			}
		}
	}
	if ( preg_match( '#^(?:wp-admin(?:/|$)|wp-json(?:/|$)|wp-login\.php(?:/|$)|wp-cron\.php(?:/|$)|xmlrpc\.php(?:/|$))#i', $path ) ) return false;
	if ( isset( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
		if ( isset( $query['rest_route'] ) ) return false;
		if ( isset( $query['author'] ) || isset( $query['author_name'] ) ) return true;
	}
	global $wp_rewrite;
	$author_base = trim( $wp_rewrite->author_base ?? 'author', '/' );
	return (bool) preg_match( '#^' . preg_quote( $author_base, '#' ) . '/[^/]+(?:/page/[0-9]+|/feed(?:/[^/]+)?)?/?$#', $path );
}

function pera_author_archives_unlink( $html ) {
	if ( is_admin() ) return $html;
	// Tokenize rather than reserializing HTML: unrelated markup stays byte-for-byte
	// intact, including biography links, images, entities and nested name markup.
	$tokens = wp_html_split( $html );
	$anchors = array();
	foreach ( $tokens as $index => $token ) {
		if ( preg_match( '#^</a\s*>$#i', $token ) ) {
			$opening = array_pop( $anchors );
			if ( null !== $opening && $opening['unlink'] ) {
				$tokens[ $opening['index'] ] = '';
				$tokens[ $index ] = '';
			}
			continue;
		}
		if ( '' === $token || '<' !== $token[0] ) continue;
		$tag = new WP_HTML_Tag_Processor( $token );
		if ( $tag->next_tag( 'A' ) ) {
			$anchors[] = array( 'index' => $index, 'unlink' => pera_author_archives_is_url( $tag->get_attribute( 'href' ) ) );
		}
	}
	return implode( '', $tokens );
}
add_filter( 'wp_list_authors', 'pera_author_archives_unlink' );
add_filter( 'render_block_core/post-author', 'pera_author_archives_unlink' );
add_filter( 'render_block_core/post-author-name', 'pera_author_archives_unlink' );
