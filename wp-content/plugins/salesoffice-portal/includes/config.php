<?php

if (!defined('ABSPATH')) {
    exit;
}

define('SO_PORTAL_SHORTCODE_TAG', 'so_portal');
define('SO_PORTAL_REST_NAMESPACE', 'salesoffice-portal/v1');

/**
 * Public read by default. If later you want token-gated read,
 * change this to a stricter callback.
 */
function so_portal_can_read()
{
    return true;
}
