<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_acf_save_json_path($path)
{
    return PERA_PORTAL_PATH . '/acf-json';
}

function pera_portal_acf_load_json_paths($paths)
{
    if (!is_array($paths)) {
        $paths = [];
    }

    $json_path = PERA_PORTAL_PATH . '/acf-json';
    if (!in_array($json_path, $paths, true)) {
        $paths[] = $json_path;
    }

    return $paths;
}

if (function_exists('acf') || class_exists('ACF')) {
    add_filter('acf/settings/save_json', 'pera_portal_acf_save_json_path');
    add_filter('acf/settings/load_json', 'pera_portal_acf_load_json_paths');
}
