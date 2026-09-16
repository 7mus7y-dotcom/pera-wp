<?php

define('ABSPATH', __DIR__ . '/');

$GLOBALS['title_allowed'] = false;
$GLOBALS['title_reads'] = [];

function add_action() {}
function add_filter() {}
function is_admin() { return false; }
function pera_is_crm_route() { return true; }
function sanitize_key($value) { return (string) $value; }
function get_query_var($key, $default = '') {
    return $key === 'pera_crm_view' ? 'client' : ($key === 'pera_crm_client_id' ? 106 : $default);
}
function get_current_user_id() { return 7; }
function peracrm_user_can_access_client($user_id, $client_id) { return $GLOBALS['title_allowed']; }
function get_the_title($id) { $GLOBALS['title_reads'][] = 'title'; return 'Client B Secret Name'; }
function get_post_meta($id, $key) { $GLOBALS['title_reads'][] = $key; return 'secret_type'; }
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
title_expect([], $GLOBALS['title_reads'], 'denied title path reads neither Client B name nor type metadata');

$GLOBALS['title_allowed'] = true;
$allowed_title = pera_crm_get_client_detail_document_title();
title_expect(true, strpos($allowed_title, 'Client B Secret Name') !== false, 'authorized title path can read client metadata');

echo "PeraCRM document title authorization behavior tests passed\n";
