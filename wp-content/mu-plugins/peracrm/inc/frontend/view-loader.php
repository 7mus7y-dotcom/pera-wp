<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('peracrm_frontend_get_theme_path')) {
    function peracrm_frontend_get_theme_path(string $relative_path): string
    {
        return trailingslashit(get_stylesheet_directory()) . ltrim($relative_path, '/');
    }
}

if (!function_exists('peracrm_frontend_view_path')) {
    function peracrm_frontend_view_path(string $relative_path, string $theme_fallback_relative = ''): string
    {
        $plugin_path = trailingslashit(PERACRM_INC) . 'views/' . ltrim($relative_path, '/');
        if (file_exists($plugin_path)) {
            return $plugin_path;
        }

        if ($theme_fallback_relative !== '') {
            $theme_path = peracrm_frontend_get_theme_path($theme_fallback_relative);
            if (file_exists($theme_path)) {
                return $theme_path;
            }
        }

        return '';
    }
}

if (!function_exists('peracrm_frontend_render_view')) {
    function peracrm_frontend_render_view(string $relative_path, array $args = [], string $theme_fallback_relative = ''): bool
    {
        $template_path = peracrm_frontend_view_path($relative_path, $theme_fallback_relative);
        if ($template_path === '') {
            return false;
        }

        if (!empty($args)) {
            extract($args, EXTR_SKIP);
        }

        include $template_path;
        return true;
    }
}

if (!function_exists('peracrm_frontend_render_partial')) {
    function peracrm_frontend_render_partial(string $slug, array $args = []): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        $relative_path = 'partials/' . $slug . '.php';
        $theme_fallback = 'parts/' . $slug . '.php';

        return peracrm_frontend_render_view($relative_path, ['args' => $args] + $args, $theme_fallback);
    }
}
