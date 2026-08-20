# Property translation field inventory

Audited templates: `single-property.php`, `archive-property.php`, `page-property-map.php`, both property cards, home featured/special offers, related taxonomy cards, archive AJAX, and property offer/JSON-LD helpers.

## A — stored translation (`meta:<field>`)

`project_name`, `floor_plans_heading`, `floor_plans_custom_text`, `property_editorial_intro`, `property_highlights_text`, `property_district_analysis`, `property_investment_potential`, `property_buyer_suitability`, `property_developer_profile`, `property_faq_text`, `target_buyer_type`, `property_key_advantages`, `further_reading_heading`, `further_reading_text`, `custom_video_heading`, `custom_video_text`, `project_summary_heading`, `project_summary`, `yt_heading`, `whats_special_heading`, `about_this_project`, `location_info_heading`, `distances`, archive headings/intro/body/CTA text, post subtitle, SEO title and SEO description. Core post title/content/excerpt remain structured post fields.

Term names and descriptions for `district`, `region`, `property_type`, `property_tags`, and `special` use the canonical term ID with `term_name` / `term_description` rows. The district ACF prose fields `district_archive_subtitle` and `district_archive_body` are term-owned and use `term` rows with `meta:<field>` keys.

The main property archive fields (`archive_h1`, subtitle, intro, bottom content, and CTA copy) are post-owned: the theme resolves the private `property-archive-seo-settings` page and passes that numeric page ID to ACF. Property detail fields likewise use their numeric property post ID. No approved field is stored on the ACF options object.

## B — preserve exactly

Images/galleries and attachment IDs/URLs/alts; video files/embeds; maps, latitude and longitude; prices and currencies; `v2_units` numeric price/bed/size data and its index; reference/CRM IDs; land/compound size; number of units; completion dates; estimated rental yield; bedrooms; floor-plan files; relationship IDs; advisor phone/photo; checkboxes; URLs; schema identifiers, availability, dates, and numeric offer data.

## C — controlled vocabulary

Property type/status, furnished state, view/facility labels, sale/rent state, parking/pool, citizenship/investment suitability, and taxonomy names. District/region names prefer stored reviewed translations; the English value is retained until a transliteration policy or translation exists.

Nested free-text repeater values are excluded until their row identity is stable. Amenity checkbox values use vocabulary rather than AI. No unapproved meta is filtered globally.
