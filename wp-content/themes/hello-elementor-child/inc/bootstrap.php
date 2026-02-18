<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Access control helpers shared by admin + front-end.
 */
require_once get_stylesheet_directory() . '/inc/access-control.php';

/**
 * Load taxonomy term meta (term excerpt + featured image).
 * Used by inc/seo-all.php for term meta descriptions + social images.
 */
require_once get_stylesheet_directory() . '/inc/taxonomy-meta.php';
/**
 * SEO helper functions used by templates.
 */
require_once get_stylesheet_directory() . '/inc/seo-helpers.php';

/**
 * Enforce district ancestors for property assignments.
 */
require_once get_stylesheet_directory() . '/inc/district-ancestors.php';
/**
 * Favourites (property)
 */
require_once get_stylesheet_directory() . '/inc/favourites.php';
require_once get_stylesheet_directory() . '/inc/property-pagination.php';
require_once get_stylesheet_directory() . '/inc/property-archive-query.php';
require_once get_stylesheet_directory() . '/inc/property-card-helpers.php';
require_once get_stylesheet_directory() . '/inc/client-portal.php';
require_once get_stylesheet_directory() . '/inc/crm-data.php';
require_once get_stylesheet_directory() . '/inc/crm-router.php';
require_once get_stylesheet_directory() . '/inc/crm-client-view.php';
require_once get_stylesheet_directory() . '/inc/disable-hello-parent-loads.php';
require_once get_stylesheet_directory() . '/inc/portfolio-token.php';
