<?php
/**
 * Enquiry Handlers (Sell / Rent / Property / Citizenship)
 * Location: /inc/enquiry.php
 *
 * Loads only when required (see functions.php loader snippet below).
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! defined( 'PERA_CRM_DEBUG_FORMS' ) ) {
  define( 'PERA_CRM_DEBUG_FORMS', false );
}

function pera_forms_get_request_id() {
  static $request_id = null;

  if ( null !== $request_id ) {
    return $request_id;
  }

  if ( function_exists( 'wp_generate_uuid4' ) ) {
    $request_id = sanitize_key( wp_generate_uuid4() );
  }

  if ( empty( $request_id ) ) {
    $request_id = sanitize_key( uniqid( 'pera_forms_', true ) );
  }

  return $request_id;
}

function pera_forms_debug_log( $message, array $context = array() ) {
  if ( ! ( defined( 'PERA_CRM_DEBUG_FORMS' ) && PERA_CRM_DEBUG_FORMS ) ) {
    return;
  }

  $context['rid'] = pera_forms_get_request_id();

  $safe_context = array();
  foreach ( $context as $key => $value ) {
    $safe_key = sanitize_key( (string) $key );
    if ( is_scalar( $value ) || null === $value ) {
      $safe_context[ $safe_key ] = $value;
    }
  }

  $line = '[Pera forms] ' . sanitize_text_field( (string) $message ) . ' ' . wp_json_encode( $safe_context );

  $upload_dir = wp_get_upload_dir();
  $log_dir    = isset( $upload_dir['basedir'] ) ? trailingslashit( $upload_dir['basedir'] ) : '';
  $log_file   = $log_dir ? $log_dir . 'pera-forms.log' : '';

  if ( $log_dir && ( is_dir( $log_dir ) || wp_mkdir_p( $log_dir ) ) && is_writable( $log_dir ) ) {
    $can_write_target = ( ! file_exists( $log_file ) && is_writable( $log_dir ) ) || ( file_exists( $log_file ) && is_writable( $log_file ) );

    if ( $can_write_target ) {
      clearstatcache( true, $log_file );
      if ( file_exists( $log_file ) && filesize( $log_file ) > 5 * 1024 * 1024 ) {
        $rotated = $log_dir . 'pera-forms.log.1';
        if ( file_exists( $rotated ) && is_writable( $rotated ) ) {
          @unlink( $rotated );
        }
        @rename( $log_file, $rotated );
      }

      @file_put_contents( $log_file, gmdate( 'c' ) . ' ' . $line . PHP_EOL, FILE_APPEND );
      return;
    }
  }

  error_log( $line );
}

function pera_forms_nonce_failure_redirect( $form_key, $fallback_url, $query_arg, $anchor = '' ) {
  $redirect = ! empty( $_POST['_wp_http_referer'] )
    ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) )
    : home_url( $fallback_url );

  $redirect = preg_replace( '/#.*$/', '', $redirect );
  $redirect = add_query_arg(
    array(
      $query_arg => 'failed',
      'reason'   => 'nonce',
    ),
    $redirect
  );

  if ( $anchor ) {
    $redirect .= $anchor;
  }

  pera_forms_debug_log(
    'nonce_redirect',
    array(
      'form_key'     => $form_key,
      'handler'      => __FUNCTION__,
      'nonce_status' => 'fail',
      'redirect'     => $redirect,
    )
  );

  wp_safe_redirect( $redirect );
  exit;
}

/**
 * Auto-reply helpers.
 */
function pera_enquiry_autoreply_is_rate_limited( $context, $email ) {
  $ip = '';
  if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
    $parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
    $ip    = trim( $parts[0] );
  } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
    $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
  }

  $email = strtolower( sanitize_email( $email ) );
  $key   = 'pera_autoreply_' . md5( $context . '|' . $email . '|' . $ip );

  if ( get_transient( $key ) ) {
    return true;
  }

  set_transient( $key, 1, 60 );
  return false;
}

function pera_enquiry_autoreply_first_name( $name ) {
  $name = trim( (string) $name );
  if ( $name === '' ) {
    return '';
  }

  $parts = preg_split( '/\s+/', $name );
  return $parts ? $parts[0] : '';
}

function pera_send_enquiry_autoreply( $context, $to_email, $subject, array $lines ) {
  if ( ! is_email( $to_email ) ) {
    return false;
  }

  if ( pera_enquiry_autoreply_is_rate_limited( $context, $to_email ) ) {
    return false;
  }

  $host = wp_parse_url( home_url(), PHP_URL_HOST );
  $host = $host ? preg_replace( '/^www\./', '', $host ) : 'peraproperty.com';

  $headers = array(
    'From: Pera Property <no-reply@' . $host . '>',
    'Reply-To: Pera Property <info@peraproperty.com>',
    'Content-Type: text/plain; charset=UTF-8',
  );

  $body = implode( "\n", array_filter( $lines, 'strlen' ) );
  $sent = wp_mail( $to_email, $subject, $body, $headers );

  if ( ! $sent ) {
    error_log( 'Auto-reply failed for ' . $context . ' enquiry.' );
  }

  return $sent;
}

/**
 * Master handler for both Citizenship and Sell/Rent/Property enquiries.
 */
function pera_handle_citizenship_enquiry() {
  static $handled = false;

  if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    return;
  }

  $has_enquiry_key = isset( $_POST['sr_action'] ) || isset( $_POST['pera_citizenship_action'] ) || isset( $_POST['fav_enquiry_action'] );
  if ( ! $has_enquiry_key ) {
    return;
  }

  if ( $handled ) {
    pera_forms_debug_log( 'double_process_guard', array( 'handler' => __FUNCTION__ ) );
    return;
  }

  $handled = true;

  /* =========================================
   * A) SELL / RENT / PROPERTY ENQUIRY BRANCH
   * Trigger: <input type="hidden" name="sr_action" value="1">
   * ========================================= */
  if ( isset( $_POST['sr_action'] ) ) {
    pera_forms_debug_log(
      'handler_hit',
      array(
        'form_key' => 'sr_action',
        'route'    => 'init',
        'handler'  => __FUNCTION__,
      )
    );

    // Security: SR nonce
    $sr_nonce = isset( $_POST['sr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sr_nonce'] ) ) : '';
    if ( '' === $sr_nonce || ! wp_verify_nonce( $sr_nonce, 'pera_seller_landlord_enquiry' ) ) {
      $failed_context = isset( $_POST['form_context'] ) ? sanitize_text_field( wp_unslash( $_POST['form_context'] ) ) : '';
      $failed_anchor  = ( 'property' === $failed_context ) ? '#contact-form' : '#contact';
      pera_forms_debug_log( 'nonce_fail', array( 'form_key' => 'sr_action', 'handler' => __FUNCTION__, 'nonce_status' => '' === $sr_nonce ? 'missing' : 'invalid' ) );
      pera_forms_nonce_failure_redirect( 'sr_action', '/', 'sr_status', $failed_anchor );
    }
    pera_forms_debug_log( 'nonce_pass', array( 'form_key' => 'sr_action', 'handler' => __FUNCTION__, 'nonce_status' => 'pass' ) );

    // Honeypot check – bots fill this, humans don't
    if ( ! empty( $_POST['sr_company'] ?? '' ) ) {
      pera_forms_debug_log( 'spam_trap', array( 'form_key' => 'sr_action', 'handler' => __FUNCTION__, 'spam_trap' => 'triggered' ) );
      $redirect = add_query_arg( 'sr_status', 'failed', home_url( '/' ) ) . '#contact';
      wp_safe_redirect( $redirect );
      exit;
    }


    // Context (whitelist)
    $raw_context  = isset( $_POST['form_context'] ) ? sanitize_text_field( wp_unslash( $_POST['form_context'] ) ) : 'general';
    $sr_context   = isset( $_POST['sr_context'] ) ? sanitize_text_field( wp_unslash( $_POST['sr_context'] ) ) : '';
    $allowed_ctx  = array( 'sell-page', 'rent-page', 'property', 'general', 'general-contact', 'sell', 'rent' );
    $form_context = in_array( $raw_context, $allowed_ctx, true ) ? $raw_context : 'general';
    $sr_context   = ( $sr_context === 'bodrum_property' ) ? $sr_context : '';

    // Core fields
    $name    = isset( $_POST['sr_name'] )  ? sanitize_text_field( wp_unslash( $_POST['sr_name'] ) )  : '';
    $email   = isset( $_POST['sr_email'] ) ? sanitize_email( wp_unslash( $_POST['sr_email'] ) )      : '';
    $phone   = isset( $_POST['sr_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['sr_phone'] ) ) : '';
    $consent = ! empty( $_POST['sr_consent'] ) ? 'Yes' : 'No';

    // Optional message
    $message = isset( $_POST['sr_message'] )
      ? sanitize_textarea_field( wp_unslash( $_POST['sr_message'] ) )
      : '';

    // Sell/Rent-only fields
    $intent       = isset( $_POST['sr_intent'] )       ? sanitize_text_field( wp_unslash( $_POST['sr_intent'] ) )       : '';
    $location     = isset( $_POST['sr_location'] )     ? sanitize_text_field( wp_unslash( $_POST['sr_location'] ) )     : '';
    $details      = isset( $_POST['sr_details'] )      ? sanitize_textarea_field( wp_unslash( $_POST['sr_details'] ) )  : '';
    $expectations = isset( $_POST['sr_expectations'] ) ? sanitize_text_field( wp_unslash( $_POST['sr_expectations'] ) ) : '';

    // Property-only hidden fields
    $property_id    = isset( $_POST['sr_property_id'] )    ? absint( $_POST['sr_property_id'] ) : 0;
    $property_title = isset( $_POST['sr_property_title'] ) ? sanitize_text_field( wp_unslash( $_POST['sr_property_title'] ) ) : '';
    $property_url   = isset( $_POST['sr_property_url'] )   ? esc_url_raw( wp_unslash( $_POST['sr_property_url'] ) ) : '';

    $to = 'info@peraproperty.com';

    if ( $form_context === 'property' ) {

      $ref     = $property_id ? (string) $property_id : 'N/A';
      $subject_prefix = ( $sr_context === 'bodrum_property' ) ? 'Bodrum property enquiry' : 'Property enquiry';
      $subject = $subject_prefix . ' – ' . ( $property_title ?: 'Listing' ) . ' (Ref: ' . $ref . ')';

      $body  = "New property enquiry submitted:\n\n";
      $body .= "Name: {$name}\n";
      $body .= "Phone: {$phone}\n";
      $body .= "Email: {$email}\n\n";
      $body .= "Listing Ref: {$ref}\n";
      $body .= "Listing Title: " . ( $property_title ?: 'N/A' ) . "\n";
      $body .= "Listing URL: " . ( $property_url ?: 'N/A' ) . "\n\n";
      $body .= "Enquiry context: " . ( $sr_context ?: $form_context ) . "\n";

      if ( $message !== '' ) {
        $body .= "Message:\n{$message}\n\n";
      }

      $body .= "Consent to contact: {$consent}\n";
      $body .= "Form context: {$form_context}\n";

    } else {

      // Normalize intent (sell page may not send radios; rent page does)
      $intent_norm = $intent ?: ( ( $form_context === 'sell-page' ) ? 'sell' : '' );

      if ( $intent_norm === 'sell' ) {
        $subject = 'I want to sell my property';
      } elseif ( $intent_norm === 'rent' ) {
        $subject = 'I want to rent out my property (long-term)';
      } elseif ( $intent_norm === 'short-term' ) {
        $subject = 'I want to rent out my property (short-term / Airbnb)';
      } else {
        $subject = 'New enquiry – ' . $name . ' (' . $form_context . ')';
      }

      $body  = "New enquiry submitted:\n\n";
      $body .= "Name: {$name}\n";
      $body .= "Phone: {$phone}\n";
      $body .= "Email: {$email}\n\n";
      $body .= "Intent: {$intent_norm}\n";
      $body .= "Property location: {$location}\n\n";
      $body .= "Property details:\n{$details}\n\n";
      $body .= "Price / rent expectations: {$expectations}\n\n";

      if ( $message !== '' ) {
        $body .= "Message:\n{$message}\n\n";
      }

      $body .= "Consent to contact: {$consent}\n";
      $body .= "Form context: {$form_context}\n";
    }

    $headers = array(
      'From: ' . ( $name ?: 'Website Enquiry' ) . ' <info@peraproperty.com>',
      'Content-Type: text/plain; charset=UTF-8',
    );
    
    if ( is_email( $email ) ) {
      $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
    }


    $sent = wp_mail( $to, $subject, $body, $headers );
    pera_forms_debug_log( 'mail_result', array( 'form_key' => 'sr_action', 'handler' => __FUNCTION__, 'wp_mail' => $sent ? '1' : '0' ) );

    if ( $sent && $form_context === 'property' && is_email( $email ) ) {
      $first_name = pera_enquiry_autoreply_first_name( $name );
      $greeting   = $first_name ? 'Hello ' . $first_name . ',' : 'Hello,';
      $ref        = $property_id ? (string) $property_id : 'N/A';

      $auto_context_label = ( $sr_context === 'bodrum_property' ) ? 'Bodrum property' : 'Property';
      $auto_lines = array(
        $greeting,
        "We've received your enquiry and recorded the details below.",
        'Name: ' . ( $name ?: 'Not provided' ),
        'Listing title: ' . ( $property_title ?: 'Not provided' ),
        'Listing ref: ' . $ref,
        'Listing link: ' . ( $property_url ?: 'Not provided' ),
        'Enquiry type: ' . $auto_context_label,
        "If any of these details are incorrect, reply to this email and we'll update them.",
        'A consultant will review and contact you via your preferred method.',
        'If you need to add details, reply to this email.',
        'Pera Property',
        'info@peraproperty.com',
      );

      $auto_subject_prefix = ( $sr_context === 'bodrum_property' ) ? 'We received your Bodrum property enquiry' : 'We received your enquiry';
      $auto_subject = $auto_subject_prefix . ' — ' . ( $property_title ?: 'Property listing' );
      $auto_context = ( $sr_context === 'bodrum_property' ) ? 'bodrum_property' : 'property';
      pera_send_enquiry_autoreply( $auto_context, $email, $auto_subject, $auto_lines );
    }

    // Redirect: base (referer), add sr_status, then force the correct fragment by context.
    $redirect = ! empty( $_POST['_wp_http_referer'] )
      ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) )
      : home_url( '/' );

    // Remove any existing fragment so we can set a deterministic one.
    $redirect = preg_replace( '/#.*$/', '', $redirect );

    // Append success flag
    $redirect = add_query_arg( 'sr_status', $sent ? 'sent' : 'failed', $redirect );

    // Append fragment
    if ( $form_context === 'property' ) {
      $redirect .= '#contact-form';
    } else {
      // Sell / Rent / General enquiries should return to the contact section.
      $redirect .= '#contact';
    }

    pera_forms_debug_log( 'redirect', array( 'form_key' => 'sr_action', 'handler' => __FUNCTION__, 'redirect' => $redirect ) );
    wp_safe_redirect( $redirect );
    exit;
  }

  /* ==============================
   * C) FAVOURITES ENQUIRY BRANCH
   * Trigger: <input type="hidden" name="fav_enquiry_action" value="1">
   * ============================== */
  if ( isset( $_POST['fav_enquiry_action'] ) ) {
    pera_forms_debug_log( 'handler_hit', array( 'form_key' => 'fav_enquiry_action', 'route' => 'init', 'handler' => __FUNCTION__ ) );

    $fav_nonce = isset( $_POST['fav_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fav_nonce'] ) ) : '';
    if ( '' === $fav_nonce || ! wp_verify_nonce( $fav_nonce, 'pera_favourites_enquiry' ) ) {
      pera_forms_debug_log( 'nonce_fail', array( 'form_key' => 'fav_enquiry_action', 'handler' => __FUNCTION__, 'nonce_status' => '' === $fav_nonce ? 'missing' : 'invalid' ) );
      pera_forms_nonce_failure_redirect( 'fav_enquiry_action', '/my-favourites/', 'enquiry', '#favourites-enquiry' );
    }
    pera_forms_debug_log( 'nonce_pass', array( 'form_key' => 'fav_enquiry_action', 'handler' => __FUNCTION__, 'nonce_status' => 'pass' ) );

    if ( ! empty( $_POST['fav_company'] ?? '' ) ) {
      pera_forms_debug_log( 'spam_trap', array( 'form_key' => 'fav_enquiry_action', 'handler' => __FUNCTION__, 'spam_trap' => 'triggered' ) );
      $redirect = add_query_arg( 'enquiry', 'failed', home_url( '/my-favourites/' ) ) . '#favourites-enquiry';
      wp_safe_redirect( $redirect );
      exit;
    }

    $raw_ids = $_POST['fav_post_ids'] ?? '';
    $ids = array();

    if ( is_array( $raw_ids ) ) {
      $ids = array_map( 'absint', wp_unslash( $raw_ids ) );
    } else {
      $raw_ids = sanitize_text_field( wp_unslash( $raw_ids ) );
      $parts = preg_split( '/[,\s]+/', $raw_ids );
      $ids = array_map( 'absint', $parts ? $parts : array() );
    }

    $ids = array_values( array_unique( array_filter( $ids ) ) );
    $ids = array_slice( $ids, 0, 100 );

    $valid_posts = array();
    if ( ! empty( $ids ) ) {
      $valid_posts = get_posts(
        array(
          'post_type'      => 'property',
          'post_status'    => 'publish',
          'post__in'       => $ids,
          'orderby'        => 'post__in',
          'posts_per_page' => 100,
        )
      );
    }

    $first_name = '';
    $last_name  = '';
    $email      = '';
    $phone      = '';

    if ( is_user_logged_in() ) {
      $current_user = wp_get_current_user();
      $first_name = get_user_meta( $current_user->ID, 'first_name', true );
      $last_name  = get_user_meta( $current_user->ID, 'last_name', true );
      $email      = $current_user->user_email;

      $phone_keys = array( 'phone', 'mobile', 'billing_phone' );
      foreach ( $phone_keys as $phone_key ) {
        $candidate = get_user_meta( $current_user->ID, $phone_key, true );
        if ( $candidate ) {
          $phone = $candidate;
          break;
        }
      }
    }

    $first_name = $first_name ? $first_name : ( isset( $_POST['fav_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fav_first_name'] ) ) : '' );
    $last_name  = $last_name ? $last_name : ( isset( $_POST['fav_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fav_last_name'] ) ) : '' );
    $email      = $email ? $email : ( isset( $_POST['fav_email'] ) ? sanitize_email( wp_unslash( $_POST['fav_email'] ) ) : '' );
    $phone      = $phone ? $phone : ( isset( $_POST['fav_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['fav_phone'] ) ) : '' );

    $message = isset( $_POST['fav_message'] )
      ? sanitize_textarea_field( wp_unslash( $_POST['fav_message'] ) )
      : '';

    if ( ! $first_name || ! $last_name || ! $phone || ! is_email( $email ) ) {
      wp_die( 'Required fields missing', 'Error', array( 'response' => 400 ) );
    }

    $full_name = trim( $first_name . ' ' . $last_name );
    $to = 'info@peraproperty.com';
    $subject = 'Favourites Enquiry — ' . ( $full_name ?: 'Website user' );

    $body  = "User enquired on all favourites.\n\n";
    $body .= "Name: {$full_name}\n";
    $body .= "Email: {$email}\n";
    $body .= "Mobile: {$phone}\n\n";

    if ( $message !== '' ) {
      $body .= "Message:\n{$message}\n\n";
    }

    $body .= "Favourites:\n";
    $auto_favourites_lines = array();

    if ( ! empty( $valid_posts ) ) {
      foreach ( $valid_posts as $post ) {
        $post_id = $post->ID;
        $title = get_the_title( $post_id );
        $link = get_permalink( $post_id );
        $body .= '#' . $post_id . ' — ' . ( $title ?: 'Untitled' ) . "\n";
        $body .= $link . "\n\n";
        $auto_favourites_lines[] = ( $title ?: 'Untitled' ) . ' — ' . $link;
      }
    } else {
      $body .= "No valid favourites were submitted.\n";
    }

    $headers = array(
      'From: ' . ( $full_name ?: 'Website Enquiry' ) . ' <info@peraproperty.com>',
      'Content-Type: text/plain; charset=UTF-8',
    );

    if ( is_email( $email ) ) {
      $headers[] = 'Reply-To: ' . $full_name . ' <' . $email . '>';
    }

    $sent = wp_mail( $to, $subject, $body, $headers );
    pera_forms_debug_log( 'mail_result', array( 'form_key' => 'fav_enquiry_action', 'handler' => __FUNCTION__, 'wp_mail' => $sent ? '1' : '0' ) );

    if ( $sent && is_email( $email ) ) {
      $first = pera_enquiry_autoreply_first_name( $full_name );
      $greeting = $first ? 'Hello ' . $first . ',' : 'Hello,';
      $auto_lines = array(
        $greeting,
        "Thanks for your favourites enquiry. We'll respond shortly with details on the properties below.",
        'Requested properties:',
      );

      if ( ! empty( $auto_favourites_lines ) ) {
        foreach ( $auto_favourites_lines as $line ) {
          $auto_lines[] = '- ' . $line;
        }
      } else {
        $auto_lines[] = '- No properties were received.';
      }

      $auto_lines[] = 'If you need to add or update your list, reply to this email.';
      $auto_lines[] = 'Pera Property';
      $auto_lines[] = 'info@peraproperty.com';

      $auto_subject = 'We received your favourites enquiry';
      pera_send_enquiry_autoreply( 'favourites', $email, $auto_subject, $auto_lines );
    }

    if ( $sent && is_user_logged_in() && ! empty( $ids ) ) {
      update_user_meta(
        get_current_user_id(),
        'pera_last_fav_enquiry',
        array(
          'timestamp' => current_time( 'mysql' ),
          'ids'       => array_values( array_unique( array_map( 'absint', $ids ) ) ),
        )
      );
    }

    $redirect = ! empty( $_POST['_wp_http_referer'] )
      ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) )
      : home_url( '/my-favourites/' );
    $redirect = preg_replace( '/#.*$/', '', $redirect );
    $redirect = add_query_arg( 'enquiry', $sent ? 'sent' : 'failed', $redirect );
    $redirect .= '#favourites-enquiry';

    pera_forms_debug_log( 'redirect', array( 'form_key' => 'fav_enquiry_action', 'handler' => __FUNCTION__, 'redirect' => $redirect ) );
    wp_safe_redirect( $redirect );
    exit;
  }

  /* ==============================
   * B) CITIZENSHIP ENQUIRY BRANCH
   * Trigger: <input type="hidden" name="pera_citizenship_action" value="1">
   * ============================== */
  if ( isset( $_POST['pera_citizenship_action'] ) ) {
    pera_forms_debug_log( 'handler_hit', array( 'form_key' => 'pera_citizenship_action', 'route' => 'init', 'handler' => __FUNCTION__ ) );

    $cit_nonce = isset( $_POST['pera_citizenship_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pera_citizenship_nonce'] ) ) : '';
    if ( '' === $cit_nonce || ! wp_verify_nonce( $cit_nonce, 'pera_citizenship_enquiry' ) ) {
      pera_forms_debug_log( 'nonce_fail', array( 'form_key' => 'pera_citizenship_action', 'handler' => __FUNCTION__, 'nonce_status' => '' === $cit_nonce ? 'missing' : 'invalid' ) );
      pera_forms_nonce_failure_redirect( 'pera_citizenship_action', '/citizenship-by-investment/', 'enquiry', '#citizenship-form' );
    }
    pera_forms_debug_log( 'nonce_pass', array( 'form_key' => 'pera_citizenship_action', 'handler' => __FUNCTION__, 'nonce_status' => 'pass' ) );

    $name  = isset( $_POST['name'] )  ? sanitize_text_field( wp_unslash( $_POST['name'] ) )  : '';
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) )      : '';

    $enquiry_type = isset( $_POST['enquiry_type'] ) ? sanitize_text_field( wp_unslash( $_POST['enquiry_type'] ) ) : '';
    $family       = isset( $_POST['family'] )       ? sanitize_text_field( wp_unslash( $_POST['family'] ) )       : '';
    $message      = isset( $_POST['message'] )      ? wp_kses_post( wp_unslash( $_POST['message'] ) )             : '';

    $contact_methods = array();
    if ( ! empty( $_POST['contact_method'] ) && is_array( $_POST['contact_method'] ) ) {
      $contact_methods = array_map( 'sanitize_text_field', wp_unslash( $_POST['contact_method'] ) );
    }

    $to      = 'info@peraproperty.com';
    $subject = 'New Citizenship Enquiry from ' . $name;

    $body  = "New citizenship enquiry submitted:\n\n";
    $body .= "Name: {$name}\n";
    $body .= "Phone: {$phone}\n";
    $body .= "Email: {$email}\n\n";

    $body .= "Preferred contact method(s): " . ( ! empty( $contact_methods ) ? implode( ', ', $contact_methods ) : 'Not specified' ) . "\n";
    $body .= "Type of enquiry: {$enquiry_type}\n";
    $body .= "Family members: {$family}\n\n";
    $body .= "Questions / Comments:\n{$message}\n";

    $headers = array();
    if ( is_email( $email ) ) {
      $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
    }

    $sent = wp_mail( $to, $subject, $body, $headers );
    pera_forms_debug_log( 'mail_result', array( 'form_key' => 'pera_citizenship_action', 'handler' => __FUNCTION__, 'wp_mail' => $sent ? '1' : '0' ) );

    if ( $sent && is_email( $email ) ) {
      $first_name = pera_enquiry_autoreply_first_name( $name );
      $greeting   = $first_name ? 'Hello ' . $first_name . ',' : 'Hello,';

      $auto_lines = array(
        $greeting,
        "We've received your enquiry and recorded the details below.",
        'Name: ' . ( $name ?: 'Not provided' ),
        'Enquiry type: ' . ( $enquiry_type ?: 'Not specified' ),
        'Preferred contact methods: ' . ( ! empty( $contact_methods ) ? implode( ', ', $contact_methods ) : 'Not specified' ),
        'Family members: ' . ( $family ?: 'Not specified' ),
        "If any of these details are incorrect, reply to this email and we'll update them.",
        'A consultant will review and contact you via your preferred method.',
        'If you need to add details, reply to this email.',
        'Pera Property',
        'info@peraproperty.com',
      );

      $auto_subject = 'We received your citizenship enquiry';
      pera_send_enquiry_autoreply( 'citizenship', $email, $auto_subject, $auto_lines );
    }

    $status   = $sent ? 'ok' : 'mail-failed';
    $redirect = home_url( '/citizenship-by-investment/?enquiry=' . $status . '#citizenship-form' );

    pera_forms_debug_log( 'redirect', array( 'form_key' => 'pera_citizenship_action', 'handler' => __FUNCTION__, 'redirect' => $redirect ) );
    wp_safe_redirect( $redirect );
    exit;
  }

  // If neither branch, do nothing.
}

/**
 * Gateway on init: decide whether to call the handler at all.
 * Keeps the original behaviour but now supports both forms.
 */
function pera_maybe_handle_citizenship_enquiry() {

  if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    return;
  }

  if ( isset( $_POST['sr_action'] ) || isset( $_POST['pera_citizenship_action'] ) || isset( $_POST['fav_enquiry_action'] ) ) {
    pera_handle_citizenship_enquiry();
  }
}
add_action( 'init', 'pera_maybe_handle_citizenship_enquiry' );

function pera_ajax_get_enquiry_nonces() {
  $ip = '';
  if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
    $parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
    $ip    = trim( $parts[0] );
  } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
    $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
  }

  $rate_key = 'pera_enquiry_nonce_refresh_' . md5( $ip );
  if ( get_transient( $rate_key ) ) {
    pera_forms_debug_log( 'nonce_refresh_rate_limited', array( 'form_key' => 'public_enquiry_nonce_refresh', 'handler' => __FUNCTION__ ) );
    wp_send_json_error( array( 'message' => 'Too many requests.' ), 429 );
  }

  set_transient( $rate_key, 1, 30 );

  $payload = array(
    'sr_nonce'               => wp_create_nonce( 'pera_seller_landlord_enquiry' ),
    'fav_nonce'              => wp_create_nonce( 'pera_favourites_enquiry' ),
    'pera_citizenship_nonce' => wp_create_nonce( 'pera_citizenship_enquiry' ),
    'generated_at'           => time(),
  );

  pera_forms_debug_log( 'nonce_refresh', array( 'form_key' => 'public_enquiry_nonce_refresh', 'handler' => __FUNCTION__, 'nonce_status' => 'refresh' ) );
  wp_send_json_success( $payload );
}
add_action( 'wp_ajax_pera_get_enquiry_nonces', 'pera_ajax_get_enquiry_nonces' );
add_action( 'wp_ajax_nopriv_pera_get_enquiry_nonces', 'pera_ajax_get_enquiry_nonces' );
