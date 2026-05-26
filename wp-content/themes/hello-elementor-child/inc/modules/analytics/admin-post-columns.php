<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_add_post_visits_30d_column' ) ) {
	function pera_analytics_add_post_visits_30d_column( array $columns ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $columns;
		}

		$columns['pera_visits_30d'] = esc_html__( 'Visits 30d', 'hello-elementor-child' );

		return $columns;
	}
}
add_filter( 'manage_post_posts_columns', 'pera_analytics_add_post_visits_30d_column' );

if ( ! function_exists( 'pera_analytics_make_post_visits_30d_sortable' ) ) {
	function pera_analytics_make_post_visits_30d_sortable( array $sortable_columns ): array {
		$sortable_columns['pera_visits_30d'] = 'pera_visits_30d';

		return $sortable_columns;
	}
}
add_filter( 'manage_edit-post_sortable_columns', 'pera_analytics_make_post_visits_30d_sortable' );

if ( ! function_exists( 'pera_analytics_render_post_visits_30d_column' ) ) {
	function pera_analytics_render_post_visits_30d_column( string $column_name, int $post_id ): void {
		if ( 'pera_visits_30d' !== $column_name || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$daily_table = $wpdb->prefix . 'pera_page_visit_daily';
		$start_date  = wp_date( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) );
		$end_date    = wp_date( 'Y-m-d', current_time( 'timestamp' ) );

		$visits = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(visits), 0)
				 FROM {$daily_table}
				 WHERE post_id = %d
				   AND summary_date BETWEEN %s AND %s",
				$post_id,
				$start_date,
				$end_date
			)
		);

		echo esc_html( number_format_i18n( $visits ) );
	}
}
add_action( 'manage_post_posts_custom_column', 'pera_analytics_render_post_visits_30d_column', 10, 2 );

if ( ! function_exists( 'pera_analytics_prepare_post_visits_30d_sorting' ) ) {
	function pera_analytics_prepare_post_visits_30d_sorting( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $pagenow;
		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( $post_type && 'post' !== $post_type ) {
			return;
		}

		if ( 'pera_visits_30d' !== $query->get( 'orderby' ) ) {
			return;
		}

		$order = strtoupper( (string) $query->get( 'order' ) );
		$order = 'ASC' === $order ? 'ASC' : 'DESC';

		$query->set( 'order', $order );
		$query->set( 'pera_sort_visits_30d', true );
	}
}
add_action( 'pre_get_posts', 'pera_analytics_prepare_post_visits_30d_sorting' );

if ( ! function_exists( 'pera_analytics_apply_post_visits_30d_sorting_clauses' ) ) {
	function pera_analytics_apply_post_visits_30d_sorting_clauses( array $clauses, WP_Query $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() || ! $query->get( 'pera_sort_visits_30d' ) ) {
			return $clauses;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return $clauses;
		}

		global $wpdb;

		$daily_table = $wpdb->prefix . 'pera_page_visit_daily';
		$start_date  = wp_date( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) );
		$end_date    = wp_date( 'Y-m-d', current_time( 'timestamp' ) );
		$order       = strtoupper( (string) $query->get( 'order' ) );
		$order       = 'ASC' === $order ? 'ASC' : 'DESC';

		$alias = 'pera_visits_30d_sort';

		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN (
				SELECT post_id, SUM(visits) AS visits_30d
				FROM {$daily_table}
				WHERE summary_date BETWEEN %s AND %s
				GROUP BY post_id
			) {$alias} ON {$wpdb->posts}.ID = {$alias}.post_id",
			$start_date,
			$end_date
		);

		$clauses['orderby'] = "COALESCE({$alias}.visits_30d, 0) {$order}, {$wpdb->posts}.ID DESC";

		return $clauses;
	}
}
add_filter( 'posts_clauses', 'pera_analytics_apply_post_visits_30d_sorting_clauses', 10, 2 );
