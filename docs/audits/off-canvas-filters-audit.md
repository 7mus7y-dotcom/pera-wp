# Off-canvas Filters Audit (Property Archive)

## Scope and entry-point check

Primary file requested was `single-property.php`, but no Filters off-canvas implementation exists there. The Filters trigger/dialog implementation is on the property archive template (`archive-property.php`) and is styled in `css/property.css` with inline JS in the same template.

## 1) Component ownership

### PHP ownership
- Trigger button: `#filters-trigger.property-filters-trigger`.  
- Dialog root (off-canvas container): `#property-filter-dialog.property-filter-dialog[role="dialog"]`.  
- Overlay: `.property-filter-dialog__overlay[data-filter-overlay]`.  
- Panel container: `.property-filter-dialog__panel[role="document"]`.  
- Panel inner/content: `.property-filter-dialog__content`.  
- Close button: `.property-filter-dialog__close[data-filter-close]`.

All of the above live in:
- `wp-content/themes/hello-elementor-child/archive-property.php`

### CSS ownership
Primary filter/drawer styles are in:
- `wp-content/themes/hello-elementor-child/css/property.css`

No component-specific filters drawer rules were found in `main.css` for this archive dialog.

### JS ownership
Open/close + accessibility + scroll lock are inline in:
- `wp-content/themes/hello-elementor-child/archive-property.php`

Key handlers:
- Open listener: `dialogTrigger.addEventListener('click', openDialog)`
- Close listener: close button click (`dialogClose`) and overlay click (`dialogOverlay`)
- ESC listener: document `keydown`, closes on `Escape` when dialog is open
- Body lock logic: adds `body.has-open-dialog`; also writes `document.body.style.overflow = 'hidden'` and restores previous inline overflow value on close

### Body lock class
- `body.has-open-dialog` (defined in `css/property.css`, also toggled in JS)

---

## 2) Breakpoint behaviour audit

### Desktop (>=1200px)
- Panel width: `min(520px, 100%)` by default, and overridden at `min-width:768px` to `50vw`.
- Positioning: fixed full-viewport dialog root; right-side panel via `margin-left:auto`.
- Overlay coverage: full inset overlay.
- Close button position: in sticky header (`top:0`) inside panel content.
- Content overflow: panel is scroll container (`overflow:auto`), with sticky header retained.
- Scroll behaviour: body lock class + inline `overflow:hidden`.

### Tablet (768–1199px)
- Same enhanced behavior as desktop due to `min-width:768px` rule.
- Panel width is 50vw; overlay full-screen; panel remains right anchored.
- Internal panel scrolling remains active.

### Mobile (<=767px)
- Panel width: `width:min(520px,100%)` and now constrained by `max-width:100vw`.
- Positioning: fixed root, right-side sheet.
- Overlay: full-screen and below panel via explicit z-index layering.
- Close button: sticky header, now minimum 44x44 touch target.
- Content/panel overflow: panel itself scrolls (`overflow:auto`, iOS momentum scroll enabled).
- Body scroll: locked while dialog open.

---

## 3) Mobile-specific issue findings

Found pre-fix risks:
- Overlay/panel stacking relied on DOM order and lacked explicit layering between overlay/panel.
- Panel used `height:100%` without `100dvh`, which can be unreliable on mobile dynamic viewport (notably iOS URL bar behavior).
- Close button had no enforced minimum touch target.
- Trigger did not expose `aria-expanded` state updates.

Not found from code audit:
- No evidence close button is intentionally moved off-screen.
- No parent-relative panel positioning in enhanced mode (it is fixed via dialog root).

---

## 4) Stacking context / z-index audit

Current intended stack inside dialog:
1. Page content (below)
2. Dialog overlay
3. Dialog panel
4. Panel controls/header (close button in sticky header)

Fix applied to make this explicit:
- Dialog root remains high stack (`z-index:9999`)
- Overlay forced to `z-index:0`
- Panel forced to `z-index:1`
- Sticky header remains `z-index:10` within panel

This avoids close-button click-blocking from overlay overlap.

---

## 5) Overlay behaviour audit

- Overlay is a sibling of panel under dialog root.
- It covers full viewport (`inset:0`) when dialog is open.
- Overlay click closes dialog via `[data-filter-overlay]` click handler.
- With explicit panel `z-index:1`, overlay should not intercept panel control clicks.

---

## 6) Close button audit

- Close button exists in dialog header and is focus-targeted on open.
- Improved touch target with min `44x44`.
- Header remains sticky to keep close button visible while panel content scrolls.

---

## 7) Scroll behaviour audit

On open:
- JS sets `body.has-open-dialog`
- JS stores previous inline body overflow, then sets `body.style.overflow = 'hidden'`

On close:
- Removes class and restores previous inline overflow

Panel scrolling:
- `overflow:auto` on panel
- `-webkit-overflow-scrolling:touch` for mobile smoothness
- `height:100dvh` added to reduce iOS viewport clipping risk

---

## 8) Accessibility checks

Present:
- `role="dialog"`, `aria-modal="true"`, `aria-hidden` toggled in JS
- ESC closes dialog
- Focus moved to close button on open

Improved:
- Trigger now has `aria-expanded` and JS updates it on open/close

Not implemented (out of minimal-fix scope):
- Full focus trap loop within dialog

---

## 9) Recommended minimal safe fix (implemented)

1. Add explicit stacking order between overlay and panel.
2. Add mobile-safe viewport height (`100dvh`) and panel viewport clamping.
3. Ensure close button minimum touch target.
4. Keep panel as scroll container and avoid internal conflicting content overflow.
5. Add trigger `aria-expanded` state wiring.

These changes are intentionally minimal and component-scoped.
