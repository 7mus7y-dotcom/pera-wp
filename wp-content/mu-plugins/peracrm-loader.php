<?php
/**
 * Plugin Name: PeraCRM MU Loader
 * Description: Loads the nested PeraCRM MU-plugin entrypoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/peracrm/peracrm.php';
