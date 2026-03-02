<?php

if (!defined('ABSPATH')) {
    exit;
}

class SoPortalAccessService
{
    public static function userCanAccess($user_id = 0)
    {
        return function_exists('so_portal_user_can_access')
            ? so_portal_user_can_access((int) $user_id)
            : false;
    }
}
