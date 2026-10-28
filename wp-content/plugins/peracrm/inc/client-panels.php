<?php
/** Client workspace registry and employee preferences. See docs/client-panels.md. */
if (!defined('ABSPATH')) { exit; }

function peracrm_client_panel_registry(): array
{
    // Trusted allowlist: never derive dismissibility from request data or markup.
    return [
        'crm-client-summary' => ['label' => __('Client summary', 'peracrm'), 'optional' => false],
        'profile' => ['label' => __('Profile and key facts', 'peracrm'), 'optional' => false],
        'status' => ['label' => __('CRM status', 'peracrm'), 'optional' => false],
        'reminders' => ['label' => __('Next actions and reminders', 'peracrm'), 'optional' => false],
        'notes' => ['label' => __('Advisor notes', 'peracrm'), 'optional' => false],
        'timeline' => ['label' => __('Activity and timeline', 'peracrm'), 'optional' => false],
        'deals' => ['label' => __('Deals', 'peracrm'), 'optional' => true, 'stages' => ['offer_made', 'deal_closed']],
        'seller_properties' => ['label' => __('Properties for sale', 'peracrm'), 'optional' => true, 'type' => 'seller'],
        'landlord_properties' => ['label' => __('Properties for rent', 'peracrm'), 'optional' => true, 'type' => 'landlord'],
        'enquiries' => ['label' => __('Client enquiries', 'peracrm'), 'optional' => true],
        'theme_portfolio' => ['label' => __('Theme portfolio', 'peracrm'), 'optional' => true],
    ];
}

function peracrm_client_panels_eligible(array $profile, array $party): array
{
    return array_filter(peracrm_client_panel_registry(), static function ($panel) use ($profile, $party) {
        return $panel['optional']
            && (!isset($panel['type']) || $panel['type'] === ($profile['client_type'] ?? ''))
            && (!isset($panel['stages']) || in_array($party['lead_pipeline_stage'] ?? '', $panel['stages'], true));
    });
}

function peracrm_client_panels_allowed(int $client_id): bool
{
    if (!is_user_logged_in() || $client_id <= 0 || !function_exists('pera_crm_client_view_access_state')) { return false; }
    return !empty(pera_crm_client_view_access_state($client_id)['allowed']);
}

function peracrm_client_panels_meta_key(int $client_id): string
{
    // One bounded record per customized client, namespaced to the CRM target blog.
    $blog_id = peracrm_with_target_blog(static function () { return get_current_blog_id(); });
    return '_peracrm_client_panels_v1_' . $blog_id . '_' . $client_id;
}

function peracrm_client_panels_hidden(int $client_id): array
{
    if (!peracrm_client_panels_allowed($client_id)) { return []; }
    $stored = get_user_meta(get_current_user_id(), peracrm_client_panels_meta_key($client_id), true);
    if (!is_array($stored) || ($stored['version'] ?? 0) !== 1 || !is_array($stored['hidden'] ?? null)) { return []; }
    $optional = array_filter(peracrm_client_panel_registry(), static function ($panel) { return $panel['optional']; });
    // Filter on reads too: tampered/stale preferences cannot hide protected panels.
    return array_values(array_intersect(array_keys($optional), array_filter($stored['hidden'], 'is_string')));
}

function peracrm_client_panels_change(int $client_id, string $operation, string $panel = '')
{
    if (!peracrm_client_panels_allowed($client_id)) { return new WP_Error('forbidden', __('Access denied.', 'peracrm')); }
    if (!in_array($operation, ['hide', 'show', 'restore_all'], true)) { return new WP_Error('invalid', __('Invalid panel action.', 'peracrm')); }
    if ($operation !== 'restore_all') {
        $registry = peracrm_client_panel_registry();
        if (empty($registry[$panel]['optional'])) { return new WP_Error('invalid', __('This panel cannot be hidden.', 'peracrm')); }
        $eligible = peracrm_with_target_blog(static function () use ($client_id) {
            return peracrm_client_panels_eligible((array) peracrm_client_get_profile($client_id), (array) peracrm_party_get($client_id));
        });
        if (!isset($eligible[$panel])) { return new WP_Error('invalid', __('This panel is currently unavailable. Refresh the page.', 'peracrm')); }
    }
    $user_id = get_current_user_id();
    $key = peracrm_client_panels_meta_key($client_id);
    $previous = get_user_meta($user_id, $key, true);
    $hidden = peracrm_client_panels_hidden($client_id);
    if ($operation === 'restore_all') { $hidden = []; }
    elseif ($operation === 'hide') { $hidden[] = $panel; }
    else { $hidden = array_diff($hidden, [$panel]); }
    $next = ['version' => 1, 'hidden' => array_values(array_unique($hidden))];
    if ($previous === $next || ($previous === '' && !$next['hidden'])) { return $next['hidden']; }
    // Compare previous value to avoid silently overwriting another tab's changes.
    $ok = $previous === '' ? add_user_meta($user_id, $key, $next, true) : update_user_meta($user_id, $key, $next, $previous);
    if (!$ok) { return new WP_Error('save_failed', __('Unable to save panel preferences. Please refresh and try again.', 'peracrm')); }
    return $next['hidden'];
}

function peracrm_client_panels_ajax(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_user_logged_in()) {
        wp_send_json_error(['message' => __('Unauthorized.', 'peracrm')], 403);
    }
    $client_id = isset($_POST['client_id']) && is_scalar($_POST['client_id']) ? absint($_POST['client_id']) : 0;
    $nonce = isset($_POST['nonce']) && is_string($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'peracrm_client_panels_' . $client_id)) {
        wp_send_json_error(['message' => __('Your session expired. Refresh and try again.', 'peracrm')], 403);
    }
    $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? wp_unslash($_POST['operation']) : '';
    $panel = isset($_POST['panel']) && is_string($_POST['panel']) ? wp_unslash($_POST['panel']) : '';
    $result = peracrm_client_panels_change($client_id, $operation, $panel);
    if (is_wp_error($result)) { wp_send_json_error(['message' => $result->get_error_message()], $result->get_error_code() === 'forbidden' ? 403 : 400); }
    wp_send_json_success(['hidden' => $result]);
}
add_action('wp_ajax_peracrm_client_panels', 'peracrm_client_panels_ajax');

function peracrm_client_panel_attributes(string $key, array $hidden): void
{
    $registry = peracrm_client_panel_registry();
    if (empty($registry[$key]['optional'])) { return; }
    echo ' data-crm-optional-panel="' . esc_attr($key) . '"';
    if (in_array($key, $hidden, true)) { echo ' hidden'; }
}

function peracrm_client_panel_dismiss(string $key): void
{
    $registry = peracrm_client_panel_registry();
    if (empty($registry[$key]['optional'])) { return; }
    echo '<button type="button" class="btn btn--ghost btn--blue crm-panel-dismiss" data-crm-panel-hide="' . esc_attr($key) . '" aria-label="' . esc_attr(sprintf(__('Hide %s panel', 'peracrm'), $registry[$key]['label'])) . '" hidden><span aria-hidden="true">×</span></button>';
}

/** Reconcile conditional panels after an existing status/profile AJAX save. */
function peracrm_client_panels_fragments(string $markup): array
{
    if (!class_exists('DOMDocument')) { return []; }
    $dom = new DOMDocument();
    $previous_errors = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $markup);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);
    $xpath = new DOMXPath($dom);
    $fragments = [];
    foreach (['instructions', 'deals'] as $slot) {
        $node = $xpath->query('//*[@data-crm-panel-slot="' . $slot . '"]')->item(0);
        if ($node) { $fragments[$slot] = $dom->saveHTML($node); }
    }
    $node = $xpath->query('//*[@data-crm-manage-panels]')->item(0);
    if ($node) { $fragments['manage'] = $dom->saveHTML($node); }
    return $fragments;
}
