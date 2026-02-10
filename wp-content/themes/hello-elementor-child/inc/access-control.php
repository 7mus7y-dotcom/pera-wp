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

if ( ! function_exists( 'pera_should_show_admin_bar' ) ) {
  /**
   * Show admin bar in wp-admin, and on front-end for administrators/employees.
   */
  function pera_should_show_admin_bar(): bool {
    if ( is_admin() ) {
      return true;
    }

    if ( ! is_user_logged_in() ) {
      return false;
    }

    return pera_is_frontend_admin_equivalent();
  }
}

add_filter( 'show_admin_bar', function ( bool $show ): bool {
  if ( is_admin() ) {
    return $show;
  }

  return pera_should_show_admin_bar();
}, 20 );
