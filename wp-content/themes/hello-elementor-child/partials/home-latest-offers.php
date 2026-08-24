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
		'aria_label'         => pera_ml_ui( 'Latest opportunities in Istanbul', 'theme.partials.home-latest-offers.latest_opportunities_in_istanbul' ),
		'title'              => pera_ml_ui( 'Curated Opportunities in Istanbul', 'theme.partials.home-latest-offers.curated_opportunities_in_istanbul' ),
		'description'        => pera_ml_ui( 'Handpicked current offers from selected Istanbul projects.', 'theme.partials.home-latest-offers.handpicked_current_offers_from_selected_istanbul_projects' ),
		'slider_id'          => 'home-latest-offers-slider',
		'cards'              => $cards,
		'card_list_modifier' => 'home',
	)
);
