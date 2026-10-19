<?php
if (!defined('ABSPATH')) { exit; }
function peracrm_campaign_drafts_register_routes()
{
    add_rewrite_rule('^crm/campaigns/queue/?$', 'index.php?pera_crm=1&pera_crm_view=campaigns&peracrm_campaign_screen=queue', 'top');
    add_rewrite_rule('^crm/campaigns/([0-9]+)/queue/([0-9]+)/?$', 'index.php?pera_crm=1&pera_crm_view=campaigns&peracrm_campaign_screen=queue&peracrm_campaign_id=$matches[1]&peracrm_campaign_job_id=$matches[2]', 'top');
    add_rewrite_rule('^crm/campaigns/([0-9]+)/queue/?$', 'index.php?pera_crm=1&pera_crm_view=campaigns&peracrm_campaign_screen=queue&peracrm_campaign_id=$matches[1]', 'top');
    add_rewrite_rule('^crm/campaigns/(new|configuration)/?$', 'index.php?pera_crm=1&pera_crm_view=campaigns&peracrm_campaign_screen=$matches[1]', 'top');
    add_rewrite_rule('^crm/campaigns/([0-9]+)/?$', 'index.php?pera_crm=1&pera_crm_view=campaigns&peracrm_campaign_id=$matches[1]', 'top');
    add_rewrite_rule('^crm/campaigns/?$', 'index.php?pera_crm=1&pera_crm_view=campaigns', 'top');
}
add_filter('query_vars', static function ($vars) { return array_merge($vars,['peracrm_campaign_id','peracrm_campaign_screen','peracrm_campaign_job_id']); });

/** Same-page errors retain submitted fields and original revision; never put draft content in URLs/session. */
add_action('template_redirect', static function () {
    if ((string)get_query_var('pera_crm_view') !== 'campaigns') { return; }
    if (!peracrm_campaigns_staff_can('peracrm_manage_campaigns')) { wp_die('Campaign access is not permitted.','',['response'=>403]); }
    peracrm_subscribers_private_headers();
    if ((string)get_query_var('peracrm_campaign_screen') === 'queue') {
        if (!peracrm_campaigns_staff_can('peracrm_manage_campaigns',true)) { wp_die('Queue access denied.','',['response'=>403]); }
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD'],true)) { wp_die('Use the protected queue controls.','',['response'=>405]); }
        return;
    }
    $configuration = (string)get_query_var('peracrm_campaign_screen') === 'configuration';
    if ($configuration) {
        try { peracrm_campaign_drafts_require(false,true); } catch (Throwable $error) { wp_die('Administrator access required.','',['response'=>403]); }
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method,['GET','HEAD','POST'],true)) { wp_die('Method not allowed.','',['response'=>405]); }
    if ($method !== 'POST') { return; }
    if (!peracrm_campaigns_staff_can('peracrm_manage_campaigns',true)) { wp_die('Campaign writes are not permitted.','',['response'=>403]); }
    check_admin_referer('peracrm_campaign_draft');
    $input = isset($_POST['document']) && is_array($_POST['document']) ? wp_unslash($_POST['document']) : [];
    // Ordered form arrays can have gaps after remove; only the form adapter normalizes indexes.
    if (isset($input['blocks']) && is_array($input['blocks'])) { $input['blocks'] = array_values($input['blocks']); }
    if ($configuration && isset($input['senders']) && is_array($input['senders'])) { $input['senders'] = array_values($input['senders']); }
    $raw_revision = $_POST['revision'] ?? '';
    $revision = is_string($raw_revision) && preg_match('/^[0-9]{1,10}$/D',$raw_revision) ? (int)$raw_revision : -1;
    $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? $_POST['operation'] : '';
    if ($configuration && $operation === 'configuration') {
        $result = peracrm_campaign_configuration_save($revision,$input);
    } elseif (!$configuration && $operation === 'save') {
        $result = peracrm_campaign_draft_save((int)get_query_var('peracrm_campaign_id'),$revision,$input);
    } elseif (!$configuration && $operation === 'duplicate') {
        $raw_id = $_POST['source_id'] ?? '';
        $result = is_string($raw_id) && ctype_digit($raw_id) ? peracrm_campaign_draft_duplicate((int)$raw_id) : new WP_Error('campaign_invalid','Invalid campaign.');
    } else { $result = new WP_Error('campaign_invalid','Invalid operation.'); }
    if (is_wp_error($result)) {
        $http_status = $result->get_error_code() === 'campaign_conflict' ? 409 : 422;
        $GLOBALS['peracrm_campaign_form'] = ['document'=>$input,'revision'=>$revision,'error'=>$result->get_error_message(),'http_status'=>$http_status];
        status_header($http_status);
        return;
    }
    wp_safe_redirect(add_query_arg('campaign_saved','1',peracrm_campaign_drafts_url($configuration ? 'configuration/' : $result['id'].'/')));
    exit;
}, 1);
