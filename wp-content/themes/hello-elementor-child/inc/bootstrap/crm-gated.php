<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'peracrm_frontend_bridge_include' ) ) {
	// MU plugin owns CRM helper loading; keep theme side-effect free when bridge exists.
	return;
}

$crm_data = get_stylesheet_directory() . '/inc/crm-data.php';
if ( file_exists( $crm_data ) ) {
	require_once $crm_data;
}

$crm_client_view = get_stylesheet_directory() . '/inc/crm-client-view.php';
if ( file_exists( $crm_client_view ) ) {
	require_once $crm_client_view;
}
