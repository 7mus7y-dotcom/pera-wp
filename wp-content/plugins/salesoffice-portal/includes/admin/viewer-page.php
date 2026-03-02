<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_register_viewer_submenu()
{
    add_submenu_page(
        'so-portal',
        __('Viewer', 'salesoffice-portal'),
        __('Viewer', 'salesoffice-portal'),
        'read',
        'so-portal-viewer',
        'so_portal_render_viewer_page'
    );
}

function so_portal_hide_disallowed_viewer_submenu()
{
    if (function_exists('so_portal_user_is_allowed_for_admin_ui') && so_portal_user_is_allowed_for_admin_ui()) {
        return;
    }

    remove_submenu_page('so-portal', 'so-portal-viewer');
}

function so_portal_render_viewer_page()
{
    if (!so_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'salesoffice-portal'));
    }

    $building_id = isset($_GET['building_id']) ? absint(wp_unslash($_GET['building_id'])) : 0;
    $floor_id = isset($_GET['floor_id']) ? absint(wp_unslash($_GET['floor_id'])) : 0;

    so_portal_set_script_config([
        'rest_url' => esc_url_raw(rest_url(SO_PORTAL_REST_NAMESPACE . '/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'building_id' => $building_id,
        'floor_id' => $floor_id,
    ]);
    so_portal_enqueue_viewer_assets();

    echo '<div class="wrap"><h1>' . esc_html__('Salesoffice Portal Viewer', 'salesoffice-portal') . '</h1>';

    $template_path = SO_PORTAL_PATH . 'templates/portal-shell.php';
    if (file_exists($template_path)) {
        include $template_path;
    }

    echo '</div>';
}

add_action('admin_menu', 'so_portal_register_viewer_submenu');
add_action('network_admin_menu', 'so_portal_register_viewer_submenu');
add_action('admin_menu', 'so_portal_hide_disallowed_viewer_submenu', 99);
add_action('network_admin_menu', 'so_portal_hide_disallowed_viewer_submenu', 99);
