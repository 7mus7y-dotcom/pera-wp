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

if ( ! function_exists( 'pera_crm_client_view_access_state' ) ) {
	function pera_crm_client_view_access_state( int $client_id ): array {
		if ( ! pera_crm_client_view_can_manage() ) {
			return array( 'allowed' => false, 'message' => __( 'You do not have permission to access Client View.', 'hello-elementor-child' ) );
		}

		$client = pera_crm_client_view_with_target_blog(
			static function () use ( $client_id ) {
				return $client_id > 0 ? get_post( $client_id ) : null;
			}
		);

		if ( ! ( $client instanceof WP_Post ) || 'crm_client' !== $client->post_type ) {
			return array( 'allowed' => false, 'message' => __( 'Client not found.', 'hello-elementor-child' ) );
		}

		if ( ! current_user_can( 'edit_post', $client_id ) ) {
			return array( 'allowed' => false, 'message' => __( 'You do not have permission to view this client.', 'hello-elementor-child' ) );
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
				return array( 'allowed' => false, 'message' => __( 'Access denied. You are not assigned to this client.', 'hello-elementor-child' ) );
			}
		}

		return array( 'allowed' => true, 'message' => '' );
	}
}

if ( ! function_exists( 'pera_crm_client_view_notice_message' ) ) {
	function pera_crm_client_view_notice_message( string $notice ): array {
		$map = array(
			'note_added'           => array( 'success', __( 'CRM note added.', 'hello-elementor-child' ) ),
			'note_missing'         => array( 'warning', __( 'Please add a note before saving.', 'hello-elementor-child' ) ),
			'note_failed'          => array( 'warning', __( 'Unable to save CRM note.', 'hello-elementor-child' ) ),
			'reminder_added'       => array( 'success', __( 'CRM reminder created.', 'hello-elementor-child' ) ),
			'reminder_done'        => array( 'success', __( 'Reminder marked done.', 'hello-elementor-child' ) ),
			'reminder_dismissed'   => array( 'success', __( 'Reminder dismissed.', 'hello-elementor-child' ) ),
			'reminder_failed'      => array( 'warning', __( 'Unable to update reminder.', 'hello-elementor-child' ) ),
			'profile_saved'        => array( 'success', __( 'Profile saved.', 'hello-elementor-child' ) ),
			'profile_failed'       => array( 'warning', __( 'Unable to save profile.', 'hello-elementor-child' ) ),
			'advisor_reassigned'   => array( 'success', __( 'Advisor reassigned.', 'hello-elementor-child' ) ),
			'deal_saved'           => array( 'success', __( 'Deal saved.', 'hello-elementor-child' ) ),
			'deal_failed'          => array( 'warning', __( 'Unable to save deal.', 'hello-elementor-child' ) ),
			'link_success'         => array( 'success', __( 'User linked successfully.', 'hello-elementor-child' ) ),
			'unlink_success'       => array( 'success', __( 'User unlinked successfully.', 'hello-elementor-child' ) ),
			'property_linked'      => array( 'success', __( 'Property linked.', 'hello-elementor-child' ) ),
			'property_unlinked'    => array( 'success', __( 'Property unlinked.', 'hello-elementor-child' ) ),
			'property_link_failed' => array( 'warning', __( 'Unable to link property.', 'hello-elementor-child' ) ),
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

if ( ! function_exists( 'pera_crm_client_view_timeline_items' ) ) {
	function pera_crm_client_view_timeline_items( int $client_id, string $filter = 'all', int $limit = 50 ): array {
		$filter = sanitize_key( $filter );
		if ( ! in_array( $filter, array( 'all', 'activity', 'notes', 'reminders' ), true ) ) {
			$filter = 'all';
		}

		if ( function_exists( 'peracrm_timeline_get_items' ) ) {
			$items = peracrm_timeline_get_items( $client_id, $limit, $filter );
			return is_array( $items ) ? $items : array();
		}

		$items = array();

		if ( ( 'all' === $filter || 'notes' === $filter ) && function_exists( 'peracrm_notes_list' ) ) {
			foreach ( (array) peracrm_notes_list( $client_id, $limit, 0 ) as $note ) {
				$author = get_userdata( (int) ( $note['advisor_user_id'] ?? 0 ) );
				$items[] = array(
					'type'   => 'notes',
					'title'  => __( 'Note added', 'hello-elementor-child' ),
					'detail' => (string) ( $note['note_body'] ?? '' ),
					'ts'     => strtotime( (string) ( $note['created_at'] ?? '' ) ),
					'meta'   => array( 'author' => $author ? $author->display_name : '' ),
				);
			}
		}

		if ( ( 'all' === $filter || 'reminders' === $filter ) && function_exists( 'peracrm_reminders_list_for_client' ) ) {
			foreach ( (array) peracrm_reminders_list_for_client( $client_id, $limit, 0, null ) as $reminder ) {
				$items[] = array(
					'type'   => 'reminders',
					'title'  => __( 'Reminder', 'hello-elementor-child' ),
					'detail' => (string) ( $reminder['note'] ?? '' ),
					'ts'     => strtotime( (string) ( $reminder['due_at'] ?? '' ) ),
					'meta'   => array( 'status' => (string) ( $reminder['status'] ?? 'pending' ) ),
				);
			}
		}

		if ( ( 'all' === $filter || 'activity' === $filter ) && function_exists( 'peracrm_activity_list' ) ) {
			foreach ( (array) peracrm_activity_list( $client_id, $limit, 0, null ) as $activity ) {
				$items[] = array(
					'type'   => 'activity',
					'title'  => (string) ( $activity['event_type'] ?? __( 'Activity', 'hello-elementor-child' ) ),
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

		return array_slice( $items, 0, $limit );
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
				$reminders = function_exists( 'peracrm_reminders_list_for_client' ) ? (array) peracrm_reminders_list_for_client( $client_id, 20, 0, null ) : array();
				$activity  = function_exists( 'peracrm_activity_list' ) ? (array) peracrm_activity_list( $client_id, 20, 0, null ) : array();
				$deals     = function_exists( 'peracrm_deals_get_by_party' ) ? (array) peracrm_deals_get_by_party( $client_id ) : array();

				$relation_types  = array( 'favourite', 'enquiry', 'viewed', 'portfolio' );
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
				$last_activity    = $last_activity_ts > 0 ? human_time_diff( $last_activity_ts, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'hello-elementor-child' ) : '—';

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
				);
			}
		);
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
		$relation_type = isset( $_POST['relation_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['relation_type'] ) ) : 'enquiry';
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
