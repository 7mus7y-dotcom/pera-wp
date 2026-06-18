<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = is_array( $args ?? null ) ? $args : array();

$defaults = array(
	'aria_label'          => __( 'Current Istanbul property offers for citizenship buyers', 'hello-elementor-child' ),
	'eyebrow'             => __( 'Citizenship property shortlist', 'hello-elementor-child' ),
	'heading'             => __( 'Current Istanbul property offers for citizenship buyers', 'hello-elementor-child' ),
	'intro'               => __( 'Selected Istanbul property offers that may suit buyers applying for Turkish citizenship through real estate investment, reviewed for citizenship suitability, valuation logic, title deed status, location quality, and resale potential.', 'hello-elementor-child' ),
	'view_all_label'      => __( 'View all citizenship properties', 'hello-elementor-child' ),
	'request_label'       => __( 'Request a private shortlist', 'hello-elementor-child' ),
	'previous_aria_label' => __( 'Previous offers', 'hello-elementor-child' ),
	'next_aria_label'     => __( 'Next offers', 'hello-elementor-child' ),
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
