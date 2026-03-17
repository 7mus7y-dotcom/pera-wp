<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('peracrm_frontend_view_path')) {
    function peracrm_frontend_view_path(string $relative_path): string
    {
        $plugin_path = trailingslashit(PERACRM_INC) . 'views/' . ltrim($relative_path, '/');
        if (file_exists($plugin_path)) {
            return $plugin_path;
        }

        return '';
    }
}

if (!function_exists('peracrm_frontend_render_view')) {
    function peracrm_frontend_render_view(string $relative_path, array $args = []): bool
    {
        $template_path = peracrm_frontend_view_path($relative_path);
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

        return peracrm_frontend_render_view($relative_path, ['args' => $args] + $args);
    }
}


if (!function_exists('peracrm_frontend_render_shell_header')) {
    function peracrm_frontend_render_shell_header(): void
    {
        $shell_header = peracrm_frontend_view_path('shell/header.php');
        if ($shell_header !== '') {
            include $shell_header;
            return;
        }

        do_action('get_header', null, array());
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
</head>
<body <?php body_class('crm-route'); ?>>
<?php wp_body_open(); ?>
        <?php
    }
}


if (!function_exists('peracrm_frontend_render_shell_footer')) {
    function peracrm_frontend_render_shell_footer(): void
    {
        $shell_footer = peracrm_frontend_view_path('shell/footer.php');
        if ($shell_footer !== '') {
            include $shell_footer;
            return;
        }

        do_action('get_footer');
        wp_footer();
        echo '</body>';
        echo '</html>';
    }
}
