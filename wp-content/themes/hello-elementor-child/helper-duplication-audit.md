# Helper Duplication Audit

> Scope scanned: inc/seo-*.php, archive-property.php, single-property.php, parts/property-card*.php, inc/ajax-property-archive.php, inc/v2-units-index.php. Evidence uses repo-wide `rg` results captured below.

## Evidence snapshots (rg)
```
rg -n "function\s+pera_|pera_" inc archive-property.php single-property.php parts -S
rg -n "pera_get_.*archive_heading|archive_heading|pre_get_document_title|wp_head|wp_robots" inc/seo-*.php archive-property.php single-property.php parts -S
```

### Key findings (clusters)

#### 1) Archive heading/title formatting
- **Files involved:** `inc/seo-helpers.php`, `inc/seo-property-archive.php`, `archive-property.php`.
- **Functions/blocks:** `pera_get_district_archive_heading`, `pera_get_region_archive_heading`, `pera_get_property_tags_archive_heading`, plus corresponding title helpers used by SEO title filters.
- **Why duplicate:** The same location/title formatting logic is used for both template heading output and SEO title filters.
- **Risk of unifying:** Low.
- **Suggested single source of truth:** `inc/seo-helpers.php` (already hosts these helpers).

#### 2) Canonical + filtered-archive detection
- **Files involved:** `inc/seo-property-archive.php`, `inc/seo-all.php`, `archive-property.php`.
- **Functions/blocks:** `pera_property_archive_is_filtered_request` vs `pera_is_filtered_property_archive`, plus canonical building logic in both SEO modules; `archive-property.php` uses `pera_property_archive_base_url()` when available.
- **Why duplicate:** Both SEO modules implement similar “filtered archive detection” and canonical building logic.
- **Risk of unifying:** Medium (SEO output/edge cases).
- **Suggested single source of truth:** `inc/seo-helpers.php` or new `inc/seo-archive-helpers.php`.

#### 3) Social/OG/Twitter meta assembly
- **Files involved:** `inc/seo-property.php`, `inc/seo-all.php`.
- **Functions/blocks:** `pera_property_get_meta_description` vs `pera_seo_all_get_description`, image lookups (`pera_property_get_social_image` vs `pera_seo_all_get_image`), plus repetitive meta-tag output blocks.
- **Why duplicate:** Similar composition steps and output of meta tags, with slightly different data sources.
- **Risk of unifying:** Medium (different contexts and outputs).
- **Suggested single source of truth:** `inc/seo-meta-helpers.php` (new).

#### 4) District/term resolution helpers
- **Files involved:** `inc/seo-property.php`, `inc/district-ancestors.php`, `single-property.php`.
- **Functions/blocks:** `pera_property_get_district_name` in SEO module; `pera_get_deepest_term` in district-ancestors; `single-property.php` uses `pera_get_deepest_term`.
- **Why duplicate:** Multiple places resolve “deepest district/term” logic for single property context.
- **Risk of unifying:** Low/Medium (taxonomy rules).
- **Suggested single source of truth:** `inc/property-helpers.php` or `inc/district-helpers.php`.

#### 5) Units selection + aggregation logic
- **Files involved:** `inc/v2-units-index.php`, `single-property.php`, `parts/home-featured-property.php`.
- **Functions/blocks:** `pera_v2_get_selected_unit`, `pera_v2_units_aggregate_by_beds`, `pera_v2_units_format_price_text`, `pera_v2_units_aggregate`, plus template-side gating (`function_exists` checks).
- **Why duplicate:** Template code repeatedly re-implements selection/aggregation flows around the same unit helpers.
- **Risk of unifying:** Medium (template behavior).
- **Suggested single source of truth:** `inc/units-helpers.php` (new).

#### 6) Price formatting/price bounds
- **Files involved:** `inc/v2-units-index.php`, `inc/ajax-property-archive.php`, `archive-property.php`, `parts/home-featured-property.php`.
- **Functions/blocks:** `pera_v2_get_price_bounds`, `pera_v2_units_format_price_text`, `pera_v2_units_format_size_text`, plus archive and AJAX usage.
- **Why duplicate:** Similar price bound retrieval/formatting used in archive UI, AJAX responses, and template parts.
- **Risk of unifying:** Medium (UI formatting differences).
- **Suggested single source of truth:** `inc/units-helpers.php` or `inc/property-formatting.php`.

#### 7) Property card data preparation
- **Files involved:** `archive-property.php`, `inc/ajax-property-archive.php`, `parts/property-card-v2.php`, `parts/_archive/property-card.php`.
- **Functions/blocks:** repeated `set_query_var( 'pera_property_card_args', … )` and render flows.
- **Why duplicate:** Multiple places construct nearly identical `pera_property_card_args` before rendering card partials.
- **Risk of unifying:** Medium.
- **Suggested single source of truth:** `inc/property-card-helpers.php` or a shared `parts/property-card-args.php`.

## Notable notes
- The compatibility alias `pera_v2_get_units()` is declared in `inc/v2-units-index.php` and only referenced internally there; no external references were found in the scan.
- `inc/_archive/published-term-counts.php` appears unused outside its own definition.
