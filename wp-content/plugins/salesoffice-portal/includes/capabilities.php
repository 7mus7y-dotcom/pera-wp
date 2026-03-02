<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('so_portal_user_can_access')) {
    function so_portal_user_can_access($user_id = 0)
    {
        $user_id = (int) $user_id;

        if (is_multisite()) {
            if ($user_id > 0 && is_super_admin($user_id)) {
                return true;
            }

            if ($user_id === 0 && is_super_admin()) {
                return true;
            }
        }

        $access_cap = defined('SO_PORTAL_ACCESS_CAP') ? (string) SO_PORTAL_ACCESS_CAP : 'access_salesoffice_portal';

        if ($user_id > 0) {
            $user = get_userdata($user_id);
            if (!$user instanceof WP_User) {
                return false;
            }

            if (user_can($user, 'manage_options')) {
                return true;
            }

            if (defined('SO_PORTAL_ACCESS_MODE') && SO_PORTAL_ACCESS_MODE === 'dedicated_cap') {
                return user_can($user, $access_cap);
            }

            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        if (defined('SO_PORTAL_ACCESS_MODE') && SO_PORTAL_ACCESS_MODE === 'dedicated_cap') {
            return current_user_can($access_cap);
        }

        return false;
    }
}

if (!function_exists('so_portal_current_user_can_access')) {
    function so_portal_current_user_can_access()
    {
        return so_portal_user_can_access(0);
    }
}
