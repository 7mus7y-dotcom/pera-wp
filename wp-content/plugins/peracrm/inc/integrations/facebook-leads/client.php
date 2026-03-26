<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_facebook_leads_graph_get_lead($leadgen_id, array $options = [])
{
    $leadgen_id = sanitize_text_field((string) $leadgen_id);
    if ($leadgen_id === '') {
        return [
            'ok' => false,
            'leadgen_id' => '',
            'http_status' => 0,
            'error_code' => 'invalid_leadgen_id',
            'error_message' => 'Leadgen ID is required.',
            'lead' => [],
            'raw' => [],
        ];
    }

    $token = peracrm_facebook_leads_get_page_access_token();
    if ($token === '') {
        return [
            'ok' => false,
            'leadgen_id' => $leadgen_id,
            'http_status' => 0,
            'error_code' => 'missing_page_access_token',
            'error_message' => 'Page access token is not configured.',
            'lead' => [],
            'raw' => [],
        ];
    }

    $fields = [
        'id',
        'created_time',
        'field_data',
        'form_id',
        'ad_id',
        'campaign_id',
        'is_organic',
        'ad_name',
        'campaign_name',
    ];

    $endpoint = sprintf('https://graph.facebook.com/v19.0/%s', rawurlencode($leadgen_id));
    $url = add_query_arg([
        'fields' => implode(',', $fields),
        'access_token' => $token,
    ], $endpoint);

    $timeout = isset($options['timeout']) ? max(1, (int) $options['timeout']) : 8;

    $response = wp_remote_get($url, [
        'timeout' => $timeout,
    ]);

    if (is_wp_error($response)) {
        return [
            'ok' => false,
            'leadgen_id' => $leadgen_id,
            'http_status' => 0,
            'error_code' => 'request_error',
            'error_message' => $response->get_error_message(),
            'lead' => [],
            'raw' => [
                'wp_error_code' => $response->get_error_code(),
                'wp_error_message' => $response->get_error_message(),
            ],
        ];
    }

    $http_status = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $decoded = json_decode($raw_body, true);

    $raw = [
        'http_status' => $http_status,
        'headers' => peracrm_facebook_leads_graph_sanitize_headers(wp_remote_retrieve_headers($response)),
        'body_excerpt' => peracrm_facebook_leads_sanitize_debug_excerpt($raw_body),
    ];

    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'leadgen_id' => $leadgen_id,
            'http_status' => $http_status,
            'error_code' => 'invalid_json_response',
            'error_message' => 'Graph returned a non-JSON response.',
            'lead' => [],
            'raw' => $raw,
        ];
    }

    if ($http_status >= 400 || isset($decoded['error'])) {
        $error = isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : [];
        return [
            'ok' => false,
            'leadgen_id' => $leadgen_id,
            'http_status' => $http_status,
            'error_code' => sanitize_key((string) ($error['type'] ?? 'graph_error')),
            'error_message' => sanitize_text_field((string) ($error['message'] ?? 'Graph request failed.')),
            'lead' => [],
            'raw' => $raw,
        ];
    }

    $lead = peracrm_facebook_leads_graph_normalize_lead($decoded);
    if ($lead['id'] === '') {
        return [
            'ok' => false,
            'leadgen_id' => $leadgen_id,
            'http_status' => $http_status,
            'error_code' => 'missing_id',
            'error_message' => 'Graph response missing lead id.',
            'lead' => $lead,
            'raw' => $raw,
        ];
    }

    return [
        'ok' => true,
        'leadgen_id' => $leadgen_id,
        'http_status' => $http_status,
        'error_code' => '',
        'error_message' => '',
        'lead' => $lead,
        'raw' => $raw,
    ];
}

function peracrm_facebook_leads_graph_normalize_lead(array $payload)
{
    $normalized = [
        'id' => isset($payload['id']) ? sanitize_text_field((string) $payload['id']) : '',
        'created_time' => isset($payload['created_time']) ? sanitize_text_field((string) $payload['created_time']) : '',
        'form_id' => isset($payload['form_id']) ? sanitize_text_field((string) $payload['form_id']) : '',
        'form_name' => isset($payload['form_name']) ? sanitize_text_field((string) $payload['form_name']) : '',
        'ad_id' => isset($payload['ad_id']) ? sanitize_text_field((string) $payload['ad_id']) : '',
        'ad_name' => isset($payload['ad_name']) ? sanitize_text_field((string) $payload['ad_name']) : '',
        'campaign_id' => isset($payload['campaign_id']) ? sanitize_text_field((string) $payload['campaign_id']) : '',
        'campaign_name' => isset($payload['campaign_name']) ? sanitize_text_field((string) $payload['campaign_name']) : '',
        'field_data' => [],
    ];

    if (isset($payload['field_data']) && is_array($payload['field_data'])) {
        foreach ($payload['field_data'] as $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            if ($field_name === '') {
                continue;
            }

            $values = [];
            if (isset($field['values']) && is_array($field['values'])) {
                foreach ($field['values'] as $value) {
                    if (is_scalar($value) || $value === null) {
                        $values[] = sanitize_text_field((string) $value);
                    }
                }
            }

            $normalized['field_data'][] = [
                'name' => $field_name,
                'values' => $values,
            ];
        }
    }

    return $normalized;
}

function peracrm_facebook_leads_graph_sanitize_headers($headers)
{
    if (is_object($headers) && method_exists($headers, 'getAll')) {
        $headers = $headers->getAll();
    }

    if (!is_array($headers)) {
        return [];
    }

    $normalized = [];
    foreach ($headers as $key => $value) {
        $header_key = sanitize_key((string) $key);
        if ($header_key === '' || in_array($header_key, ['authorization', 'cookie', 'set_cookie'], true)) {
            continue;
        }

        if (is_array($value)) {
            $normalized[$header_key] = implode(', ', array_map('strval', $value));
            continue;
        }

        $normalized[$header_key] = sanitize_text_field((string) $value);
    }

    return $normalized;
}

function peracrm_facebook_leads_sanitize_debug_excerpt($value)
{
    $value = (string) $value;
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        if (isset($decoded['field_data']) && is_array($decoded['field_data'])) {
            $field_names = [];
            foreach ($decoded['field_data'] as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $field_name = sanitize_key((string) ($field['name'] ?? ''));
                if ($field_name !== '') {
                    $field_names[] = $field_name;
                }
            }

            $decoded['field_data'] = [
                'field_count' => count($field_names),
                'field_names' => $field_names,
            ];
        }

        $value = wp_json_encode($decoded);
    }

    $value = preg_replace('/[\r\n\t]+/', ' ', $value);
    if (!is_string($value)) {
        $value = '';
    }

    if (strlen($value) > 2000) {
        return substr($value, 0, 2000) . '…[truncated]';
    }

    return $value;
}
