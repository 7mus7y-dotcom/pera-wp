<?php
defined( 'ABSPATH' ) || exit;

/** Translate exactly one server-approved health row; HTTP concerns remain in the admin adapter. */
final class Pera_ML_Translation_Health_Orchestrator {
	private $status; private $storage; private $translator; private $ui; private $ui_registry;
	private $taxonomies = array( 'district', 'region', 'property_type', 'property_tags', 'special' );
	public function __construct( $status, $storage, $translator, $ui, $ui_registry ) { $this->status = $status; $this->storage = $storage; $this->translator = $translator; $this->ui = $ui; $this->ui_registry = $ui_registry; }

	public function translate( array $row ) {
		if ( ! isset( $row['object_type'], $row['object_id'], $row['field'], $row['language'], $row['status'] ) || ! in_array( $row['language'], Pera_ML_Translation_Health::LANGUAGES, true ) || ! in_array( $row['status'], array( 'missing', 'stale' ), true ) ) return new WP_Error( 'invalid_row' );
		$type = sanitize_text_field( $row['object_type'] ); $id = absint( $row['object_id'] ); $field = Pera_ML_Storage::normalize_field_key( $row['field'] ); $language = sanitize_key( $row['language'] );
		if ( 'ui' === $type ) return $this->translate_ui( $row['field'], $language );
		if ( 0 === strpos( $type, 'taxonomy:' ) ) return $this->translate_term( substr( $type, 9 ), $id, $field, $language );
		return $this->translate_post( $type, $id, $field, $language );
	}
	private function translate_ui( $identity, $language ) { $item = $this->ui_registry->find( $identity ); if ( ! $item || 'current' === $this->ui->status( $item, $language ) ) return new WP_Error( 'invalid_row' ); $result = $this->ui->translate_registered( $identity, $language ); if ( is_wp_error( $result ) ) return $result; return 'current' === $this->ui->status( $item, $language ) ? $result : new WP_Error( 'translation_not_stored' ); }
	private function translate_term( $taxonomy, $id, $field, $language ) {
		if ( ! in_array( $taxonomy, $this->taxonomies, true ) ) return new WP_Error( 'invalid_row' );
		$term = get_term( $id ); if ( ! $term instanceof WP_Term || $taxonomy !== $term->taxonomy || ! in_array( $field, Pera_ML_Fields::taxonomy_fields( $term->taxonomy ), true ) ) return new WP_Error( 'invalid_row' );
		if ( 'term_name' === $field ) $source = $term->name; elseif ( 'term_description' === $field ) $source = $term->description; elseif ( 0 === strpos( $field, 'meta:' ) ) $source = get_term_meta( $id, substr( $field, 5 ), true ); else return new WP_Error( 'invalid_row' );
		if ( ! is_string( $source ) || '' === trim( $source ) ) return new WP_Error( 'invalid_row' );
		$stored = $this->storage->get( 'term', $id, $field, $language, $source );
		if ( is_array( $stored ) && '' !== trim( (string) $stored['translated_text'] ) && empty( $stored['is_stale'] ) && ( ! isset( $stored['status'] ) || 'current' === $stored['status'] ) ) return new WP_Error( 'invalid_row' );
		$result = $this->translator->translate_and_store( 'term', $id, $field, $language, $source );
		return $this->confirm_stored( $result, 'term', $id, $field, $language, $source );
	}
	private function translate_post( $type, $id, $field, $language ) {
		$post = get_post( $id ); if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page', 'property' ), true ) || $type !== $post->post_type ) return new WP_Error( 'invalid_row' );
		$sources = $this->status->applicable_sources( $id, $post->post_type ); $state = $this->status->get( $id, $language, $post->post_type ); $eligible = in_array( $field, array_merge( $state['missing'], $state['stale'] ), true ); $source = isset( $sources[ $field ] ) && is_string( $sources[ $field ] ) ? $sources[ $field ] : '';
		if ( ! $eligible || '' === trim( $source ) ) return new WP_Error( 'invalid_row' );
		$result = $this->translator->translate_and_store( 'post', $id, $field, $language, $source );
		return $this->confirm_stored( $result, 'post', $id, $field, $language, $source );
	}
	private function confirm_stored( $result, $type, $id, $field, $language, $source ) { if ( is_wp_error( $result ) ) return $result; $row = $this->storage->get( $type, $id, $field, $language, $source ); return is_array( $row ) && isset( $row['translated_text'] ) && '' !== trim( (string) $row['translated_text'] ) && empty( $row['is_stale'] ) && ( ! isset( $row['status'] ) || 'current' === $row['status'] ) ? $result : new WP_Error( 'translation_not_stored' ); }
}
