<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_facebook_leads_mask_secret($value)
{
    $value = (string) $value;
    $len = strlen($value);
    if ($len <= 0) {
        return '';
    }

    if ($len <= 8) {
        return str_repeat('*', $len);
    }

    return substr($value, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($value, -4);
}

function peracrm_facebook_leads_log($level, $message, array $context = [])
{
    $safe_context = [];
    foreach ($context as $key => $value) {
        $safe_key = sanitize_key((string) $key);
        if ($safe_key === '') {
            continue;
        }

        if (in_array($safe_key, ['verify_token', 'app_secret', 'page_access_token', 'authorization', 'access_token', 'x_hub_signature_256'], true)) {
            $safe_context[$safe_key] = peracrm_facebook_leads_mask_secret($value);
            continue;
        }

        $safe_context[$safe_key] = peracrm_facebook_leads_log_normalize_value($value);
    }

    $line = sprintf(
        '[PeraCRM facebook_leads][%s] %s %s',
        sanitize_key((string) $level),
        sanitize_text_field((string) $message),
        wp_json_encode($safe_context)
    );

    error_log($line);
}

function peracrm_facebook_leads_log_normalize_value($value)
{
    if (is_scalar($value) || $value === null) {
        return $value;
    }

    if (!is_array($value)) {
        return null;
    }

    $normalized = [];
    foreach ($value as $key => $item) {
        $item_key = sanitize_key((string) $key);
        if ($item_key === '') {
            continue;
        }

        if (in_array($item_key, ['verify_token', 'app_secret', 'page_access_token', 'authorization', 'access_token', 'x_hub_signature_256'], true)) {
            $normalized[$item_key] = peracrm_facebook_leads_mask_secret((string) $item);
            continue;
        }

        $normalized[$item_key] = peracrm_facebook_leads_log_normalize_value($item);
    }

    return $normalized;
}

function peracrm_facebook_leads_log_debug($message, array $context = [])
{
    peracrm_facebook_leads_log('debug', $message, $context);
}

function peracrm_facebook_leads_log_warning($message, array $context = [])
{
    peracrm_facebook_leads_log('warning', $message, $context);
}

function peracrm_facebook_leads_log_info($message, array $context = [])
{
    peracrm_facebook_leads_log('info', $message, $context);
}
