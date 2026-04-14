<?php

if (!defined('ABSPATH')) {
    exit;
}

class PeraCRM_Email_Provider
{
    public function send(array $event, $recipient)
    {
        $recipient = sanitize_email((string) $recipient);
        if ($recipient === '' || !is_email($recipient)) {
            return [
                'ok' => false,
                'provider_code' => 'invalid_recipient',
                'http_status' => null,
                'response_excerpt' => '',
                'error_message' => 'Invalid admin email recipient',
            ];
        }

        $subject = sprintf(
            'PeraCRM: New enquiry received (%s)',
            sanitize_text_field((string) ($event['source_label'] ?? 'Unknown'))
        );

        $lines = [
            'New enquiry received',
            '',
            'Source: ' . sanitize_text_field((string) ($event['source_label'] ?? '')),
            'Name: ' . sanitize_text_field((string) ($event['name'] ?? '')),
            'Phone: ' . sanitize_text_field((string) ($event['phone'] ?? '')),
            'Email: ' . sanitize_email((string) ($event['email'] ?? '')),
            'Interested in: ' . sanitize_text_field((string) ($event['interest'] ?? '')),
            'Form: ' . sanitize_text_field((string) ($event['form_name'] ?? '')),
            'Client ID: ' . (int) ($event['client_id'] ?? 0),
            'CRM: ' . esc_url_raw((string) ($event['client_url'] ?? '')),
            '',
            'Event key: ' . sanitize_text_field((string) ($event['event_key'] ?? '')),
        ];

        $body = implode("\n", $lines);

        $sent = wp_mail($recipient, $subject, $body);

        return [
            'ok' => (bool) $sent,
            'provider_code' => $sent ? 'wp_mail' : 'wp_mail_failed',
            'http_status' => null,
            'response_excerpt' => $sent ? 'accepted_by_wp_mail' : 'wp_mail_returned_false',
            'error_message' => $sent ? '' : 'wp_mail returned false',
        ];
    }
}
