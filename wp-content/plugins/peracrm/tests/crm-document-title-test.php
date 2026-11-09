<?php
/**
 * Standalone route-title regression; run with php tests/crm-document-title-test.php.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['crm_title_qv'] = [];
$GLOBALS['crm_title_admin'] = false;
$GLOBALS['crm_title_access'] = true;
$GLOBALS['crm_title_site'] = false;
function add_action(...$args) { return true; }
function add_filter(...$args) { return true; }
function get_query_var($key, $default = '') { return $GLOBALS['crm_title_qv'][$key] ?? $default; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function is_admin() { return $GLOBALS['crm_title_admin']; }
function __($text, $domain = '') { return $text; }
function absint($value) { return abs((int) $value); }
function peracrm_with_target_blog($fn) { return $fn(); }
function peracrm_user_can_access_client($user_id, $client_id) { return $GLOBALS['crm_title_access']; }
function pera_crm_client_view_get_client_id() { return (int) get_query_var('pera_crm_client_id', 0); }
function get_current_user_id() { return 42; }
function get_the_title($id) { return $id === 123 ? 'Jane Example' : ''; }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function get_post_meta($id, $key, $single) { return $key === '_peracrm_client_type' ? 'buyer' : ''; }
function peracrm_client_type_options() { return ['buyer' => 'Buyer']; }
require_once dirname(__DIR__) . '/inc/frontend/routing.php';
function expect_title($expected, $view = '', $action = '', $extra = []) {
    $GLOBALS['crm_title_qv'] = array_merge(['pera_crm' => '1', 'pera_crm_view' => $view, 'pera_crm_action' => $action], $extra);
    $actual = pera_crm_get_route_document_title();
    if ($actual !== $expected) {
        throw new RuntimeException('Expected "' . $expected . '", got "' . $actual . '"');
    }
    if (pera_crm_filter_client_document_title('Public property title') !== $expected) {
        throw new RuntimeException('pre_get_document_title filter failed for ' . $view);
    }
    $parts = pera_crm_filter_client_document_title_parts(['title' => 'Property for sale in Istanbul', 'site' => 'Pera Property']);
    if ($parts['title'] !== $expected || isset($parts['site'])) {
        throw new RuntimeException('document_title_parts filter failed for ' . $view);
    }
}
expect_title('Overview | PeraCRM');
foreach ([
    'leads' => 'Clients',
    'tasks' => 'Tasks',
    'appointments' => 'Appointments',
    'pipeline' => 'Pipeline',
    'performance' => 'Performance',
    'whatsapp_logs' => 'WhatsApp Logs',
    'email_logs' => 'Email Logs',
    'campaigns' => 'Campaigns',
    'subscribers' => 'Subscribers',
] as $view => $label) {
    expect_title($label . ' | PeraCRM', $view);
}
expect_title('New Client | PeraCRM', '', 'new');
expect_title('Jane Example - Buyer | PeraCRM', 'client', '', ['pera_crm_client_id' => 123]);
$GLOBALS['crm_title_access'] = false;
expect_title('Client | PeraCRM', 'client', '', ['pera_crm_client_id' => 123]);
$GLOBALS['crm_title_access'] = true;
expect_title('Campaign Queue | PeraCRM', 'campaigns', '', ['peracrm_campaign_screen' => 'queue']);
expect_title('New Campaign | PeraCRM', 'campaigns', '', ['peracrm_campaign_screen' => 'new']);
expect_title('Campaign Settings | PeraCRM', 'campaigns', '', ['peracrm_campaign_screen' => 'configuration']);
expect_title('Edit Campaign | PeraCRM', 'campaigns', '', ['peracrm_campaign_id' => 25]);
$GLOBALS['crm_title_qv'] = ['pera_crm' => '0'];
if (pera_crm_get_route_document_title() !== '' ||
    pera_crm_filter_client_document_title('Public title') !== 'Public title' ||
    pera_crm_filter_client_document_title_parts(['title' => 'Public title'])['title'] !== 'Public title') {
    throw new RuntimeException('Public title unexpectedly modified.');
}
$GLOBALS['crm_title_qv'] = ['pera_crm' => '1'];
$GLOBALS['crm_title_admin'] = true;
if (pera_crm_get_route_document_title() !== '') {
    throw new RuntimeException('Admin title unexpectedly modified.');
}
echo "CRM browser tab title tests passed.\n";
