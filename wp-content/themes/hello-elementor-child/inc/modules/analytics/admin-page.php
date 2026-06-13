<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_get_reporting_window' ) ) {
	function pera_analytics_get_reporting_window( string $period_key ): array {
		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );

		switch ( $period_key ) {
			case 'all_time':
				$current_start = null;
				$current_end   = $now;
				$previous_start = null;
				$previous_end   = null;
				break;
			case '24h':
				$current_start = $now->modify( '-24 hours' );
				$current_end   = $now;
				$previous_start = $current_start->modify( '-24 hours' );
				$previous_end   = $current_start;
				break;
			case '7d':
				$current_start = $now->modify( '-7 days' );
				$current_end   = $now;
				$previous_start = $current_start->modify( '-7 days' );
				$previous_end   = $current_start;
				break;
			case '14d':
				$current_start = $now->modify( '-14 days' );
				$current_end   = $now;
				$previous_start = $current_start->modify( '-14 days' );
				$previous_end   = $current_start;
				break;
			case '30d':
				$current_start = $now->modify( '-30 days' );
				$current_end   = $now;
				$previous_start = $current_start->modify( '-30 days' );
				$previous_end   = $current_start;
				break;
			case 'last_month':
				$current_start  = $now->modify( 'first day of last month' )->setTime( 0, 0, 0 );
				$current_end    = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
				$previous_start = $now->modify( 'first day of -2 month' )->setTime( 0, 0, 0 );
				$previous_end   = $current_start;
				break;
			case 'this_month':
			default:
				$current_start = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
				$current_end   = $now;
				$elapsed       = $current_end->getTimestamp() - $current_start->getTimestamp();
				$previous_start = $current_start->modify( '-1 month' );
				$previous_end_candidate = $previous_start->modify( '+' . $elapsed . ' seconds' );
				$previous_month_end = $previous_start->modify( 'first day of next month' )->setTime( 0, 0, 0 );
				$previous_end = $previous_end_candidate > $previous_month_end ? $previous_month_end : $previous_end_candidate;
				$period_key = 'this_month';
				break;
		}

		return array(
			'key'      => $period_key,
			'current'  => array(
				'start' => $current_start instanceof DateTimeImmutable ? $current_start->format( 'Y-m-d H:i:s' ) : null,
				'end'   => $current_end->format( 'Y-m-d H:i:s' ),
			),
			'previous' => array(
				'start' => $previous_start instanceof DateTimeImmutable ? $previous_start->format( 'Y-m-d H:i:s' ) : null,
				'end'   => $previous_end instanceof DateTimeImmutable ? $previous_end->format( 'Y-m-d H:i:s' ) : null,
			),
		);
	}
}

if ( ! function_exists( 'pera_analytics_get_period_totals' ) ) {
	function pera_analytics_get_period_totals( ?string $start, string $end ): array {
		global $wpdb;
		$raw_table = pera_analytics_raw_table_name();

		$rollup = pera_analytics_get_period_page_rollup( $start, $end );
		$visits = 0;
		foreach ( $rollup as $row ) {
			$visits += (int) $row['visits'];
		}

		if ( null === $start ) {
			$totals = $wpdb->get_row(
				$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_id) AS uniques
					FROM {$raw_table}
					WHERE visited_at < %s
					  " . pera_analytics_suspected_bot_where_clause() . "",
					$end
				),
				ARRAY_A
			);
		} else {
			$totals = $wpdb->get_row(
				$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_id) AS uniques
					FROM {$raw_table}
					WHERE visited_at >= %s
					  AND visited_at < %s
					  " . pera_analytics_suspected_bot_where_clause() . "",
					$start,
					$end
				),
				ARRAY_A
			);
		}

		return array(
			'visits'  => $visits,
			'uniques' => isset( $totals['uniques'] ) ? (int) $totals['uniques'] : 0,
		);
	}
}

if ( ! function_exists( 'pera_analytics_register_admin_page' ) ) {
	function pera_analytics_register_admin_page(): void {
		add_menu_page(
			esc_html__( 'Site Performance', 'hello-elementor-child' ),
			esc_html__( 'Site Performance', 'hello-elementor-child' ),
			'manage_options',
			'pera-site-performance',
			'pera_analytics_render_admin_page',
			'dashicons-chart-area',
			59
		);
	}
}
add_action( 'admin_menu', 'pera_analytics_register_admin_page' );


if ( ! function_exists( 'pera_analytics_enqueue_admin_page_assets' ) ) {
	function pera_analytics_enqueue_admin_page_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_pera-site-performance' !== $hook_suffix ) {
			return;
		}

		wp_register_style( 'pera-site-performance-admin', false, array(), null );
		wp_enqueue_style( 'pera-site-performance-admin' );
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', array(), '4.4.3', true );
		wp_register_script( 'pera-site-performance-chart', '', array( 'chart-js' ), null, true );
		wp_enqueue_script( 'pera-site-performance-chart' );

		$performance_admin_css = <<<'CSS'
.pera-site-performance-admin {
	max-width: 100%;
}

.pera-site-performance-admin .pera-performance-filter {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
	margin: 8px 0 10px;
}

.pera-site-performance-admin .pera-performance-filter label {
	margin: 0;
}

.pera-site-performance-admin .pera-performance-kpis {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	margin: 12px 0 18px;
}

.pera-site-performance-admin .pera-performance-kpi {
	box-sizing: border-box;
	padding: 12px;
	min-width: 180px;
}

.pera-site-performance-admin .pera-performance-kpi__value {
	display: block;
	margin-top: 4px;
	font-size: 20px;
	line-height: 1.3;
	overflow-wrap: anywhere;
}

.pera-site-performance-admin .pera-performance-table-wrap {
	max-width: 100%;
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
	background-color: #fff;
	padding-bottom: 1px;
}

.pera-site-performance-admin .pera-performance-table-wrap:focus {
	outline: 2px solid #2271b1;
	outline-offset: 2px;
}

.pera-site-performance-admin .pera-performance-table {
	width: 100%;
	min-width: 720px;
}

.pera-site-performance-admin .pera-performance-table th,
.pera-site-performance-admin .pera-performance-table td {
	vertical-align: top;
}

.pera-site-performance-admin .pera-performance-table .column-page {
	min-width: 260px;
}

.pera-site-performance-admin .pera-performance-table__number {
	text-align: right;
	white-space: nowrap;
}

.pera-site-performance-admin .pera-performance-page-path {
	overflow-wrap: anywhere;
	word-break: break-word;
}

.pera-site-performance-admin .pera-performance-scroll-hint {
	display: none;
}

.pera-site-performance-admin .pera-performance-chart-card {
	padding: 12px;
	margin-bottom: 18px;
}

.pera-site-performance-admin .pera-performance-chart-wrap {
	position: relative;
	min-height: 280px;
}

@media screen and (max-width: 782px) {
	.pera-site-performance-admin .pera-performance-filter {
		align-items: stretch;
	}

	.pera-site-performance-admin .pera-performance-filter label {
		width: 100%;
	}

	.pera-site-performance-admin .pera-performance-filter select,
	.pera-site-performance-admin .pera-performance-filter .button {
		box-sizing: border-box;
		width: 100%;
		min-height: 44px;
	}

	.pera-site-performance-admin .pera-performance-kpis {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 10px;
	}

	.pera-site-performance-admin .pera-performance-kpi {
		min-width: 0;
		margin-bottom: 0;
	}

	.pera-site-performance-admin .pera-performance-kpi__value {
		font-size: 18px;
	}

	.pera-site-performance-admin .pera-performance-table-wrap {
		border: 1px solid #c3c4c7;
		box-shadow: inset -18px 0 14px -16px rgba(0, 0, 0, .35);
	}

	.pera-site-performance-admin .pera-performance-scroll-hint {
		display: block;
		margin: 0 0 6px;
		color: #646970;
		font-size: 12px;
	}

	.pera-site-performance-admin .pera-performance-table {
		border: 0;
	}
}

@media screen and (max-width: 430px) {
	.pera-site-performance-admin .pera-performance-kpis {
		grid-template-columns: 1fr;
	}

	.pera-site-performance-admin .pera-performance-kpi__value {
		font-size: 17px;
	}
}

CSS;

		wp_add_inline_style( 'pera-site-performance-admin', $performance_admin_css );

		$chart_js = <<<'JS'
(function () {
	if (!window.peraPerformanceChartData || !window.Chart) {
		return;
	}
	var canvas = document.getElementById('pera-visits-by-day-chart');
	if (!canvas) {
		return;
	}
	new window.Chart(canvas, {
		type: 'line',
		data: {
			labels: window.peraPerformanceChartData.labels || [],
			datasets: [
				{
					label: window.peraPerformanceChartData.visitsLabel || 'Visits',
					data: window.peraPerformanceChartData.visits || [],
					borderColor: '#2271b1',
					backgroundColor: 'rgba(34,113,177,0.12)',
					borderWidth: 2,
					tension: 0.25,
					pointRadius: 2
				},
				{
					label: window.peraPerformanceChartData.uniqueVisitorsLabel || 'Unique visitors',
					data: window.peraPerformanceChartData.uniqueVisitors || [],
					borderColor: '#3858e9',
					backgroundColor: 'rgba(56,88,233,0.08)',
					borderWidth: 2,
					tension: 0.25,
					pointRadius: 2
				}
			]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: { position: 'top' }
			},
			scales: {
				y: {
					beginAtZero: true,
					ticks: {
						precision: 0
					}
				}
			}
		}
	});
})();
JS;
		wp_add_inline_script( 'pera-site-performance-chart', $chart_js );
	}
}
add_action( 'admin_enqueue_scripts', 'pera_analytics_enqueue_admin_page_assets' );

if ( ! function_exists( 'pera_analytics_render_admin_pages_table' ) ) {
	function pera_analytics_render_admin_pages_table( array $rows, string $aria_label, string $empty_message, bool $show_previous_comparison = true ): void {
		?>
		<p class="pera-performance-scroll-hint"><?php echo esc_html__( 'Scroll horizontally to view all table columns.', 'hello-elementor-child' ); ?></p>
		<div class="pera-performance-table-wrap" tabindex="0" role="region" aria-label="<?php echo esc_attr( $aria_label ); ?>">
		<table class="widefat striped pera-performance-table">
			<thead><tr>
				<th class="column-page"><?php echo esc_html__( 'Page', 'hello-elementor-child' ); ?></th>
				<th class="pera-performance-table__number"><?php echo esc_html__( 'Visits', 'hello-elementor-child' ); ?></th>
				<th class="pera-performance-table__number"><?php echo esc_html__( 'Unique visitors', 'hello-elementor-child' ); ?></th>
				<th class="pera-performance-table__number"><?php echo esc_html__( 'Previous period visits', 'hello-elementor-child' ); ?></th>
				<th class="pera-performance-table__number"><?php echo esc_html__( '% change', 'hello-elementor-child' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="5"><?php echo esc_html( $empty_message ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$page_path  = (string) $row['page_path'];
					$page_title = '' !== $row['page_title'] ? $row['page_title'] : $page_path;
					$page_url   = home_url( $page_path );
					?>
					<tr>
						<td class="column-page"><a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $page_title ); ?></a><br><small class="pera-performance-page-path"><?php echo esc_html( $page_path ); ?></small></td>
						<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (int) $row['visits'] ) ); ?></td>
						<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (int) $row['uniques'] ) ); ?></td>
						<td class="pera-performance-table__number"><?php echo $show_previous_comparison ? esc_html( number_format_i18n( (int) $row['previous_visits'] ) ) : esc_html__( '—', 'hello-elementor-child' ); ?></td>
						<td class="pera-performance-table__number"><?php echo $show_previous_comparison ? esc_html( pera_analytics_percent_change( (int) $row['visits'], (int) $row['previous_visits'] ) ) : esc_html__( '—', 'hello-elementor-child' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		</div>
		<?php
	}
}

if ( ! function_exists( 'pera_analytics_source_type_label' ) ) {
	function pera_analytics_source_type_label( string $source_type ): string {
		$labels = array(
			'direct'         => __( 'Direct', 'hello-elementor-child' ),
			'internal'       => __( 'Internal', 'hello-elementor-child' ),
			'organic_search' => __( 'Organic search', 'hello-elementor-child' ),
			'social'         => __( 'Social', 'hello-elementor-child' ),
			'referral'       => __( 'Referral', 'hello-elementor-child' ),
		);

		return isset( $labels[ $source_type ] ) ? $labels[ $source_type ] : $source_type;
	}
}

if ( ! function_exists( 'pera_analytics_render_admin_page' ) ) {
	function pera_analytics_render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'hello-elementor-child' ) );
		}

		$allowed_periods = array(
			'24h'        => __( 'Last 24 hours', 'hello-elementor-child' ),
			'7d'         => __( 'Last 7 days', 'hello-elementor-child' ),
			'14d'        => __( 'Last 14 days', 'hello-elementor-child' ),
			'30d'        => __( 'Last 30 days', 'hello-elementor-child' ),
			'this_month' => __( 'This month', 'hello-elementor-child' ),
			'last_month' => __( 'Last month', 'hello-elementor-child' ),
			'all_time'   => __( 'All time', 'hello-elementor-child' ),
		);

		$period_input = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'this_month';
		if ( ! isset( $allowed_periods[ $period_input ] ) ) {
			$period_input = 'this_month';
		}

		$window = pera_analytics_get_reporting_window( $period_input );
		$show_previous_comparison = 'all_time' !== $window['key'];
		$totals_current  = pera_analytics_get_period_totals( $window['current']['start'], $window['current']['end'] );
		$totals_previous = $show_previous_comparison
			? pera_analytics_get_period_totals( $window['previous']['start'], $window['previous']['end'] )
			: array( 'visits' => 0, 'uniques' => 0 );
		$all_page_rows   = pera_analytics_build_period_page_rows(
			$window['current']['start'],
			$window['current']['end'],
			$window['previous']['start'],
			$window['previous']['end']
		);
		$split_page_rows = pera_analytics_split_page_rows_by_type( $all_page_rows, 20, 20 );
		$source_rows     = pera_analytics_get_source_breakdown( $window['current']['start'], $window['current']['end'] );
		$top_direct_entries = pera_analytics_get_top_direct_entry_pages( $window['current']['start'], $window['current']['end'], 20 );
		$top_countries  = pera_analytics_get_top_countries( $window['current']['start'], $window['current']['end'], 10 );
		$top_referrers   = pera_analytics_get_top_referrers( $window['current']['start'], $window['current']['end'], 10 );

		$summary_change = $show_previous_comparison ? pera_analytics_percent_change( $totals_current['visits'], $totals_previous['visits'] ) : '—';
		$daily_totals   = pera_analytics_get_daily_totals_for_window( $window['current']['start'], $window['current']['end'] );
		$chart_labels   = array();
		$chart_visits   = array();
		$chart_uniques  = array();
		foreach ( $daily_totals as $daily_total ) {
			$chart_labels[]  = (string) ( $daily_total['summary_date'] ?? '' );
			$chart_visits[]  = (int) ( $daily_total['visits'] ?? 0 );
			$chart_uniques[] = (int) ( $daily_total['unique_visitors'] ?? 0 );
		}

		wp_localize_script(
			'pera-site-performance-chart',
			'peraPerformanceChartData',
			array(
				'labels'              => $chart_labels,
				'visits'              => $chart_visits,
				'uniqueVisitors'      => $chart_uniques,
				'visitsLabel'         => __( 'Visits', 'hello-elementor-child' ),
				'uniqueVisitorsLabel' => __( 'Unique visitors', 'hello-elementor-child' ),
			)
		);
		?>
		<div class="wrap pera-site-performance-admin">
			<h1><?php echo esc_html__( 'Site Performance', 'hello-elementor-child' ); ?></h1>
			<form class="pera-performance-filter" method="get" action="">
				<input type="hidden" name="page" value="pera-site-performance" />
				<label for="pera-period"><strong><?php echo esc_html__( 'Period', 'hello-elementor-child' ); ?>:</strong></label>
				<select id="pera-period" name="period">
					<?php foreach ( $allowed_periods as $period_key => $period_label ) : ?>
						<option value="<?php echo esc_attr( $period_key ); ?>" <?php selected( $window['key'], $period_key ); ?>><?php echo esc_html( $period_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Apply', 'hello-elementor-child' ), 'secondary', '', false ); ?>
			</form>
			<p class="description"><?php echo esc_html__( 'Unique visitor counts are calculated from recent raw visit data and may be unavailable for older periods after raw data is pruned.', 'hello-elementor-child' ); ?></p>

			<p><?php echo esc_html( $allowed_periods[ $window['key'] ] ); ?><?php echo $show_previous_comparison ? esc_html__( ' compared to previous matching period.', 'hello-elementor-child' ) : esc_html__( ' across all recorded analytics history.', 'hello-elementor-child' ); ?></p>

			<div class="pera-performance-kpis">
				<div class="postbox pera-performance-kpi"><strong><?php echo esc_html__( 'Total visits', 'hello-elementor-child' ); ?></strong><span class="pera-performance-kpi__value"><?php echo esc_html( number_format_i18n( $totals_current['visits'] ) ); ?></span></div>
				<div class="postbox pera-performance-kpi"><strong><?php echo esc_html__( 'Unique visitors', 'hello-elementor-child' ); ?></strong><span class="pera-performance-kpi__value"><?php echo esc_html( number_format_i18n( $totals_current['uniques'] ) ); ?></span></div>
				<div class="postbox pera-performance-kpi"><strong><?php echo esc_html__( 'Previous period visits', 'hello-elementor-child' ); ?></strong><span class="pera-performance-kpi__value"><?php echo $show_previous_comparison ? esc_html( number_format_i18n( $totals_previous['visits'] ) ) : esc_html__( '—', 'hello-elementor-child' ); ?></span></div>
				<div class="postbox pera-performance-kpi"><strong><?php echo esc_html__( '% change', 'hello-elementor-child' ); ?></strong><span class="pera-performance-kpi__value"><?php echo esc_html( $summary_change ); ?></span></div>
			</div>

			<h2><?php echo esc_html__( 'Visits by day', 'hello-elementor-child' ); ?></h2>
			<div class="postbox pera-performance-chart-card">
				<p class="description"><?php echo esc_html__( 'Daily totals for visits and unique visitors in the selected period.', 'hello-elementor-child' ); ?></p>
				<div class="pera-performance-chart-wrap">
					<canvas id="pera-visits-by-day-chart" aria-label="<?php echo esc_attr__( 'Line chart showing visits and unique visitors by day', 'hello-elementor-child' ); ?>" role="img"></canvas>
				</div>
				<noscript>
					<p><?php echo esc_html__( 'JavaScript is disabled. Showing tabular data instead.', 'hello-elementor-child' ); ?></p>
				</noscript>
				<p class="pera-performance-scroll-hint"><?php echo esc_html__( 'Scroll horizontally to view all table columns.', 'hello-elementor-child' ); ?></p>
				<div class="pera-performance-table-wrap" tabindex="0" role="region" aria-label="<?php echo esc_attr__( 'Visits by day fallback table', 'hello-elementor-child' ); ?>">
					<table class="widefat striped pera-performance-table">
						<thead><tr>
							<th><?php echo esc_html__( 'Date', 'hello-elementor-child' ); ?></th>
							<th class="pera-performance-table__number"><?php echo esc_html__( 'Visits', 'hello-elementor-child' ); ?></th>
							<th class="pera-performance-table__number"><?php echo esc_html__( 'Unique visitors', 'hello-elementor-child' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( empty( $daily_totals ) ) : ?>
							<tr><td colspan="3"><?php echo esc_html__( 'No daily visit data available for this period yet.', 'hello-elementor-child' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $daily_totals as $daily_total ) : ?>
								<tr>
									<td><?php echo esc_html( (string) ( $daily_total['summary_date'] ?? '' ) ); ?></td>
									<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (int) ( $daily_total['visits'] ?? 0 ) ) ); ?></td>
									<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (int) ( $daily_total['unique_visitors'] ?? 0 ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<h2><?php echo esc_html__( 'Visit origins', 'hello-elementor-child' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Visit origin data is based on recent raw visit records and may not be available for older/all-time periods after raw data is pruned.', 'hello-elementor-child' ); ?></p>
			<p class="pera-performance-scroll-hint"><?php echo esc_html__( 'Scroll horizontally to view all table columns.', 'hello-elementor-child' ); ?></p>
			<div class="pera-performance-table-wrap" tabindex="0" role="region" aria-label="<?php echo esc_attr__( 'Visit origins table', 'hello-elementor-child' ); ?>">
			<table class="widefat striped pera-performance-table">
				<thead><tr>
					<th><?php echo esc_html__( 'Source type', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( 'Visits', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( 'Unique visitors', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( '% of visits', 'hello-elementor-child' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $source_rows as $row ) : ?>
					<?php
					$visits  = (int) ( $row['visits'] ?? 0 );
					$uniques = (int) ( $row['uniques'] ?? 0 );
					$share   = $totals_current['visits'] > 0 ? ( $visits / $totals_current['visits'] ) * 100 : 0;
					?>
					<tr>
						<td><?php echo esc_html( pera_analytics_source_type_label( (string) ( $row['source_type'] ?? 'direct' ) ) ); ?></td>
						<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( $visits ) ); ?></td>
						<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( $uniques ) ); ?></td>
						<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( $share, 1 ) ); ?>%</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>


			<h2><?php echo esc_html__( 'Top Countries', 'hello-elementor-child' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Country data is captured from Cloudflare country headers when available; otherwise visits are grouped as Unknown.', 'hello-elementor-child' ); ?></p>
			<p class="pera-performance-scroll-hint"><?php echo esc_html__( 'Scroll horizontally to view all table columns.', 'hello-elementor-child' ); ?></p>
			<div class="pera-performance-table-wrap" tabindex="0" role="region" aria-label="<?php echo esc_attr__( 'Top countries table', 'hello-elementor-child' ); ?>">
			<table class="widefat striped pera-performance-table">
				<thead><tr>
					<th><?php echo esc_html__( 'Country', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( 'Visits', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( 'Unique visitors', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( '% of visits', 'hello-elementor-child' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $top_countries ) ) : ?>
					<tr><td colspan="4"><?php echo esc_html__( 'No country data available for this period yet.', 'hello-elementor-child' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $top_countries as $country ) : ?>
						<?php
						$visits       = (int) ( $country['visits'] ?? 0 );
						$country_code = (string) ( $country['country_code'] ?? 'XX' );
						$country_name = (string) ( $country['country_name'] ?? 'Unknown' );
						$share        = $totals_current['visits'] > 0 ? ( $visits / $totals_current['visits'] ) * 100 : 0;
						$label        = 'XX' !== $country_code ? sprintf( '%s (%s)', $country_name, $country_code ) : $country_name;
						?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( $visits ) ); ?></td>
							<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (int) ( $country['uniques'] ?? 0 ) ) ); ?></td>
							<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( $share, 1 ) ); ?>%</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			</div>

			<h2><?php echo esc_html__( 'Top Direct Entry Pages', 'hello-elementor-child' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Shows pages where visitors first entered the site with no recorded external referrer. This can include typed URLs, bookmarks, WhatsApp/email links, app traffic, privacy-stripped referrers and some bot-like traffic.', 'hello-elementor-child' ); ?></p>
			<p class="pera-performance-scroll-hint"><?php echo esc_html__( 'Scroll horizontally to view all table columns.', 'hello-elementor-child' ); ?></p>
			<div class="pera-performance-table-wrap" tabindex="0" role="region" aria-label="<?php echo esc_attr__( 'Top direct entry pages table', 'hello-elementor-child' ); ?>">
			<table class="widefat striped pera-performance-table">
				<thead><tr>
					<th><?php echo esc_html__( 'Page', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( 'Direct entries', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( 'Unique visitors', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( '% of direct entries', 'hello-elementor-child' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $top_direct_entries ) ) : ?>
					<tr><td colspan="4"><?php echo esc_html__( 'No direct entry pages available for this period yet.', 'hello-elementor-child' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $top_direct_entries as $row ) : ?>
						<?php
						$page_path  = (string) ( $row['page_path'] ?? '' );
						$page_title = '' !== (string) ( $row['page_title'] ?? '' ) ? (string) $row['page_title'] : $page_path;
						$page_url   = home_url( $page_path );
						?>
						<tr>
							<td class="column-page"><a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $page_title ); ?></a><br><small class="pera-performance-page-path"><?php echo esc_html( $page_path ); ?></small></td>
							<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (int) ( $row['direct_entries'] ?? 0 ) ) ); ?></td>
							<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (int) ( $row['unique_visitors'] ?? 0 ) ) ); ?></td>
							<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (float) ( $row['percent_of_direct_entries'] ?? 0 ), 1 ) ); ?>%</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			</div>

			<h2><?php echo esc_html__( 'Top external referrers', 'hello-elementor-child' ); ?></h2>
			<p class="pera-performance-scroll-hint"><?php echo esc_html__( 'Scroll horizontally to view all table columns.', 'hello-elementor-child' ); ?></p>
			<div class="pera-performance-table-wrap" tabindex="0" role="region" aria-label="<?php echo esc_attr__( 'Top external referrers table', 'hello-elementor-child' ); ?>">
			<table class="widefat striped pera-performance-table">
				<thead><tr>
					<th><?php echo esc_html__( 'Referrer host', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( 'Visits', 'hello-elementor-child' ); ?></th>
					<th class="pera-performance-table__number"><?php echo esc_html__( 'Unique visitors', 'hello-elementor-child' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $top_referrers ) ) : ?>
					<tr><td colspan="3"><?php echo esc_html__( 'No external referrer data available for this period yet.', 'hello-elementor-child' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $top_referrers as $referrer ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $referrer['referer_host'] ?? '' ) ); ?></td>
							<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (int) ( $referrer['visits'] ?? 0 ) ) ); ?></td>
							<td class="pera-performance-table__number"><?php echo esc_html( number_format_i18n( (int) ( $referrer['uniques'] ?? 0 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			</div>

			<h2><?php echo esc_html__( 'Top static, archive and template pages', 'hello-elementor-child' ); ?></h2>
			<?php
			pera_analytics_render_admin_pages_table(
				$split_page_rows['static'],
				esc_html__( 'Top static, archive and template pages table', 'hello-elementor-child' ),
				__( 'No static, archive or template page data available for this period yet.', 'hello-elementor-child' ),
				$show_previous_comparison
			);
			?>

			<h2><?php echo esc_html__( 'Top blog posts', 'hello-elementor-child' ); ?></h2>
			<?php
			pera_analytics_render_admin_pages_table(
				$split_page_rows['posts'],
				esc_html__( 'Top blog posts table', 'hello-elementor-child' ),
				__( 'No blog post data available for this period yet.', 'hello-elementor-child' ),
				$show_previous_comparison
			);
			?>
		</div>
		<?php
	}
}
