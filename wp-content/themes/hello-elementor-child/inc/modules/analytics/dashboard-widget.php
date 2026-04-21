<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_register_dashboard_widget' ) ) {
	function pera_analytics_register_dashboard_widget(): void {
		wp_add_dashboard_widget(
			'pera_page_visits_widget',
			__( 'Top Pages This Month', 'hello-elementor-child' ),
			'pera_analytics_render_dashboard_widget'
		);
	}
}
add_action( 'wp_dashboard_setup', 'pera_analytics_register_dashboard_widget' );

if ( ! function_exists( 'pera_analytics_percent_change' ) ) {
	function pera_analytics_percent_change( int $current, int $previous ): string {
		if ( 0 === $previous ) {
			return $current > 0 ? '+100%' : '0%';
		}

		$delta = ( ( $current - $previous ) / $previous ) * 100;
		$sign  = $delta > 0 ? '+' : '';

		return $sign . number_format_i18n( $delta, 1 ) . '%';
	}
}

if ( ! function_exists( 'pera_analytics_render_dashboard_widget' ) ) {
	function pera_analytics_render_dashboard_widget(): void {
		$rows   = pera_analytics_get_top_pages( 8 );
		$totals = pera_analytics_get_month_totals();

		echo '<p><strong>' . esc_html__( 'Visits this month:', 'hello-elementor-child' ) . '</strong> ' . esc_html( number_format_i18n( $totals['current']['visits'] ) );
		echo ' &nbsp;•&nbsp; <strong>' . esc_html__( 'Unique visitors:', 'hello-elementor-child' ) . '</strong> ' . esc_html( number_format_i18n( $totals['current']['uniques'] ) );
		echo '</p>';

		echo '<p><small>';
		echo esc_html__( 'Last month comparison uses the full previous calendar month.', 'hello-elementor-child' );
		echo '</small></p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No tracked visits yet.', 'hello-elementor-child' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Page', 'hello-elementor-child' ) . '</th>';
		echo '<th>' . esc_html__( 'Visits', 'hello-elementor-child' ) . '</th>';
		echo '<th>' . esc_html__( 'Unique', 'hello-elementor-child' ) . '</th>';
		echo '<th>' . esc_html__( 'Last month', 'hello-elementor-child' ) . '</th>';
		echo '<th>' . esc_html__( 'Change', 'hello-elementor-child' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$title = ! empty( $row['page_title'] ) ? $row['page_title'] : $row['page_path'];
			echo '<tr>';
			echo '<td>' . esc_html( $title ) . '<br/><code>' . esc_html( $row['page_path'] ) . '</code></td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $row['visits_this_month'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $row['uniques_this_month'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $row['visits_last_month'] ) ) . '</td>';
			echo '<td>' . esc_html( pera_analytics_percent_change( (int) $row['visits_this_month'], (int) $row['visits_last_month'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
