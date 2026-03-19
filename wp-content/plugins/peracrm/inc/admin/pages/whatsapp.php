<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_admin_is_real_whatsapp_logs_screen($hook = '')
{
    if (is_admin()) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page === 'pera-whatsapp-logs') {
            return true;
        }

        if ($hook !== '') {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && isset($screen->id) && $screen->id === 'toplevel_page_pera-whatsapp-logs') {
                return true;
            }
        }
    }

    return false;
}

function peracrm_render_whatsapp_page()
{
    if (!peracrm_whatsapp_logs_user_can_access()) {
        wp_die('Unauthorized');
    }

    $state = peracrm_whatsapp_get_logs_view_state($_GET);
    $count_callback = static function () {
        return function_exists('peracrm_whatsapp_click_logs_count') ? (int) peracrm_whatsapp_click_logs_count() : 0;
    };
    $count_result = function_exists('peracrm_whatsapp_logs_with_target_blog') ? peracrm_whatsapp_logs_with_target_blog($count_callback) : $count_callback();
    $count = is_wp_error($count_result) ? 0 : (int) $count_result;

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('WhatsApp Click Logs', 'peracrm') . '</h1>';
    echo '<p>' . esc_html__('Front-end and admin click-log views now share the same website WhatsApp click dataset.', 'peracrm') . '</p>';
    echo '<p><strong>' . esc_html__('Total click logs:', 'peracrm') . '</strong> ' . esc_html((string) $count) . '</p>';
    echo peracrm_whatsapp_render_logs_panel($state, 'admin');
    echo '</div>';
}
