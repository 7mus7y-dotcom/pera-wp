<?php
defined( 'ABSPATH' ) || exit;

/** Read-only site-wide inventory built exclusively from canonical approved fields and stored rows. */
final class Pera_ML_Translation_Health {
	private $status; private $storage; private $ui;
	const LANGUAGES = array( 'zh', 'ar', 'de' );
	public function __construct( $status, $storage, $ui ) { $this->status = $status; $this->storage = $storage; $this->ui = $ui; }

	public function inventory() {
		$rows = array(); $ui_items = $this->ui->inventory( self::LANGUAGES );
		foreach ( $ui_items as $identity => $item ) foreach ( self::LANGUAGES as $language ) $rows[] = $this->row( 'ui', Pera_ML_UI::object_id( $identity ), $item['semantic_key'], $identity, $language, $item['statuses'][ $language ] );
		$ids = get_posts( array( 'post_type' => array( 'post', 'page', 'property', 'team' ), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) );
		$archive_settings_id = Pera_ML_Fields::archive_settings_object_id();
		if ( $archive_settings_id && ! in_array( $archive_settings_id, $ids, true ) ) $ids[] = $archive_settings_id;
		$grouped = array( 'post' => array(), 'page' => array(), 'property' => array(), 'team' => array() );
		foreach ( $ids as $id ) { $post = get_post( $id ); if ( $post && isset( $grouped[ $post->post_type ] ) ) $grouped[ $post->post_type ][] = (int) $id; }
		foreach ( $grouped as $post_type => $post_ids ) if ( $post_ids ) $this->status->preload( $post_ids, self::LANGUAGES, $post_type );
		foreach ( $ids as $id ) {
			$post = get_post( $id ); if ( ! $post || ! isset( $grouped[ $post->post_type ] ) ) continue;
			$sources = array_filter( $this->status->applicable_sources( $id, $post->post_type ), static function ( $source ) { return is_string( $source ) && '' !== trim( $source ); } ); if ( ! $sources ) continue;
			foreach ( self::LANGUAGES as $language ) { $state = $this->status->get( $id, $language, $post->post_type ); foreach ( $sources as $field => $source ) { $status = in_array( $field, $state['missing'], true ) ? 'missing' : ( in_array( $field, $state['stale'], true ) ? 'stale' : 'current' ); $rows[] = $this->row( $post->post_type, $id, get_the_title( $id ), $field, $language, $status ); } }
		}
		foreach ( Pera_ML_Fields::supported_taxonomies() as $taxonomy ) {
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ); if ( is_wp_error( $terms ) ) continue;
			foreach ( $terms as $term ) foreach ( $this->term_sources( $term, $taxonomy ) as $field => $source ) foreach ( self::LANGUAGES as $language ) {
				$stored = $this->storage->get( 'term', $term->term_id, $field, $language, $source );
				$status = ! is_array( $stored ) || '' === trim( (string) $stored['translated_text'] ) ? 'missing' : ( ! empty( $stored['is_stale'] ) || ( isset( $stored['status'] ) && 'current' !== $stored['status'] ) ? 'stale' : 'current' );
				$rows[] = $this->row( 'taxonomy:' . $taxonomy, $term->term_id, $term->name, $field, $language, $status );
			}
		}
		return array( 'ui_total' => count( $ui_items ), 'rows' => $rows, 'counts' => $this->counts( $rows ) );
	}
	private function term_sources( $term, $taxonomy ) {
		$sources = array();
		foreach ( Pera_ML_Fields::taxonomy_fields( $taxonomy ) as $field ) {
			if ( 'term_name' === $field ) $value = (string) $term->name;
			elseif ( 'term_description' === $field ) $value = (string) $term->description;
			elseif ( 0 === strpos( $field, 'meta:' ) ) $value = get_term_meta( $term->term_id, substr( $field, 5 ), true );
			else continue;
			if ( is_string( $value ) && '' !== trim( $value ) ) $sources[ $field ] = $value;
		}
		return $sources;
	}
	private function row( $type, $id, $title, $field, $language, $status ) { return array( 'object_type' => $type, 'object_id' => (int) $id, 'title' => (string) $title, 'field' => $field, 'language' => $language, 'status' => $status ); }
	private function counts( $rows ) { $counts = array(); foreach ( $rows as $row ) { $group = 'ui' === $row['object_type'] ? 'ui' : ( 0 === strpos( $row['object_type'], 'taxonomy:' ) ? 'taxonomies' : 'content' ); if ( ! isset( $counts[ $group ][ $row['language'] ] ) ) $counts[ $group ][ $row['language'] ] = array( 'current' => 0, 'missing' => 0, 'stale' => 0 ); $counts[ $group ][ $row['language'] ][ $row['status'] ]++; } return $counts; }
}
