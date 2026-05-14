<?php
/**
 * Blog archive ordering.
 *
 * Orders public post archives by last modified date.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pre_get_posts', 'pera_order_blog_archives_by_modified_date' );

/**
 * Order the front-end main post archive queries by latest modified date first.
 *
 * @param WP_Query $query WordPress query instance.
 */
function pera_order_blog_archives_by_modified_date( $query ) {
	if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
		return;
	}

	if (
		$query->is_home()
		|| $query->is_category()
		|| $query->is_tag()
		|| $query->is_author()
		|| $query->is_date()
	) {
		$query->set( 'orderby', 'modified' );
		$query->set( 'order', 'DESC' );
	}
}
