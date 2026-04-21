<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_is_likely_bot_ua' ) ) {
	function pera_analytics_is_likely_bot_ua( string $user_agent ): bool {
		$user_agent = strtolower( trim( $user_agent ) );

		if ( '' === $user_agent ) {
			return true;
		}

		$patterns = apply_filters(
			'pera_analytics_bot_patterns',
			array(
				'bot',
				'spider',
				'crawler',
				'preview',
				'headless',
				'slurp',
				'curl',
				'wget',
				'python-requests',
				'node-fetch',
				'go-http-client',
				'facebookexternalhit',
				'slackbot',
				'linkedinbot',
				'whatsapp',
				'twitterbot',
				'pingdom',
				'uptimerobot',
				'newrelic',
				'datadog',
				'statuscake',
			)
		);

		foreach ( $patterns as $pattern ) {
			if ( '' !== $pattern && strpos( $user_agent, strtolower( (string) $pattern ) ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
