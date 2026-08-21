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
		$default_instructions = isset( $language_config['instructions'] ) ? $language_config['instructions'] : '';
		$context = array( 'target_language' => $language, 'target_name' => $language_config['name'], 'instructions' => apply_filters( 'pera_ml_language_instructions', $default_instructions, $language ), 'glossary' => $this->glossary_prompt() );
		$pipe_faq_fields = apply_filters( 'pera_ml_pipe_faq_fields', array( 'meta:seo_faq_v2' ) );
		if ( is_array( $pipe_faq_fields ) && in_array( $field, $pipe_faq_fields, true ) ) {
			$translated = $this->translate_pipe_faq_and_store( $type, $id, $field, $language, $source, $provider, $context );
			if ( is_wp_error( $translated ) ) do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) );
			return $translated;
		}
		if ( 'post_content' === $field ) {
			$translated = $this->translate_post_content_chunks( $source, $provider, $context );
			if ( is_wp_error( $translated ) ) {
				do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) );
				return $translated;
			}
			$translated = $this->retry_echoed_html_segments( $source, $translated, $provider, $context, $language );
			if ( is_wp_error( $translated ) ) {
				do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) );
				return $translated;
			}
			$translated = $this->normalize_structural_html_whitespace( $translated );
		} else {
			$protected = $this->protect( $source );
			$translated = $provider->translate( $protected['text'], $context );
			if ( is_wp_error( $translated ) ) { do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) ); return $translated; }
			$translated = $this->restore( $translated, $protected['tokens'] );
			if ( is_wp_error( $translated ) ) return $translated;
		}
		$this->storage->put( $type, $id, $field, $language, $source, $translated, $provider->id() );
		return $translated;
	}
	/** Translate complete top-level HTML blocks sequentially, retrying only a structurally damaged block. */
	private function translate_post_content_chunks( $source, $provider, array $context ) {
		$output = '';
		$chunks = $this->bounded_structural_chunks( $source );
		if ( is_wp_error( $chunks ) ) return $chunks;
		foreach ( $chunks as $chunk ) {
			if ( empty( $chunk['translate'] ) ) {
				$output .= $chunk['text'];
				continue;
			}
			$translated = $this->translate_fragment( $chunk['text'], $provider, $context );
			if ( is_wp_error( $translated ) && 'pera_ml_structure_changed' === $translated->get_error_code() ) {
				$retry_context = $context;
				$retry_context['instructions'] = trim( (string) $retry_context['instructions'] . "\n" . 'Preserve every placeholder matching PERAMLPROTECTED<number>TOKEN exactly once, unchanged, and in its original order. Return no additional placeholders.' );
				$translated = $this->translate_fragment( $chunk['text'], $provider, $retry_context );
			}
			if ( is_wp_error( $translated ) ) return $translated;
			$output .= $translated;
		}
		return $output;
	}
	/**
	 * Group complete sibling blocks. Oversized compound wrappers are emitted verbatim
	 * around recursively grouped, balanced children rather than sent unmatched.
	 */
	private function bounded_structural_chunks( $source ) {
		$source = (string) $source;
		if ( '' === $source ) return array( array( 'translate' => true, 'text' => '' ) );
		$max_chars = max( 1, (int) apply_filters( 'pera_ml_post_content_chunk_max_chars', 10000 ) );
		$max_tokens = max( 1, (int) apply_filters( 'pera_ml_post_content_chunk_max_tokens', 35 ) );
		return $this->build_structural_chunk_plan( $source, $max_chars, $max_tokens );
	}
	private function build_structural_chunk_plan( $source, $max_chars, $max_tokens ) {
		$blocks = $this->top_level_structural_blocks( $source );
		$plan = array();
		$chunk = '';
		foreach ( $blocks as $block ) {
			$candidate = $chunk . $block;
			if ( '' !== $chunk && $this->exceeds_chunk_limits( $candidate, $max_chars, $max_tokens ) ) {
				$plan[] = array( 'translate' => true, 'text' => $chunk );
				$chunk = '';
			}
			if ( $this->exceeds_chunk_limits( $block, $max_chars, $max_tokens ) ) {
				if ( '' !== $chunk ) { $plan[] = array( 'translate' => true, 'text' => $chunk ); $chunk = ''; }
				$subplan = $this->subdivide_compound_block( $block, $max_chars, $max_tokens );
				if ( is_wp_error( $subplan ) ) return $subplan;
				$plan = array_merge( $plan, $subplan );
			} else {
				$chunk .= $block;
			}
		}
		if ( '' !== $chunk || empty( $plan ) ) $plan[] = array( 'translate' => true, 'text' => $chunk );
		return $plan;
	}
	private function top_level_structural_blocks( $source ) {
		$source = (string) $source;
		$void = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' );
		preg_match_all( '/<!--.*?-->|\[[A-Za-z][^\]]*\]|<\/?[A-Za-z][^>]*>/s', $source, $matches, PREG_OFFSET_CAPTURE );
		$blocks = array();
		$stack = array();
		$start = 0;
		$pending_end = 0;
		foreach ( $matches[0] as $match ) {
			$token = $match[0];
			if ( $pending_end ) {
				if ( '<!--' === substr( $token, 0, 4 ) && preg_match( '/^<!--\s*\/wp:[A-Za-z0-9_\/-]+\s*-->$/isu', $token ) ) {
					$end = $match[1] + strlen( $token );
					$blocks[] = substr( $source, $start, $end - $start );
					$start = $end;
					$pending_end = 0;
					continue;
				}
				$blocks[] = substr( $source, $start, $pending_end - $start );
				$start = $pending_end;
				$pending_end = 0;
			}
			if ( '<' !== substr( $token, 0, 1 ) || '<!--' === substr( $token, 0, 4 ) ) continue;
			if ( ! preg_match( '/^<\s*(\/?)\s*([A-Za-z0-9]+)/', $token, $tag_match ) ) continue;
			$closing = '/' === $tag_match[1];
			$tag = strtolower( $tag_match[2] );
			if ( ! $closing ) {
				if ( ! in_array( $tag, $void, true ) && ! preg_match( '/\/\s*>$/', $token ) ) $stack[] = $tag;
				continue;
			}
			if ( ! empty( $stack ) ) {
				$position = array_search( $tag, array_reverse( $stack, true ), true );
				if ( false !== $position ) $stack = array_slice( $stack, 0, $position );
			}
			if ( empty( $stack ) ) {
				$pending_end = $match[1] + strlen( $token );
			}
		}
		if ( $start < strlen( $source ) ) $blocks[] = substr( $source, $start );
		return empty( $blocks ) ? array( $source ) : $blocks;
	}
	private function subdivide_compound_block( $block, $max_chars, $max_tokens ) {
		$gutenberg = '/^(\s*<!--\s*wp:([A-Za-z0-9_\/-]+)(?:\s+.*?)?\s*-->\s*)(.*)(\s*<!--\s*\/wp:\2\s*-->\s*)$/isu';
		if ( preg_match( $gutenberg, $block, $comments ) ) {
			$wrapped = $this->subdivide_compound_block( $comments[3], $max_chars, $max_tokens );
			if ( is_wp_error( $wrapped ) ) return $wrapped;
			return array_merge(
				array( array( 'translate' => false, 'text' => $comments[1] ) ),
				$wrapped,
				array( array( 'translate' => false, 'text' => $comments[4] ) )
			);
		}
		$pattern = '/^(\s*)(<(div|figure|section|ul|ol|table|thead|tbody|tfoot)\b[^>]*>)(.*)(<\/\3\s*>)(\s*)$/isu';
		if ( ! preg_match( $pattern, $block, $parts ) ) {
			return new WP_Error( 'pera_ml_chunk_too_large', __( 'A structural block exceeds the translation chunk limits and cannot be safely subdivided.', 'pera-multilingual' ) );
		}
		$children = $this->build_structural_chunk_plan( $parts[4], $max_chars, $max_tokens );
		if ( is_wp_error( $children ) ) return $children;
		return array_merge(
			array( array( 'translate' => false, 'text' => $parts[1] . $parts[2] ) ),
			$children,
			array( array( 'translate' => false, 'text' => $parts[5] . $parts[6] ) )
		);
	}
	private function exceeds_chunk_limits( $text, $max_chars, $max_tokens ) {
		return strlen( $text ) > $max_chars || count( $this->protect( $text )['tokens'] ) > $max_tokens;
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
	/**
	 * Require target-script text plus an exact run of at least four English words.
	 * Deliberately limited to zh/ar: German and English both use Latin script, so a
	 * script test would reject legitimate German. This does not weaken zh/ar checks.
	 */
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
