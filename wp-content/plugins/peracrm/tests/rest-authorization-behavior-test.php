<?php

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

class WP_REST_Server { const READABLE = 'GET'; }
class WP_REST_Request {
    private $params;
    public function __construct(array $params = []) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}
class WP_REST_Response {
    private $data;
    public function __construct($data) { $this->data = $data; }
    public function get_data() { return $this->data; }
}
class WP_Post {
    public $ID = 101;
    public $post_type = 'crm_client';
    public $post_status = 'publish';
    public $post_title = 'Client A';
}
class Authorization_Test_WPDB {
    public $posts = 'wp_posts';
    public $queries = [];
    public function prepare($sql, ...$args) {
        $args = count($args) === 1 && is_array($args[0]) ? $args[0] : $args;
        foreach ($args as $value) {
            $replacement = is_int($value) ? (string) $value : "'" . addslashes((string) $value) . "'";
            $sql = preg_replace('/%[dsf]/', $replacement, $sql, 1);
        }
        return $sql;
    }
    public function get_var($sql) { $this->queries[] = $sql; return 1; }
    public function get_results($sql) {
        $this->queries[] = $sql;
        if (strpos($sql, 'completed_count') !== false) {
            return [['party_id' => 101, 'completed_count' => 1]];
        }
        return [['id' => 501, 'party_id' => 101, 'title' => 'Deal A', 'stage' => 'open', 'closed_reason' => 'none', 'deal_value' => 10, 'currency' => 'USD', 'owner_user_id' => 7, 'updated_at' => '2026-01-01']];
    }
}

$GLOBALS['wpdb'] = new Authorization_Test_WPDB();
$GLOBALS['rest_scope'] = ['type' => 'assigned', 'ids' => [101]];

function add_action() {}
function register_rest_route() {}
function is_user_logged_in() { return true; }
function current_user_can() { return true; }
function get_current_user_id() { return 7; }
function peracrm_get_client_access_scope() { return $GLOBALS['rest_scope']; }
function peracrm_with_target_blog(callable $callback) { return $callback(); }
function peracrm_table($suffix) { return 'wp_' . $suffix; }
function get_post($id) { return (int) $id === 101 ? new WP_Post() : null; }

require dirname(__DIR__) . '/inc/rest.php';

function rest_expect($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$before = count($GLOBALS['wpdb']->queries);
rest_expect([0, []], peracrm_rest_get_client_ids_by_type('clients', 20, 0, ['type' => 'empty', 'ids' => []]), 'empty scope returns zero client records and total');
rest_expect($before, count($GLOBALS['wpdb']->queries), 'empty scope does not execute an unrestricted query');

$clients = peracrm_rest_get_clients(new WP_REST_Request(['page' => 1, 'per_page' => 20]))->get_data();
rest_expect(1, $clients['total'], 'client collection total is scoped');
rest_expect([101], array_column($clients['items'], 'id'), 'client collection excludes Client B');

$leads = peracrm_rest_get_leads(new WP_REST_Request(['page' => 1, 'per_page' => 20]))->get_data();
rest_expect(1, $leads['total'], 'lead collection total is scoped');
rest_expect([101], array_column($leads['items'], 'id'), 'lead collection excludes Client B');

$deals = peracrm_rest_get_deals(new WP_REST_Request(['page' => 1, 'per_page' => 20]))->get_data();
rest_expect(1, $deals['total'], 'deal collection total is scoped');
rest_expect([101], array_column($deals['items'], 'party_id'), 'deal collection excludes deals associated with Client B');

foreach ($GLOBALS['wpdb']->queries as $query) {
    rest_expect(false, strpos($query, '102') !== false, 'generated scoped query does not include Client B');
}

echo "PeraCRM REST authorization behavior tests passed\n";
