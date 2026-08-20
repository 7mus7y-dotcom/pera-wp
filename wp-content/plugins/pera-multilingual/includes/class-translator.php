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
		$protected = $this->protect( $source );
		$translated = $provider->translate( $protected['text'], $context );
		if ( is_wp_error( $translated ) ) { do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) ); return $translated; }
		$translated = $this->restore( $translated, $protected['tokens'] );
		if ( is_wp_error( $translated ) ) return $translated;
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
	public function protect( $source ) {
		$tokens = array();
		$pattern = '/<!--\s*\/?wp:.*?-->|<[^>]+>|\[[A-Za-z][^\]]*\]|https?:\/\/[^\s<"\']+|[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}|\{\{[^}]+\}\}|%\d*\$?[a-z]|(?:\+?\d[\d\s().-]{6,}\d)|(?:[$€£¥]\s?\d[\d,.]*|\d[\d,.]*\s?(?:USD|EUR|GBP|TRY|CNY|RMB|AED|SAR|TL|m²|sqm|sq\.?\s?m|ft²))/iu';
		$text = preg_replace_callback( $pattern, static function ( $match ) use ( &$tokens ) { $key = 'PERAMLPROTECTED' . count( $tokens ) . 'TOKEN'; $tokens[ $key ] = $match[0]; return $key; }, (string) $source );
		return array( 'text' => $text, 'tokens' => $tokens );
	}
	public function restore( $translated, array $tokens ) {
		foreach ( $tokens as $key => $value ) if ( 1 !== substr_count( (string) $translated, $key ) ) return new WP_Error( 'pera_ml_structure_changed', __( 'Translation lost or duplicated protected content.', 'pera-multilingual' ) );
		$restored = strtr( (string) $translated, $tokens );
		return preg_match( '/PERAMLPROTECTED\d+TOKEN/', $restored ) ? new WP_Error( 'pera_ml_structure_changed', __( 'Translation contains an invalid protected token.', 'pera-multilingual' ) ) : $restored;
	}
}
