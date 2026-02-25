<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_acf_save_json_path($path)
{
    return PERA_PORTAL_PATH . '/acf-json';
}
add_filter('acf/settings/save_json', 'pera_portal_acf_save_json_path');

function pera_portal_acf_load_json_paths($paths)
{
    $paths[] = PERA_PORTAL_PATH . '/acf-json';
    return $paths;
}
add_filter('acf/settings/load_json', 'pera_portal_acf_load_json_paths');
