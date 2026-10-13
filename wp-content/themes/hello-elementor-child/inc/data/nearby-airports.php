<?php
/**
 * OurAirports public-domain airport reference points, verified 2026-10-13.
 * Source: davidmegginson/ourairports-data, airports.csv, commit
 * 132fc8c3471c96cfffe3ece8a89f9f252f670243. See docs/nearby-amenities.md.
 * These are airport reference points, not terminal entrances or routing endpoints.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
return array(
    'version' => '20261013',
    'airports' => array(
        array( 'id' => 'LTFM', 'point' => array( 41.274874, 28.732136 ), 'names' => array( 'name' => 'İstanbul Airport (IST)' ) ),
        array( 'id' => 'LTFJ', 'point' => array( 40.898602, 29.3092 ), 'names' => array( 'name' => 'Istanbul Sabiha Gökçen International Airport (SAW)' ) ),
    ),
);
