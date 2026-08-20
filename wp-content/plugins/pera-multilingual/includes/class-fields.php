<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Fields {
	private $router; private $storage; private $vocabulary;
	public function __construct( $router, $storage, $vocabulary ) { $this->router = $router; $this->storage = $storage; $this->vocabulary = $vocabulary; }
	public function hooks() { foreach ( $this->approved() as $field ) add_filter( 'acf/format_value/name=' . $field, array( $this, 'acf_value' ), 20, 3 ); }
	public function acf_value( $value, $post_id, $field ) {
		if ( ! is_string( $value ) ) return $value;
		$object = $this->identify_acf_object( $post_id );
		return $object ? $this->get_for_object( $object['type'], $object['id'], $field['name'], $value ) : $value;
	}
	public function approved() {
		return apply_filters( 'pera_ml_translatable_meta_fields', array( 'project_name', 'floor_plans_heading', 'floor_plans_custom_text', 'property_editorial_intro', 'property_highlights_text', 'property_district_analysis', 'property_investment_potential', 'property_buyer_suitability', 'property_developer_profile', 'property_faq_text', 'target_buyer_type', 'property_key_advantages', 'further_reading_heading', 'further_reading_text', 'custom_video_heading', 'custom_video_text', 'project_summary_heading', 'project_summary', 'yt_heading', 'whats_special_heading', 'about_this_project', 'location_info_heading', 'distances', 'archive_h1', 'archive_subtitle', 'archive_intro_content', 'archive_bottom_content', 'archive_cta_heading', 'archive_cta_text', 'district_archive_subtitle', 'district_archive_body', 'post_subtitle', 'seo_title', 'seo_meta_description' ) );
	}
	public function get( $post_id, $field, $source, $language = null ) {
		return $this->get_for_object( 'post', (int) $post_id, $field, $source, $language );
	}
	public function get_for_object( $object_type, $object_id, $field, $source, $language = null ) {
		$language = $language ? sanitize_key( $language ) : $this->router->current_language();
		if ( 'en' === $language || $object_id <= 0 || ! in_array( $field, $this->approved(), true ) ) return $source;
		$row = $this->storage->get( $object_type, (int) $object_id, 'meta:' . sanitize_key( $field ), $language, (string) $source );
		return $row && isset( $row['translated_text'] ) ? $row['translated_text'] : $source;
	}
	public function identify_acf_object( $post_id ) {
		if ( $post_id instanceof WP_Term ) return array( 'type' => 'term', 'id' => (int) $post_id->term_id );
		if ( $post_id instanceof WP_Post ) return array( 'type' => 'post', 'id' => (int) $post_id->ID );
		if ( is_numeric( $post_id ) && (int) $post_id > 0 ) return array( 'type' => 'post', 'id' => (int) $post_id );
		if ( is_string( $post_id ) && preg_match( '/^(term|district|region|property_type|property_tags|special)_(\d+)$/i', $post_id, $match ) ) return array( 'type' => 'term', 'id' => (int) $match[2] );
		return null;
	}
	public function term( $term, $field = 'name', $language = null ) {
		if ( ! $term instanceof WP_Term ) return '';
		$language = $language ? sanitize_key( $language ) : $this->router->current_language(); $source = 'description' === $field ? $term->description : $term->name;
		if ( 'en' === $language ) return $source;
		$row = $this->storage->get( 'term', $term->term_id, 'term_' . $field, $language, (string) $source );
		return $row ? $row['translated_text'] : ( 'name' === $field ? $this->vocabulary->translate( $source, $language ) : $source );
	}
}
