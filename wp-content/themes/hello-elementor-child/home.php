<?php
/**
 * Home (posts index) template.
 *
 * Keep standard blog index rendering aligned with archive.php so
 * category/tag/date and posts index share one SEO/content pattern.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require get_stylesheet_directory() . '/archive.php';
