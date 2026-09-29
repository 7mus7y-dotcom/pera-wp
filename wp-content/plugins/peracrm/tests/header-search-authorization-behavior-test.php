<?php

define('ABSPATH', __DIR__ . '/');

class Header_Search_Test_WPDB {
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $last_query = '';
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($sql, $params) {
        foreach ($params as $value) {
            $replacement = is_int($value) ? (string) $value : "'" . addslashes((string) $value) . "'";
            $sql = preg_replace('/%[dsf]/', $replacement, $sql, 1);
        }
        return $sql;
    }
    public function get_col($sql) {
        $this->last_query = $sql;
        // Model scope enforcement, canonical database ordering and SQL LIMIT.
        $ids = strpos($sql, 'p.ID IN (101,102,103)') !== false ? [101, 102, 103] : [101, 102, 103, 106];
        if (preg_match('/LIMIT ([0-9]+)/', $sql, $matches)) {
            $ids = array_slice($ids, 0, (int) $matches[1]);
        }
        return $ids;
    }
}

$GLOBALS['wpdb'] = new Header_Search_Test_WPDB();

function add_action() {}
function is_user_logged_in() { return true; }
function get_current_user_id() { return 7; }
function peracrm_get_effective_crm_user_id() { return 7; }
function peracrm_is_impersonating_crm_user() { return false; }
function peracrm_get_client_access_scope() { return ['type' => 'assigned', 'ids' => [101, 102, 103]]; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function get_the_title($id) { return 'Client ' . (int) $id; }
function get_post_meta($id, $key) {
    $values = [101 => ['_peracrm_email' => 'a@example.test', '_peracrm_phone' => '+1001']];
    return $values[(int) $id][$key] ?? '';
}
function home_url($path) { return 'https://example.test' . $path; }
function esc_url_raw($url) { return $url; }
function __($value) { return $value; }

require dirname(__DIR__) . '/inc/services/header_search_service.php';

function search_expect($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$results = peracrm_header_search_results('Client', 2);
search_expect([101, 102], array_column($results, 'id'), 'employee preview returns no more than its requested limit');
search_expect(true, false !== strpos($GLOBALS['wpdb']->last_query, 'LIMIT 2'), 'preview limit is applied in prepared SQL');
search_expect(false, strpos($GLOBALS['wpdb']->last_query, '106') !== false, 'header search SQL does not include Client B');


$ids = peracrm_header_search_matching_ids('Client');
search_expect([101, 102, 103], $ids, 'full-page search uses the same employee scope without the preview cap');
search_expect(false, strpos($GLOBALS['wpdb']->last_query, 'LIMIT') !== false, 'full-page matcher is not capped at the dropdown limit');
search_expect(array_slice($ids, 0, 2), array_column($results, 'id'), 'preview records are the first records in full-page order');
search_expect(true, strpos($GLOBALS['wpdb']->last_query, "'_peracrm_email'") !== false, 'matcher searches canonical email metadata');
search_expect(true, strpos($GLOBALS['wpdb']->last_query, "'_peracrm_phone'") !== false, 'matcher searches canonical phone metadata');
search_expect([], peracrm_header_search_matching_ids(' '), 'blank terms cannot produce an unbounded result set');
search_expect([], peracrm_header_search_matching_ids('x'), 'terms shorter than the live-search minimum do not query records');

echo "PeraCRM header search authorization behavior tests passed\n";
