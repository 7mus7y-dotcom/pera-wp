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
