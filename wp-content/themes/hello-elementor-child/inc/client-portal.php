<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pera_register_client_portal_page() {
    $page_slug     = 'client-portal';
    $page_title    = 'Client Portal';
    $template_file = 'page-client-portal.php';

    $existing_page = get_page_by_path( $page_slug );

    if ( ! $existing_page ) {
        $page_id = wp_insert_post( array(
            'post_title'   => $page_title,
            'post_name'    => $page_slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );

        if ( ! is_wp_error( $page_id ) ) {
            update_post_meta( $page_id, '_wp_page_template', $template_file );
        }

        return;
    }

    update_post_meta( $existing_page->ID, '_wp_page_template', $template_file );

    if ( $existing_page->post_name !== $page_slug ) {
        wp_update_post( array(
            'ID'        => $existing_page->ID,
            'post_name' => $page_slug,
        ) );
    }
}
add_action( 'after_switch_theme', 'pera_register_client_portal_page' );

function pera_handle_client_portal_profile_update() {
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/client-login/' ) );
        exit;
    }

    check_admin_referer( 'pera_client_portal_update', 'pera_client_portal_nonce' );

    $user_id = get_current_user_id();
    $user    = get_userdata( $user_id );

    if ( ! $user ) {
        wp_safe_redirect( add_query_arg( 'updated', 0, wp_get_referer() ?: home_url( '/client-portal/' ) ) );
        exit;
    }

    $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
    $phone_raw  = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '';
    $phone      = preg_replace( '/[^0-9+]/', '', (string) $phone_raw );
    $phone      = substr( $phone, 0, 30 );

    $preferred_contact = isset( $_POST['preferred_contact'] ) ? sanitize_key( wp_unslash( $_POST['preferred_contact'] ) ) : '';
    $allowed_contact   = array( 'phone', 'whatsapp', 'email' );
    if ( ! in_array( $preferred_contact, $allowed_contact, true ) ) {
        $preferred_contact = '';
    }

    $budget_min = isset( $_POST['budget_min_usd'] ) ? (int) wp_unslash( $_POST['budget_min_usd'] ) : 0;
    $budget_max = isset( $_POST['budget_max_usd'] ) ? (int) wp_unslash( $_POST['budget_max_usd'] ) : 0;

    if ( $budget_min < 0 ) {
        $budget_min = 0;
    }
    if ( $budget_max < 0 ) {
        $budget_max = 0;
    }
    if ( $budget_max > 0 && $budget_min > 0 && $budget_max < $budget_min ) {
        $swap       = $budget_min;
        $budget_min = $budget_max;
        $budget_max = $swap;
    }

    update_user_meta( $user_id, 'first_name', $first_name );
    update_user_meta( $user_id, 'last_name', $last_name );
    update_user_meta( $user_id, 'phone', $phone );

    $client_id = (int) get_user_meta( $user_id, 'crm_client_id', true );
    if ( $client_id <= 0 && function_exists( 'peracrm_autolink_user_to_client' ) ) {
        $client_id = (int) peracrm_autolink_user_to_client( $user );
    }

    $changed_keys = array();

    if ( $client_id > 0 && function_exists( 'peracrm_client_get_profile' ) && function_exists( 'peracrm_client_update_profile' ) ) {
        $old_profile = peracrm_client_get_profile( $client_id );

        $data = array(
            'phone'             => $phone,
            'email'             => $user->user_email,
            'preferred_contact' => $preferred_contact,
            'budget_min_usd'    => $budget_min,
            'budget_max_usd'    => $budget_max,
        );

        peracrm_client_update_profile( $client_id, $data );

        $new_profile = peracrm_client_get_profile( $client_id );

        $map = array(
            'phone'             => 'phone',
            'preferred_contact' => 'preferred_contact',
            'budget_min_usd'    => 'budget_min_usd',
            'budget_max_usd'    => 'budget_max_usd',
        );

        foreach ( $map as $event_key => $profile_key ) {
            $before = isset( $old_profile[ $profile_key ] ) ? (string) $old_profile[ $profile_key ] : '';
            $after  = isset( $new_profile[ $profile_key ] ) ? (string) $new_profile[ $profile_key ] : '';

            if ( $before !== $after ) {
                $changed_keys[] = $event_key;
            }
        }

        if ( function_exists( 'peracrm_log_event' ) && ! empty( $changed_keys ) ) {
            peracrm_log_event( $client_id, 'profile_update', array(
                'changed_keys' => array_values( $changed_keys ),
                'source'       => 'client_portal',
                'user_id'      => $user_id,
            ) );
        }
    }

    $redirect_url = wp_get_referer();
    if ( ! $redirect_url ) {
        $redirect_url = home_url( '/client-portal/' );
    }

    $redirect_url = remove_query_arg( array( 'updated' ), $redirect_url );
    $redirect_url = add_query_arg( 'updated', 1, $redirect_url );

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_pera_client_portal_update_profile', 'pera_handle_client_portal_profile_update' );
