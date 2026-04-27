<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_get_district_page_faqs' ) ) {
	/**
	 * Return district FAQ rows stored in term meta.
	 *
	 * @param int|null $term_id Optional district term ID. Defaults to queried district term.
	 * @return array<int,array{question:string,answer:string}>
	 */
	function pera_get_district_page_faqs( $term_id = null ): array {
		if ( null === $term_id ) {
			$queried = get_queried_object();
			if ( ! ( $queried instanceof WP_Term ) || 'district' !== $queried->taxonomy ) {
				return array();
			}

			$term_id = (int) $queried->term_id;
		}

		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			return array();
		}

		$term = get_term( $term_id, 'district' );
		if ( ! ( $term instanceof WP_Term ) ) {
			return array();
		}

		$stored = get_term_meta( $term_id, 'district_page_faqs', true );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return array();
		}

		$faqs = array();
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$question = isset( $row['question'] ) ? trim( (string) $row['question'] ) : '';
			$answer   = isset( $row['answer'] ) ? trim( (string) $row['answer'] ) : '';

			if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
				continue;
			}

			$faqs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		return $faqs;
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
		<div class="article-body">
			<h2><?php echo esc_html( $heading ); ?></h2>
			<?php foreach ( $faqs as $faq ) : ?>
				<?php
				$question = isset( $faq['question'] ) ? trim( (string) $faq['question'] ) : '';
				$answer   = isset( $faq['answer'] ) ? trim( (string) $faq['answer'] ) : '';
				if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
					continue;
				}
				?>
				<details class="card-shell mb-sm">
					<summary><strong><?php echo esc_html( $question ); ?></strong></summary>
					<div><?php echo wp_kses_post( wpautop( $answer ) ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'pera_render_faq_schema' ) ) {
	/**
	 * Render FAQPage JSON-LD for FAQ rows.
	 *
	 * @param array<int,array{question:string,answer:string}> $faqs FAQ rows.
	 */
	function pera_render_faq_schema( $faqs ): void {
		if ( ! is_array( $faqs ) || empty( $faqs ) ) {
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

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}

if ( ! function_exists( 'pera_render_district_page_faqs' ) ) {
	/**
	 * Render district FAQs in archive page body.
	 *
	 * @param WP_Term|int|null $term Optional district term.
	 */
	function pera_render_district_page_faqs( $term = null ): void {
		$term_obj = null;
		if ( $term instanceof WP_Term ) {
			$term_obj = $term;
		} elseif ( is_numeric( $term ) ) {
			$fetched = get_term( (int) $term, 'district' );
			if ( $fetched instanceof WP_Term ) {
				$term_obj = $fetched;
			}
		} else {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Term ) {
				$term_obj = $queried;
			}
		}

		if ( ! ( $term_obj instanceof WP_Term ) || 'district' !== $term_obj->taxonomy ) {
			return;
		}

		$faqs = pera_get_district_page_faqs( (int) $term_obj->term_id );
		if ( empty( $faqs ) ) {
			return;
		}
		?>
		<section class="section">
			<div class="container">
				<?php pera_render_faq_html( $faqs, sprintf( 'Frequently Asked Questions About %s', $term_obj->name ) ); ?>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'pera_render_district_page_faq_schema' ) ) {
	/**
	 * Render district FAQ schema on district archives.
	 *
	 * @param WP_Term|int|null $term Optional district term.
	 */
	function pera_render_district_page_faq_schema( $term = null ): void {
		$term_obj = null;
		if ( $term instanceof WP_Term ) {
			$term_obj = $term;
		} elseif ( is_numeric( $term ) ) {
			$fetched = get_term( (int) $term, 'district' );
			if ( $fetched instanceof WP_Term ) {
				$term_obj = $fetched;
			}
		} else {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Term ) {
				$term_obj = $queried;
			}
		}

		if ( ! ( $term_obj instanceof WP_Term ) || 'district' !== $term_obj->taxonomy ) {
			return;
		}

		$faqs = pera_get_district_page_faqs( (int) $term_obj->term_id );
		pera_render_faq_schema( $faqs );
	}
}

if ( ! function_exists( 'pera_district_faq_row_template' ) ) {
	/**
	 * Render a single district FAQ admin row.
	 */
	function pera_district_faq_row_template( int $index, string $question = '', string $answer = '' ): void {
		?>
		<div class="pera-district-faq-row">
			<p>
				<label><strong><?php esc_html_e( 'Question', 'peraproperty' ); ?></strong></label>
				<input type="text" class="widefat" name="district_page_faqs[<?php echo esc_attr( (string) $index ); ?>][question]" value="<?php echo esc_attr( $question ); ?>" />
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Answer', 'peraproperty' ); ?></strong></label>
				<textarea class="widefat" rows="4" name="district_page_faqs[<?php echo esc_attr( (string) $index ); ?>][answer]"><?php echo esc_textarea( $answer ); ?></textarea>
			</p>
			<p><button type="button" class="button-link-delete pera-remove-faq-row"><?php esc_html_e( 'Remove FAQ', 'peraproperty' ); ?></button></p>
		</div>
		<?php
	}
}

if ( ! function_exists( 'pera_render_district_add_faq_fields' ) ) {
	/**
	 * Render FAQ UI on add district term screen.
	 */
	function pera_render_district_add_faq_fields( string $taxonomy ): void {
		if ( 'district' !== $taxonomy ) {
			return;
		}
		?>
		<div class="form-field term-group">
			<label for="district-page-faqs-wrapper"><?php esc_html_e( 'District FAQs', 'peraproperty' ); ?></label>
			<?php wp_nonce_field( 'pera_district_faq_save', 'pera_district_faq_nonce' ); ?>
			<input type="hidden" id="district-page-faqs-payload" name="district_page_faqs_payload" value="[]" />
			<div id="district-page-faqs-wrapper" class="pera-district-faqs-wrapper" data-next-index="1">
				<?php pera_district_faq_row_template( 0 ); ?>
			</div>
			<p><button type="button" class="button" id="pera-add-faq-row"><?php esc_html_e( 'Add FAQ', 'peraproperty' ); ?></button></p>
		</div>
		<?php
	}
	add_action( 'district_add_form_fields', 'pera_render_district_add_faq_fields' );
}

if ( ! function_exists( 'pera_render_district_edit_faq_fields' ) ) {
	/**
	 * Render FAQ UI on edit district term screen.
	 */
	function pera_render_district_edit_faq_fields( WP_Term $term ): void {
		if ( 'district' !== $term->taxonomy ) {
			return;
		}

		$faqs = pera_get_district_page_faqs( (int) $term->term_id );
		if ( empty( $faqs ) ) {
			$faqs = array( array( 'question' => '', 'answer' => '' ) );
		}
		?>
		<tr class="form-field term-group-wrap">
			<th scope="row"><label for="district-page-faqs-wrapper"><?php esc_html_e( 'District FAQs', 'peraproperty' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'pera_district_faq_save', 'pera_district_faq_nonce' ); ?>
				<input type="hidden" id="district-page-faqs-payload" name="district_page_faqs_payload" value="<?php echo esc_attr( wp_json_encode( array_values( $faqs ) ) ); ?>" />
				<div id="district-page-faqs-wrapper" class="pera-district-faqs-wrapper" data-next-index="<?php echo esc_attr( (string) count( $faqs ) ); ?>">
					<?php foreach ( $faqs as $index => $faq ) : ?>
						<?php
						$question = isset( $faq['question'] ) ? (string) $faq['question'] : '';
						$answer   = isset( $faq['answer'] ) ? (string) $faq['answer'] : '';
						pera_district_faq_row_template( (int) $index, $question, $answer );
						?>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" id="pera-add-faq-row"><?php esc_html_e( 'Add FAQ', 'peraproperty' ); ?></button></p>
			</td>
		</tr>
		<?php
	}
	add_action( 'district_edit_form_fields', 'pera_render_district_edit_faq_fields' );
}

if ( ! function_exists( 'pera_save_district_term_faqs' ) ) {
	/**
	 * Save district FAQ term meta from add/edit forms.
	 */
	function pera_save_district_term_faqs( int $term_id, int $tt_id, string $taxonomy ): void {
		unset( $tt_id );

		if ( 'district' !== $taxonomy ) {
			return;
		}

		$taxonomy_obj = get_taxonomy( $taxonomy );
		$required_cap = ( $taxonomy_obj && isset( $taxonomy_obj->cap->edit_terms ) ) ? $taxonomy_obj->cap->edit_terms : 'manage_categories';
		if ( ! current_user_can( $required_cap ) ) {
			return;
		}

		$nonce = isset( $_POST['pera_district_faq_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pera_district_faq_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_district_faq_save' ) ) {
			return;
		}

		$raw_rows                     = array();
		$has_valid_rows_submission     = false;
		$has_explicit_empty_submission = false;

		if ( isset( $_POST['district_page_faqs_payload'] ) ) {
			$payload_raw = wp_unslash( $_POST['district_page_faqs_payload'] );
			if ( is_string( $payload_raw ) && '' !== $payload_raw ) {
				$decoded = json_decode( $payload_raw, true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
					$raw_rows                     = $decoded;
					$has_valid_rows_submission     = true;
					$has_explicit_empty_submission = empty( $decoded );
				}
			}
		}

		if ( ! $has_valid_rows_submission && isset( $_POST['district_page_faqs'] ) ) {
			$legacy_rows = wp_unslash( $_POST['district_page_faqs'] );
			if ( is_array( $legacy_rows ) ) {
				$raw_rows                     = $legacy_rows;
				$has_valid_rows_submission     = true;
				$has_explicit_empty_submission = empty( $legacy_rows );
			}
		}

		if ( ! $has_valid_rows_submission ) {
			return;
		}

		$sanitized_rows = array();
		foreach ( $raw_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$question = isset( $row['question'] ) ? sanitize_text_field( $row['question'] ) : '';
			$answer   = isset( $row['answer'] ) ? wp_kses_post( $row['answer'] ) : '';

			if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
				continue;
			}

			$sanitized_rows[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		if ( empty( $sanitized_rows ) ) {
			if ( $has_explicit_empty_submission ) {
				delete_term_meta( $term_id, 'district_page_faqs' );
			}
			return;
		}

		update_term_meta( $term_id, 'district_page_faqs', array_values( $sanitized_rows ) );
	}
	add_action( 'created_term', 'pera_save_district_term_faqs', 10, 3 );
	add_action( 'edited_term', 'pera_save_district_term_faqs', 10, 3 );
}

if ( ! function_exists( 'pera_enqueue_district_faq_admin_assets' ) ) {
	/**
	 * Load district FAQ admin script only on district taxonomy term pages.
	 */
	function pera_enqueue_district_faq_admin_assets( string $hook_suffix ): void {
		if ( 'edit-tags.php' !== $hook_suffix && 'term.php' !== $hook_suffix ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'district' !== $screen->taxonomy ) {
			return;
		}

		if ( ! in_array( $screen->base, array( 'edit-tags', 'term' ), true ) ) {
			return;
		}

		$admin_css_path = get_stylesheet_directory() . '/assets/css/admin-district-term.css';
		if ( file_exists( $admin_css_path ) ) {
			wp_enqueue_style(
				'pera-district-term-admin',
				get_stylesheet_directory_uri() . '/assets/css/admin-district-term.css',
				array(),
				(string) filemtime( $admin_css_path )
			);
		}

		wp_enqueue_script(
			'pera-district-faqs-admin',
			get_stylesheet_directory_uri() . '/assets/js/admin-district-faqs.js',
			array(),
			filemtime( get_stylesheet_directory() . '/assets/js/admin-district-faqs.js' ),
			true
		);
	}
	add_action( 'admin_enqueue_scripts', 'pera_enqueue_district_faq_admin_assets' );
}
