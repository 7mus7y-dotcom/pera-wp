<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'peracrm_frontend_bridge_include' ) || defined( 'PERACRM_BOOTSTRAPPED' ) ) {
	// PeraCRM plugin owns CRM helper loading.
	return;
}

/*
 * Legacy fallback removed for multisite migration:
 * do not load theme CRM helpers when plugin is not bootstrapped,
 * otherwise CRM code continues to run even after plugin deactivation.
 */
