<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERACRM_DEBUG_INGEST')) {
    define('PERACRM_DEBUG_INGEST', false);
}

function peracrm_ingest_debug_enabled()
{
    return defined('WP_DEBUG') && WP_DEBUG && defined('PERACRM_DEBUG_INGEST') && PERACRM_DEBUG_INGEST;
}

function peracrm_ingest_debug_log($message, array $context = [])
{
    if (!peracrm_ingest_debug_enabled()) {
        return;
    }

    $safe_context = [];
    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $safe_context[sanitize_key((string) $key)] = $value;
        }
    }

    error_log('[PeraCRM ingest] ' . sanitize_text_field($message) . ' ' . wp_json_encode($safe_context));
}

function peracrm_ingest_split_name($full_name)
{
    $full_name = trim((string) $full_name);
    if ($full_name === '') {
        return ['', ''];
    }

    $parts = preg_split('/\s+/', $full_name);
    if (!$parts) {
        return [$full_name, ''];
    }

    $first_name = sanitize_text_field((string) array_shift($parts));
    $last_name = sanitize_text_field(trim(implode(' ', $parts)));

    return [$first_name, $last_name];
}

function peracrm_ingest_source_url()
{
    $source_url = '';
    if (!empty($_POST['_wp_http_referer'])) {
        $source_url = esc_url_raw(wp_unslash($_POST['_wp_http_referer']));
    }

    if ($source_url === '' && !empty($_SERVER['HTTP_REFERER'])) {
        $source_url = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
    }

    return $source_url;
}

function peracrm_ingest_source_post_id($source_url)
{
    if ($source_url === '') {
        return 0;
    }

    return (int) url_to_postid($source_url);
}

function peracrm_ingest_request_meta()
{
    $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
        : '';

    $ip_address = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
        $ip_address = trim((string) $parts[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    }

    return [
        'user_agent' => $user_agent,
        'ip' => $ip_address,
    ];
}

function peracrm_ingest_sanitize_raw_fields($value)
{
    if (is_array($value)) {
        $sanitized = [];
        foreach ($value as $key => $item) {
            $sanitized_key = is_string($key) ? sanitize_key($key) : $key;
            $sanitized[$sanitized_key] = peracrm_ingest_sanitize_raw_fields($item);
        }

        return $sanitized;
    }

    if (is_scalar($value)) {
        return sanitize_textarea_field((string) $value);
    }

    return '';
}

function peracrm_ingest_collect_prefixed_post_fields($prefix, array $exclude = [])
{
    $fields = [];
    $exclude_lookup = array_fill_keys($exclude, true);

    foreach ($_POST as $key => $value) {
        $key = (string) $key;
        if (strpos($key, $prefix) !== 0) {
            continue;
        }

        if (isset($exclude_lookup[$key])) {
            continue;
        }

        $fields[$key] = wp_unslash($value);
    }

    return $fields;
}

function peracrm_ingest_normalize_sr_form_context($form_context, $sr_context, $intent)
{
    $form_context = sanitize_key((string) $form_context);
    $sr_context = sanitize_key((string) $sr_context);
    $intent = sanitize_key((string) $intent);

    if ($form_context === 'property' || $sr_context === 'bodrum_property') {
        return 'property_enquiry';
    }

    if ($form_context === 'sell' || $form_context === 'sell-page' || $intent === 'sell') {
        return 'sell';
    }

    if ($form_context === 'rent' || $form_context === 'rent-page' || $intent === 'rent' || $intent === 'short-term') {
        return 'rent';
    }

    return $form_context;
}

function peracrm_ingest_log_submission(array $payload)
{
    $email = isset($payload['email']) ? sanitize_email($payload['email']) : '';
    if ($email === '') {
        peracrm_ingest_debug_log('Skipped submission without email', [
            'form' => isset($payload['form']) ? $payload['form'] : 'unknown',
        ]);
        return;
    }

    $client_id = peracrm_find_or_create_client_by_email($email, [
        'first_name' => isset($payload['first_name']) ? $payload['first_name'] : '',
        'last_name' => isset($payload['last_name']) ? $payload['last_name'] : '',
        'phone' => isset($payload['phone']) ? $payload['phone'] : '',
        'source' => 'website_form',
        'status' => 'enquiry',
    ]);

    if ($client_id <= 0) {
        peracrm_ingest_debug_log('Failed to create/update client', [
            'form' => isset($payload['form']) ? $payload['form'] : 'unknown',
        ]);
        return;
    }

    $event_payload = [
        'form' => isset($payload['form']) ? sanitize_key($payload['form']) : 'unknown',
        'form_context' => isset($payload['form_context']) ? sanitize_key($payload['form_context']) : '',
        'source' => 'website_form',
        'page_url' => isset($payload['page_url']) ? esc_url_raw($payload['page_url']) : '',
        'post_id' => isset($payload['post_id']) ? absint($payload['post_id']) : 0,
        'property_id' => isset($payload['property_id']) ? absint($payload['property_id']) : 0,
        'message' => isset($payload['message']) ? sanitize_textarea_field($payload['message']) : '',
        'submitted_at' => peracrm_now_mysql(),
        'user_agent' => isset($payload['user_agent']) ? sanitize_text_field($payload['user_agent']) : '',
        'ip' => isset($payload['ip']) ? sanitize_text_field($payload['ip']) : '',
    ];

    if (!empty($payload['raw_fields']) && is_array($payload['raw_fields'])) {
        $event_payload['raw_fields'] = peracrm_ingest_sanitize_raw_fields($payload['raw_fields']);
    }

    if (!empty($payload['property_ids']) && is_array($payload['property_ids'])) {
        $property_ids = array_values(array_filter(array_map('absint', $payload['property_ids'])));
        if (!empty($property_ids)) {
            $event_payload['property_ids'] = $property_ids;
            $event_payload['properties_count'] = count($property_ids);
        }
    }

    peracrm_log_event($client_id, 'enquiry', $event_payload);

    $property_id = isset($payload['property_id']) ? absint($payload['property_id']) : 0;
    if ($property_id > 0 && function_exists('peracrm_client_property_link')) {
        peracrm_client_property_link($client_id, $property_id, 'enquiry');
    }

    if (!empty($payload['property_ids']) && is_array($payload['property_ids']) && function_exists('peracrm_client_property_link')) {
        foreach ($payload['property_ids'] as $property_id_item) {
            $property_id_item = absint($property_id_item);
            if ($property_id_item <= 0) {
                continue;
            }

            peracrm_client_property_link($client_id, $property_id_item, 'enquiry');
        }
    }

    peracrm_ingest_debug_log('Captured submission', [
        'form' => isset($payload['form']) ? $payload['form'] : 'unknown',
        'client_id' => $client_id,
        'property_id' => $property_id,
        'post_id' => isset($event_payload['post_id']) ? $event_payload['post_id'] : 0,
    ]);
}

function peracrm_ingest_should_capture_sr()
{
    if (!isset($_POST['sr_action'])) {
        return false;
    }

    if (!isset($_POST['sr_nonce'])) {
        return false;
    }

    $nonce = wp_unslash($_POST['sr_nonce']);
    if (!wp_verify_nonce($nonce, 'pera_seller_landlord_enquiry')) {
        return false;
    }

    // Mirror theme honeypot rejection.
    $honeypot = isset($_POST['sr_company']) ? sanitize_text_field(wp_unslash($_POST['sr_company'])) : '';
    if ($honeypot !== '') {
        return false;
    }

    // Minimal required gate for CRM: valid lead email.
    $email = isset($_POST['sr_email']) ? sanitize_email(wp_unslash($_POST['sr_email'])) : '';

    return is_email($email);
}

function peracrm_ingest_should_capture_favourites()
{
    if (!isset($_POST['fav_enquiry_action'])) {
        return false;
    }

    if (!isset($_POST['fav_nonce'])) {
        return false;
    }

    $nonce = wp_unslash($_POST['fav_nonce']);
    if (!wp_verify_nonce($nonce, 'pera_favourites_enquiry')) {
        return false;
    }

    // Mirror theme honeypot rejection.
    $honeypot = isset($_POST['fav_company']) ? sanitize_text_field(wp_unslash($_POST['fav_company'])) : '';
    if ($honeypot !== '') {
        return false;
    }

    $email = isset($_POST['fav_email']) ? sanitize_email(wp_unslash($_POST['fav_email'])) : '';
    if (!is_email($email)) {
        return false;
    }

    // Mirror theme required field checks for non-logged-in users.
    if (!is_user_logged_in()) {
        $first_name = isset($_POST['fav_first_name']) ? sanitize_text_field(wp_unslash($_POST['fav_first_name'])) : '';
        $last_name = isset($_POST['fav_last_name']) ? sanitize_text_field(wp_unslash($_POST['fav_last_name'])) : '';
        $phone = isset($_POST['fav_phone']) ? sanitize_text_field(wp_unslash($_POST['fav_phone'])) : '';

        if ($first_name === '' || $last_name === '' || $phone === '') {
            return false;
        }
    }

    return true;
}

function peracrm_ingest_should_capture_citizenship()
{
    if (!isset($_POST['pera_citizenship_action'])) {
        return false;
    }

    if (!isset($_POST['pera_citizenship_nonce'])) {
        return false;
    }

    $nonce = wp_unslash($_POST['pera_citizenship_nonce']);
    if (!wp_verify_nonce($nonce, 'pera_citizenship_enquiry')) {
        return false;
    }

    // Minimal required gate for CRM: valid lead email.
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

    return is_email($email);
}

function peracrm_ingest_theme_enquiry_forms()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    if (!function_exists('peracrm_find_or_create_client_by_email') || !function_exists('peracrm_log_event')) {
        return;
    }

    try {
        if (peracrm_ingest_should_capture_sr()) {
            $name = isset($_POST['sr_name']) ? sanitize_text_field(wp_unslash($_POST['sr_name'])) : '';
            [$first_name, $last_name] = peracrm_ingest_split_name($name);

            $sr_intent = isset($_POST['sr_intent']) ? sanitize_text_field(wp_unslash($_POST['sr_intent'])) : '';
            $sr_context = isset($_POST['sr_context']) ? sanitize_text_field(wp_unslash($_POST['sr_context'])) : '';
            $raw_form_context = isset($_POST['form_context']) ? sanitize_text_field(wp_unslash($_POST['form_context'])) : '';
            $normalized_form_context = peracrm_ingest_normalize_sr_form_context($raw_form_context, $sr_context, $sr_intent);

            $page_url = peracrm_ingest_source_url();
            $post_id = peracrm_ingest_source_post_id($page_url);
            $meta = peracrm_ingest_request_meta();

            $sr_raw_fields = peracrm_ingest_collect_prefixed_post_fields('sr_', [
                'sr_action',
                'sr_nonce',
                'sr_company',
                'sr_name',
                'sr_email',
                'sr_phone',
            ]);
            $sr_raw_fields['form_context'] = $raw_form_context;
            $sr_raw_fields['form_context_normalized'] = $normalized_form_context;
            $sr_raw_fields['sr_context'] = $sr_context;
            $sr_raw_fields['intent'] = $sr_intent;
            $sr_raw_fields['consent'] = !empty($_POST['sr_consent']) ? 'yes' : 'no';

            peracrm_ingest_log_submission([
                'form' => 'sr_enquiry',
                'form_context' => $normalized_form_context,
                'email' => isset($_POST['sr_email']) ? sanitize_email(wp_unslash($_POST['sr_email'])) : '',
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => isset($_POST['sr_phone']) ? sanitize_text_field(wp_unslash($_POST['sr_phone'])) : '',
                'page_url' => $page_url,
                'post_id' => $post_id,
                'property_id' => isset($_POST['sr_property_id']) ? absint($_POST['sr_property_id']) : 0,
                'message' => isset($_POST['sr_message']) ? sanitize_textarea_field(wp_unslash($_POST['sr_message'])) : '',
                'user_agent' => $meta['user_agent'],
                'ip' => $meta['ip'],
                'raw_fields' => $sr_raw_fields,
            ]);

            return;
        }

        if (peracrm_ingest_should_capture_favourites()) {
            $first_name = isset($_POST['fav_first_name']) ? sanitize_text_field(wp_unslash($_POST['fav_first_name'])) : '';
            $last_name = isset($_POST['fav_last_name']) ? sanitize_text_field(wp_unslash($_POST['fav_last_name'])) : '';
            if ($first_name === '' && $last_name === '' && is_user_logged_in()) {
                $user = wp_get_current_user();
                $first_name = (string) get_user_meta($user->ID, 'first_name', true);
                $last_name = (string) get_user_meta($user->ID, 'last_name', true);
            }

            $page_url = peracrm_ingest_source_url();
            $post_id = peracrm_ingest_source_post_id($page_url);
            $meta = peracrm_ingest_request_meta();

            $property_ids = isset($_POST['fav_post_ids']) ? wp_unslash($_POST['fav_post_ids']) : [];
            if (!is_array($property_ids)) {
                $property_ids = preg_split('/[,\s]+/', (string) $property_ids);
            }
            $property_ids = array_values(array_filter(array_map('absint', (array) $property_ids)));
            // Cap at 200 IDs to avoid oversized payload abuse while preserving normal user behaviour.
            $property_ids = array_slice(array_values(array_unique($property_ids)), 0, 200);

            $message = isset($_POST['fav_message']) ? sanitize_textarea_field(wp_unslash($_POST['fav_message'])) : '';
            if ($message === '' && !empty($property_ids)) {
                $message = sprintf('Favourites enquiry for %d properties.', count($property_ids));
            }

            $fav_raw_fields = peracrm_ingest_collect_prefixed_post_fields('fav_', [
                'fav_enquiry_action',
                'fav_nonce',
                'fav_company',
                'fav_first_name',
                'fav_last_name',
                'fav_email',
                'fav_phone',
                'fav_message',
            ]);
            $fav_raw_fields['property_ids'] = $property_ids;
            $fav_raw_fields['properties_count'] = count($property_ids);

            peracrm_ingest_log_submission([
                'form' => 'favourites_enquiry',
                'form_context' => 'favourites',
                'email' => isset($_POST['fav_email']) ? sanitize_email(wp_unslash($_POST['fav_email'])) : '',
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => isset($_POST['fav_phone']) ? sanitize_text_field(wp_unslash($_POST['fav_phone'])) : '',
                'page_url' => $page_url,
                'post_id' => $post_id,
                'property_id' => 0,
                'property_ids' => $property_ids,
                'message' => $message,
                'user_agent' => $meta['user_agent'],
                'ip' => $meta['ip'],
                'raw_fields' => $fav_raw_fields,
            ]);

            return;
        }

        if (peracrm_ingest_should_capture_citizenship()) {
            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            [$first_name, $last_name] = peracrm_ingest_split_name($name);

            $page_url = peracrm_ingest_source_url();
            $post_id = peracrm_ingest_source_post_id($page_url);
            $meta = peracrm_ingest_request_meta();

            $contact_methods = [];
            if (!empty($_POST['contact_method']) && is_array($_POST['contact_method'])) {
                $contact_methods = array_map('sanitize_text_field', wp_unslash($_POST['contact_method']));
            }

            $citizenship_raw_fields = [
                'enquiry_type' => isset($_POST['enquiry_type']) ? wp_unslash($_POST['enquiry_type']) : '',
                'family' => isset($_POST['family']) ? wp_unslash($_POST['family']) : '',
                'contact_method' => $contact_methods,
                'policy' => !empty($_POST['policy']) ? 'yes' : 'no',
            ];

            peracrm_ingest_log_submission([
                'form' => 'citizenship_enquiry',
                'form_context' => 'citizenship',
                'email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
                'page_url' => $page_url,
                'post_id' => $post_id,
                'property_id' => 0,
                'message' => isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '',
                'user_agent' => $meta['user_agent'],
                'ip' => $meta['ip'],
                'raw_fields' => $citizenship_raw_fields,
            ]);
        }
    } catch (Throwable $e) {
        peracrm_ingest_debug_log('Capture failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
add_action('init', 'peracrm_ingest_theme_enquiry_forms', 9);
