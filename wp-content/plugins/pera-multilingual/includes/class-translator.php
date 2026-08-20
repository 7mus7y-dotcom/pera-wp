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
		if ( 'post_content' === $field ) {
			$translated = $this->retry_echoed_html_segments( $source, $translated, $provider, $context, $language );
			if ( is_wp_error( $translated ) ) {
				do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) );
				return $translated;
			}
			$translated = $this->normalize_structural_html_whitespace( $translated );
		}
		$this->storage->put( $type, $id, $field, $language, $source, $translated, $provider->id() );
		return $translated;
	}
	/** Retry only block-level HTML segments in which translated prose echoes its English source. */
	private function retry_echoed_html_segments( $source, $translated, $provider, array $context, $language ) {
		$pattern = '~(<(li|p|h[1-6]|blockquote|td|th)\b[^>]*>)(.*?)(</\2\s*>)~isu';
		$glossary = isset( $context['glossary'] ) ? $context['glossary'] : '';
		preg_match_all( $pattern, (string) $source, $source_segments, PREG_SET_ORDER );
		preg_match_all( $pattern, (string) $translated, $translated_segments, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

		if ( count( $source_segments ) !== count( $translated_segments ) ) {
			return new WP_Error( 'pera_ml_structure_changed', __( 'Translation changed the HTML segment structure.', 'pera-multilingual' ) );
		}

		$replacements = array();
		foreach ( $source_segments as $index => $source_segment ) {
			$translated_segment = $translated_segments[ $index ];
			if ( strtolower( $source_segment[2] ) !== strtolower( $translated_segment[2][0] ) ) {
				return new WP_Error( 'pera_ml_structure_changed', __( 'Translation changed the HTML segment structure.', 'pera-multilingual' ) );
			}
			$source_text     = html_entity_decode( strip_tags( $source_segment[3] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$translated_text = html_entity_decode( strip_tags( $translated_segment[3][0] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( ! $this->has_source_echo( $source_text, $translated_text, $language, $glossary ) ) continue;

			$retry_context = $context;
			$strict = 'Return only the target-language translation. Do not repeat or include the English source text.';
			$retry_context['instructions'] = trim( (string) $retry_context['instructions'] . "\n" . $strict );
			$retry = $this->translate_fragment( $source_segment[3], $provider, $retry_context );
			if ( is_wp_error( $retry ) ) return $retry;
			$retry_text = html_entity_decode( strip_tags( $retry ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( $this->has_source_echo( $source_text, $retry_text, $language, $glossary ) ) {
				return new WP_Error( 'pera_ml_source_echo', __( 'Translation repeated substantial English source prose.', 'pera-multilingual' ) );
			}
			$replacements[] = array( $translated_segment[3][1], strlen( $translated_segment[3][0] ), $retry );
		}

		foreach ( array_reverse( $replacements ) as $replacement ) {
			$translated = substr_replace( $translated, $replacement[2], $replacement[0], $replacement[1] );
		}
		return $translated;
	}
	/** Require target-script text plus an exact run of at least four English words and 20 characters. */
	private function has_source_echo( $source, $translated, $language, $glossary = '' ) {
		$target_pattern = 'zh' === $language ? '/\p{Han}/u' : ( 'ar' === $language ? '/\p{Arabic}/u' : '' );
		if ( ! $target_pattern || ! preg_match( $target_pattern, (string) $translated ) ) return false;
		$protected = $this->protect( $source );
		$candidate_source = strtr( $protected['text'], array_fill_keys( array_keys( $protected['tokens'] ), ' ' ) );
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $glossary ) as $rule ) {
			$separator = strpos( $rule, '=>' );
			if ( false !== $separator ) $candidate_source = str_replace( trim( substr( $rule, 0, $separator ) ), ' ', $candidate_source );
		}
		// Acronyms and multi-word title-cased names are legitimate preserved content, not prose echoes.
		$candidate_source = preg_replace( '/\b[A-Z]{2,}\b/u', ' ', $candidate_source );
		$candidate_source = preg_replace( '/\b(?:[A-Z][a-z]+\s+){1,}[A-Z][a-z]+\b/u', ' ', $candidate_source );
		preg_match_all( "/\b[A-Za-z][A-Za-z'’-]*(?:[ \t]+[A-Za-z][A-Za-z'’-]*){3,}\b/u", $candidate_source, $runs );
		foreach ( $runs[0] as $run ) {
			$words = preg_split( '/[ \t]+/', trim( $run ) );
			for ( $start = 0; $start <= count( $words ) - 4; $start++ ) {
				for ( $length = 4; $start + $length <= count( $words ); $length++ ) {
					$phrase = implode( ' ', array_slice( $words, $start, $length ) );
					if ( strlen( $phrase ) >= 20 && false !== strpos( (string) $translated, $phrase ) ) return true;
				}
			}
		}
		return false;
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
