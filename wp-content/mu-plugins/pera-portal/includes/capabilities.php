<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pera_portal_user_can_access')) {
    function pera_portal_user_can_access($user_id = 0)
    {
        if (defined('PERA_PORTAL_ACCESS_MODE') && PERA_PORTAL_ACCESS_MODE === 'dedicated_cap') {
            $access_cap = defined('PERA_PORTAL_ACCESS_CAP') ? (string) PERA_PORTAL_ACCESS_CAP : 'access_pera_portal';

            if ((int) $user_id > 0) {
                $user = get_userdata((int) $user_id);
                if (!$user instanceof WP_User) {
                    return false;
                }

                return user_can($user, 'manage_options') || user_can($user, $access_cap);
            }

            return current_user_can('manage_options') || current_user_can($access_cap);
        }

        if (function_exists('peracrm_user_can_access_crm')) {
            return peracrm_user_can_access_crm($user_id);
        }

        if ((int) $user_id > 0) {
            $user = get_userdata((int) $user_id);
            if (!$user instanceof WP_User) {
                return false;
            }

            return user_can($user, 'manage_options');
        }

        return current_user_can('manage_options');
    }
}

if (!function_exists('pera_portal_current_user_can_access')) {
    function pera_portal_current_user_can_access()
    {
        return function_exists('pera_portal_user_can_access')
            ? (bool) pera_portal_user_can_access(0)
            : current_user_can('manage_options');
    }
}
