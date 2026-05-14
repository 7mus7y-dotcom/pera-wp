<?php
/**
 * Editorial updated date for posts.
 *
 * Adds an admin-only custom date field for controlling the public updated date.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PERA_EDITORIAL_UPDATED_DATE_META_KEY' ) ) {
	define( 'PERA_EDITORIAL_UPDATED_DATE_META_KEY', '_pera_editorial_updated_date' );
}

add_action( 'add_meta_boxes_post', 'pera_add_editorial_updated_date_meta_box' );

/**
 * Add the editorial updated date meta box to standard posts.
 */
function pera_add_editorial_updated_date_meta_box() {
	add_meta_box(
		'pera-editorial-updated-date',
		__( 'Editorial updated date', 'peraproperty' ),
		'pera_render_editorial_updated_date_meta_box',
		'post',
		'side',
		'default'
	);
}

/**
 * Render the editorial updated date meta box.
 *
 * @param WP_Post $post Current post object.
 */
function pera_render_editorial_updated_date_meta_box( $post ) {
	$value = get_post_meta( $post->ID, PERA_EDITORIAL_UPDATED_DATE_META_KEY, true );
	$value = is_string( $value ) ? $value : '';

	wp_nonce_field( 'pera_save_editorial_updated_date', 'pera_editorial_updated_date_nonce' );
	?>
	<p>
		<label for="pera-editorial-updated-date-field">
			<?php esc_html_e( 'Updated date', 'peraproperty' ); ?>
		</label>
	</p>

	<p>
		<input
			type="date"
			id="pera-editorial-updated-date-field"
			name="pera_editorial_updated_date"
			value="<?php echo esc_attr( $value ); ?>"
			class="widefat"
		/>
	</p>

	<p class="description">
		<?php esc_html_e( 'Controls the public Updated date shown on post cards and single articles. Leave empty to use the WordPress modified date.', 'peraproperty' ); ?>
	</p>
	<?php
}

add_action( 'save_post_post', 'pera_save_editorial_updated_date_meta_box' );

/**
 * Save the editorial updated date for standard posts.
 *
 * @param int $post_id Current post ID.
 */
function pera_save_editorial_updated_date_meta_box( $post_id ) {
	if ( ! isset( $_POST['pera_editorial_updated_date_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['pera_editorial_updated_date_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'pera_save_editorial_updated_date' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$date = isset( $_POST['pera_editorial_updated_date'] )
		? sanitize_text_field( wp_unslash( $_POST['pera_editorial_updated_date'] ) )
		: '';

	if ( '' === $date ) {
		delete_post_meta( $post_id, PERA_EDITORIAL_UPDATED_DATE_META_KEY );
		return;
	}

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return;
	}

	$date_parts = explode( '-', $date );

	if ( 3 !== count( $date_parts ) || ! checkdate( (int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0] ) ) {
		return;
	}

	update_post_meta( $post_id, PERA_EDITORIAL_UPDATED_DATE_META_KEY, $date );
}

/**
 * Get the raw editorial updated date value.
 *
 * @param int|null $post_id Optional post ID.
 * @return string
 */
function pera_get_editorial_updated_date_raw( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$value = get_post_meta( $post_id, PERA_EDITORIAL_UPDATED_DATE_META_KEY, true );

	return is_string( $value ) ? $value : '';
}

/**
 * Get the public updated date, preferring the editorial updated date when set.
 *
 * @param string   $format  Optional date format.
 * @param int|null $post_id Optional post ID.
 * @return string
 */
function pera_get_public_updated_date( $format = '', $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$format      = $format ? $format : get_option( 'date_format' );
	$manual_date = pera_get_editorial_updated_date_raw( $post_id );

	if ( $manual_date ) {
		$timestamp = strtotime( $manual_date );

		if ( false !== $timestamp ) {
			return wp_date( $format, $timestamp );
		}
	}

	return get_the_modified_date( $format, $post_id );
}

/**
 * Get the datetime attribute value for the public updated date.
 *
 * @param int|null $post_id Optional post ID.
 * @return string
 */
function pera_get_public_updated_datetime_attr( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$manual_date = pera_get_editorial_updated_date_raw( $post_id );

	if ( $manual_date ) {
		$timestamp = strtotime( $manual_date );

		if ( false !== $timestamp ) {
			return wp_date( DATE_W3C, $timestamp );
		}
	}

	return get_the_modified_date( DATE_W3C, $post_id );
}
