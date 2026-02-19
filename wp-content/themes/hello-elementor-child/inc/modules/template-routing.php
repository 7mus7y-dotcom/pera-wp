<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =======================================================
   TEMPLATE ROUTING
   ======================================================= */

add_filter( 'template_include', 'pera_force_property_archive_template', 20 );
