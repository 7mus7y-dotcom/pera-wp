<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'PERA_CRM_DEBUG_AJAX' ) ) {
	define( 'PERA_CRM_DEBUG_AJAX', false );
}

require_once get_stylesheet_directory() . '/inc/bootstrap.php';
require_once get_stylesheet_directory() . '/inc/theme-helpers.php';
require_once get_stylesheet_directory() . '/inc/theme-modules.php';
