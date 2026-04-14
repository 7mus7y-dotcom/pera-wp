<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_enquiry_notifications_dispatch(array $outcome)
{
    $client_id = (int) ($outcome['client_id'] ?? 0);
    if ($client_id <= 0) {
        return;
    }

    if (!empty($outcome['is_duplicate_replay'])) {
        return;
    }

    $event = peracrm_enquiry_notifications_build_event($outcome);
    if (empty($event['event_key'])) {
        return;
    }

    $claim = peracrm_notification_log_claim_event($event);
    if (empty($claim['claimed'])) {
        return;
    }

    $admin_email = peracrm_enquiry_notifications_admin_email();
    $whatsapp_recipient = '+905452054356';

    $email_provider = new PeraCRM_Email_Provider();
    $email_result = $email_provider->send($event, $admin_email);
    peracrm_enquiry_notifications_log_attempt($event, 'email', $admin_email, $email_result);

    $whatsapp_provider = new PeraCRM_Whatsapp_Meta_Provider();
    $whatsapp_result = $whatsapp_provider->send($event, $whatsapp_recipient);
    peracrm_enquiry_notifications_log_attempt($event, 'whatsapp', $whatsapp_recipient, $whatsapp_result);
}

function peracrm_enquiry_notifications_log_attempt(array $event, $channel, $recipient, array $result)
{
    $ok = !empty($result['ok']);

    peracrm_notification_log_insert([
        'event_type' => (string) ($event['event_type'] ?? ''),
        'source' => (string) ($event['source'] ?? ''),
        'source_event_id' => $event['source_event_id'] ?? null,
        'fingerprint' => $event['fingerprint'] ?? null,
        'event_key' => (string) ($event['event_key'] ?? ''),
        'client_id' => (int) ($event['client_id'] ?? 0),
        'enquiry_id' => $event['enquiry_id'] ?? null,
        'channel' => sanitize_key((string) $channel),
        'recipient' => sanitize_text_field((string) $recipient),
        'status' => $ok ? 'sent' : 'failed',
        'provider_code' => (string) ($result['provider_code'] ?? ''),
        'http_status' => $result['http_status'] ?? null,
        'response_excerpt' => (string) ($result['response_excerpt'] ?? ''),
        'error_message' => (string) ($result['error_message'] ?? ''),
        'meta' => [
            'event' => $event,
            'result' => $result,
        ],
    ]);
}

function peracrm_enquiry_notifications_admin_email()
{
    $candidate = get_option('admin_email');
    $candidate = sanitize_email((string) $candidate);

    if ($candidate !== '' && is_email($candidate)) {
        return $candidate;
    }

    return '';
}

function peracrm_enquiry_notifications_handle_test_send()
{
    if (!function_exists('peracrm_admin_user_can_manage') || !peracrm_admin_user_can_manage()) {
        wp_die('Unauthorized');
    }

    check_admin_referer('peracrm_enquiry_notification_test');

    $sample_event = [
        'event_type' => peracrm_enquiry_notifications_event_type(),
        'source' => 'website_form',
        'source_event_id' => 'test-' . gmdate('YmdHis'),
        'fingerprint' => hash('sha256', 'test-' . wp_generate_uuid4()),
        'event_key' => hash('sha256', 'test|' . wp_generate_uuid4()),
        'client_id' => 0,
        'enquiry_id' => '',
        'name' => 'Test Lead',
        'phone' => '+905001112233',
        'email' => peracrm_enquiry_notifications_admin_email(),
        'interest' => 'Test project',
        'form_name' => 'Test action',
        'source_label' => 'Website',
        'client_url' => esc_url_raw(home_url('/crm/clients/')),
    ];

    $email_provider = new PeraCRM_Email_Provider();
    $email_result = $email_provider->send($sample_event, peracrm_enquiry_notifications_admin_email());

    $whatsapp_provider = new PeraCRM_Whatsapp_Meta_Provider();
    $whatsapp_result = $whatsapp_provider->send($sample_event, '+905452054356');

    peracrm_notification_log_insert([
        'event_type' => $sample_event['event_type'],
        'source' => $sample_event['source'],
        'source_event_id' => $sample_event['source_event_id'],
        'fingerprint' => $sample_event['fingerprint'],
        'event_key' => $sample_event['event_key'],
        'client_id' => 0,
        'enquiry_id' => '',
        'channel' => 'test_tool',
        'recipient' => 'admin',
        'status' => (!empty($email_result['ok']) && !empty($whatsapp_result['ok'])) ? 'sent' : 'failed',
        'provider_code' => 'manual_test',
        'meta' => [
            'email_result' => $email_result,
            'whatsapp_result' => $whatsapp_result,
        ],
    ]);

    wp_safe_redirect(add_query_arg([
        'post_type' => 'crm_client',
        'page' => 'peracrm-whatsapp',
        'enquiry_test' => (!empty($email_result['ok']) && !empty($whatsapp_result['ok'])) ? '1' : '0',
    ], admin_url('edit.php')));
    exit;
}
