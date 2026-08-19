<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Translator {
	private $registry; private $storage;
	public function __construct( $registry, $storage ) { $this->registry = $registry; $this->storage = $storage; }
	public function provider( $id = '' ) {
		$id = $id ? $id : get_option( 'pera_ml_provider', 'openai' );
		$provider = 'mock' === $id ? new Pera_ML_Mock_Provider() : new Pera_ML_OpenAI_Provider();
		return apply_filters( 'pera_ml_provider', $provider, $id );
	}
	/** Explicit generation entry point. It is never called by frontend rendering hooks. */
	public function translate_and_store( $type, $id, $field, $language, $source, $provider_id = '' ) {
		$language_config = $this->registry->get( $language );
		if ( ! $language_config || ! empty( $language_config['source'] ) ) return new WP_Error( 'pera_ml_invalid_language', __( 'Invalid target language.', 'pera-multilingual' ) );
		$provider = $this->provider( $provider_id );
		$context = array( 'target_language' => $language, 'target_name' => $language_config['name'], 'instructions' => apply_filters( 'pera_ml_language_instructions', '', $language ), 'glossary' => $this->glossary_prompt() );
		$translated = $provider->translate( $source, $context );
		if ( is_wp_error( $translated ) ) { do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) ); return $translated; }
		if ( ! $this->structure_is_safe( $source, $translated ) ) return new WP_Error( 'pera_ml_structure_changed', __( 'Translation changed protected markup or tokens.', 'pera-multilingual' ) );
		$this->storage->put( $type, $id, $field, $language, $source, $translated, $provider->id() );
		return $translated;
	}
	private function glossary_prompt() {
		$entries = get_option( 'pera_ml_glossary', array() );
		$lines = array( 'Pera Property => PRESERVE' );
		foreach ( is_array( $entries ) ? $entries : array() as $entry ) {
			if ( ! empty( $entry['source'] ) ) $lines[] = $entry['source'] . ' => ' . ( ! empty( $entry['translation'] ) ? $entry['translation'] : 'PRESERVE' );
		}
		return implode( "\n", $lines );
	}
	private function structure_is_safe( $source, $translated ) {
		$pattern = '/(?:<[^>]+>|<!--\s*wp:.*?-->|\[[^\]]+\]|https?:\/\/[^\s<"\']+|[\w.+-]+@[\w.-]+\.[A-Za-z]{2,})/s';
		preg_match_all( $pattern, $source, $a ); preg_match_all( $pattern, $translated, $b );
		return $a[0] === $b[0];
	}
}
