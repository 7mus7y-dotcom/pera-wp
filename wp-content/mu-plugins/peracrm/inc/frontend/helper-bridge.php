<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('peracrm_frontend_bridge_include')) {
    function peracrm_frontend_bridge_include(string $plugin_relative): void
    {
        $plugin_file = trailingslashit(PERACRM_INC) . ltrim($plugin_relative, '/');
        if (file_exists($plugin_file)) {
            require_once $plugin_file;
        }
    }
}

peracrm_frontend_bridge_include('frontend-data/crm-data.php');
peracrm_frontend_bridge_include('frontend-data/crm-client-view.php');
