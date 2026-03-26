<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_facebook_leads_default_settings()
{
    return [
        'enabled' => 0,
        'verify_token' => '',
        'app_secret' => '',
        'page_access_token' => '',
    ];
}

function peracrm_facebook_leads_get_settings()
{
    $saved = get_option('peracrm_facebook_leads_settings', []);
    if (!is_array($saved)) {
        $saved = [];
    }

    $settings = wp_parse_args($saved, peracrm_facebook_leads_default_settings());

    $constant_map = [
        'enabled' => 'PERACRM_FACEBOOK_LEADS_ENABLED',
        'verify_token' => 'PERACRM_FACEBOOK_LEADS_VERIFY_TOKEN',
        'app_secret' => 'PERACRM_FACEBOOK_LEADS_APP_SECRET',
        'page_access_token' => 'PERACRM_FACEBOOK_LEADS_PAGE_ACCESS_TOKEN',
    ];

    foreach ($constant_map as $key => $constant_name) {
        if (!defined($constant_name)) {
            continue;
        }

        $constant_value = constant($constant_name);
        if ($key === 'enabled') {
            $settings[$key] = !empty($constant_value) ? 1 : 0;
            continue;
        }

        $settings[$key] = is_scalar($constant_value) ? (string) $constant_value : '';
    }

    $settings['enabled'] = !empty($settings['enabled']) ? 1 : 0;
    $settings['verify_token'] = sanitize_text_field((string) $settings['verify_token']);
    $settings['app_secret'] = sanitize_text_field((string) $settings['app_secret']);
    $settings['page_access_token'] = sanitize_text_field((string) $settings['page_access_token']);

    return apply_filters('peracrm_facebook_leads_settings', $settings);
}

function peracrm_facebook_leads_is_enabled()
{
    $settings = peracrm_facebook_leads_get_settings();

    return !empty($settings['enabled']);
}

function peracrm_facebook_leads_get_verify_token()
{
    $settings = peracrm_facebook_leads_get_settings();

    return (string) ($settings['verify_token'] ?? '');
}

function peracrm_facebook_leads_get_app_secret()
{
    $settings = peracrm_facebook_leads_get_settings();

    return (string) ($settings['app_secret'] ?? '');
}

function peracrm_facebook_leads_get_page_access_token()
{
    $settings = peracrm_facebook_leads_get_settings();

    return (string) ($settings['page_access_token'] ?? '');
}
