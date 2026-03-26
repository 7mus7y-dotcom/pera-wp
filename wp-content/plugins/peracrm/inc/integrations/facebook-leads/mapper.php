<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_facebook_leads_map_graph_lead_to_enquiry(array $lead, array $notification = [])
{
    $fields = peracrm_facebook_leads_index_field_data(isset($lead['field_data']) && is_array($lead['field_data']) ? $lead['field_data'] : []);

    $full_name = peracrm_facebook_leads_pick_first_non_empty($fields, [
        'full_name',
        'name',
    ]);

    $first_name = peracrm_facebook_leads_pick_first_non_empty($fields, [
        'first_name',
        'firstname',
        'first',
    ]);

    $last_name = peracrm_facebook_leads_pick_first_non_empty($fields, [
        'last_name',
        'lastname',
        'last',
        'surname',
        'family_name',
    ]);

    if ($first_name === '' && $last_name === '' && $full_name !== '' && function_exists('peracrm_ingest_split_name')) {
        [$first_name, $last_name] = peracrm_ingest_split_name($full_name);
    }

    $email = peracrm_facebook_leads_pick_first_non_empty($fields, [
        'email',
        'email_address',
        'e_mail',
    ]);

    $phone = peracrm_facebook_leads_pick_first_non_empty($fields, [
        'phone_number',
        'phone',
        'mobile_phone_number',
        'mobile',
        'tel',
    ]);

    $facebook_lead_id = sanitize_text_field((string) ($lead['id'] ?? ''));
    $facebook_form_id = sanitize_text_field((string) ($lead['form_id'] ?? ($notification['form_id'] ?? '')));
    $facebook_form_name = sanitize_text_field((string) ($lead['form_name'] ?? ''));
    $facebook_ad_id = sanitize_text_field((string) ($lead['ad_id'] ?? ''));
    $facebook_ad_name = sanitize_text_field((string) ($lead['ad_name'] ?? ''));

    $raw_fields = [
        'source' => 'facebook_lead_ads',
        'facebook_form_id' => $facebook_form_id,
        'facebook_form_name' => $facebook_form_name,
        'facebook_ad_id' => $facebook_ad_id,
        'facebook_ad_name' => $facebook_ad_name,
        'facebook_lead_id' => $facebook_lead_id,
        'facebook_payload' => peracrm_facebook_leads_sanitize_raw_payload($lead),
    ];

    $payload = [
        'name' => $full_name,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => sanitize_email($email),
        'phone' => sanitize_text_field((string) $phone),
        'raw_fields' => $raw_fields,
    ];

    return [
        'payload' => $payload,
        'source_meta' => [
            'source' => 'facebook_lead_ads',
            'facebook_form_id' => $facebook_form_id,
            'facebook_form_name' => $facebook_form_name,
            'facebook_ad_id' => $facebook_ad_id,
            'facebook_ad_name' => $facebook_ad_name,
            'facebook_lead_id' => $facebook_lead_id,
            'raw_facebook_payload' => $raw_fields['facebook_payload'],
        ],
    ];
}

function peracrm_facebook_leads_index_field_data(array $field_data)
{
    $indexed = [];

    foreach ($field_data as $field) {
        if (!is_array($field)) {
            continue;
        }

        $name = sanitize_key((string) ($field['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $values = isset($field['values']) && is_array($field['values']) ? $field['values'] : [];
        foreach ($values as $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $text = sanitize_text_field((string) $value);
            if ($text === '') {
                continue;
            }

            $indexed[$name][] = $text;
        }
    }

    return $indexed;
}

function peracrm_facebook_leads_pick_first_non_empty(array $fields, array $keys)
{
    foreach ($keys as $key) {
        $key = sanitize_key((string) $key);
        if ($key === '' || empty($fields[$key]) || !is_array($fields[$key])) {
            continue;
        }

        foreach ($fields[$key] as $value) {
            $value = sanitize_text_field((string) $value);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function peracrm_facebook_leads_sanitize_raw_payload($value)
{
    if (is_array($value)) {
        $field_names = [];
        if (isset($value['field_data']) && is_array($value['field_data'])) {
            foreach ($value['field_data'] as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $field_name = sanitize_key((string) ($field['name'] ?? ''));
                if ($field_name !== '') {
                    $field_names[] = $field_name;
                }
            }
        }

        return [
            'id' => sanitize_text_field((string) ($value['id'] ?? '')),
            'created_time' => sanitize_text_field((string) ($value['created_time'] ?? '')),
            'form_id' => sanitize_text_field((string) ($value['form_id'] ?? '')),
            'form_name' => sanitize_text_field((string) ($value['form_name'] ?? '')),
            'ad_id' => sanitize_text_field((string) ($value['ad_id'] ?? '')),
            'ad_name' => sanitize_text_field((string) ($value['ad_name'] ?? '')),
            'campaign_id' => sanitize_text_field((string) ($value['campaign_id'] ?? '')),
            'campaign_name' => sanitize_text_field((string) ($value['campaign_name'] ?? '')),
            'field_data' => [
                'field_count' => count($field_names),
                'field_names' => $field_names,
            ],
        ];
    }

    if (is_scalar($value) || $value === null) {
        return sanitize_textarea_field((string) $value);
    }

    return '';
}
