<?php

if (!defined('ABSPATH')) {
    exit;
}

$so_portal_core_files = [
    SO_PORTAL_PATH . 'includes/config.php',
    SO_PORTAL_PATH . 'includes/capabilities.php',
    SO_PORTAL_PATH . 'includes/helpers/format.php',
    SO_PORTAL_PATH . 'includes/helpers/sanitize.php',
    SO_PORTAL_PATH . 'includes/services/AccessService.php',
    SO_PORTAL_PATH . 'includes/services/UnitLookupService.php',
    SO_PORTAL_PATH . 'includes/services/SvgPlanService.php',
    SO_PORTAL_PATH . 'includes/cpt/building.php',
    SO_PORTAL_PATH . 'includes/cpt/floor.php',
    SO_PORTAL_PATH . 'includes/cpt/unit.php',
    SO_PORTAL_PATH . 'includes/rest/routes.php',
    SO_PORTAL_PATH . 'includes/assets/enqueue.php',
    SO_PORTAL_PATH . 'includes/shortcodes/portal-shortcode.php',
    SO_PORTAL_PATH . 'includes/frontend/template-routing.php',
];

foreach ($so_portal_core_files as $so_portal_bootstrap_file) {
    if (file_exists($so_portal_bootstrap_file)) {
        require_once $so_portal_bootstrap_file;
    }
}

if (function_exists('acf_add_local_field_group') || function_exists('get_field')) {
    $so_portal_acf_fields_file = SO_PORTAL_PATH . 'includes/acf/fields.php';
    if (file_exists($so_portal_acf_fields_file)) {
        require_once $so_portal_acf_fields_file;
    }
}

if (is_admin()) {
    $so_portal_admin_files = [
        SO_PORTAL_PATH . 'includes/admin/menu.php',
        SO_PORTAL_PATH . 'includes/admin/viewer-page.php',
    ];

    foreach ($so_portal_admin_files as $so_portal_bootstrap_file) {
        if (file_exists($so_portal_bootstrap_file)) {
            require_once $so_portal_bootstrap_file;
        }
    }
}
