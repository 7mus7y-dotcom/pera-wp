# Single Property Gallery Masonry Audit

## Phase 1 audit

- **Gallery image source:** `single-property.php` reads ACF `main_gallery` for the current `property` post and normalizes attachment IDs into `$gallery_ids` / `$photo_count` before rendering the gallery section. No featured-image + attachment fallback is used in this template path.
- **Slider library used for this gallery area:** no Swiper/Splide/Slick initialization is present in this template or theme JS for the single-property gallery strip. The current gallery behavior is native horizontal scrolling with custom chevron controls (inline script: `initGalleryChevronScroll()` in `single-property.php`).
- **Current gallery/thumb markup:** gallery section uses `.property-gallery-shell`, `.property-gallery-nav` (prev/next), and `.property-gallery-strip` containing two rendered rows (`.property-gallery-strip__row`) made of `.property-gallery-strip__item` images.
- **Existing thumbnail click/sync logic:** none in current code. The current JS only scrolls the gallery strip left/right via chevrons.
- **Current CSS styling:** gallery strip and nav styles are in `css/slider.css` under the “Property Gallery” block (`.property-gallery-strip`, row/item styles, nav button styles).

## Smallest-change implementation plan

1. Keep the slider/main media implementation untouched and refactor only the gallery thumbnail markup in `single-property.php`.
2. Replace the two pre-split rows with one thumbnail list container (`.property-gallery-masonry`) inside existing shell/strip wrappers.
3. Use accessible thumbnail `<button>` elements with `data-slide-index` and lazy-loaded images.
4. Add a compact CSS block in `css/slider.css` to create a two-row, horizontally scrollable masonry-like grid (desktop/tablet) and 1-row fallback on mobile.
5. Extend existing inline gallery JS to:
   - preserve chevron horizontal scrolling,
   - wire thumbnail click → existing slider API **if present** (Swiper/Splide/Slick detection),
   - keep `.is-active` thumbnail state synchronized on slider change events.
