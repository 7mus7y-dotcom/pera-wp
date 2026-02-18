# Step 2 Audit — AJAX archive refactor to shared query builder

## Scope
- AJAX endpoint file audited: `wp-content/themes/hello-elementor-child/inc/ajax-property-archive.php`
- SSR baseline compared: `wp-content/themes/hello-elementor-child/archive-property.php`
- Shared builder baseline compared: `wp-content/themes/hello-elementor-child/inc/property-archive-query.php`

## 1) AJAX handler + helper mapping

### Main AJAX action handler
- **Function:** `pera_ajax_filter_properties_v2()`.
- **Registered actions:**
  - `wp_ajax_pera_filter_properties_v2`
  - `wp_ajax_nopriv_pera_filter_properties_v2`
- **Refs:** `ajax-property-archive.php` lines 160–163 and 626–627.

### Helper functions / filters used by handler
- `pera_v2_filter_array_of_slugs($raw)` — sanitizes slug arrays, but currently only processes arrays (scalar becomes empty array). Ref: lines 31–39.
- `pera_v2_parse_beds_from_index(string $idx)` — parses `|1|2|...|` meta index for bedroom facet counts. Ref: lines 41–61.
- `pera_v2_add_term_counts_for_posts(array $post_ids, string $taxonomy)` — computes taxonomy facet counts from ID set. Ref: lines 63–101.
- Keyword search extension filters gated by query var `pera_kw_project`:
  - `posts_join` filter. Ref: lines 107–121.
  - `posts_search` filter. Ref: lines 123–145.
  - `posts_distinct` filter. Ref: lines 147–153.

## 2) Request payload parsing in AJAX today

Inside `pera_ajax_filter_properties_v2()` the handler currently consumes:

- `paged` -> int >=1. Ref: line 169.
- `v2_beds` -> absint scalar (0 = no beds filter). Ref: lines 172–175.
- `district` -> array slugs only (scalar currently dropped). Ref: line 178 + helper lines 31–39.
- `property_tags` -> array slugs only (scalar currently dropped). Ref: line 179 + helper lines 31–39.
- `property_type` -> single slug. Ref: lines 182–185.
- `sort` -> allowed: `date_desc`, `date_asc`, `price_asc`, `price_desc`; default `date_desc`. Ref: lines 188–195.
- `min_price` / `max_price` -> numeric floats from non-empty strings; empty string means no filter. Ref: lines 197–209.
- Reversed min/max swap when both >0. Ref: lines 211–216.
- `s` keyword -> sanitized text, trimmed. Ref: lines 218–224.
  - Numeric keyword detected and used as post ID (`p`). Ref: lines 226–227 + 417–425.
- `archive_taxonomy` + `archive_term_id` -> optional validated taxonomy context for property tax archives. Ref: lines 229–249.
- `pera_debug` -> enables debug HTML for admin-equivalent users. Ref: line 251 and 580–588.
- `archive_base` -> used only for pagination base URL construction. Ref: lines 551–575.

## 3) How AJAX currently builds and executes queries

### Primary grid query args
- Starts from base args:
  - `post_type=property`, `post_status=publish`, `posts_per_page=12`, `paged=$paged`.
  - Ref: lines 297–302.
- Builds `tax_query` with relation AND:
  - district slug IN, archive taxonomy term_id, property_tags slug IN, property_type slug.
  - Ref: lines 306–343.
- Builds `meta_query` relation AND:
  - beds token on `v2_index_flat` LIKE `|N|`.
  - price overlap (`v2_price_usd_max >= min`, `v2_price_usd_min <= max`).
  - Ref: lines 345–415.
- Applies keyword:
  - `p` for numeric keyword.
  - else `s` and optional `pera_kw_project=1` for admin-equivalent.
  - Ref: lines 417–426.
- Applies sort switch:
  - `price_*` sorts via `meta_key=v2_price_usd_min`, `orderby=meta_value_num`, order asc/desc.
  - `date_*` sorts via `orderby=date`.
  - Ref: lines 360–384.

### Facet query
- Copies `$args` into `$facet_args`, sets `posts_per_page=-1`, `paged=1`, `fields=ids`, `no_found_rows=true`, and unsets ordering/meta_key.
- Runs `WP_Query($facet_args)` and truncates IDs to max 2000.
- Ref: lines 432–455.

### Grid rendering
- Runs `WP_Query($args)`.
- Loops results rendering with `pera_render_property_card($card_args)` fallback to `get_template_part('parts/property-card-v2')`.
- No results fallback HTML: `<p class="no-results">No properties found.</p>`.
- Ref: lines 483–513.

### Pagination HTML + count
- `count_text` derives from `$q->found_posts` as `"{n} properties found"`.
- Pagination built with `pera_render_property_pagination($q, $paged, $add_args, $pagination_base)`.
- `add_args` includes active filters and excludes default sort `date_desc`.
- Ref: lines 515–578 and 592.

## 4) Current JSON response shape (exact keys)

Current `wp_send_json_success()` payload keys:

1. `grid_html` (string)
2. `count_text` (string)
3. `has_more` (bool)
4. `next_page` (int|null)
5. `district_counts` (object/map)
6. `bedroom_counts` (object/map)
7. `tag_counts` (object/map)
8. `property_type_counts` (object/map)
9. `pagination_html` (string)
10. `max_pages` (int)
11. `current_page` (int)
12. `debug_html` (string)
13. `price_bounds` (object) with keys:
   - `global_min` (int)
   - `global_max` (int)
   - `applied_min` (int)
   - `applied_max` (int)

Ref: lines 590–613.

## 5) AJAX vs SSR prelude comparison (drift analysis)

Compared with SSR prelude in `archive-property.php` lines 24–159.

### Shared/intended parity already present
- Same sort key vocabulary + default `date_desc`.
- Same keyword numeric-post-ID behavior.
- Same price overlap semantics using `v2_price_usd_min/max`.
- Same taxonomy filters (district, property_tags, property_type, taxonomy context).

### Differences likely intentional
- AJAX has `archive_taxonomy`/`archive_term_id` from payload to emulate term archive context; SSR gets taxonomy context from queried object helper. (`archive-property.php` lines 82–84 vs AJAX lines 229–249.)
- AJAX computes facet counts (`district_counts`, `tag_counts`, etc.) via a separate unpaged IDs query; SSR template does not do this in the same stage.
- AJAX constructs `pagination_base` from `archive_base`/referrer/current request to regenerate links post-filter.

### Differences that are drift risks
- **Scalar taxonomy payload handling drift:** SSR accepts scalar OR array for district/tags (`archive-property.php` lines 46–64); AJAX helper currently drops scalar values (`ajax-property-archive.php` lines 31–39).
- **Args source drift risk:** SSR now uses shared builder (`archive-property.php` line 159), while AJAX still hand-builds args (lines 297–426).
- **Default query flags drift risk:** shared builder currently includes `update_post_meta_cache=false` and `update_post_term_cache=false` (`property-archive-query.php` lines 53–54), while AJAX hand-built args currently omit those keys.

## 6) Refactor target summary
- Refactor AJAX query arg construction to call `pera_property_archive_build_args_from_context($ctx, $overrides)`.
- Keep all AJAX-specific output behavior unchanged:
  - same rendered card HTML path,
  - same pagination helper + structure,
  - same count/facet/debug/price fields and JSON key shape.
- Add low-risk future hook for portfolio scope using optional `portfolio_post__in` payload -> pass via builder overrides.
