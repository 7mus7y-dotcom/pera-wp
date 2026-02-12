<?php
/**
 * CRM dashboard data helpers (read-only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

		$current_user_id = get_current_user_id();
		$is_employee     = function_exists( 'pera_crm_user_is_employee' ) && pera_crm_user_is_employee( $current_user_id );

		if ( $is_employee && function_exists( 'peracrm_reminders_count_for_advisor' ) ) {
			return max( 0, (int) peracrm_reminders_count_for_advisor( $current_user_id, pera_crm_reminders_open_status(), 'overdue' ) );
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
		$current_user_id = get_current_user_id();
		$is_employee     = pera_crm_user_is_employee( $current_user_id );
		$allowed_ids     = $is_employee ? pera_crm_get_allowed_client_ids_for_user( $current_user_id ) : array();

		if ( function_exists( 'peracrm_activity_list' ) ) {
			$args = array(
				'limit'   => 20,
				'orderby' => 'created_at',
				'order'   => 'DESC',
			);
			if ( $is_employee ) {
				if ( empty( $allowed_ids ) ) {
					return array( 'available' => true, 'rows' => array() );
				}
				$args['party_ids'] = $allowed_ids;
			}
			$items = pera_crm_normalize_activities( peracrm_activity_list( $args ) );
			if ( ! empty( $items ) || $is_employee ) {
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
			if ( $is_employee && ! empty( $allowed_ids ) ) {
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
	 * Fetch latest leads for dashboard.
	 *
	 * @return array<int,array{id:int,name:string,url:string}>
	 */
	function pera_crm_get_recent_leads( int $limit = 20 ): array {
		$current_user_id = get_current_user_id();
		$is_employee     = pera_crm_user_is_employee( $current_user_id );
		$allowed_ids     = $is_employee ? pera_crm_get_allowed_client_ids_for_user( $current_user_id ) : array();
		$args = array(
			'post_type'      => 'crm_client',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => max( 1, $limit ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		if ( $is_employee ) {
			$args['post__in'] = empty( $allowed_ids ) ? array( 0 ) : $allowed_ids;
		}
		$ids = get_posts( $args );
		$rows = array();
		foreach ( (array) $ids as $lead_id ) {
			$lead_id = (int) $lead_id;
			$rows[] = array(
				'id'   => $lead_id,
				'name' => get_the_title( $lead_id ),
				'url'  => function_exists( 'pera_crm_get_client_view_url' ) ? pera_crm_get_client_view_url( $lead_id ) : home_url( '/crm/client/' . $lead_id . '/' ),
			);
		}
		return $rows;
	}
}

if ( ! function_exists( 'pera_crm_get_task_rows' ) ) {
	/**
	 * Resolve reminder/task rows from helpers or SQL fallback.
	 *
	 * @return array<int,array{reminder_id:int,lead_id:int,lead_name:string,due_date:string,reminder_note:string,status:string}>
	 */
	function pera_crm_get_task_rows( bool $overdue = false ): array {
		$current_user_id = get_current_user_id();
		$is_employee     = pera_crm_user_is_employee( $current_user_id );
		$open_status     = pera_crm_reminders_open_status();
		$now             = current_time( 'mysql' );
		$today_ts        = current_time( 'timestamp' );
		$today_start     = date( 'Y-m-d 00:00:00', $today_ts );
		$today_end       = date( 'Y-m-d 23:59:59', $today_ts );
		$rows            = array();
		$debug_ids       = array();

		if ( $is_employee && function_exists( 'peracrm_reminders_list_for_advisor' ) ) {
			$range = $overdue ? 'overdue' : 'all';
			$raw   = peracrm_reminders_list_for_advisor( $current_user_id, 200, 0, $open_status, $range, 'asc' );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$due_at = (string) ( $row['due_at'] ?? '' );
					if ( '' === $due_at ) {
						continue;
					}
					if ( ! $overdue && ( $due_at < $today_start || $due_at > $today_end ) ) {
						continue;
					}
					if ( $overdue && $due_at >= $now ) {
						continue;
					}
					$lead_id     = (int) ( $row['client_id'] ?? 0 );
					$debug_ids[] = (string) ( $row['id'] ?? 0 );
					$rows[]      = array(
						'reminder_id'   => (int) ( $row['id'] ?? 0 ),
						'lead_id'       => $lead_id,
						'lead_name'     => pera_crm_get_lead_name( $lead_id ),
						'due_date'      => $due_at,
						'reminder_note' => wp_strip_all_tags( (string) ( $row['note'] ?? '' ) ),
						'status'        => sanitize_key( (string) ( $row['status'] ?? $open_status ) ),
					);
				}
				pera_crm_debug_tasks_log( $overdue ? 'overdue' : 'today', 'mu_advisor', $debug_ids, count( $rows ) );
				return $rows;
			}
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

		if ( $overdue ) {
			$sql = $wpdb->prepare( "SELECT id, client_id, due_at, note FROM {$table} WHERE status = %s AND due_at < %s ORDER BY due_at ASC LIMIT 200", $open_status, $now ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = $wpdb->prepare( "SELECT id, client_id, due_at, note FROM {$table} WHERE status = %s AND due_at BETWEEN %s AND %s ORDER BY due_at ASC LIMIT 200", $open_status, $today_start, $today_end ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$raw   = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows  = array();
		foreach ( (array) $raw as $row ) {
			$lead_id     = (int) ( $row['client_id'] ?? 0 );
			$debug_ids[] = (string) ( $row['id'] ?? 0 );
			$rows[] = array(
				'reminder_id'   => (int) ( $row['id'] ?? 0 ),
				'lead_id'       => $lead_id,
				'lead_name'     => pera_crm_get_lead_name( $lead_id ),
				'due_date'      => (string) ( $row['due_at'] ?? '' ),
				'reminder_note' => wp_strip_all_tags( (string) ( $row['note'] ?? '' ) ),
				'status'        => $open_status,
			);
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
			$notices[] = __( 'CRM data unavailable: pipeline and KPI counts could not be loaded.', 'hello-elementor-child' );
		}

		$open_leads = 0;
		foreach ( $stage_counts as $stage_key => $count ) {
			if ( in_array( $stage_key, array( 'deal_closed', 'deal_lost' ), true ) ) {
				continue;
			}
			$open_leads += (int) $count;
		}

		$overdue_reminders = pera_crm_fetch_overdue_reminders_count();
		if ( null === $overdue_reminders ) {
			$notices[] = __( 'CRM data unavailable: reminders data source not found.', 'hello-elementor-child' );
			$overdue_reminders = 0;
		}

		$activity_data = pera_crm_fetch_recent_activity();
		if ( ! $activity_data['available'] ) {
			$notices[] = __( 'CRM data unavailable: recent activity feed could not be loaded.', 'hello-elementor-child' );
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

		return array(
			'kpis'          => $kpis,
			'pipeline'      => $pipeline,
			'activity'      => $activity,
			'todays_tasks'  => $todays_tasks,
			'overdue_tasks' => $overdue_tasks,
			'new_leads'     => $new_leads,
			'notices'       => array_values( array_unique( $notices ) ),
		);
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
	function pera_crm_get_leads_view_data( int $page = 1, int $per_page = 20 ): array {
		$current_user_id = get_current_user_id();
		$page            = max( 1, absint( $page ) );
		$per_page        = max( 1, absint( $per_page ) );
		$allowed_ids     = pera_crm_get_allowed_client_ids_for_user( $current_user_id );

		$query_args = array(
			'post_type'      => 'crm_client',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( pera_crm_user_is_employee( $current_user_id ) ) {
			if ( empty( $allowed_ids ) ) {
				$query_args['post__in'] = array( 0 );
			} else {
				$query_args['post__in'] = $allowed_ids;
			}
		}

		$leads_query = new WP_Query( $query_args );
		$post_ids    = array_map( 'intval', wp_list_pluck( (array) $leads_query->posts, 'ID' ) );

		$party_map = function_exists( 'peracrm_party_get_status_by_ids' ) ? peracrm_party_get_status_by_ids( $post_ids ) : array();

		if ( function_exists( 'peracrm_client_health_prime_cache' ) ) {
			peracrm_client_health_prime_cache( $post_ids );
		}

		$items = array();
		foreach ( $leads_query->posts as $post ) {
			$lead_id   = (int) $post->ID;
			$party     = isset( $party_map[ $lead_id ] ) && is_array( $party_map[ $lead_id ] ) ? $party_map[ $lead_id ] : array();
			$health    = function_exists( 'peracrm_client_health_get' ) ? peracrm_client_health_get( $lead_id ) : array();
			$last_ts   = isset( $health['last_activity_ts'] ) ? (int) $health['last_activity_ts'] : 0;
			$last_date = $last_ts > 0 ? wp_date( get_option( 'date_format' ), $last_ts ) : '';

			$items[] = array(
				'id'               => $lead_id,
				'title'            => get_the_title( $lead_id ),
				'stage'            => (string) ( $party['lead_pipeline_stage'] ?? 'new_enquiry' ),
				'engagement_state' => (string) ( $party['engagement_state'] ?? '' ),
				'disposition'      => (string) ( $party['disposition'] ?? '' ),
				'last_activity'    => $last_date,
				'last_activity_ts' => $last_ts,
				'crm_url'          => function_exists( 'pera_crm_get_client_view_url' ) ? pera_crm_get_client_view_url( $lead_id ) : home_url( '/crm/client/' . $lead_id . '/' ),
				'edit_url'         => admin_url( 'post.php?post=' . $lead_id . '&action=edit' ),
			);
		}

		if ( function_exists( 'wp_list_sort' ) ) {
			$items = wp_list_sort( $items, 'last_activity_ts', 'DESC', true );
		}

		return array(
			'items'         => $items,
			'query'         => $leads_query,
			'total'         => (int) $leads_query->found_posts,
			'total_pages'   => max( 1, (int) $leads_query->max_num_pages ),
			'current_page'  => $page,
			'per_page'      => $per_page,
			'is_employee'   => pera_crm_user_is_employee( $current_user_id ),
			'scoped_ids'    => $allowed_ids,
		);
	}
}
