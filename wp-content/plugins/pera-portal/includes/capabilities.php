<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pera_portal_user_can_access')) {
    function pera_portal_user_can_access($user_id = 0)
    {
        if (is_multisite()) {
            if ((int) $user_id > 0) {
                if (is_super_admin((int) $user_id)) {
                    return true;
                }

                $user = get_userdata((int) $user_id);
                if ($user instanceof WP_User && (
                    user_can($user, 'manage_network')
                    || user_can($user, 'manage_network_options')
                )) {
                    return true;
                }
            } else {
                if (is_super_admin()) {
                    return true;
                }

                if (current_user_can('manage_network') || current_user_can('manage_network_options')) {
                    return true;
                }
            }
        }

        if (defined('PERA_PORTAL_ACCESS_MODE') && PERA_PORTAL_ACCESS_MODE === 'dedicated_cap') {
            $access_cap = defined('PERA_PORTAL_ACCESS_CAP') ? (string) PERA_PORTAL_ACCESS_CAP : 'access_pera_portal';

            if ((int) $user_id > 0) {
                $user = get_userdata((int) $user_id);
                if (!$user instanceof WP_User) {
                    return false;
                }

                if (user_can($user, 'manage_options') || user_can($user, $access_cap)) {
                    return true;
                }

                if (function_exists('peracrm_user_can_access_crm')) {
                    return peracrm_user_can_access_crm($user_id);
                }

                return false;
            }

            if (current_user_can('manage_options') || current_user_can($access_cap)) {
                return true;
            }

            if (function_exists('peracrm_user_can_access_crm')) {
                return peracrm_user_can_access_crm(0);
            }

            return false;
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

if (!function_exists('pera_portal_current_user_can_create_quotes')) {
    function pera_portal_current_user_can_create_quotes()
    {
        if (!pera_portal_current_user_can_access()) {
            return false;
        }

        return current_user_can('manage_options') || current_user_can('pera_portal_create_quotes') || pera_portal_current_user_can_access();
    }
}

if (!function_exists('pera_portal_current_user_can_manage_quotes')) {
    function pera_portal_current_user_can_manage_quotes()
    {
        if (!pera_portal_current_user_can_access()) {
            return false;
        }

        return current_user_can('manage_options') || current_user_can('pera_portal_manage_quotes') || pera_portal_current_user_can_access();
    }
}

if (!function_exists('pera_portal_current_user_can_revoke_quotes')) {
    function pera_portal_current_user_can_revoke_quotes()
    {
        if (!pera_portal_current_user_can_access()) {
            return false;
        }

        return current_user_can('manage_options') || current_user_can('pera_portal_revoke_quotes') || pera_portal_current_user_can_access();
    }
}
