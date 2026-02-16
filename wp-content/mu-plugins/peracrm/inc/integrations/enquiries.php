<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERACRM_DEBUG_INGEST')) {
    define('PERACRM_DEBUG_INGEST', false);
}

function peracrm_ingest_debug_enabled()
{
    return (bool) (defined('PERACRM_DEBUG_INGEST') && PERACRM_DEBUG_INGEST);
}

function peracrm_ingest_debug_log($message, array $context = [])
{
    if (!peracrm_ingest_debug_enabled()) {
        return;
    }

    $safe_context = [];
    foreach ($context as $key => $value) {
        $safe_key = sanitize_key((string) $key);
        if (is_scalar($value) || $value === null) {
            $safe_context[$safe_key] = $value;
            continue;
        }

        if (is_array($value)) {
            $safe_context[$safe_key] = wp_json_encode($value);
        }
    }

    error_log('[PeraCRM ingest] ' . sanitize_text_field((string) $message) . ' ' . wp_json_encode($safe_context));
}

function peracrm_ingest_mark_flow_handled($flow)
{
    static $handled = [];
    $flow = sanitize_key((string) $flow);

    if ($flow === '') {
        return false;
    }

    if (isset($handled[$flow])) {
        return true;
    }

    $handled[$flow] = true;
    return false;
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

function peracrm_ingest_request_context($handler, $form_id = '', $form_name = '')
{
    return [
        'handler' => sanitize_key((string) $handler),
        'form_id' => sanitize_text_field((string) $form_id),
        'form_name' => sanitize_text_field((string) $form_name),
        'current_blog_id' => (int) get_current_blog_id(),
        'target_blog_id' => (int) peracrm_get_target_blog_id(),
        'site_url' => esc_url_raw((string) home_url('/')),
        'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
    ];
}

function peracrm_ingest_source_url_from_payload(array $payload)
{
    $keys = ['page_url', 'referrer', '_wp_http_referer', 'sr_property_url'];
    foreach ($keys as $key) {
        if (empty($payload[$key])) {
            continue;
        }

        return esc_url_raw((string) $payload[$key]);
    }

    if (!empty($_POST['_wp_http_referer'])) {
        return esc_url_raw(wp_unslash($_POST['_wp_http_referer']));
    }

    if (!empty($_SERVER['HTTP_REFERER'])) {
        return esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
    }

    if (!empty($_SERVER['REQUEST_URI'])) {
        return esc_url_raw(home_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))));
    }

    return '';
}

function peracrm_ingest_guess_property_id(array $payload)
{
    if (!empty($payload['property_id'])) {
        return absint($payload['property_id']);
    }

    $source_url = peracrm_ingest_source_url_from_payload($payload);
    if ($source_url === '') {
        return 0;
    }

    $post_id = (int) url_to_postid($source_url);
    if ($post_id <= 0) {
        return 0;
    }

    $post_type = (string) get_post_type($post_id);
    if (!in_array($post_type, ['property', 'bodrum-property'], true)) {
        return 0;
    }

    return $post_id;
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
        if (strpos($key, $prefix) !== 0 || isset($exclude_lookup[$key])) {
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

function peracrm_ingest_fingerprint($email, $handler, $property_id, $message)
{
    return hash('sha256', strtolower(trim((string) $email)) . '|' . sanitize_key((string) $handler) . '|' . (int) $property_id . '|' . trim((string) $message));
}

function peracrm_ingest_recent_fingerprint_exists($fingerprint, $window_seconds = 300)
{
    $fingerprint = sanitize_text_field((string) $fingerprint);
    $window_seconds = max(1, (int) $window_seconds);

    if ($fingerprint === '' || !function_exists('peracrm_activity_table_exists') || !peracrm_activity_table_exists()) {
        return false;
    }

    global $wpdb;

    return (bool) peracrm_with_target_blog(static function () use ($wpdb, $fingerprint, $window_seconds) {
        $table = peracrm_table('crm_activity');
        $since = date('Y-m-d H:i:s', current_time('timestamp') - $window_seconds);
        $like = '%"fingerprint"%' . $fingerprint . '%';

        $query = $wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE event_type = %s
               AND created_at >= %s
               AND event_payload LIKE %s
             LIMIT 1",
            'enquiry',
            $since,
            $like
        );

        return (int) $wpdb->get_var($query) > 0;
    });
}

function peracrm_ingest_enquiry(array $payload, array $context = [])
{
    peracrm_ingest_debug_log('peracrm_ingest_enquiry entered', $context);

    if (!function_exists('peracrm_find_or_create_client_by_email') || !function_exists('peracrm_log_event')) {
        peracrm_ingest_debug_log('ingest skipped: crm apis unavailable', $context);
        return 0;
    }

    $email = isset($payload['email']) ? sanitize_email((string) $payload['email']) : '';
    if (!is_email($email)) {
        peracrm_ingest_debug_log('email missing/invalid', $context + ['email' => $email]);
        return 0;
    }

    $first_name = isset($payload['first_name']) ? sanitize_text_field((string) $payload['first_name']) : '';
    $last_name = isset($payload['last_name']) ? sanitize_text_field((string) $payload['last_name']) : '';
    if ($first_name === '' && $last_name === '' && !empty($payload['name'])) {
        [$first_name, $last_name] = peracrm_ingest_split_name((string) $payload['name']);
    }

    $property_id = peracrm_ingest_guess_property_id($payload);
    $page_url = peracrm_ingest_source_url_from_payload($payload);
    $post_id = $page_url !== '' ? (int) url_to_postid($page_url) : 0;
    $message = isset($payload['message']) ? sanitize_textarea_field((string) $payload['message']) : '';

    $context_log = $context + [
        'current_blog_id' => (int) get_current_blog_id(),
        'target_blog_id' => (int) peracrm_get_target_blog_id(),
        'page_url' => $page_url,
        'property_id' => $property_id,
    ];
    peracrm_ingest_debug_log('ingest start', $context_log);

    $raw_fields = [];
    if (!empty($payload['raw_fields']) && is_array($payload['raw_fields'])) {
        $raw_fields = peracrm_ingest_sanitize_raw_fields($payload['raw_fields']);
    }

    $phone = isset($payload['phone']) ? sanitize_text_field((string) $payload['phone']) : '';
    $phone = preg_replace('/[^0-9+]/', '', $phone);

    $result = (int) peracrm_with_target_blog(static function () use ($email, $first_name, $last_name, $payload, $context, $property_id, $page_url, $post_id, $raw_fields, $message, $phone) {
        global $wpdb;

        $client_id = (int) peracrm_find_or_create_client_by_email($email, [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'source' => !empty($context['handler']) ? sanitize_key((string) $context['handler']) : 'website_form',
            'status' => 'enquiry',
        ]);

        if ($client_id <= 0) {
            peracrm_ingest_debug_log('client create/update failed', $context + [
                'wpdb_error' => isset($wpdb->last_error) ? (string) $wpdb->last_error : '',
            ]);
            return 0;
        }

        peracrm_ingest_debug_log('client created/updated', $context + ['client_id' => $client_id]);

        if (function_exists('peracrm_party_upsert_status') && function_exists('peracrm_party_get')) {
            $party = peracrm_party_get($client_id);
            $existing_stage = isset($party['lead_pipeline_stage']) ? sanitize_key((string) $party['lead_pipeline_stage']) : '';
            $stage_to_set = ($existing_stage === '' || $existing_stage === 'new_enquiry') ? 'new_enquiry' : $existing_stage;

            $party_updated = peracrm_party_upsert_status($client_id, [
                'lead_pipeline_stage' => $stage_to_set,
                'engagement_state' => 'engaged',
                'disposition' => 'none',
            ]);

            peracrm_ingest_debug_log('party status upserted', $context + [
                'client_id' => $client_id,
                'existing_stage' => $existing_stage,
                'stage_set' => $stage_to_set,
                'party_updated' => $party_updated ? 1 : 0,
            ]);
        }

        $fingerprint = peracrm_ingest_fingerprint(
            $email,
            !empty($context['handler']) ? (string) $context['handler'] : 'website_form',
            $property_id,
            $message
        );

        $event_payload = [
            'form' => !empty($context['handler']) ? sanitize_key((string) $context['handler']) : 'website_form',
            'form_id' => !empty($context['form_id']) ? sanitize_text_field((string) $context['form_id']) : '',
            'form_name' => !empty($context['form_name']) ? sanitize_text_field((string) $context['form_name']) : '',
            'form_context' => isset($payload['form_context']) ? sanitize_key((string) $payload['form_context']) : '',
            'source' => 'website_form',
            'fingerprint' => $fingerprint,
            'page_url' => $page_url,
            'post_id' => $post_id,
            'property_id' => $property_id,
            'message' => $message,
            'preferred_contact' => isset($payload['preferred_contact']) ? sanitize_text_field((string) $payload['preferred_contact']) : '',
            'submitted_at' => peracrm_now_mysql(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'ip' => !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? sanitize_text_field(trim((string) explode(',', wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']))[0])) : (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : ''),
            'referrer' => isset($context['referrer']) ? esc_url_raw((string) $context['referrer']) : '',
            'current_blog_id' => isset($context['current_blog_id']) ? (int) $context['current_blog_id'] : (int) get_current_blog_id(),
            'target_blog_id' => isset($context['target_blog_id']) ? (int) $context['target_blog_id'] : (int) peracrm_get_target_blog_id(),
            'site_url' => isset($context['site_url']) ? esc_url_raw((string) $context['site_url']) : esc_url_raw((string) home_url('/')),
        ];

        if (!empty($raw_fields)) {
            $event_payload['raw_fields'] = $raw_fields;
        }

        $activity_logged = false;
        if (peracrm_ingest_recent_fingerprint_exists($fingerprint, 300)) {
            peracrm_ingest_debug_log('event deduped by fingerprint', $context + ['client_id' => $client_id, 'fingerprint' => $fingerprint]);
        } else {
            $activity_logged = (bool) peracrm_log_event($client_id, 'enquiry', $event_payload);
            peracrm_ingest_debug_log('event logged', $context + ['client_id' => $client_id, 'activity_logged' => $activity_logged ? 1 : 0]);
        }

        $property_linked = false;
        if ($property_id > 0 && function_exists('peracrm_client_property_link')) {
            $property_linked = (bool) peracrm_client_property_link($client_id, $property_id, 'enquiry');
            peracrm_ingest_debug_log('property link attempted', $context + [
                'client_id' => $client_id,
                'property_id' => $property_id,
                'property_linked' => $property_linked ? 1 : 0,
            ]);
        }

        if (!empty($wpdb->last_error)) {
            peracrm_ingest_debug_log('wpdb last_error', $context + ['wpdb_error' => (string) $wpdb->last_error]);
        }

        peracrm_ingest_debug_log('ingest complete', $context + [
            'client_id' => $client_id,
            'activity_logged' => $activity_logged ? 1 : 0,
            'property_linked' => $property_linked ? 1 : 0,
        ]);

        return $client_id;
    });

    return $result > 0 ? $result : 0;
}

function peracrm_ingest_should_capture_sr()
{
    $seen = isset($_POST['sr_action']);
    peracrm_ingest_debug_log('detector sr_action seen', ['seen' => $seen ? 1 : 0]);
    if (!$seen || !isset($_POST['sr_nonce'])) {
        if ($seen) {
            peracrm_ingest_debug_log('detector sr_action nonce fail', ['reason' => 'nonce_missing']);
        }
        return false;
    }

    $nonce = wp_unslash($_POST['sr_nonce']);
    if (!wp_verify_nonce($nonce, 'pera_seller_landlord_enquiry')) {
        peracrm_ingest_debug_log('detector sr_action nonce fail', ['reason' => 'nonce_invalid']);
        return false;
    }

    peracrm_ingest_debug_log('detector sr_action nonce pass');

    $honeypot = isset($_POST['sr_company']) ? sanitize_text_field(wp_unslash($_POST['sr_company'])) : '';
    if ($honeypot !== '') {
        peracrm_ingest_debug_log('detector sr_action honeypot fail');
        return false;
    }

    return true;
}

function peracrm_ingest_should_capture_favourites()
{
    $seen = isset($_POST['fav_enquiry_action']);
    peracrm_ingest_debug_log('detector fav_enquiry_action seen', ['seen' => $seen ? 1 : 0]);
    if (!$seen || !isset($_POST['fav_nonce'])) {
        if ($seen) {
            peracrm_ingest_debug_log('detector fav_enquiry_action nonce fail', ['reason' => 'nonce_missing']);
        }
        return false;
    }

    $nonce = wp_unslash($_POST['fav_nonce']);
    if (!wp_verify_nonce($nonce, 'pera_favourites_enquiry')) {
        peracrm_ingest_debug_log('detector fav_enquiry_action nonce fail', ['reason' => 'nonce_invalid']);
        return false;
    }

    peracrm_ingest_debug_log('detector fav_enquiry_action nonce pass');

    $honeypot = isset($_POST['fav_company']) ? sanitize_text_field(wp_unslash($_POST['fav_company'])) : '';
    if ($honeypot !== '') {
        peracrm_ingest_debug_log('detector fav_enquiry_action honeypot fail');
        return false;
    }

    return true;
}

function peracrm_ingest_should_capture_citizenship()
{
    $seen = isset($_POST['pera_citizenship_action']);
    peracrm_ingest_debug_log('detector pera_citizenship_action seen', ['seen' => $seen ? 1 : 0]);
    if (!$seen || !isset($_POST['pera_citizenship_nonce'])) {
        if ($seen) {
            peracrm_ingest_debug_log('detector pera_citizenship_action nonce fail', ['reason' => 'nonce_missing']);
        }
        return false;
    }

    $nonce = wp_unslash($_POST['pera_citizenship_nonce']);
    if (!wp_verify_nonce($nonce, 'pera_citizenship_enquiry')) {
        peracrm_ingest_debug_log('detector pera_citizenship_action nonce fail', ['reason' => 'nonce_invalid']);
        return false;
    }

    peracrm_ingest_debug_log('detector pera_citizenship_action nonce pass');
    return true;
}

function peracrm_ingest_theme_enquiries($trigger = 'init')
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    if (peracrm_ingest_should_capture_sr()) {
        if (peracrm_ingest_mark_flow_handled('sr_action')) {
            peracrm_ingest_debug_log('theme sr_action already ingested; skipping duplicate trigger', ['trigger' => $trigger]);
            return;
        }

        $name = isset($_POST['sr_name']) ? sanitize_text_field(wp_unslash($_POST['sr_name'])) : '';
        [$first_name, $last_name] = peracrm_ingest_split_name($name);
        $sr_intent = isset($_POST['sr_intent']) ? sanitize_text_field(wp_unslash($_POST['sr_intent'])) : '';
        $sr_context = isset($_POST['sr_context']) ? sanitize_text_field(wp_unslash($_POST['sr_context'])) : '';
        $raw_form_context = isset($_POST['form_context']) ? sanitize_text_field(wp_unslash($_POST['form_context'])) : '';
        $normalized_form_context = peracrm_ingest_normalize_sr_form_context($raw_form_context, $sr_context, $sr_intent);
        $sr_phone = function_exists('peracrm_phone_canonical_from_source')
            ? peracrm_phone_canonical_from_source($_POST, 'sr_phone_country', 'sr_phone_national', 'sr_phone')
            : (isset($_POST['sr_phone']) ? sanitize_text_field(wp_unslash($_POST['sr_phone'])) : '');

        $_POST['sr_phone'] = $sr_phone;

        peracrm_ingest_enquiry([
            'form_context' => $normalized_form_context,
            'email' => isset($_POST['sr_email']) ? sanitize_email(wp_unslash($_POST['sr_email'])) : '',
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $sr_phone,
            'message' => isset($_POST['sr_message']) ? sanitize_textarea_field(wp_unslash($_POST['sr_message'])) : '',
            'property_id' => isset($_POST['sr_property_id']) ? absint($_POST['sr_property_id']) : 0,
            'page_url' => !empty($_POST['_wp_http_referer']) ? esc_url_raw(wp_unslash($_POST['_wp_http_referer'])) : '',
            'sr_property_url' => isset($_POST['sr_property_url']) ? esc_url_raw(wp_unslash($_POST['sr_property_url'])) : '',
            'raw_fields' => peracrm_ingest_collect_prefixed_post_fields('sr_', ['sr_action', 'sr_nonce', 'sr_company']),
        ], peracrm_ingest_request_context('theme_sr_form', '', 'sr_enquiry') + ['trigger' => sanitize_key((string) $trigger)]);

        return;
    }

    if (peracrm_ingest_should_capture_favourites()) {
        if (peracrm_ingest_mark_flow_handled('fav_enquiry_action')) {
            peracrm_ingest_debug_log('theme fav_enquiry_action already ingested; skipping duplicate trigger', ['trigger' => $trigger]);
            return;
        }

        $first_name = isset($_POST['fav_first_name']) ? sanitize_text_field(wp_unslash($_POST['fav_first_name'])) : '';
        $last_name = isset($_POST['fav_last_name']) ? sanitize_text_field(wp_unslash($_POST['fav_last_name'])) : '';
        $email = isset($_POST['fav_email']) ? sanitize_email(wp_unslash($_POST['fav_email'])) : '';
        $phone = isset($_POST['fav_phone']) ? sanitize_text_field(wp_unslash($_POST['fav_phone'])) : '';

        if (is_user_logged_in() && ($first_name === '' || $last_name === '' || !is_email($email) || $phone === '')) {
            $user = wp_get_current_user();
            if ($first_name === '') {
                $first_name = (string) get_user_meta($user->ID, 'first_name', true);
            }
            if ($last_name === '') {
                $last_name = (string) get_user_meta($user->ID, 'last_name', true);
            }
            if (!is_email($email)) {
                $email = (string) $user->user_email;
            }
            if ($phone === '') {
                foreach (['phone', 'mobile', 'billing_phone'] as $phone_key) {
                    $candidate = (string) get_user_meta($user->ID, $phone_key, true);
                    if ($candidate !== '') {
                        $phone = $candidate;
                        break;
                    }
                }
            }
        }

        peracrm_ingest_enquiry([
            'form_context' => 'favourites',
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'message' => isset($_POST['fav_message']) ? sanitize_textarea_field(wp_unslash($_POST['fav_message'])) : '',
            'page_url' => !empty($_POST['_wp_http_referer']) ? esc_url_raw(wp_unslash($_POST['_wp_http_referer'])) : '',
            'raw_fields' => peracrm_ingest_collect_prefixed_post_fields('fav_', ['fav_enquiry_action', 'fav_nonce', 'fav_company']),
        ], peracrm_ingest_request_context('theme_favourites_form', '', 'favourites_enquiry') + ['trigger' => sanitize_key((string) $trigger)]);

        return;
    }

    if (peracrm_ingest_should_capture_citizenship()) {
        if (peracrm_ingest_mark_flow_handled('pera_citizenship_action')) {
            peracrm_ingest_debug_log('theme pera_citizenship_action already ingested; skipping duplicate trigger', ['trigger' => $trigger]);
            return;
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        [$first_name, $last_name] = peracrm_ingest_split_name($name);

        peracrm_ingest_enquiry([
            'form_context' => 'citizenship',
            'email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
            'message' => isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '',
            'preferred_contact' => !empty($_POST['contact_method']) && is_array($_POST['contact_method']) ? implode(', ', array_map('sanitize_text_field', wp_unslash($_POST['contact_method']))) : '',
            'page_url' => !empty($_POST['_wp_http_referer']) ? esc_url_raw(wp_unslash($_POST['_wp_http_referer'])) : '',
            'raw_fields' => [
                'enquiry_type' => isset($_POST['enquiry_type']) ? sanitize_text_field(wp_unslash($_POST['enquiry_type'])) : '',
                'family' => isset($_POST['family']) ? sanitize_text_field(wp_unslash($_POST['family'])) : '',
            ],
        ], peracrm_ingest_request_context('theme_citizenship_form', '', 'citizenship_enquiry') + ['trigger' => sanitize_key((string) $trigger)]);
    }
}

add_action('init', static function () {
    peracrm_ingest_debug_log('init ingestion hook fired', [
        'current_blog_id' => (int) get_current_blog_id(),
        'target_blog_id' => (int) peracrm_get_target_blog_id(),
        'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
        'has_sr_action' => isset($_POST['sr_action']) ? 1 : 0,
        'has_fav_enquiry_action' => isset($_POST['fav_enquiry_action']) ? 1 : 0,
        'has_pera_citizenship_action' => isset($_POST['pera_citizenship_action']) ? 1 : 0,
    ]);

    peracrm_ingest_theme_enquiries('init');
}, 1);

add_action('template_redirect', static function () {
    peracrm_ingest_theme_enquiries('template_redirect');
}, 0);
