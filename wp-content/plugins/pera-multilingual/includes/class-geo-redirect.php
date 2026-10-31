<?php
defined( 'ABSPATH' ) || exit;

/** Optional request negotiation; translation, routing and SEO remain owned by their existing services. */
final class Pera_ML_Geo_Redirect {
	const COOKIE = 'pera_ml_language';
	const SELECTION = 'pera_ml_select';
	private $registry;
	private $router;
	private $target = '';
	private $manual = false;
	private $rewritten = false;

	public function __construct( $registry, $router ) { $this->registry = $registry; $this->router = $router; }

	public static function enabled() {
		return defined( 'PERA_ML_GEO_REDIRECT_ENABLED' ) && true === PERA_ML_GEO_REDIRECT_ENABLED
			&& defined( 'PERA_ML_GEO_CACHE_READY' ) && true === PERA_ML_GEO_CACHE_READY;
	}

	public function hooks() {
		if ( ! self::enabled() ) return;
		add_action( 'send_headers', array( $this, 'register_cache_headers' ), PHP_INT_MAX );
		add_action( 'template_redirect', array( $this, 'cache_headers' ), PHP_INT_MAX );
		if ( class_exists( 'Pera_ML_Crawler_Detection' ) ) ( new Pera_ML_Crawler_Detection() )->hooks();
		add_filter( 'pera_ml_switcher_url', array( $this, 'switcher_url' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'prepare' ), -100 );
		// Let existing canonical/filter normalization redirects run first and combine their destination.
		add_filter( 'wp_redirect', array( $this, 'combine_redirect' ), 99, 2 );
		add_filter( 'wp_redirect_status', array( $this, 'redirect_status' ), 99, 2 );
		add_action( 'template_redirect', array( $this, 'redirect' ), 11 );
	}

	public function switcher_url( $url, $code ) {
		return self::enabled() && $this->language( $code ) ? add_query_arg( self::SELECTION, $code, $url ) : $url;
	}

	/** Executed after the router restores the public URI and WP resolves the content. */
	public function prepare() {
		$this->target = ''; $this->manual = false; $this->rewritten = false;
		if ( ! self::enabled() || ! $this->eligible() || headers_sent() ) return;
		$this->cache_headers();

		if ( 'bot' === $this->audience() ) return;
		$selection = isset( $_GET[ self::SELECTION ] ) ? wp_unslash( $_GET[ self::SELECTION ] ) : '';
		if ( $this->language( $selection ) && $selection === $this->router->current_language() ) {
			$this->manual = true;
			$this->target = $selection;
			// Never issue an insecure preference cookie. Production must use HTTPS.
			if ( is_ssl() ) setcookie( self::COOKIE, $selection, $this->cookie_options() );
			return;
		}
		// A trusted audience verdict or the configured browser policy may negotiate.
		// Browser hints are not identity proof; unknown non-browser traffic bypasses.
		if ( ! in_array( $this->audience(), array( 'human', 'visitor' ), true ) ) return;
		$preference = isset( $_COOKIE[ self::COOKIE ] ) ? wp_unslash( $_COOKIE[ self::COOKIE ] ) : '';
		if ( $this->language( $preference ) ) { $this->target = $preference; return; }
		$country = $this->country();
		if ( '' === $country ) return; // No reliable GeoIP: English default, without forced navigation.
		$mapping = apply_filters( 'pera_ml_country_languages', require dirname( __DIR__ ) . '/config/country-languages.php' );
		$code = is_array( $mapping ) && isset( $mapping[ $country ] ) ? $mapping[ $country ] : 'en';
		$this->target = $this->language( $code ) ? $code : 'en';
	}

	public function cookie_options() {
		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return array( 'expires' => time() + 365 * 24 * 60 * 60, 'path' => $path ? trailingslashit( $path ) : '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax' );
	}

	public function audience() {
		$key = defined( 'PERA_ML_GEO_AUDIENCE_SERVER_VAR' ) ? PERA_ML_GEO_AUDIENCE_SERVER_VAR : 'PERA_ML_VERIFIED_AUDIENCE';
		$trusted = $this->server_value( $key );
		if ( in_array( $trusted, array( 'human', 'bot' ), true ) ) return $trusted;
		return class_exists( 'Pera_ML_Crawler_Detection' ) ? ( new Pera_ML_Crawler_Detection() )->audience() : '';
	}

	public function register_cache_headers() {
		if ( ! self::enabled() || ! $this->eligible() || headers_sent() ) return;
		$this->cache_headers();
		// The final PHP header boundary also catches later template/plugin header writes.
		header_register_callback( array( $this, 'cache_headers' ) );
	}

	public function cache_headers() {
		if ( ! self::enabled() || ! $this->eligible() || headers_sent() ) return;
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
		nocache_headers();
		header_remove( 'ETag' );
		header_remove( 'Last-Modified' );
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT', true );
		header( 'Pragma: no-cache', true );
	}

	private function server_value( $key ) {
		// A non-HTTP server parameter must be set/overwritten by the host, never copied unchecked from headers.
		if ( ! is_string( $key ) || ! preg_match( '/^[A-Z][A-Z0-9_]*$/D', $key ) || 0 === strpos( $key, 'HTTP_' ) ) return '';
		return isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ? $_SERVER[ $key ] : '';
	}

	public function country() {
		$source = defined( 'PERA_ML_GEO_COUNTRY_SOURCE' ) ? PERA_ML_GEO_COUNTRY_SOURCE : '';
		if ( in_array( $source, array( 'mmdb', 'auto' ), true ) && class_exists( 'Pera_ML_Local_GeoIP' ) ) {
			if ( 'auto' === $source ) {
				$key = defined( 'PERA_ML_GEO_COUNTRY_SERVER_VAR' ) ? PERA_ML_GEO_COUNTRY_SERVER_VAR : 'GEOIP_COUNTRY_CODE';
				$server = $this->valid_country( $this->server_value( $key ) );
				if ( '' !== $server ) return $server;
			}
			return $this->valid_country( ( new Pera_ML_Local_GeoIP() )->country() );
		}
		$country = '';
		if ( 'server' === $source ) {
			$key = defined( 'PERA_ML_GEO_COUNTRY_SERVER_VAR' ) ? PERA_ML_GEO_COUNTRY_SERVER_VAR : 'GEOIP_COUNTRY_CODE';
			$country = $this->server_value( $key );
		} elseif ( 'cloudflare' === $source ) {
			$peer = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
			$ranges = defined( 'PERA_ML_GEO_TRUSTED_PROXY_CIDRS' ) ? PERA_ML_GEO_TRUSTED_PROXY_CIDRS : array();
			foreach ( is_array( $ranges ) ? $ranges : array() as $range ) {
				if ( self::in_cidr( $peer, $range ) ) {
					$country = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : '';
					break;
				}
			}
		}
		return $this->valid_country( $country );
	}

	private function valid_country( $country ) {
		if ( ! is_string( $country ) ) return '';
		$country = strtoupper( $country );
		// Cloudflare's XX (unknown) and T1 (Tor) are not country results.
		$iso = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';
		return in_array( $country, explode( ' ', $iso ), true ) ? $country : '';
	}

	public static function in_cidr( $ip, $cidr ) {
		if ( ! is_string( $ip ) || ! is_string( $cidr ) ) return false;
		$parts = explode( '/', $cidr );
		if ( count( $parts ) > 2 ) return false;
		$address = @inet_pton( $ip ); $network = @inet_pton( $parts[0] );
		if ( false === $address || false === $network || strlen( $address ) !== strlen( $network ) ) return false;
		$bits = isset( $parts[1] ) ? $parts[1] : (string) ( strlen( $address ) * 8 );
		if ( ! ctype_digit( $bits ) || (int) $bits > strlen( $address ) * 8 ) return false;
		$bytes = intdiv( (int) $bits, 8 ); $remainder = (int) $bits % 8;
		if ( substr( $address, 0, $bytes ) !== substr( $network, 0, $bytes ) ) return false;
		return 0 === $remainder || ( ord( $address[ $bytes ] ) & ( 255 << ( 8 - $remainder ) ) ) === ( ord( $network[ $bytes ] ) & ( 255 << ( 8 - $remainder ) ) );
	}

	private function language( $code ) {
		if ( ! is_string( $code ) ) return false;
		$public = $this->registry->publicly_available();
		return isset( $public[ $code ] );
	}

	private function eligible() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] || is_admin() || wp_doing_ajax() || wp_doing_cron() ) return false;
		foreach ( array( 'REST_REQUEST', 'XMLRPC_REQUEST' ) as $flag ) if ( defined( $flag ) && constant( $flag ) ) return false;
		if ( is_feed() || is_robots() || is_trackback() || is_embed() || is_preview() || is_404() ) return false;
		foreach ( array( 'rest_route', 'feed', 'sitemap', 'sitemap_n', 'sitemap-subtype' ) as $key ) if ( isset( $_GET[ $key ] ) || get_query_var( $key ) ) return false;
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( ! is_string( $uri ) || '/' !== substr( $uri, 0, 1 ) || '//' === substr( $uri, 0, 2 ) || preg_match( '/[\x00-\x20\x7f\\\\]/', $uri ) ) return false;
		// Ask the existing resolver whether this is a local frontend route. A known
		// alternate language must actually change the path (system URLs stay unchanged).
		$frontend = false;
		foreach ( $this->registry->enabled() as $code => $language ) {
			if ( $code !== $this->router->current_language() && $this->router->url_for_language( $uri, $code ) !== $uri ) { $frontend = true; break; }
		}
		if ( ! $frontend ) return false;
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( preg_match( '#(?:^|/)(?:feed|sitemap[^/]*|wp-sitemap[^/]*)(?:/|$)|\.(?:xml|xsl|txt)(?:/|$)#i', $path ) ) return false;
		return is_singular( array( 'post', 'page', 'property' ) ) || is_archive() || is_search() || is_home() || is_front_page();
	}

	private function request_url() {
		$home = wp_parse_url( home_url( '/' ) );
		return $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . wp_unslash( $_SERVER['REQUEST_URI'] );
	}

	/** No translation existence test: the existing renderer falls back per field on the SAME object. */
	public function destination() {
		if ( '' === $this->target ) return '';
		$url = $this->request_url();
		$destination = $this->router->url_for_language( $url, $this->target );
		if ( $this->manual ) $destination = remove_query_arg( self::SELECTION, $destination );
		return $destination !== $url ? $destination : '';
	}

	public function combine_redirect( $location, $status ) {
		$this->rewritten = false;
		if ( '' === $this->target || ! is_string( $location ) || ! in_array( $status, array( 301, 302 ), true ) ) return $location;
		$home = wp_parse_url( home_url( '/' ) ); $parts = wp_parse_url( $location );
		if ( empty( $parts['host'] ) || strtolower( $parts['host'] ) !== strtolower( $home['host'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) return $location;
		// Only combine same-content slash/query normalization; leave authentication/external navigation alone.
		$requested_path = wp_parse_url( $this->router->url_for_language( $this->request_url(), 'en' ), PHP_URL_PATH );
		$redirect_path = wp_parse_url( $this->router->url_for_language( $location, 'en' ), PHP_URL_PATH );
		if ( untrailingslashit( $requested_path ) !== untrailingslashit( $redirect_path ) ) return $location;
		$result = $this->router->url_for_language( $location, $this->target );
		if ( $this->manual ) $result = remove_query_arg( self::SELECTION, $result );
		$this->rewritten = $result !== $location || $this->manual;
		return $result;
	}

	public function redirect_status( $status, $location ) { return $this->rewritten ? 302 : $status; }

	public function redirect() {
		$destination = $this->destination();
		if ( '' !== $destination && wp_safe_redirect( $destination, 302, 'Pera Multilingual language preference' ) ) exit;
	}
}
