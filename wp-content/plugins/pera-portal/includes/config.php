<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERA_PORTAL_SHORTCODE_TAG')) {
    define('PERA_PORTAL_SHORTCODE_TAG', 'pera_portal');
}

if (!defined('PERA_PORTAL_REST_NAMESPACE')) {
    define('PERA_PORTAL_REST_NAMESPACE', 'pera-portal/v1');
}

if (!defined('PERA_PORTAL_ACCESS_MODE')) {
    define('PERA_PORTAL_ACCESS_MODE', 'reuse_crm');
}

if (!defined('PERA_PORTAL_ACCESS_CAP')) {
    define('PERA_PORTAL_ACCESS_CAP', 'access_pera_portal');
}

if (!defined('PERA_PORTAL_DIAG_HEADERS')) {
    define('PERA_PORTAL_DIAG_HEADERS', false);
}
