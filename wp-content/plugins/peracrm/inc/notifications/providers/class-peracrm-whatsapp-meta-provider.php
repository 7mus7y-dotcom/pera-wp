<?php

if (!defined('ABSPATH')) {
    exit;
}

class PeraCRM_Whatsapp_Meta_Provider
{
    public function send(array $event, $recipient)
    {
        $recipient = sanitize_text_field((string) $recipient);
        $settings = function_exists('peracrm_whatsapp_get_settings')
            ? peracrm_whatsapp_get_settings()
            : [];

        $phone_number_id = sanitize_text_field((string) ($settings['phone_number_id'] ?? ''));
        $access_token = sanitize_text_field((string) ($settings['access_token'] ?? ''));
        $api_version = sanitize_text_field((string) ($settings['graph_api_version'] ?? 'v22.0'));
        if ($api_version === '') {
            $api_version = 'v22.0';
        }

        if ($phone_number_id === '' || $access_token === '') {
            return [
                'ok' => false,
                'provider_code' => 'missing_credentials',
                'http_status' => null,
                'response_excerpt' => '',
                'error_message' => 'WhatsApp Phone Number ID or Access Token missing',
            ];
        }

        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            rawurlencode($api_version),
            rawurlencode($phone_number_id)
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => preg_replace('/\D+/', '', $recipient),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $this->format_message($event),
            ],
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'provider_code' => 'transport_error',
                'http_status' => null,
                'response_excerpt' => '',
                'error_message' => $response->get_error_message(),
            ];
        }

        $http_status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $ok = $http_status >= 200 && $http_status < 300;

        return [
            'ok' => $ok,
            'provider_code' => $ok ? 'meta_cloud_api' : 'meta_cloud_api_error',
            'http_status' => $http_status,
            'response_excerpt' => substr(sanitize_text_field($body), 0, 900),
            'error_message' => $ok ? '' : 'Meta Cloud API returned non-2xx',
        ];
    }

    private function format_message(array $event)
    {
        $lines = [
            'New enquiry received',
            'Source: ' . sanitize_text_field((string) ($event['source_label'] ?? '')),
            'Name: ' . sanitize_text_field((string) ($event['name'] ?? '')),
            'Phone: ' . sanitize_text_field((string) ($event['phone'] ?? '')),
            'Email: ' . sanitize_email((string) ($event['email'] ?? '')),
        ];

        if (!empty($event['interest'])) {
            $lines[] = 'Interested in: ' . sanitize_text_field((string) $event['interest']);
        }

        if (!empty($event['form_name'])) {
            $lines[] = 'Form: ' . sanitize_text_field((string) $event['form_name']);
        }

        if (!empty($event['client_url'])) {
            $lines[] = 'CRM: ' . esc_url_raw((string) $event['client_url']);
        }

        return implode("\n", $lines);
    }
}
