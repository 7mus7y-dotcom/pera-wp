<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/source-classification.php';

if ( ! function_exists( 'pera_analytics_should_track_frontend' ) ) {
	function pera_analytics_should_track_frontend(): bool {
		if ( is_admin() || is_user_logged_in() ) {
			return false;
		}

		if ( is_feed() || is_preview() || is_search() || is_trackback() || is_robots() ) {
			return false;
		}

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'GET' !== $request_method ) {
			return false;
		}

		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path = is_string( $request_path ) ? $request_path : '';

		if ( '' !== $request_path && pera_analytics_should_skip_path( $request_path ) ) {
			return false;
		}

		return true;
	}
}


if ( ! function_exists( 'pera_analytics_should_skip_path' ) ) {
	function pera_analytics_should_skip_path( string $path ): bool {
		$normalized = '/' . ltrim( trim( $path ), '/' );

		$skip_prefixes = array(
			'/wp-admin',
			'/wp-login.php',
			'/wp-json',
			'/feed',
			'/crm/',
		);

		foreach ( $skip_prefixes as $prefix ) {
			if ( strpos( $normalized, $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'pera_analytics_enqueue_tracker' ) ) {
	function pera_analytics_enqueue_tracker(): void {
		if ( ! pera_analytics_should_track_frontend() ) {
			return;
		}

		wp_enqueue_script(
			'pera-page-visit-tracker',
			get_stylesheet_directory_uri() . '/js/page-visit-tracker.js',
			array(),
			pera_get_asset_version( '/js/page-visit-tracker.js' ),
			true
		);

		wp_localize_script(
			'pera-page-visit-tracker',
			'peraVisitTracker',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => 'pera_track_page_visit',
				'postId'   => is_singular() ? get_the_ID() : 0,
				'postType' => is_singular() ? get_post_type( get_the_ID() ) : '',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'pera_analytics_enqueue_tracker', 30 );

if ( ! function_exists( 'pera_analytics_get_or_set_visitor_id' ) ) {
	function pera_analytics_get_or_set_visitor_id(): string {
		$cookie_name = 'pera_vid';
		$visitor_id  = isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ $cookie_name ] ) ) : '';

		if ( '' === $visitor_id || strlen( $visitor_id ) < 20 ) {
			$visitor_id = wp_generate_uuid4();
			setcookie(
				$cookie_name,
				$visitor_id,
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => COOKIEPATH ?: '/',
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
			$_COOKIE[ $cookie_name ] = $visitor_id;
		}

		return $visitor_id;
	}
}



if ( ! function_exists( 'pera_analytics_country_from_code' ) ) {
	function pera_analytics_country_from_code( string $country_code, string $country_name = '' ): array {
		$country_code = strtoupper( sanitize_text_field( $country_code ) );

		if ( ! preg_match( '/^[A-Z]{2}$/', $country_code ) || 'XX' === $country_code ) {
			return array(
				'country_code' => 'XX',
				'country_name' => 'Unknown',
			);
		}

		$resolved_name = '' !== $country_name ? $country_name : $country_code;
		if ( '' === $country_name && function_exists( 'locale_get_display_region' ) ) {
			$display_name = locale_get_display_region( '-' . $country_code, get_locale() );
			if ( is_string( $display_name ) && '' !== $display_name ) {
				$resolved_name = $display_name;
			}
		}

		return array(
			'country_code' => $country_code,
			'country_name' => function_exists( 'mb_substr' ) ? mb_substr( $resolved_name, 0, 100 ) : substr( $resolved_name, 0, 100 ),
		);
	}
}

if ( ! function_exists( 'pera_analytics_get_request_ip' ) ) {
	function pera_analytics_get_request_ip(): string {
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_TRUE_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			$value = isset( $_SERVER[ $header ] ) ? (string) wp_unslash( $_SERVER[ $header ] ) : '';
			if ( '' === $value ) {
				continue;
			}

			foreach ( explode( ',', $value ) as $candidate ) {
				$ip = trim( $candidate );
				if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '';
	}
}

if ( ! function_exists( 'pera_analytics_geoip_database_paths' ) ) {
	function pera_analytics_geoip_database_paths(): array {
		$paths = array(
			WP_CONTENT_DIR . '/uploads/GeoLite2-Country.mmdb',
			WP_CONTENT_DIR . '/GeoLite2-Country.mmdb',
			ABSPATH . 'GeoLite2-Country.mmdb',
			'/usr/share/GeoIP/GeoLite2-Country.mmdb',
			'/usr/local/share/GeoIP/GeoLite2-Country.mmdb',
			'/var/lib/GeoIP/GeoLite2-Country.mmdb',
		);

		/**
		 * Allows hosts to point analytics country detection at an existing local
		 * MaxMind GeoLite2 Country database without making per-visit API calls.
		 */
		$paths = apply_filters( 'pera_analytics_geoip_database_paths', $paths );

		return array_values( array_filter( array_unique( array_map( 'strval', (array) $paths ) ) ) );
	}
}


if ( ! function_exists( 'pera_analytics_maybe_load_geoip_reader' ) ) {
	function pera_analytics_maybe_load_geoip_reader(): bool {
		if ( class_exists( '\GeoIp2\Database\Reader' ) ) {
			return true;
		}

		$autoload_paths = array(
			get_stylesheet_directory() . '/vendor/autoload.php',
			WP_CONTENT_DIR . '/vendor/autoload.php',
			ABSPATH . 'vendor/autoload.php',
		);

		/**
		 * Allows hosts to expose an existing Composer autoloader that contains the
		 * MaxMind GeoIP2 Reader package; this still uses a local database only.
		 */
		$autoload_paths = apply_filters( 'pera_analytics_geoip_autoload_paths', $autoload_paths );

		foreach ( array_filter( array_unique( array_map( 'strval', (array) $autoload_paths ) ) ) as $autoload_path ) {
			if ( is_readable( $autoload_path ) ) {
				require_once $autoload_path;
			}

			if ( class_exists( '\GeoIp2\Database\Reader' ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'pera_analytics_get_country_from_local_geoip' ) ) {
	function pera_analytics_get_country_from_local_geoip( string $ip ): array {
		if ( '' === $ip ) {
			return array();
		}

		if ( pera_analytics_maybe_load_geoip_reader() ) {
			foreach ( pera_analytics_geoip_database_paths() as $database_path ) {
				if ( ! is_readable( $database_path ) ) {
					continue;
				}

				try {
					$reader       = new \GeoIp2\Database\Reader( $database_path );
					$record       = $reader->country( $ip );
					$country_code = isset( $record->country->isoCode ) ? (string) $record->country->isoCode : '';
					$locale       = function_exists( 'get_locale' ) ? substr( (string) get_locale(), 0, 2 ) : 'en';
					$country_name = isset( $record->country->names[ $locale ] ) ? (string) $record->country->names[ $locale ] : ( isset( $record->country->name ) ? (string) $record->country->name : '' );
					$reader->close();

					if ( '' !== $country_code ) {
						return pera_analytics_country_from_code( $country_code, $country_name );
					}
				} catch ( Throwable $e ) {
					continue;
				}
			}
		}

		if ( function_exists( 'geoip_country_code_by_name' ) ) {
			$country_code = geoip_country_code_by_name( $ip );
			if ( is_string( $country_code ) && '' !== $country_code ) {
				return pera_analytics_country_from_code( $country_code );
			}
		}

		return array();
	}
}

if ( ! function_exists( 'pera_analytics_get_request_country' ) ) {
	function pera_analytics_get_request_country(): array {
		$cloudflare_country_code = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) ) : '';

		if ( preg_match( '/^[A-Z]{2}$/', $cloudflare_country_code ) && 'XX' !== $cloudflare_country_code ) {
			return pera_analytics_country_from_code( $cloudflare_country_code );
		}

		$local_geoip_country = pera_analytics_get_country_from_local_geoip( pera_analytics_get_request_ip() );
		if ( ! empty( $local_geoip_country ) ) {
			return $local_geoip_country;
		}

		// Historical visits recorded before local GeoIP support was added remain
		// XX / Unknown; only new visits can be enriched from Cloudflare or GeoIP.
		return array(
			'country_code' => 'XX',
			'country_name' => 'Unknown',
		);
	}
}

if ( ! function_exists( 'pera_analytics_handle_track_request' ) ) {
	function pera_analytics_handle_track_request(): void {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $request_method ) {
			wp_send_json_error( array( 'message' => 'Invalid request method' ), 405 );
		}

		if ( is_user_logged_in() ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}

		$origin      = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_ORIGIN'] ) ) : '';
		$site_host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$origin_host = $origin ? wp_parse_url( $origin, PHP_URL_HOST ) : '';
		if ( $origin_host && $site_host && strtolower( (string) $origin_host ) !== strtolower( (string) $site_host ) ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( pera_analytics_is_likely_bot_ua( $user_agent ) ) {
			wp_send_json_success( array( 'tracked' => false, 'reason' => 'bot' ) );
		}

		$page_path = isset( $_POST['page_path'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['page_path'] ) ) : '';
		$page_url  = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['page_url'] ) ) : '';
		$page_title= isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['page_title'] ) ) : '';
		$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['post_type'] ) ) : '';
		$referer   = '';

		if ( isset( $_POST['referer'] ) ) {
			$referer = esc_url_raw( wp_unslash( (string) $_POST['referer'] ) );
		} elseif ( isset( $_POST['referrer'] ) ) {
			$referer = esc_url_raw( wp_unslash( (string) $_POST['referrer'] ) );
		}

		if ( '' === $referer && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) );
		}
		$source    = pera_analytics_classify_referer_source( $referer, pera_analytics_normalize_host( $site_host ) );
		$country   = pera_analytics_get_request_country();

		if ( '' === $page_path || 0 !== strpos( $page_path, '/' ) || pera_analytics_should_skip_path( $page_path ) ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}

		global $wpdb;
		$raw_table = pera_analytics_raw_table_name();
		$visitor_id = pera_analytics_get_or_set_visitor_id();

		$wpdb->insert(
			$raw_table,
			array(
				'visited_at'      => current_time( 'mysql' ),
				'visitor_id'      => $visitor_id,
				'page_url'        => $page_url,
				'page_path'       => $page_path,
				'page_title'      => function_exists( 'mb_substr' ) ? mb_substr( $page_title, 0, 255 ) : substr( $page_title, 0, 255 ),
				'post_id'         => $post_id > 0 ? $post_id : 0,
				'post_type'       => '' !== $post_type ? $post_type : '',
				'referer'         => $referer,
				'referer_host'    => $source['referer_host'],
				'source_type'     => $source['source_type'],
				'is_internal'     => $source['is_internal'],
				'is_direct'       => $source['is_direct'],
				'country_code'    => $country['country_code'],
				'country_name'    => $country['country_name'],
				'user_agent_hash' => hash( 'sha256', $user_agent ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		wp_send_json_success( array( 'tracked' => true ) );
	}
}
add_action( 'wp_ajax_nopriv_pera_track_page_visit', 'pera_analytics_handle_track_request' );
