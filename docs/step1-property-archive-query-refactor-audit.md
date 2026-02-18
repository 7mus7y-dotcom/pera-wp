# Step 1 Audit — Property Archive Query Helper Refactor

## Scope confirmation
This audit covers **theme files** that query `property` posts and/or render property cards. Step 1 refactor scope is limited to extracting `archive-property.php` query args builder into a helper with zero behavior change.

---

## 1) Files where property posts are queried or rendered

| File | What it does | Step 1 impact |
|---|---|---|
| `wp-content/themes/hello-elementor-child/archive-property.php` | Main property archive template; builds SSR `$args`, runs `WP_Query`, renders archive cards and pagination. | **Will be affected** — this is the file where inline `$args` builder is extracted.
| `wp-content/themes/hello-elementor-child/functions.php` | Forces property archive/taxonomies to use `archive-property.php` and loads `inc/*` helpers. | **Will be affected** — only to include the new helper file.
| `wp-content/themes/hello-elementor-child/inc/ajax-property-archive.php` | AJAX property filter endpoint with separate query builder for async filtering. | **Will NOT be affected** — explicitly out of scope for Step 1.
| `wp-content/themes/hello-elementor-child/inc/property-pagination.php` | Pagination helper used by archive and AJAX endpoint. | **Will NOT be affected** — no query-builder extraction needed.
| `wp-content/themes/hello-elementor-child/inc/property-card-helpers.php` | `pera_render_property_card()` wrapper for property card template part. | **Will NOT be affected** — rendering helper only.
| `wp-content/themes/hello-elementor-child/parts/property-card-v2.php` | Property card markup template. | **Will NOT be affected** — markup changes forbidden.
| `wp-content/themes/hello-elementor-child/home-page.php` | Home featured properties `WP_Query` + card rendering. | **Will NOT be affected** — unrelated home feature query.
| `wp-content/themes/hello-elementor-child/home-page-test.php` | Test variant of home featured property query/rendering. | **Will NOT be affected** — unrelated test/home query.
| `wp-content/themes/hello-elementor-child/single-post.php` | Sidebar “Latest properties” query + property card rendering. | **Will NOT be affected** — posts page aside query is separate.
| `wp-content/themes/hello-elementor-child/single-property.php` | Related properties queries on single property page. | **Will NOT be affected** — explicitly excluded.
| `wp-content/themes/hello-elementor-child/archive/single-property-v2.php` | Archived variant with related properties query/cards. | **Will NOT be affected** — not part of archive args extraction.
| `wp-content/themes/hello-elementor-child/page-favourites.php` | Favorites page property query and rendering. | **Will NOT be affected** — separate favorites flow.
| `wp-content/themes/hello-elementor-child/inc/favourites.php` | Server-side favorites queries and card rendering (AJAX fragments/helpers). | **Will NOT be affected** — separate favorites subsystem.
| `wp-content/themes/hello-elementor-child/header.php` | Logged-in “latest favourites” property query in offcanvas area. | **Will NOT be affected** — header utility query only.
| `wp-content/themes/hello-elementor-child/page-property-map.php` | Property map query (`fields => ids`) for marker generation. | **Will NOT be affected** — map endpoint query is separate.
| `wp-content/themes/hello-elementor-child/inc/crm-client-view.php` | CRM property search AJAX query (`property` + `bodrum-property`). | **Will NOT be affected** — CRM lookup query is separate.
| `wp-content/themes/hello-elementor-child/page-v2-query-test.php` | Diagnostic template for v2 bedrooms query. | **Will NOT be affected** — debugging/test template.
| `wp-content/themes/hello-elementor-child/parts/featured-villa.php` | Loads shared featured property card part. | **Will NOT be affected** — no query logic.
| `wp-content/themes/hello-elementor-child/parts/featured-apartment.php` | Loads shared featured property card part. | **Will NOT be affected** — no query logic.
| `wp-content/themes/hello-elementor-child/parts/home-featured-property.php` | Shared featured property rendering partial. | **Will NOT be affected** — render-only partial.

### Taxonomy archive note (district/property_type/property_tags)
There are no separate property taxonomy templates in this theme for query-building.
Property taxonomy archive requests are routed to `archive-property.php` via `template_include` (`pera_force_property_archive_template()`), so Step 1 extraction in `archive-property.php` covers those contexts too.

### Shortcodes/widgets note
No dedicated shortcode/widget implementation rendering property cards was found in this theme scope for this Step 1 refactor.

---

## 2) Duplicate query-builder logic check

### Direct duplicate of `archive-property.php` args-builder
- **Yes**: `wp-content/themes/hello-elementor-child/inc/ajax-property-archive.php` contains a separate but closely parallel query-arg construction (tax/meta/search/sort/paged) for AJAX responses.

### Other similar (not identical) query logic
- `home-page.php`, `home-page-test.php`, `single-post.php`, `single-property.php`, `archive/single-property-v2.php`, `page-favourites.php`, `inc/favourites.php`, `header.php`, `page-property-map.php`, `inc/crm-client-view.php`, `page-v2-query-test.php` contain independent property queries with different intent and constraints.

**Step 1 action**: do **not** refactor these duplicates/similarities now.

---

## 3) Exact files to change in Step 1

1. `docs/step1-property-archive-query-refactor-audit.md` (this report)
2. `wp-content/themes/hello-elementor-child/inc/property-archive-query.php` (new helper)
3. `wp-content/themes/hello-elementor-child/archive-property.php` (use helper)
4. `wp-content/themes/hello-elementor-child/functions.php` (load helper)

No other file changes are intended.

## Step 1.1 fix addendum

- Helper approach updated to avoid request re-parsing drift: SSR builder now accepts explicit context from `archive-property.php` prelude.
- Prelude remains in template (unchanged source of truth for UI state + slider state inputs).
- Query builder helper is now a true context-based wrap of the SSR args block, preserving zero-drift behavior intent.
