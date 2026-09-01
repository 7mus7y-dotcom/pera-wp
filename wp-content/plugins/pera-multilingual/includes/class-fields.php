<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Fields {
	private $router; private $storage; private $vocabulary;
	public static function property_fields() {
		// Structured fields such as price_list_kd and canonical checkbox arrays are intentionally absent.
		return apply_filters( 'pera_ml_property_translatable_meta_fields', array( 'project_name', 'custom_text', 'project_summary_heading', 'project_summary', 'whats_special_heading', 'about_this_project', 'location_info_heading', 'distances', 'yt_heading', 'custom_video_heading', 'custom_video_button', 'custom_video_text', 'floor_plans_heading', 'floor_plans_custom_text', 'further_reading_heading', 'further_reading_text', 'kd_custom_text', 'v2_custom_text', 'seo_title', 'seo_meta_description', 'seo_faq_v2', 'property_editorial_intro', 'property_highlights_text', 'property_district_analysis', 'property_investment_potential', 'property_buyer_suitability', 'property_developer_profile', 'property_faq_text' ) );
	}
	public static function controlled_property_fields() { return array( 'facilities', 'target_buyer_type', 'property_key_advantages' ); }
	/** Taxonomies whose canonical terms are included in translation generation and health. */
	public static function supported_taxonomies() {
		return apply_filters( 'pera_ml_translatable_taxonomies', array( 'district', 'region', 'property_type', 'property_tags', 'special', 'category' ) );
	}
	/** Canonical term contract consumed by term() and the registered ACF formatting layer. */
	public static function taxonomy_fields( $taxonomy ) {
		$fields = array( 'term_name', 'term_description' );
		if ( in_array( $taxonomy, array( 'category', 'post_tag', 'district', 'region', 'property_type', 'property_tags', 'special' ), true ) ) $fields = array_merge( $fields, array( 'meta:seo_title', 'meta:seo_meta_description' ) );
		if ( in_array( $taxonomy, array( 'district', 'region', 'property_type', 'property_tags' ), true ) ) $fields = array_merge( $fields, array( 'meta:term_excerpt', 'meta:excerpt', 'meta:pera_term_excerpt' ) );
		if ( 'category' === $taxonomy ) $fields = array_merge( $fields, array( 'meta:pera_term_excerpt', 'meta:category_excerpt' ) );
		if ( in_array( $taxonomy, array( 'district', 'region', 'property_type', 'property_tags', 'special' ), true ) ) $fields[] = 'meta:seo_faq_v2';
		if ( in_array( $taxonomy, array( 'region', 'property_tags' ), true ) ) $fields = array_merge( $fields, array( 'meta:archive_subtitle', 'meta:archive_body_content' ) );
		if ( 'district' === $taxonomy ) $fields = array_merge( $fields, array( 'meta:district_archive_subtitle', 'meta:district_archive_body' ) );
		return apply_filters( 'pera_ml_taxonomy_translatable_fields', $fields, $taxonomy );
	}
	public function __construct( $router, $storage, $vocabulary ) { $this->router = $router; $this->storage = $storage; $this->vocabulary = $vocabulary; }
	public function hooks() { foreach ( array_unique( array_merge( $this->approved(), self::controlled_property_fields() ) ) as $field ) add_filter( 'acf/format_value/name=' . $field, array( $this, 'acf_value' ), 20, 3 ); }
	public function acf_value( $value, $post_id, $field ) {
		$object = $this->identify_acf_object( $post_id );
		if ( is_array( $value ) ) return $this->controlled_array_value( $value, $object, isset( $field['name'] ) ? $field['name'] : '' );
		if ( ! is_string( $value ) ) return $value;
		if ( ! $object || ! isset( $field['name'] ) ) return $value;
		$source = $value;
		$has_raw_source = false;
		// ACF type formatters can run before this name-specific filter. Translation
		// storage, generation, and health all hash the unformatted database value.
		if ( 'post' === $object['type'] && function_exists( 'get_field' ) ) {
			$raw = get_field( $field['name'], $object['id'], false );
			if ( is_string( $raw ) ) { $source = $raw; $has_raw_source = true; }
		}
		$translated = $this->get_for_object( $object['type'], $object['id'], $field['name'], $source, null, isset( $object['post_type'] ) ? $object['post_type'] : null );
		if ( $translated === $source ) return $value;
		return $has_raw_source ? $this->format_translated_acf_value( $translated, $post_id, $field ) : $translated;
	}
	/** Run translated raw content through the same ACF formatting pipeline without re-entering this filter. */
	private function format_translated_acf_value( $value, $post_id, $field ) {
		if ( ! function_exists( 'acf_format_value' ) || ! function_exists( 'remove_filter' ) ) return $value;
		$hook = 'acf/format_value/name=' . $field['name'];
		remove_filter( $hook, array( $this, 'acf_value' ), 20 );
		$value = acf_format_value( $value, $post_id, $field );
		add_filter( $hook, array( $this, 'acf_value' ), 20, 3 );
		return $value;
	}
	private function controlled_array_value( array $value, $object, $field ) {
		if ( ! $object || 'post' !== $object['type'] || 'property' !== $object['post_type'] || ! in_array( $field, self::controlled_property_fields(), true ) ) return $value;
		$language = $this->router->current_language();
		if ( 'en' === $language ) return $value;
		$translated = array();
		foreach ( $value as $key => $label ) $translated[ $key ] = is_string( $label ) ? $this->vocabulary->translate_for_field( $field, $label, $language ) : $label;
		return $translated;
	}
	public function definitions() {
		$legacy = array( 'project_name', 'floor_plans_heading', 'floor_plans_custom_text', 'property_editorial_intro', 'property_highlights_text', 'property_district_analysis', 'property_investment_potential', 'property_buyer_suitability', 'property_developer_profile', 'property_faq_text', 'further_reading_heading', 'further_reading_text', 'custom_video_heading', 'custom_video_text', 'project_summary_heading', 'project_summary', 'yt_heading', 'whats_special_heading', 'about_this_project', 'location_info_heading', 'distances', 'archive_h1', 'archive_subtitle', 'archive_intro_content', 'archive_bottom_content', 'archive_cta_heading', 'archive_cta_text', 'district_archive_subtitle', 'district_archive_body', 'post_subtitle', 'seo_title', 'seo_meta_description', 'seo_faq_v2' );
		$property = self::property_fields();
		$page = array( 'seo_title', 'seo_meta_description', 'seo_faq_v2', 'homepage_hero_subtext', 'homepage_listing_intro', 'homepage_bottom_seo_text' );
		return apply_filters( 'pera_ml_translatable_meta_fields_by_post_type', array( 'post' => apply_filters( 'pera_ml_translatable_meta_fields', $legacy ), 'page' => $page, 'property' => $property ) );
	}
	/** Get the contract for one post type, or the union used to register ACF filters. */
	public function approved( $post_type = null ) {
		$definitions = $this->definitions();
		if ( null !== $post_type ) return isset( $definitions[ $post_type ] ) ? $definitions[ $post_type ] : array();
		$fields = array(); foreach ( $definitions as $type_fields ) $fields = array_merge( $fields, $type_fields );
		return array_values( array_unique( $fields ) );
	}
	public function get( $post_id, $field, $source, $language = null ) {
		return $this->get_for_object( 'post', (int) $post_id, $field, $source, $language );
	}
	/** Translate each FAQ leaf independently without adding the repeater to the scalar field contract. */
	public function homepage_faq( $post_id, array $rows, $language = null ) {
		$language = $language ? sanitize_key( $language ) : $this->router->current_language();
		if ( 'en' === $language || (int) $post_id <= 0 ) return $rows;
		foreach ( $rows as $index => &$row ) {
			if ( ! is_array( $row ) ) continue;
			foreach ( array( 'question', 'answer' ) as $leaf ) {
				if ( ! isset( $row[ $leaf ] ) || ! is_string( $row[ $leaf ] ) || '' === trim( $row[ $leaf ] ) ) continue;
				$source = $row[ $leaf ];
				$stored = $this->storage->get( 'post', (int) $post_id, self::homepage_faq_field( $index, $leaf ), $language, $source );
				if ( is_array( $stored ) && empty( $stored['is_stale'] ) && ( ! isset( $stored['status'] ) || 'current' === $stored['status'] ) && isset( $stored['translated_text'] ) && '' !== trim( (string) $stored['translated_text'] ) ) $row[ $leaf ] = $stored['translated_text'];
			}
		}
		unset( $row );
		return $rows;
	}
	public static function homepage_faq_field( $index, $leaf ) { return 'meta:homepage_faq_' . absint( $index ) . '_' . sanitize_key( $leaf ); }
	public static function homepage_faq_sources( $post_id ) {
		// Translation generation/status hashes canonical unformatted ACF leaves.
		$rows = function_exists( 'get_field' ) ? get_field( 'faq', (int) $post_id, false ) : null;
		if ( ! is_array( $rows ) ) {
			$raw = get_post_meta( (int) $post_id, 'faq', true );
			if ( is_array( $raw ) ) {
				$rows = $raw;
			} else {
				$rows = array();
				for ( $index = 0, $count = absint( $raw ); $index < $count; $index++ ) {
					$rows[] = array(
						'question' => get_post_meta( (int) $post_id, 'faq_' . $index . '_question', true ),
						'answer'   => get_post_meta( (int) $post_id, 'faq_' . $index . '_answer', true ),
					);
				}
			}
		}
		$sources = array();
		if ( ! is_array( $rows ) ) return $sources;
		foreach ( $rows as $index => $row ) foreach ( array( 'question', 'answer' ) as $leaf ) if ( isset( $row[ $leaf ] ) && is_string( $row[ $leaf ] ) && '' !== trim( $row[ $leaf ] ) ) $sources[ self::homepage_faq_field( $index, $leaf ) ] = $row[ $leaf ];
		return $sources;
	}
	public function get_for_object( $object_type, $object_id, $field, $source, $language = null, $post_type = null ) {
		$language = $language ? sanitize_key( $language ) : $this->router->current_language();
		if ( 'post' === $object_type && null === $post_type ) $post_type = function_exists( 'get_post_type' ) ? get_post_type( $object_id ) : 'post';
		$approved = 'post' === $object_type ? $this->approved( $post_type ? $post_type : 'post' ) : $this->approved();
		if ( 'en' === $language || $object_id <= 0 || ! in_array( $field, $approved, true ) ) return $source;
		$row = $this->storage->get( $object_type, (int) $object_id, 'meta:' . sanitize_key( $field ), $language, (string) $source );
		if ( ! is_array( $row ) || ! empty( $row['is_stale'] ) || ( isset( $row['status'] ) && 'current' !== $row['status'] ) || ! isset( $row['translated_text'] ) || '' === trim( (string) $row['translated_text'] ) ) return $source;
		return $row['translated_text'];
	}
	public function identify_acf_object( $post_id ) {
		if ( $post_id instanceof WP_Term ) return array( 'type' => 'term', 'id' => (int) $post_id->term_id );
		if ( $post_id instanceof WP_Post ) return array( 'type' => 'post', 'id' => (int) $post_id->ID, 'post_type' => $post_id->post_type );
		if ( is_numeric( $post_id ) && (int) $post_id > 0 ) return array( 'type' => 'post', 'id' => (int) $post_id, 'post_type' => function_exists( 'get_post_type' ) ? get_post_type( (int) $post_id ) : 'post' );
		if ( is_string( $post_id ) && preg_match( '/^(term|district|region|property_type|property_tags|special|category)_(\d+)$/i', $post_id, $match ) ) return array( 'type' => 'term', 'id' => (int) $match[2] );
		return null;
	}
	public function term( $term, $field = 'name', $language = null ) {
		if ( ! $term instanceof WP_Term ) return '';
		$language = $language ? sanitize_key( $language ) : $this->router->current_language(); $source = 'description' === $field ? $term->description : $term->name;
		if ( 'en' === $language ) return $source;
		$row = $this->storage->get( 'term', $term->term_id, 'term_' . $field, $language, (string) $source );
		if ( is_array( $row ) && empty( $row['is_stale'] ) && ( ! isset( $row['status'] ) || 'current' === $row['status'] ) && isset( $row['translated_text'] ) && '' !== trim( (string) $row['translated_text'] ) ) return $row['translated_text'];
		return 'name' === $field ? $this->vocabulary->translate( $source, $language ) : $source;
	}
	/** Read one approved taxonomy meta translation without invoking a provider. */
	public function term_meta( $term, $field, $source, $language = null ) {
		if ( ! $term instanceof WP_Term ) return $source;
		$field = Pera_ML_Storage::normalize_field_key( $field );
		if ( 0 !== strpos( $field, 'meta:' ) ) $field = 'meta:' . sanitize_key( $field );
		if ( ! in_array( $field, self::taxonomy_fields( $term->taxonomy ), true ) ) return $source;
		$language = $language ? sanitize_key( $language ) : $this->router->current_language();
		if ( 'en' === $language ) return $source;
		$row = $this->storage->get( 'term', $term->term_id, $field, $language, (string) $source );
		if ( ! is_array( $row ) || ! empty( $row['is_stale'] ) || ( isset( $row['status'] ) && 'current' !== $row['status'] ) || ! isset( $row['translated_text'] ) || '' === trim( (string) $row['translated_text'] ) ) return $source;
		return $row['translated_text'];
	}
}
