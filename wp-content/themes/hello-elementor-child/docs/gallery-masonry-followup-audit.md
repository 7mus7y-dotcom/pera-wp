# Gallery Masonry Follow-up Audit

## Phase 1 findings (repo-wide selector usage)

Search command used:

- `rg -n "property-gallery-strip|property-gallery-strip__row|property-gallery-strip__item|property-gallery-shell|property-gallery-nav|property-gallery-masonry|property-gallery-thumb" wp-content/themes/hello-elementor-child`

Templates and files using gallery selectors:

- `single-property.php`
  - Uses shell/nav/strip + new masonry/thumb classes.
- `single-bodrum-property.php`
  - Still uses legacy `.property-gallery-strip__row` and `.property-gallery-strip__item` markup in both gallery sections.
- `archive/single-property-v2.php`
  - Still uses legacy `.property-gallery-strip__row` and `.property-gallery-strip__item` markup.
- `css/slider.css`
  - Contains property gallery styling block used by all templates above.

## Risk assessment

- **Risk: Medium**
  - Reason: `slider.css` is shared across multiple templates. Removing or globally changing legacy `.property-gallery-strip__row` / `__item` rules can visually regress `single-bodrum-property.php` and `archive/single-property-v2.php`, which still render legacy markup.

## Scoped follow-up plan

1. Scope all property gallery rules under `.property-gallery ...` to avoid global bleed into other slider patterns.
2. Keep legacy strip row/item styles in place under `.property-gallery` for templates that still rely on legacy markup.
3. Scope masonry-only styles to `.property-gallery .property-gallery-thumbs ...` so the new layout applies only to `single-property.php`.
4. Keep `single-property.php` masonry markup + chevrons, and update inline JS thumb event fallback to a stable event name (`pera:galleryThumbSelect`) with init guard to prevent double-binding.
