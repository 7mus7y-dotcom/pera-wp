<?php

if (!defined('ABSPATH')) {
    exit;
}

$pera_portal_bootstrap_files = [
    PERA_PORTAL_PATH . '/includes/cache/nocache.php',
    PERA_PORTAL_PATH . '/includes/config.php',
    PERA_PORTAL_PATH . '/includes/capabilities.php',
    PERA_PORTAL_PATH . '/includes/admin/menu.php',
    PERA_PORTAL_PATH . '/includes/admin/viewer-page.php',
    PERA_PORTAL_PATH . '/includes/admin/diagnostics-page.php',
    PERA_PORTAL_PATH . '/includes/helpers/format.php',
    PERA_PORTAL_PATH . '/includes/helpers/sanitize.php',
    PERA_PORTAL_PATH . '/includes/services/AccessService.php',
    PERA_PORTAL_PATH . '/includes/services/UnitLookupService.php',
    PERA_PORTAL_PATH . '/includes/services/SvgPlanService.php',
    PERA_PORTAL_PATH . '/includes/services/DiagnosticsSvgParser.php',
    PERA_PORTAL_PATH . '/includes/services/DiagnosticsService.php',
    PERA_PORTAL_PATH . '/includes/acf/fields.php',
    PERA_PORTAL_PATH . '/includes/cpt/building.php',
    PERA_PORTAL_PATH . '/includes/cpt/floor.php',
    PERA_PORTAL_PATH . '/includes/cpt/unit.php',
    PERA_PORTAL_PATH . '/includes/cpt/quote.php',
    PERA_PORTAL_PATH . '/includes/quotes/repository.php',
    PERA_PORTAL_PATH . '/includes/quotes/token-service.php',
    PERA_PORTAL_PATH . '/includes/quotes/media-service.php',
    PERA_PORTAL_PATH . '/includes/quotes/snapshot-service.php',
    PERA_PORTAL_PATH . '/includes/rest/quote-routes.php',
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
