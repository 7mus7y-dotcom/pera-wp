<?php
/**
 * Template Part: Featured Apartment (Home)
 * Location: /parts/featured-apartment.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_template_part('parts/home-featured-property', null, array(
  'property_id' => 46745,
  'kicker'      => pera_ml_ui( 'Featured Apartment', 'theme.home_featured_property.apartment_kicker' ),
));
