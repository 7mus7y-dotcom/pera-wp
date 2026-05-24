<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_normalize_host' ) ) {
	function pera_analytics_normalize_host( $host ): string {
		$normalized = strtolower( trim( (string) $host ) );
		if ( '' === $normalized ) {
			return '';
		}

		if ( strpos( $normalized, 'www.' ) === 0 ) {
			$normalized = substr( $normalized, 4 );
		}

		return $normalized;
	}
}

if ( ! function_exists( 'pera_analytics_classify_referer_source' ) ) {
	function pera_analytics_classify_referer_source( string $referer, string $site_host = '' ): array {
		$site_host    = '' !== $site_host ? $site_host : pera_analytics_normalize_host( wp_parse_url( home_url(), PHP_URL_HOST ) );
		$referer_host = '';
		if ( '' !== $referer ) {
			$parsed_host = wp_parse_url( $referer, PHP_URL_HOST );
			if ( is_string( $parsed_host ) ) {
				$referer_host = pera_analytics_normalize_host( $parsed_host );
			}
		}

		if ( '' === $referer_host ) {
			return array(
				'referer_host' => null,
				'source_type'  => 'direct',
				'is_internal'  => 0,
				'is_direct'    => 1,
			);
		}

		if ( '' !== $site_host && $referer_host === $site_host ) {
			return array(
				'referer_host' => $referer_host,
				'source_type'  => 'internal',
				'is_internal'  => 1,
				'is_direct'    => 0,
			);
		}

		$search_hosts = array( 'google.', 'bing.com', 'yahoo.', 'duckduckgo.com', 'yandex.' );
		foreach ( $search_hosts as $needle ) {
			if ( false !== strpos( $referer_host, $needle ) ) {
				return array(
					'referer_host' => $referer_host,
					'source_type'  => 'organic_search',
					'is_internal'  => 0,
					'is_direct'    => 0,
				);
			}
		}

		$social_hosts = array( 'facebook.com', 'instagram.com', 'linkedin.com', 'twitter.com', 'x.com', 'pinterest.', 'reddit.com', 'tiktok.com', 'youtube.com', 'youtu.be' );
		foreach ( $social_hosts as $needle ) {
			if ( false !== strpos( $referer_host, $needle ) ) {
				return array(
					'referer_host' => $referer_host,
					'source_type'  => 'social',
					'is_internal'  => 0,
					'is_direct'    => 0,
				);
			}
		}

		return array(
			'referer_host' => $referer_host,
			'source_type'  => 'referral',
			'is_internal'  => 0,
			'is_direct'    => 0,
		);
	}
}
