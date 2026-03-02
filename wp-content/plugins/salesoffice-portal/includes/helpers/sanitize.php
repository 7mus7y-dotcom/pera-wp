<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_sanitize_id($value)
{
    return absint($value);
}
