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

if ( ! function_exists( 'pera_output_manual_faq_schema_field' ) ) {
	/**
	 * Temporarily output manually entered FAQ JSON-LD from the per-object field.
	 *
	 * The field is expected to contain the full script tag when needed, and is
	 * intentionally echoed without wrapping so existing manual entries remain
	 * compatible until schema ownership is consolidated.
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

		$did_output = true;
		$GLOBALS['pera_schema_faq_emitted'] = true;

		echo "\n<!-- Pera: Manual FAQ Schema (temporary) -->\n";
		echo $schema . "\n";
	}
}
add_action( 'wp_head', 'pera_output_manual_faq_schema_field', 35 );

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

		$faq_containers = $xpath->query(
			"//*[contains(translate(@class,'FAQ','faq'),'faq') or contains(translate(@id,'FAQ','faq'),'faq')]"
		);

		if ( $faq_containers instanceof DOMNodeList ) {
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

		$items = (array) apply_filters( 'pera_schema_guide_like_faq_items', $items, $post_id );

		return (array) apply_filters( 'pera_schema_regional_guide_faq_items', $items, $post_id );
	}
}
