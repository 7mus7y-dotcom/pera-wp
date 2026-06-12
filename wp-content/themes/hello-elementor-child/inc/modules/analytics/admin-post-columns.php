<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_visits_30d_column_key' ) ) {
	function pera_analytics_visits_30d_column_key(): string {
		return 'pera_visits_30d';
	}
}

if ( ! function_exists( 'pera_analytics_visits_30d_admin_post_types' ) ) {
	function pera_analytics_visits_30d_admin_post_types(): array {
		return array( 'post', 'property' );
	}
}

if ( ! function_exists( 'pera_analytics_add_visits_30d_column' ) ) {
	function pera_analytics_add_visits_30d_column( array $columns ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $columns;
		}

		$columns[ pera_analytics_visits_30d_column_key() ] = esc_html__( 'Visits 30d', 'hello-elementor-child' );

		return $columns;
	}
}

if ( ! function_exists( 'pera_analytics_make_visits_30d_sortable' ) ) {
	function pera_analytics_make_visits_30d_sortable( array $sortable_columns ): array {
		$sortable_columns[ pera_analytics_visits_30d_column_key() ] = pera_analytics_visits_30d_column_key();

		return $sortable_columns;
	}
}

if ( ! function_exists( 'pera_analytics_visits_30d_date_window' ) ) {
	function pera_analytics_visits_30d_date_window(): array {
		return array(
			'start_date' => wp_date( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) ),
			'end_date'   => wp_date( 'Y-m-d', current_time( 'timestamp' ) ),
		);
	}
}

if ( ! function_exists( 'pera_analytics_get_post_visits_30d' ) ) {
	function pera_analytics_get_post_visits_30d( int $post_id ): int {
		global $wpdb;

		$daily_table = pera_analytics_daily_table_name();
		$date_window = pera_analytics_visits_30d_date_window();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(visits), 0)
				 FROM {$daily_table}
				 WHERE post_id = %d
				   AND summary_date BETWEEN %s AND %s",
				$post_id,
				$date_window['start_date'],
				$date_window['end_date']
			)
		);
	}
}

if ( ! function_exists( 'pera_analytics_render_visits_30d_column' ) ) {
	function pera_analytics_render_visits_30d_column( string $column_name, int $post_id ): void {
		if ( pera_analytics_visits_30d_column_key() !== $column_name || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo esc_html( number_format_i18n( pera_analytics_get_post_visits_30d( $post_id ) ) );
	}
}

if ( ! function_exists( 'pera_analytics_admin_list_post_type' ) ) {
	function pera_analytics_admin_list_post_type( WP_Query $query ): string {
		$post_type = $query->get( 'post_type' );
		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}

		$post_type = sanitize_key( (string) $post_type );
		if ( '' !== $post_type ) {
			return $post_type;
		}

		if ( isset( $_GET['post_type'] ) ) {
			return sanitize_key( wp_unslash( (string) $_GET['post_type'] ) );
		}

		return 'post';
	}
}

if ( ! function_exists( 'pera_analytics_prepare_visits_30d_sorting' ) ) {
	function pera_analytics_prepare_visits_30d_sorting( WP_Query $query ): void {
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

		if ( ! in_array( pera_analytics_admin_list_post_type( $query ), pera_analytics_visits_30d_admin_post_types(), true ) ) {
			return;
		}

		if ( pera_analytics_visits_30d_column_key() !== $query->get( 'orderby' ) ) {
			return;
		}

		$order = strtoupper( (string) $query->get( 'order' ) );
		$order = 'ASC' === $order ? 'ASC' : 'DESC';

		$query->set( 'order', $order );
		$query->set( 'pera_sort_visits_30d', true );
	}
}

if ( ! function_exists( 'pera_analytics_apply_visits_30d_sorting_clauses' ) ) {
	function pera_analytics_apply_visits_30d_sorting_clauses( array $clauses, WP_Query $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() || ! $query->get( 'pera_sort_visits_30d' ) ) {
			return $clauses;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return $clauses;
		}

		global $wpdb;

		$daily_table = pera_analytics_daily_table_name();
		$date_window = pera_analytics_visits_30d_date_window();
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
			$date_window['start_date'],
			$date_window['end_date']
		);

		$clauses['orderby'] = "COALESCE({$alias}.visits_30d, 0) {$order}, {$wpdb->posts}.ID DESC";

		return $clauses;
	}
}

if ( ! function_exists( 'pera_analytics_register_visits_30d_admin_columns' ) ) {
	function pera_analytics_register_visits_30d_admin_columns(): void {
		foreach ( pera_analytics_visits_30d_admin_post_types() as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			add_filter( "manage_{$post_type}_posts_columns", 'pera_analytics_add_visits_30d_column' );
			add_filter( "manage_edit-{$post_type}_sortable_columns", 'pera_analytics_make_visits_30d_sortable' );
			add_action( "manage_{$post_type}_posts_custom_column", 'pera_analytics_render_visits_30d_column', 10, 2 );
		}
	}
}
add_action( 'admin_init', 'pera_analytics_register_visits_30d_admin_columns' );

add_action( 'pre_get_posts', 'pera_analytics_prepare_visits_30d_sorting' );
add_filter( 'posts_clauses', 'pera_analytics_apply_visits_30d_sorting_clauses', 10, 2 );
