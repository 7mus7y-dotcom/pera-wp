# ACF field reference

> Canonical, repository-backed snapshot generated from the full ACF JSON export supplied on 3 November 2026.

## Purpose

This document records the Advanced Custom Fields configuration used by Pera Property. Use it to confirm field names, field keys, return formats, saved choice values, nested repeater structure and location rules before changing templates, WP-CLI population scripts, multilingual handling, search logic or CRM integrations.

- Source filename: `acf-export-2026-11-03.json`
- Export date: 3 November 2026
- Field groups: **9**
- Scope: complete supplied export, including Property, Bodrum Property, posts, taxonomies, services, team and V2 units
- Maintenance: replace this snapshot from a newer full ACF export when the field configuration changes materially

## Important Property status field

| Property | Value |
|---|---|
| Field group | `Projects` |
| Label | `Sold out` |
| Field name | `sold_out` |
| Field key | `field_5f8f36436f5b5` |
| Type | `checkbox` |
| Choice value | `Sold out` |
| Applies to | `post_type == property` |

Because this is an ACF checkbox, normal selected output is an array. A safe check is:

```php
$sold_out_values = function_exists( 'get_field' ) ? (array) get_field( 'sold_out', $property_id ) : array();
$is_sold_out     = in_array( 'Sold out', $sold_out_values, true );
```

## Field-group index

| # | Group | Group key | Top-level fields | Location rules |
|---:|---|---|---:|---|
| 1 | Blog category CF | `group_6a351ef32c184` | 10 | `taxonomy` == `category` OR `taxonomy` == `post_tag` |
| 2 | Bodrum | `group_69776cb411be1` | 42 | `post_type` == `bodrum-property` |
| 3 | District CPT | `group_67d007ad77e6b` | 6 | `taxonomy` == `district` OR `taxonomy` == `region` OR `taxonomy` == `property_tags` |
| 4 | Posts CF | `group_67289a5abbcb5` | 2 | `post_type` == `post` |
| 5 | Projects | `group_5ebd0dc6c6e31` | 36 | `post_type` == `property` |
| 6 | Seo helper fields | `group_69cb783872932` | 4 | `post_type` == `post` OR `post_type` == `property` OR `taxonomy` == `district` OR `taxonomy` == `region` OR `post_type` == `page` OR `taxonomy` == `property_type` OR `taxonomy` == `category` OR `taxonomy` == `property_tags` |
| 7 | Services | `group_66f14ebecefb5` | 4 | `post_type` == `service` |
| 8 | Team | `group_5eff3a01be5af` | 6 | `post_type` == `team` |
| 9 | Units (V2 Test) | `group_695ce531d3446` | 2 | `post_type` == `property` |

## 1. Blog category CF

- Group key: `group_6a351ef32c184`
- Location: `taxonomy` == `category` OR `taxonomy` == `post_tag`
- Menu order: `0`
- Position: `normal`
- Style: `default`
- Active: `true`

| Path | Label | Name | Field key | Type | Required | Instructions | Configuration |
|---|---|---|---|---|---:|---|---|
| 1 | Archive subtitle | `archive_subtitle` | `field_6a0add56a309b` | `text` | 0 | — | — |
| 2 | Archive H1 | `archive_h1` | `field_6a0add4aa309a` | `text` | 0 | — | — |
| 3 | Archive intro content | `archive_intro_content` | `field_6a0add63a309c` | `textarea` | 0 | — | — |
| 4 | Archive bottom content | `archive_bottom_content` | `field_6a0add71a309d` | `textarea` | 0 | — | — |
| 5 | Featured guide links | `featured_guide_links` | `field_6a0add8da309e` | `relationship` | 0 | — | `{"return_format":"object","post_type":["post"],"filters":["search","post_type","taxonomy"]}` |
| 6 | Featured links heading | `featured_links_heading` | `field_6a0add9ca309f` | `text` | 0 | — | — |
| 7 | Featured links intro | `featured_links_intro` | `field_6a0adda5a30a0` | `textarea` | 0 | — | — |
| 8 | Archive CTA heading | `archive_cta_heading` | `field_6a0addaca30a1` | `text` | 0 | — | — |
| 9 | Archive CTA text | `archive_cta_text` | `field_6a0addb3a30a2` | `textarea` | 0 | — | — |
| 10 | Archive WhatsApp message | `archive_whatsapp_message` | `field_6a0addbea30a3` | `textarea` | 0 | — | — |

## 2. Bodrum

- Group key: `group_69776cb411be1`
- Location: `post_type` == `bodrum-property`
- Menu order: `0`
- Position: `normal`
- Style: `default`
- Active: `true`

| Path | Label | Name | Field key | Type | Required | Instructions | Configuration |
|---|---|---|---|---|---:|---|---|
| 1 | 1. BP Titles | `` | `field_69776d9187dca` | `tab` | 0 | — | `{"placement":"top"}` |
| 2 | BP Display title | `bp_display_title` | `field_69776d2087dc6` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 3 | BP Tagline | `bp_tagline` | `field_69776d3887dc7` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 4 | BP Status Badge | `bp_status_badge` | `field_69776d4d87dc8` | `select` | 0 | — | `{"default_value":["for_sale"],"choices":{"for_sale":"for_sale","off_market":"off_market","sold":"sold","price_on_request":"price_on_request"},"return_format":"value","show_column_weight":1000}` |
| 5 | BP discretion note | `bp_discretion_note` | `field_69776d7787dc9` | `true_false` | 0 | — | `{"show_column_weight":1000}` |
| 6 | 2. Hero Media | `` | `field_69776dad87dcb` | `tab` | 0 | — | `{"placement":"top"}` |
| 7 | BP hero media type | `bp_hero_media_type` | `field_69776dbb87dcc` | `button_group` | 0 | — | `{"choices":{"image":"image","video":"video"},"return_format":"value","layout":"horizontal","show_column_weight":1000}` |
| 8 | BP hero image | `bp_hero_image` | `field_69776de887dcd` | `image` | 0 | — | `{"return_format":"id","library":"all","preview_size":"medium","show_column_weight":1000}` |
| 9 | BP hero video mp4 | `bp_hero_video_mp4` | `field_69776df987dce` | `file` | 0 | — | `{"return_format":"id","library":"all","show_column_weight":1000}` |
| 10 | BP hero video poster | `bp_hero_video_poster` | `field_69776e0c87dcf` | `image` | 0 | Fallback poster for video. | `{"return_format":"id","library":"all","preview_size":"medium","show_column_weight":1000}` |
| 11 | BP hero highlights | `bp_hero_highlights` | `field_69776e2087dd0` | `repeater` | 0 | — | `{"button_label":"Add Row","layout":"table","rows_per_page":20}` |
| 11.1 | BP hero highlights text repeater | `bp_hl_text` | `field_69776f1e87dd1` | `text` | 0 | — | `{"parent_repeater":"field_69776e2087dd0","show_column_weight":1000}` |
| 12 | 3. Key facts | `` | `field_69776f3e87dd2` | `tab` | 0 | — | `{"placement":"top"}` |
| 13 | BP Key facts | `bp_key_facts` | `field_69776f4a87dd3` | `group` | 0 | — | `{"layout":"block"}` |
| 13.1 | bp_location_line | `bp_location_line` | `field_69776f7187dd4` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 13.2 | bp_plot_area_sqm | `bp_plot_area_sqm` | `field_69776f7a87dd5` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 13.3 | bp_internal_area_sqm | `bp_internal_area_sqm` | `field_69776f8387dd6` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 13.4 | bp_total_units | `bp_total_units` | `field_69776f8987dd7` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 13.5 | bp_config_summary | `bp_config_summary` | `field_69776f9087dd8` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 14 | 4. Narrative Sections (Editorial Control) | `` | `field_69776f9d87dd9` | `tab` | 0 | — | `{"placement":"top"}` |
| 15 | BP Intro heading | `bp_intro_heading` | `field_69776fa587dda` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 16 | BP Intro text | `bp_intro_text` | `field_69776fb687ddb` | `wysiwyg` | 0 | — | `{"tabs":"all","toolbar":"basic"}` |
| 17 | BP Story blocks | `bp_story_blocks` | `field_69776fc587ddc` | `flexible_content` | 0 | — | `{"button_label":"Add Row"}` |
| 18 | 5. Gallery | `` | `field_6977701c87ddf` | `tab` | 0 | — | `{"placement":"top"}` |
| 19 | BP gallery | `bp_gallery` | `field_6977702787de0` | `gallery` | 0 | — | `{"return_format":"id","library":"all","preview_size":"medium","insert":"append","show_column_weight":1000}` |
| 20 | BP interior gallery | `bp_interior_gallery` | `field_6978ae2d24199` | `gallery` | 0 | — | `{"return_format":"id","library":"all","preview_size":"medium","insert":"append","show_column_weight":1000}` |
| 21 | BP gallery download enabled | `bp_gallery_download_enabled` | `field_6977703887de1` | `true_false` | 0 | — | `{"show_column_weight":1000}` |
| 22 | BP gallery download file | `bp_gallery_download_file` | `field_6977705c87de2` | `file` | 0 | (Optional: a zipped brochure/PDF pack; keep gated if you prefer.) | `{"return_format":"url","library":"all","show_column_weight":1000}` |
| 23 | 6. Features & Amenities | `` | `field_6977707a87de3` | `accordion` | 0 | — | — |
| 24 | BP features | `bp_features` | `field_6977708287de4` | `repeater` | 0 | — | `{"button_label":"Add Row","layout":"table","rows_per_page":20}` |
| 24.1 | BP feature title | `bp_feature_title` | `field_6977709287de5` | `text` | 0 | — | `{"parent_repeater":"field_6977708287de4","show_column_weight":1000}` |
| 24.2 | BP Feature Desc | `bp_feature_desc` | `field_697770a087de6` | `text` | 0 | — | `{"parent_repeater":"field_6977708287de4","show_column_weight":1000}` |
| 25 | BP amenities | `bp_amenities` | `field_697770b187de7` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 26 | 7. Dual-Use / Hospitality Capability | `` | `field_697770de87de9` | `tab` | 0 | — | `{"placement":"top"}` |
| 27 | BP Dual use heading | `bp_dual_use_heading` | `field_697770e587dea` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 28 | BP Dual use text | `bp_dual_use_text` | `field_697770f587deb` | `wysiwyg` | 0 | — | `{"tabs":"all","toolbar":"basic"}` |
| 29 | BP hospitality assets | `bp_hospitality_assets` | `field_6977710087dec` | `repeater` | 0 | — | `{"button_label":"Add Row","layout":"table","rows_per_page":20}` |
| 29.1 | BP HA item | `bp_ha_item` | `field_6977711a87ded` | `text` | 0 | — | `{"parent_repeater":"field_6977710087dec","show_column_weight":1000}` |
| 30 | BP Operations note | `bp_operations_note` | `field_6977712887dee` | `textarea` | 0 | — | `{"rows":4,"show_column_weight":1000}` |
| 31 | 8. Floorplans | `` | `field_6977714087def` | `tab` | 0 | — | `{"placement":"top"}` |
| 32 | BP floorplans | `bp_floorplans` | `field_6977714d87df0` | `repeater` | 0 | — | `{"button_label":"Add Row","layout":"table","rows_per_page":20}` |
| 32.1 | BP FP label | `bp_fp_label` | `field_6977716587df1` | `text` | 0 | — | `{"parent_repeater":"field_6977714d87df0","show_column_weight":1000}` |
| 32.2 | BP FP image | `bp_fp_image` | `field_6977717487df2` | `image` | 0 | — | `{"return_format":"id","library":"all","preview_size":"medium","parent_repeater":"field_6977714d87df0","show_column_weight":1000}` |
| 32.3 | BP FP note | `bp_fp_note` | `field_6977718387df3` | `text` | 0 | — | `{"parent_repeater":"field_6977714d87df0","show_column_weight":1000}` |
| 33 | 9. Location Section | `` | `field_6977719487df4` | `tab` | 0 | — | `{"placement":"top"}` |
| 34 | BP map mode | `bp_map_mode` | `field_6977719e87df5` | `select` | 0 | — | `{"choices":{"embed":"embed","image":"image"},"return_format":"value","show_column_weight":1000}` |
| 35 | BP Map embed | `bp_map_embed` | `field_697771b887df6` | `textarea` | 0 | — | `{"rows":4,"new_lines":"wpautop","show_column_weight":1000}` |
| 36 | BP Map image | `bp_map_image` | `field_697771c887df7` | `image` | 0 | — | `{"return_format":"id","library":"all","preview_size":"medium","show_column_weight":1000}` |
| 37 | BP Location highlights | `bp_location_highlights` | `field_697771d787df8` | `repeater` | 0 | — | `{"button_label":"Add Row","layout":"table","rows_per_page":20}` |
| 37.1 | BP LH text | `bp_lh_text` | `field_697771e687df9` | `text` | 0 | — | `{"parent_repeater":"field_697771d787df8","show_column_weight":1000}` |
| 38 | 10. Enquiry / Conversion Controls | `` | `field_697771f587dfa` | `tab` | 0 | — | `{"placement":"top"}` |
| 39 | BP primary cta label | `bp_primary_cta_label` | `field_6977720487dfb` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 40 | BP secondary cta label | `bp_secondary_cta_label` | `field_6977721387dfc` | `text` | 0 | — | `{"show_column_weight":1000}` |
| 41 | BP enquiry recipient | `bp_enquiry_recipient` | `field_6977722687dfd` | `email` | 0 | — | `{"show_column_weight":1000}` |
| 42 | BP Enquiry gating note | `bp_enquiry_gating_note` | `field_6977723f87dfe` | `text` | 0 | — | `{"show_column_weight":1000}` |

## 3. District CPT

- Group key: `group_67d007ad77e6b`
- Location: `taxonomy` == `district` OR `taxonomy` == `region` OR `taxonomy` == `property_tags`
- Menu order: `0`
- Position: `normal`
- Style: `default`
- Active: `true`

| Path | Label | Name | Field key | Type | Required | Instructions | Configuration |
|---|---|---|---|---|---:|---|---|
| 1 | District image | `district_image` | `field_67d007c00155a` | `image` | 0 | — | `{"return_format":"array","library":"all","preview_size":"full"}` |
| 2 | Related Districts | `related_districts` | `field_6996fcc872abf` | `taxonomy` | 0 | — | `{"return_format":"object","allow_null":1,"taxonomy":"district","field_type":"multi_select"}` |
| 3 | Related regions | `related_regions` | `field_6996fdbd72ac0` | `taxonomy` | 0 | — | `{"return_format":"object","allow_null":1,"taxonomy":"region","field_type":"multi_select"}` |
| 4 | Related tags | `related_tags` | `field_6996fdd372ac1` | `taxonomy` | 0 | — | `{"return_format":"object","allow_null":1,"taxonomy":"property_tags","field_type":"multi_select"}` |
| 5 | Regional guide | `regional_guide` | `field_69e23345f60ba` | `post_object` | 0 | — | `{"return_format":"id","taxonomy":["category:regional-guides"],"ui":1}` |
| 6 | Short label | `short_label` | `field_6a0aefe604a22` | `text` | 0 | — | — |

## 4. Posts CF

- Group key: `group_67289a5abbcb5`
- Location: `post_type` == `post`
- Menu order: `0`
- Position: `normal`
- Style: `default`
- Active: `true`

| Path | Label | Name | Field key | Type | Required | Instructions | Configuration |
|---|---|---|---|---|---:|---|---|
| 1 | Related district | `related_district` | `field_673c488121b12` | `taxonomy` | 0 | — | `{"return_format":"id","taxonomy":"district","field_type":"select"}` |
| 2 | Post subtitle | `post_subtitle` | `field_69ccf6201418e` | `text` | 0 | — | — |

## 5. Projects

- Group key: `group_5ebd0dc6c6e31`
- Location: `post_type` == `property`
- Menu order: `0`
- Position: `normal`
- Style: `default`
- Active: `true`

| Path | Label | Name | Field key | Type | Required | Instructions | Configuration |
|---|---|---|---|---|---:|---|---|
| 1 | Project Name | `project_name` | `field_5ebd0e1cffe03` | `text` | 0 | — | `{"show_column":1,"show_column_sortable":1,"show_column_weight":1000,"allow_quickedit":1}` |
| 2 | Basic Details | `` | `field_5ebd0dd99ab9a` | `tab` | 0 | — | `{"placement":"top"}` |
| 3 | Land size | `land_size` | `field_5ebd0ef694cd7` | `number` | 0 | — | `{"append":"sqm","show_column_weight":1000}` |
| 4 | Number of units | `number_of_units` | `field_5ebd0f1894cd8` | `number` | 0 | — | `{"show_column_weight":1000}` |
| 5 | Completion date | `completion_date` | `field_5ebd0f4294cda` | `date_picker` | 0 | — | `{"return_format":"F j, Y","display_format":"F j, Y","first_day":1,"show_column_weight":1000}` |
| 6 | Map | `map` | `field_5ebd0f8994cdd` | `google_map` | 0 | — | `{"height":300,"allow_in_bindings":1}` |
| 7 | Sold out | `sold_out` | `field_5f8f36436f5b5` | `checkbox` | 0 | — | `{"choices":{"Sold out":"Sold out"},"return_format":"value","layout":"vertical","allow_in_bindings":1,"custom_choice_button_text":"Add new choice"}` |
| 8 | Custom Text | `custom_text` | `field_5fd9ad2b0c44f` | `text` | 0 | — | `{"allow_in_bindings":1}` |
| 9 | Custom Text link | `custom_text_link` | `field_67176025202b5` | `url` | 0 | — | `{"allow_in_bindings":1}` |
| 10 | Texts | `` | `field_5ebd0f5a94cdb` | `tab` | 0 | — | `{"placement":"top"}` |
| 11 | Project summary heading | `project_summary_heading` | `field_670d0bff86d60` | `text` | 0 | — | `{"default_value":"Quick facts"}` |
| 12 | Project Summary | `project_summary` | `field_5ebd0f7094cdc` | `wysiwyg` | 0 | — | `{"tabs":"all","toolbar":"full","media_upload":1}` |
| 13 | Whats special heading | `whats_special_heading` | `field_66fe978d30e31` | `text` | 0 | — | `{"default_value":"What's special about this property?"}` |
| 14 | About this project | `about_this_project` | `field_5ebd0f9794cde` | `wysiwyg` | 0 | — | `{"tabs":"all","toolbar":"full","media_upload":1}` |
| 15 | Location info heading | `location_info_heading` | `field_670d0ba7764b6` | `text` | 0 | — | `{"default_value":"Location info"}` |
| 16 | Distances | `distances` | `field_5ebd102694ce5` | `wysiwyg` | 0 | — | `{"tabs":"all","toolbar":"full","media_upload":1}` |
| 17 | Facilities | `facilities` | `field_5ebd397220348` | `checkbox` | 0 | — | `{"choices":{"24 7 security":"24 7 security","5 star hotel":"5 star hotel","Art room":"Art room","Basketball courts":"Basketball courts","Bicycle Path":"Bicycle Path","Botanic park":"Botanic park","Café":"Café","Car rental":"Car rental","Central Satellite System":"Central Satellite System","Child play areas":"Child play areas","Child swimming pool":"Child swimming pool","Commercial space":"Commercial space","Concierge":"Concierge","Creche":"Creche","Elevator":"Elevator","Football courts":"Football courts","Forest View":"Forest View","Games Room":"Games Room","Generator":"Generator","Guest bedrooms":"Guest bedrooms","Guest Rooms":"Guest Rooms","Gym":"Gym","Helipad":"Helipad","Hobby room":"Hobby room","House Keeping":"House Keeping","Indoor Cinema":"Indoor Cinema","Indoor swimming pool":"Indoor swimming pool","Kids club":"Kids club","Table tennis":"Table tennis","Lake":"Lake","Lake View":"Lake View","Landscaped gardens":"Landscaped gardens","Lobby":"Lobby","Mall":"Mall","Meeting Area":"Meeting Area","Meeting rooms":"Meeting rooms","Metro station":"Metro station","Music room":"Music room","Open air cinema":"Open air cinema","Ornamental fountains":"Ornamental fountains","Outdoor exercise area":"Outdoor exercise area","Outdoor Parking":"Outdoor Parking","Outdoor swimming pool":"Outdoor swimming pool","Party room":"Party room","Playstation room":"Playstation room","Private garden":"Private garden","Promenade":"Promenade","Reception":"Reception","Recreation areas":"Recreation areas","Restaurant":"Restaurant","Rooftop restaurant":"Rooftop restaurant","Rooftop terrace":"Rooftop terrace","Sauna":"Sauna","Sea View":"Sea View","Skateboarding park":"Skateboarding park","Smart home system":"Smart home system","Spa":"Spa","Sports hall":"Sports hall","Squash":"Squash","Sunbathing terraces":"Sunbathing terraces","Tennis Court":"Tennis Court","Turkish baths":"Turkish baths","Underfloor heating":"Underfloor heating","Underground parking":"Underground parking","University":"University","Valet parking":"Valet parking","Vehicle Charger Station":"Vehicle Charger Station","Vitamin Bar":"Vitamin Bar","Volleyball courts":"Volleyball courts","Walking parkour":"Walking parkour","Water depot":"Water depot","Water Park":"Water Park","Water Sports":"Water Sports","Yacht Marina":"Yacht Marina"},"return_format":"value","layout":"horizontal","toggle":1,"custom_choice_button_text":"Add new choice"}` |
| 18 | Videos | `` | `field_6717605d70d07` | `tab` | 0 | — | `{"placement":"top"}` |
| 19 | YT heading | `yt_heading` | `field_673ee827442a1` | `text` | 0 | — | `{"default_value":"Check out this video!"}` |
| 20 | YT Video | `yt_video` | `field_5f21349ea2272` | `oembed` | 0 | — | — |
| 21 | Custom video heading | `custom_video_heading` | `field_67175bdc93495` | `text` | 0 | — | `{"default_value":"Apartment tour"}` |
| 22 | Custom video button | `custom_video_button` | `field_671760ac70d09` | `text` | 0 | This is the text that apears on the button at the top of the page | — |
| 23 | Custom video text | `custom_video_text` | `field_671760e270d0a` | `text` | 0 | TExt which will appear below the heading | — |
| 24 | Video (file) | `video_file` | `field_66ffc7da4d97b` | `file` | 0 | — | `{"return_format":"array","library":"all"}` |
| 25 | Galleries | `` | `field_5ebd0fa994cdf` | `tab` | 0 | — | `{"placement":"top"}` |
| 26 | Main image | `main_image` | `field_5ebd265523532` | `image` | 0 | — | `{"return_format":"array","library":"all","preview_size":"medium"}` |
| 27 | Main Gallery | `main_gallery` | `field_5ebd0fb294ce0` | `gallery` | 0 | — | `{"return_format":"array","library":"all","preview_size":"medium","insert":"append"}` |
| 28 | FP check box | `fp_check_box` | `field_66fea218a88d2` | `true_false` | 0 | — | — |
| 29 | Floor plans heading | `floor_plans_heading` | `field_66fea1e8a88d0` | `text` | 0 | — | `{"default_value":"Floor plans"}` |
| 30 | Floor plans custom text | `floor_plans_custom_text` | `field_66fea39cc067a` | `text` | 0 | — | `{"default_value":"These are some sample floor plans - there are many other types available."}` |
| 31 | Floor plans | `floor_plans` | `field_5ebd0fc194ce1` | `gallery` | 0 | — | `{"return_format":"array","library":"all","preview_size":"medium","insert":"append"}` |
| 32 | Link to regional guide | `` | `field_670d1a439bee5` | `tab` | 0 | — | `{"placement":"top"}` |
| 33 | Further reading heading | `further_reading_heading` | `field_6724d4c6c8388` | `text` | 0 | — | `{"default_value":"Further Reading"}` |
| 34 | Further reading text | `further_reading_text` | `field_6724d4f1c8389` | `text` | 0 | — | `{"default_value":"Read more about the region and related content."}` |
| 35 | Post heading | `post_heading` | `field_670d1a669bee6` | `relationship` | 0 | — | `{"return_format":"id","post_type":["post"],"filters":["search","post_type","taxonomy"],"elements":["featured_image"]}` |
| 36 | Price list KD | `price_list_kd` | `field_6789130683fa7` | `repeater` | 0 | — | `{"button_label":"Add Row","layout":"table","rows_per_page":20}` |
| 36.1 | Floor | `floor_kd` | `field_678a183a9ed55` | `text` | 0 | — | `{"parent_repeater":"field_6789130683fa7"}` |
| 36.2 | Type | `type_kd` | `field_678a15d96f8e5` | `text` | 0 | — | `{"parent_repeater":"field_6789130683fa7"}` |
| 36.3 | Net size sqm | `net_size_kd` | `field_678a15e36f8e6` | `number` | 0 | — | `{"parent_repeater":"field_6789130683fa7"}` |
| 36.4 | Gross size sqm | `gross_size_kd` | `field_678a16096f8e7` | `number` | 0 | — | `{"parent_repeater":"field_6789130683fa7"}` |
| 36.5 | View | `view_kd` | `field_678913ae22a82` | `text` | 0 | — | `{"parent_repeater":"field_6789130683fa7"}` |
| 36.6 | Price | `price_kd` | `field_678a161c6f8e8` | `number` | 0 | — | `{"parent_repeater":"field_6789130683fa7","prepend":"$"}` |

## 6. Seo helper fields

- Group key: `group_69cb783872932`
- Location: `post_type` == `post` OR `post_type` == `property` OR `taxonomy` == `district` OR `taxonomy` == `region` OR `post_type` == `page` OR `taxonomy` == `property_type` OR `taxonomy` == `category` OR `taxonomy` == `property_tags`
- Menu order: `0`
- Position: `normal`
- Style: `default`
- Active: `true`

| Path | Label | Name | Field key | Type | Required | Instructions | Configuration |
|---|---|---|---|---|---:|---|---|
| 1 | SEO title | `seo_title` | `field_69cb7838ccf28` | `text` | 0 | Google result title, around 50–60 characters | — |
| 2 | SEO Meta description | `seo_meta_description` | `field_69cb786accf29` | `textarea` | 0 | — | — |
| 3 | SEO Social Image | `seo_social_image` | `field_69cbaf8d8b168` | `image` | 0 | — | `{"return_format":"array","library":"all","preview_size":"medium"}` |
| 4 | SEO FAQ V2 | `seo_faq_v2` | `field_6a35018785dde` | `textarea` | 0 | — | — |

## 7. Services

- Group key: `group_66f14ebecefb5`
- Location: `post_type` == `service`
- Menu order: `0`
- Position: `normal`
- Style: `default`
- Active: `true`

| Path | Label | Name | Field key | Type | Required | Instructions | Configuration |
|---|---|---|---|---|---:|---|---|
| 1 | Heading | `heading` | `field_66f14ed26edba` | `text` | 0 | — | — |
| 2 | Description | `description` | `field_66f14ee06edbb` | `textarea` | 0 | — | — |
| 3 | Button text | `button_text` | `field_66f14ef31dc63` | `text` | 0 | — | — |
| 4 | Button link | `button_link` | `field_66f14f016605f` | `url` | 0 | — | — |

## 8. Team

- Group key: `group_5eff3a01be5af`
- Location: `post_type` == `team`
- Menu order: `0`
- Position: `normal`
- Style: `default`
- Active: `true`

| Path | Label | Name | Field key | Type | Required | Instructions | Configuration |
|---|---|---|---|---|---:|---|---|
| 1 | Name | `name` | `field_5eff3a058057b` | `text` | 0 | — | — |
| 2 | Position | `position` | `field_5eff3a0c8057c` | `text` | 0 | — | — |
| 3 | Description | `description` | `field_5eff3a138057d` | `text` | 0 | — | — |
| 4 | Photo | `photo` | `field_5eff3a1a8057e` | `image` | 0 | — | `{"return_format":"array","library":"all","preview_size":"medium"}` |
| 5 | Advisor? | `advisor` | `field_697b50c4e3d05` | `true_false` | 0 | — | `{"show_column_weight":1000}` |
| 6 | Number | `number` | `field_697b52b2e6abc` | `number` | 0 | — | `{"show_column_weight":1000}` |

## 9. Units (V2 Test)

- Group key: `group_695ce531d3446`
- Location: `post_type` == `property`
- Menu order: `0`
- Position: `normal`
- Style: `default`
- Active: `true`

| Path | Label | Name | Field key | Type | Required | Instructions | Configuration |
|---|---|---|---|---|---:|---|---|
| 1 | v2_units | `v2_units` | `field_695ce53e6547f` | `repeater` | 0 | — | `{"button_label":"Add Row","layout":"table","collapsed":"field_695ce6b9c6448","rows_per_page":20}` |
| 1.1 | v2 bedrooms | `v2_bedrooms` | `field_695ce56365480` | `number` | 0 | — | `{"parent_repeater":"field_695ce53e6547f","show_column_weight":1000}` |
| 1.2 | v2 gross size min | `v2_gross_size_min` | `field_695ce59565482` | `number` | 0 | — | `{"parent_repeater":"field_695ce53e6547f","show_column_weight":1000}` |
| 1.3 | v2 gross size max | `v2_gross_size_max` | `field_695f9830776c4` | `number` | 0 | — | `{"parent_repeater":"field_695ce53e6547f","show_column_weight":1000}` |
| 1.4 | v2 price usd min | `v2_price_usd_min` | `field_695ce5aa65483` | `number` | 0 | — | `{"parent_repeater":"field_695ce53e6547f","show_column_weight":1000}` |
| 1.5 | v2 price usd max | `v2_price_usd_max` | `field_695f983c776c5` | `number` | 0 | — | `{"parent_repeater":"field_695ce53e6547f","show_column_weight":1000}` |
| 1.6 | v2_index_key | `v2_index_key` | `field_695ce6b9c6448` | `text` | 0 | auto-generated do not edit | `{"parent_repeater":"field_695ce53e6547f","show_column_weight":1000}` |
| 2 | V2 Custom text | `v2_custom_text` | `field_6965feca39166` | `text` | 0 | — | `{"show_column_weight":1000}` |

## Complete source snapshot

The original export is retained below verbatim so no ACF setting is lost even if it is not surfaced in the summary tables.

```json
[
    {
        "key": "group_6a351ef32c184",
        "title": "Blog category CF",
        "fields": [
            {
                "key": "field_6a0add56a309b",
                "label": "Archive subtitle",
                "name": "archive_subtitle",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "placeholder": "",
                "prepend": "",
                "append": ""
            },
            {
                "key": "field_6a0add4aa309a",
                "label": "Archive H1",
                "name": "archive_h1",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "placeholder": "",
                "prepend": "",
                "append": ""
            },
            {
                "key": "field_6a0add63a309c",
                "label": "Archive intro content",
                "name": "archive_intro_content",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "rows": "",
                "placeholder": "",
                "new_lines": ""
            },
            {
                "key": "field_6a0add71a309d",
                "label": "Archive bottom content",
                "name": "archive_bottom_content",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "rows": "",
                "placeholder": "",
                "new_lines": ""
            },
            {
                "key": "field_6a0add8da309e",
                "label": "Featured guide links",
                "name": "featured_guide_links",
                "aria-label": "",
                "type": "relationship",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "post_type": [
                    "post"
                ],
                "post_status": "",
                "taxonomy": "",
                "filters": [
                    "search",
                    "post_type",
                    "taxonomy"
                ],
                "return_format": "object",
                "min": "",
                "max": "",
                "allow_in_bindings": 0,
                "elements": "",
                "bidirectional": 0,
                "bidirectional_target": []
            },
            {
                "key": "field_6a0add9ca309f",
                "label": "Featured links heading",
                "name": "featured_links_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "placeholder": "",
                "prepend": "",
                "append": ""
            },
            {
                "key": "field_6a0adda5a30a0",
                "label": "Featured links intro",
                "name": "featured_links_intro",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "rows": "",
                "placeholder": "",
                "new_lines": ""
            },
            {
                "key": "field_6a0addaca30a1",
                "label": "Archive CTA heading",
                "name": "archive_cta_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "placeholder": "",
                "prepend": "",
                "append": ""
            },
            {
                "key": "field_6a0addb3a30a2",
                "label": "Archive CTA text",
                "name": "archive_cta_text",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "rows": "",
                "placeholder": "",
                "new_lines": ""
            },
            {
                "key": "field_6a0addbea30a3",
                "label": "Archive WhatsApp message",
                "name": "archive_whatsapp_message",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "rows": "",
                "placeholder": "",
                "new_lines": ""
            }
        ],
        "location": [
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "category"
                }
            ],
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "post_tag"
                }
            ]
        ],
        "menu_order": 0,
        "position": "normal",
        "style": "default",
        "label_placement": "top",
        "instruction_placement": "label",
        "hide_on_screen": "",
        "active": true,
        "description": "",
        "show_in_rest": 0,
        "display_title": ""
    },
    {
        "key": "group_69776cb411be1",
        "title": "Bodrum",
        "fields": [
            {
                "key": "field_69776d9187dca",
                "label": "1. BP Titles",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_69776d2087dc6",
                "label": "BP Display title",
                "name": "bp_display_title",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_69776d3887dc7",
                "label": "BP Tagline",
                "name": "bp_tagline",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_69776d4d87dc8",
                "label": "BP Status Badge",
                "name": "bp_status_badge",
                "aria-label": "",
                "type": "select",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "choices": {
                    "for_sale": "for_sale",
                    "off_market": "off_market",
                    "sold": "sold",
                    "price_on_request": "price_on_request"
                },
                "default_value": [
                    "for_sale"
                ],
                "allow_null": 0,
                "multiple": 0,
                "ui": 0,
                "return_format": "value",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0,
                "show_column_filter": 0,
                "ajax": 0,
                "placeholder": "",
                "create_options": 0,
                "save_options": 0
            },
            {
                "key": "field_69776d7787dc9",
                "label": "BP discretion note",
                "name": "bp_discretion_note",
                "aria-label": "",
                "type": "true_false",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "message": "",
                "default_value": 0,
                "ui": 0,
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0,
                "show_column_filter": 0,
                "ui_on_text": "",
                "ui_off_text": ""
            },
            {
                "key": "field_69776dad87dcb",
                "label": "2. Hero Media",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_69776dbb87dcc",
                "label": "BP hero media type",
                "name": "bp_hero_media_type",
                "aria-label": "",
                "type": "button_group",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "choices": {
                    "image": "image",
                    "video": "video"
                },
                "allow_null": 0,
                "default_value": "",
                "layout": "horizontal",
                "return_format": "value",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0,
                "show_column_filter": 0
            },
            {
                "key": "field_69776de887dcd",
                "label": "BP hero image",
                "name": "bp_hero_image",
                "aria-label": "",
                "type": "image",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "id",
                "preview_size": "medium",
                "library": "all",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": "",
                "show_column": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_69776df987dce",
                "label": "BP hero video mp4",
                "name": "bp_hero_video_mp4",
                "aria-label": "",
                "type": "file",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "id",
                "library": "all",
                "min_size": "",
                "max_size": "",
                "mime_types": "",
                "show_column": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_69776e0c87dcf",
                "label": "BP hero video poster",
                "name": "bp_hero_video_poster",
                "aria-label": "",
                "type": "image",
                "instructions": "Fallback poster for video.",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "id",
                "preview_size": "medium",
                "library": "all",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": "",
                "show_column": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_69776e2087dd0",
                "label": "BP hero highlights",
                "name": "bp_hero_highlights",
                "aria-label": "",
                "type": "repeater",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "collapsed": "",
                "min": 0,
                "max": 0,
                "layout": "table",
                "button_label": "Add Row",
                "sub_fields": [
                    {
                        "key": "field_69776f1e87dd1",
                        "label": "BP hero highlights text repeater",
                        "name": "bp_hl_text",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_69776e2087dd0"
                    }
                ],
                "rows_per_page": 20
            },
            {
                "key": "field_69776f3e87dd2",
                "label": "3. Key facts",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_69776f4a87dd3",
                "label": "BP Key facts",
                "name": "bp_key_facts",
                "aria-label": "",
                "type": "group",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "layout": "block",
                "sub_fields": [
                    {
                        "key": "field_69776f7187dd4",
                        "label": "bp_location_line",
                        "name": "bp_location_line",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0
                    },
                    {
                        "key": "field_69776f7a87dd5",
                        "label": "bp_plot_area_sqm",
                        "name": "bp_plot_area_sqm",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0
                    },
                    {
                        "key": "field_69776f8387dd6",
                        "label": "bp_internal_area_sqm",
                        "name": "bp_internal_area_sqm",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0
                    },
                    {
                        "key": "field_69776f8987dd7",
                        "label": "bp_total_units",
                        "name": "bp_total_units",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0
                    },
                    {
                        "key": "field_69776f9087dd8",
                        "label": "bp_config_summary",
                        "name": "bp_config_summary",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0
                    }
                ]
            },
            {
                "key": "field_69776f9d87dd9",
                "label": "4. Narrative Sections (Editorial Control)",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_69776fa587dda",
                "label": "BP Intro heading",
                "name": "bp_intro_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_69776fb687ddb",
                "label": "BP Intro text",
                "name": "bp_intro_text",
                "aria-label": "",
                "type": "wysiwyg",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "tabs": "all",
                "toolbar": "basic",
                "media_upload": 0,
                "delay": 0
            },
            {
                "key": "field_69776fc587ddc",
                "label": "BP Story blocks",
                "name": "bp_story_blocks",
                "aria-label": "",
                "type": "flexible_content",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "layouts": {
                    "layout_69776fcee1ca6": {
                        "key": "layout_69776fcee1ca6",
                        "name": "bp_block_text",
                        "label": "bp_block_text",
                        "display": "block",
                        "sub_fields": [
                            {
                                "key": "field_69776fd987ddd",
                                "label": "bp_block_heading",
                                "name": "bp_block_heading",
                                "aria-label": "",
                                "type": "text",
                                "instructions": "",
                                "required": 0,
                                "conditional_logic": 0,
                                "wrapper": {
                                    "width": "",
                                    "class": "",
                                    "id": ""
                                },
                                "default_value": "",
                                "placeholder": "",
                                "prepend": "",
                                "append": "",
                                "maxlength": "",
                                "show_column": 0,
                                "show_column_sortable": 0,
                                "show_column_weight": 1000,
                                "allow_quickedit": 0,
                                "allow_bulkedit": 0
                            },
                            {
                                "key": "field_69776fe487dde",
                                "label": "bp_block_text",
                                "name": "bp_block_text",
                                "aria-label": "",
                                "type": "wysiwyg",
                                "instructions": "",
                                "required": 0,
                                "conditional_logic": 0,
                                "wrapper": {
                                    "width": "",
                                    "class": "",
                                    "id": ""
                                },
                                "default_value": "",
                                "tabs": "all",
                                "toolbar": "basic",
                                "media_upload": 0,
                                "delay": 0
                            }
                        ],
                        "min": "",
                        "max": ""
                    },
                    "layout_6977786584bca": {
                        "key": "layout_6977786584bca",
                        "name": "bp_block_image_text",
                        "label": "Image + Text",
                        "display": "block",
                        "sub_fields": [
                            {
                                "key": "field_6977787084bcb",
                                "label": "Block Image",
                                "name": "bp_block_image",
                                "aria-label": "",
                                "type": "image",
                                "instructions": "",
                                "required": 0,
                                "conditional_logic": 0,
                                "wrapper": {
                                    "width": "",
                                    "class": "",
                                    "id": ""
                                },
                                "return_format": "id",
                                "preview_size": "medium",
                                "library": "all",
                                "min_width": 50,
                                "min_height": "",
                                "min_size": "",
                                "max_width": "",
                                "max_height": "",
                                "max_size": "",
                                "mime_types": "",
                                "show_column": 0,
                                "show_column_weight": 1000,
                                "allow_quickedit": 0,
                                "allow_bulkedit": 0
                            },
                            {
                                "key": "field_6977788284bcc",
                                "label": "Block Heading",
                                "name": "bp_block_heading",
                                "aria-label": "",
                                "type": "text",
                                "instructions": "",
                                "required": 0,
                                "conditional_logic": 0,
                                "wrapper": {
                                    "width": "50",
                                    "class": "",
                                    "id": ""
                                },
                                "default_value": "",
                                "placeholder": "",
                                "prepend": "",
                                "append": "",
                                "maxlength": "",
                                "show_column": 0,
                                "show_column_sortable": 0,
                                "show_column_weight": 1000,
                                "allow_quickedit": 0,
                                "allow_bulkedit": 0
                            },
                            {
                                "key": "field_6977789984bcd",
                                "label": "Block Text",
                                "name": "bp_block_text",
                                "aria-label": "",
                                "type": "wysiwyg",
                                "instructions": "",
                                "required": 0,
                                "conditional_logic": 0,
                                "wrapper": {
                                    "width": "100",
                                    "class": "",
                                    "id": ""
                                },
                                "default_value": "",
                                "tabs": "all",
                                "toolbar": "basic",
                                "media_upload": 0,
                                "delay": 0
                            },
                            {
                                "key": "field_697778ab84bce",
                                "label": "Image Side",
                                "name": "bp_block_image_side",
                                "aria-label": "",
                                "type": "select",
                                "instructions": "",
                                "required": 0,
                                "conditional_logic": 0,
                                "wrapper": {
                                    "width": "50",
                                    "class": "",
                                    "id": ""
                                },
                                "choices": {
                                    "left": "Left",
                                    "right": "Right"
                                },
                                "default_value": [
                                    "right"
                                ],
                                "allow_null": 0,
                                "multiple": 0,
                                "ui": 0,
                                "return_format": "value",
                                "show_column": 0,
                                "show_column_sortable": 0,
                                "show_column_weight": 1000,
                                "allow_quickedit": 0,
                                "allow_bulkedit": 0,
                                "show_column_filter": 0,
                                "ajax": 0,
                                "placeholder": "",
                                "create_options": 0,
                                "save_options": 0
                            }
                        ],
                        "min": "",
                        "max": ""
                    },
                    "layout_697778ca84bcf": {
                        "key": "layout_697778ca84bcf",
                        "name": "bp_block_quote",
                        "label": "Quote",
                        "display": "block",
                        "sub_fields": [
                            {
                                "key": "field_697778efb5825",
                                "label": "Quote",
                                "name": "bp_quote",
                                "aria-label": "",
                                "type": "textarea",
                                "instructions": "",
                                "required": 0,
                                "conditional_logic": 0,
                                "wrapper": {
                                    "width": "100",
                                    "class": "",
                                    "id": ""
                                },
                                "default_value": "",
                                "placeholder": "",
                                "maxlength": "",
                                "rows": 4,
                                "new_lines": "wpautop",
                                "show_column": 0,
                                "show_column_weight": 1000,
                                "allow_quickedit": 0,
                                "allow_bulkedit": 0
                            },
                            {
                                "key": "field_69777903b5826",
                                "label": "Attribution",
                                "name": "bp_quote_attrib",
                                "aria-label": "",
                                "type": "text",
                                "instructions": "",
                                "required": 0,
                                "conditional_logic": 0,
                                "wrapper": {
                                    "width": "50",
                                    "class": "",
                                    "id": ""
                                },
                                "default_value": "",
                                "placeholder": "“e.g., Owner’s note \/ Concept statement”",
                                "prepend": "",
                                "append": "",
                                "maxlength": "",
                                "show_column": 0,
                                "show_column_sortable": 0,
                                "show_column_weight": 1000,
                                "allow_quickedit": 0,
                                "allow_bulkedit": 0
                            }
                        ],
                        "min": "",
                        "max": ""
                    }
                },
                "button_label": "Add Row",
                "min": "",
                "max": ""
            },
            {
                "key": "field_6977701c87ddf",
                "label": "5. Gallery",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_6977702787de0",
                "label": "BP gallery",
                "name": "bp_gallery",
                "aria-label": "",
                "type": "gallery",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "id",
                "preview_size": "medium",
                "insert": "append",
                "library": "all",
                "min": "",
                "max": "",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": "",
                "show_column": 0,
                "show_column_weight": 1000
            },
            {
                "key": "field_6978ae2d24199",
                "label": "BP interior gallery",
                "name": "bp_interior_gallery",
                "aria-label": "",
                "type": "gallery",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "id",
                "preview_size": "medium",
                "insert": "append",
                "library": "all",
                "min": "",
                "max": "",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": "",
                "show_column": 0,
                "show_column_weight": 1000
            },
            {
                "key": "field_6977703887de1",
                "label": "BP gallery download enabled",
                "name": "bp_gallery_download_enabled",
                "aria-label": "",
                "type": "true_false",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "message": "",
                "default_value": 0,
                "ui": 0,
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0,
                "show_column_filter": 0,
                "ui_on_text": "",
                "ui_off_text": ""
            },
            {
                "key": "field_6977705c87de2",
                "label": "BP gallery download file",
                "name": "bp_gallery_download_file",
                "aria-label": "",
                "type": "file",
                "instructions": "(Optional: a zipped brochure\/PDF pack; keep gated if you prefer.)",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "url",
                "library": "all",
                "min_size": "",
                "max_size": "",
                "mime_types": "",
                "show_column": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_6977707a87de3",
                "label": "6. Features & Amenities",
                "name": "",
                "aria-label": "",
                "type": "accordion",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "open": 0,
                "multi_expand": 0,
                "endpoint": 0
            },
            {
                "key": "field_6977708287de4",
                "label": "BP features",
                "name": "bp_features",
                "aria-label": "",
                "type": "repeater",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "collapsed": "",
                "min": 0,
                "max": 0,
                "layout": "table",
                "button_label": "Add Row",
                "sub_fields": [
                    {
                        "key": "field_6977709287de5",
                        "label": "BP feature title",
                        "name": "bp_feature_title",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_6977708287de4"
                    },
                    {
                        "key": "field_697770a087de6",
                        "label": "BP Feature Desc",
                        "name": "bp_feature_desc",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_6977708287de4"
                    }
                ],
                "rows_per_page": 20
            },
            {
                "key": "field_697770b187de7",
                "label": "BP amenities",
                "name": "bp_amenities",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_697770de87de9",
                "label": "7. Dual-Use \/ Hospitality Capability",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_697770e587dea",
                "label": "BP Dual use heading",
                "name": "bp_dual_use_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_697770f587deb",
                "label": "BP Dual use text",
                "name": "bp_dual_use_text",
                "aria-label": "",
                "type": "wysiwyg",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "tabs": "all",
                "toolbar": "basic",
                "media_upload": 0,
                "delay": 0
            },
            {
                "key": "field_6977710087dec",
                "label": "BP hospitality assets",
                "name": "bp_hospitality_assets",
                "aria-label": "",
                "type": "repeater",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "collapsed": "",
                "min": 0,
                "max": 0,
                "layout": "table",
                "button_label": "Add Row",
                "sub_fields": [
                    {
                        "key": "field_6977711a87ded",
                        "label": "BP HA item",
                        "name": "bp_ha_item",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_6977710087dec"
                    }
                ],
                "rows_per_page": 20
            },
            {
                "key": "field_6977712887dee",
                "label": "BP Operations note",
                "name": "bp_operations_note",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "maxlength": "",
                "rows": 4,
                "new_lines": "",
                "show_column": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_6977714087def",
                "label": "8. Floorplans",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_6977714d87df0",
                "label": "BP floorplans",
                "name": "bp_floorplans",
                "aria-label": "",
                "type": "repeater",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "collapsed": "",
                "min": 0,
                "max": 0,
                "layout": "table",
                "button_label": "Add Row",
                "sub_fields": [
                    {
                        "key": "field_6977716587df1",
                        "label": "BP FP label",
                        "name": "bp_fp_label",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_6977714d87df0"
                    },
                    {
                        "key": "field_6977717487df2",
                        "label": "BP FP image",
                        "name": "bp_fp_image",
                        "aria-label": "",
                        "type": "image",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "return_format": "id",
                        "preview_size": "medium",
                        "library": "all",
                        "min_width": "",
                        "min_height": "",
                        "min_size": "",
                        "max_width": "",
                        "max_height": "",
                        "max_size": "",
                        "mime_types": "",
                        "show_column": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_6977714d87df0"
                    },
                    {
                        "key": "field_6977718387df3",
                        "label": "BP FP note",
                        "name": "bp_fp_note",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_6977714d87df0"
                    }
                ],
                "rows_per_page": 20
            },
            {
                "key": "field_6977719487df4",
                "label": "9. Location Section",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_6977719e87df5",
                "label": "BP map mode",
                "name": "bp_map_mode",
                "aria-label": "",
                "type": "select",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "choices": {
                    "embed": "embed",
                    "image": "image"
                },
                "default_value": [],
                "allow_null": 0,
                "multiple": 0,
                "ui": 0,
                "return_format": "value",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0,
                "show_column_filter": 0,
                "ajax": 0,
                "placeholder": "",
                "create_options": 0,
                "save_options": 0
            },
            {
                "key": "field_697771b887df6",
                "label": "BP Map embed",
                "name": "bp_map_embed",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "maxlength": "",
                "rows": 4,
                "new_lines": "wpautop",
                "show_column": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_697771c887df7",
                "label": "BP Map image",
                "name": "bp_map_image",
                "aria-label": "",
                "type": "image",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "id",
                "preview_size": "medium",
                "library": "all",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": "",
                "show_column": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_697771d787df8",
                "label": "BP Location highlights",
                "name": "bp_location_highlights",
                "aria-label": "",
                "type": "repeater",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "collapsed": "",
                "min": 0,
                "max": 0,
                "layout": "table",
                "button_label": "Add Row",
                "sub_fields": [
                    {
                        "key": "field_697771e687df9",
                        "label": "BP LH text",
                        "name": "bp_lh_text",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_697771d787df8"
                    }
                ],
                "rows_per_page": 20
            },
            {
                "key": "field_697771f587dfa",
                "label": "10. Enquiry \/ Conversion Controls",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_6977720487dfb",
                "label": "BP primary cta label",
                "name": "bp_primary_cta_label",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_6977721387dfc",
                "label": "BP secondary cta label",
                "name": "bp_secondary_cta_label",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_6977722687dfd",
                "label": "BP enquiry recipient",
                "name": "bp_enquiry_recipient",
                "aria-label": "",
                "type": "email",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_6977723f87dfe",
                "label": "BP Enquiry gating note",
                "name": "bp_enquiry_gating_note",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            }
        ],
        "location": [
            [
                {
                    "param": "post_type",
                    "operator": "==",
                    "value": "bodrum-property"
                }
            ]
        ],
        "menu_order": 0,
        "position": "normal",
        "style": "default",
        "label_placement": "top",
        "instruction_placement": "label",
        "hide_on_screen": "",
        "active": true,
        "description": "",
        "show_in_rest": false,
        "display_title": "",
        "qef_simple_location_rules": 0
    },
    {
        "key": "group_67d007ad77e6b",
        "title": "District CPT",
        "fields": [
            {
                "key": "field_67d007c00155a",
                "label": "District image",
                "name": "district_image",
                "aria-label": "",
                "type": "image",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "array",
                "preview_size": "full",
                "library": "all",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": ""
            },
            {
                "key": "field_6996fcc872abf",
                "label": "Related Districts",
                "name": "related_districts",
                "aria-label": "",
                "type": "taxonomy",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "taxonomy": "district",
                "add_term": 0,
                "save_terms": 0,
                "load_terms": 0,
                "return_format": "object",
                "field_type": "multi_select",
                "allow_null": 1,
                "allow_in_bindings": 0,
                "bidirectional": 0,
                "multiple": 0,
                "bidirectional_target": []
            },
            {
                "key": "field_6996fdbd72ac0",
                "label": "Related regions",
                "name": "related_regions",
                "aria-label": "",
                "type": "taxonomy",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "taxonomy": "region",
                "add_term": 0,
                "save_terms": 0,
                "load_terms": 0,
                "return_format": "object",
                "field_type": "multi_select",
                "allow_null": 1,
                "allow_in_bindings": 0,
                "bidirectional": 0,
                "multiple": 0,
                "bidirectional_target": []
            },
            {
                "key": "field_6996fdd372ac1",
                "label": "Related tags",
                "name": "related_tags",
                "aria-label": "",
                "type": "taxonomy",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "taxonomy": "property_tags",
                "add_term": 0,
                "save_terms": 0,
                "load_terms": 0,
                "return_format": "object",
                "field_type": "multi_select",
                "allow_null": 1,
                "allow_in_bindings": 0,
                "bidirectional": 0,
                "multiple": 0,
                "bidirectional_target": []
            },
            {
                "key": "field_69e23345f60ba",
                "label": "Regional guide",
                "name": "regional_guide",
                "aria-label": "",
                "type": "post_object",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "post_type": "",
                "post_status": "",
                "taxonomy": [
                    "category:regional-guides"
                ],
                "return_format": "id",
                "multiple": 0,
                "allow_null": 0,
                "allow_in_bindings": 0,
                "bidirectional": 0,
                "ui": 1,
                "bidirectional_target": []
            },
            {
                "key": "field_6a0aefe604a22",
                "label": "Short label",
                "name": "short_label",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "placeholder": "",
                "prepend": "",
                "append": ""
            }
        ],
        "location": [
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "district"
                }
            ],
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "region"
                }
            ],
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "property_tags"
                }
            ]
        ],
        "menu_order": 0,
        "position": "normal",
        "style": "default",
        "label_placement": "top",
        "instruction_placement": "label",
        "hide_on_screen": "",
        "active": true,
        "description": "",
        "show_in_rest": 0,
        "display_title": ""
    },
    {
        "key": "group_67289a5abbcb5",
        "title": "Posts CF",
        "fields": [
            {
                "key": "field_673c488121b12",
                "label": "Related district",
                "name": "related_district",
                "aria-label": "",
                "type": "taxonomy",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "taxonomy": "district",
                "field_type": "select",
                "allow_null": 0,
                "add_term": 0,
                "save_terms": 0,
                "load_terms": 0,
                "return_format": "id",
                "multiple": 0,
                "bidirectional_target": []
            },
            {
                "key": "field_69ccf6201418e",
                "label": "Post subtitle",
                "name": "post_subtitle",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "placeholder": "",
                "prepend": "",
                "append": ""
            }
        ],
        "location": [
            [
                {
                    "param": "post_type",
                    "operator": "==",
                    "value": "post"
                }
            ]
        ],
        "menu_order": 0,
        "position": "normal",
        "style": "default",
        "label_placement": "top",
        "instruction_placement": "label",
        "hide_on_screen": "",
        "active": true,
        "description": "",
        "show_in_rest": 0,
        "display_title": ""
    },
    {
        "key": "group_5ebd0dc6c6e31",
        "title": "Projects",
        "fields": [
            {
                "key": "field_5ebd0e1cffe03",
                "label": "Project Name",
                "name": "project_name",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 1,
                "show_column_sortable": 1,
                "show_column_weight": 1000,
                "allow_quickedit": 1,
                "allow_bulkedit": 0
            },
            {
                "key": "field_5ebd0dd99ab9a",
                "label": "Basic Details",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_5ebd0ef694cd7",
                "label": "Land size",
                "name": "land_size",
                "aria-label": "",
                "type": "number",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "33",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "sqm",
                "min": "",
                "max": "",
                "step": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_5ebd0f1894cd8",
                "label": "Number of units",
                "name": "number_of_units",
                "aria-label": "",
                "type": "number",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "33",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "min": "",
                "max": "",
                "step": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            },
            {
                "key": "field_5ebd0f4294cda",
                "label": "Completion date",
                "name": "completion_date",
                "aria-label": "",
                "type": "date_picker",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "33",
                    "class": "",
                    "id": ""
                },
                "display_format": "F j, Y",
                "return_format": "F j, Y",
                "first_day": 1,
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0,
                "default_to_current_date": 0
            },
            {
                "key": "field_5ebd0f8994cdd",
                "label": "Map",
                "name": "map",
                "aria-label": "",
                "type": "google_map",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "50",
                    "class": "",
                    "id": ""
                },
                "center_lat": "",
                "center_lng": "",
                "zoom": "",
                "height": 300,
                "allow_in_bindings": 1
            },
            {
                "key": "field_5f8f36436f5b5",
                "label": "Sold out",
                "name": "sold_out",
                "aria-label": "",
                "type": "checkbox",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "10",
                    "class": "",
                    "id": ""
                },
                "choices": {
                    "Sold out": "Sold out"
                },
                "default_value": [],
                "return_format": "value",
                "allow_custom": 0,
                "allow_in_bindings": 1,
                "layout": "vertical",
                "toggle": 0,
                "save_custom": 0,
                "custom_choice_button_text": "Add new choice"
            },
            {
                "key": "field_5fd9ad2b0c44f",
                "label": "Custom Text",
                "name": "custom_text",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "15",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 1,
                "placeholder": "",
                "prepend": "",
                "append": ""
            },
            {
                "key": "field_67176025202b5",
                "label": "Custom Text link",
                "name": "custom_text_link",
                "aria-label": "",
                "type": "url",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "15",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "allow_in_bindings": 1,
                "placeholder": ""
            },
            {
                "key": "field_5ebd0f5a94cdb",
                "label": "Texts",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_670d0bff86d60",
                "label": "Project summary heading",
                "name": "project_summary_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "Quick facts",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_5ebd0f7094cdc",
                "label": "Project Summary",
                "name": "project_summary",
                "aria-label": "",
                "type": "wysiwyg",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "tabs": "all",
                "toolbar": "full",
                "media_upload": 1,
                "delay": 0
            },
            {
                "key": "field_66fe978d30e31",
                "label": "Whats special heading",
                "name": "whats_special_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "What's special about this property?",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_5ebd0f9794cde",
                "label": "About this project",
                "name": "about_this_project",
                "aria-label": "",
                "type": "wysiwyg",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "tabs": "all",
                "toolbar": "full",
                "media_upload": 1,
                "delay": 0
            },
            {
                "key": "field_670d0ba7764b6",
                "label": "Location info heading",
                "name": "location_info_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "Location info",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_5ebd102694ce5",
                "label": "Distances",
                "name": "distances",
                "aria-label": "",
                "type": "wysiwyg",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "tabs": "all",
                "toolbar": "full",
                "media_upload": 1,
                "delay": 0
            },
            {
                "key": "field_5ebd397220348",
                "label": "Facilities",
                "name": "facilities",
                "aria-label": "",
                "type": "checkbox",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "choices": {
                    "24 7 security": "24 7 security",
                    "5 star hotel": "5 star hotel",
                    "Art room": "Art room",
                    "Basketball courts": "Basketball courts",
                    "Bicycle Path": "Bicycle Path",
                    "Botanic park": "Botanic park",
                    "Café": "Café",
                    "Car rental": "Car rental",
                    "Central Satellite System": "Central Satellite System",
                    "Child play areas": "Child play areas",
                    "Child swimming pool": "Child swimming pool",
                    "Commercial space": "Commercial space",
                    "Concierge": "Concierge",
                    "Creche": "Creche",
                    "Elevator": "Elevator",
                    "Football courts": "Football courts",
                    "Forest View": "Forest View",
                    "Games Room": "Games Room",
                    "Generator": "Generator",
                    "Guest bedrooms": "Guest bedrooms",
                    "Guest Rooms": "Guest Rooms",
                    "Gym": "Gym",
                    "Helipad": "Helipad",
                    "Hobby room": "Hobby room",
                    "House Keeping": "House Keeping",
                    "Indoor Cinema": "Indoor Cinema",
                    "Indoor swimming pool": "Indoor swimming pool",
                    "Kids club": "Kids club",
                    "Table tennis": "Table tennis",
                    "Lake": "Lake",
                    "Lake View": "Lake View",
                    "Landscaped gardens": "Landscaped gardens",
                    "Lobby": "Lobby",
                    "Mall": "Mall",
                    "Meeting Area": "Meeting Area",
                    "Meeting rooms": "Meeting rooms",
                    "Metro station": "Metro station",
                    "Music room": "Music room",
                    "Open air cinema": "Open air cinema",
                    "Ornamental fountains": "Ornamental fountains",
                    "Outdoor exercise area": "Outdoor exercise area",
                    "Outdoor Parking": "Outdoor Parking",
                    "Outdoor swimming pool": "Outdoor swimming pool",
                    "Party room": "Party room",
                    "Playstation room": "Playstation room",
                    "Private garden": "Private garden",
                    "Promenade": "Promenade",
                    "Reception": "Reception",
                    "Recreation areas": "Recreation areas",
                    "Restaurant": "Restaurant",
                    "Rooftop restaurant": "Rooftop restaurant",
                    "Rooftop terrace": "Rooftop terrace",
                    "Sauna": "Sauna",
                    "Sea View": "Sea View",
                    "Skateboarding park": "Skateboarding park",
                    "Smart home system": "Smart home system",
                    "Spa": "Spa",
                    "Sports hall": "Sports hall",
                    "Squash": "Squash",
                    "Sunbathing terraces": "Sunbathing terraces",
                    "Tennis Court": "Tennis Court",
                    "Turkish baths": "Turkish baths",
                    "Underfloor heating": "Underfloor heating",
                    "Underground parking": "Underground parking",
                    "University": "University",
                    "Valet parking": "Valet parking",
                    "Vehicle Charger Station": "Vehicle Charger Station",
                    "Vitamin Bar": "Vitamin Bar",
                    "Volleyball courts": "Volleyball courts",
                    "Walking parkour": "Walking parkour",
                    "Water depot": "Water depot",
                    "Water Park": "Water Park",
                    "Water Sports": "Water Sports",
                    "Yacht Marina": "Yacht Marina"
                },
                "allow_custom": 0,
                "default_value": [],
                "layout": "horizontal",
                "toggle": 1,
                "return_format": "value",
                "save_custom": 0,
                "custom_choice_button_text": "Add new choice"
            },
            {
                "key": "field_6717605d70d07",
                "label": "Videos",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_673ee827442a1",
                "label": "YT heading",
                "name": "yt_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "20",
                    "class": "",
                    "id": ""
                },
                "default_value": "Check out this video!",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_5f21349ea2272",
                "label": "YT Video",
                "name": "yt_video",
                "aria-label": "",
                "type": "oembed",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "70",
                    "class": "",
                    "id": ""
                },
                "width": "",
                "height": ""
            },
            {
                "key": "field_67175bdc93495",
                "label": "Custom video heading",
                "name": "custom_video_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "20",
                    "class": "",
                    "id": ""
                },
                "default_value": "Apartment tour",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_671760ac70d09",
                "label": "Custom video button",
                "name": "custom_video_button",
                "aria-label": "",
                "type": "text",
                "instructions": "This is the text that apears on the button at the top of the page",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "20",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_671760e270d0a",
                "label": "Custom video text",
                "name": "custom_video_text",
                "aria-label": "",
                "type": "text",
                "instructions": "TExt which will appear below the heading",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "20",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_66ffc7da4d97b",
                "label": "Video (file)",
                "name": "video_file",
                "aria-label": "",
                "type": "file",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "array",
                "library": "all",
                "min_size": "",
                "max_size": "",
                "mime_types": ""
            },
            {
                "key": "field_5ebd0fa994cdf",
                "label": "Galleries",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_5ebd265523532",
                "label": "Main image",
                "name": "main_image",
                "aria-label": "",
                "type": "image",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "array",
                "preview_size": "medium",
                "library": "all",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": ""
            },
            {
                "key": "field_5ebd0fb294ce0",
                "label": "Main Gallery",
                "name": "main_gallery",
                "aria-label": "",
                "type": "gallery",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "array",
                "preview_size": "medium",
                "insert": "append",
                "library": "all",
                "min": "",
                "max": "",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": ""
            },
            {
                "key": "field_66fea218a88d2",
                "label": "FP check box",
                "name": "fp_check_box",
                "aria-label": "",
                "type": "true_false",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "20",
                    "class": "",
                    "id": ""
                },
                "message": "",
                "default_value": 0,
                "ui": 0,
                "ui_on_text": "",
                "ui_off_text": ""
            },
            {
                "key": "field_66fea1e8a88d0",
                "label": "Floor plans heading",
                "name": "floor_plans_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "40",
                    "class": "",
                    "id": ""
                },
                "default_value": "Floor plans",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_66fea39cc067a",
                "label": "Floor plans custom text",
                "name": "floor_plans_custom_text",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "40",
                    "class": "",
                    "id": ""
                },
                "default_value": "These are some sample floor plans - there are many other types available.",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_5ebd0fc194ce1",
                "label": "Floor plans",
                "name": "floor_plans",
                "aria-label": "",
                "type": "gallery",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "array",
                "preview_size": "medium",
                "insert": "append",
                "library": "all",
                "min": "",
                "max": "",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": ""
            },
            {
                "key": "field_670d1a439bee5",
                "label": "Link to regional guide",
                "name": "",
                "aria-label": "",
                "type": "tab",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "placement": "top",
                "endpoint": 0,
                "selected": 0
            },
            {
                "key": "field_6724d4c6c8388",
                "label": "Further reading heading",
                "name": "further_reading_heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "50",
                    "class": "",
                    "id": ""
                },
                "default_value": "Further Reading",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_6724d4f1c8389",
                "label": "Further reading text",
                "name": "further_reading_text",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "50",
                    "class": "",
                    "id": ""
                },
                "default_value": "Read more about the region and related content.",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_670d1a669bee6",
                "label": "Post heading",
                "name": "post_heading",
                "aria-label": "",
                "type": "relationship",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "post_type": [
                    "post"
                ],
                "taxonomy": "",
                "filters": [
                    "search",
                    "post_type",
                    "taxonomy"
                ],
                "elements": [
                    "featured_image"
                ],
                "min": "",
                "max": "",
                "return_format": "id",
                "bidirectional_target": []
            },
            {
                "key": "field_6789130683fa7",
                "label": "Price list KD",
                "name": "price_list_kd",
                "aria-label": "",
                "type": "repeater",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "collapsed": "",
                "min": 0,
                "max": 0,
                "layout": "table",
                "button_label": "Add Row",
                "sub_fields": [
                    {
                        "key": "field_678a183a9ed55",
                        "label": "Floor",
                        "name": "floor_kd",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "10",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "parent_repeater": "field_6789130683fa7"
                    },
                    {
                        "key": "field_678a15d96f8e5",
                        "label": "Type",
                        "name": "type_kd",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "20",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "parent_repeater": "field_6789130683fa7"
                    },
                    {
                        "key": "field_678a15e36f8e6",
                        "label": "Net size sqm",
                        "name": "net_size_kd",
                        "aria-label": "",
                        "type": "number",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "20",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "min": "",
                        "max": "",
                        "step": "",
                        "parent_repeater": "field_6789130683fa7"
                    },
                    {
                        "key": "field_678a16096f8e7",
                        "label": "Gross size sqm",
                        "name": "gross_size_kd",
                        "aria-label": "",
                        "type": "number",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "15",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "min": "",
                        "max": "",
                        "step": "",
                        "parent_repeater": "field_6789130683fa7"
                    },
                    {
                        "key": "field_678913ae22a82",
                        "label": "View",
                        "name": "view_kd",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "20",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "parent_repeater": "field_6789130683fa7"
                    },
                    {
                        "key": "field_678a161c6f8e8",
                        "label": "Price",
                        "name": "price_kd",
                        "aria-label": "",
                        "type": "number",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "15",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "$",
                        "append": "",
                        "min": "",
                        "max": "",
                        "step": "",
                        "parent_repeater": "field_6789130683fa7"
                    }
                ],
                "rows_per_page": 20
            }
        ],
        "location": [
            [
                {
                    "param": "post_type",
                    "operator": "==",
                    "value": "property"
                }
            ]
        ],
        "menu_order": 0,
        "position": "normal",
        "style": "default",
        "label_placement": "top",
        "instruction_placement": "label",
        "hide_on_screen": "",
        "active": true,
        "description": "",
        "show_in_rest": 0,
        "display_title": ""
    },
    {
        "key": "group_69cb783872932",
        "title": "Seo helper fields",
        "fields": [
            {
                "key": "field_69cb7838ccf28",
                "label": "SEO title",
                "name": "seo_title",
                "aria-label": "",
                "type": "text",
                "instructions": "Google result title, around 50–60 characters",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "placeholder": "",
                "prepend": "",
                "append": ""
            },
            {
                "key": "field_69cb786accf29",
                "label": "SEO Meta description",
                "name": "seo_meta_description",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "rows": "",
                "placeholder": "",
                "new_lines": ""
            },
            {
                "key": "field_69cbaf8d8b168",
                "label": "SEO Social Image",
                "name": "seo_social_image",
                "aria-label": "",
                "type": "image",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "array",
                "library": "all",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": "",
                "allow_in_bindings": 0,
                "preview_size": "medium"
            },
            {
                "key": "field_6a35018785dde",
                "label": "SEO FAQ V2",
                "name": "seo_faq_v2",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "maxlength": "",
                "allow_in_bindings": 0,
                "rows": "",
                "placeholder": "",
                "new_lines": ""
            }
        ],
        "location": [
            [
                {
                    "param": "post_type",
                    "operator": "==",
                    "value": "post"
                }
            ],
            [
                {
                    "param": "post_type",
                    "operator": "==",
                    "value": "property"
                }
            ],
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "district"
                }
            ],
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "region"
                }
            ],
            [
                {
                    "param": "post_type",
                    "operator": "==",
                    "value": "page"
                }
            ],
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "property_type"
                }
            ],
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "category"
                }
            ],
            [
                {
                    "param": "taxonomy",
                    "operator": "==",
                    "value": "property_tags"
                }
            ]
        ],
        "menu_order": 0,
        "position": "normal",
        "style": "default",
        "label_placement": "top",
        "instruction_placement": "label",
        "hide_on_screen": "",
        "active": true,
        "description": "",
        "show_in_rest": 0,
        "display_title": ""
    },
    {
        "key": "group_66f14ebecefb5",
        "title": "Services",
        "fields": [
            {
                "key": "field_66f14ed26edba",
                "label": "Heading",
                "name": "heading",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_66f14ee06edbb",
                "label": "Description",
                "name": "description",
                "aria-label": "",
                "type": "textarea",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "maxlength": "",
                "rows": "",
                "new_lines": ""
            },
            {
                "key": "field_66f14ef31dc63",
                "label": "Button text",
                "name": "button_text",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_66f14f016605f",
                "label": "Button link",
                "name": "button_link",
                "aria-label": "",
                "type": "url",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": ""
            }
        ],
        "location": [
            [
                {
                    "param": "post_type",
                    "operator": "==",
                    "value": "service"
                }
            ]
        ],
        "menu_order": 0,
        "position": "normal",
        "style": "default",
        "label_placement": "top",
        "instruction_placement": "label",
        "hide_on_screen": "",
        "active": true,
        "description": "",
        "show_in_rest": false,
        "display_title": ""
    },
    {
        "key": "group_5eff3a01be5af",
        "title": "Team",
        "fields": [
            {
                "key": "field_5eff3a058057b",
                "label": "Name",
                "name": "name",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_5eff3a0c8057c",
                "label": "Position",
                "name": "position",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_5eff3a138057d",
                "label": "Description",
                "name": "description",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": ""
            },
            {
                "key": "field_5eff3a1a8057e",
                "label": "Photo",
                "name": "photo",
                "aria-label": "",
                "type": "image",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "return_format": "array",
                "preview_size": "medium",
                "library": "all",
                "min_width": "",
                "min_height": "",
                "min_size": "",
                "max_width": "",
                "max_height": "",
                "max_size": "",
                "mime_types": ""
            },
            {
                "key": "field_697b50c4e3d05",
                "label": "Advisor?",
                "name": "advisor",
                "aria-label": "",
                "type": "true_false",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "message": "",
                "default_value": 0,
                "ui": 0,
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0,
                "show_column_filter": 0,
                "ui_on_text": "",
                "ui_off_text": ""
            },
            {
                "key": "field_697b52b2e6abc",
                "label": "Number",
                "name": "number",
                "aria-label": "",
                "type": "number",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "min": "",
                "max": "",
                "step": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            }
        ],
        "location": [
            [
                {
                    "param": "post_type",
                    "operator": "==",
                    "value": "team"
                }
            ]
        ],
        "menu_order": 0,
        "position": "normal",
        "style": "default",
        "label_placement": "top",
        "instruction_placement": "label",
        "hide_on_screen": "",
        "active": true,
        "description": "",
        "show_in_rest": false,
        "display_title": "",
        "qef_simple_location_rules": 0
    },
    {
        "key": "group_695ce531d3446",
        "title": "Units (V2 Test)",
        "fields": [
            {
                "key": "field_695ce53e6547f",
                "label": "v2_units",
                "name": "v2_units",
                "aria-label": "",
                "type": "repeater",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "collapsed": "field_695ce6b9c6448",
                "min": 0,
                "max": 0,
                "layout": "table",
                "button_label": "Add Row",
                "sub_fields": [
                    {
                        "key": "field_695ce56365480",
                        "label": "v2 bedrooms",
                        "name": "v2_bedrooms",
                        "aria-label": "",
                        "type": "number",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "min": "",
                        "max": "",
                        "step": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_695ce53e6547f"
                    },
                    {
                        "key": "field_695ce59565482",
                        "label": "v2 gross size min",
                        "name": "v2_gross_size_min",
                        "aria-label": "",
                        "type": "number",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "min": "",
                        "max": "",
                        "step": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_695ce53e6547f"
                    },
                    {
                        "key": "field_695f9830776c4",
                        "label": "v2 gross size max",
                        "name": "v2_gross_size_max",
                        "aria-label": "",
                        "type": "number",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "min": "",
                        "max": "",
                        "step": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_695ce53e6547f"
                    },
                    {
                        "key": "field_695ce5aa65483",
                        "label": "v2 price usd min",
                        "name": "v2_price_usd_min",
                        "aria-label": "",
                        "type": "number",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "min": "",
                        "max": "",
                        "step": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_695ce53e6547f"
                    },
                    {
                        "key": "field_695f983c776c5",
                        "label": "v2 price usd max",
                        "name": "v2_price_usd_max",
                        "aria-label": "",
                        "type": "number",
                        "instructions": "",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "min": "",
                        "max": "",
                        "step": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_695ce53e6547f"
                    },
                    {
                        "key": "field_695ce6b9c6448",
                        "label": "v2_index_key",
                        "name": "v2_index_key",
                        "aria-label": "",
                        "type": "text",
                        "instructions": "auto-generated do not edit",
                        "required": 0,
                        "conditional_logic": 0,
                        "wrapper": {
                            "width": "",
                            "class": "",
                            "id": ""
                        },
                        "default_value": "",
                        "placeholder": "",
                        "prepend": "",
                        "append": "",
                        "maxlength": "",
                        "show_column": 0,
                        "show_column_sortable": 0,
                        "show_column_weight": 1000,
                        "allow_quickedit": 0,
                        "allow_bulkedit": 0,
                        "parent_repeater": "field_695ce53e6547f"
                    }
                ],
                "rows_per_page": 20
            },
            {
                "key": "field_6965feca39166",
                "label": "V2 Custom text",
                "name": "v2_custom_text",
                "aria-label": "",
                "type": "text",
                "instructions": "",
                "required": 0,
                "conditional_logic": 0,
                "wrapper": {
                    "width": "",
                    "class": "",
                    "id": ""
                },
                "default_value": "",
                "placeholder": "",
                "prepend": "",
                "append": "",
                "maxlength": "",
                "show_column": 0,
                "show_column_sortable": 0,
                "show_column_weight": 1000,
                "allow_quickedit": 0,
                "allow_bulkedit": 0
            }
        ],
        "location": [
            [
                {
                    "param": "post_type",
                    "operator": "==",
                    "value": "property"
                }
            ]
        ],
        "menu_order": 0,
        "position": "normal",
        "style": "default",
        "label_placement": "top",
        "instruction_placement": "label",
        "hide_on_screen": "",
        "active": true,
        "description": "",
        "show_in_rest": false,
        "display_title": "",
        "qef_simple_location_rules": 0
    }
]
```
