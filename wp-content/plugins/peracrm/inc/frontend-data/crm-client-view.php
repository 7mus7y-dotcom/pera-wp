<?php
/**
 * Front-end CRM Client View helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_crm_client_view_get_client_id' ) ) {
	function pera_crm_client_view_get_client_id(): int {
		$client_id = (int) get_query_var( 'client_id', 0 );
		if ( $client_id <= 0 ) {
			$client_id = (int) get_query_var( 'pera_crm_client_id', 0 );
		}

		return max( 0, $client_id );
	}
}

if ( ! function_exists( 'pera_crm_client_view_with_target_blog' ) ) {
	function pera_crm_client_view_with_target_blog( callable $callback ) {
		if ( function_exists( 'peracrm_with_target_blog' ) ) {
			return peracrm_with_target_blog( $callback );
		}

		return $callback();
	}
}

if ( ! function_exists( 'pera_crm_client_view_can_manage' ) ) {
	function pera_crm_client_view_can_manage(): bool {
		return function_exists( 'peracrm_admin_user_can_manage' )
			? (bool) peracrm_admin_user_can_manage()
			: ( current_user_can( 'manage_options' ) || current_user_can( 'edit_crm_clients' ) );
	}
}


if ( ! function_exists( 'pera_crm_ajax_debug_enabled' ) ) {
	function pera_crm_ajax_debug_enabled(): bool {
		return defined( 'WP_DEBUG' )
			&& WP_DEBUG
			&& defined( 'PERA_CRM_DEBUG_AJAX' )
			&& PERA_CRM_DEBUG_AJAX;
	}
}

if ( ! function_exists( 'pera_crm_ajax_is_expected_action' ) ) {
	function pera_crm_ajax_is_expected_action( string $expected_action ): bool {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
		return $action === sanitize_key( $expected_action );
	}
}

if ( ! function_exists( 'pera_crm_ajax_error' ) ) {
	function pera_crm_ajax_error( string $code, string $message, int $status = 400, array $context = array() ): void {
		$payload = array(
			'ok'      => false,
			'code'    => sanitize_key( $code ),
			'message' => $message,
		);

		if ( pera_crm_ajax_debug_enabled() ) {
			$payload['context'] = $context;
			error_log( 'PeraCRM AJAX rejected [' . sanitize_key( $code ) . ']: ' . $message );
		}

		wp_send_json_error( $payload, $status );
		exit;
	}
}

if ( ! function_exists( 'pera_crm_ajax_success' ) ) {
	function pera_crm_ajax_success( array $data = array() ): void {
		wp_send_json_success( array_merge( array( 'ok' => true ), $data ) );
		exit;
	}
}

if ( ! function_exists( 'pera_crm_client_view_access_state' ) ) {
	function pera_crm_client_view_access_state( int $client_id ): array {
		if ( ! pera_crm_client_view_can_manage() ) {
			return array( 'allowed' => false, 'message' => __( 'You do not have permission to access Client View.', 'peracrm' ) );
		}

		$client = pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ) {
				return $client_id > 0 ? get_post( $client_id ) : null;
			}
		);

		if ( ! ( $client instanceof WP_Post ) || 'crm_client' !== $client->post_type ) {
			return array( 'allowed' => false, 'message' => __( 'Client not found.', 'peracrm' ) );
		}

		if ( ! current_user_can( 'edit_post', $client_id ) ) {
			return array( 'allowed' => false, 'message' => __( 'You do not have permission to view this client.', 'peracrm' ) );
		}

		$can_manage_all = current_user_can( 'manage_options' ) || current_user_can( 'peracrm_manage_all_clients' );
		if ( ! $can_manage_all ) {
			$assigned_id = pera_crm_client_view_with_target_blog(
				static function () use ( $client_id ) {
					if ( function_exists( 'peracrm_client_get_assigned_advisor_id' ) ) {
						return (int) peracrm_client_get_assigned_advisor_id( $client_id );
					}

					return 0;
				}
			);

			if ( $assigned_id !== get_current_user_id() ) {
				return array( 'allowed' => false, 'message' => __( 'Access denied. You are not assigned to this client.', 'peracrm' ) );
			}
		}

		return array( 'allowed' => true, 'message' => '' );
	}
}

if ( ! function_exists( 'pera_crm_client_view_notice_message' ) ) {
	function pera_crm_client_view_notice_message( string $notice ): array {
		$map = array(
			'note_added'           => array( 'success', __( 'CRM note added.', 'peracrm' ) ),
			'note_missing'         => array( 'warning', __( 'Please add a note before saving.', 'peracrm' ) ),
			'note_failed'          => array( 'warning', __( 'Unable to save CRM note.', 'peracrm' ) ),
			'reminder_added'       => array( 'success', __( 'CRM reminder created.', 'peracrm' ) ),
			'reminder_done'        => array( 'success', __( 'Reminder marked done.', 'peracrm' ) ),
			'reminder_dismissed'   => array( 'success', __( 'Reminder dismissed.', 'peracrm' ) ),
			'reminder_failed'      => array( 'warning', __( 'Unable to update reminder.', 'peracrm' ) ),
			'profile_saved'        => array( 'success', __( 'Profile saved.', 'peracrm' ) ),
			'profile_failed'       => array( 'warning', __( 'Unable to save profile.', 'peracrm' ) ),
			'advisor_reassigned'   => array( 'success', __( 'Advisor reassigned.', 'peracrm' ) ),
			'deal_saved'           => array( 'success', __( 'Deal saved.', 'peracrm' ) ),
			'deal_deleted'         => array( 'success', __( 'Deal deleted.', 'peracrm' ) ),
			'deal_failed'          => array( 'warning', __( 'Unable to save deal.', 'peracrm' ) ),
			'link_success'         => array( 'success', __( 'User linked successfully.', 'peracrm' ) ),
			'unlink_success'       => array( 'success', __( 'User unlinked successfully.', 'peracrm' ) ),
			'property_linked'      => array( 'success', __( 'Property linked.', 'peracrm' ) ),
			'property_unlinked'    => array( 'success', __( 'Property unlinked.', 'peracrm' ) ),
			'property_link_failed' => array( 'warning', __( 'Unable to link property.', 'peracrm' ) ),
			'converted_to_client'  => array( 'success', __( 'Lead converted to client.', 'peracrm' ) ),
			'convert_failed'       => array( 'warning', __( 'Unable to convert this lead.', 'peracrm' ) ),
			'client_deleted'       => array( 'success', __( 'Client deleted.', 'peracrm' ) ),
			'client_delete_failed' => array( 'warning', __( 'Unable to delete this client.', 'peracrm' ) ),
		);

		return $map[ $notice ] ?? array( '', '' );
	}
}

if ( ! function_exists( 'pera_crm_client_view_url' ) ) {
	function pera_crm_client_view_url( int $client_id, array $args = array() ): string {
		$base = home_url( '/crm/client/' . max( 0, $client_id ) . '/' );

		return empty( $args ) ? $base : add_query_arg( $args, $base );
	}
}

if ( ! function_exists( 'pera_crm_client_view_has_full_timeline_details_access' ) ) {
	function pera_crm_client_view_has_full_timeline_details_access(): bool {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'peracrm_manage_all_clients' ) ) {
			return true;
		}

		if ( function_exists( 'peracrm_admin_user_can_reassign' ) && peracrm_admin_user_can_reassign() ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'pera_crm_client_view_render_enquiry_details' ) ) {
	function pera_crm_client_view_render_enquiry_details( array $payload, bool $full_access = false ): string {
		if ( $full_access && function_exists( 'peracrm_timeline_render_enquiry_details' ) ) {
			return (string) peracrm_timeline_render_enquiry_details( $payload );
		}

		if ( ! function_exists( 'peracrm_timeline_collect_enquiry_fields' ) || ! function_exists( 'peracrm_timeline_enquiry_field_label' ) ) {
			return '';
		}

		$rows   = array();
		$fields = peracrm_timeline_collect_enquiry_fields( $payload );
		$employee_safe_keys = array(
			'message',
			'contact_method',
			'source_page',
			'form_context',
			'form',
			'property_id',
			'property_ids',
			'sr_property_title',
			'sr_property_url',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'referrer',
			'page_url',
		);

		foreach ( array_keys( $fields ) as $field_key ) {
			if ( ! in_array( (string) $field_key, $employee_safe_keys, true ) ) {
				unset( $fields[ $field_key ] );
			}
		}

		$property_title = isset( $payload['sr_property_title'] ) ? trim( (string) $payload['sr_property_title'] ) : '';
		$property_url   = isset( $payload['sr_property_url'] ) ? esc_url_raw( (string) $payload['sr_property_url'] ) : '';
		unset( $fields['sr_property_title'], $fields['sr_property_url'] );

		if ( '' !== $property_title && '' !== $property_url ) {
			$rows[] = '<tr><th>Property</th><td><a href="' . esc_url( $property_url ) . '" target="_blank" rel="noopener">' . esc_html( $property_title ) . '</a></td></tr>';
		}

		$property_ids = array();
		if ( ! empty( $payload['property_ids'] ) && is_array( $payload['property_ids'] ) ) {
			$property_ids = array_values( array_filter( array_map( 'absint', $payload['property_ids'] ) ) );
		}
		if ( ! empty( $property_ids ) ) {
			$rows[] = '<tr><th>Properties count</th><td>' . esc_html( (string) count( $property_ids ) ) . '</td></tr>';
			$rows[] = '<tr><th>Properties</th><td>' . esc_html( implode( ', ', array_map( 'strval', $property_ids ) ) ) . '</td></tr>';
		}

		foreach ( $fields as $key => $value ) {
			$label = peracrm_timeline_enquiry_field_label( (string) $key );
			if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$value_html = '<a href="' . esc_url( (string) $value ) . '" target="_blank" rel="noopener">' . esc_html( (string) $value ) . '</a>';
			} else {
				$value_html = esc_html( (string) $value );
			}
			$rows[] = '<tr><th>' . esc_html( $label ) . '</th><td>' . $value_html . '</td></tr>';
		}

		if ( empty( $rows ) ) {
			return '';
		}

		return '<details class="peracrm-enquiry-details"><summary>View details</summary><div class="peracrm-enquiry-details__body"><table class="peracrm-enquiry-details__table"><tbody>' . implode( '', $rows ) . '</tbody></table></div></details>';
	}
}

if ( ! function_exists( 'pera_crm_client_view_prepare_timeline_items' ) ) {
	function pera_crm_client_view_prepare_timeline_items( array $items ): array {
		$full_details_access = pera_crm_client_view_has_full_timeline_details_access();

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$type = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '';
			$items[ $index ]['type_label'] = function_exists( 'peracrm_timeline_type_label' ) ? peracrm_timeline_type_label( $type ) : ucfirst( $type );
			$items[ $index ]['time'] = function_exists( 'peracrm_timeline_time_display' ) ? peracrm_timeline_time_display( (int) ( $item['ts'] ?? 0 ) ) : array( 'relative' => '', 'title' => '' );
			$items[ $index ]['meta_line'] = function_exists( 'peracrm_timeline_meta_line' ) ? peracrm_timeline_meta_line( (array) ( $item['meta'] ?? array() ) ) : '';

			$payload = is_array( $item['event_payload'] ?? null ) ? (array) $item['event_payload'] : array();
			if ( ! empty( $payload ) ) {
				$details_html = pera_crm_client_view_render_enquiry_details( $payload, $full_details_access );
				if ( '' !== $details_html ) {
					$items[ $index ]['details_html'] = $details_html;
				} elseif ( isset( $items[ $index ]['details_html'] ) ) {
					unset( $items[ $index ]['details_html'] );
				}
			}
		}

		return $items;
	}
}

if ( ! function_exists( 'pera_crm_client_view_timeline_items' ) ) {
	function pera_crm_client_view_timeline_items( int $client_id, string $filter = 'all', int $limit = 50 ): array {
		$filter = sanitize_key( $filter );
		if ( ! in_array( $filter, array( 'all', 'activity', 'notes', 'reminders' ), true ) ) {
			$filter = 'all';
		}

		if ( function_exists( 'peracrm_timeline_get_items' ) ) {
			$items = peracrm_timeline_get_items( $client_id, $limit, $filter );
			return is_array( $items ) ? pera_crm_client_view_prepare_timeline_items( $items ) : array();
		}

		$items = array();

		if ( ( 'all' === $filter || 'notes' === $filter ) && function_exists( 'peracrm_notes_list' ) ) {
			foreach ( (array) peracrm_notes_list( $client_id, $limit, 0 ) as $note ) {
				$author = get_userdata( (int) ( $note['advisor_user_id'] ?? 0 ) );
				$items[] = array(
					'type'   => 'note',
					'title'  => __( 'Note added', 'peracrm' ),
					'detail' => (string) ( $note['note_body'] ?? '' ),
					'ts'     => strtotime( (string) ( $note['created_at'] ?? '' ) ),
					'meta'   => array( 'author' => $author ? $author->display_name : '' ),
				);
			}
		}

		if ( ( 'all' === $filter || 'reminders' === $filter ) && function_exists( 'peracrm_reminders_list_for_client' ) ) {
			$timezone = wp_timezone();
			foreach ( (array) peracrm_reminders_list_for_client( $client_id, $limit, 0, null ) as $reminder ) {
				$due_at = (string) ( $reminder['due_at'] ?? '' );
				$due_ts = function_exists( 'pera_crm_parse_local_mysql_datetime_to_ts' )
					? pera_crm_parse_local_mysql_datetime_to_ts( $due_at, $timezone )
					: 0;

				$items[] = array(
					'type'   => 'reminder',
					'title'  => __( 'Reminder', 'peracrm' ),
					'detail' => (string) ( $reminder['note'] ?? '' ),
					'ts'     => $due_ts,
					'meta'   => array( 'status' => (string) ( $reminder['status'] ?? 'pending' ) ),
				);
			}
		}

		if ( ( 'all' === $filter || 'activity' === $filter ) && function_exists( 'peracrm_activity_list' ) ) {
			foreach ( (array) peracrm_activity_list( $client_id, $limit, 0, null ) as $activity ) {
				$items[] = array(
					'type'   => 'activity',
					'title'  => (string) ( $activity['event_type'] ?? __( 'Activity', 'peracrm' ) ),
					'detail' => '',
					'ts'     => strtotime( (string) ( $activity['created_at'] ?? '' ) ),
					'meta'   => array(),
				);
			}
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return (int) ( $b['ts'] ?? 0 ) <=> (int) ( $a['ts'] ?? 0 );
			}
		);

		return pera_crm_client_view_prepare_timeline_items( array_slice( $items, 0, $limit ) );
	}
}

if ( ! function_exists( 'pera_crm_client_view_bucket_reminders' ) ) {
	/**
	 * Bucket client reminders into today/overdue/upcoming sections.
	 *
	 * @param array<int,array<string,mixed>> $reminders Raw reminders list.
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	function pera_crm_client_view_bucket_reminders( array $reminders ): array {
		$timezone      = wp_timezone();
		$now_mysql     = current_time( 'mysql' );
		$now_ts        = function_exists( 'pera_crm_parse_local_mysql_datetime_to_ts' )
			? pera_crm_parse_local_mysql_datetime_to_ts( $now_mysql, $timezone )
			: strtotime( $now_mysql );
		$now_ts        = $now_ts > 0 ? $now_ts : current_time( 'timestamp' );
		$today_local   = current_datetime();
		$today_start   = $today_local->setTime( 0, 0, 0 )->getTimestamp();
		$today_end     = $today_local->setTime( 23, 59, 59 )->getTimestamp();
		$open_status   = function_exists( 'pera_crm_reminders_open_status' ) ? pera_crm_reminders_open_status() : 'pending';
		$open_statuses = array_values( array_unique( array_filter( array( 'pending', 'open', $open_status ) ) ) );

		$buckets = array(
			'today'    => array(),
			'overdue'  => array(),
			'upcoming' => array(),
		);

		foreach ( $reminders as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status = sanitize_key( (string) ( $row['status'] ?? '' ) );
			if ( ! in_array( $status, $open_statuses, true ) ) {
				continue;
			}

			$due_at = (string) ( $row['due_at'] ?? '' );
			$due_ts = function_exists( 'pera_crm_parse_local_mysql_datetime_to_ts' )
				? pera_crm_parse_local_mysql_datetime_to_ts( $due_at, $timezone )
				: strtotime( $due_at );

			if ( $due_ts <= 0 ) {
				continue;
			}

			$row['due_ts']      = $due_ts;
			$row['due_display'] = function_exists( 'pera_crm_format_datetime_dmy_hm' )
				? pera_crm_format_datetime_dmy_hm( $due_ts, $timezone )
				: wp_date( 'd/m/y H:i', $due_ts, $timezone );

			if ( $due_ts >= $today_start && $due_ts <= $today_end ) {
				$buckets['today'][] = $row;
			} elseif ( $due_ts < $now_ts ) {
				$buckets['overdue'][] = $row;
			} elseif ( $due_ts > $now_ts ) {
				$buckets['upcoming'][] = $row;
			}
		}

		$sort_due_asc = static function ( array $left, array $right ): int {
			return (int) ( $left['due_ts'] ?? 0 ) <=> (int) ( $right['due_ts'] ?? 0 );
		};

		usort( $buckets['today'], $sort_due_asc );
		usort( $buckets['overdue'], $sort_due_asc );
		usort( $buckets['upcoming'], $sort_due_asc );

		return $buckets;
	}
}

if ( ! function_exists( 'pera_crm_client_view_load_data' ) ) {
	function pera_crm_client_view_load_data( int $client_id ): array {
		return (array) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ) {
				$client  = get_post( $client_id );
				$profile = function_exists( 'peracrm_client_get_profile' ) ? peracrm_client_get_profile( $client_id ) : array();
				$party   = function_exists( 'peracrm_party_get' ) ? peracrm_party_get( $client_id ) : array();
				$health  = function_exists( 'peracrm_client_health_get' ) ? peracrm_client_health_get( $client_id ) : array();

				$assigned_id   = function_exists( 'peracrm_client_get_assigned_advisor_id' ) ? (int) peracrm_client_get_assigned_advisor_id( $client_id ) : 0;
				$assigned_user = $assigned_id > 0 ? get_userdata( $assigned_id ) : null;

				$linked_user = null;
				$linked      = get_users(
					array(
						'meta_key'   => 'crm_client_id',
						'meta_value' => $client_id,
						'number'     => 1,
						'orderby'    => 'ID',
						'order'      => 'ASC',
					)
				);
				if ( ! empty( $linked ) && $linked[0] instanceof WP_User ) {
					$linked_user = $linked[0];
				}

				$notes = function_exists( 'peracrm_notes_list' ) ? (array) peracrm_notes_list( $client_id, 20, 0 ) : array();
				$reminders = function_exists( 'peracrm_reminders_list_for_client' ) ? (array) peracrm_reminders_list_for_client( $client_id, 50, 0, null ) : array();
				$activity  = function_exists( 'peracrm_activity_list' ) ? (array) peracrm_activity_list( $client_id, 20, 0, null ) : array();
				$deals     = function_exists( 'peracrm_deals_get_by_party' ) ? (array) peracrm_deals_get_by_party( $client_id ) : array();

				$relation_types  = array( 'favourite', 'enquiry', 'portfolio' );
				$property_groups = array();
				$property_total  = 0;
				if ( function_exists( 'peracrm_client_property_list' ) ) {
					foreach ( $relation_types as $relation ) {
						$list                       = (array) peracrm_client_property_list( $client_id, $relation, 20 );
						$property_groups[ $relation ] = $list;
						$property_total             += count( $list );
					}
				}

				$open_reminders = function_exists( 'peracrm_reminders_count_open_by_client' ) ? (int) peracrm_reminders_count_open_by_client( $client_id ) : 0;
				$overdue        = function_exists( 'peracrm_reminders_count_overdue_by_client' ) ? (int) peracrm_reminders_count_overdue_by_client( $client_id ) : 0;
				$timeline_filter = isset( $_GET['peracrm_timeline'] ) ? sanitize_key( wp_unslash( (string) $_GET['peracrm_timeline'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$timeline = pera_crm_client_view_timeline_items( $client_id, $timeline_filter, 50 );

				$last_activity_ts = isset( $health['last_activity_ts'] ) ? (int) $health['last_activity_ts'] : 0;
				$last_activity    = $last_activity_ts > 0 ? human_time_diff( $last_activity_ts, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'peracrm' ) : '—';

				return array(
					'client'           => $client,
					'profile'          => is_array( $profile ) ? $profile : array(),
					'party'            => is_array( $party ) ? $party : array(),
					'health'           => is_array( $health ) ? $health : array(),
					'assigned_id'      => $assigned_id,
					'assigned_user'    => $assigned_user,
					'linked_user'      => $linked_user,
					'notes'            => $notes,
					'reminders'        => $reminders,
					'activity'         => $activity,
					'deals'            => $deals,
					'property_groups'  => $property_groups,
					'property_total'   => $property_total,
					'open_reminders'   => $open_reminders,
					'overdue_reminders'=> $overdue,
					'timeline_filter'  => $timeline_filter,
					'timeline'         => $timeline,
					'last_activity'    => $last_activity,
					'derived_type'     => function_exists( 'peracrm_party_get_derived_type' ) ? peracrm_party_get_derived_type( $client_id ) : 'lead',
					'client_type_options' => function_exists( 'peracrm_client_type_options' ) ? (array) peracrm_client_type_options() : array( 'citizenship' => 'Citizenship', 'investor' => 'Investor', 'lifestyle' => 'Lifestyle', 'seller' => 'Seller', 'landlord' => 'Landlord', 'agent' => 'Agent' ),
				);
			}
		);
	}
}

if ( ! function_exists( 'pera_crm_client_view_decode_payload' ) ) {
	function pera_crm_client_view_decode_payload( $raw ): array {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}
}

if ( ! function_exists( 'pera_crm_client_view_property_project_name' ) ) {
	function pera_crm_client_view_property_project_name( int $property_id ): string {
		if ( $property_id <= 0 ) {
			return '';
		}

		$project_name = '';
		if ( function_exists( 'get_field' ) ) {
			$project_name = (string) get_field( 'project_name', $property_id );
		}

		if ( '' === $project_name ) {
			$project_name = (string) get_post_meta( $property_id, 'project_name', true );
		}

		return '' !== $project_name ? $project_name : (string) get_the_title( $property_id );
	}
}

if ( ! function_exists( 'pera_crm_client_view_source_pills' ) ) {
	function pera_crm_client_view_source_pills( int $client_id, array $activity = array() ): array {
		$source_key = sanitize_key( (string) get_post_meta( $client_id, 'crm_source', true ) );
		$pills      = array();

		if ( false !== strpos( $source_key, 'instagram' ) ) {
			$pills[] = __( 'Instagram', 'peracrm' );
			return $pills;
		}

		if ( false !== strpos( $source_key, 'meta' ) ) {
			$pills[] = __( 'Meta Ads', 'peracrm' );
			$pills[] = __( 'Ad: (TBD)', 'peracrm' );
			return $pills;
		}

		$is_website = in_array( $source_key, array( 'website', 'website_form' ), true ) || '' === $source_key || false !== strpos( $source_key, 'form' );
		if ( ! $is_website ) {
			$pills[] = '' !== $source_key ? ucwords( str_replace( '_', ' ', $source_key ) ) : __( 'Website', 'peracrm' );
			return $pills;
		}

		$pills[] = __( 'Website', 'peracrm' );

		$property_name = '';
		if ( function_exists( 'peracrm_client_property_list' ) ) {
			$enquiry_links = (array) peracrm_client_property_list( $client_id, 'enquiry', 1 );
			if ( ! empty( $enquiry_links[0]['property_id'] ) ) {
				$property_name = pera_crm_client_view_property_project_name( (int) $enquiry_links[0]['property_id'] );
			}
		}

		$form_hint = '';
		foreach ( $activity as $row ) {
			if ( ! is_array( $row ) || 'enquiry' !== (string) ( $row['event_type'] ?? '' ) ) {
				continue;
			}

			$payload   = pera_crm_client_view_decode_payload( $row['event_payload'] ?? array() );
			$form_blob = strtolower( trim( implode( ' ', array_filter( array(
				(string) ( $payload['form'] ?? '' ),
				(string) ( $payload['form_name'] ?? '' ),
				(string) ( $payload['form_context'] ?? '' ),
				(string) ( $payload['form_id'] ?? '' ),
			) ) ) ) );

			if ( '' === $property_name && ! empty( $payload['property_id'] ) ) {
				$property_name = pera_crm_client_view_property_project_name( (int) $payload['property_id'] );
			}

			if ( '' !== $form_blob ) {
				$form_hint = $form_blob;
				break;
			}
		}

		if ( '' !== $property_name ) {
			$pills[] = $property_name;
		} elseif ( false !== strpos( $form_hint, 'rent' ) ) {
			$pills[] = __( 'Rent', 'peracrm' );
		} elseif ( false !== strpos( $form_hint, 'sell' ) ) {
			$pills[] = __( 'Sell', 'peracrm' );
		} elseif ( false !== strpos( $form_hint, 'citizen' ) ) {
			$pills[] = __( 'Citizenship', 'peracrm' );
		}

		return $pills;
	}
}

if ( ! function_exists( 'pera_crm_client_view_handle_property_actions' ) ) {
	function pera_crm_client_view_handle_property_actions(): void {
		if ( is_admin() || ! pera_is_crm_route() || 'client' !== sanitize_key( (string) get_query_var( 'pera_crm_view', '' ) ) ) {
			return;
		}

		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		$action = isset( $_POST['pera_crm_property_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['pera_crm_property_action'] ) ) : '';
		if ( ! in_array( $action, array( 'link', 'unlink' ), true ) ) {
			return;
		}

		$client_id = isset( $_POST['peracrm_client_id'] ) ? absint( wp_unslash( (string) $_POST['peracrm_client_id'] ) ) : 0;
		$redirect  = pera_crm_client_view_url( $client_id );
		if ( $client_id <= 0 ) {
			wp_safe_redirect( add_query_arg( 'peracrm_notice', 'property_link_failed', $redirect ) );
			exit;
		}

		$access = pera_crm_client_view_access_state( $client_id );
		if ( empty( $access['allowed'] ) ) {
			wp_safe_redirect( add_query_arg( 'peracrm_notice', 'property_link_failed', $redirect ) );
			exit;
		}

		$nonce = isset( $_POST['pera_crm_property_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['pera_crm_property_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_property_action' ) ) {
			wp_safe_redirect( add_query_arg( 'peracrm_notice', 'property_link_failed', $redirect ) );
			exit;
		}

		$property_id   = isset( $_POST['property_id'] ) ? absint( wp_unslash( (string) $_POST['property_id'] ) ) : 0;
		$relation_type = isset( $_POST['relation_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['relation_type'] ) ) : '';
		if ( 'link' === $action ) {
			$relation_type = 'portfolio';
		}
		$ok            = false;

		if ( $property_id > 0 ) {
			$ok = (bool) pera_crm_client_view_with_target_blog(
				static function () use ( $action, $client_id, $property_id, $relation_type ) {
					if ( 'unlink' === $action && function_exists( 'peracrm_client_property_unlink' ) ) {
						return peracrm_client_property_unlink( $client_id, $property_id, $relation_type );
					}

					if ( 'link' === $action && function_exists( 'peracrm_client_property_link' ) ) {
						return peracrm_client_property_link( $client_id, $property_id, $relation_type );
					}

					return false;
				}
			);
		}

		$notice = $ok ? ( 'unlink' === $action ? 'property_unlinked' : 'property_linked' ) : 'property_link_failed';
		wp_safe_redirect( add_query_arg( 'peracrm_notice', $notice, $redirect ) );
		exit;
	}
}
add_action( 'template_redirect', 'pera_crm_client_view_handle_property_actions', 20 );

if ( ! function_exists( 'pera_crm_client_view_parse_property_ids_csv' ) ) {
	/**
	 * Parse comma-separated property IDs.
	 *
	 * @param string $raw Raw property IDs string.
	 * @return int[]
	 */
	function pera_crm_client_view_parse_property_ids_csv( string $raw ): array {
		$chunks = preg_split( '/[\s,]+/', $raw );
		if ( ! is_array( $chunks ) ) {
			return array();
		}

		$ids  = array();
		$seen = array();

		foreach ( $chunks as $chunk ) {
			$id = absint( (string) $chunk );
			if ( $id <= 0 || isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;
			$ids[]       = $id;
		}

		return $ids;
	}
}


if ( ! function_exists( 'pera_crm_client_view_theme_portfolio_property_meta_key' ) ) {
	function pera_crm_client_view_theme_portfolio_property_meta_key(): string {
		return '_peracrm_theme_portfolio_property_ids';
	}
}

if ( ! function_exists( 'pera_crm_client_view_theme_portfolio_url_state' ) ) {
	function pera_crm_client_view_theme_portfolio_url_state( int $client_id ): array {
		return (array) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ): array {
				return array(
					'url'        => esc_url_raw( (string) get_post_meta( $client_id, '_peracrm_theme_portfolio_url', true ) ),
					'token'      => sanitize_text_field( (string) get_post_meta( $client_id, '_peracrm_theme_portfolio_token', true ) ),
					'updated_at' => (int) get_post_meta( $client_id, '_peracrm_theme_portfolio_updated_at', true ),
				);
			}
		);
	}
}

if ( ! function_exists( 'pera_crm_client_view_get_theme_portfolio_property_ids' ) ) {
	function pera_crm_client_view_get_theme_portfolio_property_ids( int $client_id ): array {
		return (array) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ): array {
				$raw  = get_post_meta( $client_id, pera_crm_client_view_theme_portfolio_property_meta_key(), true );
				$rows = is_array( $raw ) ? $raw : array();
				$ids  = array();
				$seen = array();

				foreach ( $rows as $value ) {
					$property_id = absint( $value );
					if ( $property_id <= 0 || isset( $seen[ $property_id ] ) ) {
						continue;
					}

					$post = get_post( $property_id );
					if ( ! ( $post instanceof WP_Post ) || 'property' !== $post->post_type || 'publish' !== $post->post_status ) {
						continue;
					}

					$seen[ $property_id ] = true;
					$ids[]                = $property_id;
				}

				return $ids;
			}
		);
	}
}

if ( ! function_exists( 'pera_crm_client_view_clear_theme_portfolio_url_state' ) ) {
	function pera_crm_client_view_clear_theme_portfolio_url_state( int $client_id ): void {
		delete_post_meta( $client_id, '_peracrm_theme_portfolio_url' );
		delete_post_meta( $client_id, '_peracrm_theme_portfolio_token' );
		delete_post_meta( $client_id, '_peracrm_theme_portfolio_created_at' );
		delete_post_meta( $client_id, '_peracrm_theme_portfolio_updated_at' );
	}
}

if ( ! function_exists( 'pera_crm_client_view_save_theme_portfolio_property_ids' ) ) {
	function pera_crm_client_view_save_theme_portfolio_property_ids( int $client_id, array $property_ids ): void {
		$clean = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $property_ids )
				)
			)
		);

		pera_crm_client_view_with_target_blog(
			static function () use ( $client_id, $clean ): void {
				if ( empty( $clean ) ) {
					delete_post_meta( $client_id, pera_crm_client_view_theme_portfolio_property_meta_key() );
					pera_crm_client_view_clear_theme_portfolio_url_state( $client_id );
					return;
				}

				update_post_meta( $client_id, pera_crm_client_view_theme_portfolio_property_meta_key(), $clean );
			}
		);
	}
}

if ( ! function_exists( 'pera_crm_client_view_theme_offer_rows_for_property' ) ) {
	function pera_crm_client_view_theme_offer_rows_for_property( int $property_id ): array {
		$rows = array();
		if ( function_exists( 'pera_latest_offers_get_rows' ) ) {
			$rows = (array) pera_latest_offers_get_rows( $property_id );
		} else {
			$rows = (array) get_post_meta( $property_id, '_pera_latest_offers', true );
		}

		return is_array( $rows ) ? $rows : array();
	}
}

if ( ! function_exists( 'pera_crm_client_view_normalize_theme_offer_row' ) ) {
	function pera_crm_client_view_normalize_theme_offer_row( int $property_id, array $offer_row ): array {
		return array(
			'property_id'        => $property_id,
			'offer_type'         => sanitize_text_field( (string) ( $offer_row['type'] ?? '' ) ),
			'floor'              => sanitize_text_field( (string) ( $offer_row['floor'] ?? '' ) ),
			'net_size'           => sanitize_text_field( (string) ( $offer_row['net_sqm'] ?? '' ) ),
			'gross_size'         => sanitize_text_field( (string) ( $offer_row['gross_sqm'] ?? '' ) ),
			'list_price'         => sanitize_text_field( (string) ( $offer_row['list_price'] ?? '' ) ),
			'cash_price'         => sanitize_text_field( (string) ( $offer_row['cash_price'] ?? '' ) ),
			'notes'              => sanitize_textarea_field( (string) ( $offer_row['notes'] ?? '' ) ),
			'floor_plan_id'      => absint( $offer_row['floor_plan_id'] ?? 0 ),
			'offer_source'       => 'theme_latest_offers',
			'offer_storage_meta' => '_pera_latest_offers',
		);
	}
}

if ( ! function_exists( 'pera_crm_client_view_get_theme_portfolio_preview' ) ) {
	function pera_crm_client_view_get_theme_portfolio_preview( int $client_id ): array {
		return (array) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ): array {
				$property_ids = pera_crm_client_view_get_theme_portfolio_property_ids( $client_id );
				$properties   = array();
				$offers       = array();

				foreach ( $property_ids as $property_id ) {
					$properties[] = array(
						'property_id' => $property_id,
						'label'       => function_exists( 'pera_crm_client_view_property_project_name' )
							? (string) pera_crm_client_view_property_project_name( $property_id )
							: (string) get_the_title( $property_id ),
						'url'         => (string) get_permalink( $property_id ),
					);

					$property_rows = pera_crm_client_view_theme_offer_rows_for_property( $property_id );
					foreach ( $property_rows as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}

						$offers[] = pera_crm_client_view_normalize_theme_offer_row( $property_id, $row );
					}
				}

				return array(
					'property_ids' => $property_ids,
					'properties'   => $properties,
					'offers'       => $offers,
				);
			}
		);
	}
}

if ( ! function_exists( 'pera_crm_client_view_refresh_theme_portfolio_url' ) ) {
	function pera_crm_client_view_refresh_theme_portfolio_url( int $client_id ): array {
		$property_ids = pera_crm_client_view_get_theme_portfolio_property_ids( $client_id );
		if ( empty( $property_ids ) ) {
			pera_crm_client_view_with_target_blog(
				static function () use ( $client_id ): void {
					pera_crm_client_view_clear_theme_portfolio_url_state( $client_id );
				}
			);
			return array();
		}

		$refreshed_at = time();
		$state        = (array) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ): array {
				return array(
					'token'      => sanitize_text_field( (string) get_post_meta( $client_id, '_peracrm_theme_portfolio_token', true ) ),
					'created_at' => (int) get_post_meta( $client_id, '_peracrm_theme_portfolio_created_at', true ),
				);
			}
		);
		$token        = (string) ( $state['token'] ?? '' );
		$created_at   = (int) ( $state['created_at'] ?? 0 );

		if ( '' === $token ) {
			$seed  = wp_json_encode( array( $client_id, $property_ids, $refreshed_at, wp_rand( 1000, 999999 ) ) );
			$token = substr( hash_hmac( 'sha256', (string) $seed, wp_salt( 'auth' ) ), 0, 24 );
		}

		if ( $created_at <= 0 ) {
			$created_at = $refreshed_at;
		}

		$url = trailingslashit( home_url( '/portfolio/' . rawurlencode( $token ) ) );

		pera_crm_client_view_with_target_blog(
			static function () use ( $client_id, $url, $token, $created_at, $refreshed_at ): void {
				update_post_meta( $client_id, '_peracrm_theme_portfolio_url', $url );
				update_post_meta( $client_id, '_peracrm_theme_portfolio_token', $token );
				if ( (int) get_post_meta( $client_id, '_peracrm_theme_portfolio_created_at', true ) <= 0 ) {
					update_post_meta( $client_id, '_peracrm_theme_portfolio_created_at', $created_at );
				}
				update_post_meta( $client_id, '_peracrm_theme_portfolio_updated_at', $refreshed_at );
			}
		);

		return array(
			'url'         => $url,
			'token'       => $token,
			'updated_at'  => $refreshed_at,
			'updated_label'=> wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $refreshed_at ),
			'count'       => count( $property_ids ),
		);
	}
}

if ( ! function_exists( 'pera_crm_client_view_theme_portfolio_access_or_error' ) ) {
	function pera_crm_client_view_theme_portfolio_access_or_error( int $client_id ): void {
		if ( $client_id <= 0 ) {
			pera_crm_ajax_error( 'invalid_client', __( 'Invalid client.', 'peracrm' ), 400 );
		}
		$access = pera_crm_client_view_access_state( $client_id );
		if ( empty( $access['allowed'] ) ) {
			pera_crm_ajax_error( 'access_denied', __( 'Access denied.', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}
	}
}

if ( ! function_exists( 'pera_crm_theme_portfolio_add_property_ajax' ) ) {
	function pera_crm_theme_portfolio_add_property_ajax(): void {
		if ( ! pera_crm_ajax_is_expected_action( 'peracrm_theme_portfolio_add_property' ) ) {
			pera_crm_ajax_error( 'invalid_action', __( 'Invalid action', 'peracrm' ), 400 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_theme_portfolio_add_property' ) ) {
			pera_crm_ajax_error( 'invalid_nonce', __( 'Invalid nonce', 'peracrm' ), 403 );
		}

		$client_id   = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		$property_id = isset( $_POST['property_id'] ) ? absint( wp_unslash( (string) $_POST['property_id'] ) ) : 0;
		pera_crm_client_view_theme_portfolio_access_or_error( $client_id );

		$property_exists = (bool) pera_crm_client_view_with_target_blog(
			static function () use ( $property_id ): bool {
				$post = get_post( $property_id );
				return ( $post instanceof WP_Post ) && 'property' === $post->post_type && 'publish' === $post->post_status;
			}
		);
		if ( ! $property_exists ) {
			pera_crm_ajax_error( 'invalid_property', __( 'Property not found.', 'peracrm' ), 404 );
		}

		$ids   = pera_crm_client_view_get_theme_portfolio_property_ids( $client_id );
		$ids[] = $property_id;
		pera_crm_client_view_save_theme_portfolio_property_ids( $client_id, $ids );
		pera_crm_ajax_success( array( 'code' => 'theme_portfolio_property_added' ) );
	}
}
add_action( 'wp_ajax_peracrm_theme_portfolio_add_property', 'pera_crm_theme_portfolio_add_property_ajax' );

if ( ! function_exists( 'pera_crm_theme_portfolio_remove_property_ajax' ) ) {
	function pera_crm_theme_portfolio_remove_property_ajax(): void {
		if ( ! pera_crm_ajax_is_expected_action( 'peracrm_theme_portfolio_remove_property' ) ) {
			pera_crm_ajax_error( 'invalid_action', __( 'Invalid action', 'peracrm' ), 400 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_theme_portfolio_remove_property' ) ) {
			pera_crm_ajax_error( 'invalid_nonce', __( 'Invalid nonce', 'peracrm' ), 403 );
		}

		$client_id   = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		$property_id = isset( $_POST['property_id'] ) ? absint( wp_unslash( (string) $_POST['property_id'] ) ) : 0;
		pera_crm_client_view_theme_portfolio_access_or_error( $client_id );

		$ids = array_values(
			array_filter(
				pera_crm_client_view_get_theme_portfolio_property_ids( $client_id ),
				static function ( $id ) use ( $property_id ): bool {
					return (int) $id !== $property_id;
				}
			)
		);
		pera_crm_client_view_save_theme_portfolio_property_ids( $client_id, $ids );
		pera_crm_ajax_success( array( 'code' => 'theme_portfolio_property_removed' ) );
	}
}
add_action( 'wp_ajax_peracrm_theme_portfolio_remove_property', 'pera_crm_theme_portfolio_remove_property_ajax' );

if ( ! function_exists( 'pera_crm_refresh_theme_portfolio_url_ajax' ) ) {
	function pera_crm_refresh_theme_portfolio_url_ajax(): void {
		if ( ! pera_crm_ajax_is_expected_action( 'peracrm_refresh_theme_portfolio_url' ) ) {
			pera_crm_ajax_error( 'invalid_action', __( 'Invalid action', 'peracrm' ), 400 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_refresh_theme_portfolio_url' ) ) {
			pera_crm_ajax_error( 'invalid_nonce', __( 'Invalid nonce', 'peracrm' ), 403 );
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		pera_crm_client_view_theme_portfolio_access_or_error( $client_id );

		$state = pera_crm_client_view_refresh_theme_portfolio_url( $client_id );
		if ( empty( $state['url'] ) ) {
			pera_crm_ajax_error( 'theme_portfolio_empty', __( 'Select at least one property first.', 'peracrm' ), 400 );
		}

		pera_crm_ajax_success(
			array(
				'code'          => 'theme_portfolio_url_refreshed',
				'url'           => (string) ( $state['url'] ?? '' ),
				'token'         => (string) ( $state['token'] ?? '' ),
				'updated_at'    => (int) ( $state['updated_at'] ?? 0 ),
				'updated_label' => (string) ( $state['updated_label'] ?? '' ),
				'count'         => (int) ( $state['count'] ?? 0 ),
			)
		);
	}
}
add_action( 'wp_ajax_peracrm_refresh_theme_portfolio_url', 'pera_crm_refresh_theme_portfolio_url_ajax' );

if ( ! function_exists( 'pera_crm_client_view_get_portfolio_property_ids' ) ) {
	/**
	 * Fetch ordered, valid portfolio-linked property IDs for a client.
	 *
	 * @param int $client_id CRM client ID.
	 * @return int[]
	 */
	function pera_crm_client_view_get_portfolio_property_ids( int $client_id ): array {
		return (array) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ): array {
				if ( ! function_exists( 'peracrm_client_property_list' ) ) {
					return array();
				}

				$list = (array) peracrm_client_property_list( $client_id, 'portfolio', 500 );
				$ids  = array();
				$seen = array();

				foreach ( $list as $row ) {
					$property_id = absint( $row['property_id'] ?? 0 );
					if ( $property_id <= 0 || isset( $seen[ $property_id ] ) ) {
						continue;
					}

					$post = get_post( $property_id );
					if ( ! ( $post instanceof WP_Post ) || 'property' !== $post->post_type || 'publish' !== $post->post_status ) {
						continue;
					}

					$seen[ $property_id ] = true;
					$ids[]                = $property_id;
				}

				return $ids;
			}
		);
	}
}

if ( ! function_exists( 'pera_crm_client_view_ensure_portfolio_support' ) ) {
	function pera_crm_client_view_ensure_portfolio_support(): bool {
		if ( function_exists( 'pera_portfolio_token_create_portfolio' ) ) {
			return true;
		}

		$theme_file = trailingslashit( get_stylesheet_directory() ) . 'inc/portfolio-token.php';
		if ( file_exists( $theme_file ) ) {
			require_once $theme_file;
		}

		return function_exists( 'pera_portfolio_token_create_portfolio' );
	}
}

if ( ! function_exists( 'pera_crm_create_portfolio_token_ajax' ) ) {
	/**
	 * Create a token portfolio from CRM client view.
	 */
	function pera_crm_create_portfolio_token_ajax(): void {
		if ( ! pera_crm_ajax_is_expected_action( 'peracrm_create_portfolio_token' ) ) {
			pera_crm_ajax_error( 'invalid_action', __( 'Invalid action', 'peracrm' ), 400 );
		}
		if ( ! is_user_logged_in() ) {
			pera_crm_ajax_error( 'forbidden', __( 'Forbidden', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_create_portfolio_token' ) ) {
			pera_crm_ajax_error( 'invalid_nonce', __( 'Invalid nonce', 'peracrm' ), 403, array( 'user_id' => get_current_user_id(), 'has_nonce' => '' !== $nonce ) );
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		if ( $client_id <= 0 ) {
			pera_crm_ajax_error( 'invalid_client', __( 'Invalid client.', 'peracrm' ), 400 );
		}

		$access = pera_crm_client_view_access_state( $client_id );
		if ( empty( $access['allowed'] ) ) {
			pera_crm_ajax_error( 'access_denied', __( 'Access denied.', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}

		$exists = (bool) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ): bool {
				$client = get_post( $client_id );
				return ( $client instanceof WP_Post ) && 'crm_client' === $client->post_type;
			}
		);
		if ( ! $exists ) {
			pera_crm_ajax_error( 'client_not_found', __( 'Client not found.', 'peracrm' ), 404 );
		}

		$property_ids = pera_crm_client_view_get_portfolio_property_ids( $client_id );
		if ( empty( $property_ids ) ) {
			pera_crm_ajax_error( 'portfolio_empty', __( 'No portfolio-linked properties found for this client.', 'peracrm' ), 400 );
		}

		$expires_raw = isset( $_POST['expiry'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['expiry'] ) ) : '';
		$expires_raw = '' !== $expires_raw ? $expires_raw : '+30 days';
		$expires_at  = strtotime( $expires_raw );
		if ( false === $expires_at || $expires_at <= 0 ) {
			pera_crm_ajax_error( 'invalid_expiry', __( 'Invalid expiry format.', 'peracrm' ), 400 );
		}

		if ( ! pera_crm_client_view_ensure_portfolio_support() ) {
			pera_crm_ajax_error( 'portfolio_create_unavailable', __( 'Portfolio creation is unavailable.', 'peracrm' ), 500 );
		}

		$result = pera_crm_client_view_with_target_blog(
			static function () use ( $property_ids, $client_id, $expires_at ) {
				return pera_portfolio_token_create_portfolio( $property_ids, $client_id, $expires_at );
			}
		);

		if ( is_wp_error( $result ) ) {
			pera_crm_ajax_error( 'portfolio_create_failed', $result->get_error_message(), 400 );
		}

		$portfolio_url   = isset( $result['url'] ) ? esc_url_raw( (string) $result['url'] ) : '';
		$portfolio_token = isset( $result['token'] ) ? sanitize_text_field( (string) $result['token'] ) : '';
		$portfolio_post  = isset( $result['post_id'] ) ? (int) $result['post_id'] : 0;
		$created_at      = time();

		pera_crm_client_view_with_target_blog(
			static function () use ( $client_id, $portfolio_url, $portfolio_token, $portfolio_post, $expires_at, $created_at ): void {
				update_post_meta( $client_id, '_peracrm_portfolio_url', $portfolio_url );
				update_post_meta( $client_id, '_peracrm_portfolio_token', $portfolio_token );
				update_post_meta( $client_id, '_peracrm_portfolio_post_id', $portfolio_post );
				update_post_meta( $client_id, '_peracrm_portfolio_expires_at', (int) $expires_at );
				update_post_meta( $client_id, '_peracrm_portfolio_created_at', (int) $created_at );
			}
		);

		pera_crm_ajax_success(
			array(
				'code'          => 'portfolio_token_created',
				'url'           => $portfolio_url,
				'token'         => $portfolio_token,
				'post_id'       => $portfolio_post,
				'expires_at'    => (int) $expires_at,
				'expires_label' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $expires_at ),
				'count'         => count( $property_ids ),
			)
		);
	}
}
add_action( 'wp_ajax_peracrm_create_portfolio_token', 'pera_crm_create_portfolio_token_ajax' );


if ( ! function_exists( 'pera_crm_update_portfolio_token_ajax' ) ) {
	/**
	 * Update an existing token portfolio from CRM client view.
	 */
	function pera_crm_update_portfolio_token_ajax(): void {
		if ( ! pera_crm_ajax_is_expected_action( 'peracrm_update_portfolio_token' ) ) {
			pera_crm_ajax_error( 'invalid_action', __( 'Invalid action', 'peracrm' ), 400 );
		}
		if ( ! is_user_logged_in() ) {
			pera_crm_ajax_error( 'forbidden', __( 'Forbidden', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_update_portfolio_token' ) ) {
			pera_crm_ajax_error( 'invalid_nonce', __( 'Invalid nonce', 'peracrm' ), 403, array( 'user_id' => get_current_user_id(), 'has_nonce' => '' !== $nonce ) );
		}

		$client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		if ( $client_id <= 0 ) {
			pera_crm_ajax_error( 'invalid_client', __( 'Invalid client.', 'peracrm' ), 400 );
		}

		$access = pera_crm_client_view_access_state( $client_id );
		if ( empty( $access['allowed'] ) ) {
			pera_crm_ajax_error( 'access_denied', __( 'Access denied.', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}

		$property_ids = pera_crm_client_view_get_portfolio_property_ids( $client_id );
		if ( empty( $property_ids ) ) {
			pera_crm_ajax_error( 'portfolio_empty', __( 'No portfolio properties linked.', 'peracrm' ), 400 );
		}

		$portfolio_post_id = isset( $_POST['portfolio_post_id'] ) ? absint( wp_unslash( (string) $_POST['portfolio_post_id'] ) ) : 0;
		$portfolio_state   = (array) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id, $portfolio_post_id, $property_ids ): array {
				$mapped_portfolio_id = (int) get_post_meta( $client_id, '_peracrm_portfolio_post_id', true );
				$candidate_ids       = array_values(
					array_unique(
						array_filter(
							array(
								$portfolio_post_id,
								$mapped_portfolio_id,
							)
						)
					)
				);

				$resolved_post_id = 0;
				foreach ( $candidate_ids as $candidate_id ) {
					$post = get_post( (int) $candidate_id );
					if ( ! ( $post instanceof WP_Post ) || 'portfolio' !== $post->post_type ) {
						continue;
					}

					$portfolio_client_id = (int) get_post_meta( (int) $candidate_id, '_portfolio_client_id', true );
					$belongs             = ( $portfolio_client_id === $client_id ) || ( $portfolio_client_id <= 0 && $mapped_portfolio_id === (int) $candidate_id );
					if ( ! $belongs ) {
						continue;
					}

					$resolved_post_id = (int) $candidate_id;
					break;
				}

				if ( $resolved_post_id <= 0 ) {
					return array( 'valid' => false );
				}

				$post = get_post( $resolved_post_id );
				if ( ! ( $post instanceof WP_Post ) || 'portfolio' !== $post->post_type ) {
					return array( 'valid' => false );
				}

				update_post_meta( $resolved_post_id, '_portfolio_property_ids', $property_ids );
				update_post_meta( $resolved_post_id, '_portfolio_updated_at', time() );
				wp_update_post(
					array(
						'ID' => $resolved_post_id,
					)
				);
				clean_post_cache( $resolved_post_id );

				$token      = sanitize_text_field( (string) get_post_meta( $resolved_post_id, '_portfolio_token', true ) );
				$expires_at = (int) get_post_meta( $resolved_post_id, '_portfolio_expires_at', true );
				if ( '' === $token ) {
					return array( 'valid' => false );
				}

				$url = trailingslashit( home_url( '/portfolio/' . $token ) );

				update_post_meta( $client_id, '_peracrm_portfolio_url', $url );
				update_post_meta( $client_id, '_peracrm_portfolio_token', $token );
				update_post_meta( $client_id, '_peracrm_portfolio_post_id', $resolved_post_id );
				update_post_meta( $client_id, '_peracrm_portfolio_expires_at', $expires_at );
				if ( (int) get_post_meta( $client_id, '_peracrm_portfolio_created_at', true ) <= 0 ) {
					update_post_meta( $client_id, '_peracrm_portfolio_created_at', time() );
				}
				update_post_meta( $client_id, '_peracrm_portfolio_updated_at', time() );

				return array(
					'valid'      => true,
					'url'        => $url,
					'token'      => $token,
					'post_id'    => $resolved_post_id,
					'expires_at' => $expires_at,
				);
			}
		);

		if ( empty( $portfolio_state['valid'] ) ) {
			if ( ! pera_crm_client_view_ensure_portfolio_support() ) {
				pera_crm_ajax_error( 'portfolio_create_unavailable', __( 'Portfolio creation is unavailable.', 'peracrm' ), 500 );
			}

			$expires_at = strtotime( '+30 days' );
			$result     = pera_crm_client_view_with_target_blog(
				static function () use ( $property_ids, $client_id, $expires_at ) {
					return pera_portfolio_token_create_portfolio( $property_ids, $client_id, $expires_at );
				}
			);
			if ( is_wp_error( $result ) ) {
				pera_crm_ajax_error( 'portfolio_create_failed', $result->get_error_message(), 400 );
			}

			$created_at      = time();
			$portfolio_state = array(
				'valid'      => true,
				'url'        => isset( $result['url'] ) ? esc_url_raw( (string) $result['url'] ) : '',
				'token'      => isset( $result['token'] ) ? sanitize_text_field( (string) $result['token'] ) : '',
				'post_id'    => isset( $result['post_id'] ) ? (int) $result['post_id'] : 0,
				'expires_at' => (int) $expires_at,
			);

			pera_crm_client_view_with_target_blog(
				static function () use ( $client_id, $portfolio_state, $expires_at, $created_at ): void {
					update_post_meta( $client_id, '_peracrm_portfolio_url', $portfolio_state['url'] );
					update_post_meta( $client_id, '_peracrm_portfolio_token', $portfolio_state['token'] );
					update_post_meta( $client_id, '_peracrm_portfolio_post_id', (int) $portfolio_state['post_id'] );
					update_post_meta( $client_id, '_peracrm_portfolio_expires_at', (int) $expires_at );
					update_post_meta( $client_id, '_peracrm_portfolio_created_at', (int) $created_at );
					update_post_meta( $client_id, '_peracrm_portfolio_updated_at', (int) $created_at );
				}
			);
		}

		$expires_at = (int) ( $portfolio_state['expires_at'] ?? 0 );
		pera_crm_ajax_success(
			array(
				'code'          => 'portfolio_token_updated',
				'url'           => (string) ( $portfolio_state['url'] ?? '' ),
				'token'         => (string) ( $portfolio_state['token'] ?? '' ),
				'post_id'       => (int) ( $portfolio_state['post_id'] ?? 0 ),
				'expires_at'    => $expires_at,
				'expires_label' => $expires_at > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_at ) : '',
			)
		);
	}
}
add_action( 'wp_ajax_peracrm_update_portfolio_token', 'pera_crm_update_portfolio_token_ajax' );


if ( ! function_exists( 'pera_crm_upload_portfolio_floor_plan_ajax' ) ) {
	/**
	 * Upload a portfolio floor plan JPEG and return attachment details.
	 */
	function pera_crm_upload_portfolio_floor_plan_ajax(): void {
		if ( ! pera_crm_ajax_is_expected_action( 'pera_crm_upload_portfolio_floor_plan' ) ) {
			pera_crm_ajax_error( 'invalid_action', __( 'Invalid action', 'peracrm' ), 400 );
		}
		if ( ! is_user_logged_in() ) {
			pera_crm_ajax_error( 'forbidden', __( 'Forbidden', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_upload_portfolio_floor_plan' ) ) {
			pera_crm_ajax_error( 'invalid_nonce', __( 'Invalid nonce', 'peracrm' ), 403, array( 'user_id' => get_current_user_id(), 'has_nonce' => '' !== $nonce ) );
		}

		$client_id   = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		$property_id = isset( $_POST['property_id'] ) ? absint( wp_unslash( (string) $_POST['property_id'] ) ) : 0;

		if ( $client_id <= 0 || $property_id <= 0 || ! isset( $_FILES['floor_plan'] ) || ! is_array( $_FILES['floor_plan'] ) ) {
			pera_crm_ajax_error( 'invalid_input', __( 'Invalid input.', 'peracrm' ), 400 );
		}

		$access   = pera_crm_client_view_access_state( $client_id );
		$can_view = isset( $access['can_view'] ) ? ! empty( $access['can_view'] ) : ! empty( $access['allowed'] );
		$can_edit = isset( $access['can_edit'] ) ? ! empty( $access['can_edit'] ) : $can_view;
		if ( ! $can_view || ! $can_edit ) {
			pera_crm_ajax_error( 'access_denied', __( 'Access denied.', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}

		$relation_row = pera_crm_client_view_with_target_blog(
			static function () use ( $client_id, $property_id ) {
				if ( function_exists( 'peracrm_client_property_get_row' ) ) {
					return peracrm_client_property_get_row( $client_id, $property_id, 'portfolio' );
				}

				if ( ! function_exists( 'peracrm_client_property_list' ) ) {
					return null;
				}

				$rows = (array) peracrm_client_property_list( $client_id, 'portfolio', 500 );
				foreach ( $rows as $row ) {
					if ( (int) ( $row['property_id'] ?? 0 ) === $property_id ) {
						return $row;
					}
				}

				return null;
			}
		);
		if ( ! is_array( $relation_row ) ) {
			pera_crm_ajax_error( 'portfolio_relation_not_found', __( 'Portfolio relation not found for this property.', 'peracrm' ), 404 );
		}

		$upload = $_FILES['floor_plan'];
		if ( empty( $upload['name'] ) || ! isset( $upload['error'] ) || (int) $upload['error'] !== UPLOAD_ERR_OK ) {
			pera_crm_ajax_error( 'upload_failed', __( 'Floor plan upload failed.', 'peracrm' ), 400 );
		}

		$filename   = isset( $upload['name'] ) ? (string) $upload['name'] : '';
		$tmp_name   = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
		$file_check = wp_check_filetype_and_ext( $tmp_name, $filename );
		$ext        = isset( $file_check['ext'] ) ? strtolower( (string) $file_check['ext'] ) : '';
		$type       = isset( $file_check['type'] ) ? strtolower( (string) $file_check['type'] ) : '';

		if ( ! in_array( $ext, array( 'jpg', 'jpeg' ), true ) || ! in_array( $type, array( 'image/jpeg', 'image/jpg' ), true ) ) {
			pera_crm_ajax_error( 'invalid_file_type', __( 'Floor plan must be a JPG/JPEG file.', 'peracrm' ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment = media_handle_upload( 'floor_plan', 0 );
		if ( is_wp_error( $attachment ) ) {
			pera_crm_ajax_error( 'upload_store_failed', $attachment->get_error_message(), 400 );
		}

		$attachment_id = (int) $attachment;
		$url           = (string) wp_get_attachment_url( $attachment_id );

		pera_crm_ajax_success(
			array(
				'code'          => 'upload_ok',
				'attachment_id' => $attachment_id,
				'url'           => $url,
			)
		);
	}
}
add_action( 'wp_ajax_pera_crm_upload_portfolio_floor_plan', 'pera_crm_upload_portfolio_floor_plan_ajax' );

if ( ! function_exists( 'pera_crm_client_view_normalize_decimal_input' ) ) {
	/**
	 * Normalize decimal user input for DB writes.
	 *
	 * @param mixed $value Raw input.
	 * @return string|null
	 */
	function pera_crm_client_view_normalize_decimal_input( $value ) {
		$raw = is_scalar( $value ) ? (string) $value : '';
		$raw = trim( str_replace( ' ', '', $raw ) );
		if ( false !== strpos( $raw, ',' ) ) {
			return null;
		}

		if ( '' === $raw ) {
			return null;
		}

		if ( ! preg_match( '/^\d+(?:\.\d+)?$/', $raw ) ) {
			return null;
		}

		$number = (float) $raw;

		return rtrim( rtrim( sprintf( '%.2f', $number ), '0' ), '.' );
	}
}

if ( ! function_exists( 'pera_crm_save_portfolio_property_fields_ajax' ) ) {
	/**
	 * Save advisor-editable portfolio fields for a linked property.
	 */
	function pera_crm_save_portfolio_property_fields_ajax(): void {
		if ( ! pera_crm_ajax_is_expected_action( 'pera_crm_save_portfolio_property_fields' ) ) {
			pera_crm_ajax_error( 'invalid_action', __( 'Invalid action', 'peracrm' ), 400 );
		}
		if ( ! is_user_logged_in() ) {
			pera_crm_ajax_error( 'forbidden', __( 'Forbidden', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_save_portfolio_property_fields' ) ) {
			pera_crm_ajax_error( 'invalid_nonce', __( 'Invalid nonce', 'peracrm' ), 403, array( 'user_id' => get_current_user_id(), 'has_nonce' => '' !== $nonce ) );
		}

		$client_id   = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		$property_id = isset( $_POST['property_id'] ) ? absint( wp_unslash( (string) $_POST['property_id'] ) ) : 0;
		$raw_fields   = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? (array) wp_unslash( $_POST['fields'] ) : array();

		if ( $client_id <= 0 || $property_id <= 0 || empty( $raw_fields ) ) {
			pera_crm_ajax_error( 'invalid_input', __( 'Invalid input.', 'peracrm' ), 400 );
		}

		$access = pera_crm_client_view_access_state( $client_id );
		$can_view = isset( $access['can_view'] ) ? ! empty( $access['can_view'] ) : ! empty( $access['allowed'] );
		$can_edit = isset( $access['can_edit'] ) ? ! empty( $access['can_edit'] ) : $can_view;
		if ( ! $can_view || ! $can_edit ) {
			pera_crm_ajax_error( 'access_denied', __( 'Access denied.', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}

		$exists = (bool) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ) {
				$client = get_post( $client_id );
				return ( $client instanceof WP_Post ) && 'crm_client' === $client->post_type;
			}
		);
		if ( ! $exists ) {
			pera_crm_ajax_error( 'client_not_found', __( 'Client not found.', 'peracrm' ), 404 );
		}

		$relation_row = pera_crm_client_view_with_target_blog(
			static function () use ( $client_id, $property_id ) {
				if ( function_exists( 'peracrm_client_property_get_row' ) ) {
					return peracrm_client_property_get_row( $client_id, $property_id, 'portfolio' );
				}

				if ( ! function_exists( 'peracrm_client_property_list' ) ) {
					return null;
				}

				$rows = (array) peracrm_client_property_list( $client_id, 'portfolio', 500 );
				foreach ( $rows as $row ) {
					if ( (int) ( $row['property_id'] ?? 0 ) === $property_id ) {
						return $row;
					}
				}

				return null;
			}
		);
		if ( ! is_array( $relation_row ) ) {
			pera_crm_ajax_error( 'portfolio_relation_not_found', __( 'Portfolio relation not found for this property.', 'peracrm' ), 404 );
		}

		$allowed = array( 'unit_type', 'floor_number', 'net_size', 'gross_size', 'list_price', 'cash_price', 'notes', 'floor_plan_attachment_id' );
		$sanitized = array();

		$floor_plan_url = '';

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $raw_fields ) ) {
				continue;
			}

			if ( 'floor_plan_attachment_id' === $key ) {
				$value = absint( $raw_fields[ $key ] );
				$sanitized[ $key ] = $value > 0 ? $value : null;
				continue;
			}

			if ( 'unit_type' === $key || 'floor_number' === $key ) {
				$value = sanitize_text_field( (string) $raw_fields[ $key ] );
				$sanitized[ $key ] = '' === $value ? null : $value;
				continue;
			}

			if ( 'notes' === $key ) {
				$value = sanitize_textarea_field( (string) $raw_fields[ $key ] );
				$value = trim( $value );
				if ( function_exists( 'mb_substr' ) ) {
					$value = mb_substr( $value, 0, 500 );
				} else {
					$value = substr( $value, 0, 500 );
				}
				$sanitized[ $key ] = '' === $value ? null : $value;
				continue;
			}

			$normalized = pera_crm_client_view_normalize_decimal_input( $raw_fields[ $key ] );
			if ( null === $normalized ) {
				$candidate = is_scalar( $raw_fields[ $key ] ) ? trim( (string) $raw_fields[ $key ] ) : '';
				if ( '' !== $candidate ) {
					pera_crm_ajax_error( 'invalid_field_value', sprintf( __( 'Invalid value for %s.', 'peracrm' ), $key ), 400 );
				}
			}
			$sanitized[ $key ] = $normalized;
		}

		if ( empty( $sanitized ) ) {
			pera_crm_ajax_error( 'no_valid_fields', __( 'No valid fields were provided.', 'peracrm' ), 400 );
		}

		$updated = (bool) pera_crm_client_view_with_target_blog(
			static function () use ( $client_id, $property_id, $sanitized ) {
				if ( ! function_exists( 'peracrm_client_property_update_portfolio_fields' ) ) {
					return false;
				}

				return peracrm_client_property_update_portfolio_fields( $client_id, $property_id, $sanitized );
			}
		);

		if ( ! $updated ) {
			pera_crm_ajax_error( 'save_failed', __( 'Unable to save portfolio fields.', 'peracrm' ), 400 );
		}

		$floor_plan_attachment_id = isset( $sanitized['floor_plan_attachment_id'] ) ? (int) $sanitized['floor_plan_attachment_id'] : (int) ( $relation_row['floor_plan_attachment_id'] ?? 0 );

		if ( $floor_plan_attachment_id > 0 && '' === $floor_plan_url ) {
			$floor_plan_url = (string) wp_get_attachment_url( $floor_plan_attachment_id );
		}

		pera_crm_ajax_success(
			array(
				'code'                      => 'portfolio_fields_saved',
				'client_id'                 => $client_id,
				'property_id'               => $property_id,
				'fields'                    => $sanitized,
				'floor_plan_attachment_id'  => $floor_plan_attachment_id,
				'floor_plan_url'            => $floor_plan_url,
			)
		);
	}
}
add_action( 'wp_ajax_pera_crm_save_portfolio_property_fields', 'pera_crm_save_portfolio_property_fields_ajax' );

if ( ! function_exists( 'pera_crm_property_search_ajax' ) ) {
	function pera_crm_property_search_ajax(): void {
		if ( ! pera_crm_ajax_is_expected_action( 'pera_crm_property_search' ) ) {
			pera_crm_ajax_error( 'invalid_action', __( 'Invalid action', 'peracrm' ), 400 );
		}
		if ( ! is_user_logged_in() || ! pera_crm_client_view_can_manage() ) {
			pera_crm_ajax_error( 'forbidden', __( 'Forbidden', 'peracrm' ), 403, array( 'user_id' => get_current_user_id() ) );
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_crm_property_search' ) ) {
			pera_crm_ajax_error( 'invalid_nonce', __( 'Invalid nonce', 'peracrm' ), 403, array( 'user_id' => get_current_user_id(), 'has_nonce' => '' !== $nonce ) );
		}

		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			pera_crm_ajax_success( array( 'code' => 'search_empty', 'items' => array() ) );
		}

		$items = pera_crm_client_view_with_target_blog(
			static function () use ( $term ): array {
				$base_args = array(
					'post_type'           => array( 'property', 'bodrum-property' ),
					'post_status'         => 'publish',
					'posts_per_page'      => 10,
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'fields'              => 'ids',
				);

				$meta_query = new WP_Query(
					$base_args + array(
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'     => 'project_name',
								'value'   => $term,
								'compare' => 'LIKE',
							),
						),
					)
				);

				$title_query = new WP_Query(
					$base_args + array(
						's' => $term,
					)
				);

				$ids = array_values( array_unique( array_merge( array_map( 'intval', (array) $meta_query->posts ), array_map( 'intval', (array) $title_query->posts ) ) ) );
				$ids = array_slice( $ids, 0, 10 );

				$results = array();
				foreach ( $ids as $property_id ) {
					$project_name  = pera_crm_client_view_property_project_name( $property_id );
					$district_list = wp_get_post_terms( $property_id, 'district', array( 'fields' => 'names' ) );
					$results[]     = array(
						'property_id'  => $property_id,
						'project_name' => $project_name,
						'district'     => ! empty( $district_list ) ? (string) $district_list[0] : '',
					);
				}

				return $results;
			}
		);

		pera_crm_ajax_success( array( 'code' => 'search_ok', 'items' => $items ) );
	}
}
add_action( 'wp_ajax_pera_crm_property_search', 'pera_crm_property_search_ajax' );

if ( ! function_exists( 'pera_crm_client_view_render_full_html' ) ) {
	function pera_crm_client_view_render_full_html( int $client_id ): string {
		$template = function_exists( 'peracrm_frontend_view_path' ) ? peracrm_frontend_view_path( 'pages/crm-client.php' ) : '';
		if ( '' === $template || ! file_exists( $template ) ) {
			return '';
		}

		$original_client_qv = get_query_var( 'client_id', null );
		$original_pera_client_qv = get_query_var( 'pera_crm_client_id', null );
		$original_view_qv   = get_query_var( 'pera_crm_view', null );
		set_query_var( 'client_id', $client_id );
		set_query_var( 'pera_crm_client_id', $client_id );
		set_query_var( 'pera_crm_view', 'client' );

		ob_start();
		include $template;
		$html = (string) ob_get_clean();

		set_query_var( 'client_id', $original_client_qv );
		set_query_var( 'pera_crm_client_id', $original_pera_client_qv );
		set_query_var( 'pera_crm_view', $original_view_qv );

		return $html;
	}
}

if ( ! function_exists( 'pera_crm_client_view_xpath_literal' ) ) {
	function pera_crm_client_view_xpath_literal( string $value ): string {
		if ( false === strpos( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		if ( false === strpos( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		$parts = explode( '"', $value );
		$safe  = array();
		foreach ( $parts as $index => $part ) {
			if ( '' !== $part ) {
				$safe[] = '"' . $part . '"';
			}
			if ( $index !== count( $parts ) - 1 ) {
				$safe[] = "'\"'";
			}
		}

		return 'concat(' . implode( ',', $safe ) . ')';
	}
}

if ( ! function_exists( 'pera_crm_client_view_extract_panel_html' ) ) {
	function pera_crm_client_view_extract_panel_html( string $markup, string $panel ): string {
		if ( '' === $markup || '' === $panel ) {
			return '';
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return '';
		}

		$panel = sanitize_key( $panel );
		if ( '' === $panel ) {
			return '';
		}

		$allowed_panels = array( 'profile', 'status', 'notes', 'reminders', 'properties', 'deals', 'advisor' );
		if ( ! in_array( $panel, $allowed_panels, true ) ) {
			return '';
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $markup );
		libxml_clear_errors();
		if ( false === $loaded ) {
			return '';
		}
		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//*[@data-crm-panel=' . pera_crm_client_view_xpath_literal( $panel ) . ']' );
		if ( ! $nodes || $nodes->length < 1 ) {
			return '';
		}

		$node = $nodes->item( 0 );
		return $node ? (string) $dom->saveHTML( $node ) : '';
	}
}

if ( ! function_exists( 'pera_crm_client_action_ajax_json' ) ) {
	function pera_crm_client_action_ajax_json( bool $ok, string $message, int $client_id, string $panel, int $status = 200 ): void {
		$markup = pera_crm_client_view_render_full_html( $client_id );
		$panel_html = pera_crm_client_view_extract_panel_html( $markup, $panel );
		$render_failed = ( '' === $panel_html );
		if ( $render_failed && '' === $message ) {
			$message = $ok ? __( 'Saved. Please refresh to view the latest panel.', 'peracrm' ) : __( 'Unable to refresh panel content.', 'peracrm' );
		}
		if ( $ok ) {
			wp_send_json_success(
				array(
					'ok' => true,
					'message' => $message,
					'panel' => $panel,
					'panel_html' => $panel_html,
					'render_failed' => $render_failed,
				),
				$status
			);
		}

		wp_send_json_error(
			array(
				'ok' => false,
				'message' => $message,
				'panel' => $panel,
				'panel_html' => $panel_html,
				'render_failed' => $render_failed,
			),
			$status
		);
	}
}

if ( ! function_exists( 'pera_crm_client_action_ajax' ) ) {
	function pera_crm_client_action_ajax(): void {
		if ( ! is_user_logged_in() || ! function_exists( 'pera_crm_client_view_access_state' ) ) {
			wp_send_json_error( array( 'ok' => false, 'message' => __( 'Unauthorized.', 'peracrm' ) ), 403 );
		}

		$type = isset( $_POST['form_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['form_type'] ) ) : '';
		$client_id = isset( $_POST['peracrm_client_id'] ) ? absint( wp_unslash( (string) $_POST['peracrm_client_id'] ) ) : 0;
		if ( $client_id <= 0 ) {
			wp_send_json_error( array( 'ok' => false, 'message' => __( 'Invalid client.', 'peracrm' ) ), 400 );
		}

		$access = pera_crm_client_view_access_state( $client_id );
		if ( empty( $access['allowed'] ) ) {
			wp_send_json_error( array( 'ok' => false, 'message' => __( 'Access denied.', 'peracrm' ) ), 403 );
		}

		if ( 'profile' === $type ) {
			check_admin_referer( 'peracrm_save_client_profile', 'peracrm_save_client_profile_nonce' );
			$data = array();
			$data['status'] = isset( $_POST['peracrm_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['peracrm_status'] ) ) : '';
			$data['preferred_contact'] = isset( $_POST['peracrm_preferred_contact'] )
				? wp_unslash( $_POST['peracrm_preferred_contact'] )
				: array();
			$data['budget_min_usd'] = isset( $_POST['peracrm_budget_min_usd'] ) ? (float) wp_unslash( (string) $_POST['peracrm_budget_min_usd'] ) : '';
			$data['budget_max_usd'] = isset( $_POST['peracrm_budget_max_usd'] ) ? (float) wp_unslash( (string) $_POST['peracrm_budget_max_usd'] ) : '';
			$data['bedrooms'] = isset( $_POST['peracrm_bedrooms'] ) ? absint( wp_unslash( (string) $_POST['peracrm_bedrooms'] ) ) : '';
			$data['phone'] = function_exists( 'peracrm_phone_canonical_from_source' )
				? peracrm_phone_canonical_from_source( $_POST, 'peracrm_phone_country', 'peracrm_phone_national', 'peracrm_phone' )
				: ( isset( $_POST['peracrm_phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['peracrm_phone'] ) ) : '' );
			$data['email'] = isset( $_POST['peracrm_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['peracrm_email'] ) ) : '';
			if ( isset( $_POST['peracrm_client_type'] ) ) {
				$data['client_type'] = sanitize_key( wp_unslash( (string) $_POST['peracrm_client_type'] ) );
			}
			$ok = function_exists( 'peracrm_client_update_profile' ) ? (bool) peracrm_client_update_profile( $client_id, $data ) : false;
			pera_crm_client_action_ajax_json( $ok, $ok ? __( 'Profile saved.', 'peracrm' ) : __( 'Unable to save profile.', 'peracrm' ), $client_id, 'profile', $ok ? 200 : 400 );
		} elseif ( 'status' === $type ) {
			check_admin_referer( 'peracrm_save_party_status' );
			$status_payload = array( 'lead_stage_updated_at' => function_exists( 'peracrm_now_mysql' ) ? peracrm_now_mysql() : current_time( 'mysql' ) );
			if ( isset( $_POST['lead_pipeline_stage'] ) ) {
				$status_payload['lead_pipeline_stage'] = sanitize_key( wp_unslash( (string) $_POST['lead_pipeline_stage'] ) );
			}
			if ( isset( $_POST['engagement_state'] ) ) {
				$status_payload['engagement_state'] = sanitize_key( wp_unslash( (string) $_POST['engagement_state'] ) );
			}
			if ( isset( $_POST['disposition'] ) ) {
				$status_payload['disposition'] = sanitize_key( wp_unslash( (string) $_POST['disposition'] ) );
			}
			$ok = function_exists( 'peracrm_party_upsert_status' ) ? (bool) peracrm_party_upsert_status( $client_id, $status_payload ) : false;
			if ( isset( $_POST['peracrm_client_type'] ) && function_exists( 'peracrm_client_update_profile' ) ) {
				$ok = (bool) peracrm_client_update_profile(
					$client_id,
					array(
						'client_type' => sanitize_key( wp_unslash( (string) $_POST['peracrm_client_type'] ) ),
					)
				) && $ok;
			}
			pera_crm_client_action_ajax_json( $ok, $ok ? __( 'Status saved.', 'peracrm' ) : __( 'Unable to save status.', 'peracrm' ), $client_id, 'status', $ok ? 200 : 400 );
		} elseif ( 'note' === $type ) {
			check_admin_referer( 'peracrm_add_note', 'peracrm_add_note_nonce' );
			$assigned_advisor_id = function_exists( 'peracrm_admin_get_assigned_advisor_id_for_client' ) ? (int) peracrm_admin_get_assigned_advisor_id_for_client( $client_id ) : 0;
			$can_override = current_user_can( 'manage_options' ) || current_user_can( 'peracrm_manage_all_reminders' );
			$is_assigned = $assigned_advisor_id > 0 && $assigned_advisor_id === get_current_user_id();
			if ( ! $can_override && ! $is_assigned ) {
				wp_send_json_error( array( 'ok' => false, 'message' => __( 'Unauthorized.', 'peracrm' ) ), 403 );
			}

			$note_body = isset( $_POST['peracrm_note_body'] ) ? trim( sanitize_textarea_field( wp_unslash( (string) $_POST['peracrm_note_body'] ) ) ) : '';
			$actor_user_id = function_exists( 'peracrm_get_actor_user_id' ) ? peracrm_get_actor_user_id() : get_current_user_id();
			$note_id = '' !== $note_body && function_exists( 'peracrm_note_add' ) ? (int) peracrm_note_add( $client_id, $actor_user_id, substr( $note_body, 0, 5000 ) ) : 0;
			if ( $note_id > 0 && function_exists( 'peracrm_log_event' ) ) {
				peracrm_log_event( $client_id, 'note_added', array(
					'note_id'       => $note_id,
					'actor_user_id' => $actor_user_id,
				) );
			}
			$ok = $note_id > 0;
			pera_crm_client_action_ajax_json( $ok, $ok ? __( 'Note added.', 'peracrm' ) : __( 'Unable to add note.', 'peracrm' ), $client_id, 'notes', $ok ? 200 : 400 );
		} elseif ( 'note-delete' === $type ) {
			check_admin_referer( 'peracrm_delete_note', 'peracrm_delete_note_nonce' );
			$assigned_advisor_id = function_exists( 'peracrm_admin_get_assigned_advisor_id_for_client' ) ? (int) peracrm_admin_get_assigned_advisor_id_for_client( $client_id ) : 0;
			$can_override = current_user_can( 'manage_options' ) || current_user_can( 'peracrm_manage_all_reminders' );
			$is_assigned = $assigned_advisor_id > 0 && $assigned_advisor_id === get_current_user_id();
			if ( ! $can_override && ! $is_assigned ) {
				wp_send_json_error( array( 'ok' => false, 'message' => __( 'Unauthorized.', 'peracrm' ) ), 403 );
			}

			$note_id = isset( $_POST['peracrm_note_id'] ) ? absint( wp_unslash( (string) $_POST['peracrm_note_id'] ) ) : 0;
			$ok      = $note_id > 0 && function_exists( 'peracrm_notes_delete' ) ? (bool) peracrm_notes_delete( $client_id, $note_id ) : false;
			pera_crm_client_action_ajax_json( $ok, $ok ? __( 'Note deleted.', 'peracrm' ) : __( 'Unable to delete note.', 'peracrm' ), $client_id, 'notes', $ok ? 200 : 400 );
		} elseif ( 'reminder' === $type ) {
			check_admin_referer( 'peracrm_add_reminder', 'peracrm_add_reminder_nonce' );
			$due = function_exists( 'peracrm_admin_parse_datetime' ) ? peracrm_admin_parse_datetime( wp_unslash( (string) ( $_POST['peracrm_due_at'] ?? '' ) ) ) : '';
			$note = isset( $_POST['peracrm_reminder_note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['peracrm_reminder_note'] ) ) : '';
			$actor_user_id    = function_exists( 'peracrm_get_actor_user_id' ) ? peracrm_get_actor_user_id() : get_current_user_id();
			$assigned_advisor = function_exists( 'peracrm_client_get_assigned_advisor_id' ) ? (int) peracrm_client_get_assigned_advisor_id( $client_id ) : 0;
			if ( function_exists( 'peracrm_resolve_assignee_user_id' ) ) {
				$assigned_advisor = peracrm_resolve_assignee_user_id( 0, $assigned_advisor );
			} elseif ( $assigned_advisor <= 0 ) {
				$assigned_advisor = $actor_user_id;
			}
			$reminder_id = '' !== $due && function_exists( 'peracrm_reminder_add' ) ? (int) peracrm_reminder_add( $client_id, $assigned_advisor, $due, substr( (string) $note, 0, 5000 ) ) : 0;
			if ( $reminder_id > 0 && function_exists( 'peracrm_log_event' ) ) {
				peracrm_log_event( $client_id, 'reminder_added', array(
					'reminder_id'    => $reminder_id,
					'advisor_user_id' => $assigned_advisor,
					'actor_user_id'   => $actor_user_id,
					'status'          => 'pending',
				) );
			}
			$ok = $reminder_id > 0;
			pera_crm_client_action_ajax_json( $ok, $ok ? __( 'Reminder added.', 'peracrm' ) : __( 'Unable to add reminder.', 'peracrm' ), $client_id, 'reminders', $ok ? 200 : 400 );
		} elseif ( 'reminder-status' === $type ) {
			check_admin_referer( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' );
			$reminder_id = isset( $_POST['peracrm_reminder_id'] ) ? absint( wp_unslash( (string) $_POST['peracrm_reminder_id'] ) ) : 0;
			$status      = isset( $_POST['peracrm_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['peracrm_status'] ) ) : '';
			$result      = function_exists( 'peracrm_reminders_update_status_authorized' )
				? peracrm_reminders_update_status_authorized(
					$reminder_id,
					$status,
					function_exists( 'peracrm_get_actor_user_id' ) ? peracrm_get_actor_user_id() : get_current_user_id(),
					array( 'enforce_client_scope' => true )
				)
				: new WP_Error( 'missing_handler', __( 'Reminder actions unavailable.', 'peracrm' ) );
			$ok          = ! is_wp_error( $result );
			if ( $ok && function_exists( 'peracrm_log_event' ) ) {
				$reminder = function_exists( 'peracrm_reminders_get' ) ? peracrm_reminders_get( $reminder_id ) : null;
				peracrm_log_event( $client_id, 'reminder_status_changed', array(
					'reminder_id'    => $reminder_id,
					'status'         => $status,
					'advisor_user_id' => isset( $reminder['advisor_user_id'] ) ? (int) $reminder['advisor_user_id'] : 0,
					'actor_user_id'  => function_exists( 'peracrm_get_actor_user_id' ) ? peracrm_get_actor_user_id() : get_current_user_id(),
				) );
			}
			$message     = $ok ? __( 'Reminder updated.', 'peracrm' ) : ( $result instanceof WP_Error ? $result->get_error_message() : __( 'Unable to update reminder.', 'peracrm' ) );
			pera_crm_client_action_ajax_json( $ok, $message, $client_id, 'reminders', $ok ? 200 : 400 );
		} elseif ( 'property-link' === $type ) {
			check_admin_referer( 'pera_crm_property_action', 'pera_crm_property_nonce' );
			$property_id = isset( $_POST['property_id'] ) ? absint( wp_unslash( (string) $_POST['property_id'] ) ) : 0;
			$ok = $property_id > 0 && function_exists( 'peracrm_client_property_link' )
				? (bool) peracrm_client_property_link( $client_id, $property_id, 'portfolio' )
				: false;
			pera_crm_client_action_ajax_json( $ok, $ok ? __( 'Property linked.', 'peracrm' ) : __( 'Unable to link property.', 'peracrm' ), $client_id, 'properties', $ok ? 200 : 400 );
		} elseif ( 'deal' === $type ) {
			$nonce = isset( $_POST['peracrm_deal_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['peracrm_deal_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'peracrm_create_deal' ) && ! wp_verify_nonce( $nonce, 'peracrm_update_deal' ) ) {
				wp_send_json_error( array( 'ok' => false, 'message' => __( 'Invalid nonce.', 'peracrm' ) ), 403 );
			}
			if ( ! function_exists( 'peracrm_deals_create' ) || ! function_exists( 'peracrm_deals_update' ) ) {
				wp_send_json_error( array( 'ok' => false, 'message' => __( 'Deals helpers unavailable.', 'peracrm' ) ), 500 );
			}
			$deal_id = isset( $_POST['deal_id'] ) ? absint( wp_unslash( (string) $_POST['deal_id'] ) ) : 0;
			$payload = array(
				'party_id' => $client_id,
				'title' => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '',
				'stage' => isset( $_POST['stage'] ) ? sanitize_key( wp_unslash( (string) $_POST['stage'] ) ) : 'reservation_taken',
				'primary_property_id' => isset( $_POST['primary_property_id'] ) ? absint( wp_unslash( (string) $_POST['primary_property_id'] ) ) : 0,
				'deal_value' => isset( $_POST['deal_value'] ) ? (float) wp_unslash( (string) $_POST['deal_value'] ) : null,
				'currency' => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['currency'] ) ) : 'USD',
			);
			if ( isset( $_POST['owner_user_id'] ) ) {
				$requested_owner_user_id = absint( wp_unslash( (string) $_POST['owner_user_id'] ) );
				if ( $requested_owner_user_id > 0 && function_exists( 'peracrm_user_is_valid_advisor' ) && ! peracrm_user_is_valid_advisor( $requested_owner_user_id ) ) {
					wp_send_json_error( array( 'ok' => false, 'message' => __( 'Invalid deal owner.', 'peracrm' ) ), 400 );
				}
				$payload['owner_user_id'] = $requested_owner_user_id;
			}
			$ok = $deal_id > 0 ? (bool) peracrm_deals_update( $deal_id, $payload ) : ( (int) peracrm_deals_create( $payload ) > 0 );
			pera_crm_client_action_ajax_json( $ok, $ok ? __( 'Deal saved.', 'peracrm' ) : __( 'Unable to save deal.', 'peracrm' ), $client_id, 'deals', $ok ? 200 : 400 );
		} elseif ( 'advisor' === $type ) {
			check_admin_referer( 'peracrm_reassign_client_advisor', 'peracrm_reassign_client_advisor_nonce' );
			if ( ! function_exists( 'peracrm_admin_user_can_reassign' ) || ! peracrm_admin_user_can_reassign() ) {
				wp_send_json_error( array( 'ok' => false, 'message' => __( 'Unauthorized.', 'peracrm' ) ), 403 );
			}

			$new_advisor = isset( $_POST['peracrm_assigned_advisor'] ) ? absint( wp_unslash( (string) $_POST['peracrm_assigned_advisor'] ) ) : 0;
			if ( $new_advisor > 0 && function_exists( 'peracrm_user_is_staff' ) && ! peracrm_user_is_staff( $new_advisor ) ) {
				wp_send_json_error( array( 'ok' => false, 'message' => __( 'Invalid advisor selection.', 'peracrm' ) ), 400 );
			}

			$old_advisor = function_exists( 'peracrm_client_get_assigned_advisor_id' ) ? (int) peracrm_client_get_assigned_advisor_id( $client_id ) : 0;

			foreach ( array( 'assigned_advisor_user_id', 'crm_assigned_advisor' ) as $meta_key ) {
				if ( $new_advisor > 0 ) {
					update_post_meta( $client_id, $meta_key, $new_advisor );
				} else {
					delete_post_meta( $client_id, $meta_key );
				}
			}

			if ( $new_advisor !== $old_advisor && function_exists( 'peracrm_log_event' ) ) {
				peracrm_log_event( $client_id, 'advisor_reassigned', array(
					'from'          => $old_advisor,
					'to'            => $new_advisor,
					'actor_user_id' => function_exists( 'peracrm_get_actor_user_id' ) ? peracrm_get_actor_user_id() : get_current_user_id(),
				) );
			}

			pera_crm_client_action_ajax_json( true, __( 'Advisor reassigned.', 'peracrm' ), $client_id, 'advisor', 200 );
		} else {
			wp_send_json_error( array( 'ok' => false, 'message' => __( 'Unsupported action.', 'peracrm' ) ), 400 );
		}
	}
}
add_action( 'wp_ajax_pera_crm_client_action', 'pera_crm_client_action_ajax' );
