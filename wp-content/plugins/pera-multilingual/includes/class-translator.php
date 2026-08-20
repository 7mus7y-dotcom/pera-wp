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
		$pipe_faq_fields = apply_filters( 'pera_ml_pipe_faq_fields', array( 'meta:seo_faq_v2' ) );
		if ( is_array( $pipe_faq_fields ) && in_array( $field, $pipe_faq_fields, true ) ) {
			$translated = $this->translate_pipe_faq_and_store( $type, $id, $field, $language, $source, $provider, $context );
			if ( is_wp_error( $translated ) ) do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) );
			return $translated;
		}
		$protected = $this->protect( $source );
		$translated = $provider->translate( $protected['text'], $context );
		if ( is_wp_error( $translated ) ) { do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) ); return $translated; }
		$translated = $this->restore( $translated, $protected['tokens'] );
		if ( is_wp_error( $translated ) ) return $translated;
		if ( 'post_content' === $field ) $translated = $this->normalize_structural_html_whitespace( $translated );
		$this->storage->put( $type, $id, $field, $language, $source, $translated, $provider->id() );
		return $translated;
	}
	/** Translate pipe-delimited FAQ fields without exposing their row structure to the provider. */
	private function translate_pipe_faq_and_store( $type, $id, $field, $language, $source, $provider, array $context ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $source );
		$output = array();

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				$output[] = '';
				continue;
			}

			$separator = strpos( $line, '|' );
			if ( false === $separator ) {
				$output[] = $line;
				continue;
			}

			$question = trim( substr( $line, 0, $separator ) );
			$answer   = trim( substr( $line, $separator + 1 ) );
			if ( '' === $question || '' === $answer ) {
				$output[] = $line;
				continue;
			}

			$translated_question = $this->translate_fragment( $question, $provider, $context );
			if ( is_wp_error( $translated_question ) ) return $translated_question;
			$translated_answer = $this->translate_fragment( $answer, $provider, $context );
			if ( is_wp_error( $translated_answer ) ) return $translated_answer;

			// A provider must not introduce row delimiters or merge entries.
			if ( preg_match( '/[|\r\n]/', $translated_question ) || preg_match( '/[|\r\n]/', $translated_answer ) ) {
				$output[] = $line;
				continue;
			}
			$output[] = trim( $translated_question ) . ' | ' . trim( $translated_answer );
		}

		$translated = implode( "\n", $output );
		$this->storage->put( $type, $id, $field, $language, $source, $translated, $provider->id() );
		return $translated;
	}
	private function translate_fragment( $source, $provider, array $context ) {
		$protected  = $this->protect( $source );
		$translated = $provider->translate( $protected['text'], $context );
		return is_wp_error( $translated ) ? $translated : $this->restore( $translated, $protected['tokens'] );
	}
	/** Remove indentation-only text nodes in lists without changing whitespace in prose. */
	private function normalize_structural_html_whitespace( $html ) {
		$indentation = '[\s\x{00A0}\x{2000}-\x{200B}\x{202F}\x{3000}]*';
		$html = preg_replace( '/(<(?:ul|ol)\b[^>]*>)' . $indentation . '(?=<li\b)/iu', '$1', (string) $html );
		return preg_replace( '/(<\/li>)' . $indentation . '(?=<li\b|<\/(?:ul|ol)>)/iu', '$1', $html );
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
