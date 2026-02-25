<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_user_can_access($user_id = 0)
{
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
