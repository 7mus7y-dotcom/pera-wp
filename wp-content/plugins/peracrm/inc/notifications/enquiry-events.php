<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_enquiry_notifications_event_type()
{
    return 'enquiry_received';
}

function peracrm_enquiry_notifications_source_label($source)
{
    $source = sanitize_key((string) $source);

    if ($source === 'facebook_lead_ads') {
        return 'Facebook';
    }

    return 'Website';
}

function peracrm_enquiry_notifications_build_event(array $outcome)
{
    $event_type = peracrm_enquiry_notifications_event_type();
    $payload = isset($outcome['payload']) && is_array($outcome['payload']) ? $outcome['payload'] : [];
    $context = isset($outcome['context']) && is_array($outcome['context']) ? $outcome['context'] : [];

    $source = sanitize_key((string) ($outcome['source'] ?? ($context['handler'] ?? 'website_form')));
    if ($source === '') {
        $source = 'website_form';
    }

    $source_event_id = sanitize_text_field((string) ($outcome['source_event_id'] ?? ''));
    $activity_id = (int) ($outcome['activity_id'] ?? 0);
    $submission_id = sanitize_text_field((string) ($outcome['submission_id'] ?? ''));

    $event_identity = peracrm_enquiry_notifications_resolve_identity($event_type, $source, $source_event_id, $activity_id, $submission_id, $payload, $context);

    return [
        'event_type' => $event_type,
        'source' => $source,
        'source_event_id' => $source_event_id,
        'fingerprint' => sanitize_text_field((string) ($outcome['retry_fingerprint'] ?? '')),
        'event_key' => $event_identity['event_key'],
        'identity_kind' => $event_identity['identity_kind'],
        'identity_value' => $event_identity['identity_value'],
        'client_id' => (int) ($outcome['client_id'] ?? 0),
        'enquiry_id' => $activity_id > 0 ? (string) $activity_id : '',
        'activity_id' => $activity_id,
        'submission_id' => $submission_id,
        'name' => peracrm_enquiry_notifications_pick_name($payload, (int) ($outcome['client_id'] ?? 0)),
        'phone' => sanitize_text_field((string) ($payload['phone'] ?? '')),
        'email' => sanitize_email((string) ($payload['email'] ?? '')),
        'interest' => peracrm_enquiry_notifications_pick_interest($payload),
        'form_name' => sanitize_text_field((string) ($context['form_name'] ?? $context['form_id'] ?? '')),
        'source_label' => peracrm_enquiry_notifications_source_label($source),
        'client_url' => peracrm_enquiry_notifications_client_url((int) ($outcome['client_id'] ?? 0)),
        'raw_context' => $context,
    ];
}

function peracrm_enquiry_notifications_resolve_identity($event_type, $source, $source_event_id, $activity_id, $submission_id, array $payload, array $context)
{
    $event_type = sanitize_key((string) $event_type);
    $source = sanitize_key((string) $source);
    $source_event_id = sanitize_text_field((string) $source_event_id);
    $activity_id = (int) $activity_id;
    $submission_id = sanitize_text_field((string) $submission_id);

    if ($source_event_id !== '') {
        $identity_value = 'source_event:' . $source_event_id;
        return [
            'identity_kind' => 'source_event_id',
            'identity_value' => $identity_value,
            'event_key' => hash('sha256', $event_type . '|' . $source . '|' . $identity_value),
        ];
    }

    if ($activity_id > 0) {
        $identity_value = 'activity:' . $activity_id;
        return [
            'identity_kind' => 'activity_id',
            'identity_value' => $identity_value,
            'event_key' => hash('sha256', $event_type . '|' . $source . '|' . $identity_value),
        ];
    }

    if ($submission_id !== '') {
        $identity_value = 'submission:' . $submission_id;
        return [
            'identity_kind' => 'submission_id',
            'identity_value' => $identity_value,
            'event_key' => hash('sha256', $event_type . '|' . $source . '|' . $identity_value),
        ];
    }

    // Safety fallback: time-bounded key to suppress immediate retries only.
    $retry_fingerprint = peracrm_enquiry_notifications_retry_fingerprint($payload, $context);
    $bucket = gmdate('YmdHi');
    $identity_value = 'retry:' . $retry_fingerprint . ':' . $bucket;

    return [
        'identity_kind' => 'retry_window',
        'identity_value' => $identity_value,
        'event_key' => hash('sha256', $event_type . '|' . $source . '|' . $identity_value),
    ];
}

function peracrm_enquiry_notifications_retry_fingerprint(array $payload, array $context)
{
    $parts = [
        sanitize_key((string) ($context['handler'] ?? 'website_form')),
        strtolower(trim((string) ($payload['email'] ?? ''))),
        preg_replace('/\D+/', '', (string) ($payload['phone'] ?? '')),
        sanitize_text_field((string) ($context['form_id'] ?? '')),
    ];

    return hash('sha256', implode('|', $parts));
}

function peracrm_enquiry_notifications_pick_name(array $payload, $client_id)
{
    $first = sanitize_text_field((string) ($payload['first_name'] ?? ''));
    $last = sanitize_text_field((string) ($payload['last_name'] ?? ''));
    $full = trim($first . ' ' . $last);

    if ($full !== '') {
        return $full;
    }

    $name = sanitize_text_field((string) ($payload['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $client = get_post((int) $client_id);
    if ($client && !empty($client->post_title)) {
        return sanitize_text_field((string) $client->post_title);
    }

    return '';
}

function peracrm_enquiry_notifications_pick_interest(array $payload)
{
    $candidates = [
        $payload['project'] ?? '',
        $payload['property_name'] ?? '',
        $payload['property_title'] ?? '',
        $payload['subject'] ?? '',
        $payload['message'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $text = sanitize_text_field((string) $candidate);
        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

function peracrm_enquiry_notifications_client_url($client_id)
{
    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        return '';
    }

    if (function_exists('pera_crm_get_client_view_url')) {
        return esc_url_raw((string) pera_crm_get_client_view_url($client_id));
    }

    return esc_url_raw(home_url('/crm/client/' . $client_id . '/'));
}
