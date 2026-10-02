# Pera Property CPT AI Upload Guide

Last reviewed against `main`: 2 October 2026.

Current code authorities:

- `wp-content/themes/hello-elementor-child/inc/acf-fields.php`
- `wp-content/themes/hello-elementor-child/inc/v2-units-index.php`
- `wp-content/themes/hello-elementor-child/inc/seo-property.php`
- `wp-content/themes/hello-elementor-child/single-property.php`
- `wp-content/plugins/pera-multilingual/`

## Purpose

This guide instructs an AI assistant how to prepare and populate Pera Property WordPress `property` CPT listings for:

- New-build projects
- Individual resale properties

Authoritative examples:

- Project listing: post `55912`
- Resale listing: post `59052`

The AI must inspect supplied source material, prepare accurate content, populate the CPT through guarded WP-CLI commands, verify the result, and leave publication to a human.

## Non-negotiable workflow rules

1. If the user supplies a post ID, update only that post.
2. If no ID is supplied, create a new `property` post with status `draft`.
3. Never modify a published post without explicit confirmation naming the post ID.
4. Never publish a new listing automatically. Keep new posts as drafts; preserve the status of an explicitly approved existing-post update.
5. Before any write, verify `get_post_type($id) === "property"`.
6. Prefer small, idempotent batches during initial testing. A full importer may be used after the field mapping is confirmed.
7. Use exact ACF field keys for checkbox, true/false, repeater, gallery and other structured fields.
8. Verify stored values after every substantive write.
9. Never invent missing facts, prices, facilities, eligibility, yields, distances, completion dates or developer claims.
10. Record contradictions and missing information rather than silently resolving them.
11. Make every preflight check before the first mutation. A command that fails after partial writes is not a safe importer.
12. Preserve the existing post status when updating an approved published listing. Do not demote or republish it as a side effect.

## Published-post backup and rollback

Before changing an approved published post:

1. Export the exact post fields, taxonomies and meta keys that the command may change.
2. Store a timestamped snapshot outside the post or in a uniquely named backup meta key.
3. Validate that structured values such as repeaters and maps can be decoded before writing anything.
4. Print the snapshot location or backup key in the command output.
5. Keep the snapshot until the edited listing has been reviewed.

A rollback command must restore the snapshot as captured. Do not make restoration conditional on the post still matching an assumed intermediate state. Never claim a backup is complete if it omits a field that the update command can change.

## Project-name confidentiality rule

By default, the actual commercial project or development name may appear only in the internal ACF field:

- `project_name`

It must not appear in:

- WordPress post title
- Slug
- Excerpt
- Post content
- Project summary
- Main description
- Editorial sections
- FAQs
- SEO title or description
- Image titles, filenames or alt text prepared by the AI

Use neutral alternatives such as `the project`, `the development`, `this property` or `the residence`.

Do not add developer or construction-credibility material to public narrative fields. Leave the dedicated developer profile blank.

Apply the same discretion to named resale compounds or residences unless the user explicitly authorises public use. A user may, for example, approve a recognised compound name for a resale title and SEO because buyers search for that name.

When an exception is approved:

- Record the exact permitted name or spelling and the public fields in which it may appear.
- Use it only where it materially improves identification or search relevance.
- Keep unrelated internal names, developer codes and inventory labels private.
- Run an audit for both the permitted spelling and confidential variants before handoff.

This is a content workflow rule, not a technical secrecy boundary. The theme may expose `project_name` to authorised frontend-admin users, and the multilingual plugin treats it as translatable metadata.

## Source handling

Typical source material includes brochures, presentations, price lists, availability sheets, floor plans, maps, videos and image folders.

Classify each extracted fact as:

- Confirmed by supplied source
- Confirmed by the user
- Derived mechanically, such as a minimum across a price table
- Missing or contradictory

Marketing copy in a developer brochure is not independent evidence. Ignore developer statistics and construction-credibility claims rather than reproducing them.

Do not make absolute safety claims. Prefer `advanced seismic protection`, `seismically engineered` or a precise description of the installed system. Never describe any building as earthquake-proof.

## Writing standards

### Titles

The public post title should be imaginative, benefit-led and location-aware. It must not reveal the project name unless the user has approved a specific public-name exception.

Example style:

`Contemporary Green Living with Advanced Seismic Protection in Topkapi`

The SEO title should be direct and search-focused.

Example style:

`New Build Apartments for Sale in Topkapi, Istanbul`

### Excerpt

Write one concise sentence summarising property type, location and the strongest verified benefits. Do not repeat the forbidden project name.

### HTML conventions

Rich-text fields should use simple semantic HTML:

- Paragraphs: `<p>...</p>`
- Lists: `<ul><li>...</li></ul>`

Avoid decorative classes unless an existing template specifically requires them.

Plain-text conventions:

- `property_highlights_text`: one concise highlight per line, no bullets. Keep the list short and do not repeat features already shown in the Facilities section.
- `property_faq_text`: `Question?|Answer.` with a blank line between entries. Include no more than five FAQs.
- `estimated_rental_yield`: short value only, and only when defensible

Do not copy typographical or spacing errors from legacy examples.

### Mandatory content limits

- Quick facts: no more than five list items. Each item must be a short sentence of no more than 100 characters.
- Property highlights: keep the list concise and limited to the most important differentiators. Exclude facilities already selected in the `facilities` checkbox field.
- Location and district analysis: no more than two paragraphs, with no more than four sentences in each paragraph.
- Investment and rental potential: no more than two paragraphs, with no more than four sentences in each paragraph.
- Developer and construction credibility: always ignore this section and leave `property_developer_profile` blank.
- FAQs: no more than five question-and-answer pairs.

## Core narrative fields

Populate fields only when applicable and adequately sourced:

| Field | Purpose |
|---|---|
| `project_summary_heading` | Normally `Quick facts` |
| `project_summary` | Maximum five short HTML bullets; each no more than 100 characters |
| `whats_special_heading` | Benefit-led section heading |
| `about_this_project` | Main property description in HTML |
| `location_info_heading` | Location/distances heading |
| `distances` | Verified HTML list of distances or travel times |
| `property_editorial_intro` | Short editorial introduction in HTML |
| `property_highlights_text` | Short list of key differentiators not duplicated in Facilities |
| `property_district_analysis` | Maximum two paragraphs and four sentences per paragraph |
| `property_investment_potential` | Maximum two paragraphs and four sentences per paragraph |
| `estimated_rental_yield` | Only when supported; otherwise leave blank |
| `property_buyer_suitability` | HTML list of suitable buyer profiles |
| `property_developer_profile` | Always leave blank; developer/construction credibility is ignored |
| `property_faq_text` | Maximum five plain-text question/answer pairs |
| `seo_title` | Direct search-focused title |
| `seo_meta_description` | Concise search description |

Always leave `property_developer_profile` blank for both projects and resales.

## V2 units and pricing: mandatory system

Always use `v2_units`. Do not populate legacy pricing fields for new work.

Legacy fields include:

- `price`
- `price_usd`
- `bedrooms_2`
- `size`

Historical posts still contain these values, but current archive filtering, sorting, cards, the single-property template and property SEO use V2 data.

Some legacy code retains fallbacks for old listings. That is not a reason to write legacy fields on new listings.

### V2 ACF keys

| Field | ACF key |
|---|---|
| `v2_units` | `field_695ce53e6547f` |
| `v2_bedrooms` | `field_695ce56365480` |
| `v2_gross_size_min` | `field_695ce59565482` |
| `v2_gross_size_max` | `field_695f9830776c4` |
| `v2_price_usd_min` | `field_695ce5aa65483` |
| `v2_price_usd_max` | `field_695f983c776c5` |
| `v2_index_key` | `field_695ce6b9c6448` |

### New-build projects

Create one row per bedroom type. Each row may contain:

- Bedroom count
- Minimum gross size
- Maximum gross size
- Minimum USD price
- Maximum USD price

Use the current availability/price list, not brochure launch prices. Round sensibly when the user permits it, but do not distort the range.

If room types contain half-room labels such as `3.5+1`, do not invent a decimal bedroom value. Merge or round them into an integer bedroom row only after the user confirms the grouping rule.

### Resale properties

Create exactly one V2 row:

- Enter the bedroom count.
- Put the property size in `v2_gross_size_min`.
- Leave `v2_gross_size_max` blank.
- Put the price in `v2_price_usd_min`.
- Leave `v2_price_usd_max` blank.

The V2 indexer derives the post-level minimum and maximum values and falls missing maximums back to the minimums. Post `59052` confirms this pattern.

After writing `v2_units`, run the existing V2 reindexing logic or save through ACF so these derived fields are correct:

- `v2_price_usd_min`
- `v2_price_usd_max`
- `v2_index_flat`
- Per-row `v2_index_key`

Never hand-build an inconsistent index key.

The current indexer does not create separate post-level gross-size bounds. Validate sizes from the stored repeater rows and validate prices from the derived post meta. Do not fail an otherwise correct import by expecting size meta that the indexer does not produce.

## Structured ACF fields

### Facilities

- Name: `facilities`
- Key: `field_5ebd397220348`

Select only exact allowed values confirmed by the source. Do not assume common facilities.

Known allowed values include, among others:

- `24 7 security`
- `Basketball courts`
- `Child play areas`
- `Commercial space`
- `Gym`
- `Landscaped gardens`
- `Outdoor exercise area`
- `Recreation areas`
- `Underground parking`

Nearby infrastructure is not an on-site facility. For example, do not select `Metro station` merely because a station is nearby.

### Target buyer type

- Name: `target_buyer_type`
- Key: `field_target_buyer_type`

Allowed values:

- `Investor`
- `Family`
- `Luxury Buyer`
- `Citizenship Buyer`
- `Holiday Home Buyer`
- `Second Home Buyer`
- `Retiree`
- `Professional`

### Key advantages

- Name: `property_key_advantages`
- Key: `field_property_key_advantages`

Allowed values:

- `Sea View`
- `Bosphorus View`
- `City Centre`
- `Citizenship Eligible`
- `Metro Access`
- `Luxury Residence`
- `Hotel Residence`
- `Key Ready`
- `Payment Plan`
- `Family Concept`
- `Furnished`
- `Restored Building`

Only select advantages supported by the property facts.

### Other toggles

| Field | ACF key | Rule |
|---|---|---|
| `sold_out` | `field_5f8f36436f5b5` | Select only when confirmed sold out |
| `fp_check_box` | `field_66fea218a88d2` | Enable only when usable floor plans are attached |
| `prices_kd_cb` | `field_678914148ded3` | Enable only when its associated price-list feature is intentionally used |

## Taxonomies

Never create a new term merely because a neighbourhood name appears in the source. First list all existing terms and understand the taxonomy purpose.

### Property type

- `Residential` — ID `85`
- `Apartments` — ID `86`, child of Residential
- `Villas` — ID `88`, child of Residential

Select the parent and applicable child.

### Special

- `Featured` — ID `94`
- `Project` — ID `169`
- `Resales` — ID `180`
- `Special offer` — ID `216`
- `Citizenship` — ID `217`

Rules:

- New build: normally `Project`
- Individual resale: normally `Resales`
- Never assign both `Project` and `Resales`
- `Citizenship` requires explicit confirmation
- `Featured` requires an intentional merchandising decision

### Region

Regions are broad market areas, not individual neighbourhoods. Existing examples include:

- City Centre — ID `28`
- Old City — ID `223`
- West Istanbul — ID `31`
- Maslak — ID `107`
- Bosphorus — ID `26`

Do not create a neighbourhood-level region without explicit approval.

### District

Select both `Istanbul` and the correct district child.

- Istanbul — ID `226`

Verify administrative geography carefully. A neighbourhood may share its name with a separate Istanbul district. For example, Maltepe Mahallesi in Zeytinburnu must not be assigned the Asian-side `Maltepe` district taxonomy.

### Property tags

Use only relevant existing marketing tags. Examples:

- Istanbul Investment property for sale — ID `75`
- Property for sale in Istanbul City Centre — ID `73`
- Property for sale near Old City Istanbul — ID `222`
- Key ready apartments for sale in Istanbul — ID `76`
- Luxury Property for Sale in Istanbul — ID `78`

Do not use view, marina, forest, lake, villa, luxury or key-ready tags without factual support.

## Media

Important ACF keys:

| Field | ACF key |
|---|---|
| `main_image` | `field_5ebd265523532` |
| `main_gallery` | `field_5ebd0fb294ce0` |
| `floor_plans` | `field_5ebd0fc194ce1` |
| `video_file` | `field_66ffc7da4d97b` |

These fields require WordPress Media Library attachment IDs, not Google Drive URLs.

Media workflow:

1. Select and optimise suitable source images.
2. Convert unsupported formats such as TIFF to web-suitable JPEG or WebP.
3. Upload through WordPress so attachment records and metadata exist.
4. Assign one strong lead image to `main_image`.
5. Assign an intentional, non-duplicative sequence to `main_gallery`.
6. Add accurate, descriptive alt text without the forbidden project name.
7. Add floor plans separately and enable `fp_check_box` only after verifying them.
8. Upload property videos as valid WordPress media attachments when using `video_file`.

The standard WordPress featured image may be empty; Property CPT display uses `main_image`.

## Map and location

The `map` field stores a structured ACF Google Map value, normally including:

- Address
- Latitude
- Longitude
- Zoom
- Place ID when available
- Administrative fields

Do not guess coordinates. Use a confirmed address or map pin. For privacy-sensitive resale listings, follow the user-provided level of precision.

## Video

YouTube fields include:

- `YT_video_checkbox`
- `yt_heading`
- `yt_video`

Enable the checkbox only when a valid video URL is present and relevant to that exact listing.

For an uploaded MP4, use the `video_file` ACF field and a valid Media Library attachment. The current single-property template accepts an attachment value and integrates the video into the property media experience. Do not rely on the legacy standalone apartment-tour section or set video toggles without a playable source.

## Translation workflow

Editing English source fields can make existing translations stale. After the English listing passes validation, preview the affected translation queue for the exact post:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php \
  dry-run status=all object_id=PROPERTY_ID limit=5000
```

After reviewing the dry run, translate the missing and stale fields for that post:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php \
  status=all object_id=PROPERTY_ID limit=5000
```

Do not run an unfiltered site-wide translation merely to update one listing. The tool accepts `status=missing|stale|all` and optional `object_type`, `object_id` and `field` filters.

## Validation checklist

Before handoff, verify:

1. Correct Property CPT ID and draft/published status
2. No published post was changed without explicit approval
3. Project-name confidentiality follows the default rule or a documented user-approved exception
4. Title and excerpt are accurate and non-generic
5. Required narrative fields are populated with valid HTML/plain-text conventions
6. Quick facts contain no more than five items and each item is no more than 100 characters
7. Highlights are concise and do not duplicate selected facilities
8. District analysis contains no more than two paragraphs and four sentences per paragraph
9. Investment analysis contains no more than two paragraphs and four sentences per paragraph
10. `property_developer_profile` is blank
11. FAQs contain no more than five question-and-answer pairs
12. No unsupported claims or inferred amenities
13. V2 rows match the current price/availability source
14. Resale maximum size and price fields are blank unless a genuine range exists
15. Derived V2 indexes match the repeater rows
16. Checkbox values exactly match allowed ACF choices
17. Taxonomies reflect administrative geography and listing type
18. `Project` and `Resales` are not both assigned
19. Citizenship, Featured, Key Ready and Sold Out are explicitly justified
20. Main image, gallery and floor plans use valid attachment IDs
21. SEO title and description contain no confidential name; any public compound name is explicitly approved
22. Uploaded video values resolve to playable media or a valid listing-specific URL
23. A published-post update has a verified rollback snapshot covering every changed value
24. Translation dry run was reviewed and the exact post's stale/missing translations were processed when required
25. A new post remains a draft; an approved existing post retains its original status

## Recommended execution pattern

1. Read-only audit of the target post and relevant ACF definitions
2. Present a field mapping and list missing facts
3. Obtain explicit approval before modifying any published post
4. Snapshot every value an approved published-post command may change
5. Complete all preflight validation before the first write
6. Write simple narrative fields
7. Write structured ACF fields using field keys
8. Assign existing taxonomy term IDs
9. Write `v2_units` and run reindexing
10. Assign media attachment IDs
11. Run automated validation and the applicable project-name audit
12. Preview and process translations for the exact post when required
13. Provide a concise visual-review checklist; do not publish a new draft

All importer commands should be idempotent: rerunning them should replace intended values rather than duplicate rows, galleries or taxonomy terms.
