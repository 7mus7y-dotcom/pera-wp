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
		} elseif ( false !== ( $simple_list = $this->parse_simple_html_list( $source ) ) ) {
			$translated = $this->translate_simple_html_list( $simple_list, $provider, $context );
			if ( is_wp_error( $translated ) ) {
				do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) );
				return $translated;
			}
		} elseif ( $this->contains_translatable_html( $source ) ) {
			// Rich non-content fields use the same balanced-fragment recovery as
			// post_content, without its source-echo or whitespace cleanup passes.
			$translated = $this->translate_rich_html_chunks( $source, $provider, $context );
			if ( is_wp_error( $translated ) ) {
				do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) );
				return $translated;
			}
		} else {
			$protected = $this->protect( $source );
			$translated = $provider->translate( $protected['text'], $context );
			if ( is_wp_error( $translated ) ) { do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) ); return $translated; }
			$translated = $this->restore( $translated, $protected['tokens'] );
			if ( is_wp_error( $translated ) && 'pera_ml_structure_changed' === $translated->get_error_code() ) {
				$translated = $provider->translate( $protected['text'], $this->strict_placeholder_context( $context ) );
				if ( ! is_wp_error( $translated ) ) $translated = $this->restore( $translated, $protected['tokens'] );
			}
			if ( is_wp_error( $translated ) ) {
				do_action( 'pera_ml_translation_error', $translated, compact( 'type', 'id', 'field', 'language' ) );
				return $translated;
			}
		}
		$this->storage->put( $type, $id, $field, $language, $source, $translated, $provider->id() );
		return $translated;
	}
	/** Detect a real, valid-looking HTML tag rather than comparison punctuation. */
	private function contains_translatable_html( $source ) {
		$tags = 'a|abbr|address|article|aside|b|blockquote|br|caption|cite|code|col|colgroup|dd|del|details|div|dl|dt|em|figcaption|figure|footer|h[1-6]|header|hr|i|img|ins|kbd|li|main|mark|nav|ol|p|pre|q|s|section|small|span|strong|sub|summary|sup|table|tbody|td|tfoot|th|thead|tr|u|ul';
		$attributes = '(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*';
		return 1 === preg_match( '/<\/?(?:' . $tags . ')\b' . $attributes . '\/?\s*>/iu', (string) $source );
	}
	/**
	 * Parse exactly one flat ul/ol whose only children are plain-text li elements.
	 * Returning false deliberately sends nested, inline, or malformed markup through
	 * the existing generic protected-placeholder path.
	 */
	private function parse_simple_html_list( $source ) {
		$attributes = '(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>]+))?)*\s*';
		$outer_pattern = '/^(\s*)(<((?:ul|ol))\b' . $attributes . '>)(.*)(<\/\3\s*>)(\s*)$/isu';
		if ( ! preg_match( $outer_pattern, (string) $source, $outer ) ) return false;

		$inner = $outer[4];
		$item_pattern = '/\G(\s*)(<li\b' . $attributes . '>)([^<>]*)(<\/li\s*>)/isu';
		$items = array();
		$offset = 0;
		while ( preg_match( $item_pattern, $inner, $item, 0, $offset ) ) {
			$items[] = array( 'leading' => $item[1], 'open' => $item[2], 'content' => $item[3], 'close' => $item[4] );
			$offset += strlen( $item[0] );
		}
		$trailing = substr( $inner, $offset );
		if ( empty( $items ) || ! preg_match( '/^\s*$/u', $trailing ) ) return false;

		return array(
			'prefix' => $outer[1], 'open' => $outer[2], 'items' => $items,
			'trailing' => $trailing, 'close' => $outer[5], 'suffix' => $outer[6],
		);
	}
	/** Translate flat-list item text independently and rebuild every wrapper locally. */
	private function translate_simple_html_list( array $list, $provider, array $context ) {
		$output = $list['prefix'] . $list['open'];
		foreach ( $list['items'] as $item ) {
			$translated = '' === trim( $item['content'] ) ? $item['content'] : $this->translate_fragment( $item['content'], $provider, $context );
			if ( is_wp_error( $translated ) ) return $translated;
			$output .= $item['leading'] . $item['open'] . $translated . $item['close'];
		}
		return $output . $list['trailing'] . $list['close'] . $list['suffix'];
	}
	/** Translate complete top-level HTML blocks sequentially, retrying only a structurally damaged block. */
	private function translate_post_content_chunks( $source, $provider, array $context ) {
		return $this->translate_structural_chunks( $source, $provider, $context, 'post_content' );
	}
	/** Translate rich meta in smaller balanced groups to limit provider exposure. */
	private function translate_rich_html_chunks( $source, $provider, array $context ) {
		return $this->translate_structural_chunks( $source, $provider, $context, 'rich_html' );
	}
	private function translate_structural_chunks( $source, $provider, array $context, $scope ) {
		$output = '';
		$chunks = $this->bounded_structural_chunks( $source, $scope );
		if ( is_wp_error( $chunks ) ) return $chunks;
		foreach ( $chunks as $chunk ) {
			if ( empty( $chunk['translate'] ) ) {
				$output .= $chunk['text'];
				continue;
			}
			$translated = $this->translate_structural_fragment( $chunk['text'], $provider, $context );
			if ( is_wp_error( $translated ) ) return $translated;
			$output .= $translated;
		}
		return $output;
	}
	/**
	 * Recover a provider-damaged fragment by replanning only that fragment at
	 * progressively smaller limits. A leaf gets the existing single strict retry.
	 */
	private function translate_structural_fragment( $source, $provider, array $context, $depth = 0 ) {
		$translated = $this->translate_fragment( $source, $provider, $context );
		if ( ! is_wp_error( $translated ) || 'pera_ml_structure_changed' !== $translated->get_error_code() ) return $translated;

		$max_depth = 8;
		if ( $depth < $max_depth ) {
			$protected_count = count( $this->protect( $source )['tokens'] );
			// The character limit here is only a recovery grouping hint. Do not let
			// it become smaller than an otherwise safe, indivisible direct child:
			// token reduction can still separate sibling leaves without falsely
			// treating the longer sibling as a globally oversized compound block.
			$largest_child = 1;
			foreach ( $this->top_level_structural_blocks( $source ) as $child ) {
				$largest_child = max( $largest_child, strlen( $child ) );
			}
			$smaller_chars = max( $largest_child, (int) floor( strlen( $source ) / 2 ) );
			$smaller_tokens = max( 1, (int) floor( $protected_count / 2 ) );
			$subplan = $this->build_structural_chunk_plan( $source, $smaller_chars, $smaller_tokens );
			if ( ! is_wp_error( $subplan ) && $this->is_smaller_structural_plan( $subplan, $source ) ) {
				$output = '';
				foreach ( $subplan as $piece ) {
					if ( empty( $piece['translate'] ) ) {
						$output .= $piece['text'];
						continue;
					}
					$piece_translation = $this->translate_structural_fragment( $piece['text'], $provider, $context, $depth + 1 );
					if ( is_wp_error( $piece_translation ) ) return $piece_translation;
					$output .= $piece_translation;
				}
				return $output;
			}
		}

		$retry_context = $this->strict_placeholder_context( $context );
		$retried = $this->translate_fragment( $source, $provider, $retry_context );
		if ( ! is_wp_error( $retried ) || 'pera_ml_structure_changed' !== $retried->get_error_code() ) return $retried;

		// A safe structural leaf is reconstructed locally only after ordinary recovery
		// and its strict protected-placeholder retry have both failed.
		$inline = $this->translate_inline_leaf( $source, $provider, $context );
		return false === $inline ? $retried : $inline;
	}
	private function strict_placeholder_context( array $context ) {
		$instruction = 'Preserve every placeholder matching PERAMLPROTECTED<number>TOKEN exactly once, unchanged, and in its original order. Do not remove, translate, reformat, split, duplicate, or add spaces inside these placeholders. Return all placeholders together with the translated text. Return no additional placeholders.';
		$context['instructions'] = trim( (string) $context['instructions'] . "\n" . $instruction );
		return $context;
	}
	/**
	 * Translate the text nodes of one balanced block leaf while retaining its block
	 * wrapper and every supported inline tag byte-for-byte. Plain inner text is safe;
	 * any unparsed angle bracket, malformed tag, or nested block rejects the leaf.
	 */
	private function translate_inline_leaf( $source, $provider, array $context ) {
		$leaf_pattern = '/^(\s*)(<((?:li|p|h[1-6]|blockquote|td|th))\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)(.*)(<\/\3\s*>)(\s*)$/isu';
		if ( ! preg_match( $leaf_pattern, (string) $source, $leaf ) ) return false;

		$inner = $leaf[4];
		$tag_pattern = '/<\s*(\/?)\s*(strong|b|em|i|span|a|br)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/isu';
		preg_match_all( $tag_pattern, $inner, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$parts = array();
		$stack = array();
		$offset = 0;
		foreach ( $matches as $match ) {
			$tag_text = $match[0][0];
			$tag_offset = $match[0][1];
			$text = substr( $inner, $offset, $tag_offset - $offset );
			if ( false !== strpos( $text, '<' ) || false !== strpos( $text, '>' ) ) return false;
			if ( '' !== $text ) $parts[] = array( 'tag' => false, 'text' => $text );

			$tag = strtolower( $match[2][0] );
			$closing = '' !== $match[1][0];
			$self_closing = preg_match( '/\/\s*>$/', $tag_text );
			if ( 'br' === $tag ) {
				if ( $closing ) return false;
			} elseif ( $closing ) {
				if ( empty( $stack ) || array_pop( $stack ) !== $tag || ! preg_match( '/^<\s*\/\s*' . preg_quote( $tag, '/' ) . '\s*>$/iu', $tag_text ) ) return false;
			} elseif ( $self_closing ) {
				return false;
			} else {
				$stack[] = $tag;
			}
			$parts[] = array( 'tag' => true, 'text' => $tag_text );
			$offset = $tag_offset + strlen( $tag_text );
		}
		$tail = substr( $inner, $offset );
		if ( false !== strpos( $tail, '<' ) || false !== strpos( $tail, '>' ) || ! empty( $stack ) ) return false;
		if ( '' !== $tail ) $parts[] = array( 'tag' => false, 'text' => $tail );
		$translated_inner = '';
		foreach ( $parts as $part ) {
			if ( $part['tag'] || '' === trim( html_entity_decode( $part['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) {
				$translated_inner .= $part['text'];
				continue;
			}
			$translated = $this->translate_fragment( $part['text'], $provider, $context );
			if ( is_wp_error( $translated ) ) return $translated;
			if ( false !== strpos( $translated, '<' ) || false !== strpos( $translated, '>' ) ) {
				return new WP_Error( 'pera_ml_structure_changed', __( 'Translation introduced unexpected HTML structure.', 'pera-multilingual' ) );
			}
			$translated_inner .= $translated;
		}
		return $leaf[1] . $leaf[2] . $translated_inner . $leaf[5] . $leaf[6];
	}
	private function is_smaller_structural_plan( array $plan, $source ) {
		$provider_fragments = array();
		foreach ( $plan as $piece ) if ( ! empty( $piece['translate'] ) ) $provider_fragments[] = $piece['text'];
		return ! empty( $provider_fragments ) && ( 1 !== count( $provider_fragments ) || $provider_fragments[0] !== $source );
	}
	/**
	 * Group complete sibling blocks. Oversized compound wrappers are emitted verbatim
	 * around recursively grouped, balanced children rather than sent unmatched.
	 */
	private function bounded_structural_chunks( $source, $scope = 'post_content' ) {
		$source = (string) $source;
		if ( '' === $source ) return array( array( 'translate' => true, 'text' => '' ) );
		$prefix = 'rich_html' === $scope ? 'pera_ml_rich_html_chunk_' : 'pera_ml_post_content_chunk_';
		$max_chars = max( 1, (int) apply_filters( $prefix . 'max_chars', 10000 ) );
		$max_tokens = max( 1, (int) apply_filters( $prefix . 'max_tokens', 'rich_html' === $scope ? 9 : 35 ) );
		return $this->build_structural_chunk_plan( $source, $max_chars, $max_tokens );
	}
	private function build_structural_chunk_plan( $source, $max_chars, $max_tokens ) {
		$blocks = $this->top_level_structural_blocks( $source );
		$plan = array();
		$chunk = '';
		$chunk_is_structural = null;
		foreach ( $blocks as $block ) {
			$block_is_structural = 1 === preg_match( '/^\s*(?:<!--\s*wp:|<(?:address|article|aside|blockquote|dd|details|div|dl|dt|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|summary|table|tbody|td|tfoot|th|thead|tr|ul)\b)/iu', $block );
			$candidate = $chunk . $block;
			if ( '' !== $chunk && ( $block_is_structural !== $chunk_is_structural || $this->exceeds_chunk_limits( $candidate, $max_chars, $max_tokens ) ) ) {
				$plan[] = array( 'translate' => true, 'text' => $chunk );
				$chunk = '';
				$chunk_is_structural = null;
			}
			if ( $this->exceeds_chunk_limits( $block, $max_chars, $max_tokens ) ) {
				if ( '' !== $chunk ) { $plan[] = array( 'translate' => true, 'text' => $chunk ); $chunk = ''; $chunk_is_structural = null; }
				$subplan = $this->subdivide_compound_block( $block, $max_chars, $max_tokens );
				if ( is_wp_error( $subplan ) ) return $subplan;
				$plan = array_merge( $plan, $subplan );
			} else {
				$chunk .= $block;
				$chunk_is_structural = $block_is_structural;
			}
		}
		if ( '' !== $chunk || empty( $plan ) ) $plan[] = array( 'translate' => true, 'text' => $chunk );
		return $plan;
	}
	private function top_level_structural_blocks( $source ) {
		$source = (string) $source;
		$void = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' );
		$block = array( 'address', 'article', 'aside', 'blockquote', 'dd', 'details', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul' );
		preg_match_all( '/<!--.*?-->|\[[A-Za-z][^\]]*\]|<\/?[A-Za-z][^>]*>/s', $source, $matches, PREG_OFFSET_CAPTURE );
		$blocks = array();
		$stack = array();
		$start = 0;
		$structural_start = null;
		$gutenberg_depth = 0;
		foreach ( $matches[0] as $match ) {
			$token = $match[0];
			$offset = $match[1];
			if ( '<!--' === substr( $token, 0, 4 ) ) {
				if ( preg_match( '/^<!--\s*wp:[A-Za-z0-9_\/-]+(?:\s+.*?)?\s*(\/)?-->$/isu', $token, $comment ) ) {
					if ( null === $structural_start ) {
						$leading = substr( $source, $start, $offset - $start );
						if ( '' !== $leading && ! preg_match( '/^\s*$/u', $leading ) ) $blocks[] = $leading;
						$structural_start = '' === $leading || preg_match( '/^\s*$/u', $leading ) ? $start : $offset;
						$gutenberg_depth = empty( $comment[1] ) ? 1 : 0;
					} elseif ( $gutenberg_depth > 0 && empty( $comment[1] ) ) {
						$gutenberg_depth++;
					}
					if ( ! empty( $comment[1] ) && 0 === $gutenberg_depth && empty( $stack ) ) {
						$end = $offset + strlen( $token );
						$blocks[] = substr( $source, $structural_start, $end - $structural_start );
						$start = $end;
						$structural_start = null;
					}
				} elseif ( null !== $structural_start && $gutenberg_depth > 0 && preg_match( '/^<!--\s*\/wp:[A-Za-z0-9_\/-]+\s*-->$/isu', $token ) ) {
					$gutenberg_depth = max( 0, $gutenberg_depth - 1 );
					if ( 0 === $gutenberg_depth ) {
						$end = $offset + strlen( $token );
						$blocks[] = substr( $source, $structural_start, $end - $structural_start );
						$start = $end;
						$structural_start = null;
						$stack = array();
					}
				}
				continue;
			}
			if ( '<' !== substr( $token, 0, 1 ) ) continue;
			if ( ! preg_match( '/^<\s*(\/?)\s*([A-Za-z0-9]+)/', $token, $tag_match ) ) continue;
			$closing = '/' === $tag_match[1];
			$tag = strtolower( $tag_match[2] );
			if ( ! $closing ) {
				if ( null === $structural_start && in_array( $tag, $block, true ) ) {
					$leading = substr( $source, $start, $offset - $start );
					if ( '' !== $leading && ! preg_match( '/^\s*$/u', $leading ) ) $blocks[] = $leading;
					$structural_start = '' === $leading || preg_match( '/^\s*$/u', $leading ) ? $start : $offset;
				}
				if ( null === $structural_start ) continue;
				if ( ! in_array( $tag, $void, true ) && ! preg_match( '/\/\s*>$/', $token ) ) $stack[] = $tag;
				if ( empty( $stack ) && 0 === $gutenberg_depth ) {
					$end = $offset + strlen( $token );
					$blocks[] = substr( $source, $structural_start, $end - $structural_start );
					$start = $end;
					$structural_start = null;
				}
				continue;
			}
			if ( null === $structural_start ) continue;
			for ( $position = count( $stack ) - 1; $position >= 0; $position-- ) {
				if ( $tag === $stack[ $position ] ) {
					$stack = array_slice( $stack, 0, $position );
					break;
				}
			}
			if ( empty( $stack ) && 0 === $gutenberg_depth ) {
				$end = $offset + strlen( $token );
				$blocks[] = substr( $source, $structural_start, $end - $structural_start );
				$start = $end;
				$structural_start = null;
			}
		}
		if ( $start < strlen( $source ) ) {
			$remainder = substr( $source, $start );
			if ( ! empty( $blocks ) && preg_match( '/^\s*$/u', $remainder ) ) {
				$blocks[ count( $blocks ) - 1 ] .= $remainder;
			} else {
				$blocks[] = $remainder;
			}
		}
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
