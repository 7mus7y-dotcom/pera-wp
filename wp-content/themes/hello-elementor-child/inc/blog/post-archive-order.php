<?php
/**
 * Blog archive ordering.
 *
 * Allows public post archives to be ordered by the selected date option.
 *
 * TODO: Consider ordering by _pera_editorial_updated_date once editorial dates are populated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pre_get_posts', 'pera_order_blog_archives_by_selected_date' );

/**
 * Get supported blog archive sort options.
 *
 * @return array<string,array{label:string,orderby:string,order:string}>
 */
function pera_get_blog_archive_sort_options() {
	return array(
		'updated'   => array(
			'label'   => __( 'Last updated', 'peraproperty' ),
			'orderby' => 'modified',
			'order'   => 'DESC',
		),
		'published' => array(
			'label'   => __( 'Newest published', 'peraproperty' ),
			'orderby' => 'date',
			'order'   => 'DESC',
		),
		'oldest'    => array(
			'label'   => __( 'Oldest published', 'peraproperty' ),
			'orderby' => 'date',
			'order'   => 'ASC',
		),
	);
}

/**
 * Get the current blog archive sort key from the request.
 *
 * @return string
 */
function pera_get_blog_archive_sort_key() {
	$sort    = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'published';
	$options = pera_get_blog_archive_sort_options();

	return array_key_exists( $sort, $options ) ? $sort : 'published';
}

/**
 * Determine whether a query is the public main blog archive query.
 *
 * @param mixed $query WordPress query instance.
 * @return bool
 */
function pera_is_blog_archive_query( $query ) {
	return $query instanceof WP_Query
		&& $query->is_main_query()
		&& (
			$query->is_home()
			|| $query->is_category()
			|| $query->is_tag()
			|| $query->is_author()
			|| $query->is_date()
		);
}

/**
 * Order the front-end main post archive queries by the selected date option.
 *
 * @param WP_Query $query WordPress query instance.
 */
function pera_order_blog_archives_by_selected_date( $query ) {
	if ( is_admin() || ! pera_is_blog_archive_query( $query ) ) {
		return;
	}

	$options = pera_get_blog_archive_sort_options();
	$sort    = pera_get_blog_archive_sort_key();
	$choice  = $options[ $sort ];

	$query->set( 'orderby', $choice['orderby'] );
	$query->set( 'order', $choice['order'] );
}

/**
 * Render the blog archive sort control.
 */
function pera_render_blog_archive_sort_control() {
	global $wp_query;

	if ( is_admin() || ! pera_is_blog_archive_query( $wp_query ) ) {
		return;
	}

	$options      = pera_get_blog_archive_sort_options();
	$current_sort = pera_get_blog_archive_sort_key();
	$base_url     = remove_query_arg( array( 'paged', 'page' ), get_pagenum_link( 1, false ) );
	?>
	<nav class="blog-sort" aria-label="<?php esc_attr_e( 'Sort posts', 'peraproperty' ); ?>">
		<span class="blog-sort__label"><?php esc_html_e( 'Sort by:', 'peraproperty' ); ?></span>
		<div class="blog-sort__options">
			<?php foreach ( $options as $key => $option ) : ?>
				<?php
				$is_active = ( $key === $current_sort );
				$classes   = 'blog-sort__link' . ( $is_active ? ' is-active' : '' );
				$url       = add_query_arg( 'sort', $key, $base_url );
				?>
				<a
					class="<?php echo esc_attr( $classes ); ?>"
					href="<?php echo esc_url( $url ); ?>"
					<?php echo $is_active ? 'aria-current="true"' : ''; ?>
				>
					<?php echo esc_html( $option['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</nav>
	<?php
}
