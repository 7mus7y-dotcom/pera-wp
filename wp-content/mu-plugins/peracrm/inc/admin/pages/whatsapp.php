<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_handle_whatsapp_settings_save()
{
    if (!peracrm_admin_user_can_manage()) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_whatsapp_settings');

    $raw = isset($_POST['peracrm_whatsapp']) && is_array($_POST['peracrm_whatsapp'])
        ? wp_unslash($_POST['peracrm_whatsapp'])
        : [];

    peracrm_whatsapp_save_settings($raw);

    wp_safe_redirect(add_query_arg([
        'post_type' => 'crm_client',
        'page' => 'peracrm-whatsapp',
        'updated' => '1',
    ], admin_url('edit.php')));
    exit;
}

function peracrm_render_whatsapp_page()
{
    if (!peracrm_admin_user_can_manage()) {
        wp_die('Unauthorized');
    }

    $settings = peracrm_whatsapp_get_settings();
    $diag = peracrm_whatsapp_get_diagnostic();
    $count = (int) peracrm_with_target_blog(static function () {
        return function_exists('peracrm_whatsapp_count_messages') ? peracrm_whatsapp_count_messages() : 0;
    });

    $endpoint = esc_url_raw(rest_url('peracrm/v1/whatsapp/webhook'));

    echo '<div class="wrap">';
    echo '<h1>WhatsApp Inbound</h1>';

    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success"><p>WhatsApp settings saved.</p></div>';
    }

    echo '<h2>Webhook diagnostics</h2>';
    echo '<table class="widefat striped" style="max-width:900px">';
    echo '<tbody>';
    echo '<tr><th scope="row">Webhook endpoint</th><td><code>' . esc_html($endpoint) . '</code></td></tr>';
    echo '<tr><th scope="row">Enabled</th><td>' . (!empty($settings['enabled']) ? 'Yes' : 'No') . '</td></tr>';
    echo '<tr><th scope="row">Phone number ID</th><td>' . ($settings['phone_number_id'] !== '' ? esc_html($settings['phone_number_id']) : 'Missing') . '</td></tr>';
    echo '<tr><th scope="row">Access token</th><td>' . ($settings['access_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['access_token'])) : 'Missing') . '</td></tr>';
    echo '<tr><th scope="row">Verify token</th><td>' . ($settings['verify_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['verify_token'])) : 'Missing') . '</td></tr>';
    echo '<tr><th scope="row">Last inbound webhook received</th><td>' . ($diag['last_received_at'] ? esc_html($diag['last_received_at']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">Last webhook status</th><td>' . ($diag['last_status'] ? esc_html($diag['last_status']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">Latest error summary</th><td>' . ($diag['last_error'] ? esc_html($diag['last_error']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">Stored WhatsApp messages</th><td>' . esc_html((string) $count) . '</td></tr>';
    echo '</tbody>';
    echo '</table>';

    echo '<h2>Settings</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px">';
    wp_nonce_field('peracrm_whatsapp_settings');
    echo '<input type="hidden" name="action" value="peracrm_save_whatsapp_settings" />';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">Enable inbound webhook</th><td><label><input type="checkbox" name="peracrm_whatsapp[enabled]" value="1" ' . checked(!empty($settings['enabled']), true, false) . ' /> Enabled</label></td></tr>';
    echo '<tr><th scope="row">Phone Number ID</th><td><input type="text" name="peracrm_whatsapp[phone_number_id]" class="regular-text" value="' . esc_attr((string) $settings['phone_number_id']) . '" /></td></tr>';
    echo '<tr><th scope="row">Access Token</th><td><input type="password" name="peracrm_whatsapp[access_token]" class="regular-text" value="" autocomplete="new-password" /><p class="description">Leave blank to keep existing token.</p></td></tr>';
    echo '<tr><th scope="row">Verify Token</th><td><input type="text" name="peracrm_whatsapp[verify_token]" class="regular-text" value="" placeholder="' . esc_attr(peracrm_whatsapp_mask_secret((string) $settings['verify_token'])) . '" /><p class="description">Set token used by Meta webhook verification.</p></td></tr>';
    echo '<tr><th scope="row">Test mode</th><td><label><input type="checkbox" name="peracrm_whatsapp[test_mode]" value="1" ' . checked(!empty($settings['test_mode']), true, false) . ' /> Enable non-production handling</label></td></tr>';
    echo '</tbody></table>';
    submit_button('Save WhatsApp settings');
    echo '</form>';

    echo '</div>';
}
