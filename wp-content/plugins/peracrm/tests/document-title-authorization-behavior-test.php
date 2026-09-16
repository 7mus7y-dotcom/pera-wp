<?php

define('ABSPATH', __DIR__ . '/');
define('PERACRM_TARGET_BLOG_ID', 2);

$GLOBALS['title_allowed'] = false;
$GLOBALS['title_blog'] = 1;
$GLOBALS['title_blog_stack'] = [];
$GLOBALS['title_reads'] = [];
$GLOBALS['title_posts'] = [
    1 => [106 => ['title' => 'Origin Collision Secret', 'type' => 'origin_type']],
    2 => [106 => ['title' => 'Target CRM Client', 'type' => 'target_type']],
];

function add_action() {}
function add_filter() {}
function is_admin() { return false; }
function pera_is_crm_route() { return true; }
function sanitize_key($value) { return (string) $value; }
function get_query_var($key, $default = '') {
    return $key === 'pera_crm_view' ? 'client' : ($key === 'pera_crm_client_id' ? 106 : $default);
}
function get_current_user_id() { return 7; }
function get_current_blog_id() { return $GLOBALS['title_blog']; }
function switch_to_blog($blog_id) { $GLOBALS['title_blog_stack'][] = $GLOBALS['title_blog']; $GLOBALS['title_blog'] = (int) $blog_id; }
function restore_current_blog() { $GLOBALS['title_blog'] = array_pop($GLOBALS['title_blog_stack']); }
function peracrm_with_target_blog(callable $callback) {
    $switched = get_current_blog_id() !== PERACRM_TARGET_BLOG_ID;
    if ($switched) switch_to_blog(PERACRM_TARGET_BLOG_ID);
    try { return $callback(); } finally { if ($switched) restore_current_blog(); }
}
function peracrm_user_can_access_client($user_id, $client_id) { return $GLOBALS['title_allowed']; }
function get_the_title($id) {
    $GLOBALS['title_reads'][] = [get_current_blog_id(), 'title'];
    return $GLOBALS['title_posts'][get_current_blog_id()][(int) $id]['title'];
}
function get_post_meta($id, $key) {
    $GLOBALS['title_reads'][] = [get_current_blog_id(), $key];
    return $key === '_peracrm_client_type' ? $GLOBALS['title_posts'][get_current_blog_id()][(int) $id]['type'] : '';
}
function wp_strip_all_tags($value) { return strip_tags($value); }

require dirname(__DIR__) . '/inc/frontend/routing.php';

function title_expect($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

title_expect('', pera_crm_get_client_detail_document_title(), 'denied direct client request receives generic document title');
title_expect([], $GLOBALS['title_reads'], 'denied title path reads neither blog protected client metadata');
title_expect(1, get_current_blog_id(), 'denied title path restores originating blog');

$GLOBALS['title_allowed'] = true;
$allowed_title = pera_crm_get_client_detail_document_title();
title_expect('Target CRM Client - Target Type', $allowed_title, 'authorized title uses target-blog client title and type');
title_expect(false, strpos($allowed_title, 'Origin Collision Secret') !== false, 'origin-blog colliding metadata is not used');
title_expect([[2, 'title'], [2, '_peracrm_client_type']], $GLOBALS['title_reads'], 'all authorized metadata reads occur on target blog');
title_expect(1, get_current_blog_id(), 'authorized title path restores originating blog');

echo "PeraCRM document title authorization behavior tests passed\n";
