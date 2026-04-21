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
			$day_start = $date . ' 00:00:00';
			$day_end   = gmdate( 'Y-m-d H:i:s', strtotime( $day_start . ' +1 day' ) );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						%s AS summary_date,
						page_path,
						COALESCE(post_id, 0) AS post_id,
						MAX(page_title) AS page_title,
						COUNT(*) AS visits,
						COUNT(DISTINCT visitor_id) AS unique_visitors
					FROM {$raw_table}
					WHERE visited_at >= %s
					  AND visited_at < %s
					GROUP BY page_path, COALESCE(post_id, 0)",
					$date,
					$day_start,
					$day_end
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
	function pera_analytics_month_window( string $month = 'current' ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$tz = wp_timezone();

		$now             = new DateTimeImmutable( 'now', $tz );
		$current_start   = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
		$current_end     = $now;
		$current_elapsed = $current_end->getTimestamp() - $current_start->getTimestamp();

		$previous_start         = $current_start->modify( '-1 month' );
		$previous_end_candidate = $previous_start->modify( '+' . $current_elapsed . ' seconds' );
		$previous_month_end     = $previous_start->modify( 'first day of next month' )->setTime( 0, 0, 0 );
		$previous_end           = $previous_end_candidate > $previous_month_end ? $previous_month_end : $previous_end_candidate;

		return array(
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

if ( ! function_exists( 'pera_analytics_get_period_page_rollup' ) ) {
	function pera_analytics_get_period_page_rollup( string $start, string $end ): array {
		global $wpdb;

		$raw_table   = pera_analytics_raw_table_name();
		$daily_table = pera_analytics_daily_table_name();
		$tz          = wp_timezone();

		$start_dt = new DateTimeImmutable( $start, $tz );
		$end_dt   = new DateTimeImmutable( $end, $tz );
		$today    = ( new DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0, 0 );

		if ( $end_dt <= $start_dt ) {
			return array();
		}

		$raw_windows = array();
		$daily_rows  = array();

		$daily_start = $start_dt;
		if ( '00:00:00' !== $start_dt->format( 'H:i:s' ) ) {
			$daily_start = $start_dt->modify( 'tomorrow' )->setTime( 0, 0, 0 );
		}

		$daily_end = $end_dt->setTime( 0, 0, 0 );
		if ( $daily_end > $today ) {
			$daily_end = $today;
		}

		if ( $daily_start < $daily_end ) {
			$daily_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT page_path, MAX(page_title) AS page_title, SUM(visits) AS visits
					FROM {$daily_table}
					WHERE summary_date >= %s
					  AND summary_date < %s
					GROUP BY page_path",
					$daily_start->format( 'Y-m-d' ),
					$daily_end->format( 'Y-m-d' )
				),
				ARRAY_A
			);
		}

		if ( $start_dt < $daily_start ) {
			$raw_windows[] = array(
				'start' => $start_dt->format( 'Y-m-d H:i:s' ),
				'end'   => $daily_start->format( 'Y-m-d H:i:s' ),
			);
		}

		if ( $daily_end < $end_dt ) {
			$raw_windows[] = array(
				'start' => $daily_end->format( 'Y-m-d H:i:s' ),
				'end'   => $end_dt->format( 'Y-m-d H:i:s' ),
			);
		}

		$raw_rows = array();
		foreach ( $raw_windows as $window ) {
			$window_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT page_path, MAX(page_title) AS page_title, COUNT(*) AS visits
					FROM {$raw_table}
					WHERE visited_at >= %s
					  AND visited_at < %s
					GROUP BY page_path",
					$window['start'],
					$window['end']
				),
				ARRAY_A
			);

			if ( ! empty( $window_rows ) ) {
				$raw_rows = array_merge( $raw_rows, $window_rows );
			}
		}

		$combined = array();
		foreach ( array_merge( $daily_rows, $raw_rows ) as $row ) {
			$page_path = (string) ( $row['page_path'] ?? '' );
			if ( '' === $page_path ) {
				continue;
			}

			if ( ! isset( $combined[ $page_path ] ) ) {
				$combined[ $page_path ] = array(
					'page_path' => $page_path,
					'page_title'=> '',
					'visits'    => 0,
				);
			}

			$combined[ $page_path ]['visits'] += (int) ( $row['visits'] ?? 0 );
			if ( ! empty( $row['page_title'] ) ) {
				$combined[ $page_path ]['page_title'] = (string) $row['page_title'];
			}
		}

		return $combined;
	}
}

if ( ! function_exists( 'pera_analytics_get_period_uniques_by_path' ) ) {
	function pera_analytics_get_period_uniques_by_path( string $start, string $end ): array {
		global $wpdb;

		$raw_table = pera_analytics_raw_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					page_path,
					COUNT(DISTINCT visitor_id) AS uniques
				FROM {$raw_table}
				WHERE visited_at >= %s
				  AND visited_at < %s
				GROUP BY page_path",
				$start,
				$end
			),
			ARRAY_A
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ (string) $row['page_path'] ] = (int) $row['uniques'];
		}

		return $map;
	}
}

if ( ! function_exists( 'pera_analytics_get_top_pages' ) ) {
	function pera_analytics_get_top_pages( int $limit = 10 ): array {
		$windows = pera_analytics_month_window();

		$current_rollup = pera_analytics_get_period_page_rollup( $windows['current']['start'], $windows['current']['end'] );
		$previous_rollup = pera_analytics_get_period_page_rollup( $windows['previous']['start'], $windows['previous']['end'] );
		$current_uniques = pera_analytics_get_period_uniques_by_path( $windows['current']['start'], $windows['current']['end'] );

		$rows = array();
		foreach ( $current_rollup as $page_path => $row ) {
			$rows[] = array(
				'page_path'          => $page_path,
				'page_title'         => $row['page_title'],
				'visits_this_month'  => (int) $row['visits'],
				'uniques_this_month' => isset( $current_uniques[ $page_path ] ) ? (int) $current_uniques[ $page_path ] : 0,
				'visits_last_month'  => isset( $previous_rollup[ $page_path ] ) ? (int) $previous_rollup[ $page_path ]['visits'] : 0,
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return (int) $b['visits_this_month'] <=> (int) $a['visits_this_month'];
			}
		);

		return array_slice( $rows, 0, $limit );
	}
}

if ( ! function_exists( 'pera_analytics_get_month_totals' ) ) {
	function pera_analytics_get_month_totals(): array {
		global $wpdb;

		$raw_table = pera_analytics_raw_table_name();
		$windows   = pera_analytics_month_window();

		$current_rollup  = pera_analytics_get_period_page_rollup( $windows['current']['start'], $windows['current']['end'] );
		$previous_rollup = pera_analytics_get_period_page_rollup( $windows['previous']['start'], $windows['previous']['end'] );

		$current_visits  = 0;
		$previous_visits = 0;
		foreach ( $current_rollup as $row ) {
			$current_visits += (int) $row['visits'];
		}
		foreach ( $previous_rollup as $row ) {
			$previous_visits += (int) $row['visits'];
		}

		$current_totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_id) AS uniques
				FROM {$raw_table}
				WHERE visited_at >= %s
				  AND visited_at < %s",
				$windows['current']['start'],
				$windows['current']['end']
			),
			ARRAY_A
		);
		$previous_totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_id) AS uniques
				FROM {$raw_table}
				WHERE visited_at >= %s
				  AND visited_at < %s",
				$windows['previous']['start'],
				$windows['previous']['end']
			),
			ARRAY_A
		);

		return array(
			'current'  => array(
				'visits'  => (int) $current_visits,
				'uniques' => isset( $current_totals['uniques'] ) ? (int) $current_totals['uniques'] : 0,
			),
			'previous' => array(
				'visits'  => (int) $previous_visits,
				'uniques' => isset( $previous_totals['uniques'] ) ? (int) $previous_totals['uniques'] : 0,
			),
		);
	}
}
