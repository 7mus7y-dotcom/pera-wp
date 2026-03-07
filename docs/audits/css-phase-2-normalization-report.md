# CSS Phase 2 Normalization Report

## Scope
- Phase 2 targeted medium-risk, cascade-safe normalization in active CSS sources, focusing on repeated selector families and repeated breakpoint groups without redesign.
- Unlike Phase 1 (exact-duplicate and token-safe replacements), this pass allowed controlled in-file consolidation where intent and source-order behavior were clearly preserved.

## Confirmed Active Files and Load Order
- Core global stylesheet is enqueued first on all front-end pages:
  - `wp-content/themes/hello-elementor-child/css/main.css` (`pera-main-css`).
- `slider.css` is conditionally enqueued with dependency on `pera-main-css`, so it loads after `main.css` where enabled.
- `property.css` is conditionally enqueued with dependency on `pera-main-css`, so it also loads after `main.css`.
- `property-card.css`, `blog.css`, and `posts.css` are conditionally enqueued with dependency chains that can include `slider.css` when `$needs_slider` is true.
- Additional adjacent active files in overlapping contexts:
  - `property-card.css` overlaps with `property.css` on home/single property/favourites and via property archive helper.
  - `blog.css` and `posts.css` overlap in blog page, single post, and blog archives.
  - `crm.css` is enqueued only in CRM routes.
  - `login.css` is enqueued only on wp-login; `login-custom.css` was inspected as adjacent drift context but not found in enqueue logic.

## Changes Made

### Selector-family consolidation
1. **`wp-content/themes/hello-elementor-child/css/slider.css`**
   - **Selectors:** `.property-gallery-strip`, `.property-gallery-strip::-webkit-scrollbar`, `.property-gallery-strip__row`, `.property-gallery-strip__item img`
   - **What changed:** Merged duplicate later definitions into the earlier property-gallery base block and removed repeated duplicate selector blocks.
   - **Why cascade-safe:** The declarations were semantically identical in effective value (or already overwritten by later same-value declarations); consolidating into the first occurrence preserved final computed styles.
   - **Visual neutrality:** Intended to be visually neutral; no style target values were changed beyond replacing duplicated max-height/height split with the already-existing canonical row-height variable declaration.

2. **`wp-content/themes/hello-elementor-child/css/property.css`**
   - **Selector:** `.property-pagination`
   - **What changed:** Unified centering declarations (`width`, `display`, `justify-content`) into the first `.property-pagination` block and removed the later duplicate block.
   - **Why cascade-safe:** Same selector specificity and same declarations; moving into earlier block does not alter later overrides for descendants.
   - **Visual neutrality:** Intended to be visually neutral (no value changes).

### Media-query consolidation
1. **`wp-content/themes/hello-elementor-child/css/slider.css`**
   - **Media query:** `@media (max-width: 640px)`
   - **What changed:** Combined two adjacent same-breakpoint blocks into a single `@media (max-width: 640px)` block.
   - **Why cascade-safe:** Both blocks had non-conflicting selectors and were already contiguous; merged block preserves selector order and specificity behavior.
   - **Visual neutrality:** Intended to be visually neutral.

### Exact-equivalence token normalization
- No literal-to-token substitutions were made in this phase. Existing literals were retained unless exact, active-cascade equivalence could be proven.

### Overlap/drift cleanup
- Inspected overlap between:
  - `property.css` ↔ `property-card.css`
  - `main.css` ↔ `posts.css` / `cards-post.css`
  - `blog.css` archive/category adjacent selectors
- No cross-file normalization was applied because observed overlaps appeared context-dependent or not provably accidental without higher risk.

### `!important` cleanup
- No `!important` removals were made in this phase due to unresolved specificity-risk in active template contexts.

## Token Verification Notes
- No token replacements were applied in Phase 2, so no token-equivalence entries were required.

## Changes Deliberately Not Made
- Did not merge breakpoint groups across files (out of scope).
- Did not perform broad consolidation in `main.css` due to high volume of repeated breakpoints with potential source-order sensitivity across unrelated components.
- Did not consolidate overlapping component rules between `property.css` and `property-card.css` where ownership appeared intentionally split by template context.
- Did not remove `!important` flags in archive and pagination-related selectors without full route-level specificity audit.

## Risk Notes
- Touched areas were limited to selector duplicate consolidation and adjacent same-breakpoint merge in `slider.css` plus local duplicate family cleanup in `property.css`.
- Primary cascade-sensitive area touched: property gallery strip/image sizing declarations in `slider.css`.
- Recommended visual QA focus:
  - property gallery two-row strip behavior on mobile and tablet
  - property archive pagination centering and load-more alignment

## Suggested Phase 3 Topics
- High-risk consolidation opportunities in `main.css` where repeated `@media (max-width: 768px)` clusters are dispersed across many component sections.
- Route-specific specificity audit before any `!important` removals.
- Controlled ownership mapping for overlapping card primitives between `property.css`, `property-card.css`, `posts.css`, and `cards-post.css` prior to any cross-file normalization.
