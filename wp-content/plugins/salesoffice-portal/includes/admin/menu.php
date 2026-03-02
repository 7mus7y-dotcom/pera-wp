<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_register_admin_menu()
{
    add_menu_page(
        __('Salesoffice Portal', 'salesoffice-portal'),
        __('Salesoffice Portal', 'salesoffice-portal'),
        'read',
        'so-portal',
        'so_portal_render_admin_page',
        'dashicons-admin-multisite',
        3
    );
}

function so_portal_user_is_allowed_for_admin_ui()
{
    return function_exists('so_portal_current_user_can_access')
        ? (bool) so_portal_current_user_can_access()
        : false;
}

function so_portal_hide_disallowed_admin_menus()
{
    if (so_portal_user_is_allowed_for_admin_ui()) {
        return;
    }

    remove_menu_page('so-portal');
    remove_submenu_page('so-portal', 'so-portal');

    remove_menu_page('edit.php?post_type=pera_building');
    remove_menu_page('edit.php?post_type=pera_floor');
    remove_menu_page('edit.php?post_type=pera_unit');
}

function so_portal_render_admin_page()
{
    if (!so_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'salesoffice-portal'));
    }

    $floor_id = isset($_GET['floor_id']) ? absint(wp_unslash($_GET['floor_id'])) : 0;

    so_portal_set_script_config([
        'rest_url' => esc_url_raw(rest_url(SO_PORTAL_REST_NAMESPACE . '/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'building_id' => 0,
        'floor_id' => $floor_id,
    ]);
    so_portal_enqueue_viewer_assets();

    echo '<div class="wrap"><h1>' . esc_html__('Salesoffice Portal', 'salesoffice-portal') . '</h1>';

    $template_path = SO_PORTAL_PATH . 'templates/portal-shell.php';
    if (file_exists($template_path)) {
        include $template_path;
    }

    echo '</div>';
}

add_action('admin_menu', 'so_portal_register_admin_menu');
add_action('network_admin_menu', 'so_portal_register_admin_menu');
add_action('admin_menu', 'so_portal_hide_disallowed_admin_menus', 99);
add_action('network_admin_menu', 'so_portal_hide_disallowed_admin_menus', 99);
