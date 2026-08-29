<?php
defined( 'ABSPATH' ) || exit;

/** Read-only translation coverage calculations for admin interfaces. */
final class Pera_ML_Translation_Status {
	private $storage;
	private $results = array();

	public function __construct( $storage ) { $this->storage = $storage; }

	/**
	 * Warm status results for several objects and languages with one translation query.
	 * WordPress' post/meta caches are used for canonical source values.
	 */
	public function preload( array $object_ids, array $languages, $post_type = 'post' ) {
		global $wpdb;
		$object_ids = array_values( array_filter( array_unique( array_map( 'absint', $object_ids ) ) ) );
		$languages = array_values( array_filter( array_unique( array_map( 'sanitize_key', $languages ) ) ) );
		if ( ! $object_ids || ! $languages ) return;

		update_meta_cache( 'post', $object_ids );
		$id_placeholders = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );
		$lang_placeholders = implode( ',', array_fill( 0, count( $languages ), '%s' ) );
		$table = $wpdb->prefix . 'pera_ml_translations';
		$args = array_merge( array( 'post' ), $object_ids, $languages );
		$sql = "SELECT object_id,field_key,language,source_hash,translated_text,status FROM {$table} WHERE object_type=%s AND object_id IN ({$id_placeholders}) AND language IN ({$lang_placeholders})";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		$indexed = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$key = (int) $row['object_id'] . '|' . sanitize_key( $row['language'] );
			$indexed[ $key ][ $row['field_key'] ] = $row;
		}
		foreach ( $object_ids as $object_id ) foreach ( $languages as $language ) {
			$row_key = $object_id . '|' . $language;
			$key = $object_id . '|' . $language . '|' . sanitize_key( $post_type );
			$this->results[ $key ] = $this->calculate( $object_id, $language, isset( $indexed[ $row_key ] ) ? $indexed[ $row_key ] : array(), $post_type );
		}
	}

	public function get( $object_id, $language, $post_type = 'post' ) {
		$key = absint( $object_id ) . '|' . sanitize_key( $language ) . '|' . sanitize_key( $post_type );
		if ( ! isset( $this->results[ $key ] ) ) $this->preload( array( $object_id ), array( $language ), $post_type );
		return isset( $this->results[ $key ] ) ? $this->results[ $key ] : $this->empty_result();
	}

	/** Canonical English fields which are eligible for translation. */
	public function applicable_sources( $object_id, $post_type = 'post' ) {
		$post = get_post( $object_id );
		if ( ! $post ) return array();
		$definitions = array(
			'page' => array( 'post_content' => false, 'post_title' => false, 'post_excerpt' => true, 'meta:seo_title' => true, 'meta:seo_meta_description' => true, 'meta:seo_faq_v2' => true ),
			'post' => array( 'post_content' => false, 'post_title' => false, 'post_excerpt' => true, 'meta:seo_title' => true, 'meta:seo_meta_description' => true, 'meta:seo_faq_v2' => true ),
			'property' => array( 'post_title' => true, 'post_content' => true, 'post_excerpt' => true ),
		);
		foreach ( array( 'page', 'post', 'property' ) as $type ) {
			foreach ( ( new Pera_ML_Fields( null, null, null ) )->approved( $type ) as $field ) $definitions[ $type ][ 'meta:' . $field ] = true;
		}
		$definitions = apply_filters( 'pera_ml_status_field_definitions', $definitions, $post_type );
		$fields = isset( $definitions[ $post_type ] ) ? $definitions[ $post_type ] : array();
		$sources = array();
		foreach ( $fields as $field => $optional ) {
			$source = 0 === strpos( $field, 'meta:' ) ? get_post_meta( $object_id, substr( $field, 5 ), true ) : $post->$field;
			if ( ! $optional || ( is_string( $source ) && '' !== trim( $source ) ) ) $sources[ $field ] = (string) $source;
		}
		return $sources;
	}

	/** Forget request-local results after a field is stored. */
	public function invalidate( $object_id, $language = '' ) {
		$prefix = absint( $object_id ) . '|';
		$language_prefix = $language ? $prefix . sanitize_key( $language ) . '|' : $prefix;
		foreach ( array_keys( $this->results ) as $key ) if ( 0 === strpos( $key, $language_prefix ) ) unset( $this->results[ $key ] );
	}

	private function calculate( $object_id, $language, array $rows, $post_type ) {
		$sources = $this->applicable_sources( $object_id, $post_type );
		$result = $this->empty_result();
		$result['applicable'] = count( $sources );
		foreach ( $sources as $field => $source ) {
			$row = isset( $rows[ $field ] ) ? $rows[ $field ] : null;
			$legacy = sanitize_key( $field );
			if ( ! $row && $legacy !== $field && isset( $rows[ $legacy ] ) ) $row = $rows[ $legacy ];
			if ( ! $row || '' === trim( (string) $row['translated_text'] ) ) { $result['missing'][] = $field; continue; }
			$is_stale = 'stale' === $row['status'] || ! hash_equals( (string) $row['source_hash'], hash( 'sha256', (string) $source ) );
			if ( $is_stale ) $result['stale'][] = $field; else $result['current']++;
		}
		$result['existing'] = $result['current'] + count( $result['stale'] );
		$result['complete'] = $result['applicable'] > 0 && $result['current'] === $result['applicable'];
		return $result;
	}

	private function empty_result() { return array( 'applicable' => 0, 'current' => 0, 'existing' => 0, 'stale' => array(), 'missing' => array(), 'complete' => false ); }
}
