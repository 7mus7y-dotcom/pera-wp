<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_schema_has_active_seo_plugin' ) ) {
	/**
	 * Detect common SEO plugins that may already own JSON-LD output.
	 */
	function pera_schema_has_active_seo_plugin(): bool {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| class_exists( 'WPSEO_Frontend' )
			|| class_exists( 'RankMath\\Frontend\\Frontend' )
			|| class_exists( 'AIOSEO\\Plugin\\Common\\Main\\Main' );
	}
}

if ( ! function_exists( 'pera_schema_has_plugin_faq_block' ) ) {
	/**
	 * Detect FAQ blocks that SEO plugins typically serialize to FAQPage.
	 */
	function pera_schema_has_plugin_faq_block( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		if ( $content === '' ) {
			return false;
		}

		return stripos( $content, 'wp:yoast/faq-block' ) !== false
			|| stripos( $content, 'rank-math/faq-block' ) !== false
			|| stripos( $content, 'aioseo/faq' ) !== false;
	}
}

if ( ! function_exists( 'pera_schema_should_emit_type' ) ) {
	/**
	 * Granular schema guard (type + context aware).
	 *
	 * @param array<string,mixed> $context
	 */
	function pera_schema_should_emit_type( string $type, array $context = array() ): bool {
		$has_seo_plugin = pera_schema_has_active_seo_plugin();
		$force_theme_owner = ! empty( $context['force_theme_owner'] );

		switch ( $type ) {
			case 'Article':
			case 'BlogPosting':
			case 'BreadcrumbList':
				// Prefer plugin output unless this context is explicitly theme-owned.
				$should_emit = ! $has_seo_plugin || $force_theme_owner;
				break;

			case 'FAQPage':
				$post_id = isset( $context['post_id'] ) ? (int) $context['post_id'] : 0;
				if ( $post_id > 0 && pera_schema_has_plugin_faq_block( $post_id ) ) {
					$should_emit = false;
				} elseif ( ! empty( $context['plugin_likely_outputs_same_type'] ) ) {
					$should_emit = false;
				} else {
					$should_emit = true;
				}
				break;

			case 'CollectionPage':
			case 'ItemList':
				// Keep archive ItemList/CollectionPage by default even with SEO plugins.
				// Only suppress when caller can clearly prove same-type duplication risk.
				$should_emit = ! empty( $context['plugin_likely_outputs_same_type'] ) ? false : true;
				break;

			default:
				$should_emit = true;
				break;
		}

		/**
		 * Final override hook for edge environments.
		 */
		return (bool) apply_filters( 'pera_schema_should_emit_type', $should_emit, $type, $context );
	}
}

if ( ! function_exists( 'pera_prepare_manual_faq_schema_output' ) ) {
	/**
	 * Prepare legacy/manual FAQ schema field output.
	 *
	 * Supported field formats:
	 * - A complete <script type="application/ld+json">...</script> element.
	 * - Raw JSON only, which is wrapped in an application/ld+json script tag.
	 *
	 * Anything else is treated as unsafe/malformed legacy content and skipped.
	 */
	function pera_prepare_manual_faq_schema_output( string $schema ): string {
		$schema = trim( $schema );
		if ( $schema === '' ) {
			return '';
		}

		if ( preg_match( '/^<script\\b(?P<attrs>[^>]*)>(?P<json>.*)<\\/script>$/is', $schema, $matches ) ) {
			$attrs = isset( $matches['attrs'] ) ? (string) $matches['attrs'] : '';
			$json  = isset( $matches['json'] ) ? trim( (string) $matches['json'] ) : '';

			if ( stripos( $attrs, 'application/ld+json' ) === false || $json === '' ) {
				return '';
			}

			json_decode( $json, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return '';
			}

			return $schema;
		}

		if ( $schema[0] !== '{' && $schema[0] !== '[' ) {
			return '';
		}

		json_decode( $schema, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return '';
		}

		return '<script type="application/ld+json">' . $schema . '</script>';
	}
}

if ( ! function_exists( 'pera_output_manual_faq_schema_field' ) ) {
	/**
	 * Temporarily output manually entered FAQ JSON-LD from the per-object field.
	 *
	 * The field supports either a full JSON-LD script tag or raw JSON. Raw JSON
	 * is wrapped here so it never appears as visible body text after browser
	 * parsing.
	 *
	 * Legacy/manual raw schema path: keep for backwards compatibility, but plan
	 * to deprecate it once FAQ schema ownership is fully centralised.
	 */
	function pera_output_manual_faq_schema_field(): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		static $did_output = false;
		if ( $did_output ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || $post_type === '' ) {
			return;
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || empty( $post_type_object->public ) ) {
			return;
		}

		$schema = '';

		if ( function_exists( 'get_field' ) ) {
			$acf_schema = get_field( 'seo_faq_schema', $post_id );
			if ( is_scalar( $acf_schema ) ) {
				$schema = (string) $acf_schema;
			}
		}

		if ( trim( $schema ) === '' ) {
			$schema = (string) get_post_meta( $post_id, 'seo_faq_schema', true );
		}

		$schema = trim( $schema );

		if ( $schema === '' ) {
			return;
		}

		$schema_output = pera_prepare_manual_faq_schema_output( $schema );
		if ( $schema_output === '' ) {
			return;
		}

		$did_output = true;
		$GLOBALS['pera_schema_faq_emitted'] = true;

		echo "\n<!-- Pera: Manual FAQ Schema (temporary) -->\n";
		echo $schema_output . "\n";
	}
}
add_action( 'wp_head', 'pera_output_manual_faq_schema_field', 35 );

if ( ! function_exists( 'pera_get_single_post_faq_v2_items' ) ) {
	/**
	 * Return parsed FAQ rows from the authoritative single-post seo_faq_v2 field.
	 *
	 * @return array<int,array{question:string,answer:string}>
	 */
	function pera_get_single_post_faq_v2_items( int $post_id ): array {
		if ( $post_id <= 0 || ! function_exists( 'pera_parse_faq_pipe_text' ) ) {
			return array();
		}

		$post_faq_raw = '';

		if ( function_exists( 'get_field' ) ) {
			$post_faq_value = get_field( 'seo_faq_v2', $post_id );

			if ( is_scalar( $post_faq_value ) ) {
				$post_faq_raw = trim( (string) $post_faq_value );
			}
		}

		if ( '' === $post_faq_raw ) {
			$post_faq_value = get_post_meta( $post_id, 'seo_faq_v2', true );

			if ( is_scalar( $post_faq_value ) ) {
				$post_faq_raw = trim( (string) $post_faq_value );
			}
		}

		if ( '' === $post_faq_raw ) {
			return array();
		}

		return pera_parse_faq_pipe_text( $post_faq_raw );
	}
}

if ( ! function_exists( 'pera_output_single_post_faq_schema_field' ) ) {
	/**
	 * Output FAQPage JSON-LD for the editable post FAQ field from wp_head.
	 *
	 * Visible FAQ HTML remains owned by single-post.php. This head-only schema
	 * output mirrors the template's existing seo_faq_v2 lookup and pipe parser
	 * while centralising JSON-LD output with the other schema guards.
	 */
	function pera_output_single_post_faq_schema_field(): void {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}

		if ( ! empty( $GLOBALS['pera_schema_faq_emitted'] ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}

		if ( function_exists( 'pera_schema_has_plugin_faq_block' ) && pera_schema_has_plugin_faq_block( $post_id ) ) {
			return;
		}

		if (
			function_exists( 'pera_schema_should_emit_type' )
			&& ! pera_schema_should_emit_type(
				'FAQPage',
				array(
					'context' => 'single_post',
					'post_id' => $post_id,
				)
			)
		) {
			return;
		}

		if ( ! function_exists( 'pera_render_faq_schema' ) ) {
			return;
		}

		$post_faq_items = pera_get_single_post_faq_v2_items( $post_id );
		if ( empty( $post_faq_items ) ) {
			return;
		}

		pera_render_faq_schema(
			$post_faq_items,
			array(
				'context' => 'single_post',
				'post_id' => $post_id,
			)
		);
	}
}
add_action( 'wp_head', 'pera_output_single_post_faq_schema_field', 36 );

if ( ! function_exists( 'pera_schema_is_regional_guide_post' ) ) {
	/**
	 * Regional guides are currently implemented as blog posts in the
	 * `regional-guides` category.
	 */
	function pera_schema_is_regional_guide_post( int $post_id ): bool {
		if ( $post_id <= 0 || get_post_type( $post_id ) !== 'post' ) {
			return false;
		}

		$is_regional = has_category( 'regional-guides', $post_id );

		/**
		 * Allow additional project-specific regional guide detection.
		 */
		return (bool) apply_filters( 'pera_schema_is_regional_guide_post', $is_regional, $post_id );
	}
}

if ( ! function_exists( 'pera_schema_guide_like_category_slugs' ) ) {
	/**
	 * Default guide-like category slugs for post classification.
	 *
	 * @return array<int,string>
	 */
	function pera_schema_guide_like_category_slugs(): array {
		$slugs = array(
			'regional-guides',
			'buyer-guides',
			'investment-advice',
		);

		$slugs = apply_filters( 'pera_schema_guide_like_category_slugs', $slugs );

		if ( ! is_array( $slugs ) ) {
			return array();
		}

		$slugs = array_values(
			array_filter(
				array_map(
					static function ( $slug ): string {
						return sanitize_title( (string) $slug );
					},
					$slugs
				),
				static function ( string $slug ): bool {
					return $slug !== '';
				}
			)
		);

		return array_values( array_unique( $slugs ) );
	}
}

if ( ! function_exists( 'pera_schema_guide_like_category_priority' ) ) {
	/**
	 * Guide category priority when a post belongs to multiple guide-like categories.
	 *
	 * @return array<int,string>
	 */
	function pera_schema_guide_like_category_priority( int $post_id = 0 ): array {
		$priority = array(
			'regional-guides',
			'buyer-guides',
			'investment-advice',
		);

		$priority = apply_filters( 'pera_schema_guide_like_category_priority', $priority, $post_id );
		if ( ! is_array( $priority ) ) {
			return array();
		}

		$priority = array_values(
			array_filter(
				array_map(
					static function ( $slug ): string {
						return sanitize_title( (string) $slug );
					},
					$priority
				),
				static function ( string $slug ): bool {
					return $slug !== '';
				}
			)
		);

		return array_values( array_unique( $priority ) );
	}
}

if ( ! function_exists( 'pera_schema_get_primary_guide_like_category_slug' ) ) {
	/**
	 * Resolve the most relevant guide-like category slug for a post.
	 */
	function pera_schema_get_primary_guide_like_category_slug( int $post_id ): string {
		if ( $post_id <= 0 || get_post_type( $post_id ) !== 'post' ) {
			return '';
		}

		$guide_like_slugs = pera_schema_guide_like_category_slugs();
		if ( empty( $guide_like_slugs ) ) {
			return '';
		}

		$assigned = wp_get_post_categories(
			$post_id,
			array(
				'fields' => 'slugs',
			)
		);

		if ( ! is_array( $assigned ) || empty( $assigned ) ) {
			return '';
		}

		$assigned = array_values(
			array_filter(
				array_map(
					static function ( $slug ): string {
						return sanitize_title( (string) $slug );
					},
					$assigned
				),
				static function ( string $slug ): bool {
					return $slug !== '';
				}
			)
		);

		$guide_assigned = array_values( array_intersect( $assigned, $guide_like_slugs ) );
		if ( empty( $guide_assigned ) ) {
			return '';
		}

		$priority = pera_schema_guide_like_category_priority( $post_id );
		foreach ( $priority as $slug ) {
			if ( in_array( $slug, $guide_assigned, true ) ) {
				return $slug;
			}
		}

		return (string) $guide_assigned[0];
	}
}

if ( ! function_exists( 'pera_schema_is_guide_like_post' ) ) {
	/**
	 * Guide-like posts are standard posts in configured guide categories.
	 */
	function pera_schema_is_guide_like_post( int $post_id ): bool {
		$is_guide_like = pera_schema_get_primary_guide_like_category_slug( $post_id ) !== '';

		return (bool) apply_filters( 'pera_schema_is_guide_like_post', $is_guide_like, $post_id );
	}
}

if ( ! function_exists( 'pera_schema_guide_like_breadcrumb_items' ) ) {
	/**
	 * Build guide-like breadcrumbs:
	 * Home > Blog > [Guide Category] > Current Post.
	 *
	 * @return array<int, array{name:string,url:string}>
	 */
	function pera_schema_guide_like_breadcrumb_items( int $post_id ): array {
		$guide_slug = pera_schema_get_primary_guide_like_category_slug( $post_id );
		if ( $guide_slug === '' ) {
			return array();
		}

		$items = array(
			array(
				'name' => __( 'Home', 'hello-elementor-child' ),
				'url'  => (string) home_url( '/' ),
			),
		);

		$posts_page_id = (int) get_option( 'page_for_posts' );
		$blog_url      = '';
		$blog_name     = __( 'Blog', 'hello-elementor-child' );

		if ( $posts_page_id > 0 ) {
			$posts_url = get_permalink( $posts_page_id );
			if ( is_string( $posts_url ) && $posts_url !== '' ) {
				$blog_url = $posts_url;
			}

			$posts_title = trim( (string) get_the_title( $posts_page_id ) );
			if ( $posts_title !== '' ) {
				$blog_name = $posts_title;
			}
		} else {
			$archive_url = get_post_type_archive_link( 'post' );
			if ( is_string( $archive_url ) && $archive_url !== '' ) {
				$blog_url = $archive_url;
			}
		}

		if ( $blog_url !== '' ) {
			$items[] = array(
				'name' => $blog_name,
				'url'  => $blog_url,
			);
		}

		$guide_category = get_category_by_slug( $guide_slug );
		if ( $guide_category instanceof WP_Term ) {
			$guide_url = get_category_link( $guide_category->term_id );
			if ( ! is_wp_error( $guide_url ) && is_string( $guide_url ) && $guide_url !== '' ) {
				$items[] = array(
					'name' => $guide_category->name,
					'url'  => $guide_url,
				);
			}
		}

		$post_title = trim( (string) get_the_title( $post_id ) );
		if ( $post_title !== '' ) {
			$items[] = array(
				'name' => $post_title,
				'url'  => '',
			);
		}

		return $items;
	}
}

if ( ! function_exists( 'pera_schema_regional_guide_breadcrumb_items' ) ) {
	/**
	 * Build guide breadcrumbs: Home > Blog > Regional Guides > Current Guide.
	 *
	 * @return array<int, array{name:string,url:string}>
	 */
	function pera_schema_regional_guide_breadcrumb_items( int $post_id ): array {
		$items = array(
			array(
				'name' => __( 'Home', 'hello-elementor-child' ),
				'url'  => (string) home_url( '/' ),
			),
		);

		$posts_page_id = (int) get_option( 'page_for_posts' );
		$blog_url      = '';
		$blog_name     = __( 'Blog', 'hello-elementor-child' );

		if ( $posts_page_id > 0 ) {
			$posts_url = get_permalink( $posts_page_id );
			if ( is_string( $posts_url ) && $posts_url !== '' ) {
				$blog_url = $posts_url;
			}

			$posts_title = trim( (string) get_the_title( $posts_page_id ) );
			if ( $posts_title !== '' ) {
				$blog_name = $posts_title;
			}
		} else {
			$archive_url = get_post_type_archive_link( 'post' );
			if ( is_string( $archive_url ) && $archive_url !== '' ) {
				$blog_url = $archive_url;
			}
		}

		if ( $blog_url !== '' ) {
			$items[] = array(
				'name' => $blog_name,
				'url'  => $blog_url,
			);
		}

		$regional_category = get_category_by_slug( 'regional-guides' );
		if ( $regional_category instanceof WP_Term ) {
			$regional_url = get_category_link( $regional_category->term_id );
			if ( ! is_wp_error( $regional_url ) && is_string( $regional_url ) && $regional_url !== '' ) {
				$items[] = array(
					'name' => $regional_category->name,
					'url'  => $regional_url,
				);
			}
		}

		$post_title = trim( (string) get_the_title( $post_id ) );
		if ( $post_title !== '' ) {
			$items[] = array(
				'name' => $post_title,
				'url'  => '',
			);
		}

		return $items;
	}
}

if ( ! function_exists( 'pera_schema_extract_visible_faq_items_from_post' ) ) {
	/**
	 * Parse visible FAQ content from post HTML when possible.
	 *
	 * Supports:
	 * - details/summary FAQ accordions.
	 * - Gutenberg details blocks with "faq" in class/anchor.
	 * - Common FAQ wrappers/classes and heading+paragraph pairs.
	 *
	 * @return array<int,array{question:string,answer:string}>
	 */
	function pera_schema_extract_visible_faq_items_from_post( int $post_id ): array {
		$content = (string) get_post_field( 'post_content', $post_id );
		if ( trim( $content ) === '' ) {
			return array();
		}

		// If plugin-specific FAQ blocks are present, let that plugin own FAQ schema.
		if ( pera_schema_has_plugin_faq_block( $post_id ) ) {
			return array();
		}

		// seo_faq_v2 is the authoritative theme FAQ source when populated.
		if ( function_exists( 'pera_get_single_post_faq_v2_items' ) && ! empty( pera_get_single_post_faq_v2_items( $post_id ) ) ) {
			return array();
		}

		$content = (string) apply_filters( 'the_content', $content );
		if ( trim( wp_strip_all_tags( $content ) ) === '' ) {
			return array();
		}

		$items = array();
		$dom   = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		$details_nodes = $xpath->query(
			"//details[contains(concat(' ', normalize-space(translate(@class,'FAQ','faq')), ' '), ' faq-item ')]"
			. "|//*[contains(concat(' ', normalize-space(translate(@class,'FAQ','faq')), ' '), ' faq-section ') or contains(concat(' ', normalize-space(translate(@class,'FAQ','faq')), ' '), ' faq-accordion ')]//details"
		);

		if ( $details_nodes instanceof DOMNodeList ) {
			foreach ( $details_nodes as $details_node ) {
				if ( ! ( $details_node instanceof DOMElement ) ) {
					continue;
				}

				$summary_nodes = $xpath->query( './summary', $details_node );
				if ( ! ( $summary_nodes instanceof DOMNodeList ) || $summary_nodes->length < 1 ) {
					continue;
				}

				$summary_node = $summary_nodes->item( 0 );
				$question     = $summary_node ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $summary_node->textContent ) ) ) : '';
				if ( $question === '' ) {
					continue;
				}

				$answer = '';
				$answer_nodes = $xpath->query(
					".//*[contains(concat(' ', normalize-space(translate(@class,'FAQ','faq')), ' '), ' faq-answer ')]",
					$details_node
				);

				if ( $answer_nodes instanceof DOMNodeList && $answer_nodes->length > 0 ) {
					$answer_node = $answer_nodes->item( 0 );
					$answer      = $answer_node ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $answer_node->textContent ) ) ) : '';
				}

				if ( $answer === '' ) {
					$answer_parts = array();
					foreach ( $details_node->childNodes as $child_node ) {
						if ( $child_node instanceof DOMElement && strtolower( $child_node->tagName ) === 'summary' ) {
							continue;
						}

						$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $child_node->textContent ) ) );
						if ( $text !== '' ) {
							$answer_parts[] = $text;
						}
					}

					$answer = trim( implode( ' ', $answer_parts ) );
				}

				if ( $answer !== '' ) {
					$items[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
			}
		}

		$faq_containers = $xpath->query(
			"//*[contains(translate(@class,'FAQ','faq'),'faq') or contains(translate(@id,'FAQ','faq'),'faq')]"
		);

		if ( empty( $items ) && $faq_containers instanceof DOMNodeList ) {
			foreach ( $faq_containers as $container ) {
				$question_nodes = $xpath->query( ".//h2|.//h3|.//h4|.//strong", $container );

				if ( ! ( $question_nodes instanceof DOMNodeList ) ) {
					continue;
				}

				foreach ( $question_nodes as $question_node ) {
					$question = trim( wp_strip_all_tags( (string) $question_node->textContent ) );
					if ( $question === '' ) {
						continue;
					}

					if (
						$question_node instanceof DOMElement
						&& in_array( strtolower( $question_node->tagName ), array( 'h2', 'h3', 'h4' ), true )
						&& preg_match( '/\\b(frequently\\s+asked\\s+questions|faq)\\b/i', $question )
					) {
						continue;
					}

					$answer = '';
					$sibling = $question_node->nextSibling;
					while ( $sibling ) {
						if ( $sibling instanceof DOMElement ) {
							$text = trim( wp_strip_all_tags( (string) $sibling->textContent ) );
							if ( $text !== '' ) {
								$answer = $text;
								break;
							}
						}
						$sibling = $sibling->nextSibling;
					}

					if ( $answer !== '' ) {
						$items[] = array(
							'question' => $question,
							'answer'   => $answer,
						);
					}
				}
			}
		}

		$items = array_values(
			array_filter(
				$items,
				static function ( array $item ): bool {
					return ! empty( $item['question'] ) && ! empty( $item['answer'] );
				}
			)
		);

		$items = array_slice( $items, 0, 12 );

		if ( count( $items ) === 1 ) {
			$single_question = isset( $items[0]['question'] ) ? trim( (string) $items[0]['question'] ) : '';
			if ( preg_match( '/\\bfrequently\\s+asked\\s+questions\\b/i', $single_question ) || preg_match( '/^faq$/i', $single_question ) ) {
				$items = array();
			}
		}

		$items = (array) apply_filters( 'pera_schema_guide_like_faq_items', $items, $post_id );

		return (array) apply_filters( 'pera_schema_regional_guide_faq_items', $items, $post_id );
	}
}
