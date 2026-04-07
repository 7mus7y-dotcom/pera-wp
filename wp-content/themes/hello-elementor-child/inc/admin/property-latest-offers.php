<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_property_latest_offers_meta_key' ) ) {
	function pera_property_latest_offers_meta_key(): string {
		return '_pera_latest_offers';
	}
}

if ( ! function_exists( 'pera_property_latest_offers_get_rows' ) ) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function pera_property_latest_offers_get_rows( int $post_id ): array {
		$rows = get_post_meta( $post_id, pera_property_latest_offers_meta_key(), true );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$normalized[] = array(
				'type'          => isset( $row['type'] ) ? (string) $row['type'] : '',
				'floor'         => isset( $row['floor'] ) ? (string) $row['floor'] : '',
				'net_sqm'       => isset( $row['net_sqm'] ) ? (string) $row['net_sqm'] : '',
				'gross_sqm'     => isset( $row['gross_sqm'] ) ? (string) $row['gross_sqm'] : '',
				'list_price'    => isset( $row['list_price'] ) ? (string) $row['list_price'] : '',
				'cash_price'    => isset( $row['cash_price'] ) ? (string) $row['cash_price'] : '',
				'notes'         => isset( $row['notes'] ) ? (string) $row['notes'] : '',
				'floor_plan_id' => isset( $row['floor_plan_id'] ) ? (int) $row['floor_plan_id'] : 0,
			);
		}

		return $normalized;
	}
}

if ( ! function_exists( 'pera_property_latest_offers_register_meta_box' ) ) {
	function pera_property_latest_offers_register_meta_box(): void {
		add_meta_box(
			'pera-latest-offers',
			__( 'Latest Offers', 'hello-elementor-child' ),
			'pera_property_latest_offers_render_meta_box',
			'property',
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes_property', 'pera_property_latest_offers_register_meta_box' );

if ( ! function_exists( 'pera_property_latest_offers_get_attachment_label' ) ) {
	function pera_property_latest_offers_get_attachment_label( int $attachment_id ): string {
		if ( $attachment_id < 1 ) {
			return '';
		}

		$title = get_the_title( $attachment_id );
		if ( is_string( $title ) && $title !== '' ) {
			return $title;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( is_string( $file_path ) && $file_path !== '' ) {
			return wp_basename( $file_path );
		}

		return '';
	}
}

if ( ! function_exists( 'pera_property_latest_offers_render_meta_box' ) ) {
	function pera_property_latest_offers_render_meta_box( WP_Post $post ): void {
		$rows = pera_property_latest_offers_get_rows( (int) $post->ID );

		wp_nonce_field( 'pera_property_latest_offers_save', 'pera_property_latest_offers_nonce' );
		?>
		<div class="pera-latest-offers-wrap">
			<div class="pera-latest-offers-scroll">
				<table class="widefat striped pera-latest-offers-table">
					<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Floor', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Net (m²)', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Gross (m²)', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'List price ($)', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Cash price ($)', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Floor plan', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'hello-elementor-child' ); ?></th>
					</tr>
					</thead>
					<tbody data-pera-latest-offers-rows>
					<?php
					if ( empty( $rows ) ) {
						$rows[] = array(
							'type'          => '',
							'floor'         => '',
							'net_sqm'       => '',
							'gross_sqm'     => '',
							'list_price'    => '',
							'cash_price'    => '',
							'notes'         => '',
							'floor_plan_id' => 0,
						);
					}

					foreach ( $rows as $index => $row ) {
						$floor_plan_id    = isset( $row['floor_plan_id'] ) ? (int) $row['floor_plan_id'] : 0;
						$floor_plan_label = pera_property_latest_offers_get_attachment_label( $floor_plan_id );
						$floor_plan_url   = $floor_plan_id > 0 ? wp_get_attachment_url( $floor_plan_id ) : '';
						?>
						<tr class="pera-latest-offers-row">
							<td><input type="text" class="regular-text" name="pera_latest_offers[<?php echo esc_attr( (string) $index ); ?>][type]" value="<?php echo esc_attr( (string) $row['type'] ); ?>" /></td>
							<td><input type="text" class="small-text" name="pera_latest_offers[<?php echo esc_attr( (string) $index ); ?>][floor]" value="<?php echo esc_attr( (string) $row['floor'] ); ?>" /></td>
							<td><input type="number" step="0.01" min="0" class="small-text" name="pera_latest_offers[<?php echo esc_attr( (string) $index ); ?>][net_sqm]" value="<?php echo esc_attr( (string) $row['net_sqm'] ); ?>" /></td>
							<td><input type="number" step="0.01" min="0" class="small-text" name="pera_latest_offers[<?php echo esc_attr( (string) $index ); ?>][gross_sqm]" value="<?php echo esc_attr( (string) $row['gross_sqm'] ); ?>" /></td>
							<td><input type="number" step="1" min="0" class="small-text" name="pera_latest_offers[<?php echo esc_attr( (string) $index ); ?>][list_price]" value="<?php echo esc_attr( (string) $row['list_price'] ); ?>" /></td>
							<td><input type="number" step="1" min="0" class="small-text" name="pera_latest_offers[<?php echo esc_attr( (string) $index ); ?>][cash_price]" value="<?php echo esc_attr( (string) $row['cash_price'] ); ?>" /></td>
							<td><textarea rows="2" cols="18" name="pera_latest_offers[<?php echo esc_attr( (string) $index ); ?>][notes]"><?php echo esc_textarea( (string) $row['notes'] ); ?></textarea></td>
							<td>
								<input type="hidden" class="pera-floor-plan-id" name="pera_latest_offers[<?php echo esc_attr( (string) $index ); ?>][floor_plan_id]" value="<?php echo esc_attr( (string) $floor_plan_id ); ?>" />
								<div class="pera-floor-plan-label"><?php echo esc_html( $floor_plan_label !== '' ? $floor_plan_label : __( 'No file selected', 'hello-elementor-child' ) ); ?></div>
								<div class="pera-floor-plan-preview">
									<?php if ( is_string( $floor_plan_url ) && $floor_plan_url !== '' ) : ?>
										<a href="<?php echo esc_url( $floor_plan_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'hello-elementor-child' ); ?></a>
									<?php endif; ?>
								</div>
								<div class="pera-floor-plan-actions">
									<button type="button" class="button button-small pera-floor-plan-select"><?php esc_html_e( 'Select JPG', 'hello-elementor-child' ); ?></button>
									<button type="button" class="button button-small pera-floor-plan-remove" <?php disabled( $floor_plan_id < 1 ); ?>><?php esc_html_e( 'Remove', 'hello-elementor-child' ); ?></button>
								</div>
							</td>
							<td>
								<button type="button" class="button-link-delete pera-latest-offers-delete-row"><?php esc_html_e( 'Delete row', 'hello-elementor-child' ); ?></button>
							</td>
						</tr>
						<?php
					}
					?>
					</tbody>
				</table>
			</div>

			<p>
				<button type="button" class="button" data-pera-latest-offers-add-row><?php esc_html_e( 'Add Row', 'hello-elementor-child' ); ?></button>
			</p>
		</div>
		<script type="text/html" id="tmpl-pera-latest-offers-row">
			<tr class="pera-latest-offers-row">
				<td><input type="text" class="regular-text" name="pera_latest_offers[__index__][type]" value="" /></td>
				<td><input type="text" class="small-text" name="pera_latest_offers[__index__][floor]" value="" /></td>
				<td><input type="number" step="0.01" min="0" class="small-text" name="pera_latest_offers[__index__][net_sqm]" value="" /></td>
				<td><input type="number" step="0.01" min="0" class="small-text" name="pera_latest_offers[__index__][gross_sqm]" value="" /></td>
				<td><input type="number" step="1" min="0" class="small-text" name="pera_latest_offers[__index__][list_price]" value="" /></td>
				<td><input type="number" step="1" min="0" class="small-text" name="pera_latest_offers[__index__][cash_price]" value="" /></td>
				<td><textarea rows="2" cols="18" name="pera_latest_offers[__index__][notes]"></textarea></td>
				<td>
					<input type="hidden" class="pera-floor-plan-id" name="pera_latest_offers[__index__][floor_plan_id]" value="" />
					<div class="pera-floor-plan-label"><?php esc_html_e( 'No file selected', 'hello-elementor-child' ); ?></div>
					<div class="pera-floor-plan-preview"></div>
					<div class="pera-floor-plan-actions">
						<button type="button" class="button button-small pera-floor-plan-select"><?php esc_html_e( 'Select JPG', 'hello-elementor-child' ); ?></button>
						<button type="button" class="button button-small pera-floor-plan-remove" disabled><?php esc_html_e( 'Remove', 'hello-elementor-child' ); ?></button>
					</div>
				</td>
				<td><button type="button" class="button-link-delete pera-latest-offers-delete-row"><?php esc_html_e( 'Delete row', 'hello-elementor-child' ); ?></button></td>
			</tr>
		</script>
		<?php
	}
}

if ( ! function_exists( 'pera_property_latest_offers_enqueue_admin_assets' ) ) {
	function pera_property_latest_offers_enqueue_admin_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'post.php' && $hook_suffix !== 'post-new.php' ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->post_type !== 'property' ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'pera-property-latest-offers-admin',
			get_stylesheet_directory_uri() . '/assets/js/admin-property-latest-offers.js',
			array( 'jquery' ),
			pera_get_asset_version( '/assets/js/admin-property-latest-offers.js' ),
			true
		);

		wp_enqueue_style(
			'pera-property-latest-offers-admin',
			get_stylesheet_directory_uri() . '/assets/css/admin-property-latest-offers.css',
			array(),
			pera_get_asset_version( '/assets/css/admin-property-latest-offers.css' )
		);
	}
}
add_action( 'admin_enqueue_scripts', 'pera_property_latest_offers_enqueue_admin_assets' );

if ( ! function_exists( 'pera_property_latest_offers_sanitize_decimal' ) ) {
	function pera_property_latest_offers_sanitize_decimal( $value ): string {
		$raw = is_scalar( $value ) ? (string) $value : '';
		$raw = str_replace( ',', '.', $raw );
		$raw = preg_replace( '/[^0-9.\-]/', '', $raw );

		if ( $raw === null || $raw === '' ) {
			return '';
		}

		$number = (float) $raw;
		if ( $number < 0 ) {
			$number = 0;
		}

		return (string) $number;
	}
}

if ( ! function_exists( 'pera_property_latest_offers_sanitize_price' ) ) {
	function pera_property_latest_offers_sanitize_price( $value ): string {
		$raw = is_scalar( $value ) ? (string) $value : '';
		$raw = preg_replace( '/[^0-9]/', '', $raw );

		if ( $raw === null || $raw === '' ) {
			return '';
		}

		$number = (int) $raw;
		if ( $number < 0 ) {
			$number = 0;
		}

		return (string) $number;
	}
}

if ( ! function_exists( 'pera_property_latest_offers_save' ) ) {
	function pera_property_latest_offers_save( int $post_id, WP_Post $post ): void {
		if ( $post->post_type !== 'property' ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['pera_property_latest_offers_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['pera_property_latest_offers_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'pera_property_latest_offers_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$submitted = isset( $_POST['pera_latest_offers'] ) ? wp_unslash( $_POST['pera_latest_offers'] ) : array();
		if ( ! is_array( $submitted ) ) {
			delete_post_meta( $post_id, pera_property_latest_offers_meta_key() );
			return;
		}

		$clean = array();
		foreach ( $submitted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$item = array(
				'type'          => isset( $row['type'] ) ? sanitize_text_field( (string) $row['type'] ) : '',
				'floor'         => isset( $row['floor'] ) ? sanitize_text_field( (string) $row['floor'] ) : '',
				'net_sqm'       => isset( $row['net_sqm'] ) ? pera_property_latest_offers_sanitize_decimal( $row['net_sqm'] ) : '',
				'gross_sqm'     => isset( $row['gross_sqm'] ) ? pera_property_latest_offers_sanitize_decimal( $row['gross_sqm'] ) : '',
				'list_price'    => isset( $row['list_price'] ) ? pera_property_latest_offers_sanitize_price( $row['list_price'] ) : '',
				'cash_price'    => isset( $row['cash_price'] ) ? pera_property_latest_offers_sanitize_price( $row['cash_price'] ) : '',
				'notes'         => isset( $row['notes'] ) ? sanitize_textarea_field( (string) $row['notes'] ) : '',
				'floor_plan_id' => isset( $row['floor_plan_id'] ) ? absint( $row['floor_plan_id'] ) : 0,
			);

			$is_empty =
				$item['type'] === ''
				&& $item['floor'] === ''
				&& $item['net_sqm'] === ''
				&& $item['gross_sqm'] === ''
				&& $item['list_price'] === ''
				&& $item['cash_price'] === ''
				&& $item['notes'] === ''
				&& (int) $item['floor_plan_id'] < 1;

			if ( $is_empty ) {
				continue;
			}

			$clean[] = $item;
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, pera_property_latest_offers_meta_key() );
			return;
		}

		update_post_meta( $post_id, pera_property_latest_offers_meta_key(), array_values( $clean ) );
	}
}
add_action( 'save_post_property', 'pera_property_latest_offers_save', 10, 2 );
