<?php

if (!defined('ABSPATH')) {
    exit;
}

class PeraPortalAccessService
{
    public static function userCanAccess($user_id = 0)
    {
        return function_exists('pera_portal_user_can_access')
            ? pera_portal_user_can_access((int) $user_id)
            : false;
    }
}
