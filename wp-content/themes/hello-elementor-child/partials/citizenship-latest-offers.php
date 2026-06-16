<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards = function_exists( 'pera_latest_offers_collect_homepage_cards' )
	? pera_latest_offers_collect_homepage_cards( 6 )
	: array();

if ( empty( $cards ) ) {
	return;
}

get_template_part(
	'partials/latest-offers-section',
	null,
	array(
		'section_class'      => 'section section-soft pera-citizenship-latest-offers',
		'aria_label'         => __( 'Current Istanbul property offers for citizenship buyers', 'hello-elementor-child' ),
		'kicker'             => __( 'Citizenship property shortlist', 'hello-elementor-child' ),
		'title'              => __( 'Current Istanbul property offers for citizenship buyers', 'hello-elementor-child' ),
		'description'        => __( 'Selected Istanbul property offers that may suit buyers applying for Turkish citizenship through real estate investment, reviewed for citizenship suitability, valuation logic, title deed status, location quality, and resale potential.', 'hello-elementor-child' ),
		'slider_id'          => 'citizenship-latest-offers-slider',
		'cards'              => $cards,
		'card_list_modifier' => 'citizenship',
		'primary_cta'        => array(
			'label' => __( 'View all citizenship properties', 'hello-elementor-child' ),
			'url'   => home_url( '/turkish-citizenship-properties/?view=cards' ),
			'class' => 'btn btn--solid btn--blue',
		),
		'secondary_cta'      => array(
			'label' => __( 'Request a private shortlist', 'hello-elementor-child' ),
			'url'   => '#citizenship-callback',
			'class' => 'btn btn--solid btn--green',
		),
	)
);
