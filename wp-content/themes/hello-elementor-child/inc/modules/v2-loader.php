<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * -------------------------------------------------
 * V2 Search / Index System (isolated, non-breaking)
 * -------------------------------------------------
 */
/**
 * TEMP: Homepage test template assets
 * Safe to delete after testing
 */
require_once get_stylesheet_directory() . '/inc/home-page-test-assets.php';

require_once get_stylesheet_directory() . '/inc/v2-units-index.php';
require_once get_stylesheet_directory() . '/inc/ajax-property-archive.php';

if ( is_admin() ) {
  require_once get_stylesheet_directory() . '/inc/filter-for-admin-panel.php';
}
