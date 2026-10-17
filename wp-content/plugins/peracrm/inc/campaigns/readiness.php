<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Cheap target-blog readiness gate; only successful schema assertions enable services. */
function peracrm_campaigns_schema_ready()
{
    return peracrm_with_target_blog(static function () {
        $status = peracrm_campaigns_schema_option('peracrm_campaigns_schema_status', []);
        return ($status['state'] ?? '') === 'ready' && (int) ($status['version'] ?? 0) === 21
            && (int) peracrm_campaigns_schema_option('peracrm_schema_version', 0) >= 21;
    });
}

/** Real target-blog administrator only; no employee/manager or impersonated retries. */
function peracrm_campaigns_can_retry_schema()
{
    return peracrm_with_target_blog(static function () {
        $actor = peracrm_get_actor_user_id();
        return $actor > 0 && user_can($actor, 'manage_options') && !peracrm_is_impersonating_crm_user();
    });
}

/** Internal staff service, also usable from authenticated WP-CLI eval. */
function peracrm_campaigns_retry_schema()
{
    if (!peracrm_campaigns_can_retry_schema()) {
        return new WP_Error('campaign_forbidden', 'Only an administrator may retry the campaign schema.');
    }
    return peracrm_campaigns_run_schema_upgrade(true);
}

/** Plugin-owned WordPress admin notice; no exception/SQL text is rendered. */
function peracrm_campaigns_schema_admin_notice()
{
    if (!peracrm_campaigns_can_retry_schema()) {
        return;
    }
    $status = peracrm_with_target_blog(static function () {
        return peracrm_campaigns_schema_option('peracrm_campaigns_schema_status', []);
    });
    if (!in_array($status['state'] ?? '', ['failed', 'checking'], true)) {
        return;
    }
    echo '<div class="notice notice-error"><p>' . esc_html(
        'PeraCRM campaign schema is unavailable (schema_verification_failed). Existing website and CRM features remain available. Check database permissions and table definitions, then retry. An interrupted verification can be retried after 15 minutes.'
    ) . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="peracrm_campaigns_retry_schema">';
    wp_nonce_field('peracrm_campaigns_retry_schema');
    echo '<p><button type="submit" class="button">' . esc_html('Retry campaign schema verification') . '</button></p></form></div>';
}
add_action('admin_notices', 'peracrm_campaigns_schema_admin_notice');
add_action('network_admin_notices', 'peracrm_campaigns_schema_admin_notice');

function peracrm_campaigns_handle_schema_retry()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !peracrm_campaigns_can_retry_schema()) {
        wp_die('Unauthorized', '', ['response' => 403]);
    }
    check_admin_referer('peracrm_campaigns_retry_schema');
    peracrm_campaigns_retry_schema();
    wp_safe_redirect(admin_url());
    exit;
}
add_action('admin_post_peracrm_campaigns_retry_schema', 'peracrm_campaigns_handle_schema_retry');
