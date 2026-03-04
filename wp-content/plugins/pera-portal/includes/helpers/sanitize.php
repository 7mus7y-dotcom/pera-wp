<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_sanitize_id($value)
{
    return absint($value);
}
