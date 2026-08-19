<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Storage {
	private $table;
	public function __construct() { global $wpdb; $this->table = $wpdb->prefix . 'pera_ml_translations'; }

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $wpdb->prefix . 'pera_ml_translations';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(32) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			field_key varchar(191) NOT NULL,
			language varchar(12) NOT NULL,
			source_hash char(64) NOT NULL,
			source_text longtext NOT NULL,
			translated_text longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'current',
			provider varchar(50) NOT NULL DEFAULT '',
			translated_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY object_field_language (object_type,object_id,field_key,language),
			KEY language_status (language,status),
			KEY source_hash (source_hash)
		) {$charset};" );
	}

	public function get( $object_type, $object_id, $field, $language, $source = '' ) {
		$key = $this->cache_key( $object_type, $object_id, $field, $language );
		$cached = wp_cache_get( $key, 'pera_ml' );
		if ( false === $cached ) {
			global $wpdb;
			$cached = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE object_type=%s AND object_id=%d AND field_key=%s AND language=%s", $object_type, $object_id, $field, $language ), ARRAY_A );
			wp_cache_set( $key, $cached ? $cached : array(), 'pera_ml' );
		}
		if ( empty( $cached ) ) return null;
		$cached['is_stale'] = '' !== $source && ! hash_equals( $cached['source_hash'], hash( 'sha256', $source ) );
		return $cached;
	}

	public function put( $object_type, $object_id, $field, $language, $source, $translation, $provider = 'manual' ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$data = array( 'object_type' => sanitize_key( $object_type ), 'object_id' => absint( $object_id ), 'field_key' => sanitize_key( $field ), 'language' => sanitize_key( $language ), 'source_hash' => hash( 'sha256', $source ), 'source_text' => $source, 'translated_text' => $translation, 'status' => 'current', 'provider' => sanitize_key( $provider ), 'translated_at' => $now, 'updated_at' => $now );
		$sql = "INSERT INTO {$this->table} (object_type,object_id,field_key,language,source_hash,source_text,translated_text,status,provider,translated_at,updated_at) VALUES (%s,%d,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE source_hash=VALUES(source_hash),source_text=VALUES(source_text),translated_text=VALUES(translated_text),status='current',provider=VALUES(provider),translated_at=VALUES(translated_at),updated_at=VALUES(updated_at)";
		$result = $wpdb->query( $wpdb->prepare( $sql, array_values( $data ) ) );
		wp_cache_delete( $this->cache_key( $object_type, $object_id, $field, $language ), 'pera_ml' );
		return false !== $result;
	}

	public function mark_object_stale( $object_type, $object_id ) {
		global $wpdb;
		$object_type = sanitize_key( $object_type );
		$object_id = absint( $object_id );
		if ( '' === $object_type || 0 === $object_id ) return false;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT field_key,language FROM {$this->table} WHERE object_type=%s AND object_id=%d", $object_type, $object_id ), ARRAY_A );
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->table} SET status='stale',updated_at=%s WHERE object_type=%s AND object_id=%d AND status<>'stale'", current_time( 'mysql', true ), $object_type, $object_id ) );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			wp_cache_delete( $this->cache_key( $object_type, $object_id, $row['field_key'], $row['language'] ), 'pera_ml' );
		}
		return $result;
	}

	private function cache_key( $type, $id, $field, $language ) { return md5( $type . '|' . $id . '|' . $field . '|' . $language ); }
}
