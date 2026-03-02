<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_acf_save_json_path($path)
{
    unset($path);

    return SO_PORTAL_PATH . 'acf-json';
}

function so_portal_acf_load_json_paths($paths)
{
    if (!is_array($paths)) {
        $paths = [];
    }

    $json_path = SO_PORTAL_PATH . 'acf-json';

    if (!in_array($json_path, $paths, true)) {
        $paths[] = $json_path;
    }

    return $paths;
}

add_filter('acf/settings/save_json', 'so_portal_acf_save_json_path', 20);
add_filter('acf/settings/load_json', 'so_portal_acf_load_json_paths', 20);
