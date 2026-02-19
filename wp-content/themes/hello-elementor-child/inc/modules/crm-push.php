<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', function () {
  if ( ! is_user_logged_in() || ! function_exists( 'pera_is_crm_route' ) || ! pera_is_crm_route() ) {
    return;
  }

  wp_enqueue_script(
    'pera-crm-push',
    get_stylesheet_directory_uri() . '/js/crm-push.js',
    array(),
    pera_get_asset_version( '/js/crm-push.js' ),
    true
  );

  $public_key = function_exists( 'peracrm_push_get_public_config' ) ? peracrm_push_get_public_config() : array();

  wp_localize_script(
    'pera-crm-push',
    'peraCrmPush',
    array(
      'swUrl'          => esc_url_raw( (string) ( $public_key['swUrl'] ?? home_url( '/peracrm-sw.js' ) ) ),
      'publicKey'      => (string) ( $public_key['publicKey'] ?? ( defined( 'PERACRM_VAPID_PUBLIC_KEY' ) ? (string) PERACRM_VAPID_PUBLIC_KEY : '' ) ),
      'subscribeUrl'   => esc_url_raw( (string) ( $public_key['subscribeUrl'] ?? rest_url( 'peracrm/v1/push/subscribe' ) ) ),
      'unsubscribeUrl' => esc_url_raw( (string) ( $public_key['unsubscribeUrl'] ?? rest_url( 'peracrm/v1/push/unsubscribe' ) ) ),
      'digestRunUrl'   => esc_url_raw( (string) ( $public_key['digestRunUrl'] ?? rest_url( 'peracrm/v1/push/digest/run' ) ) ),
      'debugUrl'       => esc_url_raw( (string) ( $public_key['debugUrl'] ?? rest_url( 'peracrm/v1/push/debug' ) ) ),
      'canRunDigest'   => isset( $public_key['canRunDigest'] ) ? (bool) $public_key['canRunDigest'] : ( function_exists( 'peracrm_push_user_can_run_digest' ) ? (bool) peracrm_push_user_can_run_digest( get_current_user_id() ) : false ),
      'isConfigured'   => isset( $public_key['isConfigured'] ) ? (bool) $public_key['isConfigured'] : false,
      'missingReasons' => isset( $public_key['missingReasons'] ) && is_array( $public_key['missingReasons'] ) ? $public_key['missingReasons'] : array(),
      'debug'          => function_exists( 'peracrm_push_debug_snapshot' ) ? peracrm_push_debug_snapshot( get_current_user_id() ) : array(),
      'clickUrl'       => (string) ( $public_key['clickUrl'] ?? '/crm/tasks/' ),
      'restNonce'      => wp_create_nonce( 'wp_rest' ),
    )
  );
}, 45 );
