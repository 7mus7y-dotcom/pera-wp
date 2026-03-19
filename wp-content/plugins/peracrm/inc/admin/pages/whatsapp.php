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

    $settings = peracrm_whatsapp_get_settings();
    $diag = peracrm_whatsapp_get_diagnostic();
    $state = peracrm_whatsapp_get_logs_view_state($_GET);
    $count_callback = static function () {
        return function_exists('peracrm_whatsapp_count_messages') ? (int) peracrm_whatsapp_count_messages() : 0;
    };
    $count_result = function_exists('peracrm_whatsapp_logs_with_target_blog') ? peracrm_whatsapp_logs_with_target_blog($count_callback) : $count_callback();
    $count = is_wp_error($count_result) ? 0 : (int) $count_result;
    $endpoint = esc_url_raw(rest_url('peracrm/v1/whatsapp/webhook'));

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('WhatsApp Inbound', 'peracrm') . '</h1>';

    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success"><p>' . esc_html__('WhatsApp settings saved.', 'peracrm') . '</p></div>';
    }

    echo '<h2>' . esc_html__('Webhook diagnostics', 'peracrm') . '</h2>';
    echo '<table class="widefat striped" style="max-width:900px"><tbody>';
    echo '<tr><th scope="row">' . esc_html__('Webhook endpoint', 'peracrm') . '</th><td><code>' . esc_html($endpoint) . '</code></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Enabled', 'peracrm') . '</th><td>' . (!empty($settings['enabled']) ? esc_html__('Yes', 'peracrm') : esc_html__('No', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Phone number ID', 'peracrm') . '</th><td>' . ($settings['phone_number_id'] !== '' ? esc_html($settings['phone_number_id']) : esc_html__('Missing', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Access token', 'peracrm') . '</th><td>' . ($settings['access_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['access_token'])) : esc_html__('Missing', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Verify token', 'peracrm') . '</th><td>' . ($settings['verify_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['verify_token'])) : esc_html__('Missing', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Last inbound webhook received', 'peracrm') . '</th><td>' . ($diag['last_received_at'] ? esc_html($diag['last_received_at']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Last webhook status', 'peracrm') . '</th><td>' . ($diag['last_status'] ? esc_html($diag['last_status']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Latest error summary', 'peracrm') . '</th><td>' . ($diag['last_error'] ? esc_html($diag['last_error']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Stored WhatsApp messages', 'peracrm') . '</th><td>' . esc_html((string) $count) . '</td></tr>';
    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Logs', 'peracrm') . '</h2>';
    echo peracrm_whatsapp_render_logs_panel($state, 'admin');

    echo '<h2>' . esc_html__('Settings', 'peracrm') . '</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px">';
    wp_nonce_field('peracrm_whatsapp_settings');
    echo '<input type="hidden" name="action" value="peracrm_save_whatsapp_settings" />';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">' . esc_html__('Enable inbound webhook', 'peracrm') . '</th><td><label><input type="checkbox" name="peracrm_whatsapp[enabled]" value="1" ' . checked(!empty($settings['enabled']), true, false) . ' /> ' . esc_html__('Enabled', 'peracrm') . '</label></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Phone Number ID', 'peracrm') . '</th><td><input type="text" name="peracrm_whatsapp[phone_number_id]" class="regular-text" value="' . esc_attr((string) $settings['phone_number_id']) . '" /></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Access Token', 'peracrm') . '</th><td><input type="password" name="peracrm_whatsapp[access_token]" class="regular-text" value="" autocomplete="new-password" /><p class="description">' . esc_html__('Leave blank to keep existing token.', 'peracrm') . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Verify Token', 'peracrm') . '</th><td><input type="text" name="peracrm_whatsapp[verify_token]" class="regular-text" value="" placeholder="' . esc_attr(peracrm_whatsapp_mask_secret((string) $settings['verify_token'])) . '" /><p class="description">' . esc_html__('Set token used by Meta webhook verification.', 'peracrm') . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Test mode', 'peracrm') . '</th><td><label><input type="checkbox" name="peracrm_whatsapp[test_mode]" value="1" ' . checked(!empty($settings['test_mode']), true, false) . ' /> ' . esc_html__('Enable non-production handling', 'peracrm') . '</label></td></tr>';
    echo '</tbody></table>';
    submit_button(__('Save WhatsApp settings', 'peracrm'));
    echo '</form>';
    echo '</div>';
}
