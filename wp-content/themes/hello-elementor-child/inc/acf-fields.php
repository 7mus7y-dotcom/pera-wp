<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'acf/init',
	static function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'    => 'group_homepage_faq',
				'title'  => 'Homepage FAQ',
				'fields' => array(
					array(
						'key'   => 'field_homepage_hero_subtext',
						'label' => 'Hero subtext',
						'name'  => 'homepage_hero_subtext',
						'type'  => 'textarea',
					),
					array(
						'key'   => 'field_homepage_listing_intro',
						'label' => 'First paragraph before listings',
						'name'  => 'homepage_listing_intro',
						'type'  => 'wysiwyg',
					),
					array(
						'key'   => 'field_homepage_bottom_seo_text',
						'label' => 'Second paragraph towards the end',
						'name'  => 'homepage_bottom_seo_text',
						'type'  => 'wysiwyg',
					),
					array(
						'key'          => 'field_homepage_faq',
						'label'        => 'FAQ',
						'name'         => 'faq',
						'type'         => 'repeater',
						'layout'       => 'row',
						'button_label' => 'Add FAQ',
						'sub_fields'   => array(
							array(
								'key'   => 'field_homepage_faq_question',
								'label' => 'Question',
								'name'  => 'question',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_homepage_faq_answer',
								'label' => 'Answer',
								'name'  => 'answer',
								'type'  => 'textarea',
							),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'page_type',
							'operator' => '==',
							'value'    => 'front_page',
						),
					),
				),
			)
		);
	}
);
