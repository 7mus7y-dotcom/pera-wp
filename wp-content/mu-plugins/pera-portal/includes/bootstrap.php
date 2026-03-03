<?php

if (!defined('ABSPATH')) {
    exit;
}

$pera_portal_bootstrap_files = [
    PERA_PORTAL_PATH . '/includes/config.php',
    PERA_PORTAL_PATH . '/includes/capabilities.php',
    PERA_PORTAL_PATH . '/includes/admin/menu.php',
    PERA_PORTAL_PATH . '/includes/admin/viewer-page.php',
    PERA_PORTAL_PATH . '/includes/helpers/format.php',
    PERA_PORTAL_PATH . '/includes/helpers/sanitize.php',
    PERA_PORTAL_PATH . '/includes/services/AccessService.php',
    PERA_PORTAL_PATH . '/includes/services/UnitLookupService.php',
    PERA_PORTAL_PATH . '/includes/services/SvgPlanService.php',
    PERA_PORTAL_PATH . '/includes/acf/fields.php',
    PERA_PORTAL_PATH . '/includes/cpt/building.php',
    PERA_PORTAL_PATH . '/includes/cpt/floor.php',
    PERA_PORTAL_PATH . '/includes/cpt/unit.php',
    PERA_PORTAL_PATH . '/includes/rest/routes.php',
    PERA_PORTAL_PATH . '/includes/assets/enqueue.php',
    PERA_PORTAL_PATH . '/includes/shortcodes/portal-shortcode.php',
    PERA_PORTAL_PATH . '/includes/frontend/template-routing.php',
    PERA_PORTAL_PATH . '/includes/routing/portal-pages.php',
];

foreach ($pera_portal_bootstrap_files as $pera_portal_bootstrap_file) {
    if (file_exists($pera_portal_bootstrap_file)) {
        require_once $pera_portal_bootstrap_file;
    }
}
