<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Identifiers used only by the observational Embedded Signup launcher.
 * These are public application/configuration identifiers, not secrets.
 */
function peracrm_whatsapp_embedded_signup_config()
{
    $settings = peracrm_whatsapp_get_settings();
    $graph_version = (string) ($settings['graph_api_version'] ?? 'v22.0');
    if (!preg_match('/^v[0-9]+\.[0-9]+$/', $graph_version)) {
        $graph_version = 'v22.0';
    }

    return [
        'app_id' => '1684147635917810',
        'configuration_id' => '28055443294150871',
        'graph_version' => $graph_version,
    ];
}

/**
 * Builds the non-OAuth Embedded Signup extras payload.
 *
 * This is the single location for the WhatsApp Business App onboarding /
 * Coexistence launch selector used by the diagnostic launcher.
 */
function peracrm_whatsapp_embedded_signup_extras()
{
    return [
        'setup' => (object) [],
        'featureType' => 'whatsapp_business_app_onboarding',
        'sessionInfoVersion' => '3',
    ];
}

function peracrm_whatsapp_embedded_signup_enqueue_assets($version = null)
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $config = peracrm_whatsapp_embedded_signup_config();
    wp_enqueue_script('facebook-jssdk', 'https://connect.facebook.net/en_US/sdk.js', [], null, true);
    wp_enqueue_script(
        'peracrm-whatsapp-embedded-signup',
        PERACRM_URL . '/assets/whatsapp-embedded-signup.js',
        ['facebook-jssdk'],
        $version,
        true
    );
    wp_localize_script('peracrm-whatsapp-embedded-signup', 'peracrmWhatsAppEmbeddedSignup', [
        'appId' => $config['app_id'],
        'configurationId' => $config['configuration_id'],
        'graphVersion' => $config['graph_version'],
        'extras' => peracrm_whatsapp_embedded_signup_extras(),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('peracrm_whatsapp_embedded_signup'),
    ]);
}

function peracrm_whatsapp_embedded_signup_render_panel()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $config = peracrm_whatsapp_embedded_signup_config();
    $rows = [
        'sdk' => __('SDK loaded', 'peracrm'),
        'launch' => __('Embedded Signup launch status', 'peracrm'),
        'event' => __('Latest event type', 'peracrm'),
        'waba' => __('Returned WABA ID', 'peracrm'),
        'phone' => __('Returned Phone Number ID', 'peracrm'),
        'business' => __('Returned Business ID', 'peracrm'),
        'code' => __('Authorization code received', 'peracrm'),
        'outcome' => __('Error / cancellation status', 'peracrm'),
    ];

    echo '<section class="peracrm-whatsapp-admin__section" id="peracrm-wa-embedded-signup">';
    echo '<h2>' . esc_html__('Diagnostic Embedded Signup tool', 'peracrm') . '</h2>';
    echo '<p>' . esc_html__('Administrator-only, observational launcher. Review every Meta screen and cancel if migration, deregistration, or disconnection is offered instead of an explicit Business App / Coexistence path. PeraCRM will not exchange the returned code or call Meta APIs.', 'peracrm') . '</p>';
    echo '<p><button type="button" class="button button-primary" id="peracrm-wa-embedded-signup-launch">' . esc_html__('Launch WhatsApp Embedded Signup', 'peracrm') . '</button></p>';
    echo '<table class="widefat striped peracrm-whatsapp-table"><tbody>';
    echo '<tr><th scope="row">' . esc_html__('App ID', 'peracrm') . '</th><td><code>' . esc_html($config['app_id']) . '</code></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Configuration ID', 'peracrm') . '</th><td><code>' . esc_html($config['configuration_id']) . '</code></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Graph version', 'peracrm') . '</th><td><code>' . esc_html($config['graph_version']) . '</code></td></tr>';
    foreach ($rows as $key => $label) {
        $initial = in_array($key, ['sdk', 'code'], true) ? __('No', 'peracrm') : '—';
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td data-peracrm-wa-diagnostic="' . esc_attr($key) . '">' . esc_html($initial) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p class="description">' . esc_html__('Only non-secret identifiers and event state are shown. OAuth codes, tokens, secrets, PINs, OTPs, and unrestricted message payloads are never displayed or logged.', 'peracrm') . '</p>';
    echo '</section>';
}

function peracrm_whatsapp_embedded_signup_receive_code()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'peracrm')], 403);
    }

    if (!check_ajax_referer('peracrm_whatsapp_embedded_signup', 'nonce', false)) {
        wp_send_json_error(['message' => __('Invalid security token.', 'peracrm')], 403);
    }

    $code = isset($_POST['code']) ? trim((string) wp_unslash($_POST['code'])) : '';
    if ($code === '' || strlen($code) > 4096 || !preg_match('/^[A-Za-z0-9._~+\/=\-]+$/', $code)) {
        wp_send_json_error(['message' => __('Invalid authorization code.', 'peracrm')], 400);
    }

    // Deliberately validate in memory only: no exchange, persistence, or logging.
    wp_send_json_success(['received' => true]);
}
