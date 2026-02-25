<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_format_label($value)
{
    return sanitize_text_field((string) $value);
}
