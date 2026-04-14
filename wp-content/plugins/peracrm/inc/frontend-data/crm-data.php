<?php
/**
 * CRM dashboard data helpers (read-only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! function_exists( 'pera_crm_format_date_dmy' ) ) {
	/**
	 * Format a timestamp as DD/MM/YY.
	 */
	function pera_crm_format_date_dmy( int $timestamp, ?DateTimeZone $timezone = null ): string {
		if ( $timestamp <= 0 ) {
			return '';
		}

		return wp_date( 'd/m/y', $timestamp, $timezone ?: wp_timezone() );
	}
}

if ( ! function_exists( 'pera_crm_format_time_hm' ) ) {
	/**
	 * Format a timestamp as HH:MM (24-hour).
	 */
	function pera_crm_format_time_hm( int $timestamp, ?DateTimeZone $timezone = null ): string {
		if ( $timestamp <= 0 ) {
			return '';
		}

		return wp_date( 'H:i', $timestamp, $timezone ?: wp_timezone() );
	}
}

if ( ! function_exists( 'pera_crm_format_datetime_dmy_hm' ) ) {
	/**
	 * Format a timestamp as DD/MM/YY HH:MM.
	 */
	function pera_crm_format_datetime_dmy_hm( int $timestamp, ?DateTimeZone $timezone = null ): string {
		if ( $timestamp <= 0 ) {
			return '';
		}

		$zone = $timezone ?: wp_timezone();
		return pera_crm_format_date_dmy( $timestamp, $zone ) . ' ' . pera_crm_format_time_hm( $timestamp, $zone );
	}
}

if ( ! function_exists( 'pera_crm_get_pipeline_stages' ) ) {
	/**
	 * Get canonical lead pipeline stages.
	 *
	 * @return array<string,string>
	 */
	function pera_crm_get_pipeline_stages(): array {
		if ( function_exists( 'peracrm_party_get_pipeline_stages' ) ) {
			$stages = peracrm_party_get_pipeline_stages();
			if ( is_array( $stages ) && ! empty( $stages ) ) {
				return $stages;
			}
		}

		if ( function_exists( 'peracrm_get_lead_pipeline_stages' ) ) {
			$stages = peracrm_get_lead_pipeline_stages();
			if ( is_array( $stages ) && ! empty( $stages ) ) {
				return $stages;
			}
		}

		return array(
			'new_enquiry'      => 'New enquiry',
			'qualified'        => 'Qualified',
			'viewing_arranged' => 'Viewing arranged',
			'offer_made'       => 'Offer made',
			'deal_closed'      => 'Deal closed',
			'deal_lost'        => 'Deal lost',
		);
	}
}

if ( ! function_exists( 'pera_crm_normalize_stage_counts' ) ) {
	/**
	 * Normalize stage counts from helper responses.
	 *
	 * @param mixed $raw Raw helper response.
	 * @return array<string,int>
	 */
	function pera_crm_normalize_stage_counts( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$counts = array();
		foreach ( $raw as $key => $value ) {
			if ( is_string( $key ) || is_int( $key ) ) {
				if ( is_numeric( $value ) ) {
					$counts[ sanitize_key( (string) $key ) ] = (int) $value;
					continue;
				}

				if ( is_array( $value ) ) {
					$stage = '';
					if ( isset( $value['stage'] ) ) {
						$stage = (string) $value['stage'];
					} elseif ( isset( $value['status'] ) ) {
						$stage = (string) $value['status'];
					}

					$count = 0;
					if ( isset( $value['count'] ) && is_numeric( $value['count'] ) ) {
						$count = (int) $value['count'];
					} elseif ( isset( $value['total'] ) && is_numeric( $value['total'] ) ) {
						$count = (int) $value['total'];
					}

					if ( '' !== $stage ) {
						$counts[ sanitize_key( $stage ) ] = $count;
					}
				}
			}
		}

		return $counts;
	}
}

if ( ! function_exists( 'pera_crm_fetch_stage_counts' ) ) {
	/**
	 * Fetch pipeline counts via MU plugin helpers when available.
	 *
	 * @param string[] $stage_keys Stage keys to request.
	 * @return array{counts:array<string,int>,available:bool}
	 */
	function pera_crm_fetch_stage_counts( array $stage_keys ): array {
		$callbacks = array(
			'peracrm_party_get_counts_by_stage',
			'peracrm_party_get_stage_counts',
			'peracrm_party_counts_by_stage',
			'peracrm_parties_count_by_stage',
		);

		foreach ( $callbacks as $callback ) {
			if ( ! function_exists( $callback ) ) {
				continue;
			}

			$response = call_user_func( $callback, $stage_keys );
			$counts   = pera_crm_normalize_stage_counts( $response );

			if ( empty( $counts ) ) {
				$response = call_user_func( $callback );
				$counts   = pera_crm_normalize_stage_counts( $response );
			}

			if ( ! empty( $counts ) ) {
				return array(
					'counts'    => $counts,
					'available' => true,
				);
			}
		}

		return array(
			'counts'    => array(),
			'available' => false,
		);
	}
}

if ( ! function_exists( 'pera_crm_fetch_overdue_reminders_count' ) ) {
	/**
	 * Fetch overdue reminders count if reminders table exists.
	 */
	function pera_crm_fetch_overdue_reminders_count(): ?int {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return null;
		}

		$table_candidates = array( $wpdb->prefix . 'crm_reminders' );

		$reminders_table = '';
		foreach ( $table_candidates as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( $exists === $table ) {
				$reminders_table = $table;
				break;
			}
		}

		if ( '' === $reminders_table ) {
			return null;
		}

		$callbacks = array(
			'peracrm_reminders_count_overdue',
			'peracrm_reminder_count_overdue',
			'peracrm_activity_count_overdue_reminders',
		);

		foreach ( $callbacks as $callback ) {
			if ( function_exists( $callback ) ) {
				$count = call_user_func( $callback );
				if ( is_numeric( $count ) ) {
					return max( 0, (int) $count );
				}
			}
		}

		$now = current_time( 'mysql' );
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$reminders_table} WHERE due_at < %s AND status = %s", $now, pera_crm_reminders_open_status() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$count = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return is_numeric( $count ) ? max( 0, (int) $count ) : 0;
	}
}

if ( ! function_exists( 'pera_crm_get_overdue_reminders_count_for_current_user' ) ) {
	/**
	 * Fetch overdue reminders count in the same scope as the CRM dashboard tasks.
	 */
	function pera_crm_get_overdue_reminders_count_for_current_user(): int {
		if ( ! is_user_logged_in() || ! function_exists( 'pera_crm_user_can_access' ) || ! pera_crm_user_can_access() ) {
			return 0;
		}

		$real_user_id          = get_current_user_id();
		$effective_user_id     = function_exists( 'peracrm_get_effective_crm_user_id' ) ? peracrm_get_effective_crm_user_id() : $real_user_id;
		$is_impersonating      = function_exists( 'peracrm_is_impersonating_crm_user' ) && peracrm_is_impersonating_crm_user();
		$effective_is_employee = function_exists( 'pera_crm_user_is_employee' ) && pera_crm_user_is_employee( $effective_user_id );

		if ( function_exists( 'peracrm_reminders_count_for_advisor' ) && ( $is_impersonating || $effective_is_employee ) ) {
			return max( 0, (int) peracrm_reminders_count_for_advisor( $effective_user_id, pera_crm_reminders_open_status(), 'overdue' ) );
		}

		$count = pera_crm_fetch_overdue_reminders_count();
		return null === $count ? 0 : max( 0, (int) $count );
	}
}

if ( ! function_exists( 'pera_crm_reminders_open_status' ) ) {
	/**
	 * Resolve status value used for open reminders.
	 */
	function pera_crm_reminders_open_status(): string {
		if ( function_exists( 'peracrm_reminders_allowed_statuses' ) ) {
			$statuses = peracrm_reminders_allowed_statuses();
			if ( is_array( $statuses ) ) {
				if ( in_array( 'open', $statuses, true ) ) {
					return 'open';
				}
				if ( in_array( 'pending', $statuses, true ) ) {
					return 'pending';
				}
			}
		}

		return 'open';
	}
}

if ( ! function_exists( 'pera_crm_reminders_closed_statuses' ) ) {
	/**
	 * Resolve reminder statuses that should be treated as closed/completed.
	 *
	 * @return string[]
	 */
	function pera_crm_reminders_closed_statuses(): array {
		$closed = array( 'done', 'dismissed', 'completed', 'closed' );

		if ( function_exists( 'peracrm_reminders_allowed_statuses' ) ) {
			$statuses = peracrm_reminders_allowed_statuses();
			if ( is_array( $statuses ) && ! empty( $statuses ) ) {
				foreach ( $statuses as $status ) {
					$status = sanitize_key( (string) $status );
					if ( in_array( $status, array( 'done', 'dismissed', 'completed', 'closed' ), true ) ) {
						$closed[] = $status;
					}
				}
			}
		}

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $closed ) ) ) );
	}
}

if ( ! function_exists( 'pera_crm_reminder_is_closed_status' ) ) {
	/**
	 * Check whether a reminder status is considered closed/completed.
	 */
	function pera_crm_reminder_is_closed_status( string $status ): bool {
		return in_array( sanitize_key( $status ), pera_crm_reminders_closed_statuses(), true );
	}
}

if ( ! function_exists( 'pera_crm_debug_tasks_log' ) ) {
	/**
	 * Log dashboard task data path diagnostics.
	 *
	 * @param string   $scope Task scope.
	 * @param string   $path  Data source path.
	 * @param string[] $ids   Reminder IDs.
	 * @param int      $count Row count.
	 */
	function pera_crm_debug_tasks_log( string $scope, string $path, array $ids, int $count ): void {
		if ( ! defined( 'PERA_CRM_DEBUG_TASKS' ) || ! PERA_CRM_DEBUG_TASKS || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		error_log(
			sprintf(
				'[PERA CRM tasks] %s path=%s count=%d first_ids=%s',
				$scope,
				$path,
				$count,
				implode( ',', array_slice( $ids, 0, 3 ) )
			)
		);
	}
}

if ( ! function_exists( 'pera_crm_parse_local_mysql_datetime_to_ts' ) ) {
	/**
	 * Parse DATETIME values stored in WP local timezone into timestamps.
	 */
	function pera_crm_parse_local_mysql_datetime_to_ts( string $datetime, ?DateTimeZone $timezone = null ): int {
		$datetime = trim( $datetime );
		if ( '' === $datetime ) {
			return 0;
		}

		$timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
		$formats  = array( 'Y-m-d H:i:s', 'Y-m-d H:i' );
		foreach ( $formats as $format ) {
			$parsed = DateTimeImmutable::createFromFormat( $format, $datetime, $timezone );
			if ( $parsed instanceof DateTimeImmutable ) {
				return $parsed->getTimestamp();
			}
		}

		return 0;
	}
}

if ( ! function_exists( 'pera_crm_get_lead_name' ) ) {
	/**
	 * Resolve lead name for dashboard task rows.
	 */
	function pera_crm_get_lead_name( int $lead_id ): string {
		if ( $lead_id <= 0 ) {
			return '';
		}

		$lead_name = get_the_title( $lead_id );
		if ( '' !== $lead_name ) {
			return $lead_name;
		}

		if ( function_exists( 'peracrm_party_get' ) ) {
			$party = peracrm_party_get( $lead_id );
			if ( is_array( $party ) ) {
				foreach ( array( 'post_title', 'title', 'name' ) as $key ) {
					if ( ! empty( $party[ $key ] ) ) {
						return (string) $party[ $key ];
					}
				}
			}
		}

		return '';
	}
}

if ( ! function_exists( 'pera_crm_normalize_activities' ) ) {
	/**
	 * Normalize recent activity records for UI.
	 *
	 * @param mixed $rows Raw helper response.
	 * @return array<int,array{time:string,type:string,summary:string,client_id:int}>
	 */
	function pera_crm_normalize_activities( $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$items = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$time = '';
			foreach ( array( 'created_at', 'activity_time', 'time', 'date_gmt', 'date' ) as $time_key ) {
				if ( ! empty( $row[ $time_key ] ) ) {
					$time = (string) $row[ $time_key ];
					break;
				}
			}

			$type = '';
			foreach ( array( 'type', 'activity_type', 'event' ) as $type_key ) {
				if ( ! empty( $row[ $type_key ] ) ) {
					$type = (string) $row[ $type_key ];
					break;
				}
			}

			$summary = '';
			foreach ( array( 'summary', 'message', 'title', 'description' ) as $summary_key ) {
				if ( ! empty( $row[ $summary_key ] ) ) {
					$summary = wp_strip_all_tags( (string) $row[ $summary_key ] );
					break;
				}
			}

			$client_id = 0;
			foreach ( array( 'party_id', 'client_id', 'post_id', 'lead_id' ) as $id_key ) {
				if ( isset( $row[ $id_key ] ) && is_numeric( $row[ $id_key ] ) ) {
					$client_id = (int) $row[ $id_key ];
					break;
				}
			}

			$items[] = array(
				'time'      => $time,
				'type'      => $type,
				'summary'   => $summary,
				'client_id' => max( 0, $client_id ),
			);
		}

		return array_slice( $items, 0, 20 );
	}
}

if ( ! function_exists( 'pera_crm_fetch_recent_activity' ) ) {
	/**
	 * Fetch last 20 activity records.
	 *
	 * @return array{available:bool,rows:array<int,array{time:string,type:string,summary:string,client_id:int}>}
	 */
	function pera_crm_fetch_recent_activity(): array {
		$real_user_id          = get_current_user_id();
		$effective_user_id     = function_exists( 'peracrm_get_effective_crm_user_id' ) ? peracrm_get_effective_crm_user_id() : $real_user_id;
		$is_impersonating      = function_exists( 'peracrm_is_impersonating_crm_user' ) && peracrm_is_impersonating_crm_user();
		$effective_is_employee = pera_crm_user_is_employee( $effective_user_id );
		$allowed_ids           = ( $is_impersonating || $effective_is_employee ) ? pera_crm_get_allowed_client_ids_for_user( $effective_user_id ) : array();

		if ( function_exists( 'peracrm_activity_list' ) ) {
			$args = array(
				'limit'   => 20,
				'orderby' => 'created_at',
				'order'   => 'DESC',
			);
			if ( $is_impersonating || $effective_is_employee ) {
				if ( empty( $allowed_ids ) ) {
					return array( 'available' => true, 'rows' => array() );
				}
				$args['party_ids'] = $allowed_ids;
			}
			$items = pera_crm_normalize_activities( peracrm_activity_list( $args ) );
			if ( ! empty( $items ) || $is_impersonating || $effective_is_employee ) {
				return array( 'available' => true, 'rows' => $items );
			}
			$items = pera_crm_normalize_activities( peracrm_activity_list( array( 'limit' => 20 ) ) );
			return array( 'available' => true, 'rows' => $items );
		}

		$callbacks = array( 'peracrm_activity_get_recent', 'peracrm_activity_list_recent', 'peracrm_activity_recent' );
		foreach ( $callbacks as $callback ) {
			if ( ! function_exists( $callback ) ) {
				continue;
			}
			$items = pera_crm_normalize_activities( call_user_func( $callback, 20 ) );
			if ( ( $is_impersonating || $effective_is_employee ) && ! empty( $allowed_ids ) ) {
				$items = array_values( array_filter( $items, static function ( array $item ) use ( $allowed_ids ): bool {
					return in_array( (int) $item['client_id'], $allowed_ids, true );
				} ) );
			}
			return array( 'available' => true, 'rows' => array_slice( $items, 0, 20 ) );
		}

		return array( 'available' => false, 'rows' => array() );
	}
}

if ( ! function_exists( 'pera_crm_get_recent_leads' ) ) {
	/**
	 * Resolve new-lead IDs assigned to a user.
	 *
	 * New lead definition for this pass:
	 * - Assigned to the target user.
	 * - Created in the last N hours.
	 * - Not in closed/completed/lost-style states.
	 *
	 * @return int[]
	 */
	function pera_crm_get_new_lead_ids_for_user( int $user_id = 0, int $hours = 72 ): array {
		$real_user_id          = get_current_user_id();
		$effective_user_id     = function_exists( 'peracrm_get_effective_crm_user_id' ) ? peracrm_get_effective_crm_user_id() : $real_user_id;
		$is_impersonating      = function_exists( 'peracrm_is_impersonating_crm_user' ) && peracrm_is_impersonating_crm_user();
		$effective_is_employee = pera_crm_user_is_employee( $effective_user_id );
		$target_user_id        = $user_id > 0 ? $user_id : $effective_user_id;
		$allowed_ids           = ( $is_impersonating || $effective_is_employee ) ? pera_crm_get_allowed_client_ids_for_user( $effective_user_id ) : array();

		$hours = max( 1, absint( $hours ) );
		if ( $target_user_id <= 0 ) {
			return array();
		}

		$after         = gmdate( 'Y-m-d H:i:s', time() - ( $hours * HOUR_IN_SECONDS ) );
		$prefetch_cap  = 250;
		$args          = array(
			'post_type'      => 'crm_client',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => $prefetch_cap,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'date_query'     => array(
				array(
					'column'    => 'post_date_gmt',
					'after'     => $after,
					'inclusive' => true,
				),
			),
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'assigned_advisor_user_id',
					'value'   => $target_user_id,
					'compare' => '=',
				),
				array(
					'key'     => 'crm_assigned_advisor',
					'value'   => $target_user_id,
					'compare' => '=',
				),
			),
		);

		if ( $is_impersonating || $effective_is_employee ) {
			$args['post__in'] = empty( $allowed_ids ) ? array( 0 ) : $allowed_ids;
		}

		$ids = array_values( array_unique( array_map( 'intval', (array) get_posts( $args ) ) ) );
		if ( $prefetch_cap === count( $ids ) ) {
			$args['posts_per_page'] = -1;
			$ids                    = array_values( array_unique( array_map( 'intval', (array) get_posts( $args ) ) ) );
		}

		if ( empty( $ids ) ) {
			return array();
		}

		$client_ids     = function_exists( 'peracrm_party_batch_get_closed_won_client_ids' )
			? array_map( 'intval', peracrm_party_batch_get_closed_won_client_ids( $ids ) )
			: array();
		$client_lookup  = array_flip( $client_ids );
		$party_map      = function_exists( 'peracrm_party_get_status_by_ids' ) ? peracrm_party_get_status_by_ids( $ids ) : array();
		$closed_stages  = array( 'completed', 'lost', 'deal_closed', 'deal_lost', 'closed_won', 'closed_lost' );
		$closed_states  = array( 'closed', 'completed', 'lost' );
		$filtered_ids   = array();

		foreach ( $ids as $lead_id ) {
			if ( isset( $client_lookup[ $lead_id ] ) ) {
				continue;
			}

			$party      = isset( $party_map[ $lead_id ] ) && is_array( $party_map[ $lead_id ] ) ? $party_map[ $lead_id ] : array();
			$engagement = sanitize_key( (string) ( $party['engagement_state'] ?? '' ) );
			$stage      = sanitize_key( (string) ( $party['lead_pipeline_stage'] ?? '' ) );
			$status     = sanitize_key( (string) get_post_meta( $lead_id, 'status', true ) );

			if ( in_array( $engagement, $closed_states, true ) || in_array( $stage, $closed_stages, true ) || in_array( $status, $closed_states, true ) ) {
				continue;
			}

			$filtered_ids[] = $lead_id;
		}

		return array_values( array_unique( array_map( 'intval', $filtered_ids ) ) );
	}

	/**
	 * Count new leads assigned to a user in the last N hours.
	 */
	function pera_crm_count_new_leads_for_user( int $user_id = 0, int $hours = 72 ): int {
		return count( pera_crm_get_new_lead_ids_for_user( $user_id, $hours ) );
	}

	/**
	 * Hydrate CRM lead post IDs into row data for list/panel rendering.
	 *
	 * @param int[] $lead_ids Lead post IDs.
	 * @param int   $limit   Maximum rows to return.
	 * @return array<int,array{id:int,name:string,phone:string,source:string,enquiry_at:string,url:string}>
	 */
	function pera_crm_hydrate_lead_rows_for_panel( array $lead_ids, int $limit = 10 ): array {
		$lead_ids = array_values( array_filter( array_map( 'intval', $lead_ids ) ) );
		if ( empty( $lead_ids ) ) {
			return array();
		}

		$rows  = array();
		$limit = max( 1, $limit );
		foreach ( $lead_ids as $lead_id ) {
			$source_key = sanitize_key( (string) get_post_meta( $lead_id, 'crm_source', true ) );
			$source     = '' !== $source_key ? ucwords( str_replace( '_', ' ', $source_key ) ) : __( 'Website', 'peracrm' );
			$created_ts = (int) get_post_time( 'U', true, $lead_id );
			$rows[]     = array(
				'id'         => $lead_id,
				'name'       => get_the_title( $lead_id ),
				'phone'      => (string) get_post_meta( $lead_id, '_peracrm_phone', true ),
				'source'     => $source,
				'enquiry_at' => $created_ts > 0 ? pera_crm_format_datetime_dmy_hm( $created_ts ) : '',
				'url'        => function_exists( 'pera_crm_get_client_view_url' ) ? pera_crm_get_client_view_url( $lead_id ) : home_url( '/crm/client/' . $lead_id . '/' ),
			);

			if ( count( $rows ) >= $limit ) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * Fetch latest leads for dashboard.
	 *
	 * @return array<int,array{id:int,name:string,url:string}>
	 */
	function pera_crm_get_recent_leads( int $limit = 20 ): array {
		$real_user_id          = get_current_user_id();
		$effective_user_id     = function_exists( 'peracrm_get_effective_crm_user_id' ) ? peracrm_get_effective_crm_user_id() : $real_user_id;
		$is_impersonating      = function_exists( 'peracrm_is_impersonating_crm_user' ) && peracrm_is_impersonating_crm_user();
		$effective_is_employee = pera_crm_user_is_employee( $effective_user_id );
		$allowed_ids           = ( $is_impersonating || $effective_is_employee ) ? pera_crm_get_allowed_client_ids_for_user( $effective_user_id ) : array();
		$args            = array(
			'post_type'      => 'crm_client',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => max( 1, $limit * 3 ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		if ( $is_impersonating || $effective_is_employee ) {
			$args['post__in'] = empty( $allowed_ids ) ? array( 0 ) : $allowed_ids;
		}

		$ids = array_values( array_map( 'intval', (array) get_posts( $args ) ) );
		$client_ids = function_exists( 'peracrm_party_batch_get_closed_won_client_ids' )
			? array_map( 'intval', peracrm_party_batch_get_closed_won_client_ids( $ids ) )
			: array();
		$client_lookup = array_flip( $client_ids );

		$rows = array();
		foreach ( $ids as $lead_id ) {
			if ( isset( $client_lookup[ $lead_id ] ) ) {
				continue;
			}
			$rows[] = $lead_id;

			if ( count( $rows ) >= $limit ) {
				break;
			}
		}

		return pera_crm_hydrate_lead_rows_for_panel( $rows, $limit );
	}

}

if ( ! function_exists( 'pera_crm_get_task_rows' ) ) {
	/**
	 * Resolve latest note snippets for CRM client IDs.
	 *
	 * @param int[] $client_ids Client IDs.
	 * @return array<int,string>
	 */
	function pera_crm_get_latest_note_snippets( array $client_ids ): array {
		$snippets = array();
		$ids      = array_values( array_unique( array_filter( array_map( 'absint', $client_ids ) ) ) );

		if ( empty( $ids ) || ! function_exists( 'peracrm_notes_list' ) ) {
			return $snippets;
		}

		foreach ( $ids as $client_id ) {
			$latest_note = peracrm_notes_list( $client_id, 1, 0 );
			if ( empty( $latest_note ) || ! is_array( $latest_note ) || ! is_array( $latest_note[0] ?? null ) ) {
				$snippets[ $client_id ] = '';
				continue;
			}

			$snippets[ $client_id ] = wp_strip_all_tags( (string) ( $latest_note[0]['note_body'] ?? '' ) );
		}

		return $snippets;
	}

	/**
	 * Resolve reminder/task rows from helpers or SQL fallback.
	 *
	 * @return array<int,array{reminder_id:int,lead_id:int,lead_name:string,due_date:string,reminder_note:string,last_note:string,status:string}>
	 */
	function pera_crm_get_task_rows( bool $overdue = false ): array {
		$real_user_id          = get_current_user_id();
		$effective_user_id     = function_exists( 'peracrm_get_effective_crm_user_id' ) ? peracrm_get_effective_crm_user_id() : $real_user_id;
		$is_impersonating      = function_exists( 'peracrm_is_impersonating_crm_user' ) && peracrm_is_impersonating_crm_user();
		$effective_is_employee = pera_crm_user_is_employee( $effective_user_id );
		$should_scope          = $is_impersonating || $effective_is_employee;
		$open_status           = pera_crm_reminders_open_status();
		$timezone        = wp_timezone();
		$current_dt      = current_datetime();
		$today_start_ts  = $current_dt->setTime( 0, 0, 0 )->getTimestamp();
		$today_end_ts    = $current_dt->setTime( 23, 59, 59 )->getTimestamp();
		$now_ts          = $current_dt->getTimestamp();
		$rows            = array();
		$debug_ids       = array();

		if ( $should_scope && function_exists( 'peracrm_reminders_list_for_advisor' ) ) {
			$range = $overdue ? 'overdue' : 'all';
			$raw   = peracrm_reminders_list_for_advisor( $effective_user_id, 200, 0, $open_status, $range, 'asc' );
			if ( is_array( $raw ) ) {
				$note_client_ids = array();
				foreach ( $raw as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$due_at = (string) ( $row['due_at'] ?? '' );
					$due_ts = pera_crm_parse_local_mysql_datetime_to_ts( $due_at, $timezone );
					if ( $due_ts <= 0 ) {
						continue;
					}
					if ( ! $overdue && ( $due_ts < $today_start_ts || $due_ts > $today_end_ts ) ) {
						continue;
					}
					if ( $overdue && $due_ts >= $now_ts ) {
						continue;
					}
					$lead_id     = (int) ( $row['client_id'] ?? 0 );
					$debug_ids[] = (string) ( $row['id'] ?? 0 );
					$rows[]      = array(
						'reminder_id'   => (int) ( $row['id'] ?? 0 ),
						'lead_id'       => $lead_id,
						'lead_name'     => pera_crm_get_lead_name( $lead_id ),
						'due_date'      => pera_crm_format_datetime_dmy_hm( $due_ts, $timezone ),
						'reminder_note' => wp_strip_all_tags( (string) ( $row['note'] ?? '' ) ),
						'last_note'     => '',
						'status'        => sanitize_key( (string) ( $row['status'] ?? $open_status ) ),
					);
					$note_client_ids[] = $lead_id;
				}

				$latest_notes = pera_crm_get_latest_note_snippets( $note_client_ids );
				foreach ( $rows as $index => $task_row ) {
					$lead_id                   = (int) ( $task_row['lead_id'] ?? 0 );
					$rows[ $index ]['last_note'] = (string) ( $latest_notes[ $lead_id ] ?? '' );
				}
				pera_crm_debug_tasks_log( $overdue ? 'overdue' : 'today', 'mu_advisor', $debug_ids, count( $rows ) );
				return $rows;
			}
		}

		if ( $is_impersonating || $effective_is_employee ) {
			pera_crm_debug_tasks_log( $overdue ? 'overdue' : 'today', 'no_employee_source', $debug_ids, 0 );
			return array();
		}

		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return array();
		}
		$table_candidates = array( $wpdb->prefix . 'crm_reminders' );
		$table = '';
		foreach ( $table_candidates as $candidate ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $candidate ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( $exists === $candidate ) {
				$table = $candidate;
				break;
			}
		}
		if ( '' === $table ) {
			return array();
		}

		$now_mysql     = wp_date( 'Y-m-d H:i:s', $now_ts, $timezone );
		$today_start   = wp_date( 'Y-m-d H:i:s', $today_start_ts, $timezone );
		$today_end     = wp_date( 'Y-m-d H:i:s', $today_end_ts, $timezone );

		if ( $overdue ) {
			$sql = $wpdb->prepare( "SELECT id, client_id, due_at, note FROM {$table} WHERE status = %s AND due_at < %s ORDER BY due_at ASC LIMIT 200", $open_status, $now_mysql ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = $wpdb->prepare( "SELECT id, client_id, due_at, note FROM {$table} WHERE status = %s AND due_at BETWEEN %s AND %s ORDER BY due_at ASC LIMIT 200", $open_status, $today_start, $today_end ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$raw   = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows  = array();
		$note_client_ids = array();
		foreach ( (array) $raw as $row ) {
			$lead_id     = (int) ( $row['client_id'] ?? 0 );
			$debug_ids[] = (string) ( $row['id'] ?? 0 );
			$rows[] = array(
				'reminder_id'   => (int) ( $row['id'] ?? 0 ),
				'lead_id'       => $lead_id,
				'lead_name'     => pera_crm_get_lead_name( $lead_id ),
				'due_date'      => pera_crm_format_datetime_dmy_hm( pera_crm_parse_local_mysql_datetime_to_ts( (string) ( $row['due_at'] ?? '' ), $timezone ), $timezone ),
				'reminder_note' => wp_strip_all_tags( (string) ( $row['note'] ?? '' ) ),
				'last_note'     => '',
				'status'        => $open_status,
			);
			$note_client_ids[] = $lead_id;
		}

		$latest_notes = pera_crm_get_latest_note_snippets( $note_client_ids );
		foreach ( $rows as $index => $task_row ) {
			$lead_id                   = (int) ( $task_row['lead_id'] ?? 0 );
			$rows[ $index ]['last_note'] = (string) ( $latest_notes[ $lead_id ] ?? '' );
		}

		pera_crm_debug_tasks_log( $overdue ? 'overdue' : 'today', 'sql_all', $debug_ids, count( $rows ) );
		return $rows;
	}
}

if ( ! function_exists( 'pera_crm_get_todays_tasks' ) ) {
	function pera_crm_get_todays_tasks(): array {
		return pera_crm_get_task_rows( false );
	}
}

if ( ! function_exists( 'pera_crm_get_overdue_tasks' ) ) {
	function pera_crm_get_overdue_tasks(): array {
		return pera_crm_get_task_rows( true );
	}
}


if ( ! function_exists( 'pera_crm_get_request_filter' ) ) {
	/**
	 * Return whitelisted CRM deep-link filter from request.
	 *
	 * @param string[] $allowed Allowed filter keys.
	 */
	function pera_crm_get_request_filter( array $allowed ): string {
		if ( ! isset( $_GET['filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		$filter = sanitize_key( wp_unslash( (string) $_GET['filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $filter, $allowed, true ) ? $filter : '';
	}
}

if ( ! function_exists( 'pera_crm_get_tasks_view_data' ) ) {
	/**
	 * Build /crm/tasks view model from open reminders.
	 *
	 * Uses a fixed 200-row cap (same cap currently used by dashboard task helpers)
	 * to keep rendering predictable until a shared front-end CRM pagination pattern exists.
	 *
	 * @return array<string,mixed>
	 */
	function pera_crm_get_tasks_view_data(): array {
		$active_filter         = pera_crm_get_request_filter( array( 'overdue', 'today', 'open' ) );
		$real_user_id          = get_current_user_id();
		$effective_user_id     = function_exists( 'peracrm_get_effective_crm_user_id' ) ? peracrm_get_effective_crm_user_id() : $real_user_id;
		$is_impersonating      = function_exists( 'peracrm_is_impersonating_crm_user' ) && peracrm_is_impersonating_crm_user();
		$effective_is_employee = pera_crm_user_is_employee( $effective_user_id );
		$should_scope          = $is_impersonating || $effective_is_employee;
		$open_status           = pera_crm_reminders_open_status();
		$timezone        = wp_timezone();
		$today           = current_datetime();
		$today_start_ts  = $today->setTime( 0, 0, 0 )->getTimestamp();
		$today_end_ts    = $today->setTime( 23, 59, 59 )->getTimestamp();
		$raw_rows        = array();

		if ( function_exists( 'peracrm_reminders_list_for_advisor' ) && $should_scope ) {
			$raw = peracrm_reminders_list_for_advisor( $effective_user_id, 200, 0, $open_status, 'all', 'asc' );
			if ( is_array( $raw ) ) {
				$raw_rows = $raw;
			}
		} else {
			global $wpdb;
			if ( isset( $wpdb ) ) {
				$table_candidates = array( $wpdb->prefix . 'crm_reminders' );
				$table            = '';
				foreach ( $table_candidates as $candidate ) {
					$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $candidate ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					if ( $exists === $candidate ) {
						$table = $candidate;
						break;
					}
				}

				if ( '' !== $table ) {
					$sql      = $wpdb->prepare( "SELECT id, client_id, advisor_user_id, due_at, status, note FROM {$table} WHERE status = %s ORDER BY due_at ASC, id ASC LIMIT 200", $open_status ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$raw_rows = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				}
			}
		}

		$advisor_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $row ): int {
							return (int) ( $row['advisor_user_id'] ?? 0 );
						},
						$raw_rows
					)
				)
			)
		);

		$advisor_map = array();
		if ( ! empty( $advisor_ids ) ) {
			$users = get_users(
				array(
					'include' => $advisor_ids,
					'fields'  => array( 'ID', 'display_name' ),
				)
			);
			foreach ( $users as $user ) {
				$advisor_map[ (int) $user->ID ] = (string) $user->display_name;
			}
		}

		$all_rows     = array();
		$today_rows   = array();
		$overdue_rows = array();
		$future_rows  = array();

		foreach ( $raw_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$due_at = (string) ( $row['due_at'] ?? '' );
			$due_ts = pera_crm_parse_local_mysql_datetime_to_ts( $due_at, $timezone );
			if ( $due_ts <= 0 ) {
				continue;
			}

			$client_id   = (int) ( $row['client_id'] ?? 0 );
			$advisor_id  = (int) ( $row['advisor_user_id'] ?? 0 );
			$is_overdue  = $due_ts < $today_start_ts;
			$client_name = $client_id > 0 ? get_the_title( $client_id ) : '';
			if ( '' === $client_name ) {
				$client_name = $client_id > 0 ? sprintf( __( 'Client #%d', 'peracrm' ), $client_id ) : __( 'Unknown client', 'peracrm' );
			}

			$view_row = array(
				'reminder_id'   => (int) ( $row['id'] ?? 0 ),
				'client_id'     => $client_id,
				'client_name'   => $client_name,
				'client_url'    => function_exists( 'pera_crm_get_client_view_url' ) ? pera_crm_get_client_view_url( $client_id ) : home_url( '/crm/client/' . $client_id . '/' ),
				'due_at'        => $due_at,
				'due_ts'        => $due_ts,
				'due_display'   => pera_crm_format_datetime_dmy_hm( $due_ts, $timezone ),
				'note'          => wp_strip_all_tags( (string) ( $row['note'] ?? '' ) ),
				'assigned_to'   => $advisor_id > 0 ? ( $advisor_map[ $advisor_id ] ?? sprintf( __( 'User #%d', 'peracrm' ), $advisor_id ) ) : '',
				'is_overdue'    => $is_overdue,
				'status_label'  => $is_overdue ? __( 'Overdue', 'peracrm' ) : __( 'Open', 'peracrm' ),
			);

			$all_rows[] = $view_row;
			if ( $due_ts >= $today_start_ts && $due_ts <= $today_end_ts ) {
				$today_rows[] = $view_row;
			} elseif ( $due_ts < $today_start_ts ) {
				$overdue_rows[] = $view_row;
			} elseif ( $due_ts > $today_end_ts ) {
				$future_rows[] = $view_row;
			}
		}

		$active_rows = $all_rows;
		if ( 'overdue' === $active_filter ) {
			$active_rows = $overdue_rows;
		} elseif ( 'today' === $active_filter ) {
			$active_rows = $today_rows;
		} elseif ( 'open' === $active_filter ) {
			$active_rows = $all_rows;
		}

		return array(
			'is_employee'   => $effective_is_employee,
			'all'           => $all_rows,
			'today'         => $today_rows,
			'outstanding'   => $overdue_rows,
			'upcoming'      => $future_rows,
			'active_filter' => $active_filter,
			'active_rows'   => $active_rows,
		);
	}
}

if ( ! function_exists( 'pera_crm_get_dashboard_data' ) ) {

	/**
	 * Build CRM dashboard view model.
	 *
	 * @return array<string,mixed>
	 */
	function pera_crm_get_dashboard_data(): array {
		$stages       = pera_crm_get_pipeline_stages();
		$stage_keys   = array_keys( $stages );
		$counts_data  = pera_crm_fetch_stage_counts( $stage_keys );
		$stage_counts = $counts_data['counts'];
		$notices      = array();

		if ( ! $counts_data['available'] ) {
			$notices[] = __( 'CRM data unavailable: pipeline and KPI counts could not be loaded.', 'peracrm' );
		}

		$open_leads = 0;
		foreach ( $stage_counts as $stage_key => $count ) {
			if ( in_array( $stage_key, array( 'deal_closed', 'deal_lost' ), true ) ) {
				continue;
			}
			$open_leads += (int) $count;
		}

		$overdue_reminders = pera_crm_get_overdue_reminders_count_for_current_user();

		$activity_data = pera_crm_fetch_recent_activity();
		if ( ! $activity_data['available'] ) {
			$notices[] = __( 'CRM data unavailable: recent activity feed could not be loaded.', 'peracrm' );
		}

		$todays_tasks  = pera_crm_get_todays_tasks();
		$overdue_tasks = pera_crm_get_overdue_tasks();
		$new_leads     = pera_crm_get_recent_leads( 20 );

		$kpis = array(
			'total_open_leads'   => $open_leads,
			'new_enquiries'      => (int) ( $stage_counts['new_enquiry'] ?? 0 ),
			'qualified'          => (int) ( $stage_counts['qualified'] ?? 0 ),
			'viewing_arranged'   => (int) ( $stage_counts['viewing_arranged'] ?? 0 ),
			'offer_made'         => (int) ( $stage_counts['offer_made'] ?? 0 ),
			'overdue_reminders'  => (int) $overdue_reminders,
		);

		$pipeline = array();
		foreach ( $stages as $stage_key => $label ) {
			$pipeline[] = array(
				'key'   => (string) $stage_key,
				'label' => (string) $label,
				'count' => (int) ( $stage_counts[ $stage_key ] ?? 0 ),
			);
		}

		$activity = array_map(
			static function ( array $item ): array {
				$item['edit_url'] = $item['client_id'] > 0
					? admin_url( 'post.php?post=' . (int) $item['client_id'] . '&action=edit' )
					: '';
				return $item;
			},
			$activity_data['rows']
		);

			$pipeline_health = array();
			if ( function_exists( 'pera_crm_get_pipeline_health_metrics' ) ) {
				$pipeline_health = pera_crm_get_pipeline_health_metrics(
					(int) $overdue_reminders,
					count( $todays_tasks ),
					(int) pera_crm_count_new_leads_for_user( 0, 72 )
				);
			}

		return array(
			'kpis'          => $kpis,
			'pipeline'      => $pipeline,
			'pipeline_health' => $pipeline_health,
			'activity'      => $activity,
			'todays_tasks'  => $todays_tasks,
			'overdue_tasks' => $overdue_tasks,
			'new_leads'     => $new_leads,
			'notices'       => array_values( array_unique( $notices ) ),
		);
	}
}

if ( ! function_exists( 'pera_crm_get_pipeline_health_metrics' ) ) {
	/**
	 * Build actionable pipeline health metrics for overview page.
	 *
	 * @return array<int,array{key:string,label:string,value:int,context:string}>
	 */
	function pera_crm_get_pipeline_health_metrics( int $overdue_reminders, int $due_today, int $new_leads_72h ): array {
		$real_user_id          = get_current_user_id();
		$effective_user_id     = function_exists( 'peracrm_get_effective_crm_user_id' ) ? peracrm_get_effective_crm_user_id() : $real_user_id;
		$is_impersonating      = function_exists( 'peracrm_is_impersonating_crm_user' ) && peracrm_is_impersonating_crm_user();
		$effective_is_employee = pera_crm_user_is_employee( $effective_user_id );
		$can_manage_all        = current_user_can( 'manage_options' ) || current_user_can( 'peracrm_manage_all_clients' );

		$query_args = array(
			'post_type'      => 'crm_client',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( ! $can_manage_all || $is_impersonating || $effective_is_employee ) {
			$allowed_ids = pera_crm_get_allowed_client_ids_for_user( $effective_user_id );
			$query_args['post__in'] = empty( $allowed_ids ) ? array( 0 ) : $allowed_ids;
		}

		$lead_ids  = array_values( array_filter( array_map( 'intval', (array) get_posts( $query_args ) ) ) );
		$party_map = ! empty( $lead_ids ) && function_exists( 'peracrm_party_get_status_by_ids' )
			? (array) peracrm_party_get_status_by_ids( $lead_ids )
			: array();

		$open_ids = array();
		foreach ( $lead_ids as $lead_id ) {
			$party = is_array( $party_map[ $lead_id ] ?? null ) ? $party_map[ $lead_id ] : array();
			$stage = sanitize_key( (string) ( $party['lead_pipeline_stage'] ?? '' ) );
			if ( in_array( $stage, array( 'deal_closed', 'deal_lost' ), true ) ) {
				continue;
			}
			$open_ids[] = $lead_id;
		}
		$open_in_scope = count( $open_ids );

		$stale_cutoff_ts = current_time( 'timestamp' ) - ( 7 * DAY_IN_SECONDS );
		$unassigned      = 0;
		$stale           = 0;
		foreach ( $open_ids as $lead_id ) {
			$assigned_id = function_exists( 'peracrm_client_get_assigned_advisor_id' ) ? (int) peracrm_client_get_assigned_advisor_id( $lead_id ) : 0;
			if ( $assigned_id <= 0 ) {
				$unassigned++;
			}

			$health   = function_exists( 'peracrm_client_health_get' ) ? (array) peracrm_client_health_get( $lead_id ) : array();
			$last_ts  = (int) ( $health['last_activity_ts'] ?? 0 );
			if ( $last_ts <= 0 || $last_ts < $stale_cutoff_ts ) {
				$stale++;
			}
		}

		$metrics = array(
			array(
				'key'     => 'overdue_reminders',
				'label'   => __( 'Overdue reminders', 'peracrm' ),
				'value'   => max( 0, $overdue_reminders ),
				'context' => __( 'Needs immediate follow-up', 'peracrm' ),
			),
			array(
				'key'     => 'due_today',
				'label'   => __( 'Due today', 'peracrm' ),
				'value'   => max( 0, $due_today ),
				'context' => __( 'Open reminders due before day-end', 'peracrm' ),
			),
			array(
				'key'     => 'new_leads_72h',
				'label'   => __( 'New leads (72h)', 'peracrm' ),
				'value'   => max( 0, $new_leads_72h ),
				'context' => __( 'Recently created leads in your scope', 'peracrm' ),
			),
			array(
				'key'     => 'unassigned_open',
				'label'   => __( 'Unassigned open leads', 'peracrm' ),
				'value'   => max( 0, $unassigned ),
				'context' => __( 'Open leads without an advisor owner', 'peracrm' ),
			),
			array(
				'key'     => 'stale_open',
				'label'   => __( 'No activity (7+ days)', 'peracrm' ),
				'value'   => max( 0, $stale ),
				'context' => __( 'Open leads with stale or missing recent activity', 'peracrm' ),
			),
				array(
					'key'     => 'open_pipeline',
					'label'   => __( 'Open leads in scope', 'peracrm' ),
					'value'   => max( 0, $open_in_scope ),
					'context' => __( 'Leads not in closed or lost stage for your current CRM scope', 'peracrm' ),
				),
			);

		return $metrics;
	}
}


if ( ! function_exists( 'pera_crm_user_is_employee' ) ) {
	/**
	 * Is the user an employee (non-manager scope).
	 */
	function pera_crm_user_is_employee( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! ( $user instanceof WP_User ) ) {
			return false;
		}

		$roles = (array) $user->roles;
		return in_array( 'employee', $roles, true ) && ! in_array( 'manager', $roles, true ) && ! in_array( 'administrator', $roles, true );
	}
}

if ( ! function_exists( 'pera_crm_get_allowed_client_ids_for_user' ) ) {
	/**
	 * Resolve CRM client IDs visible to the current user.
	 *
	 * @return int[]
	 */
	function pera_crm_get_allowed_client_ids_for_user( int $user_id ): array {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		if ( ! pera_crm_user_is_employee( $user_id ) ) {
			return array();
		}

		if ( function_exists( 'peracrm_with_target_blog' ) ) {
			return peracrm_with_target_blog(
				static function () use ( $user_id ): array {
					global $wpdb;

					$meta_keys = function_exists( 'peracrm_pipeline_assigned_meta_keys' )
						? (array) peracrm_pipeline_assigned_meta_keys()
						: array( 'assigned_advisor_user_id', 'crm_assigned_advisor' );
					$meta_keys = array_values( array_filter( array_map( 'sanitize_key', $meta_keys ) ) );
					if ( empty( $meta_keys ) ) {
						return array();
					}

					$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
					$params       = array_merge( $meta_keys, array( (string) $user_id ) );
					$query        = $wpdb->prepare(
						"SELECT DISTINCT pm.post_id
						 FROM {$wpdb->postmeta} pm
						 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
						 WHERE p.post_type = 'crm_client'
						   AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')
						   AND pm.meta_key IN ({$placeholders})
						   AND pm.meta_value = %s",
						$params
					);

					$ids = $wpdb->get_col( $query );
					return array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
				}
			);
		}

		$ids = get_posts(
			array(
				'post_type'      => 'crm_client',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'assigned_advisor_user_id',
						'value'   => $user_id,
						'compare' => '=',
					),
					array(
						'key'     => 'crm_assigned_advisor',
						'value'   => $user_id,
						'compare' => '=',
					),
				),
			)
		);

		return array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
	}
}

add_filter(
	'peracrm_allowed_client_ids_for_user',
	static function ( $ids, $user_id ) {
		if ( ! function_exists( 'pera_crm_get_allowed_client_ids_for_user' ) ) {
			return $ids;
		}

		return pera_crm_get_allowed_client_ids_for_user( (int) $user_id );
	},
	10,
	2
);

if ( ! function_exists( 'pera_crm_get_leads_view_data' ) ) {
	/**
	 * Build paginated leads data for the CRM leads view.
	 *
	 * @return array<string,mixed>
	 */
	function pera_crm_get_leads_view_data( int $page = 1, int $per_page = 20, string $derived_type = 'lead', string $list_view = 'leads' ): array {
		$current_user_id = get_current_user_id();
		$page            = max( 1, absint( $page ) );
		$per_page        = max( 1, absint( $per_page ) );
		$allowed_ids     = pera_crm_get_allowed_client_ids_for_user( $current_user_id );
		$derived_type    = in_array( $derived_type, array( 'lead', 'client' ), true ) ? $derived_type : 'lead';
		$list_view       = in_array( $list_view, array( 'leads', 'clients', 'inactive' ), true ) ? $list_view : 'leads';
		$q               = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
		$stage           = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( (string) $_GET['stage'] ) ) : '';
		$advisor         = isset( $_GET['advisor'] ) ? absint( wp_unslash( (string) $_GET['advisor'] ) ) : 0;
		$active_filter   = pera_crm_get_request_filter( array( 'unassigned', 'stale', 'new72', 'open_scope' ) );

		$query_args = array(
			'post_type'      => 'crm_client',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( pera_crm_user_is_employee( $current_user_id ) ) {
			$query_args['post__in'] = empty( $allowed_ids ) ? array( 0 ) : $allowed_ids;
		}

		$post_ids = array_values( array_map( 'intval', get_posts( $query_args ) ) );
		if ( empty( $post_ids ) ) {
			return array(
				'items'         => array(),
				'query'         => null,
				'total'         => 0,
				'total_pages'   => 1,
				'current_page'  => $page,
				'per_page'      => $per_page,
				'derived_type'  => $derived_type,
				'active_filter' => $active_filter,
				'is_employee'   => pera_crm_user_is_employee( $current_user_id ),
				'scoped_ids'    => $allowed_ids,
			);
		}

		$client_ids = function_exists( 'peracrm_party_batch_get_closed_won_client_ids' )
			? array_map( 'intval', peracrm_party_batch_get_closed_won_client_ids( $post_ids ) )
			: array();
		$client_lookup = array_flip( $client_ids );
		$base_ids = array_values(
			array_filter(
				$post_ids,
				static function ( int $lead_id ) use ( $derived_type, $client_lookup, $list_view ): bool {
					if ( 'inactive' === $list_view ) {
						return true;
					}

					$is_client = isset( $client_lookup[ $lead_id ] );
					return 'client' === $derived_type ? $is_client : ! $is_client;
				}
			)
		);

		$party_map_full = function_exists( 'peracrm_party_get_status_by_ids' ) ? peracrm_party_get_status_by_ids( $base_ids ) : array();
		$filtered_ids   = array_values(
			array_filter(
				$base_ids,
				static function ( int $lead_id ) use ( $list_view, $party_map_full ): bool {
					$party      = isset( $party_map_full[ $lead_id ] ) && is_array( $party_map_full[ $lead_id ] ) ? $party_map_full[ $lead_id ] : array();
					$engagement = sanitize_key( (string) ( $party['engagement_state'] ?? '' ) );
					$inactive   = in_array( $engagement, array( 'closed', 'dormant' ), true );

					return 'inactive' === $list_view ? $inactive : ! $inactive;
				}
			)
		);

		if ( '' !== $stage ) {
			$filtered_ids = array_values(
				array_filter(
					$filtered_ids,
					static function ( int $lead_id ) use ( $stage, $party_map_full ): bool {
						$party = isset( $party_map_full[ $lead_id ] ) && is_array( $party_map_full[ $lead_id ] ) ? $party_map_full[ $lead_id ] : array();
						return sanitize_key( (string) ( $party['lead_pipeline_stage'] ?? '' ) ) === $stage;
					}
				)
			);
		}

		if ( $advisor > 0 ) {
			$filtered_ids = array_values(
				array_filter(
					$filtered_ids,
					static function ( int $lead_id ) use ( $advisor ): bool {
						$assigned_id = function_exists( 'peracrm_client_get_assigned_advisor_id' ) ? (int) peracrm_client_get_assigned_advisor_id( $lead_id ) : 0;
						return $assigned_id === $advisor;
					}
				)
			);
		}

		if ( '' !== $active_filter && 'leads' === $list_view && 'lead' === $derived_type ) {
			$stale_cutoff_ts = current_time( 'timestamp' ) - ( 7 * DAY_IN_SECONDS );
			$new_cutoff_ts   = current_time( 'timestamp' ) - ( 72 * HOUR_IN_SECONDS );
			$filtered_ids    = array_values(
				array_filter(
					$filtered_ids,
					static function ( int $lead_id ) use ( $active_filter, $party_map_full, $stale_cutoff_ts, $new_cutoff_ts ): bool {
						if ( 'unassigned' === $active_filter ) {
							$assigned_id = function_exists( 'peracrm_client_get_assigned_advisor_id' ) ? (int) peracrm_client_get_assigned_advisor_id( $lead_id ) : 0;
							return $assigned_id <= 0;
						}

						if ( 'stale' === $active_filter ) {
							$health  = function_exists( 'peracrm_client_health_get' ) ? (array) peracrm_client_health_get( $lead_id ) : array();
							$last_ts = (int) ( $health['last_activity_ts'] ?? 0 );
							return $last_ts <= 0 || $last_ts < $stale_cutoff_ts;
						}

						if ( 'new72' === $active_filter ) {
							$created_ts = (int) get_post_time( 'U', true, $lead_id );
							return $created_ts > 0 && $created_ts >= $new_cutoff_ts;
						}

						if ( 'open_scope' === $active_filter ) {
							$party = isset( $party_map_full[ $lead_id ] ) && is_array( $party_map_full[ $lead_id ] ) ? $party_map_full[ $lead_id ] : array();
							$stage = sanitize_key( (string) ( $party['lead_pipeline_stage'] ?? '' ) );
							return ! in_array( $stage, array( 'deal_closed', 'deal_lost' ), true );
						}

						return true;
					}
				)
			);
		}

		if ( '' !== $q ) {
			$term = function_exists( 'mb_strtolower' ) ? mb_strtolower( $q ) : strtolower( $q );
			$filtered_ids = array_values(
				array_filter(
					$filtered_ids,
					static function ( int $lead_id ) use ( $term ): bool {
						$title = function_exists( 'mb_strtolower' ) ? mb_strtolower( get_the_title( $lead_id ) ) : strtolower( get_the_title( $lead_id ) );
						if ( false !== strpos( $title, $term ) ) {
							return true;
						}

						$email_fields = array( '_peracrm_email', 'crm_primary_email', 'primary_email' );
						foreach ( $email_fields as $field_key ) {
							$email_value = (string) get_post_meta( $lead_id, $field_key, true );
							$email_value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $email_value ) : strtolower( $email_value );
							if ( '' !== $email_value && false !== strpos( $email_value, $term ) ) {
								return true;
							}
						}

						return false;
					}
				)
			);
		}

		$total = count( $filtered_ids );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$offset_ids = array_slice( $filtered_ids, ( $page - 1 ) * $per_page, $per_page );

		$party_map = array();
		foreach ( $offset_ids as $offset_id ) {
			if ( isset( $party_map_full[ $offset_id ] ) ) {
				$party_map[ $offset_id ] = $party_map_full[ $offset_id ];
			}
		}
		if ( function_exists( 'peracrm_client_health_prime_cache' ) ) {
			peracrm_client_health_prime_cache( $offset_ids );
		}

		$items = array();
		$advisor_name_cache = array();
		foreach ( $offset_ids as $lead_id ) {
			$party     = isset( $party_map[ $lead_id ] ) && is_array( $party_map[ $lead_id ] ) ? $party_map[ $lead_id ] : array();
			$health    = function_exists( 'peracrm_client_health_get' ) ? peracrm_client_health_get( $lead_id ) : array();
			$last_ts   = isset( $health['last_activity_ts'] ) ? (int) $health['last_activity_ts'] : 0;
			$last_date = $last_ts > 0 ? pera_crm_format_datetime_dmy_hm( $last_ts ) : '';
			$source    = sanitize_key( (string) get_post_meta( $lead_id, 'crm_source', true ) );
			$source    = '' !== $source ? str_replace( '_', ' ', $source ) : '';

			$assigned_id = function_exists( 'peracrm_client_get_assigned_advisor_id' ) ? (int) peracrm_client_get_assigned_advisor_id( $lead_id ) : 0;
			$assigned    = '';
			if ( $assigned_id > 0 ) {
				if ( ! isset( $advisor_name_cache[ $assigned_id ] ) ) {
					$user                               = get_userdata( $assigned_id );
					$advisor_name_cache[ $assigned_id ] = $user instanceof WP_User ? (string) $user->display_name : '';
				}
				$assigned = (string) $advisor_name_cache[ $assigned_id ];
			}

			$created_ts = (int) get_post_time( 'U', true, $lead_id );
			$updated_ts = (int) get_post_modified_time( 'U', true, $lead_id );

			$items[] = array(
				'id'               => $lead_id,
				'title'            => get_the_title( $lead_id ),
				'stage'            => (string) ( $party['lead_pipeline_stage'] ?? 'new_enquiry' ),
				'engagement_state' => (string) ( $party['engagement_state'] ?? '' ),
				'disposition'      => (string) ( $party['disposition'] ?? '' ),
				'last_activity'    => $last_date,
				'last_activity_ts' => $last_ts,
				'source'           => ucwords( $source ),
				'assigned_to'      => $assigned,
				'created'          => $created_ts > 0 ? pera_crm_format_datetime_dmy_hm( $created_ts ) : '',
				'created_ts'       => $created_ts,
				'updated'          => $updated_ts > 0 ? pera_crm_format_datetime_dmy_hm( $updated_ts ) : '',
				'updated_ts'       => $updated_ts,
				'derived_type'     => isset( $client_lookup[ $lead_id ] ) ? 'client' : 'lead',
				'crm_url'          => function_exists( 'pera_crm_get_client_view_url' ) ? pera_crm_get_client_view_url( $lead_id ) : home_url( '/crm/client/' . $lead_id . '/' ),
				'edit_url'         => admin_url( 'post.php?post=' . $lead_id . '&action=edit' ),
			);
		}

		if ( function_exists( 'wp_list_sort' ) ) {
			$items = wp_list_sort( $items, 'last_activity_ts', 'DESC', true );
		}

		return array(
			'items'         => $items,
			'query'         => null,
			'total'         => $total,
			'total_pages'   => $total_pages,
			'current_page'  => min( $page, $total_pages ),
			'per_page'      => $per_page,
			'derived_type'  => $derived_type,
			'active_filter' => $active_filter,
			'is_employee'   => pera_crm_user_is_employee( $current_user_id ),
			'scoped_ids'    => $allowed_ids,
		);
	}

}

if ( ! function_exists( 'pera_crm_get_pipeline_advisor_options' ) ) {
	/**
	 * Fetch advisor filter options in CRM target blog context.
	 *
	 * @return array<int,array{id:int,label:string}>
	 */
	function pera_crm_get_pipeline_advisor_options(): array {
		$target_blog_id = get_current_blog_id();
		if ( function_exists( 'peracrm_get_target_blog_id' ) ) {
			$resolved_blog_id = (int) peracrm_get_target_blog_id();
			if ( $resolved_blog_id > 0 ) {
				$target_blog_id = $resolved_blog_id;
			}
		}

		$load_users = static function (): array {
			$users = get_users(
				array(
					'role__in' => array( 'employee', 'manager', 'administrator' ),
					'orderby'  => 'display_name',
					'order'    => 'ASC',
					'number'   => 200,
				)
			);

			$options = array();
			foreach ( $users as $user ) {
				if ( ! ( $user instanceof WP_User ) ) {
					continue;
				}

				$user_id = (int) $user->ID;
				$label   = trim( (string) $user->display_name );
				if ( $user_id <= 0 || '' === $label ) {
					continue;
				}

				$options[] = array(
					'id'    => $user_id,
					'label' => $label,
				);
			}

			return $options;
		};

		if ( function_exists( 'peracrm_with_target_blog' ) ) {
			$result = peracrm_with_target_blog( $load_users );
			return is_array( $result ) ? $result : array();
		}

		if ( is_multisite() && $target_blog_id > 0 && get_current_blog_id() !== $target_blog_id && function_exists( 'switch_to_blog' ) && function_exists( 'restore_current_blog' ) ) {
			switch_to_blog( $target_blog_id );
			try {
				return $load_users();
			} finally {
				restore_current_blog();
			}
		}

		return $load_users();
	}
}

if ( ! function_exists( 'pera_crm_get_pipeline_view_data' ) ) {
	/**
	 * Build grouped pipeline board data using the existing CRM scope resolver.
	 *
	 * @return array<string,mixed>
	 */
	function pera_crm_get_pipeline_view_data(): array {
		$current_user_id = get_current_user_id();
		$allowed_ids     = pera_crm_get_allowed_client_ids_for_user( $current_user_id );
		$q               = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
		$stage           = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( (string) $_GET['stage'] ) ) : '';
		$advisor         = isset( $_GET['advisor'] ) ? absint( wp_unslash( (string) $_GET['advisor'] ) ) : 0;
		$active_filter   = pera_crm_get_request_filter( array( 'unassigned', 'stale', 'new72', 'open_scope' ) );

		$stages = pera_crm_get_pipeline_stages();
		if ( empty( $stages ) ) {
			$stages = array(
				'new'       => 'New',
				'contacted' => 'Contacted',
				'qualified' => 'Qualified',
				'viewing'   => 'Viewing',
				'offer'     => 'Offer',
				'won'       => 'Won / Closed',
			);
		}

		$fallback_stage_key = 'unassigned_new';
		$stages[ $fallback_stage_key ] = __( 'Unassigned / New', 'peracrm' );

		if ( '' !== $stage && ! isset( $stages[ $stage ] ) ) {
			$stage = '';
		}

		$post_statuses = array( 'publish', 'private', 'draft', 'pending', 'future' );

		$query_args = array(
			'post_type'      => 'crm_client',
			'post_status'    => $post_statuses,
			'posts_per_page' => 250,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		$can_manage_all = current_user_can( 'manage_options' ) || current_user_can( 'peracrm_manage_all_clients' );
		if ( ! $can_manage_all ) {
			$query_args['post__in'] = empty( $allowed_ids ) ? array( 0 ) : $allowed_ids;
		}

		if ( '' !== $q ) {
			$search_limit = 500;
			$scoped_post__in = array();

			if ( ! $can_manage_all ) {
				$scoped_post__in = empty( $allowed_ids ) ? array( 0 ) : $allowed_ids;
			}

			$title_query_args = array(
				'post_type'      => 'crm_client',
				'post_status'    => $post_statuses,
				'posts_per_page' => $search_limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				's'              => $q,
			);

			if ( ! empty( $scoped_post__in ) ) {
				$title_query_args['post__in'] = $scoped_post__in;
			}

			$meta_query_args = array(
				'post_type'      => 'crm_client',
				'post_status'    => $post_statuses,
				'posts_per_page' => $search_limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_peracrm_email',
						'value'   => $q,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'crm_primary_email',
						'value'   => $q,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'primary_email',
						'value'   => $q,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_peracrm_phone',
						'value'   => $q,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'crm_phone',
						'value'   => $q,
						'compare' => 'LIKE',
					),
				),
			);

			if ( ! empty( $scoped_post__in ) ) {
				$meta_query_args['post__in'] = $scoped_post__in;
			}

			$title_matches = get_posts( $title_query_args );
			$meta_matches  = get_posts( $meta_query_args );

			$search_ids = array_values(
				array_unique(
					array_filter(
						array_map(
							'intval',
							array_merge( (array) $title_matches, (array) $meta_matches )
						)
					)
				)
			);

			$query_args['post__in'] = empty( $search_ids ) ? array( 0 ) : $search_ids;
		}

		if ( $advisor > 0 ) {
			$query_args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => 'assigned_advisor_user_id',
					'value'   => $advisor,
					'compare' => '=',
				),
				array(
					'key'     => 'crm_assigned_advisor',
					'value'   => $advisor,
					'compare' => '=',
				),
			);
		}

		$clients = get_posts( $query_args );
		$ids     = array_values( array_filter( array_map( 'intval', wp_list_pluck( (array) $clients, 'ID' ) ) ) );

		$party_map = array();
		if ( ! empty( $ids ) && function_exists( 'peracrm_party_get_status_by_ids' ) ) {
			$party_map = peracrm_party_get_status_by_ids( $ids );
		}
		if ( ! empty( $ids ) && function_exists( 'peracrm_client_health_prime_cache' ) ) {
			peracrm_client_health_prime_cache( $ids );
		}

		$board = array();
		foreach ( $stages as $stage_key => $stage_label ) {
			$board[ $stage_key ] = array(
				'key'   => (string) $stage_key,
				'label' => (string) $stage_label,
				'items' => array(),
			);
		}

		$advisor_names = array();
		$advisor_ids   = array();

		foreach ( $clients as $client ) {
			if ( ! ( $client instanceof WP_Post ) ) {
				continue;
			}

			$client_id  = (int) $client->ID;
			$party      = isset( $party_map[ $client_id ] ) && is_array( $party_map[ $client_id ] ) ? $party_map[ $client_id ] : array();
			$stage_key  = sanitize_key( (string) ( $party['lead_pipeline_stage'] ?? '' ) );
			$profile    = function_exists( 'peracrm_client_get_profile' ) ? (array) peracrm_client_get_profile( $client_id ) : array();
			$assigned   = function_exists( 'peracrm_client_get_assigned_advisor_id' ) ? (int) peracrm_client_get_assigned_advisor_id( $client_id ) : 0;
			$health     = function_exists( 'peracrm_client_health_get' ) ? (array) peracrm_client_health_get( $client_id ) : array();
			$last_ts    = isset( $health['last_activity_ts'] ) ? (int) $health['last_activity_ts'] : 0;
			$last_label = $last_ts > 0 ? pera_crm_format_datetime_dmy_hm( $last_ts ) : '';

			if ( '' === $stage_key || ! isset( $board[ $stage_key ] ) ) {
				$stage_key = $fallback_stage_key;
			}

			if ( '' !== $stage && $stage_key !== $stage ) {
				continue;
			}

			$advisor_label = '';
			if ( $assigned > 0 ) {
				$advisor_ids[ $assigned ] = $assigned;
				if ( ! isset( $advisor_names[ $assigned ] ) ) {
					$user                     = get_userdata( $assigned );
					$advisor_names[ $assigned ] = $user instanceof WP_User ? (string) $user->display_name : '';
				}
				$advisor_label = (string) $advisor_names[ $assigned ];
			}

			$budget_min = isset( $profile['budget_min_usd'] ) ? absint( $profile['budget_min_usd'] ) : 0;
			$budget_max = isset( $profile['budget_max_usd'] ) ? absint( $profile['budget_max_usd'] ) : 0;
			$source_data = pera_crm_resolve_party_source_bucket( $client_id, $party );
			$lead_source = sanitize_text_field( (string) ( $source_data['label'] ?? '' ) );

			$board[ $stage_key ]['items'][] = array(
				'id'            => $client_id,
				'title'         => get_the_title( $client_id ),
				'client_url'    => function_exists( 'pera_crm_get_client_view_url' ) ? pera_crm_get_client_view_url( $client_id ) : home_url( '/crm/client/' . $client_id . '/' ),
				'advisor_label' => $advisor_label,
				'last_activity' => $last_label,
				'lead_source'   => $lead_source,
				'budget_min'    => $budget_min,
				'budget_max'    => $budget_max,
			);
		}

		foreach ( $board as $stage_key => $stage_data ) {
			$board[ $stage_key ]['count'] = count( $stage_data['items'] );
		}

		$advisor_options = array();
		if ( $can_manage_all ) {
			$advisor_options = pera_crm_get_pipeline_advisor_options();
		}

		if ( ! empty( $advisor_options ) && function_exists( 'wp_list_sort' ) ) {
			$advisor_options = wp_list_sort( $advisor_options, 'label', 'ASC', true );
		}

		return array(
			'columns'       => $board,
			'is_employee'   => pera_crm_user_is_employee( $current_user_id ),
			'scoped_ids'    => $allowed_ids,
			'current_user'  => $current_user_id,
			'can_view_all'  => $can_manage_all,
			'filters'       => array(
				'q'               => $q,
				'stage'           => $stage,
				'advisor'         => $advisor,
				'advisor_options' => $advisor_options,
				'stage_options'   => $stages,
			),
		);
	}
}

if ( ! function_exists( 'pera_crm_get_performance_range_options' ) ) {
	/**
	 * Supported performance date ranges.
	 *
	 * @return array<string,string>
	 */
	function pera_crm_get_performance_range_options(): array {
		return array(
			'7d'         => __( 'Last 7 days', 'peracrm' ),
			'30d'        => __( 'Last 30 days', 'peracrm' ),
			'this_month' => __( 'This month', 'peracrm' ),
			'last_month' => __( 'Last month', 'peracrm' ),
		);
	}
}

if ( ! function_exists( 'pera_crm_resolve_performance_range' ) ) {
	/**
	 * Resolve date range bounds for performance dashboard.
	 *
	 * @return array{key:string,label:string,date_from:string,date_to:string}
	 */
	function pera_crm_resolve_performance_range( string $range_key = '30d' ): array {
		$options   = pera_crm_get_performance_range_options();
		$range_key = sanitize_key( $range_key );
		if ( ! isset( $options[ $range_key ] ) ) {
			$range_key = '30d';
		}

		$now      = current_time( 'timestamp' );
		$date_to  = wp_date( 'Y-m-d H:i:s', $now, wp_timezone() );
		$date_from = $date_to;

		switch ( $range_key ) {
			case '7d':
				$date_from = wp_date( 'Y-m-d H:i:s', strtotime( '-7 days', $now ), wp_timezone() );
				break;
			case 'this_month':
				$date_from = wp_date( 'Y-m-01 00:00:00', $now, wp_timezone() );
				break;
			case 'last_month':
				$date_from = wp_date( 'Y-m-01 00:00:00', strtotime( 'first day of last month', $now ), wp_timezone() );
				$date_to   = wp_date( 'Y-m-t 23:59:59', strtotime( 'last day of last month', $now ), wp_timezone() );
				break;
			case '30d':
			default:
				$date_from = wp_date( 'Y-m-d H:i:s', strtotime( '-30 days', $now ), wp_timezone() );
				break;
		}

		return array(
			'key'       => $range_key,
			'label'     => (string) $options[ $range_key ],
			'date_from' => $date_from,
			'date_to'   => $date_to,
		);
	}
}

if ( ! function_exists( 'pera_crm_get_client_assigned_advisor_id' ) ) {
	/**
	 * Resolve assigned advisor user ID for a CRM client post.
	 */
	function pera_crm_get_client_assigned_advisor_id( int $client_id ): int {
		$client_id = absint( $client_id );
		if ( $client_id <= 0 ) {
			return 0;
		}

		if ( function_exists( 'peracrm_client_get_assigned_advisor_id' ) ) {
			return max( 0, (int) peracrm_client_get_assigned_advisor_id( $client_id ) );
		}

		$assigned = (int) get_post_meta( $client_id, 'assigned_advisor_user_id', true );
		if ( $assigned > 0 ) {
			return $assigned;
		}

		return max( 0, (int) get_post_meta( $client_id, 'crm_assigned_advisor', true ) );
	}
}

if ( ! function_exists( 'pera_crm_get_performance_assigned_party_ids' ) ) {
	/**
	 * Fetch CRM party IDs assigned to advisors inside the selected date window.
	 *
	 * Note: this plugin does not maintain a canonical assignment timestamp for all
	 * assignments. Until such a source exists, performance cohorts are bounded by
	 * CRM party post creation time.
	 *
	 * @param array{date_from:string,date_to:string} $range
	 * @param int                                     $scope_user_id
	 * @return int[]
	 */
	function pera_crm_get_performance_assigned_party_ids( array $range, int $scope_user_id = 0 ): array {
		$scope_user_id = max( 0, absint( $scope_user_id ) );
		$date_from     = sanitize_text_field( (string) ( $range['date_from'] ?? '' ) );
		$date_to       = sanitize_text_field( (string) ( $range['date_to'] ?? '' ) );

		$meta_query = array();
		if ( $scope_user_id > 0 ) {
			$meta_query = array(
				'relation' => 'OR',
				array(
					'key'     => 'assigned_advisor_user_id',
					'value'   => $scope_user_id,
					'compare' => '=',
				),
				array(
					'key'     => 'crm_assigned_advisor',
					'value'   => $scope_user_id,
					'compare' => '=',
				),
			);
		} else {
			$meta_query = array(
				'relation' => 'OR',
				array(
					'key'     => 'assigned_advisor_user_id',
					'value'   => 0,
					'type'    => 'NUMERIC',
					'compare' => '>',
				),
				array(
					'key'     => 'crm_assigned_advisor',
					'value'   => 0,
					'type'    => 'NUMERIC',
					'compare' => '>',
				),
			);
		}

		$ids = get_posts(
			array(
				'post_type'      => 'crm_client',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'column'    => 'post_date',
						'after'     => $date_from,
						'before'    => $date_to,
						'inclusive' => true,
					),
				),
				'meta_query'     => $meta_query,
			)
		);

		return array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
	}
}

if ( ! function_exists( 'pera_crm_filter_true_lead_party_ids' ) ) {
	/**
	 * Canonical lead filter:
	 * - `crm_client` holds party records.
	 * - parties with completed deals are treated as converted clients and excluded.
	 *
	 * @param int[] $party_ids
	 * @return int[]
	 */
	function pera_crm_filter_true_lead_party_ids( array $party_ids ): array {
		$party_ids = array_values( array_unique( array_filter( array_map( 'intval', $party_ids ) ) ) );
		if ( empty( $party_ids ) ) {
			return array();
		}

		$converted_client_ids = function_exists( 'peracrm_party_batch_get_closed_won_client_ids' )
			? array_map( 'intval', (array) peracrm_party_batch_get_closed_won_client_ids( $party_ids ) )
			: array();
		$converted_lookup     = array_fill_keys( $converted_client_ids, true );
		$lead_ids             = array();

		foreach ( $party_ids as $party_id ) {
			if ( isset( $converted_lookup[ $party_id ] ) ) {
				continue;
			}

			$lead_ids[] = $party_id;
		}

		return array_values( array_unique( $lead_ids ) );
	}
}

if ( ! function_exists( 'pera_crm_resolve_party_source_bucket' ) ) {
	/**
	 * Resolve lead source using existing CRM pipeline precedence.
	 *
	 * @param int                $lead_id
	 * @param array<string,mixed> $party
	 * @return array{key:string,label:string}
	 */
	function pera_crm_resolve_party_source_bucket( int $lead_id, array $party = array() ): array {
		$lead_id = absint( $lead_id );
		$profile = function_exists( 'peracrm_client_get_profile' ) ? (array) peracrm_client_get_profile( $lead_id ) : array();

		$lead_source_candidates = array(
			$party['lead_source'] ?? '',
			$party['source'] ?? '',
			$profile['lead_source'] ?? '',
			$profile['source'] ?? '',
			get_post_meta( $lead_id, 'crm_source', true ),
		);

		foreach ( $lead_source_candidates as $lead_source_candidate ) {
			$lead_source_candidate = sanitize_text_field( (string) $lead_source_candidate );
			if ( '' === $lead_source_candidate ) {
				continue;
			}

			$source_key = sanitize_key( $lead_source_candidate );
			return array(
				'key'   => $source_key,
				'label' => ucwords( str_replace( '_', ' ', $lead_source_candidate ) ),
			);
		}

		return array(
			'key'   => '',
			'label' => '',
		);
	}
}

if ( ! function_exists( 'pera_crm_get_previous_performance_range' ) ) {
	/**
	 * Derive the immediately preceding equivalent range for comparison.
	 *
	 * @param array{key?:string,label?:string,date_from?:string,date_to?:string} $current_range
	 * @return array{key:string,label:string,date_from:string,date_to:string}
	 */
	function pera_crm_get_previous_performance_range( array $current_range ): array {
		$key      = sanitize_key( (string) ( $current_range['key'] ?? '30d' ) );
		$timezone = wp_timezone();

		try {
			$current_from = new DateTimeImmutable( (string) ( $current_range['date_from'] ?? '' ), $timezone );
			$current_to   = new DateTimeImmutable( (string) ( $current_range['date_to'] ?? '' ), $timezone );
		} catch ( Exception $e ) {
			return pera_crm_resolve_performance_range( '30d' );
		}

		switch ( $key ) {
			case 'this_month':
				$previous_key = 'last_month';
				$from_dt      = $current_from->modify( 'first day of last month' )->setTime( 0, 0, 0 );
				$to_dt        = $current_from->modify( 'last day of last month' )->setTime( 23, 59, 59 );
				$label        = pera_crm_get_performance_range_options()['last_month'] ?? __( 'Last month', 'peracrm' );
				break;
			case 'last_month':
				$previous_key = 'last_month';
				$from_dt      = $current_from->modify( 'first day of last month' )->setTime( 0, 0, 0 );
				$to_dt        = $current_from->modify( 'last day of last month' )->setTime( 23, 59, 59 );
				$label        = sprintf(
					/* translators: %s: month label (e.g. March 2026). */
					__( 'Previous: %s', 'peracrm' ),
					wp_date( 'F Y', $from_dt->getTimestamp(), $timezone )
				);
				break;
			case '7d':
			case '30d':
			default:
				$previous_key = $key;
				$seconds      = max( 1, $current_to->getTimestamp() - $current_from->getTimestamp() + 1 );
				$from_dt      = $current_from->modify( '-' . $seconds . ' seconds' );
				$to_dt        = $current_from->modify( '-1 second' );
				$label        = sprintf(
					/* translators: %1$s: from date, %2$s: to date. */
					__( 'Previous period (%1$s – %2$s)', 'peracrm' ),
					wp_date( get_option( 'date_format' ), $from_dt->getTimestamp(), $timezone ),
					wp_date( get_option( 'date_format' ), $to_dt->getTimestamp(), $timezone )
				);
				break;
		}

		return array(
			'key'       => $previous_key,
			'label'     => $label,
			'date_from' => $from_dt->format( 'Y-m-d H:i:s' ),
			'date_to'   => $to_dt->format( 'Y-m-d H:i:s' ),
		);
	}
}

if ( ! function_exists( 'pera_crm_get_stage_distribution_for_cohort' ) ) {
	/**
	 * Build current pipeline stage distribution for the provided cohort.
	 *
	 * Reuses canonical pipeline stages and the pipeline view fallback bucket for
	 * records without a recognized stage.
	 *
	 * @param int[]                     $cohort_ids
	 * @param array<int,array<string,mixed>> $party_map
	 * @return array<int,array{key:string,label:string,count:int}>
	 */
	function pera_crm_get_stage_distribution_for_cohort( array $cohort_ids, array $party_map = array() ): array {
		$cohort_ids = array_values( array_unique( array_filter( array_map( 'intval', $cohort_ids ) ) ) );
		$stages     = pera_crm_get_pipeline_stages();
		if ( empty( $stages ) ) {
			$stages = array(
				'new_enquiry'      => __( 'New enquiry', 'peracrm' ),
				'qualified'        => __( 'Qualified', 'peracrm' ),
				'viewing_arranged' => __( 'Viewing arranged', 'peracrm' ),
				'offer_made'       => __( 'Offer made', 'peracrm' ),
				'deal_closed'      => __( 'Deal closed', 'peracrm' ),
				'deal_lost'        => __( 'Deal lost', 'peracrm' ),
			);
		}

		$fallback_stage_key         = 'unassigned_new';
		$stages[ $fallback_stage_key ] = __( 'Unassigned / New', 'peracrm' );
		$counts                     = array_fill_keys( array_keys( $stages ), 0 );

		if ( ! empty( $cohort_ids ) && empty( $party_map ) && function_exists( 'peracrm_party_get_status_by_ids' ) ) {
			$party_map = peracrm_party_get_status_by_ids( $cohort_ids );
		}

		foreach ( $cohort_ids as $lead_id ) {
			$party     = is_array( $party_map[ $lead_id ] ?? null ) ? $party_map[ $lead_id ] : array();
			$stage_key = sanitize_key( (string) ( $party['lead_pipeline_stage'] ?? '' ) );
			if ( '' === $stage_key || ! isset( $counts[ $stage_key ] ) ) {
				$stage_key = $fallback_stage_key;
			}
			++$counts[ $stage_key ];
		}

		$distribution = array();
		foreach ( $stages as $stage_key => $stage_label ) {
			$distribution[] = array(
				'key'   => (string) $stage_key,
				'label' => sanitize_text_field( (string) $stage_label ),
				'count' => max( 0, (int) ( $counts[ $stage_key ] ?? 0 ) ),
			);
		}

		return $distribution;
	}
}

if ( ! function_exists( 'pera_crm_build_performance_summary_for_range' ) ) {
	/**
	 * Build performance summary payload for a resolved date range.
	 *
	 * @param array{key:string,label:string,date_from:string,date_to:string} $range
	 * @return array<string,mixed>
	 */
	function pera_crm_build_performance_summary_for_range( array $range, int $scope_user_id, bool $is_employee ): array {
		$candidate_party_ids = pera_crm_get_performance_assigned_party_ids( $range, $scope_user_id );
		$cohort_ids          = $candidate_party_ids;
		$party_map           = ! empty( $cohort_ids ) && function_exists( 'peracrm_party_get_status_by_ids' )
			? peracrm_party_get_status_by_ids( $cohort_ids )
			: array();

		$new_leads    = count( $cohort_ids );
		$junk         = 0;
		$qualified    = 0;
		$viewings     = 0;
		$source_rows  = array();
		$no_activity  = 0;
		$no_reminder  = 0;
		$untouched    = 0;
		$overdue      = 0;
		$stale_ts     = current_time( 'timestamp' ) - ( 3 * DAY_IN_SECONDS );
		$now_mysql    = current_time( 'mysql' );
		$reminder_by_lead = array();

		foreach ( $cohort_ids as $lead_id ) {
			$lead_id      = (int) $lead_id;
			$party        = is_array( $party_map[ $lead_id ] ?? null ) ? $party_map[ $lead_id ] : array();
			$disposition  = sanitize_key( (string) ( $party['disposition'] ?? 'none' ) );
			$stage        = sanitize_key( (string) ( $party['lead_pipeline_stage'] ?? '' ) );
			$is_junk      = 'junk_lead' === $disposition;
			$source_data  = pera_crm_resolve_party_source_bucket( $lead_id, $party );
			$source_key   = sanitize_key( (string) ( $source_data['key'] ?? '' ) );
			$source_label = sanitize_text_field( (string) ( $source_data['label'] ?? '' ) );

			if ( '' === $source_key ) {
				$source_key   = 'unknown';
				$source_label = __( 'Unknown', 'peracrm' );
			}

			if ( '' === $source_label ) {
				$source_label = ucwords( str_replace( '_', ' ', $source_key ) );
			}

			if ( ! isset( $source_rows[ $source_key ] ) ) {
				$source_rows[ $source_key ] = array(
					'source'    => $source_label,
					'key'       => $source_key,
					'leads'     => 0,
					'qualified' => 0,
					'junk'      => 0,
					'viewings'  => 0,
				);
			}

			++$source_rows[ $source_key ]['leads'];

			if ( $is_junk ) {
				++$junk;
				++$source_rows[ $source_key ]['junk'];
			} else {
				if ( 'qualified' === $stage ) {
					++$qualified;
					++$source_rows[ $source_key ]['qualified'];
				}

				if ( 'viewing_arranged' === $stage ) {
					++$viewings;
					++$source_rows[ $source_key ]['viewings'];
				}
			}

			$last_activity_ts = 0;
			$has_activity     = false;
			if ( function_exists( 'peracrm_client_health_get' ) ) {
				$health           = (array) peracrm_client_health_get( $lead_id );
				$last_activity_ts = (int) ( $health['last_activity_ts'] ?? 0 );
				$has_activity     = $last_activity_ts > 0;
			} elseif ( function_exists( 'peracrm_notes_list' ) ) {
				$latest_note = peracrm_notes_list( $lead_id, 1, 0 );
				if ( is_array( $latest_note ) && is_array( $latest_note[0] ?? null ) ) {
					$has_activity = true;
					foreach ( array( 'created_at', 'date_gmt', 'date', 'time' ) as $time_key ) {
						$raw_ts = isset( $latest_note[0][ $time_key ] ) ? strtotime( (string) $latest_note[0][ $time_key ] ) : 0;
						if ( $raw_ts > 0 ) {
							$last_activity_ts = max( $last_activity_ts, $raw_ts );
							break;
						}
					}
				}
			}
			if ( ! $has_activity ) {
				++$no_activity;
			}

			if ( ! isset( $reminder_by_lead[ $lead_id ] ) ) {
				$lead_reminders = function_exists( 'peracrm_reminders_list_for_client' )
					? (array) peracrm_reminders_list_for_client( $lead_id, 200, 0, null )
					: array();
				$has_any     = ! empty( $lead_reminders );
				$has_overdue = false;
				if ( $has_any ) {
					foreach ( $lead_reminders as $lead_reminder ) {
						if ( ! is_array( $lead_reminder ) ) {
							continue;
						}
						$due_at = (string) ( $lead_reminder['due_at'] ?? '' );
						$status = sanitize_key( (string) ( $lead_reminder['status'] ?? '' ) );
						if ( '' !== $due_at && $due_at < $now_mysql && ! pera_crm_reminder_is_closed_status( $status ) ) {
							$has_overdue = true;
							break;
						}
					}
				}
				$reminder_by_lead[ $lead_id ] = array(
					'has_any'     => $has_any,
					'has_overdue' => $has_overdue,
				);
			}

			$lead_reminder_row = is_array( $reminder_by_lead[ $lead_id ] ?? null ) ? $reminder_by_lead[ $lead_id ] : array();
			if ( empty( $lead_reminder_row['has_any'] ) ) {
				++$no_reminder;
			}
			if ( $last_activity_ts <= 0 || $last_activity_ts < $stale_ts ) {
				++$untouched;
			}
			if ( ! empty( $lead_reminder_row['has_overdue'] ) ) {
				++$overdue;
			}
		}

		if ( ! empty( $source_rows ) ) {
			uasort(
				$source_rows,
				static function ( array $a, array $b ): int {
					$lead_compare = (int) ( $b['leads'] ?? 0 ) <=> (int) ( $a['leads'] ?? 0 );
					if ( 0 !== $lead_compare ) {
						return $lead_compare;
					}
					return strcasecmp( (string) ( $a['source'] ?? '' ), (string) ( $b['source'] ?? '' ) );
				}
			);
		}

		$deals_created = 0;
		if ( function_exists( 'peracrm_deals_table_exists' ) && peracrm_deals_table_exists() ) {
			$deals_created = (int) peracrm_with_target_blog(
				static function () use ( $scope_user_id, $range ): int {
					global $wpdb;
					$table        = peracrm_table( 'peracrm_deals' );
					$postmeta     = $wpdb->postmeta;
					$where_clause = 'd.created_at >= %s AND d.created_at <= %s';
					$params       = array( (string) $range['date_from'], (string) $range['date_to'] );
					if ( $scope_user_id > 0 ) {
						$where_clause .= " AND (
							d.owner_user_id = %d
							OR (
								(d.owner_user_id IS NULL OR d.owner_user_id = 0)
								AND EXISTS (
									SELECT 1
									FROM {$postmeta} pm
									WHERE pm.post_id = d.party_id
									  AND pm.meta_key IN ('assigned_advisor_user_id','crm_assigned_advisor')
									  AND pm.meta_value = %s
								)
							)
						)";
						$params[] = $scope_user_id;
						$params[] = (string) $scope_user_id;
					}
					$query = $wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} d WHERE {$where_clause}",
						$params
					);
					$count = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					return is_numeric( $count ) ? max( 0, (int) $count ) : 0;
				}
			);
		}

		$qualified_rate = $new_leads > 0 ? ( (float) $qualified / (float) $new_leads ) : 0.0;
		$viewing_rate   = $new_leads > 0 ? ( (float) $viewings / (float) $new_leads ) : 0.0;
		$deal_rate      = $new_leads > 0 ? ( (float) $deals_created / (float) $new_leads ) : 0.0;
		$stage_distribution = pera_crm_get_stage_distribution_for_cohort( $cohort_ids, is_array( $party_map ) ? $party_map : array() );

		return array(
			'range' => $range,
			'scope' => array(
				'user_id'     => $scope_user_id,
				'is_employee' => $is_employee,
			),
			'new_leads_basis' => array(
				'date_field' => 'post_date',
				'mode'       => 'creation_date',
				'note'       => 'Lead cohort is created-in-period assigned CRM records in visible scope.',
			),
			'cards' => array(
				'new_leads'     => $new_leads,
				'qualified'     => $qualified,
				'junk'          => $junk,
				'viewings'      => $viewings,
				'deals_created' => $deals_created,
			),
			'progress' => array(
				'leads'          => (int) $new_leads,
				'qualified'      => (int) $qualified,
				'viewings'       => (int) $viewings,
				'deals_created'  => (int) $deals_created,
				'qualified_rate' => (float) $qualified_rate,
				'viewing_rate'   => (float) $viewing_rate,
				'deal_rate'      => (float) $deal_rate,
			),
			'attention' => array(
				'no_activity' => $no_activity,
				'no_reminder' => $no_reminder,
				'untouched'   => $untouched,
				'overdue'     => $overdue,
			),
			'sources' => array_values( $source_rows ),
			'stage_distribution' => $stage_distribution,
		);
	}
}

if ( ! function_exists( 'pera_crm_get_performance_delta_payload' ) ) {
	/**
	 * Build comparison delta payload between current and previous summary values.
	 *
	 * pct is null when previous is 0 and current > 0 to avoid divide-by-zero.
	 *
	 * @param array<string,mixed> $current
	 * @param array<string,mixed> $previous
	 * @return array<string,array<string,int|float|null>>
	 */
	function pera_crm_get_performance_delta_payload( array $current, array $previous ): array {
		$metrics = array(
			'leads',
			'qualified',
			'viewings',
			'deals_created',
			'qualified_rate',
			'viewing_rate',
			'deal_rate',
		);
		$delta = array();

		foreach ( $metrics as $metric ) {
			$current_value  = isset( $current['progress'][ $metric ] ) ? (float) $current['progress'][ $metric ] : 0.0;
			$previous_value = isset( $previous['progress'][ $metric ] ) ? (float) $previous['progress'][ $metric ] : 0.0;
			$abs            = $current_value - $previous_value;
			$pct            = 0.0;

			if ( $previous_value > 0 ) {
				$pct = ( $abs / $previous_value ) * 100;
			} elseif ( $current_value > 0 ) {
				$pct = null;
			}

			$delta[ $metric ] = array(
				'current'  => $current_value,
				'previous' => $previous_value,
				'abs'      => $abs,
				'pct'      => $pct,
			);
		}

		foreach ( array( 'leads', 'qualified', 'viewings', 'deals_created' ) as $count_metric ) {
			$delta[ $count_metric ]['current']  = (int) round( (float) $delta[ $count_metric ]['current'] );
			$delta[ $count_metric ]['previous'] = (int) round( (float) $delta[ $count_metric ]['previous'] );
			$delta[ $count_metric ]['abs']      = (int) round( (float) $delta[ $count_metric ]['abs'] );
		}

		return $delta;
	}
}

if ( ! function_exists( 'pera_crm_get_performance_summary' ) ) {
	/**
	 * Build server-rendered performance summary payload.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	function pera_crm_get_performance_summary( array $args = array() ): array {
		$current_user_id = get_current_user_id();
		$effective_user  = function_exists( 'peracrm_get_effective_crm_user_id' ) ? (int) peracrm_get_effective_crm_user_id() : $current_user_id;
		$is_employee     = pera_crm_user_is_employee( $effective_user );
		$scope_user_id   = $is_employee ? $effective_user : 0;
		$requested_scope = isset( $args['scope_user_id'] ) ? absint( $args['scope_user_id'] ) : null;

		if ( null !== $requested_scope ) {
			$scope_user_id = $requested_scope;
		}

		$current_range = pera_crm_resolve_performance_range( isset( $args['range_key'] ) ? (string) $args['range_key'] : '30d' );
		if ( ! empty( $args['date_from'] ) && ! empty( $args['date_to'] ) ) {
			$current_range['date_from'] = sanitize_text_field( (string) $args['date_from'] );
			$current_range['date_to']   = sanitize_text_field( (string) $args['date_to'] );
		}

		$previous_range    = pera_crm_get_previous_performance_range( $current_range );
		$current_summary   = pera_crm_build_performance_summary_for_range( $current_range, $scope_user_id, $is_employee );
		$previous_summary  = pera_crm_build_performance_summary_for_range( $previous_range, $scope_user_id, $is_employee );
		$comparison_payload = array(
			'current' => array(
				'range'    => $current_summary['range'],
				'cards'    => $current_summary['cards'],
				'progress' => $current_summary['progress'],
			),
			'previous' => array(
				'range'    => $previous_summary['range'],
				'cards'    => $previous_summary['cards'],
				'progress' => $previous_summary['progress'],
			),
			'delta' => pera_crm_get_performance_delta_payload( $current_summary, $previous_summary ),
		);

		$current_summary['comparison'] = $comparison_payload;
		return $current_summary;
	}
}
