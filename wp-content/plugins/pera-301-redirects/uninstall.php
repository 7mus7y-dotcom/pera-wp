<?php
/**
 * Uninstall handler for Pera 301 Redirects.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!defined('PERA_REDIRECTS_DELETE_DATA') || PERA_REDIRECTS_DELETE_DATA !== true) {
    return;
}

global $wpdb;
$table_name = $wpdb->prefix . 'pera_301_redirects';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");
