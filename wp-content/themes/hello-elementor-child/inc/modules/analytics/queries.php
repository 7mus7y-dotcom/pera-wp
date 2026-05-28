<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_has_suspected_bot_column' ) ) {
	function pera_analytics_has_suspected_bot_column(): bool {
		static $has_column = null;

		if ( null !== $has_column ) {
			return $has_column;
		}

		global $wpdb;
		$raw_table = pera_analytics_raw_table_name();
		$column    = $wpdb->get_var( "SHOW COLUMNS FROM {$raw_table} LIKE 'is_suspected_bot'" );
		$has_column = ! empty( $column );

		return $has_column;
	}
}

if ( ! function_exists( 'pera_analytics_suspected_bot_where_clause' ) ) {
	function pera_analytics_suspected_bot_where_clause( string $alias = '' ): string {
		if ( ! pera_analytics_has_suspected_bot_column() ) {
			return '';
		}

		$qualified = '' === $alias ? 'is_suspected_bot' : $alias . '.is_suspected_bot';
		return " AND {$qualified} = 0";
	}
}

if ( ! function_exists( 'pera_analytics_suspected_bot_thresholds' ) ) {
	function pera_analytics_suspected_bot_thresholds(): array {
		return array(
			'min_visits'        => (int) apply_filters( 'pera_analytics_suspected_bot_min_visits', 10 ),
			'max_span_seconds'  => (int) apply_filters( 'pera_analytics_suspected_bot_max_span_seconds', 180 ),
			'lookback_days'     => (int) apply_filters( 'pera_analytics_suspected_bot_lookback_days', 90 ),
		);
	}
}

if ( ! function_exists( 'pera_analytics_mark_suspected_bot_visits' ) ) {
	function pera_analytics_mark_suspected_bot_visits( ?string $start = null, ?string $end = null ): int {
		if ( ! pera_analytics_has_suspected_bot_column() ) {
			return 0;
		}

		global $wpdb;
		$raw_table   = pera_analytics_raw_table_name();
		$thresholds  = pera_analytics_suspected_bot_thresholds();
		$window_start = $start ?: gmdate( 'Y-m-d H:i:s', strtotime( '-' . max( 1, $thresholds['lookback_days'] ) . ' days' ) );
		$window_end   = $end ?: gmdate( 'Y-m-d H:i:s' );

		$sql = "UPDATE {$raw_table} v
			INNER JOIN (
				SELECT visitor_id
				FROM {$raw_table}
				WHERE visited_at >= %s
				  AND visited_at < %s
				GROUP BY visitor_id
				HAVING COUNT(*) >= %d
				   AND TIMESTAMPDIFF(SECOND, MIN(visited_at), MAX(visited_at)) <= %d
				   AND SUM(CASE WHEN referer_host IS NULL OR referer_host = '' THEN 1 ELSE 0 END) = COUNT(*)
			) flagged ON flagged.visitor_id = v.visitor_id
			SET v.is_suspected_bot = 1
			WHERE v.visited_at >= %s
			  AND v.visited_at < %s
				  " . pera_analytics_suspected_bot_where_clause( 'v' );

		$wpdb->query( $wpdb->prepare( $sql, $window_start, $window_end, $thresholds['min_visits'], $thresholds['max_span_seconds'], $window_start, $window_end ) );
		return (int) $wpdb->rows_affected;
	}
}

if ( ! function_exists( 'pera_analytics_get_suspected_bot_visits_count' ) ) {
	function pera_analytics_get_suspected_bot_visits_count( ?string $start = null, ?string $end = null ): int {
		if ( ! pera_analytics_has_suspected_bot_column() ) {
			return 0;
		}

		global $wpdb;
		$raw_table = pera_analytics_raw_table_name();
		$end       = $end ?: gmdate( 'Y-m-d H:i:s' );

		if ( null === $start ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$raw_table} WHERE visited_at < %s AND is_suspected_bot = 1", $end ) );
		} else {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$raw_table} WHERE visited_at >= %s AND visited_at < %s AND is_suspected_bot = 1", $start, $end ) );
		}

		return (int) $count;
	}
}

if ( ! function_exists( 'pera_analytics_run_daily_aggregation' ) ) {
	function pera_analytics_run_daily_aggregation(): void {
		global $wpdb;

		$raw_table   = pera_analytics_raw_table_name();
		$daily_table = pera_analytics_daily_table_name();
		$today       = gmdate( 'Y-m-d' );
		$thresholds  = pera_analytics_suspected_bot_thresholds();

		pera_analytics_mark_suspected_bot_visits(
			gmdate( 'Y-m-d H:i:s', strtotime( '-' . max( 1, $thresholds['lookback_days'] ) . ' days' ) ),
			$today . ' 00:00:00'
		);

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
						  " . pera_analytics_suspected_bot_where_clause() . "
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
	function pera_analytics_get_period_page_rollup( ?string $start, string $end ): array {
		global $wpdb;

		$raw_table   = pera_analytics_raw_table_name();
		$daily_table = pera_analytics_daily_table_name();
		$tz          = wp_timezone();

		$start_dt = null === $start ? null : new DateTimeImmutable( $start, $tz );
		$end_dt   = new DateTimeImmutable( $end, $tz );
		$today    = ( new DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0, 0 );

		if ( null !== $start_dt && $end_dt <= $start_dt ) {
			return array();
		}

		$raw_windows = array();
		$daily_rows  = array();

		$daily_start = $start_dt;
		if ( null !== $start_dt && '00:00:00' !== $start_dt->format( 'H:i:s' ) ) {
			$daily_start = $start_dt->modify( 'tomorrow' )->setTime( 0, 0, 0 );
		}

		$daily_end = $end_dt->setTime( 0, 0, 0 );
		if ( $daily_end > $today ) {
			$daily_end = $today;
		}

		if ( null === $daily_start || $daily_start < $daily_end ) {
			if ( null === $daily_start ) {
				$daily_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT page_path, MAX(page_title) AS page_title, SUM(visits) AS visits
						FROM {$daily_table}
						WHERE summary_date < %s
						GROUP BY page_path",
						$daily_end->format( 'Y-m-d' )
					),
					ARRAY_A
				);
			} else {
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
		}

		if ( null !== $start_dt && null !== $daily_start && $start_dt < $daily_start ) {
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
		} elseif ( null === $start_dt && empty( $daily_rows ) ) {
			$raw_windows[] = array(
				'start' => null,
				'end'   => $end_dt->format( 'Y-m-d H:i:s' ),
			);
		}

		$raw_rows = array();
		foreach ( $raw_windows as $window ) {
			if ( null === $window['start'] ) {
				$window_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT page_path, MAX(page_title) AS page_title, COUNT(*) AS visits
							FROM {$raw_table}
							WHERE visited_at < %s
							  " . pera_analytics_suspected_bot_where_clause() . "
							GROUP BY page_path",
						$window['end']
					),
					ARRAY_A
				);
			} else {
				$window_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT page_path, MAX(page_title) AS page_title, COUNT(*) AS visits
							FROM {$raw_table}
							WHERE visited_at >= %s
							  AND visited_at < %s
							  " . pera_analytics_suspected_bot_where_clause() . "
							GROUP BY page_path",
						$window['start'],
						$window['end']
					),
					ARRAY_A
				);
			}

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

if ( ! function_exists( 'pera_analytics_get_daily_totals_for_window' ) ) {
	function pera_analytics_get_daily_totals_for_window( ?string $start, string $end ): array {
		global $wpdb;

		$daily_table = pera_analytics_daily_table_name();
		$raw_table   = pera_analytics_raw_table_name();
		$tz          = wp_timezone();

		$start_dt = null === $start ? null : new DateTimeImmutable( $start, $tz );
		$end_dt   = new DateTimeImmutable( $end, $tz );

		if ( null !== $start_dt && $end_dt <= $start_dt ) {
			return array();
		}

		$today_date = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
		$range_start = null === $start_dt ? null : $start_dt->setTime( 0, 0, 0 );
		$range_end   = $end_dt->setTime( 0, 0, 0 );

		$rows = array();
		if ( null === $range_start ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT summary_date, SUM(visits) AS visits, SUM(unique_visitors) AS unique_visitors
					FROM {$daily_table}
					WHERE summary_date < %s
					GROUP BY summary_date
					ORDER BY summary_date ASC",
					$range_end->format( 'Y-m-d' )
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT summary_date, SUM(visits) AS visits, SUM(unique_visitors) AS unique_visitors
					FROM {$daily_table}
					WHERE summary_date BETWEEN %s AND %s
					GROUP BY summary_date
					ORDER BY summary_date ASC",
					$range_start->format( 'Y-m-d' ),
					$range_end->format( 'Y-m-d' )
				),
				ARRAY_A
			);
		}

		$totals_by_date = array();
		foreach ( $rows as $row ) {
			$date_key = (string) ( $row['summary_date'] ?? '' );
			if ( '' === $date_key ) {
				continue;
			}
			$totals_by_date[ $date_key ] = array(
				'visits'          => (int) ( $row['visits'] ?? 0 ),
				'unique_visitors' => (int) ( $row['unique_visitors'] ?? 0 ),
			);
		}

		// Optional enhancement: if the selected window includes today and the daily rollup
		// for today is unavailable, append today's counts from raw visit data.
		if ( $end_dt->format( 'Y-m-d' ) >= $today_date ) {
			$includes_today = null === $range_start || $range_start->format( 'Y-m-d' ) <= $today_date;
			if ( $includes_today && ! isset( $totals_by_date[ $today_date ] ) ) {
				$today_start = ( new DateTimeImmutable( 'today', $tz ) )->format( 'Y-m-d H:i:s' );
				$raw_today   = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS unique_visitors
						FROM {$raw_table}
						WHERE visited_at >= %s
						  AND visited_at < %s
						  " . pera_analytics_suspected_bot_where_clause(),
						$today_start,
						$end_dt->format( 'Y-m-d H:i:s' )
					),
					ARRAY_A
				);

				$totals_by_date[ $today_date ] = array(
					'visits'          => (int) ( $raw_today['visits'] ?? 0 ),
					'unique_visitors' => (int) ( $raw_today['unique_visitors'] ?? 0 ),
				);
			}
		}

		if ( null === $range_start ) {
			if ( empty( $totals_by_date ) ) {
				return array();
			}
			$ordered_dates = array_keys( $totals_by_date );
			sort( $ordered_dates );
			$range_start = new DateTimeImmutable( $ordered_dates[0], $tz );
		}

		$filled_rows = array();
		for ( $cursor = $range_start; $cursor <= $range_end; $cursor = $cursor->modify( '+1 day' ) ) {
			$date_key = $cursor->format( 'Y-m-d' );
			$filled_rows[] = array(
				'summary_date'     => $date_key,
				'visits'           => isset( $totals_by_date[ $date_key ] ) ? (int) $totals_by_date[ $date_key ]['visits'] : 0,
				'unique_visitors'  => isset( $totals_by_date[ $date_key ] ) ? (int) $totals_by_date[ $date_key ]['unique_visitors'] : 0,
			);
		}

		return $filled_rows;
	}
}

if ( ! function_exists( 'pera_analytics_get_period_uniques_by_path' ) ) {
	function pera_analytics_get_period_uniques_by_path( ?string $start, string $end ): array {
		global $wpdb;

		$raw_table = pera_analytics_raw_table_name();

		if ( null === $start ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						page_path,
						COUNT(DISTINCT visitor_id) AS uniques
						FROM {$raw_table}
						WHERE visited_at < %s
						  " . pera_analytics_suspected_bot_where_clause() . "
						GROUP BY page_path",
					$end
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						page_path,
						COUNT(DISTINCT visitor_id) AS uniques
						FROM {$raw_table}
						WHERE visited_at >= %s
						  AND visited_at < %s
						  " . pera_analytics_suspected_bot_where_clause() . "
						GROUP BY page_path",
					$start,
					$end
				),
				ARRAY_A
			);
		}

		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ (string) $row['page_path'] ] = (int) $row['uniques'];
		}

		return $map;
	}
}

if ( ! function_exists( 'pera_analytics_build_period_page_rows' ) ) {
	function pera_analytics_build_period_page_rows( ?string $current_start, string $current_end, ?string $previous_start, ?string $previous_end ): array {
		$current_rollup  = pera_analytics_get_period_page_rollup( $current_start, $current_end );
		$previous_rollup = null === $previous_end ? array() : pera_analytics_get_period_page_rollup( $previous_start, $previous_end );
		$current_uniques = pera_analytics_get_period_uniques_by_path( $current_start, $current_end );

		$rows = array();
		foreach ( $current_rollup as $page_path => $row ) {
			$rows[] = array(
				'page_path'          => $page_path,
				'page_title'         => (string) $row['page_title'],
				'visits'             => (int) $row['visits'],
				'uniques'            => isset( $current_uniques[ $page_path ] ) ? (int) $current_uniques[ $page_path ] : 0,
				'previous_visits'    => isset( $previous_rollup[ $page_path ] ) ? (int) $previous_rollup[ $page_path ]['visits'] : 0,
				'visits_this_month'  => (int) $row['visits'],
				'uniques_this_month' => isset( $current_uniques[ $page_path ] ) ? (int) $current_uniques[ $page_path ] : 0,
				'visits_last_month'  => isset( $previous_rollup[ $page_path ] ) ? (int) $previous_rollup[ $page_path ]['visits'] : 0,
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return (int) $b['visits'] <=> (int) $a['visits'];
			}
		);

		return $rows;
	}
}

if ( ! function_exists( 'pera_analytics_resolve_path_post_id' ) ) {
	function pera_analytics_resolve_path_post_id( string $page_path ): int {
		static $cache = array();

		if ( isset( $cache[ $page_path ] ) ) {
			return $cache[ $page_path ];
		}

		$path = '/' . ltrim( trim( $page_path ), '/' );
		if ( '/' === $path ) {
			$cache[ $page_path ] = 0;
			return 0;
		}

		$post_id = url_to_postid( home_url( $path ) );

		$cache[ $page_path ] = $post_id > 0 ? (int) $post_id : 0;
		return $cache[ $page_path ];
	}
}

if ( ! function_exists( 'pera_analytics_is_blog_post_path' ) ) {
	function pera_analytics_is_blog_post_path( string $page_path ): bool {
		$post_id = pera_analytics_resolve_path_post_id( $page_path );

		return $post_id > 0 && 'post' === get_post_type( $post_id );
	}
}

if ( ! function_exists( 'pera_analytics_split_page_rows_by_type' ) ) {
	function pera_analytics_split_page_rows_by_type( array $rows, int $static_limit = 20, int $posts_limit = 20 ): array {
		$static_rows = array();
		$post_rows   = array();

		foreach ( $rows as $row ) {
			$page_path = (string) ( $row['page_path'] ?? '' );
			if ( pera_analytics_is_blog_post_path( $page_path ) ) {
				$post_rows[] = $row;
				continue;
			}

			$static_rows[] = $row;
		}

		return array(
			'static' => array_slice( $static_rows, 0, $static_limit ),
			'posts'  => array_slice( $post_rows, 0, $posts_limit ),
		);
	}
}

if ( ! function_exists( 'pera_analytics_get_top_pages' ) ) {
	function pera_analytics_get_top_pages( int $limit = 10 ): array {
		$windows = pera_analytics_month_window();

		return array_slice(
			pera_analytics_build_period_page_rows(
				$windows['current']['start'],
				$windows['current']['end'],
				$windows['previous']['start'],
				$windows['previous']['end']
			),
			0,
			$limit
		);
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
				  AND visited_at < %s
				  " . pera_analytics_suspected_bot_where_clause(),
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
				  AND visited_at < %s
				  " . pera_analytics_suspected_bot_where_clause(),
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

if ( ! function_exists( 'pera_analytics_get_source_breakdown' ) ) {
	function pera_analytics_get_source_breakdown( ?string $start, string $end ): array {
		global $wpdb;
		$raw_table = pera_analytics_raw_table_name();

		if ( null === $start ) {
			$sql  = "SELECT source_type, COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS uniques FROM {$raw_table} WHERE visited_at < %s" . pera_analytics_suspected_bot_where_clause() . " GROUP BY source_type ORDER BY visits DESC";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $end ), ARRAY_A );
		} else {
			$sql  = "SELECT source_type, COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS uniques FROM {$raw_table} WHERE visited_at >= %s AND visited_at < %s" . pera_analytics_suspected_bot_where_clause() . " GROUP BY source_type ORDER BY visits DESC";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $start, $end ), ARRAY_A );
		}

		$all_sources = array(
			'direct'         => array( 'source_type' => 'direct', 'visits' => 0, 'uniques' => 0 ),
			'internal'       => array( 'source_type' => 'internal', 'visits' => 0, 'uniques' => 0 ),
			'organic_search' => array( 'source_type' => 'organic_search', 'visits' => 0, 'uniques' => 0 ),
			'social'         => array( 'source_type' => 'social', 'visits' => 0, 'uniques' => 0 ),
			'referral'       => array( 'source_type' => 'referral', 'visits' => 0, 'uniques' => 0 ),
		);

		foreach ( $rows as $row ) {
			$key = (string) ( $row['source_type'] ?? 'direct' );
			if ( ! isset( $all_sources[ $key ] ) ) {
				$key = 'referral';
			}
			$all_sources[ $key ]['visits']  += (int) ( $row['visits'] ?? 0 );
			$all_sources[ $key ]['uniques'] += (int) ( $row['uniques'] ?? 0 );
		}

		return array_values( $all_sources );
	}
}

if ( ! function_exists( 'pera_analytics_get_top_referrers' ) ) {
	function pera_analytics_get_top_referrers( ?string $start, string $end, int $limit = 10 ): array {
		global $wpdb;
		$raw_table = pera_analytics_raw_table_name();
		$limit     = max( 1, $limit );

		if ( null === $start ) {
			$sql = "SELECT referer_host, COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS uniques
				FROM {$raw_table}
				WHERE visited_at < %s
				  " . pera_analytics_suspected_bot_where_clause() . "
				  AND is_internal = 0
				  AND is_direct = 0
				  AND referer_host IS NOT NULL
				  AND referer_host <> ''
				GROUP BY referer_host
				ORDER BY visits DESC
				LIMIT %d";
			return $wpdb->get_results( $wpdb->prepare( $sql, $end, $limit ), ARRAY_A );
		}

		$sql = "SELECT referer_host, COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS uniques
			FROM {$raw_table}
				WHERE visited_at >= %s
				  AND visited_at < %s
				  " . pera_analytics_suspected_bot_where_clause() . "
				  AND is_internal = 0
			  AND is_direct = 0
			  AND referer_host IS NOT NULL
			  AND referer_host <> ''
			GROUP BY referer_host
			ORDER BY visits DESC
			LIMIT %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, $start, $end, $limit ), ARRAY_A );
	}
}

if ( ! function_exists( 'pera_analytics_get_top_direct_entry_pages' ) ) {
	function pera_analytics_get_top_direct_entry_pages( ?string $start, string $end, int $limit = 20 ): array {
		global $wpdb;
		$raw_table = pera_analytics_raw_table_name();
		$limit     = max( 1, $limit );
		$bot_where = pera_analytics_suspected_bot_where_clause( 'first' );

		$period_where = null === $start
			? 'visited_at < %s'
			: 'visited_at >= %s AND visited_at < %s';
		$period_args  = null === $start ? array( $end ) : array( $start, $end );

		$sql = "SELECT first.page_path,
			MAX(first.page_title) AS page_title,
			COUNT(*) AS direct_entries,
			COUNT(DISTINCT first.visitor_id) AS unique_visitors
		FROM (
			SELECT visitor_id, MIN(visited_at) AS first_visit
			FROM {$raw_table}
			WHERE {$period_where}
			GROUP BY visitor_id
		) AS sessions
		INNER JOIN {$raw_table} AS first
			ON first.visitor_id = sessions.visitor_id
			AND first.visited_at = sessions.first_visit
		WHERE first.source_type = 'direct'
			AND first.page_path IS NOT NULL
			AND first.page_path <> ''{$bot_where}
		GROUP BY first.page_path
		ORDER BY direct_entries DESC
		LIMIT %d";

		$query_args = array_merge( $period_args, array( $limit ) );
		$rows       = $wpdb->get_results( $wpdb->prepare( $sql, ...$query_args ), ARRAY_A );
		if ( empty( $rows ) ) {
			return array();
		}

		$total_sql = "SELECT COUNT(*) AS total_direct_entries
		FROM (
			SELECT first.visitor_id
			FROM (
				SELECT visitor_id, MIN(visited_at) AS first_visit
				FROM {$raw_table}
				WHERE {$period_where}
				GROUP BY visitor_id
			) AS sessions
			INNER JOIN {$raw_table} AS first
				ON first.visitor_id = sessions.visitor_id
				AND first.visited_at = sessions.first_visit
			WHERE first.source_type = 'direct'
				AND first.page_path IS NOT NULL
				AND first.page_path <> ''{$bot_where}
		) AS direct_sessions";
		$total_direct_entries = (int) $wpdb->get_var( $wpdb->prepare( $total_sql, ...$period_args ) );

		foreach ( $rows as &$row ) {
			$direct_entries                 = (int) ( $row['direct_entries'] ?? 0 );
			$row['percent_of_direct_entries'] = $total_direct_entries > 0 ? ( $direct_entries / $total_direct_entries ) * 100 : 0;
		}
		unset( $row );

		return $rows;
	}
}
