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
		'section_class'      => 'section pera-home-latest-offers',
		'aria_label'         => __( 'Latest opportunities in Istanbul', 'hello-elementor-child' ),
		'title'              => __( 'Curated Opportunities in Istanbul', 'hello-elementor-child' ),
		'description'        => __( 'Handpicked current offers from selected Istanbul projects.', 'hello-elementor-child' ),
		'slider_id'          => 'home-latest-offers-slider',
		'cards'              => $cards,
		'card_list_modifier' => 'home',
	)
);
