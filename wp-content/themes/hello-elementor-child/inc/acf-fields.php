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

		acf_add_local_field_group(
			array(
				'key'    => 'group_district_archive_content',
				'title'  => 'District Archive Content',
				'fields' => array(
					array(
						'key'           => 'field_district_archive_subtitle',
						'label'         => 'Archive Subtitle',
						'name'          => 'district_archive_subtitle',
						'type'          => 'textarea',
						'rows'          => 2,
						'instructions'  => 'Short plain-text subtitle shown under the H1 on district archive pages.',
					),
					array(
						'key'          => 'field_district_archive_body',
						'label'        => 'Archive Body Content',
						'name'         => 'district_archive_body',
						'type'         => 'wysiwyg',
						'tabs'         => 'all',
						'toolbar'      => 'basic',
						'media_upload' => 0,
						'instructions' => 'Commercial archive content shown below property listings. HTML allowed.',
					),
					array(
						'key'          => 'field_district_regional_guide_url',
						'label'        => 'Regional Guide URL',
						'name'         => 'district_regional_guide_url',
						'type'         => 'url',
						'instructions' => 'Optional link to the full regional guide article.',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'taxonomy',
							'operator' => '==',
							'value'    => 'district',
						),
					),
				),
			)
		);


		acf_add_local_field_group(
			array(
				'key'        => 'group_property_seo_editorial_content',
				'title'      => 'Property SEO & Editorial Content',
				'fields'     => array(
					array(
						'key'       => 'field_property_editorial_tab_overview',
						'label'     => 'Editorial Overview',
						'name'      => '',
						'type'      => 'tab',
						'placement' => 'top',
						'endpoint'  => 0,
					),
					array(
						'key'          => 'field_property_editorial_intro',
						'label'        => 'Why This Property',
						'name'         => 'property_editorial_intro',
						'type'         => 'textarea',
						'instructions' => 'Short editorial summary displayed near the top of the property page. Use 2-4 sentences summarising the property\'s key strengths, location advantages and buyer appeal.',
						'rows'         => 4,
						'new_lines'    => '',
					),
					array(
						'key'          => 'field_property_highlights_text',
						'label'        => 'Property Highlights',
						'name'         => 'property_highlights_text',
						'type'         => 'textarea',
						'instructions' => 'Add one highlight per line. These will display as a checklist in the Why This Property section.',
						'rows'         => 6,
						'new_lines'    => '',
					),
					array(
						'key'       => 'field_property_editorial_tab_area_investment',
						'label'     => 'Area & Investment',
						'name'      => '',
						'type'      => 'tab',
						'placement' => 'top',
						'endpoint'  => 0,
					),
					array(
						'key'          => 'field_property_district_analysis',
						'label'        => 'District Analysis',
						'name'         => 'property_district_analysis',
						'type'         => 'wysiwyg',
						'instructions' => 'Explain why this district/neighbourhood suits this specific property. Avoid generic district guide copy.',
						'tabs'         => 'all',
						'toolbar'      => 'basic',
						'media_upload' => 0,
					),
					array(
						'key'          => 'field_property_investment_potential',
						'label'        => 'Investment & Rental Potential',
						'name'         => 'property_investment_potential',
						'type'         => 'wysiwyg',
						'instructions' => 'Discuss rental demand, tenant profile, resale appeal, scarcity, infrastructure, and long-term value.',
						'tabs'         => 'all',
						'toolbar'      => 'basic',
						'media_upload' => 0,
					),
					array(
						'key'          => 'field_estimated_rental_yield',
						'label'        => 'Estimated Rental Yield',
						'name'         => 'estimated_rental_yield',
						'type'         => 'text',
						'instructions' => 'Optional display value only, for example: 4.5% - 5.5%. Do not use for guaranteed returns.',
					),
					array(
						'key'       => 'field_property_editorial_tab_buyer_profile',
						'label'     => 'Buyer Profile',
						'name'      => '',
						'type'      => 'tab',
						'placement' => 'top',
						'endpoint'  => 0,
					),
					array(
						'key'          => 'field_property_buyer_suitability',
						'label'        => 'Buyer Suitability',
						'name'         => 'property_buyer_suitability',
						'type'         => 'wysiwyg',
						'instructions' => 'Explain who this property is suitable for: investors, families, lifestyle buyers, citizenship buyers, professionals, etc.',
						'tabs'         => 'all',
						'toolbar'      => 'basic',
						'media_upload' => 0,
					),
					array(
						'key'           => 'field_target_buyer_type',
						'label'         => 'Target Buyer Type',
						'name'          => 'target_buyer_type',
						'type'          => 'checkbox',
						'choices'       => array(
							'Investor'           => 'Investor',
							'Family'             => 'Family',
							'Luxury Buyer'       => 'Luxury Buyer',
							'Citizenship Buyer'  => 'Citizenship Buyer',
							'Holiday Home Buyer' => 'Holiday Home Buyer',
							'Second Home Buyer'  => 'Second Home Buyer',
							'Retiree'            => 'Retiree',
							'Professional'       => 'Professional',
						),
						'return_format' => 'value',
						'layout'        => 'vertical',
					),
					array(
						'key'           => 'field_property_key_advantages',
						'label'         => 'Key Advantages',
						'name'          => 'property_key_advantages',
						'type'          => 'checkbox',
						'choices'       => array(
							'Sea View'             => 'Sea View',
							'Bosphorus View'       => 'Bosphorus View',
							'City Centre'          => 'City Centre',
							'Citizenship Eligible' => 'Citizenship Eligible',
							'Metro Access'         => 'Metro Access',
							'Luxury Residence'     => 'Luxury Residence',
							'Hotel Residence'      => 'Hotel Residence',
							'Key Ready'            => 'Key Ready',
							'Payment Plan'         => 'Payment Plan',
							'Family Concept'       => 'Family Concept',
							'Furnished'            => 'Furnished',
							'Restored Building'    => 'Restored Building',
						),
						'return_format' => 'value',
						'layout'        => 'vertical',
					),
					array(
						'key'       => 'field_property_editorial_tab_developer',
						'label'     => 'Developer',
						'name'      => '',
						'type'      => 'tab',
						'placement' => 'top',
						'endpoint'  => 0,
					),
					array(
						'key'          => 'field_property_developer_profile',
						'label'        => 'Developer / Construction Credibility',
						'name'         => 'property_developer_profile',
						'type'         => 'wysiwyg',
						'instructions' => 'For projects and new-build properties only. Leave blank for normal resale listings.',
						'tabs'         => 'all',
						'toolbar'      => 'basic',
						'media_upload' => 0,
					),
					array(
						'key'       => 'field_property_editorial_tab_faq',
						'label'     => 'FAQ',
						'name'      => '',
						'type'      => 'tab',
						'placement' => 'top',
						'endpoint'  => 0,
					),
					array(
						'key'          => 'field_property_faq_text',
						'label'        => 'Property FAQ',
						'name'         => 'property_faq_text',
						'type'         => 'textarea',
						'instructions' => 'Add one FAQ per line using this format: Question|Answer',
						'rows'         => 8,
						'new_lines'    => '',
					),
				),
				'location'   => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'property',
						),
					),
				),
				'menu_order' => 20,
			)
		);

	}
);
