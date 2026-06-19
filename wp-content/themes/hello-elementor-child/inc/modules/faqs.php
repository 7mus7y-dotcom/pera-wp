<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! function_exists( 'pera_parse_faq_pipe_text' ) ) {
	/**
	 * Parse newline-delimited FAQ rows in the canonical shared FAQ shape.
	 *
	 * Expected format: Question|Answer, one FAQ per line. Only the first pipe
	 * separates question from answer so answers can contain additional pipes.
	 *
	 * @param string $raw Raw textarea content.
	 * @return array<int,array{question:string,answer:string}>
	 */
	function pera_parse_faq_pipe_text( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array();
		}

		$faqs  = array();
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$lines = is_array( $lines ) ? $lines : array();

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || false === strpos( $line, '|' ) ) {
				continue;
			}

			$parts = explode( '|', $line, 2 );
			if ( count( $parts ) < 2 ) {
				continue;
			}

			$question = trim( wp_strip_all_tags( (string) $parts[0] ) );
			$answer   = trim( wp_kses_post( (string) $parts[1] ) );

			if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
				continue;
			}

			$faqs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);

			if ( count( $faqs ) >= 20 ) {
				break;
			}
		}

		return $faqs;
	}
}

if ( ! function_exists( 'pera_property_archive_term_raw_faq_value' ) ) {
	/**
	 * Resolve raw seo_faq_v2 text for a property taxonomy term.
	 *
	 * @return string
	 */
	function pera_property_archive_term_raw_faq_value( WP_Term $term ): string {
		$raw = '';

		if ( function_exists( 'get_field' ) ) {
			$value = get_field( 'seo_faq_v2', $term );
			if ( is_scalar( $value ) ) {
				$raw = trim( (string) $value );
			}
		}

		if ( '' === $raw ) {
			$value = get_term_meta( (int) $term->term_id, 'seo_faq_v2', true );
			if ( is_scalar( $value ) ) {
				$raw = trim( (string) $value );
			}
		}

		return $raw;
	}
}

if ( ! function_exists( 'pera_property_archive_taxonomy_is_attached_to_property' ) ) {
	/**
	 * Determine whether a taxonomy is attached to the property CPT.
	 */
	function pera_property_archive_taxonomy_is_attached_to_property( string $taxonomy ): bool {
		$property_taxonomies = get_object_taxonomies( 'property', 'names' );
		return in_array( $taxonomy, (array) $property_taxonomies, true );
	}
}

if ( ! function_exists( 'pera_get_property_archive_faq_items' ) ) {
	/**
	 * Return centralised FAQ rows for the active property archive context.
	 *
	 * @return array<int,array{question:string,answer:string}>
	 */
	function pera_get_property_archive_faq_items(): array {
		static $faq_items_by_context = array();

		$context_key = 'none';
		$raw         = '';

		if ( function_exists( 'pera_property_archive_is_clean_main_archive' ) && pera_property_archive_is_clean_main_archive() ) {
			$context_key = 'main';
			if ( function_exists( 'pera_property_archive_settings_field' ) ) {
				$value = pera_property_archive_settings_field( 'seo_faq_v2' );
				$raw   = is_scalar( $value ) ? trim( (string) $value ) : '';
			}
		} else {
			$term = get_queried_object();
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
				$taxonomy = (string) $term->taxonomy;
				$supported = function_exists( 'pera_get_property_archive_taxonomies' )
					? in_array( $taxonomy, pera_get_property_archive_taxonomies(), true )
					: taxonomy_exists( $taxonomy );

				if ( $supported && pera_property_archive_taxonomy_is_attached_to_property( $taxonomy ) ) {
					$context_key = 'term:' . $taxonomy . ':' . (int) $term->term_id;
					$raw         = pera_property_archive_term_raw_faq_value( $term );
				}
			}
		}

		if ( isset( $faq_items_by_context[ $context_key ] ) ) {
			return $faq_items_by_context[ $context_key ];
		}

		if ( '' === $raw ) {
			$faq_items_by_context[ $context_key ] = array();
			return $faq_items_by_context[ $context_key ];
		}

		$faq_items_by_context[ $context_key ] = pera_parse_faq_pipe_text( $raw );
		return $faq_items_by_context[ $context_key ];
	}
}

if ( ! function_exists( 'pera_render_faq_html' ) ) {
	/**
	 * Render FAQ rows in a details/summary accordion layout.
	 *
	 * @param array<int,array{question:string,answer:string}> $faqs FAQ rows.
	 * @param string                                          $heading Heading text.
	 */
	function pera_render_faq_html( $faqs, $heading ): void {
		if ( ! is_array( $faqs ) || empty( $faqs ) ) {
			return;
		}
		?>
		<div class="faq-section article-body">
			<h2><?php echo esc_html( $heading ); ?></h2>
			<div class="faq-accordion">
				<?php
				$faq_index = 0;
				foreach ( $faqs as $faq ) :
					$question = isset( $faq['question'] ) ? trim( (string) $faq['question'] ) : '';
					$answer   = isset( $faq['answer'] ) ? trim( (string) $faq['answer'] ) : '';
					if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
						continue;
					}
					?>
					<details class="faq-item"<?php echo 0 === $faq_index ? ' open' : ''; ?>>
						<summary><?php echo esc_html( $question ); ?></summary>
						<div class="faq-answer"><?php echo wp_kses_post( wpautop( $answer ) ); ?></div>
					</details>
					<?php $faq_index++; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'pera_render_faq_schema' ) ) {
	/**
	 * Render FAQPage JSON-LD for FAQ rows.
	 *
	 * @param array<int,array{question:string,answer:string}> $faqs FAQ rows.
	 * @param array<string,mixed>                          $context Optional schema ownership context.
	 */
	function pera_render_faq_schema( $faqs, array $context = array() ): void {
		if ( ! is_array( $faqs ) || empty( $faqs ) ) {
			return;
		}
		if ( ! empty( $GLOBALS['pera_schema_faq_emitted'] ) ) {
			return;
		}
		if (
			function_exists( 'pera_schema_should_emit_type' )
			&& ! pera_schema_should_emit_type(
				'FAQPage',
				array_merge(
					array( 'context' => 'faq_renderer' ),
					$context
				)
			)
		) {
			return;
		}

		$main_entity = array();

		foreach ( $faqs as $faq ) {
			$question = isset( $faq['question'] ) ? trim( (string) $faq['question'] ) : '';
			$answer   = isset( $faq['answer'] ) ? trim( (string) $faq['answer'] ) : '';
			$answer   = wp_strip_all_tags( $answer );

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$main_entity[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( empty( $main_entity ) ) {
			return;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $main_entity,
		);

		$GLOBALS['pera_schema_faq_emitted'] = true;
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
