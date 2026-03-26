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

        if (in_array($safe_key, ['verify_token', 'app_secret', 'page_access_token', 'authorization'], true)) {
            $safe_context[$safe_key] = peracrm_facebook_leads_mask_secret($value);
            continue;
        }

        if (is_scalar($value) || $value === null) {
            $safe_context[$safe_key] = $value;
            continue;
        }

        if (is_array($value)) {
            $safe_context[$safe_key] = wp_json_encode($value);
        }
    }

    $line = sprintf(
        '[PeraCRM facebook_leads][%s] %s %s',
        sanitize_key((string) $level),
        sanitize_text_field((string) $message),
        wp_json_encode($safe_context)
    );

    error_log($line);
}

function peracrm_facebook_leads_log_debug($message, array $context = [])
{
    peracrm_facebook_leads_log('debug', $message, $context);
}

function peracrm_facebook_leads_log_warning($message, array $context = [])
{
    peracrm_facebook_leads_log('warning', $message, $context);
}
