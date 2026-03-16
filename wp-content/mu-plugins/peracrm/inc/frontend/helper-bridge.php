<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('peracrm_frontend_bridge_include')) {
    function peracrm_frontend_bridge_include(string $plugin_relative, string $theme_relative): void
    {
        $plugin_file = trailingslashit(PERACRM_INC) . ltrim($plugin_relative, '/');
        if (file_exists($plugin_file)) {
            require_once $plugin_file;
            return;
        }

        $theme_file = trailingslashit(get_stylesheet_directory()) . ltrim($theme_relative, '/');
        if (file_exists($theme_file)) {
            require_once $theme_file;
        }
    }
}

peracrm_frontend_bridge_include('frontend-data/crm-data.php', 'inc/crm-data.php');
peracrm_frontend_bridge_include('frontend-data/crm-client-view.php', 'inc/crm-client-view.php');
