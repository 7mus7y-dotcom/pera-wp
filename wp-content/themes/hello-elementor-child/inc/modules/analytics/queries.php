<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_run_daily_aggregation' ) ) {
	function pera_analytics_run_daily_aggregation(): void {
		global $wpdb;

		$raw_table   = pera_analytics_raw_table_name();
		$daily_table = pera_analytics_daily_table_name();
		$today       = gmdate( 'Y-m-d' );

		$dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(visited_at) AS d
				 FROM {$raw_table}
				 WHERE visited_at < %s
				 ORDER BY d DESC
				 LIMIT 14",
				$today . ' 00:00:00'
			)
		);

		if ( empty( $dates ) ) {
			return;
		}

		foreach ( $dates as $date ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						DATE(visited_at) AS summary_date,
						page_path,
						COALESCE(post_id, 0) AS post_id,
						MAX(page_title) AS page_title,
						COUNT(*) AS visits,
						COUNT(DISTINCT visitor_id) AS unique_visitors
					FROM {$raw_table}
					WHERE DATE(visited_at) = %s
					GROUP BY DATE(visited_at), page_path, COALESCE(post_id, 0)",
					$date
				),
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$daily_table}
						(summary_date, page_path, post_id, page_title, visits, unique_visitors)
						VALUES (%s, %s, %d, %s, %d, %d)
						ON DUPLICATE KEY UPDATE
							page_title = VALUES(page_title),
							visits = VALUES(visits),
							unique_visitors = VALUES(unique_visitors)",
						$row['summary_date'],
						$row['page_path'],
						(int) $row['post_id'],
						$row['page_title'],
						(int) $row['visits'],
						(int) $row['unique_visitors']
					)
				);
			}
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$raw_table} WHERE visited_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) )
			)
		);
	}
}
add_action( 'pera_analytics_daily_aggregate', 'pera_analytics_run_daily_aggregation' );

if ( ! function_exists( 'pera_analytics_month_window' ) ) {
	function pera_analytics_month_window( string $month = 'current' ): array {
		$now = current_time( 'timestamp' );

		if ( 'previous' === $month ) {
			$start = strtotime( date_i18n( 'Y-m-01 00:00:00', strtotime( '-1 month', $now ) ) );
			$end   = strtotime( date_i18n( 'Y-m-t 23:59:59', strtotime( '-1 month', $now ) ) );
		} else {
			$start = strtotime( date_i18n( 'Y-m-01 00:00:00', $now ) );
			$end   = $now;
		}

		return array(
			'start' => date_i18n( 'Y-m-d H:i:s', $start ),
			'end'   => date_i18n( 'Y-m-d H:i:s', $end ),
		);
	}
}

if ( ! function_exists( 'pera_analytics_get_top_pages' ) ) {
	function pera_analytics_get_top_pages( int $limit = 10 ): array {
		global $wpdb;

		$raw_table = pera_analytics_raw_table_name();
		$current   = pera_analytics_month_window( 'current' );
		$previous  = pera_analytics_month_window( 'previous' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.page_path,
					MAX(c.page_title) AS page_title,
					COUNT(*) AS visits_this_month,
					COUNT(DISTINCT c.visitor_id) AS uniques_this_month,
					COALESCE(p.visits_last_month, 0) AS visits_last_month
				FROM {$raw_table} c
				LEFT JOIN (
					SELECT page_path, COUNT(*) AS visits_last_month
					FROM {$raw_table}
					WHERE visited_at BETWEEN %s AND %s
					GROUP BY page_path
				) p ON p.page_path = c.page_path
				WHERE c.visited_at BETWEEN %s AND %s
				GROUP BY c.page_path, p.visits_last_month
				ORDER BY visits_this_month DESC
				LIMIT %d",
				$previous['start'],
				$previous['end'],
				$current['start'],
				$current['end'],
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}

if ( ! function_exists( 'pera_analytics_get_month_totals' ) ) {
	function pera_analytics_get_month_totals(): array {
		global $wpdb;

		$raw_table = pera_analytics_raw_table_name();
		$current   = pera_analytics_month_window( 'current' );
		$previous  = pera_analytics_month_window( 'previous' );

		$current_totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS uniques
				FROM {$raw_table}
				WHERE visited_at BETWEEN %s AND %s",
				$current['start'],
				$current['end']
			),
			ARRAY_A
		);

		$previous_totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS uniques
				FROM {$raw_table}
				WHERE visited_at BETWEEN %s AND %s",
				$previous['start'],
				$previous['end']
			),
			ARRAY_A
		);

		return array(
			'current'  => array(
				'visits'  => isset( $current_totals['visits'] ) ? (int) $current_totals['visits'] : 0,
				'uniques' => isset( $current_totals['uniques'] ) ? (int) $current_totals['uniques'] : 0,
			),
			'previous' => array(
				'visits'  => isset( $previous_totals['visits'] ) ? (int) $previous_totals['visits'] : 0,
				'uniques' => isset( $previous_totals['uniques'] ) ? (int) $previous_totals['uniques'] : 0,
			),
		);
	}
}
