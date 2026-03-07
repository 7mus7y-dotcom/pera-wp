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


function pera_register_public_register_page() {
    $page_slug     = 'register';
    $page_title    = 'Register';
    $template_file = 'page-register.php';

    $existing_page = get_page_by_path( $page_slug );

    if ( ! $existing_page ) {
        $page_id = wp_insert_post( array(
            'post_title'  => $page_title,
            'post_name'   => $page_slug,
            'post_status' => 'publish',
            'post_type'   => 'page',
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
add_action( 'after_switch_theme', 'pera_register_public_register_page' );

function pera_public_register_get_redirect( $path, $args = array() ) {
    $url = home_url( $path );

    if ( ! empty( $args ) ) {
        $url = add_query_arg( $args, $url );
    }

    return wp_validate_redirect( $url, home_url( '/' ) );
}

function pera_public_register_handle_submission() {
    if ( is_user_logged_in() ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/client-portal/' ) );
        exit;
    }

    $nonce = isset( $_POST['pera_public_register_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pera_public_register_nonce'] ) ) : '';
    if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'pera_public_register_action' ) ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'invalid_nonce' ) ) );
        exit;
    }

    if ( ! empty( $_POST['company'] ) ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/' ) );
        exit;
    }

    $email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $first_name       = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $last_name        = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
    $password         = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
    $password_confirm = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';

    if ( '' === $email || '' === $first_name || '' === $last_name || '' === $password || '' === $password_confirm ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'validation' ) ) );
        exit;
    }

    if ( ! is_email( $email ) || $password !== $password_confirm ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'validation' ) ) );
        exit;
    }

    if ( email_exists( $email ) ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'email_exists' ) ) );
        exit;
    }

    $client_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
    $rate_key  = 'pera_public_register_' . md5( strtolower( $email ) . '|' . $client_ip );

    if ( get_transient( $rate_key ) ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'rate_limited' ) ) );
        exit;
    }
    set_transient( $rate_key, 1, MINUTE_IN_SECONDS );

    $email_parts = explode( '@', $email, 2 );
    $base_login  = isset( $email_parts[0] ) ? sanitize_user( $email_parts[0], true ) : '';

    if ( '' === $base_login ) {
        $base_login = 'user';
    }

    $user_login = $base_login;
    $max_tries  = 100;

    for ( $i = 2; $i <= $max_tries && username_exists( $user_login ); $i++ ) {
        $user_login = $base_login . $i;
    }

    if ( username_exists( $user_login ) ) {
        $user_login = 'user' . wp_generate_password( 8, false, false );
    }

    $user_id = wp_insert_user( array(
        'user_login' => $user_login,
        'user_pass'  => $password,
        'user_email' => $email,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'role'       => 'lead',
    ) );

    if ( is_wp_error( $user_id ) || ! $user_id ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'create_failed' ) ) );
        exit;
    }

    $membership_ok = true;

    if ( is_multisite() ) {
        $target_blog_id = function_exists( 'peracrm_get_target_blog_id' ) ? (int) peracrm_get_target_blog_id() : (int) get_current_blog_id();

        if ( $target_blog_id <= 0 ) {
            $target_blog_id = (int) get_current_blog_id();
        }

        if ( function_exists( 'peracrm_membership_ensure_lead_on_target_blog' ) ) {
            $membership = peracrm_membership_ensure_lead_on_target_blog( (int) $user_id, 'public_register' );
            $membership_ok = ! empty( $membership['ok'] );
        } else {
            $is_member = is_user_member_of_blog( $user_id, $target_blog_id );
            if ( ! $is_member ) {
                $added = add_user_to_blog( $target_blog_id, $user_id, 'lead' );
                if ( is_wp_error( $added ) ) {
                    $membership_ok = false;
                }
            }

            if ( $membership_ok ) {
                $current_blog_id = (int) get_current_blog_id();
                $switched        = false;

                if ( $current_blog_id !== $target_blog_id ) {
                    switch_to_blog( $target_blog_id );
                    $switched = true;
                }

                $target_user = new WP_User( $user_id );
                if ( ! in_array( 'lead', (array) $target_user->roles, true ) ) {
                    $target_user->set_role( 'lead' );
                }

                if ( $switched ) {
                    restore_current_blog();
                }
            }

            $membership_ok = $membership_ok && is_user_member_of_blog( $user_id, $target_blog_id );
        }
    }

    if ( ! $membership_ok ) {
        wp_delete_user( (int) $user_id );
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'membership_failed' ) ) );
        exit;
    }

    wp_safe_redirect( pera_public_register_get_redirect( '/client-login/', array( 'registered' => 1 ) ) );
    exit;
}
add_action( 'admin_post_nopriv_pera_public_register', 'pera_public_register_handle_submission' );
add_action( 'admin_post_pera_public_register', 'pera_public_register_handle_submission' );

function pera_client_portal_get_login_redirect_target() {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/client-portal/';
    $request_uri = is_string( $request_uri ) ? trim( $request_uri ) : '/client-portal/';

    if ( '' === $request_uri || '/' !== substr( $request_uri, 0, 1 ) ) {
        $request_uri = '/client-portal/';
    }

    $current_url = home_url( $request_uri );
    $login_url   = add_query_arg( 'redirect_to', $current_url, home_url( '/client-login/' ) );

    return wp_validate_redirect( $login_url, home_url( '/client-login/' ) );
}

function pera_client_portal_enforce_login_redirect() {
    if ( is_user_logged_in() || ! is_page( 'client-portal' ) ) {
        return;
    }

    wp_safe_redirect( pera_client_portal_get_login_redirect_target() );
    exit;
}
add_action( 'template_redirect', 'pera_client_portal_enforce_login_redirect', 0 );

function pera_handle_client_portal_profile_update() {
    if ( ! is_user_logged_in() ) {
        $target = wp_validate_redirect( home_url( '/client-login/' ), home_url( '/client-login/' ) );
        wp_safe_redirect( $target );
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

    $redirect_url = wp_validate_redirect( $redirect_url, home_url( '/client-portal/' ) );

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_pera_client_portal_update_profile', 'pera_handle_client_portal_profile_update' );
