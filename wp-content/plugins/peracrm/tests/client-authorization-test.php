<?php

define('ABSPATH', __DIR__ . '/');

class WP_User
{
    public $ID;
    public $roles;
    public function __construct($id, array $roles) { $this->ID = $id; $this->roles = $roles; }
}

$GLOBALS['auth_users'] = [
    1 => new WP_User(1, ['administrator']),
    7 => new WP_User(7, ['employee']),
    8 => new WP_User(8, ['employee']),
    9 => new WP_User(9, ['employee']),
    10 => new WP_User(10, ['employee']),
    11 => new WP_User(11, ['employee']),
    12 => new WP_User(12, ['manager']),
    13 => new WP_User(13, ['employee']),
];
$GLOBALS['auth_caps'] = [
    1 => ['manage_options' => true],
    7 => ['edit_crm_clients' => true],
    8 => ['edit_crm_clients' => true],
    9 => ['edit_crm_clients' => true, 'peracrm_manage_all_reminders' => true],
    10 => [],
    11 => ['edit_crm_deals' => true],
    12 => ['edit_crm_clients' => true, 'peracrm_manage_all_clients' => true],
    13 => ['edit_crm_clients' => true],
];
$GLOBALS['auth_posts'] = [101 => 'crm_client', 102 => 'crm_client', 103 => 'crm_client', 104 => 'crm_client', 105 => 'crm_client', 106 => 'crm_client', 107 => 'crm_client', 108 => 'crm_client', 200 => 'post'];
$GLOBALS['auth_meta'] = [
    101 => ['assigned_advisor_user_id' => 7],
    102 => ['crm_assigned_advisor' => 7],
    103 => ['assigned_advisor_user_id' => 7, 'crm_assigned_advisor' => 7],
    104 => ['assigned_advisor_user_id' => 7, 'crm_assigned_advisor' => 8],
    105 => [],
    106 => ['assigned_advisor_user_id' => 8, 'crm_assigned_advisor' => 8],
    107 => ['assigned_advisor_user_id' => 10],
    108 => ['assigned_advisor_user_id' => 11],
];

function get_userdata($id) { return $GLOBALS['auth_users'][(int) $id] ?? false; }
function user_can($user, $cap) { $id = $user instanceof WP_User ? $user->ID : (int) $user; return !empty($GLOBALS['auth_caps'][$id][$cap]); }
function current_user_can($cap) { return false; }
function is_multisite() { return false; }
function get_post_meta($id, $key) { return $GLOBALS['auth_meta'][(int) $id][$key] ?? ''; }
function get_post_type($id) { return $GLOBALS['auth_posts'][(int) $id] ?? false; }
function get_posts($args) {
    $wanted = (int) $args['meta_query'][1]['value'];
    return array_keys(array_filter($GLOBALS['auth_meta'], static function ($meta) use ($wanted) {
        return (int) ($meta['assigned_advisor_user_id'] ?? 0) === $wanted || (int) ($meta['crm_assigned_advisor'] ?? 0) === $wanted;
    }));
}
function add_filter() {}

require dirname(__DIR__) . '/inc/helpers.php';
require dirname(__DIR__) . '/inc/services/client_scope_service.php';

function auth_expect($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

auth_expect(7, peracrm_client_get_assigned_advisor_id(101), 'current field resolves');
auth_expect(7, peracrm_client_get_assigned_advisor_id(102), 'legacy field resolves');
auth_expect(7, peracrm_client_get_assigned_advisor_id(103), 'equal dual fields resolve');
auth_expect(0, peracrm_client_get_assigned_advisor_id(104), 'conflicting fields fail closed');
auth_expect(0, peracrm_client_get_assigned_advisor_id(105), 'unassigned client fails closed');
auth_expect([101, 102, 103], peracrm_get_client_access_scope(7)['ids'], 'assigned scope excludes conflict');
auth_expect('empty', peracrm_get_client_access_scope(13)['type'], 'empty employee scope is explicit');
auth_expect('full', peracrm_get_client_access_scope(1)['type'], 'administrator has full scope');
auth_expect(true, peracrm_user_can_access_client(7, 101), 'employee A can access assigned Client A');
auth_expect(false, peracrm_user_can_access_client(7, 106), 'employee A cannot access employee B Client B');
auth_expect(false, peracrm_user_can_access_client(7, 104), 'employee cannot access conflicting assignment');
auth_expect(false, peracrm_user_can_access_client(7, 200), 'non-client ID is rejected');
auth_expect(false, peracrm_user_can_access_client(9, 101), 'reminder-wide capability is not client-wide access');
auth_expect(false, peracrm_user_can_access_client(10, 107), 'assignment alone does not grant CRM access');
auth_expect(false, peracrm_user_can_access_client(11, 108), 'deal-only capability is not baseline client access');
auth_expect(true, peracrm_user_can_access_client(12, 106), 'manager with manage-all capability has full scope');
auth_expect(true, peracrm_user_can_access_client(1, 104), 'administrator can access a valid client despite assignment conflict');

echo "PeraCRM client authorization tests passed\n";
