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

/**
 * Run after generic archive/query customisations so the selected blog sort is
 * the final order used by the public posts-page main loop.
 */
add_action( 'pre_get_posts', 'pera_order_blog_archives_by_selected_date', 999 );
add_filter( 'posts_clauses', 'pera_order_blog_archive_by_selected_date_clauses', 999, 2 );

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
	$options = pera_get_blog_archive_sort_options();
	$sort    = 'published';

	if ( isset( $_GET['sort'] ) ) {
		$sort = sanitize_key( wp_unslash( $_GET['sort'] ) );
	}

	return array_key_exists( $sort, $options ) ? $sort : 'published';
}

/**
 * Get the explicit SQL ORDER BY clause for a blog archive sort key.
 *
 * @param string $sort Selected sort key.
 * @return string
 */
function pera_get_blog_archive_secondary_orderby_sql( $sort ) {
	global $wpdb;

	switch ( $sort ) {
		case 'updated':
			return "{$wpdb->posts}.post_modified DESC, {$wpdb->posts}.ID DESC";

		case 'oldest':
			return "{$wpdb->posts}.post_date ASC, {$wpdb->posts}.ID ASC";

		case 'published':
		default:
			return "{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
	}
}

/**
 * Get deterministic WP_Query orderby arguments for a blog archive sort key.
 *
 * @param string $sort Selected sort key.
 * @return array<string,string>
 */
function pera_get_blog_archive_orderby_args( $sort ) {
	switch ( $sort ) {
		case 'updated':
			return array(
				'modified' => 'DESC',
				'ID'       => 'DESC',
			);

		case 'oldest':
			return array(
				'date' => 'ASC',
				'ID'   => 'ASC',
			);

		case 'published':
		default:
			return array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			);
	}
}

/**
 * Get normalized featured guide post IDs for the posts page.
 *
 * @return int[]
 */
function pera_get_posts_page_featured_guide_post_ids() {
	$posts_page_id = (int) get_option( 'page_for_posts' );

	if ( $posts_page_id <= 0 || ! function_exists( 'get_field' ) ) {
		return array();
	}

	$raw_featured_links = get_field( 'featured_guide_links', $posts_page_id );
	if ( ! is_array( $raw_featured_links ) || empty( $raw_featured_links ) ) {
		return array();
	}

	$featured_post_ids = array();

	foreach ( $raw_featured_links as $featured_item ) {
		$candidate_post_id = 0;

		if ( $featured_item instanceof WP_Post ) {
			$candidate_post_id = (int) $featured_item->ID;
		} elseif ( is_numeric( $featured_item ) ) {
			$candidate_post_id = (int) $featured_item;
		} elseif ( is_array( $featured_item ) ) {
			if ( isset( $featured_item['ID'] ) && is_numeric( $featured_item['ID'] ) ) {
				$candidate_post_id = (int) $featured_item['ID'];
			} elseif ( isset( $featured_item['id'] ) && is_numeric( $featured_item['id'] ) ) {
				$candidate_post_id = (int) $featured_item['id'];
			}
		}

		if ( $candidate_post_id <= 0 || 'publish' !== get_post_status( $candidate_post_id ) ) {
			continue;
		}

		$featured_post_ids[] = $candidate_post_id;
	}

	return array_values( array_unique( $featured_post_ids ) );
}

/**
 * Determine whether the current request path is the configured posts page.
 *
 * WordPress treats /blog/?s=term as a search request rather than is_home(), so
 * query conditionals alone are not enough to distinguish it from global search.
 * Comparing the request path with the posts-page permalink keeps the search
 * ordering scoped to the normal blog URL.
 *
 * @return bool
 */
function pera_is_blog_posts_page_request_path() {
	$posts_page_id = (int) get_option( 'page_for_posts' );

	if ( $posts_page_id <= 0 ) {
		return false;
	}

	$posts_page_url = get_permalink( $posts_page_id );

	if ( ! $posts_page_url ) {
		return false;
	}

	$posts_page_path = (string) wp_parse_url( $posts_page_url, PHP_URL_PATH );
	$request_uri     = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$request_path    = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

	$posts_page_path = '/' . trim( $posts_page_path, '/' );
	$request_path    = '/' . trim( $request_path, '/' );

	return $request_path === $posts_page_path;
}

/**
 * Determine whether a search query is limited to posts.
 *
 * @param WP_Query $query WordPress query instance.
 * @return bool
 */
function pera_is_post_search_query( WP_Query $query ) {
	if ( ! $query->is_search() || '' === trim( (string) $query->get( 's' ) ) ) {
		return false;
	}

	$post_type = $query->get( 'post_type' );

	if ( empty( $post_type ) || 'post' === $post_type ) {
		return true;
	}

	return is_array( $post_type ) && array( 'post' ) === array_values( $post_type );
}

/**
 * Determine whether a query is the public main blog archive query.
 *
 * @param mixed $query WordPress query instance.
 * @return bool
 */
function pera_is_blog_archive_query( $query ) {
	if ( is_admin() || wp_doing_ajax() || ! ( $query instanceof WP_Query ) || ! $query->is_main_query() ) {
		return false;
	}

	if (
		$query->is_home()
		|| $query->is_category()
		|| $query->is_tag()
		|| $query->is_author()
		|| $query->is_date()
	) {
		return true;
	}

	return pera_is_post_search_query( $query ) && pera_is_blog_posts_page_request_path();
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

	if ( pera_is_post_search_query( $query ) ) {
		$query->set( 'post_type', 'post' );
	}

	$query->set( 'sort', $sort );
	$query->set( 'pera_blog_archive_sort', $sort );
	$query->set( 'orderby', pera_get_blog_archive_orderby_args( $sort ) );
	$query->set( 'order', $choice['order'] );

	if ( '' !== trim( (string) $query->get( 's' ) ) ) {
		return;
	}

	$featured_post_ids = array();

	if ( $query->is_home() ) {
		$featured_post_ids = pera_get_posts_page_featured_guide_post_ids();
	} elseif ( $query->is_category() && function_exists( 'pera_get_category_featured_guide_post_ids' ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$featured_post_ids = pera_get_category_featured_guide_post_ids( $term );
		}
	}

	if ( ! empty( $featured_post_ids ) ) {
		$existing_exclusions = array_filter( array_map( 'absint', (array) $query->get( 'post__not_in' ) ) );
		$query->set( 'post__not_in', array_values( array_unique( array_merge( $existing_exclusions, $featured_post_ids ) ) ) );
	}
}

/**
 * Order front-end main blog archive queries by the selected date option.
 *
 * Search results retain title-match priority before the selected date order.
 *
 * @param array<string,string> $clauses SQL clauses for the query.
 * @param WP_Query            $query   WordPress query instance.
 * @return array<string,string>
 */
function pera_order_blog_archive_by_selected_date_clauses( $clauses, $query ) {
	if ( is_admin() || ! pera_is_blog_archive_query( $query ) ) {
		return $clauses;
	}

	$search = trim( (string) $query->get( 's' ) );

	$options = pera_get_blog_archive_sort_options();
	$sort    = (string) $query->get( 'pera_blog_archive_sort' );

	if ( ! array_key_exists( $sort, $options ) ) {
		$sort = 'published';
	}

	$secondary_orderby = pera_get_blog_archive_secondary_orderby_sql( $sort );

	if ( '' === $search ) {
		$clauses['orderby'] = $secondary_orderby;

		return $clauses;
	}

	global $wpdb;

	$title_match_orderby = $wpdb->prepare(
		"CASE
WHEN {$wpdb->posts}.post_title = %s THEN 0
WHEN {$wpdb->posts}.post_title LIKE %s THEN 1
WHEN {$wpdb->posts}.post_title LIKE %s THEN 2
ELSE 3
		END ASC",
		$search,
		$wpdb->esc_like( $search ) . '%',
		'%' . $wpdb->esc_like( $search ) . '%'
	);

	$clauses['orderby'] = $title_match_orderby . ', ' . $secondary_orderby;

	return $clauses;
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
