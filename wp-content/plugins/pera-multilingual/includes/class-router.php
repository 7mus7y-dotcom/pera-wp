<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Router {
	private $registry;
	private $language = 'en';
	private $original_uri = '';
	private $stripped = false;
	private $language_root = false;

	public function __construct( $registry ) { $this->registry = $registry; }
	public function hooks() {
		add_action( 'plugins_loaded', array( $this, 'detect_and_strip_prefix' ), 1 );
		add_action( 'parse_request', array( $this, 'restore_public_uri' ), 0 );
		add_filter( 'redirect_canonical', array( $this, 'prevent_prefix_loss' ), 10, 2 );
		add_filter( 'post_link', array( $this, 'localize_url' ), 20 );
		add_filter( 'page_link', array( $this, 'localize_url' ), 20 );
		add_filter( 'post_type_link', array( $this, 'localize_url' ), 20 );
		add_filter( 'post_type_archive_link', array( $this, 'localize_url' ), 20 );
		add_filter( 'term_link', array( $this, 'localize_url' ), 20 );
		add_filter( 'author_link', array( $this, 'localize_url' ), 20 );
		add_filter( 'get_pagenum_link', array( $this, 'localize_url' ), 20 );
		add_filter( 'nav_menu_link_attributes', array( $this, 'localize_footer_menu_link' ), 20, 4 );
	}

	public function detect_and_strip_prefix() {
		if ( ! $this->is_eligible_request() || empty( $_SERVER['REQUEST_URI'] ) ) return;
		$uri = wp_unslash( (string) $_SERVER['REQUEST_URI'] );
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) return;
		$site_path = $this->home_path();
		if ( ! $this->path_belongs_to_home( $path, $site_path ) ) return;
		$relative = $this->relative_path( $path, $site_path );
		$first = strtok( $relative, '/' );
		$language = $this->registry->from_prefix( is_string( $first ) ? $first : '' );
		if ( ! $language ) return;
		$canonical_relative = ltrim( substr( $relative, strlen( $language['prefix'] ) ), '/' );
		if ( $this->is_system_path( $canonical_relative ) ) return;
		$this->language = $language['code'];
		$this->original_uri = $uri;
		$this->language_root = '' === $canonical_relative;
		$without = trailingslashit( untrailingslashit( $site_path ) ) . $canonical_relative;
		if ( '/' !== substr( $path, -1 ) && '' !== $canonical_relative ) $without = untrailingslashit( $without );
		$query = wp_parse_url( $uri, PHP_URL_QUERY );
		$_SERVER['REQUEST_URI'] = ( '' === $without ? '/' : $without ) . ( is_string( $query ) && '' !== $query ? '?' . $query : '' );
		$this->stripped = true;
	}

	public function restore_public_uri( $wp ) {
		if ( $this->stripped ) {
			$_SERVER['REQUEST_URI'] = $this->original_uri;
			if ( $this->language_root ) {
				$this->restore_front_page_query( $wp );
			} else {
				$wp->query_vars['pera_ml_lang'] = $this->language;
			}
		}
	}

	/**
	 * Give a translated language root the same main-query target as the site root.
	 *
	 * WP_Query only performs its empty-query static-front-page conversion when the
	 * original query is empty. Adding pera_ml_lang to /zh/ or /ar/ made it non-empty,
	 * so WordPress left is_home set and loaded the posts page. The router already
	 * owns the request language, so the language marker is deliberately omitted for
	 * roots and the configured front-page ID is supplied instead.
	 */
	private function restore_front_page_query( $wp ) {
		unset( $wp->query_vars['pera_ml_lang'] );
		if ( 'page' !== get_option( 'show_on_front' ) ) return;
		$front_page_id = (int) get_option( 'page_on_front' );
		if ( $front_page_id > 0 ) $wp->query_vars['page_id'] = $front_page_id;
	}

	public function current_language() { return $this->language; }
	public function set_request_language( $code ) {
		$language = $this->registry->get( sanitize_key( (string) $code ) );
		$this->language = $language && ! empty( $language['enabled'] ) ? $language['code'] : 'en';
		return $this->language;
	}
	public function is_translated() { return 'en' !== $this->language; }
	public function prevent_prefix_loss( $redirect, $requested ) {
		if ( ! $this->is_translated() || ! is_string( $redirect ) || '' === $redirect ) return $redirect;
		if ( ! $this->is_local_frontend_url( $redirect ) ) return false;
		$localized = $this->url_for_language( $redirect, $this->language );
		if ( $this->urls_match( $localized, $requested ) ) return false;
		return $localized;
	}

	public function url_for_language( $url, $code ) {
		if ( ! is_string( $url ) || '' === $url || '#' === substr( $url, 0, 1 ) || preg_match( '/^(?:mailto|tel|javascript):/i', $url ) ) return $url;
		$language = $this->registry->get( $code );
		if ( ! $language || empty( $language['enabled'] ) ) return $url;
		$root_relative = '/' === substr( $url, 0, 1 ) && 0 !== strpos( $url, '//' );
		if ( $root_relative ) {
			$home_origin = wp_parse_url( home_url( '/' ) );
			if ( empty( $home_origin['host'] ) ) return $url;
			$url = ( isset( $home_origin['scheme'] ) ? $home_origin['scheme'] . '://' : '//' ) . $home_origin['host'] . ( isset( $home_origin['port'] ) ? ':' . $home_origin['port'] : '' ) . $url;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) return $url;
		$home = wp_parse_url( home_url( '/' ) );
		if ( empty( $home['host'] ) || strtolower( $parts['host'] ) !== strtolower( $home['host'] ) ) return $url;
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$home_path = isset( $home['path'] ) ? $this->normalize_home_path( $home['path'] ) : '/';
		if ( ! $this->path_belongs_to_home( $path, $home_path ) ) return $url;
		$relative = $this->relative_path( $path, $home_path );
		foreach ( $this->registry->all() as $candidate ) {
			if ( ! empty( $candidate['prefix'] ) && ( $relative === $candidate['prefix'] || 0 === strpos( $relative, $candidate['prefix'] . '/' ) ) ) {
				$relative = ltrim( substr( $relative, strlen( $candidate['prefix'] ) ), '/' ); break;
			}
		}
		if ( $this->is_system_path( $relative ) ) return $url;
		$new_path = $home_path . ( ! empty( $language['prefix'] ) ? trailingslashit( $language['prefix'] ) : '' ) . $relative;
		$url = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//' ) . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . '/' . ltrim( $new_path, '/' );
		if ( isset( $parts['query'] ) ) $url .= '?' . $parts['query'];
		if ( isset( $parts['fragment'] ) ) $url .= '#' . $parts['fragment'];
		if ( $root_relative ) {
			$localized = wp_parse_url( $url );
			return ( isset( $localized['path'] ) ? $localized['path'] : '/' ) . ( isset( $localized['query'] ) ? '?' . $localized['query'] : '' ) . ( isset( $localized['fragment'] ) ? '#' . $localized['fragment'] : '' );
		}
		return $url;
	}

	public function localize_url( $url ) { return $this->is_translated() ? $this->url_for_language( $url, $this->language ) : $url; }
	public function localize_footer_menu_link( $atts, $item, $args, $depth ) {
		$location = is_object( $args ) && isset( $args->theme_location ) ? $args->theme_location : '';
		if ( $this->is_translated() && in_array( $location, array( 'footer_menu', 'guidance' ), true ) && isset( $atts['href'] ) ) $atts['href'] = $this->url_for_language( $atts['href'], $this->language );
		return $atts;
	}

	private function is_eligible_request() {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return false;
		if ( isset( $_GET['rest_route'] ) ) return false;
		return true;
	}

	private function home_path() {
		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return $this->normalize_home_path( is_string( $path ) ? $path : '/' );
	}

	private function normalize_home_path( $path ) {
		$path = '/' . trim( (string) $path, '/' );
		return '/' === $path ? '/' : trailingslashit( $path );
	}

	private function path_belongs_to_home( $path, $home_path ) {
		$base = untrailingslashit( $home_path );
		if ( '' === $base ) return 0 === strpos( $path, '/' );
		return $path === $base || 0 === strpos( $path, $base . '/' );
	}

	private function relative_path( $path, $home_path ) {
		$base = untrailingslashit( $home_path );
		return ltrim( '' === $base ? $path : substr( $path, strlen( $base ) ), '/' );
	}

	private function is_system_path( $relative ) {
		$relative = ltrim( strtolower( (string) $relative ), '/' );
		$first = strtok( $relative, '/' );
		$blocked = array( 'wp-admin', 'wp-includes', 'wp-content', 'wp-json', 'wp-cron.php', 'xmlrpc.php', 'wp-login.php', 'wp-signup.php', 'wp-activate.php', 'wp-comments-post.php', 'wp-trackback.php', 'robots.txt', 'favicon.ico' );
		if ( in_array( $first, $blocked, true ) ) return true;
		return (bool) preg_match( '/\.(?:css|js|map|jpe?g|png|gif|webp|avif|svg|ico|woff2?|ttf|eot|xml|json|txt)$/i', $relative );
	}

	private function is_local_frontend_url( $url ) {
		$parts = wp_parse_url( $url ); $home = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $home['host'] ) || strtolower( $parts['host'] ) !== strtolower( $home['host'] ) ) return false;
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$home_path = isset( $home['path'] ) ? $this->normalize_home_path( $home['path'] ) : '/';
		if ( ! $this->path_belongs_to_home( $path, $home_path ) ) return false;
		$relative = $this->relative_path( $path, $home_path );
		foreach ( $this->registry->all() as $language ) {
			if ( ! empty( $language['prefix'] ) && ( $relative === $language['prefix'] || 0 === strpos( $relative, $language['prefix'] . '/' ) ) ) { $relative = ltrim( substr( $relative, strlen( $language['prefix'] ) ), '/' ); break; }
		}
		return ! $this->is_system_path( $relative );
	}

	private function urls_match( $first, $second ) {
		if ( ! is_string( $second ) || '' === $second ) return false;
		return html_entity_decode( strtok( $first, '#' ), ENT_QUOTES ) === html_entity_decode( strtok( $second, '#' ), ENT_QUOTES );
	}
}
