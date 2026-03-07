# CSS Phase 1 Normalization Report

## Scope
- Phase 1 covered **safe normalization only** in active CSS sources: duplicate cleanup where clearly redundant, and token-aligned normalization for repeated radius/shadow/color literals.
- This pass intentionally avoided redesign, layout refactors, breakpoint architecture changes, and selector-system rewrites.

## Confirmed Active Files
- `wp-content/themes/hello-elementor-child/css/main.css`
- `wp-content/themes/hello-elementor-child/css/property.css`
- `wp-content/themes/hello-elementor-child/css/slider.css`

### Load / enqueue confirmation
- `main.css` is enqueued globally (`pera-main-css`) for all front-end pages.
- `slider.css` is conditionally enqueued (`pera-slider-css`) and depends on `pera-main-css`.
- `property.css` is conditionally enqueued (`pera-property-css`) and depends on `pera-main-css`.
- On templates that load all three, effective order is `main.css` first, then `slider.css`, then `property.css` (by enqueue sequence/dependencies).
- `theme-helpers.php` also enqueues `property.css` for property archives via helper (`pera_enqueue_property_archive_assets`).
- `home-page-test-assets.php` enqueues `slider.css` and `property.css` for the temporary `home-page-test.php` template.

### Active baseline conclusion
- These three baseline files are actively enqueued and are still source-of-truth CSS for their targeted views.
- No superseding compiled bundle was found replacing them as the live front-end source.

### Adjacent overlap check
- `property-card.css` is actively enqueued alongside `property.css` on several contexts, but selector overlap with `property.css` appears minimal and component boundaries are mostly separate.
- Additional adjacent overlap found in `blog.css` with selectors also present in primary CSS (`.archive-cat-btn`, `.archive-cat-card`, `.archive-cat-meta`, `.archive-cats-title`). Left unchanged in this pass.

## Changes Made

### 1) Duplicate selector cleanup
1. **File:** `wp-content/themes/hello-elementor-child/css/slider.css`
   - **Selector changed:** `.property-gallery-strip__item`
   - **What changed:** Removed a later duplicate selector block whose declarations were already defined earlier in the same file.
   - **Why low risk:** Removed only exact repeated declarations (`display`, `padding`, `border-radius`, `overflow`) while preserving unique declarations from the earlier block (`scroll-snap-align`).

### 2) Radius normalization (token-aligned)
1. **File:** `wp-content/themes/hello-elementor-child/css/main.css`
   - **Selector changed:** `.skip-link`
   - **What changed:** `border-radius: 4px;` → `border-radius: var(--radius-xs, 4px);`
   - **Why low risk:** Exact fallback match keeps rendered value unchanged.

2. **File:** `wp-content/themes/hello-elementor-child/css/property.css`
   - **Selectors changed:**
     - `.filter-price__slider input[type="range"]::-webkit-slider-runnable-track`
     - `.filter-price__slider input[type="range"]::-moz-range-track`
     - `.filter-price__slider::before`
   - **What changed:** `border-radius: 4px;` → `border-radius: var(--radius-xs, 4px);`
   - **Why low risk:** Existing token fallback preserves same radius.

3. **File:** `wp-content/themes/hello-elementor-child/css/property.css`
   - **Selector changed:** `.property-sort__pills .sort-pill`
   - **What changed:** `border-radius: 999px;` → `border-radius: var(--pill-radius, 999px);`
   - **Why low risk:** Uses already-established pill token with identical fallback value.

4. **File:** `wp-content/themes/hello-elementor-child/css/slider.css`
   - **Selectors changed:**
     - `.cards-slider::-webkit-scrollbar-thumb`
     - `.property-gallery-nav`
   - **What changed:** `border-radius: 999px;` → `border-radius: var(--pill-radius, 999px);`
   - **Why low risk:** Existing token convention already used in theme; fallback preserves shape.

### 3) Shadow normalization (token-aligned)
1. **File:** `wp-content/themes/hello-elementor-child/css/property.css`
   - **Selector changed:** `.filters-enhanced .property-filter-dialog__panel`
   - **What changed:** `box-shadow: 0 10px 30px rgba(15, 23, 42, 0.35);` → `box-shadow: var(--glass-shadow-strong, 0 10px 30px rgba(15, 23, 42, 0.35));`
   - **Why low risk:** Token fallback is an exact value match with current visual output.

### 4) Color normalization (token-aligned)
1. **File:** `wp-content/themes/hello-elementor-child/css/property.css` (dark-mode block)
   - **Selectors changed:**
     - `.property-filter-dialog__close`
     - `.property-filter-dialog__close:hover`
     - `.property-filter-dialog__close:focus`
     - `.property-filter-dialog__close:focus-visible`
   - **What changed:** `#ffffff` → `var(--inverse, #ffffff)` for `color` and `border-color`.
   - **Why low risk:** Theme already uses `--inverse` token for white/inverse text; fallback remains white.

### 5) Breakpoint cleanup
- Breakpoint values were audited in `main.css`, `property.css`, `slider.css`.
- No media-query merges were applied in this phase because potential grouping could alter cascade/source-order behavior.
- Breakpoints left intact for Phase 2 planning.

## Overlap Observations
- `property-card.css` vs `property.css`:
  - Both are active in related contexts, but selectors are mostly distinct (`.property-card__*` in `property-card.css` vs archive/filter/hero patterns in `property.css`).
  - No clearly safe exact duplicate selector cleanup was identified across these two files in this pass.
- `blog.css` overlap with primary files:
  - Overlap observed for `.archive-cat-btn`, `.archive-cat-card`, `.archive-cat-meta`, `.archive-cats-title`.
  - Intentionally left unchanged due to potential context/cascade sensitivity.
- `cards-post.css`, `posts.css`, `crm.css`, `login.css`, `login-custom.css`:
  - Reviewed for overlap signals; no clearly safe exact-duplicate removals were applied in this pass.

## Changes Deliberately Not Made
- Did not merge near-duplicate selector blocks with differing declarations in `property.css` and `slider.css` where declaration intent appears iterative/override-based.
- Did not consolidate repeated media queries across files due to cascade-order risk.
- Did not refactor broader color/radius/shadow literals where no exact existing token equivalence was clear.
- Did not edit adjacent secondary files despite overlap signals to keep Phase 1 low-risk and scoped.

## Risk Notes
- Mild cascade sensitivity remains in sections with repeated selector blocks that intentionally layer behavior (especially slider/gallery and property filters).
- Token substitutions here all include literal fallbacks, reducing visual drift risk.
- Watch areas:
  - property filter dialog panel styling and dark-mode close-button state
  - property gallery strip item rendering in single-property contexts

## Suggested Next Phase
- Phase 2 should target medium-risk cleanup:
  - map intentional vs accidental repeated selector patterns,
  - consolidate duplicate media-query groups within each file where source order can be preserved,
  - resolve known cross-file overlap in blog/category card selectors with template-aware testing.
