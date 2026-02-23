# PeraCRM Portfolio Fields Desktop UI Audit

## Scope
Audit only (no code changes) for desktop overflow issues in `/crm/client/{id}` Portfolio panel.

---

## 1) Markup location and rendered structure
**File:** `wp-content/themes/hello-elementor-child/page-crm-client.php`

Portfolio list + controls are rendered inside the main client section loop (relation = `portfolio`). Relevant DOM shape:

```php
<div class="crm-inline-form">
  <h4>Portfolio</h4>
  <button data-crm-portfolio-open="crm-client-portfolio-dialog">Create portfolio</button>
</div>

<div class="crm-inline-form" data-crm-portfolio-output>
  <label>Portfolio link:
    <input type="text" readonly data-crm-portfolio-url />
  </label>
  <button data-crm-portfolio-copy>Copy</button>
  <small data-crm-portfolio-expires>Expires: ...</small>
</div>

<ul class="crm-list peracrm-linked-properties-grid">
  <li class="peracrm-linked-properties-grid__item">
    <a class="crm-linked-property-link" ...>Property title</a>

    <div data-crm-portfolio-row data-client-id="..." data-property-id="...">
      <div class="crm-form-row-2" data-crm-portfolio-fields>
        <label>Floor <input type="text" ...></label>
        <label>Net (m²) <input type="text" ...></label>
        <label>Gross (m²) <input type="text" ...></label>
        <label>List ($) <input type="text" ...></label>
        <label>Cash ($) <input type="text" ...></label>
      </div>
      <div class="crm-inline-form">
        <button data-action="save-portfolio-fields">Save</button>
        <span data-crm-portfolio-status></span>
      </div>
    </div>

    <form class="peracrm-linked-property-unlink-form" method="post">
      <input type="hidden" name="pera_crm_property_action" value="unlink" />
      ...
      <button class="peracrm-linked-property-unlink-btn" type="submit">...</button>
    </form>
  </li>
</ul>
```

Notes from template:
- Portfolio heading + create button wrapper: `.crm-inline-form`.
- Portfolio link + copy + expiry wrapper: `.crm-inline-form[data-crm-portfolio-output]`.
- List container: `.peracrm-linked-properties-grid`.
- Card item: `.peracrm-linked-properties-grid__item`.
- Title link: `.crm-linked-property-link`.
- Editor wrappers: `[data-crm-portfolio-row]` and `[data-crm-portfolio-fields]` (also `.crm-form-row-2`).
- Unlink mini-form: `.peracrm-linked-property-unlink-form`.

---

## 2) Governing CSS rules (desktop)

### A. Outer grid and card constraints
**File:** `wp-content/themes/hello-elementor-child/css/crm.css`

- `.peracrm-linked-properties-grid` (desktop/default):
  - `display: grid;`
  - `gap: 12px;`
  - `grid-template-columns: repeat(4, minmax(0, 1fr));`
- `.peracrm-linked-properties-grid .peracrm-linked-properties-grid__item`:
  - `display: grid;`
  - `gap: 8px;`
  - `min-width: 0;`
  - `padding: 10px;`
  - `border: 1px solid #e8ecf2;`

### B. Inner editor grid
**File:** `wp-content/themes/hello-elementor-child/css/crm.css`

- `.crm-form-row-2`:
  - `display: grid;`
  - `grid-template-columns: 1fr 1fr;`
  - `gap: 12px;`

This class is used by `[data-crm-portfolio-fields]` in each portfolio card.

### C. Link/title behavior
**File:** `wp-content/themes/hello-elementor-child/css/crm.css`

- `.crm-linked-property-link`:
  - `font-weight: 600;`
  - `text-decoration: underline;`

No explicit wrapping controls (`overflow-wrap`, `word-break`) are applied for long tokens.

### D. Mobile-only portfolio guardrails (not active on desktop)
**File:** `wp-content/themes/hello-elementor-child/css/crm.css`

Inside `@media (max-width: 767px)` only:
- `.crm-route [data-crm-portfolio-row] { min-width: 0; }`
- `.crm-route [data-crm-portfolio-fields] { display:grid; grid-template-columns:1fr 1fr; min-width:0; }`
- `.crm-route [data-crm-portfolio-fields] > label { min-width:0; }`
- `.crm-route [data-crm-portfolio-fields] input { width:100%; max-width:100%; min-width:0; box-sizing:border-box; }`

These protections do **not** apply at desktop widths.

### E. Baseline styles (main.css)
**File:** `wp-content/themes/hello-elementor-child/css/main.css`

- Global `box-sizing: border-box` is present (`html` + universal inheritance), which is good baseline and not the overflow source.
- No direct `main.css` selector was found targeting `.peracrm-linked-properties-grid`, `.peracrm-linked-properties-grid__item`, `.crm-form-row-2`, or `.crm-linked-property-link`.

---

## 3) Why desktop breaks (root causes)

### Root cause A — Outer grid packs cards too tightly on desktop
**Yes.**
- `.peracrm-linked-properties-grid` uses `repeat(4, minmax(0, 1fr))` globally.
- Portfolio cards include: long title + 5 editable fields + action row + unlink action.
- Four columns leave insufficient per-card width on typical desktop/laptop ranges.

### Root cause B — Inner form grid forces minimum width pressure
**Yes.**
- `.crm-form-row-2 { grid-template-columns: 1fr 1fr; }` forces two tracks in each card.
- Five field labels/inputs in two columns create stacked rows where each column can be too narrow.
- Because tracks are `1fr` (not `minmax(0,1fr)`), min-content sizing pressure from inputs/labels is stronger.

### Root cause C — Title text wrapping hardening is missing
**Partially yes.**
- `.crm-linked-property-link` lacks explicit wrap safeguards (`overflow-wrap:anywhere` / `word-break:break-word`).
- Long project names or unbroken tokens can overflow card width.

### Root cause D — Missing `min-width:0` on key desktop inner children
**Yes.**
- Card item itself has `min-width:0`, but desktop rules do not set `min-width:0` for `[data-crm-portfolio-row]`, `[data-crm-portfolio-fields] > label`, and portfolio inputs.
- Those guards exist only in mobile media blocks, so desktop can still overflow due to grid item intrinsic sizing.

### Root cause E — Fixed/min widths on inputs
**Not primary from current CRM desktop rules, but contributes via intrinsic input size behavior.**
- No explicit desktop `width:100%`/`min-width:0` for portfolio inputs.
- Inputs can retain intrinsic/min-content pressure inside 2-column grid tracks.

---

## 4) Breakpoint inventory + cascade/order risks

### Selectors affecting `.peracrm-linked-properties-grid`
1. Base/default: `grid-template-columns: repeat(4, minmax(0, 1fr));`
2. `@media (max-width: 1024px)`: `repeat(2, minmax(0, 1fr));`
3. `@media (max-width: 767px)` appears **twice** and sets `1fr`.

### Selectors affecting `.crm-form-row-2`
1. Base/default: `grid-template-columns: 1fr 1fr;`
2. `@media (max-width: 767px)`: `grid-template-columns: 1fr;`

### Selectors affecting portfolio field inputs
- Desktop: no dedicated portfolio-scoped input shrink rules.
- Mobile (`max-width: 767px`): portfolio-specific width/min-width guards are present.

### Cascade risk
- There are duplicate `@media (max-width: 767px)` rules for `.peracrm-linked-properties-grid` in different parts of `crm.css`.
- Existing mobile portfolio fixes are scoped to mobile only, so desktop regresses while mobile appears stable.
- Any new fix should be inserted in a clear order (prefer near linked-properties block) and portfolio-scoped to avoid side effects.

---

## 5) Minimal fix plan options (no code yet)

## Plan 1 (preferred minimal): **Make portfolio cards wider + allow wrap**
CRM-scoped only.

1. **Portfolio-only outer grid adjustment**
   - Scope to portfolio context on client view page (e.g., `.crm-route .crm-page--client-view ...` with relation-specific hook already in markup context).
   - Override only portfolio list to `repeat(2, minmax(0,1fr))` on desktop (optionally 3 on very wide screens if validated).
   - Do **not** change global `.peracrm-linked-properties-grid` behavior for non-portfolio sections.

2. **Title wrapping hardening**
   - Portfolio-scoped `.crm-linked-property-link`:
     - `display:block;`
     - `overflow-wrap:anywhere;` (and/or `word-break:break-word` fallback).

3. **Desktop shrink guards for editor**
   - Add desktop-safe `min-width:0` on `[data-crm-portfolio-row]`, `[data-crm-portfolio-fields]`, `[data-crm-portfolio-fields] > label`.
   - Ensure portfolio inputs are `width:100%; max-width:100%; min-width:0;`.
   - Optional: use `grid-template-columns: repeat(2, minmax(0,1fr))` for `[data-crm-portfolio-fields]` to remove intrinsic overflow pressure.

Why preferred: smallest behavioral change; preserves existing information architecture; mostly adds containment rules.

## Plan 2: **Keep outer card density, compact editor grid/table-like structure**
CRM-scoped only.

1. Keep current outer grid density where possible.
2. Convert `[data-crm-portfolio-fields]` into a compact desktop layout:
   - single-column stack or controlled 2-up compact grid with smaller gaps.
3. Normalize action row placement (save/status/unlink grouped consistently at card footer).
4. Still apply title wrap + min-width:0 fixes from Plan 1.

Why viable: reduces internal pressure without changing how many cards appear per row, but is slightly more structural and likely more CSS than Plan 1.

---

## 6) Scoping recommendation
- Use CRM-scoped selectors in `crm.css` only.
- Prefer existing wrappers:
  - `body.crm-route`
  - `.crm-page--client-view`
  - portfolio-specific data attributes (`[data-crm-portfolio-row]`, `[data-crm-portfolio-fields]`, `[data-crm-portfolio-output]`).
- No markup change required for the preferred plan.

---

## 7) Test checklist (for implementation pass)
- [ ] Desktop 1440px: no overlap, no horizontal page/card scroll, cards visually contained.
- [ ] Desktop 1024px: stable card widths, no editor overflow.
- [ ] Mobile widths (767 and below): previous mobile fixes remain intact.
- [ ] “Copy” and “Create portfolio” controls remain visible and usable.
- [ ] Unlink control remains reachable and aligned.
