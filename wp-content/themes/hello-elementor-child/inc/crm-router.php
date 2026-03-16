<?php
/**
 * Compatibility shim: CRM router moved to MU plugin (PeraCRM).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pera_crm_router_hooks_registered')) {
    // MU plugin is unavailable: keep this file side-effect free to avoid duplicate routers.
    return;
}

// Plugin owns CRM router hooks now.
