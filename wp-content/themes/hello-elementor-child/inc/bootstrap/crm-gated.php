<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_crm_get_dashboard_data' ) ) {
	require_once get_stylesheet_directory() . '/inc/crm-data.php';
}

if ( ! function_exists( 'pera_crm_client_view_get_client_id' ) ) {
	require_once get_stylesheet_directory() . '/inc/crm-client-view.php';
}
