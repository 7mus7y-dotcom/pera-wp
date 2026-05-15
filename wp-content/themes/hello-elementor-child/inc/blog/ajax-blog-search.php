<?php
/**
 * Progressive AJAX search for public blog archives.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_get_blog_archive_sort_options' ) ) {
	require_once get_stylesheet_directory() . '/inc/blog/post-archive-order.php';
}

add_action( 'wp_ajax_pera_blog_search', 'pera_ajax_blog_search' );
add_action( 'wp_ajax_nopriv_pera_blog_search', 'pera_ajax_blog_search' );

/**
 * Get a sanitized AJAX request value.
 *
 * @param string $key Request key.
 * @return string
 */
function pera_blog_search_request_value( $key ) {
	if ( ! isset( $_POST[ $key ] ) ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
}

/**
 * Render post cards for a blog search query.
 *
 * @param WP_Query $query Search query.
 * @return string
 */
function pera_blog_search_render_grid( WP_Query $query ) {
	ob_start();
	?>
	<div class="cards-masonry">
		<?php
		while ( $query->have_posts() ) :
			$query->the_post();

			set_query_var(
				'pera_post_card_args',
				array(
					'variant'      => 'grid',
					'card_classes' => '',
				)
			);

			get_template_part( 'parts/post-card' );
		endwhile;

		set_query_var( 'pera_post_card_args', null );
		wp_reset_postdata();
		?>
	</div>
	<?php

	return (string) ob_get_clean();
}

/**
 * Render blog search pagination.
 *
 * @param WP_Query $query Search query.
 * @param int      $paged Current page.
 * @param array    $query_args Query arguments to preserve on links.
 * @return string
 */
function pera_blog_search_render_pagination( WP_Query $query, $paged, array $query_args ) {
	$max_pages = (int) $query->max_num_pages;

	if ( $max_pages < 2 ) {
		return '';
	}

	$base_url  = isset( $_POST['base_url'] ) ? esc_url_raw( wp_unslash( $_POST['base_url'] ) ) : home_url( '/' );
	$base_url  = remove_query_arg( array( 'paged', 'page', 's', 'sort' ), $base_url );
	$base_url  = $base_url ? $base_url : home_url( '/' );
	$separator = false === strpos( $base_url, '?' ) ? '?' : '&';
	$base      = $base_url . $separator . 'paged=%#%';

	$links = paginate_links(
		array(
			'base'      => $base,
			'format'    => '',
			'current'   => max( 1, $paged ),
			'total'     => $max_pages,
			'type'      => 'array',
			'mid_size'  => 1,
			'end_size'  => 1,
			'prev_text' => __( 'Previous', 'peraproperty' ),
			'next_text' => __( 'Next', 'peraproperty' ),
			'add_args'  => array_filter(
				$query_args,
				static function ( $value ) {
					return '' !== $value && null !== $value;
				}
			),
		)
	);

	if ( empty( $links ) ) {
		return '';
	}

	ob_start();
	?>
	<nav class="posts-pagination" aria-label="<?php esc_attr_e( 'Posts pagination', 'peraproperty' ); ?>">
		<ul>
			<?php foreach ( $links as $link ) : ?>
				<li><?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php

	return (string) ob_get_clean();
}

/**
 * Handle progressive blog archive search requests.
 */
function pera_ajax_blog_search() {
	check_ajax_referer( 'pera_blog_search', 'nonce' );

	$search       = sanitize_text_field( wp_unslash( $_POST['s'] ?? '' ) );
	$sort         = sanitize_key( wp_unslash( $_POST['sort'] ?? 'published' ) );
	$paged        = max( 1, absint( wp_unslash( $_POST['paged'] ?? 1 ) ) );
	$archive_type = sanitize_key( wp_unslash( $_POST['archive_type'] ?? 'home' ) );
	$archive_id   = absint( wp_unslash( $_POST['archive_id'] ?? 0 ) );
	$year         = absint( wp_unslash( $_POST['archive_year'] ?? 0 ) );
	$month        = absint( wp_unslash( $_POST['archive_month'] ?? 0 ) );
	$day          = absint( wp_unslash( $_POST['archive_day'] ?? 0 ) );

	$options = pera_get_blog_archive_sort_options();
	if ( ! array_key_exists( $sort, $options ) ) {
		$sort = 'published';
	}

	$query_args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		's'                   => $search,
		'paged'               => $paged,
		'ignore_sticky_posts' => true,
		'orderby'             => $options[ $sort ]['orderby'],
		'order'                 => $options[ $sort ]['order'],
		'pera_blog_ajax_search' => true,
	);

	switch ( $archive_type ) {
		case 'category':
			if ( $archive_id > 0 ) {
				$query_args['cat'] = $archive_id;
			}
			break;

		case 'tag':
			if ( $archive_id > 0 ) {
				$query_args['tag_id'] = $archive_id;
			}
			break;

		case 'author':
			if ( $archive_id > 0 ) {
				$query_args['author'] = $archive_id;
			}
			break;

		case 'date':
			if ( $year > 0 ) {
				$query_args['year'] = $year;
			}
			if ( $month > 0 ) {
				$query_args['monthnum'] = $month;
			}
			if ( $day > 0 ) {
				$query_args['day'] = $day;
			}
			break;
	}

	$title_first_orderby_filter = null;

	if ( '' !== trim( $search ) ) {
		global $wpdb;

		$exact_title                = $search;
		$prefix_title_like          = $wpdb->esc_like( $search ) . '%';
		$contains_title_like        = '%' . $wpdb->esc_like( $search ) . '%';
		$title_first_orderby_filter = static function ( $orderby, $query ) use ( $wpdb, $exact_title, $prefix_title_like, $contains_title_like ) {
			if ( ! ( $query instanceof WP_Query ) || ! $query->get( 'pera_blog_ajax_search' ) ) {
				return $orderby;
			}

			$title_match_orderby = $wpdb->prepare(
				"CASE
					WHEN {$wpdb->posts}.post_title = %s THEN 0
					WHEN {$wpdb->posts}.post_title LIKE %s THEN 1
					WHEN {$wpdb->posts}.post_title LIKE %s THEN 2
					ELSE 3
				END ASC",
				$exact_title,
				$prefix_title_like,
				$contains_title_like
			);

			if ( '' === trim( (string) $orderby ) ) {
				return $title_match_orderby;
			}

			return $title_match_orderby . ', ' . $orderby;
		};

		add_filter( 'posts_orderby', $title_first_orderby_filter, 10, 2 );
	}

	$blog_query = new WP_Query( $query_args );

	if ( null !== $title_first_orderby_filter ) {
		remove_filter( 'posts_orderby', $title_first_orderby_filter, 10 );
	}

	if ( $blog_query->have_posts() ) {
		$grid_html = pera_blog_search_render_grid( $blog_query );
	} else {
		$grid_html = '<div class="no-posts"><p>' . esc_html__( 'No articles found matching your search.', 'peraproperty' ) . '</p></div>';
	}

	$pagination_args = array(
		's'    => $search,
		'sort' => 'published' !== $sort ? $sort : '',
	);

	$pagination_html = pera_blog_search_render_pagination( $blog_query, $paged, $pagination_args );
	$found_posts     = (int) $blog_query->found_posts;
	$count_text      = sprintf(
		/* translators: %d: number of matching posts. */
		_n( '%d article found', '%d articles found', $found_posts, 'peraproperty' ),
		$found_posts
	);

	wp_send_json_success(
		array(
			'grid_html'       => $grid_html,
			'pagination_html' => $pagination_html,
			'count_text'      => $count_text,
			'found_posts'     => $found_posts,
			'max_pages'       => (int) $blog_query->max_num_pages,
		)
	);
}
