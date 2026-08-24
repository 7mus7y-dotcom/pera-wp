<?php
/**
 * Template Part: Featured Villa (Home)
 * Location: /parts/featured-villa.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_template_part('parts/home-featured-property', null, array(
  'property_id' => 21286,
  'kicker'      => pera_ml_ui( 'Featured Villa', 'theme.home_featured_property.villa_kicker' ),
  'guide_url'   => 'https://www.peraproperty.com/riva-istanbuls-untamed-north-where-forest-meets-the-sea_55334/',
  'guide_label' => pera_ml_ui( 'Read the Riva area guide', 'theme.home_featured_property.riva_guide' ),
));
