<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_get_reporting_window' ) ) {
	function pera_analytics_get_reporting_window( string $period_key ): array {
		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );

		switch ( $period_key ) {
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
				'start' => $current_start->format( 'Y-m-d H:i:s' ),
				'end'   => $current_end->format( 'Y-m-d H:i:s' ),
			),
			'previous' => array(
				'start' => $previous_start->format( 'Y-m-d H:i:s' ),
				'end'   => $previous_end->format( 'Y-m-d H:i:s' ),
			),
		);
	}
}

if ( ! function_exists( 'pera_analytics_get_period_totals' ) ) {
	function pera_analytics_get_period_totals( string $start, string $end ): array {
		global $wpdb;
		$raw_table = pera_analytics_raw_table_name();

		$rollup = pera_analytics_get_period_page_rollup( $start, $end );
		$visits = 0;
		foreach ( $rollup as $row ) {
			$visits += (int) $row['visits'];
		}

		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_id) AS uniques
				FROM {$raw_table}
				WHERE visited_at >= %s
				  AND visited_at < %s",
				$start,
				$end
			),
			ARRAY_A
		);

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

if ( ! function_exists( 'pera_analytics_render_admin_page' ) ) {
	function pera_analytics_render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'hello-elementor-child' ) );
		}

		$allowed_periods = array(
			'7d'         => __( 'Last 7 days', 'hello-elementor-child' ),
			'14d'        => __( 'Last 14 days', 'hello-elementor-child' ),
			'30d'        => __( 'Last 30 days', 'hello-elementor-child' ),
			'this_month' => __( 'This month', 'hello-elementor-child' ),
			'last_month' => __( 'Last month', 'hello-elementor-child' ),
		);

		$period_input = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'this_month';
		if ( ! isset( $allowed_periods[ $period_input ] ) ) {
			$period_input = 'this_month';
		}

		$window = pera_analytics_get_reporting_window( $period_input );
		$current_rollup  = pera_analytics_get_period_page_rollup( $window['current']['start'], $window['current']['end'] );
		$previous_rollup = pera_analytics_get_period_page_rollup( $window['previous']['start'], $window['previous']['end'] );
		$current_uniques_by_path = pera_analytics_get_period_uniques_by_path( $window['current']['start'], $window['current']['end'] );
		$totals_current  = pera_analytics_get_period_totals( $window['current']['start'], $window['current']['end'] );
		$totals_previous = pera_analytics_get_period_totals( $window['previous']['start'], $window['previous']['end'] );

		$rows = array();
		foreach ( $current_rollup as $page_path => $row ) {
			$rows[] = array(
				'page_path' => $page_path,
				'page_title' => (string) $row['page_title'],
				'visits' => (int) $row['visits'],
				'uniques' => isset( $current_uniques_by_path[ $page_path ] ) ? (int) $current_uniques_by_path[ $page_path ] : 0,
				'previous_visits' => isset( $previous_rollup[ $page_path ] ) ? (int) $previous_rollup[ $page_path ]['visits'] : 0,
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return $b['visits'] <=> $a['visits'];
			}
		);
		$rows = array_slice( $rows, 0, 20 );

		$summary_change = pera_analytics_percent_change( $totals_current['visits'], $totals_previous['visits'] );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Site Performance', 'hello-elementor-child' ); ?></h1>
			<form method="get" action="">
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

			<p><?php echo esc_html( $allowed_periods[ $window['key'] ] ); ?><?php echo esc_html__( ' compared to previous matching period.', 'hello-elementor-child' ); ?></p>

			<div style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0 18px;">
				<div class="postbox" style="padding:12px;min-width:180px;"><strong><?php echo esc_html__( 'Total visits', 'hello-elementor-child' ); ?></strong><br><?php echo esc_html( number_format_i18n( $totals_current['visits'] ) ); ?></div>
				<div class="postbox" style="padding:12px;min-width:180px;"><strong><?php echo esc_html__( 'Unique visitors', 'hello-elementor-child' ); ?></strong><br><?php echo esc_html( number_format_i18n( $totals_current['uniques'] ) ); ?></div>
				<div class="postbox" style="padding:12px;min-width:180px;"><strong><?php echo esc_html__( 'Previous period visits', 'hello-elementor-child' ); ?></strong><br><?php echo esc_html( number_format_i18n( $totals_previous['visits'] ) ); ?></div>
				<div class="postbox" style="padding:12px;min-width:180px;"><strong><?php echo esc_html__( '% change', 'hello-elementor-child' ); ?></strong><br><?php echo esc_html( $summary_change ); ?></div>
			</div>

			<h2><?php echo esc_html__( 'Top Pages', 'hello-elementor-child' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php echo esc_html__( 'Page', 'hello-elementor-child' ); ?></th>
					<th style="text-align:right;"><?php echo esc_html__( 'Visits', 'hello-elementor-child' ); ?></th>
					<th style="text-align:right;"><?php echo esc_html__( 'Unique visitors', 'hello-elementor-child' ); ?></th>
					<th style="text-align:right;"><?php echo esc_html__( 'Previous period visits', 'hello-elementor-child' ); ?></th>
					<th style="text-align:right;"><?php echo esc_html__( '% change', 'hello-elementor-child' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="5"><?php echo esc_html__( 'No data available for this period yet.', 'hello-elementor-child' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$page_path = (string) $row['page_path'];
						$page_title = '' !== $row['page_title'] ? $row['page_title'] : $page_path;
						$page_url = home_url( $page_path );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $page_title ); ?></a><br><small><?php echo esc_html( $page_path ); ?></small></td>
							<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $row['visits'] ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $row['uniques'] ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $row['previous_visits'] ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( pera_analytics_percent_change( $row['visits'], $row['previous_visits'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
