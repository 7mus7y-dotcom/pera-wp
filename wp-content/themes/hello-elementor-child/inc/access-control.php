<?php
/**
 * Access control helpers for employee/admin equivalents.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! function_exists( 'pera_is_employee' ) ) {
  /**
   * Check whether a user has the Employee role.
   */
  function pera_is_employee( int $user_id = 0 ): bool {
    $user = $user_id > 0 ? get_user_by( 'id', $user_id ) : wp_get_current_user();

    if ( ! $user || ! $user->exists() ) {
      return false;
    }

    return in_array( 'employee', (array) $user->roles, true );
  }
}

if ( ! function_exists( 'pera_is_frontend_admin_equivalent' ) ) {
  /**
   * Check whether a user should see admin-equivalent front-end content.
   */
  function pera_is_frontend_admin_equivalent( int $user_id = 0 ): bool {
    $user = $user_id > 0 ? get_user_by( 'id', $user_id ) : wp_get_current_user();

    if ( ! $user || ! $user->exists() ) {
      return false;
    }

    $roles = (array) $user->roles;

    return in_array( 'administrator', $roles, true ) || in_array( 'employee', $roles, true );
  }
}

add_filter( 'show_admin_bar', function ( bool $show ): bool {
  if ( ! pera_is_employee() ) {
    return $show;
  }

  $user = wp_get_current_user();
  if ( ! $user || ! $user->exists() ) {
    return $show;
  }

  if ( in_array( 'administrator', (array) $user->roles, true ) ) {
    return $show;
  }

  return false;
}, 20 );
