# Bodrum Gallery Compatibility Audit

## Phase 1 audit

Searches used:

- `rg -n "property-gallery|property-gallery-shell|property-gallery-strip|property-gallery-strip__row|property-gallery-strip__item|property-gallery-nav|property-gallery-thumbs|property-gallery-masonry|property-gallery-thumb" wp-content/themes/hello-elementor-child/single-bodrum-property.php`
- `rg -n "single-bodrum-property|property-interior-gallery|#gallery|property-gallery-shell|property-gallery-strip|property-gallery-nav--prev" wp-content/themes/hello-elementor-child/css wp-content/themes/hello-elementor-child/js wp-content/themes/hello-elementor-child/single-bodrum-property.php`

## Gallery instances found in `single-bodrum-property.php`

1. **Main gallery** (`#gallery`)
   - Wrapper: `<section class="section property-gallery" id="gallery">`
   - Contains: `.property-gallery-shell`, `.property-gallery-nav` (`--prev/--next`), `.property-gallery-strip`
   - Markup type: legacy rows/items (`.property-gallery-strip__row`, `.property-gallery-strip__item`) via `$render_gallery_row`
   - Masonry classes present: **no** (`property-gallery-thumbs`, `property-gallery-masonry`, `property-gallery-thumb` not used)

2. **Interior gallery** (`#property-interior-gallery`)
   - Previous wrapper: `<div class="section" id="property-interior-gallery">` (**missing `.property-gallery` ancestor**)
   - Contains: `.property-gallery-shell`, `.property-gallery-nav` (`--prev/--next`), `.property-gallery-strip`
   - Markup type: legacy rows/items (`.property-gallery-strip__row`, `.property-gallery-strip__item`) via `$render_gallery_row`
   - Masonry classes present: **no**

## Why breakage happens under scoped `slider.css`

`slider.css` now scopes gallery rules under `.property-gallery ...`. The main Bodrum gallery already had `.property-gallery`, but the interior gallery did not. As a result, the interior gallery shell/strip/nav/legacy-row rules would not match, causing layout and controls to lose expected styling.

## Minimal planned fix

- Add `.property-gallery` to the interior gallery wrapper only:
  - from `<div class="section" id="property-interior-gallery">`
  - to `<div class="section property-gallery" id="property-interior-gallery">`
- Keep legacy Bodrum gallery markup and local JS as-is (it already loops with `querySelectorAll('.single-bodrum-property .property-gallery-shell')` and initializes both gallery shells).
- Do not add masonry classes to Bodrum galleries.

## Repo-wide Bodrum-specific CSS/JS check

- `css/main.css` contains `.single-bodrum-property` rules for hero/layout elements only; no masonry thumb selector usage for Bodrum galleries.
- No global `js/*.js` file was found targeting Bodrum gallery selectors; gallery chevron behavior is handled locally in `single-bodrum-property.php`.
- Local Bodrum script already initializes **all** gallery shells via `querySelectorAll('.single-bodrum-property .property-gallery-shell')`.

## Applied patch

- Added `.property-gallery` to the interior gallery wrapper so scoped `.property-gallery ...` rules apply to both Bodrum galleries.
- Added a lightweight per-shell init guard (`data-gallery-init="1"`) in the local Bodrum gallery script to prevent accidental duplicate event binding.
- Kept legacy row/item markup and avoided masonry classes in Bodrum template.
