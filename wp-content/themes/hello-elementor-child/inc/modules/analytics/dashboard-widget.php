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
		$rows    = pera_analytics_get_top_pages( 8 );
		$totals  = pera_analytics_get_month_totals();
		$windows = pera_analytics_month_window();

		echo '<p><strong>' . esc_html__( 'Visits this month (to date):', 'hello-elementor-child' ) . '</strong> ' . esc_html( number_format_i18n( $totals['current']['visits'] ) );
		echo ' &nbsp;•&nbsp; <strong>' . esc_html__( 'Unique visitors (to date):', 'hello-elementor-child' ) . '</strong> ' . esc_html( number_format_i18n( $totals['current']['uniques'] ) );
		echo '</p>';

		echo '<p><small>';
		echo esc_html__( 'Comparison uses a matched month-to-date window (previous month up to the same day/time cutoff).', 'hello-elementor-child' );
		echo ' ';
		echo esc_html( sprintf( '%s → %s', $windows['current']['start'], $windows['current']['end'] ) );
		echo ' ';
		echo esc_html__( 'vs', 'hello-elementor-child' );
		echo ' ';
		echo esc_html( sprintf( '%s → %s', $windows['previous']['start'], $windows['previous']['end'] ) );
		echo '</small></p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No tracked visits yet.', 'hello-elementor-child' ) . '</p>';
			return;
		}

		echo '<div class="pera-analytics-table-wrap" style="overflow-x:auto;">';
		echo '<table class="widefat striped pera-analytics-table" style="min-width:860px;table-layout:fixed;">';
		echo '<colgroup>';
		echo '<col class="pera-analytics-col-page"/>';
		echo '<col class="pera-analytics-col-metric"/>';
		echo '<col class="pera-analytics-col-metric"/>';
		echo '<col class="pera-analytics-col-metric"/>';
		echo '<col class="pera-analytics-col-metric"/>';
		echo '</colgroup>';
		echo '<thead><tr>';
		echo '<th class="pera-analytics-col-page" style="width:380px;">' . esc_html__( 'Page', 'hello-elementor-child' ) . '</th>';
		echo '<th class="pera-analytics-col-metric" style="width:120px;text-align:right;white-space:nowrap;">' . esc_html__( 'Visits', 'hello-elementor-child' ) . '</th>';
		echo '<th class="pera-analytics-col-metric" style="width:120px;text-align:right;white-space:nowrap;">' . esc_html__( 'Unique', 'hello-elementor-child' ) . '</th>';
		echo '<th class="pera-analytics-col-metric" style="width:120px;text-align:right;white-space:nowrap;">' . esc_html__( 'Matched previous', 'hello-elementor-child' ) . '</th>';
		echo '<th class="pera-analytics-col-metric" style="width:120px;text-align:right;white-space:nowrap;">' . esc_html__( 'Change', 'hello-elementor-child' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$title = ! empty( $row['page_title'] ) ? $row['page_title'] : $row['page_path'];
			$path  = (string) $row['page_path'];
			$url   = wp_http_validate_url( $path ) ? $path : home_url( $path );

			echo '<tr>';
			echo '<td class="pera-analytics-col-page" style="width:380px;max-width:380px;">';
			echo '<a class="pera-analytics-page-link" style="display:inline-block;max-width:100%;font-weight:600;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:top;" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a>';
			echo '<div class="pera-analytics-page-path" style="margin-top:2px;font-size:11px;line-height:1.35;color:#646970;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' . esc_attr( $path ) . '">' . esc_html( $path ) . '</div>';
			echo '</td>';
			echo '<td class="pera-analytics-col-metric" style="text-align:right;white-space:nowrap;">' . esc_html( number_format_i18n( (int) $row['visits_this_month'] ) ) . '</td>';
			echo '<td class="pera-analytics-col-metric" style="text-align:right;white-space:nowrap;">' . esc_html( number_format_i18n( (int) $row['uniques_this_month'] ) ) . '</td>';
			echo '<td class="pera-analytics-col-metric" style="text-align:right;white-space:nowrap;">' . esc_html( number_format_i18n( (int) $row['visits_last_month'] ) ) . '</td>';
			echo '<td class="pera-analytics-col-metric" style="text-align:right;white-space:nowrap;">' . esc_html( pera_analytics_percent_change( (int) $row['visits_this_month'], (int) $row['visits_last_month'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}
}

if ( ! function_exists( 'pera_analytics_dashboard_widget_styles' ) ) {
	function pera_analytics_dashboard_widget_styles(): void {
		echo '<style>
			#dashboard-widgets-wrap #pera_page_visits_widget .pera-analytics-table-wrap { overflow-x: auto; }
			#dashboard-widgets-wrap #pera_page_visits_widget .pera-analytics-table { min-width: 860px; table-layout: fixed; }
			#pera_page_visits_widget col.pera-analytics-col-page { width: 380px; }
			#pera_page_visits_widget col.pera-analytics-col-metric { width: 120px; }
			#dashboard-widgets-wrap #pera_page_visits_widget .pera-analytics-col-metric { text-align: right !important; white-space: nowrap; }
			#pera_page_visits_widget .pera-analytics-page-link {
				display: inline-block;
				max-width: 100%;
				font-weight: 600;
				text-decoration: none;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
				vertical-align: top;
			}
			#pera_page_visits_widget .pera-analytics-page-link:hover,
			#pera_page_visits_widget .pera-analytics-page-link:focus { text-decoration: underline; }
			#pera_page_visits_widget .pera-analytics-page-path {
				margin-top: 2px;
				font-size: 11px;
				line-height: 1.35;
				color: #646970;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		</style>';
	}
}
add_action( 'admin_head-index.php', 'pera_analytics_dashboard_widget_styles' );
