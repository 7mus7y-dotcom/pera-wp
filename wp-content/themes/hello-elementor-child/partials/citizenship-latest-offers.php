<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = is_array( $args ?? null ) ? $args : array();

$defaults = array(
	'aria_label'          => pera_ml_ui( 'Current Istanbul property offers for citizenship buyers', 'theme.partials.citizenship-latest-offers.current_istanbul_property_offers_for_citizenship_buyers' ),
	'eyebrow'             => pera_ml_ui( 'Citizenship property shortlist', 'theme.partials.citizenship-latest-offers.citizenship_property_shortlist' ),
	'heading'             => pera_ml_ui( 'Current Istanbul property offers for citizenship buyers', 'theme.partials.citizenship-latest-offers.current_istanbul_property_offers_for_citizenship_buyers' ),
	'intro'               => pera_ml_ui( 'Selected Istanbul property offers that may suit buyers applying for Turkish citizenship through real estate investment, reviewed for citizenship suitability, valuation logic, title deed status, location quality, and resale potential.', 'theme.partials.citizenship-latest-offers.selected_istanbul_property_offers_that_may_suit_buyers_applying_for_tu' ),
	'view_all_label'      => pera_ml_ui( 'View all citizenship properties', 'theme.partials.citizenship-latest-offers.view_all_citizenship_properties' ),
	'request_label'       => pera_ml_ui( 'Request a private shortlist', 'theme.partials.citizenship-latest-offers.request_a_private_shortlist' ),
	'previous_aria_label' => pera_ml_ui( 'Previous offers', 'theme.partials.citizenship-latest-offers.previous_offers' ),
	'next_aria_label'     => pera_ml_ui( 'Next offers', 'theme.partials.citizenship-latest-offers.next_offers' ),
);

$copy = wp_parse_args( $args, $defaults );

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
		'section_class'       => 'section section-soft pera-citizenship-latest-offers',
		'aria_label'          => $copy['aria_label'],
		'kicker'              => $copy['eyebrow'],
		'title'               => $copy['heading'],
		'description'         => $copy['intro'],
		'slider_id'           => 'citizenship-latest-offers-slider',
		'cards'               => $cards,
		'card_list_modifier'  => 'citizenship',
		'previous_aria_label' => $copy['previous_aria_label'],
		'next_aria_label'     => $copy['next_aria_label'],
		'primary_cta'         => array(
			'label' => $copy['view_all_label'],
			'url'   => home_url( '/turkish-citizenship-properties/?view=cards' ),
			'class' => 'btn btn--solid btn--blue',
		),
		'secondary_cta'       => array(
			'label' => $copy['request_label'],
			'url'   => '#citizenship-callback',
			'class' => 'btn btn--solid btn--green',
		),
	)
);
