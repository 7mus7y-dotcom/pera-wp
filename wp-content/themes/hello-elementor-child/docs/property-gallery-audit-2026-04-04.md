# Property Single Gallery Audit (2026-04-04)

Scope: `single-property.php` gallery only (+ directly related CSS/PHP wiring), with supporting references to the mirrored `archive/single-property-v2.php` implementation.

## 1) Files that control the current gallery

### Markup + data prep
- `single-property.php`
  - ACF gallery source (`main_gallery`) is read and normalized into `gallery_ids`.
  - Gallery rows are hard-coded into **two alternating rows** (`$row1`, `$row2`) by index parity.
  - Markup renders nav chevrons and a strip wrapper with two `.property-gallery-strip__row` containers.
  - Inline script binds chevron buttons and scrolls the strip horizontally.

### Styling
- `css/slider.css`
  - `.property-gallery-strip` is a horizontal scroller configured as a **column flex container** (so each row is its own horizontal track).
  - Gallery image height is fixed with `height: var(--gallery-row-h, 220px)` and `width: auto`.
  - Nav chevrons are absolutely positioned overlays.
  - On `min-width: 768px`, strip changes to `overflow-x: visible`.

### Asset wiring
- `inc/modules/enqueue-assets.php`
  - `slider.css` is enqueued on single property pages via `$needs_slider`.

### Additional context
- `archive/single-property-v2.php` carries the same gallery pattern and near-identical script as `single-property.php`.

## 2) How the current gallery works (and where dual-row comes from)

The dual-row behavior is a **combination** of:
1. **PHP markup logic** splitting IDs into alternating rows (hard-coded): first row gets even indexes, second row odd indexes.
2. **CSS layout** making `.property-gallery-strip` a vertical stack of two horizontal rows.
3. **JS enhancement** only for chevron-triggered `scrollBy()`.

So the implementation is not purely visual; the two-row structure is explicitly created in template PHP and then styled as two strips.

## 3) Why it feels clumsy on mobile

Observed causes in current implementation:

1. **Two-row fragmentation**
   - Mobile users browse left/right, but content is split across two independent horizontal rows; sequence becomes visually discontinuous.

2. **Weak scan hierarchy**
   - All images have equal visual weight and there is no clear “current” or “primary” image.

3. **Tap/gesture ambiguity**
   - Large overlay chevrons sit on top of content, while native horizontal scrolling is also available.

4. **Fixed-height + auto-width images**
   - `height: 220px; width: auto; max-width: 80vw` yields variable card widths, inconsistent rhythm, and uneven perceived spacing while swiping.

5. **Desktop overflow mode flip**
   - `overflow-x: visible` at tablet+ can remove obvious affordance that this is a scrollable strip, increasing discoverability friction.

6. **No explicit snap consistency per image card size**
   - Snap exists but mixed width cards reduce predictable stepping.

## 4) Option assessment

## Option A — single-row horizontal swipe, scroll-snap, no JS
Pros:
- Lightest implementation (native scroll only).
- Directly aligns with current classes and existing strip concept.
- Best continuity for image browsing speed.

Cons:
- No larger “focus” image; all items are peers.

Verdict: **Strong baseline and easiest migration path**.

## Option B — one large primary image + thumbnail strip
Pros:
- Strong hierarchy; often better for “inspect details first” behavior.
- Familiar real-estate UX pattern.

Cons:
- Pure no-JS can be done with anchor targets/radio hacks but becomes brittle and verbose.
- Minimal JS is typically needed for smooth thumb->main sync and accessibility state updates.

Verdict: Best UX in many cases, but not the lightest in this codebase if JS-avoidance is a hard requirement.

## Option C — stacked mobile gallery, enhanced desktop
Pros:
- No swipe complexity on mobile.
- Very robust and simple.

Cons:
- Loses quick browse velocity; long vertical scroll and page-height inflation.

Verdict: Good fallback, but weaker for rapid photo browsing compared with A.

## 5) Recommended direction for this codebase

**Recommend Option A** with a pragmatic enhancement:
- Keep one horizontal strip only.
- Keep native `overflow-x: auto` + `scroll-snap-type: x mandatory`.
- Use uniform aspect-ratio cards (`aspect-ratio: 4/3` or `3/2`) and `object-fit: cover`.
- Remove chevron JS entirely (or retain chevrons only on desktop as progressive enhancement if product insists).

Why this is best here:
- Minimal code churn in current template/CSS architecture.
- Eliminates dual-row complexity at the source (PHP split logic).
- Removes inline JS dependency for core interaction.
- Better touch ergonomics and predictable card rhythm.

## 6) Lightest viable implementation plan (no patch yet)

### Files to change
1. `single-property.php`
   - Remove row splitting (`$row1/$row2`) and render one list/row directly from `gallery_ids`.
   - Remove or conditionally remove gallery nav buttons.
   - Remove `initGalleryChevronScroll()` if fully no-JS.

2. `css/slider.css`
   - Update `.property-gallery-strip` to single-row horizontal flex.
   - Add consistent card width + `aspect-ratio` on `.property-gallery-strip__item`.
   - Set image to fill card (`width:100%; height:100%; object-fit:cover`).
   - Tune gaps and `scroll-padding` for clean first/last alignment.

3. (Optional parity) `archive/single-property-v2.php`
   - Mirror same gallery changes if this file is kept as staging/candidate template.

### JS removal feasibility
- **Yes**, JS can be removed entirely for the single property gallery if chevrons are dropped.
- Native touch-scroll + scroll snap is sufficient.

## 7) Minimal patch strategy (proposed)

Phase 1 (safe + small):
1. PHP: render a single `.property-gallery-strip__row` from `gallery_ids`.
2. CSS: enforce one-size card system (`flex: 0 0 clamp(220px, 72vw, 360px)` + ratio).
3. Remove gallery chevrons and inline chevron JS block.

Phase 2 (optional polish):
1. Add subtle edge fade/gradient to suggest horizontal continuation.
2. Add `aria-label` updates and optional photo count line.

Rollback risk is low because the work is isolated to the gallery section and slider styles only.
