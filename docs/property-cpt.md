# Property CPT, listings, and units

This is the authoritative operating guide for Property CPT content and its public listing surfaces. The repository consumes the `property` post type and its taxonomies but does not register them; confirm the active WordPress/ACF configuration before altering that external contract.

## Data-entry contract

- **`v2_units` is the pricing and unit source of truth.** Legacy `price` and `price_usd` are defunct; never populate, query, or render them.
- Every usable V2 row needs `v2_bedrooms`, `v2_gross_size_min`, and `v2_price_usd_min`. Supply maxima for a real range; otherwise the indexer normalizes the maximum to the minimum.
- For resales, populate bedrooms, minimum size, and minimum price because display and search derive from them.
- Assign the `special` term deliberately. `special=project` displays **“From $MIN”**. `special=resales` displays the fixed price without **“From”**. If both exist, card logic treats it as resale.
- A project name may appear only in `project_name`, never in supporting editorial fields.
- When a supplied Property ID is being updated, edit that existing draft. When no ID is supplied, create a new Property CPT item as a draft pending inspection. Never publish as an implicit side effect.

### Editorial limits

- Keep highlights concise and do not repeat facilities.
- Location & District (`property_district_analysis`) and Investment & Rental (`property_investment_potential`): at most two paragraphs, with at most four sentences per paragraph.
- Do not populate Developer/Credibility (`property_developer_profile`).
- Use at most five FAQs in `property_faq_text` (`Question|Answer`, one per line).
- Use at most five Quick Facts, each no longer than 100 characters.

## Taxonomy and display ownership

Property archive/filter boundaries are `district` (hierarchical location), `region`, `property_type`, `property_tags`, and `special`. `inc/district-ancestors.php` enforces ancestor assignment for districts. Do not substitute taxonomy prose for `project_name`, and do not infer `special` from price shape.

`parts/property-card-v2.php` owns card image, location, special pill/tooltip, project-name admin pill, V2 aggregates, and semantic price mode. `inc/property-card-helpers.php` owns location selection. `single-property.php` owns the standard detail/gallery surface. Archive controls and server markup live in `archive-property.php`; filtered cards are rendered through the same card part.

## V2 indexing and querying

`inc/v2-units-index.php` reindexes on `acf/save_post` for properties. It normalizes reversed/missing ranges, writes each row's `v2_index_key`, and derives:

- `v2_index_flat` (bedroom tokens such as `|1|2|`);
- `v2_price_usd_min` and `v2_price_usd_max` (post-level range);
- the invalidated `pera_v2_price_bounds_v1` transient used by the slider.

Bedrooms must be positive integer strings to enter the bedroom index. Prices/sizes must be positive numeric values to enter aggregates. Do not hand-edit derived meta as a substitute for saving valid `v2_units`; after imports or direct data changes, invoke the same reindex/save path and inspect the derived fields.

`inc/property-archive-query.php` is the shared query builder. It uses AND-combined taxonomy filters, bedroom matching against `v2_index_flat`, range-overlap against derived min/max price meta, and `v2_price_usd_min` for price sorting. `inc/ajax-property-archive.php` parses AJAX inputs, builds counts, and renders refreshed cards.

### SSR/AJAX parity risk

Archive state is gathered in `archive-property.php` for the first response and separately sanitized by the AJAX handler. Both must feed `pera_property_archive_build_args_from_context()` with equivalent district/tag arrays, property type, beds, USD min/max, keyword, taxonomy context, sort, and page. A change to only one path causes initial and refreshed results/counts to disagree. Preserve the `pera_filter_properties_v2` nonce/action contract and ensure selected display currency is converted back to USD before querying; see [currency.md](currency.md).

## Validation

1. Save the property through the ACF path; verify `v2_index_key`, `v2_index_flat`, and derived price extrema.
2. Run the focused card, single-price, archive-currency, and hydration tests under the theme `tests/` directory.
3. Compare a clean SSR archive with its first AJAX refresh using the same filters, sorting, counts, pagination, and taxonomy archive.
4. Check project and resale cards at desktop/mobile sizes and verify the single/gallery, map, favourites, and portfolio surfaces if their shared card/data path changed.
5. Validate translated labels/fields using [multilingual.md](multilingual.md); do not translate structured `v2_units` values. Validate conversion using [currency.md](currency.md).
