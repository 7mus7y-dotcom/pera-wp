<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect blocked keyword spam across enquiry form pipelines.
 */
function pera_contains_blocked_keywords( string $text ): bool {
	$blocked_keywords = array(
		'crypto',
		'cryptocurrency',
		'bitcoin',
		'btc',
		'usdt',
		'usdc',
		'ethereum',
		'blockchain',
		'binance',
		'tron',
		'trx',
		'wallet',
		'web3',
		'nft',
		'tether',
		'forex',
		'trading signal',
		'investment platform',
		'passive income',
	);

	$text = strtolower( wp_strip_all_tags( $text ) );
	$text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

	if ( $text == '' ) {
		return false;
	}

	foreach ( $blocked_keywords as $keyword ) {
		$normalized_keyword = strtolower( (string) $keyword );
		$normalized_keyword = preg_replace( '/[^a-z0-9]+/', ' ', $normalized_keyword );
		$normalized_keyword = trim( preg_replace( '/\s+/', ' ', (string) $normalized_keyword ) );

		if ( $normalized_keyword !== '' && false !== strpos( $text, $normalized_keyword ) ) {
			return true;
		}
	}

	return false;
}
