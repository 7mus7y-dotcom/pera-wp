<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pera_ensure_client_page( $page_slug, $page_title, $template_file ) {
    $page_slug     = sanitize_title( (string) $page_slug );
    $page_title    = sanitize_text_field( (string) $page_title );
    $template_file = sanitize_file_name( (string) $template_file );

    if ( '' === $page_slug || '' === $page_title || '' === $template_file ) {
        return;
    }

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

function pera_register_core_client_pages() {
    pera_ensure_client_page( 'register', 'Register', 'page-register.php' );
    pera_ensure_client_page( 'client-portal', 'Client Portal', 'page-client-portal.php' );
    pera_ensure_client_page( 'client-login', 'Client Login', 'page-client-login.php' );
    pera_ensure_client_page( 'client-forgot-password', 'Forgot Password', 'page-client-forgot-password.php' );
}
add_action( 'after_switch_theme', 'pera_register_core_client_pages', 5 );

function pera_public_register_turnstile_site_key() {
    // Admin note: public registration is fail-closed unless BOTH constants below are set.
    // Define PERA_TURNSTILE_SITE_KEY and PERA_TURNSTILE_SECRET_KEY in wp-config.php.
    return defined( 'PERA_TURNSTILE_SITE_KEY' ) ? sanitize_text_field( (string) PERA_TURNSTILE_SITE_KEY ) : '';
}

function pera_public_register_turnstile_secret_key() {
    return defined( 'PERA_TURNSTILE_SECRET_KEY' ) ? sanitize_text_field( (string) PERA_TURNSTILE_SECRET_KEY ) : '';
}

function pera_public_register_turnstile_check( $token ) {
    $secret = pera_public_register_turnstile_secret_key();
    $token  = is_string( $token ) ? trim( $token ) : '';

    if ( '' === $secret || '' === $token ) {
        return false;
    }

    $response = wp_remote_post(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        array(
            'timeout' => 12,
            'body'    => array(
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
            ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $payload = json_decode( wp_remote_retrieve_body( $response ), true );
    return is_array( $payload ) && ! empty( $payload['success'] );
}

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
    $consent = isset( $_POST['privacy_terms_consent'] ) ? sanitize_key( wp_unslash( $_POST['privacy_terms_consent'] ) ) : '';
    if ( '1' !== $consent ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'consent_required' ) ) );
        exit;
    }

    $turnstile_site_key   = pera_public_register_turnstile_site_key();
    $turnstile_secret_key = pera_public_register_turnstile_secret_key();
    if ( '' === $turnstile_site_key || '' === $turnstile_secret_key ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'turnstile_not_configured' ) ) );
        exit;
    }

    $turnstile_token = isset( $_POST['cf-turnstile-response'] ) ? (string) wp_unslash( $_POST['cf-turnstile-response'] ) : '';
    if ( ! pera_public_register_turnstile_check( $turnstile_token ) ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'turnstile_failed' ) ) );
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

    if ( strlen( $password ) < 8 ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'weak_password' ) ) );
        exit;
    }

    if ( email_exists( $email ) ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'email_exists' ) ) );
        exit;
    }

    $client_ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
    $email_key      = 'pera_public_register_email_' . md5( strtolower( $email ) );
    $ip_key         = 'pera_public_register_ip_' . md5( $client_ip );
    $email_attempts = (int) get_transient( $email_key );
    $ip_attempts    = (int) get_transient( $ip_key );

    if ( $email_attempts >= 3 || $ip_attempts >= 10 ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/register/', array( 'register_error' => 'rate_limited' ) ) );
        exit;
    }
    set_transient( $email_key, $email_attempts + 1, 15 * MINUTE_IN_SECONDS );
    set_transient( $ip_key, $ip_attempts + 1, 15 * MINUTE_IN_SECONDS );

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
    update_user_meta( (int) $user_id, 'pera_email_verified', '0' );
    update_user_meta( (int) $user_id, 'pera_registered_via_public_form', '1' );

    $membership_ok = true;

    if ( is_multisite() ) {
        $target_blog_id = function_exists( 'peracrm_get_target_blog_id' ) ? (int) peracrm_get_target_blog_id() : 0;

        if ( $target_blog_id <= 0 ) {
            $membership_ok = false;
        }

        if ( $membership_ok && function_exists( 'peracrm_membership_ensure_lead_on_target_blog' ) ) {
            $membership = peracrm_membership_ensure_lead_on_target_blog( (int) $user_id, 'public_register' );
            $membership_ok = ! empty( $membership['ok'] );
        } elseif ( $membership_ok ) {
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

    $crm_synced = true;

    if ( function_exists( 'peracrm_sync_public_registration_to_client' ) ) {
        $crm_sync = peracrm_sync_public_registration_to_client( (int) $user_id, array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'source_url' => home_url( '/register/' ),
        ) );

        $crm_synced = ! empty( $crm_sync['ok'] ) && ( ! array_key_exists( 'event_logged', $crm_sync ) || ! empty( $crm_sync['event_logged'] ) );
    }

    $verify_token = wp_generate_password( 48, false, false );
    update_user_meta( (int) $user_id, 'pera_email_verification_token', wp_hash_password( $verify_token ) );
    update_user_meta( (int) $user_id, 'pera_email_verification_sent_at', (string) time() );

    $verify_url = add_query_arg(
        array(
            'pera_verify_email' => (int) $user_id,
            'token'             => rawurlencode( $verify_token ),
        ),
        home_url( '/register/' )
    );

    wp_mail(
        $email,
        sprintf( __( 'Verify your %s account', 'hello-elementor-child' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
        sprintf( __( "Hi %s,\n\nPlease verify your email by opening this link:\n%s\n\nAfter verification you can access the client portal.\n", 'hello-elementor-child' ), $first_name, esc_url_raw( $verify_url ) )
    );

    wp_safe_redirect( pera_public_register_get_redirect( '/client-login/', array(
        'registered' => 1,
        'verify_email' => 1,
        'crm_sync'   => $crm_synced ? 'ok' : 'pending',
    ) ) );
    exit;
}
add_action( 'admin_post_nopriv_pera_public_register', 'pera_public_register_handle_submission' );
add_action( 'admin_post_pera_public_register', 'pera_public_register_handle_submission' );

function pera_public_register_handle_verification_request() {
    if ( ! is_page( 'register' ) || ! isset( $_GET['pera_verify_email'], $_GET['token'] ) ) {
        return;
    }
    $user_id = (int) wp_unslash( $_GET['pera_verify_email'] );
    $token   = sanitize_text_field( (string) wp_unslash( $_GET['token'] ) );
    $hash    = (string) get_user_meta( $user_id, 'pera_email_verification_token', true );
    $sent_at = (int) get_user_meta( $user_id, 'pera_email_verification_sent_at', true );
    $expires = $sent_at > 0 ? ( $sent_at + ( 48 * HOUR_IN_SECONDS ) ) : 0;
    if ( $user_id <= 0 || '' === $token || '' === $hash || ! wp_check_password( $token, $hash ) ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/client-login/', array( 'verify_status' => 'invalid' ) ) );
        exit;
    }
    if ( $expires > 0 && time() > $expires ) {
        delete_user_meta( $user_id, 'pera_email_verification_token' );
        wp_safe_redirect( pera_public_register_get_redirect( '/client-login/', array( 'verify_status' => 'expired' ) ) );
        exit;
    }
    update_user_meta( $user_id, 'pera_email_verified', '1' );
    delete_user_meta( $user_id, 'pera_email_verification_token' );
    wp_safe_redirect( pera_public_register_get_redirect( '/client-login/', array( 'verify_status' => 'success' ) ) );
    exit;
}
add_action( 'template_redirect', 'pera_public_register_handle_verification_request', 1 );

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

function pera_get_public_client_login_url( $redirect_to = '' ) {
    $fallback = home_url( '/client-login/' );
    $login_url = $fallback;

    if ( ! is_string( $redirect_to ) || '' === $redirect_to ) {
        return $login_url;
    }

    $candidate = trim( $redirect_to );
    if ( '/' === substr( $candidate, 0, 1 ) ) {
        $candidate = home_url( $candidate );
    }

    $validated_redirect = wp_validate_redirect( $candidate, $fallback );
    if ( $validated_redirect === $fallback ) {
        return $login_url;
    }

    $home_host      = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
    $redirect_host  = wp_parse_url( $validated_redirect, PHP_URL_HOST );
    $same_site_host = is_string( $home_host ) && is_string( $redirect_host ) && strtolower( $home_host ) === strtolower( $redirect_host );

    if ( ! $same_site_host ) {
        return $login_url;
    }

    return add_query_arg( 'redirect_to', $validated_redirect, $fallback );
}

function pera_client_portal_enforce_login_redirect() {
    if ( is_user_logged_in() || ! is_page( 'client-portal' ) ) {
        return;
    }

    wp_safe_redirect( pera_client_portal_get_login_redirect_target() );
    exit;
}
add_action( 'template_redirect', 'pera_client_portal_enforce_login_redirect', 0 );

function pera_client_portal_block_unverified_public_registrations() {
    if ( ! is_user_logged_in() || ! is_page( 'client-portal' ) ) {
        return;
    }
    $user_id = get_current_user_id();
    $public_registration = (string) get_user_meta( $user_id, 'pera_registered_via_public_form', true );
    $verified            = (string) get_user_meta( $user_id, 'pera_email_verified', true );
    if ( '1' === $public_registration && '1' !== $verified ) {
        wp_safe_redirect( pera_public_register_get_redirect( '/client-login/', array( 'verify_status' => 'required' ) ) );
        exit;
    }
}
add_action( 'template_redirect', 'pera_client_portal_block_unverified_public_registrations', 1 );

add_action( 'login_form_register', function () {
    wp_safe_redirect( home_url( '/register/' ) );
    exit;
} );

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
