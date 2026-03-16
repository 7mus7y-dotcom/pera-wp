<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Deprecated theme enqueue path.
 *
 * CRM push runtime assets are MU-plugin owned on `/crm/*` routes.
 * Keep this module as a no-op for compatibility with existing includes.
 */
add_action( 'wp_enqueue_scripts', function () {
  return;
}, 45 );
