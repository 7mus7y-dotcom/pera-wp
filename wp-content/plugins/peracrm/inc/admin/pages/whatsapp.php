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
    if (!peracrm_admin_user_can_manage()) {
        wp_die('Unauthorized');
    }

    $settings = peracrm_whatsapp_get_settings();
    $diag = peracrm_whatsapp_get_diagnostic();
    $messages = function_exists('peracrm_whatsapp_get_messages')
        ? peracrm_whatsapp_get_messages(['per_page' => 10, 'paged' => 1])
        : ['rows' => [], 'pagination' => ['total' => 0]];
    $message_rows = isset($messages['rows']) && is_array($messages['rows']) ? $messages['rows'] : [];
    $message_total = isset($messages['pagination']['total']) ? (int) $messages['pagination']['total'] : 0;
    $notification_rows = function_exists('peracrm_notification_log_recent')
        ? peracrm_notification_log_recent(12)
        : [];
    $endpoint = esc_url_raw(rest_url('peracrm/v1/whatsapp/webhook'));

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('CRM WhatsApp', 'peracrm') . '</h1>';
    echo '<p>' . esc_html__('Manage WhatsApp Business webhook credentials, verify inbound delivery status, and review recent synced CRM messages. This page is reserved for CRM WhatsApp integration settings and diagnostics.', 'peracrm') . '</p>';

    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success"><p>' . esc_html__('WhatsApp settings saved.', 'peracrm') . '</p></div>';
    }
    if (isset($_GET['enquiry_test'])) {
        $ok = sanitize_key((string) wp_unslash($_GET['enquiry_test'])) === '1';
        echo '<div class="notice ' . ($ok ? 'notice-success' : 'notice-warning') . '"><p>' . esc_html($ok ? 'Enquiry notification test sent to both channels.' : 'Enquiry notification test executed with at least one failed channel.') . '</p></div>';
    }

    echo '<h2>' . esc_html__('Webhook diagnostics', 'peracrm') . '</h2>';
    echo '<table class="widefat striped" style="max-width:900px"><tbody>';
    echo '<tr><th scope="row">' . esc_html__('Webhook endpoint', 'peracrm') . '</th><td><code>' . esc_html($endpoint) . '</code></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Integration enabled', 'peracrm') . '</th><td>' . (!empty($settings['enabled']) ? esc_html__('Yes', 'peracrm') : esc_html__('No', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Phone number ID', 'peracrm') . '</th><td>' . ($settings['phone_number_id'] !== '' ? esc_html($settings['phone_number_id']) : esc_html__('Missing', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Access token', 'peracrm') . '</th><td>' . ($settings['access_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['access_token'])) : esc_html__('Missing', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Verify token', 'peracrm') . '</th><td>' . ($settings['verify_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['verify_token'])) : esc_html__('Missing', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Graph API version', 'peracrm') . '</th><td>' . esc_html((string) ($settings['graph_api_version'] ?? 'v22.0')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Test mode', 'peracrm') . '</th><td>' . (!empty($settings['test_mode']) ? esc_html__('Enabled', 'peracrm') : esc_html__('Disabled', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Last inbound webhook received', 'peracrm') . '</th><td>' . ($diag['last_received_at'] ? esc_html($diag['last_received_at']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Last webhook status', 'peracrm') . '</th><td>' . ($diag['last_status'] ? esc_html($diag['last_status']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Latest error summary', 'peracrm') . '</th><td>' . ($diag['last_error'] ? esc_html($diag['last_error']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Stored WhatsApp messages', 'peracrm') . '</th><td>' . esc_html((string) $message_total) . '</td></tr>';
    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Integration settings', 'peracrm') . '</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px">';
    wp_nonce_field('peracrm_whatsapp_settings');
    echo '<input type="hidden" name="action" value="peracrm_save_whatsapp_settings" />';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">' . esc_html__('Enable inbound webhook', 'peracrm') . '</th><td><label><input type="checkbox" name="peracrm_whatsapp[enabled]" value="1" ' . checked(!empty($settings['enabled']), true, false) . ' /> ' . esc_html__('Enabled', 'peracrm') . '</label><p class="description">' . esc_html__('Turn on WhatsApp Business webhook processing for CRM ingestion.', 'peracrm') . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Phone Number ID', 'peracrm') . '</th><td><input type="text" name="peracrm_whatsapp[phone_number_id]" class="regular-text" value="' . esc_attr((string) $settings['phone_number_id']) . '" /><p class="description">' . esc_html__('Meta WhatsApp Business phone number identifier used by the integration.', 'peracrm') . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Access Token', 'peracrm') . '</th><td><input type="password" name="peracrm_whatsapp[access_token]" class="regular-text" value="" autocomplete="new-password" /><p class="description">' . esc_html__('Leave blank to keep the existing token. Paste a new token only when rotating credentials.', 'peracrm') . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Verify Token', 'peracrm') . '</th><td><input type="text" name="peracrm_whatsapp[verify_token]" class="regular-text" value="" placeholder="' . esc_attr(peracrm_whatsapp_mask_secret((string) $settings['verify_token'])) . '" /><p class="description">' . esc_html__('Used by Meta during webhook verification requests.', 'peracrm') . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Graph API version', 'peracrm') . '</th><td><input type="text" name="peracrm_whatsapp[graph_api_version]" class="regular-text" value="' . esc_attr((string) ($settings['graph_api_version'] ?? 'v22.0')) . '" /><p class="description">' . esc_html__('Version for outbound WhatsApp Cloud API calls (example: v22.0).', 'peracrm') . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Test mode', 'peracrm') . '</th><td><label><input type="checkbox" name="peracrm_whatsapp[test_mode]" value="1" ' . checked(!empty($settings['test_mode']), true, false) . ' /> ' . esc_html__('Enable non-production handling', 'peracrm') . '</label><p class="description">' . esc_html__('Keep this enabled while validating the integration outside production traffic.', 'peracrm') . '</p></td></tr>';
    echo '</tbody></table>';
    submit_button(__('Save WhatsApp settings', 'peracrm'));
    echo '</form>';

    echo '<h2>' . esc_html__('Enquiry notification test', 'peracrm') . '</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px">';
    wp_nonce_field('peracrm_enquiry_notification_test');
    echo '<input type="hidden" name="action" value="peracrm_test_enquiry_notifications" />';
    submit_button(__('Send test WhatsApp + email alert', 'peracrm'), 'secondary', 'submit', false);
    echo '<p class="description">' . esc_html__('Sends a sample enquiry_received alert via both channels and stores a test row in the notification log.', 'peracrm') . '</p>';
    echo '</form>';

    echo '<h2>' . esc_html__('Recent synced messages', 'peracrm') . '</h2>';
    echo '<p>' . esc_html__('This preview shows the latest inbound WhatsApp message records stored for CRM sync diagnostics. It is intentionally separate from the website click-log pages.', 'peracrm') . '</p>';
    echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>';
    echo '<th scope="col">' . esc_html__('Created', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Direction', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Client', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Contact', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Phone', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Message', 'peracrm') . '</th>';
    echo '</tr></thead><tbody>';

    if (empty($message_rows)) {
        echo '<tr><td colspan="6">' . esc_html__('No WhatsApp messages have been synced yet.', 'peracrm') . '</td></tr>';
    } else {
        foreach ($message_rows as $row) {
            $client_id = isset($row['client_id']) ? (int) $row['client_id'] : 0;
            $client_label = '—';
            if ($client_id > 0) {
                $client_label = get_the_title($client_id);
                if ($client_label === '') {
                    $client_label = 'Client #' . $client_id;
                }
                $client_url = get_edit_post_link($client_id);
                $client_label = $client_url ? '<a href="' . esc_url($client_url) . '">' . esc_html($client_label) . '</a>' : esc_html($client_label);
            }

            $contact_parts = [];
            if (!empty($row['whatsapp_contact_name'])) {
                $contact_parts[] = esc_html((string) $row['whatsapp_contact_name']);
            }
            if (!empty($row['message_type'])) {
                $contact_parts[] = '<span class="description">' . esc_html(ucfirst((string) $row['message_type'])) . '</span>';
            }

            $message_preview = '—';
            if (!empty($row['message_body'])) {
                $message_preview = '<div class="peracrm-whatsapp-log-message">' . esc_html(wp_trim_words((string) $row['message_body'], 24, '…')) . '</div>';
            } elseif (!empty($row['media_url'])) {
                $message_preview = '<a href="' . esc_url((string) $row['media_url']) . '" target="_blank" rel="noopener">' . esc_html__('Media attachment', 'peracrm') . '</a>';
            }

            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html(ucfirst((string) ($row['direction'] ?? ''))) . '</td>';
            echo '<td>' . $client_label . '</td>';
            echo '<td>' . (!empty($contact_parts) ? implode('<br />', $contact_parts) : '—') . '</td>';
            echo '<td><code>' . esc_html((string) ($row['phone_e164'] ?? '')) . '</code></td>';
            echo '<td>' . $message_preview . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Recent enquiry notification logs', 'peracrm') . '</h2>';
    echo '<table class="widefat striped" style="max-width:1200px"><thead><tr>';
    echo '<th scope="col">' . esc_html__('When', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Event', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Source', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Client', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Channel', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Recipient', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Status', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Provider', 'peracrm') . '</th>';
    echo '</tr></thead><tbody>';
    if (empty($notification_rows)) {
        echo '<tr><td colspan="8">' . esc_html__('No notification rows yet.', 'peracrm') . '</td></tr>';
    } else {
        foreach ($notification_rows as $row) {
            $client_id = isset($row['client_id']) ? (int) $row['client_id'] : 0;
            $client_text = $client_id > 0 ? ('#' . $client_id) : '—';
            if ($client_id > 0) {
                $client_url = function_exists('pera_crm_get_client_view_url')
                    ? pera_crm_get_client_view_url($client_id)
                    : home_url('/crm/client/' . $client_id . '/');
                $client_text = '<a href="' . esc_url($client_url) . '" target="_blank" rel="noopener">' . esc_html('#' . $client_id) . '</a>';
            }

            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['event_type'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['source'] ?? '')) . '</td>';
            echo '<td>' . $client_text . '</td>';
            echo '<td>' . esc_html((string) ($row['channel'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['recipient'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['provider_code'] ?? '')) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';
}
