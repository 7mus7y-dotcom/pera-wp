<?php
/**
 * Plugin Name: PeraCRM MU Loader
 * Description: Loads the PeraCRM MU shim entrypoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('PERA_CRM_DISABLED') && PERA_CRM_DISABLED) {
    return;
}

require_once __DIR__ . '/peracrm.php';
