<?php

define('ABSPATH', __DIR__ . '/');
define('PERACRM_TARGET_BLOG_ID', 2);

class Role_Upgrade_Test_Role {
    public $caps = [];
    public function add_cap($cap) { $this->caps[$cap] = true; }
    public function remove_cap($cap) { unset($this->caps[$cap]); }
}

$GLOBALS['role_test_blog'] = 1;
$GLOBALS['role_test_stack'] = [];
$GLOBALS['role_test_roles'] = [
    2 => [
        'administrator' => new Role_Upgrade_Test_Role(),
        'manager' => new Role_Upgrade_Test_Role(),
        'employee' => new Role_Upgrade_Test_Role(),
        'advisor' => new Role_Upgrade_Test_Role(),
    ],
];
$GLOBALS['role_test_options'] = [
    1 => ['peracrm_legacy_advisor_user_ids' => []],
    2 => ['peracrm_legacy_advisor_user_ids' => [97]],
];
$GLOBALS['role_test_manage_options'] = [1 => true, 2 => true];

function add_action() {}
function get_current_blog_id() { return $GLOBALS['role_test_blog']; }
function switch_to_blog($blog_id) { $GLOBALS['role_test_stack'][] = $GLOBALS['role_test_blog']; $GLOBALS['role_test_blog'] = (int) $blog_id; }
function restore_current_blog() { $GLOBALS['role_test_blog'] = array_pop($GLOBALS['role_test_stack']); }
function peracrm_with_target_blog(callable $callback) {
    $switched = get_current_blog_id() !== PERACRM_TARGET_BLOG_ID;
    if ($switched) switch_to_blog(PERACRM_TARGET_BLOG_ID);
    try { return $callback(); } finally { if ($switched) restore_current_blog(); }
}
function get_role($slug) { return $GLOBALS['role_test_roles'][get_current_blog_id()][$slug] ?? null; }
function add_role($slug) { $GLOBALS['role_test_roles'][get_current_blog_id()][$slug] = new Role_Upgrade_Test_Role(); }
function remove_role($slug) { unset($GLOBALS['role_test_roles'][get_current_blog_id()][$slug]); }
function get_users($args) { return $args['role'] === 'advisor' ? [97] : []; }
function get_option($key, $default = false) { return $GLOBALS['role_test_options'][get_current_blog_id()][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['role_test_options'][get_current_blog_id()][$key] = $value; }
function delete_option($key) { unset($GLOBALS['role_test_options'][get_current_blog_id()][$key]); }
function current_user_can($cap) { return $cap === 'manage_options' && !empty($GLOBALS['role_test_manage_options'][get_current_blog_id()]); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

require dirname(__DIR__) . '/inc/roles.php';

function roles_expect($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

peracrm_upgrade_roles_and_caps_v20();
$manager = $GLOBALS['role_test_roles'][2]['manager'];
$admin = $GLOBALS['role_test_roles'][2]['administrator'];
$employee = $GLOBALS['role_test_roles'][2]['employee'];
foreach (['peracrm_manage_all_clients', 'peracrm_manage_assignments', 'peracrm_manage_all_reminders'] as $cap) {
    roles_expect(true, !empty($manager->caps[$cap]), "existing manager receives {$cap}");
    roles_expect(true, !empty($admin->caps[$cap]), "administrator retains {$cap}");
    roles_expect(false, !empty($employee->caps[$cap]), "employee does not receive {$cap}");
}
$manager_caps = $manager->caps;
peracrm_upgrade_roles_and_caps_v20();
roles_expect($manager_caps, $manager->caps, 'role capability upgrade is idempotent');
roles_expect(1, get_current_blog_id(), 'role upgrade restores the originating blog');

ob_start();
peracrm_legacy_advisor_migration_notice();
$notice = ob_get_clean();
roles_expect(true, strpos($notice, '97') !== false, 'notice reads legacy advisor IDs from target blog');
roles_expect(1, get_current_blog_id(), 'notice restores the originating admin blog');

echo "PeraCRM role upgrade and target-blog notice tests passed\n";
