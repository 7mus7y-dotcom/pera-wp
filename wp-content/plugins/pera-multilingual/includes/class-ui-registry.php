<?php
defined( 'ABSPATH' ) || exit;

/** Persistent catalogue of UI strings explicitly encountered through pera_ml_ui(). */
final class Pera_ML_UI_Registry {
	const OPTION = 'pera_ml_ui_registry';

	/** Register canonical copy without creating or changing any translation row. */
	public function register( $source, $key = '' ) {
		$source = (string) $source;
		if ( '' === $source ) return null;
		$identity = Pera_ML_UI::identity( $source, $key );
		$semantic_key = '' === trim( (string) $key ) ? $identity : trim( (string) $key );
		$items = $this->all();
		$now = current_time( 'mysql', true );
		$existing = isset( $items[ $identity ] ) && is_array( $items[ $identity ] ) ? $items[ $identity ] : array();
		$source_changed = ! isset( $existing['source_hash'] ) || ! hash_equals( (string) $existing['source_hash'], hash( 'sha256', $source ) );
		$last_seen_day = isset( $existing['last_seen'] ) ? substr( (string) $existing['last_seen'], 0, 10 ) : '';
		$now_day = substr( $now, 0, 10 );
		if ( empty( $existing ) || $source_changed || $last_seen_day !== $now_day ) {
			$items[ $identity ] = array(
				'identity' => $identity,
				'semantic_key' => $semantic_key,
				'source' => $source,
				'source_hash' => hash( 'sha256', $source ),
				'first_seen' => isset( $existing['first_seen'] ) ? $existing['first_seen'] : $now,
				'last_seen' => $now,
			);
			update_option( self::OPTION, $items, false );
		}
		return isset( $items[ $identity ] ) ? $items[ $identity ] : null;
	}

	/** @return array<string,array<string,string>> */
	public function all() {
		$items = get_option( self::OPTION, array() );
		if ( ! is_array( $items ) ) return array();
		ksort( $items, SORT_STRING );
		return $items;
	}

	public function find( $identity ) {
		$items = $this->all();
		return isset( $items[ $identity ] ) && is_array( $items[ $identity ] ) ? $items[ $identity ] : null;
	}
}
