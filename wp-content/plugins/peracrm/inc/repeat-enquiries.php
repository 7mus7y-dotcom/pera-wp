<?php
/**
 * Persistent per-user review queue for enquiries submitted by existing clients.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function peracrm_repeat_enquiries_reviewed_ids( int $user_id ): array {
	$stored = get_user_meta( $user_id, 'peracrm_repeat_enquiry_reviewed_ids', true );
	if ( ! is_array( $stored ) ) {
		return array();
	}
	return array_values( array_unique( array_filter( array_map( 'absint', $stored ) ) ) );
}

function peracrm_repeat_enquiries_user_can_view_client( int $user_id, int $client_id ): bool {
	if ( $user_id <= 0 || $client_id <= 0 || ! get_post( $client_id ) ) {
		return false;
	}
	if ( function_exists( 'pera_crm_user_is_employee' ) && pera_crm_user_is_employee( $user_id ) ) {
		if ( ! function_exists( 'pera_crm_get_allowed_client_ids_for_user' ) ) {
			return false;
		}
		$allowed = array_map( 'intval', (array) pera_crm_get_allowed_client_ids_for_user( $user_id ) );
		return in_array( $client_id, $allowed, true );
	}
	return current_user_can( 'manage_options' )
		|| current_user_can( 'peracrm_manage_all_clients' )
		|| current_user_can( 'edit_crm_clients' );
}

/**
 * Return recent repeat enquiry activity that the current user has not reviewed.
 *
 * @return array<int,array<string,mixed>>
 */
function peracrm_repeat_enquiries_get_pending( int $limit = 10 ): array {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 || ! function_exists( 'peracrm_activity_table_exists' ) || ! peracrm_activity_table_exists() ) {
		return array();
	}

	$reviewed = array_fill_keys( peracrm_repeat_enquiries_reviewed_ids( $user_id ), true );
	$limit    = max( 1, min( 25, $limit ) );

	$read_rows = static function () use ( $limit ): array {
		global $wpdb;
		$table = peracrm_table( 'crm_activity' );
		$like  = '%"is_repeat_enquiry":true%';
		$sql   = $wpdb->prepare(
			"SELECT id, client_id, event_payload, created_at
			FROM {$table}
			WHERE event_type = %s AND event_payload LIKE %s
			ORDER BY created_at DESC, id DESC
			LIMIT %d",
			'enquiry',
			$like,
			500
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	};

	$rows = function_exists( 'peracrm_with_target_blog' )
		? peracrm_with_target_blog( $read_rows )
		: $read_rows();
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$items = array();
	foreach ( $rows as $row ) {
		$activity_id = absint( $row['id'] ?? 0 );
		$client_id   = absint( $row['client_id'] ?? 0 );
		if ( $activity_id <= 0 || $client_id <= 0 || isset( $reviewed[ $activity_id ] ) ) {
			continue;
		}
		if ( ! peracrm_repeat_enquiries_user_can_view_client( $user_id, $client_id ) ) {
			continue;
		}

		$payload = json_decode( (string) ( $row['event_payload'] ?? '' ), true );
		if ( ! is_array( $payload ) || empty( $payload['is_repeat_enquiry'] ) ) {
			continue;
		}

		$property_id = absint( $payload['property_id'] ?? 0 );
		$source      = sanitize_text_field( (string) ( $payload['form_name'] ?? $payload['source'] ?? $payload['form'] ?? '' ) );
		$message     = sanitize_textarea_field( (string) ( $payload['message'] ?? '' ) );
		$items[] = array(
			'activity_id' => $activity_id,
			'client_id'   => $client_id,
			'title'       => get_the_title( $client_id ),
			'client_url'  => function_exists( 'pera_crm_get_client_view_url' )
				? pera_crm_get_client_view_url( $client_id )
				: home_url( '/crm/client/' . $client_id . '/' ),
			'created_at'  => (string) ( $row['created_at'] ?? '' ),
			'source'      => $source,
			'property'    => $property_id > 0 ? get_the_title( $property_id ) : '',
			'message'     => $message,
		);
		if ( count( $items ) >= $limit ) {
			break;
		}
	}
	return $items;
}

function peracrm_repeat_enquiries_mark_reviewed_handler(): void {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'Access denied.', 'peracrm' ), '', array( 'response' => 403 ) );
	}

	$activity_id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;
	$client_id   = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
	check_admin_referer( 'peracrm_repeat_enquiry_review_' . $activity_id );

	$user_id = get_current_user_id();
	if ( ! peracrm_repeat_enquiries_user_can_view_client( $user_id, $client_id )
		|| ! function_exists( 'peracrm_activity_table_exists' )
		|| ! peracrm_activity_table_exists() ) {
		wp_die( esc_html__( 'Access denied.', 'peracrm' ), '', array( 'response' => 403 ) );
	}

	$verify = static function () use ( $activity_id, $client_id ): bool {
		global $wpdb;
		$table = peracrm_table( 'crm_activity' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT event_type, event_payload FROM {$table} WHERE id = %d AND client_id = %d LIMIT 1", $activity_id, $client_id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) || 'enquiry' !== (string) ( $row['event_type'] ?? '' ) ) {
			return false;
		}
		$payload = json_decode( (string) ( $row['event_payload'] ?? '' ), true );
		return is_array( $payload ) && ! empty( $payload['is_repeat_enquiry'] );
	};
	$is_valid = function_exists( 'peracrm_with_target_blog' ) ? peracrm_with_target_blog( $verify ) : $verify();
	if ( ! $is_valid ) {
		wp_die( esc_html__( 'That enquiry could not be found.', 'peracrm' ), '', array( 'response' => 404 ) );
	}

	$reviewed   = peracrm_repeat_enquiries_reviewed_ids( $user_id );
	$reviewed[] = $activity_id;
	$reviewed   = array_values( array_unique( array_filter( array_map( 'absint', $reviewed ) ) ) );
	if ( count( $reviewed ) > 1000 ) {
		$reviewed = array_slice( $reviewed, -1000 );
	}
	update_user_meta( $user_id, 'peracrm_repeat_enquiry_reviewed_ids', $reviewed );

	wp_safe_redirect( add_query_arg( 'repeat_enquiry_reviewed', '1', home_url( '/crm/' ) ) );
	exit;
}
add_action( 'admin_post_peracrm_repeat_enquiry_review', 'peracrm_repeat_enquiries_mark_reviewed_handler' );
