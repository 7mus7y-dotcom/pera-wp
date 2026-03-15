# Property Card V2 District Pill Audit

## Scope
Audit only (no implementation) for inconsistent district pill output in `property-card-v2`.

---

## 1) Exact files involved

### Canonical property-card-v2 render path
- `wp-content/themes/hello-elementor-child/parts/property-card-v2.php`
- `wp-content/themes/hello-elementor-child/inc/property-card-helpers.php`

### Entry points that render `property-card-v2`
- SSR archive grid:
  - `wp-content/themes/hello-elementor-child/archive-property.php`
- AJAX archive/filter/load more:
  - `wp-content/themes/hello-elementor-child/inc/ajax-property-archive.php`
- Map card AJAX endpoint:
  - `wp-content/themes/hello-elementor-child/inc/ajax-property-archive.php` (`pera_ajax_get_map_property_card`)
- Favourites:
  - `wp-content/themes/hello-elementor-child/page-favourites.php`
  - `wp-content/themes/hello-elementor-child/inc/favourites.php`
- Related/recommendation contexts using the same card:
  - `wp-content/themes/hello-elementor-child/single-property.php`
  - `wp-content/themes/hello-elementor-child/archive/single-property-v2.php`
  - `wp-content/themes/hello-elementor-child/single-post.php`
- Other card surfaces:
  - `wp-content/themes/hello-elementor-child/home-page.php`
  - `wp-content/themes/hello-elementor-child/home-page-test.php`
  - `wp-content/themes/hello-elementor-child/page-portfolio-token.php`

### Related competing district/region term logic
- Deepest-term helper + ancestor enforcement:
  - `wp-content/themes/hello-elementor-child/inc/district-ancestors.php`
- Single property template (uses deepest district):
  - `wp-content/themes/hello-elementor-child/single-property.php`
- Special offers card-like output (uses deepest district first):
  - `wp-content/themes/hello-elementor-child/parts/home-special-offers.php`
- SEO helper (uses deepest district first):
  - `wp-content/themes/hello-elementor-child/inc/seo-property.php`
- Legacy archive card template (inline first-term logic):
  - `wp-content/themes/hello-elementor-child/parts/_archive/property-card.php`
- Home featured property template (inline first-term logic):
  - `wp-content/themes/hello-elementor-child/parts/home-featured-property.php`

---

## 2) Exact functions / template parts involved

- `pera_render_property_card(array $args = array()): void`
  - always includes `parts/property-card-v2`
- `parts/property-card-v2.php`
  - computes pills inline
- `pera_get_deepest_term(int $post_id, string $taxonomy): ?WP_Term`
  - generic deepest-term resolver
- `pera_enforce_district_ancestors(...)`
  - ensures district ancestors are assigned to property posts

---

## 3) Root cause analysis

### Primary defect in `property-card-v2`
`property-card-v2` resolves district with:

- `get_the_terms( $post_id, 'district' )`
- then picks `$district_terms[0]`

It does **not** call `pera_get_deepest_term()`.

That means the rendered district depends on whichever term appears first in the returned array, not on hierarchy depth.

### Why it appears “inconsistent”
The project enforces assigning ancestors for district terms (`pera_enforce_district_ancestors`), so a property often has both parent and child terms assigned (e.g. `Istanbul` + `Şişli`).

If rendering chooses the first returned term, output can show parent or child depending on returned order (and no explicit sorting/tie-breaker is applied in the card template).

### Multiple competing implementations amplify inconsistency
Some places use deepest-term logic, some use first-term logic:

- deepest logic used:
  - `single-property.php`
  - `parts/home-special-offers.php`
  - `inc/seo-property.php`
- first-term logic used:
  - `parts/property-card-v2.php`
  - `parts/home-featured-property.php`
  - `parts/_archive/property-card.php`

So the same property can show different district labels across different surfaces.

---

## 4) Bug classification

- **Inconsistent helper usage:** Yes.
- **Flawed deepest-term logic in card path:** Yes (`property-card-v2` does not apply deepest resolution).
- **Unsorted term arrays / order assumptions:** Yes (`[0]` selection assumes order is meaningful).
- **Cache/render-path inconsistency:** Not primary.
  - Major SSR/AJAX/map/favourites paths converge to `property-card-v2`, so most inconsistencies come from term-picking logic itself.
  - No dedicated card fragment/transient/precomputed district-label cache was found in card render paths.
- **Other cause:** ancestor auto-assignment increases frequency of parent+child co-assignment, exposing the first-term bug more often.

---

## 5) Recommended fix plan (do not implement yet)

1. **Establish a canonical location-pill helper** in `inc/` to resolve district/region for cards.
2. **Use `pera_get_deepest_term($post_id, 'district')`** as district source for card contexts.
3. **Refactor `parts/property-card-v2.php`** to use helper output only (remove inline first-term district selection).
4. **Consolidate duplicated logic** in:
   - `parts/home-featured-property.php`
   - `parts/_archive/property-card.php` (if still active)
   - optionally align `inc/seo-property.php` and `parts/home-special-offers.php` to same helper.
5. **Define explicit tie-break rule** when multiple district terms have equal depth (e.g., primary term meta, else deterministic fallback).
6. **Leave region logic separate unless multi-region assignment is expected**, then add explicit primary-region rule.

---

## Determinism statement

- For a given code path that always picks index `0`, behavior is **deterministic-but-wrong** relative to requirement.
- Across the application, behavior is **genuinely inconsistent** because there are multiple render paths with competing district-resolution strategies.
