# PeraCRM portfolio fields mobile UI audit

## Observed mobile issues
- Portfolio cards are rendered in a 2-column grid even on narrow mobile widths, so each card becomes too narrow for the inline field editor.
- The portfolio fields editor (`Floor / Net / Gross / List / Cash`) is nested inside those narrow cards; controls appear to spill/overlap because the available width is constrained by the parent card column.
- The editor rows are not explicitly scoped with mobile-safe input sizing (`min-width: 0; width: 100%; max-width: 100%`) for this feature, so intrinsic input width contributes to overflow pressure in tight columns.

## Relevant markup
Source: `wp-content/themes/hello-elementor-child/page-crm-client.php`

```php
<li class="peracrm-linked-properties-grid__item">
  <a class="crm-linked-property-link" ...>...</a>
  <div data-crm-portfolio-row data-client-id="..." data-property-id="...">
    <div class="crm-form-row-2" data-crm-portfolio-fields>
      <label>Floor
        <input type="text" name="floor_number" data-field="floor_number" ... />
      </label>
      <label>Net (m²)
        <input type="text" name="net_size" data-field="net_size" ... />
      </label>
      <label>Gross (m²)
        <input type="text" name="gross_size" data-field="gross_size" ... />
      </label>
      <label>List ($)
        <input type="text" name="list_price" data-field="list_price" ... />
      </label>
      <label>Cash ($)
        <input type="text" name="cash_price" data-field="cash_price" ... />
      </label>
    </div>
    <div class="crm-inline-form">
      <button type="button" class="btn btn--solid btn--green" data-action="save-portfolio-fields">Save</button>
      <span class="crm-inline-status" data-crm-portfolio-status aria-live="polite"></span>
    </div>
  </div>
  <form method="post" class="peracrm-linked-property-unlink-form">...</form>
</li>
```

## CSS rules affecting layout

### `wp-content/themes/hello-elementor-child/css/crm.css`
- `.crm-form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }`
- `@media (max-width: 767px) { .crm-form-row-2 { grid-template-columns: 1fr; } }`
- `.peracrm-linked-properties-grid { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); }`
- `.peracrm-linked-properties-grid .peracrm-linked-properties-grid__item { display: grid; gap: 8px; min-width: 0; padding: 10px; ... }`
- `@media (max-width: 767px) { .peracrm-linked-properties-grid { grid-template-columns: 1fr; } }`
- `@media (max-width: 1024px) { .peracrm-linked-properties-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }` **(later in file; overrides 767 rule on mobile due cascade order)**
- `.crm-page .crm-inline-form { display: flex; flex-wrap: wrap; gap: 8px; }`
- Typography normalization applies to this form: `.crm-page label/input/select/textarea { font-size: var(--crm-text-base); ... }`

### `wp-content/themes/hello-elementor-child/css/main.css`
- Global reset: `html { box-sizing: border-box; } *, *::before, *::after { box-sizing: inherit; }`
- No specific CRM layout selector for `.crm-form-row-2`, `.crm-inline-form`, or `.peracrm-linked-properties-grid` found in `main.css`.

## Mobile breakpoints in play
- CRM uses `@media (max-width: 767px)` for small/mobile behavior.
- CRM also uses `@media (max-width: 1024px)` for tablet+mobile behavior.
- Because the 1024 block appears later in `crm.css`, its `2-column` rule for `.peracrm-linked-properties-grid` wins on <=767px unless explicitly re-overridden later or made more specific.

## Root cause(s)
1. **Cascade ordering bug on the parent properties grid**
   - A mobile-intended 1-column rule exists for `.peracrm-linked-properties-grid` at `max-width: 767px`, but a later `max-width: 1024px` rule resets it to 2 columns.
   - This keeps portfolio cards narrow on phones, triggering the visual overflow/breakage in the nested editor.

2. **Nested editor lacks feature-scoped mobile width guards**
   - The `[data-crm-portfolio-fields]` inputs rely on generic browser/input sizing and inherited CRM typography; there is no explicit mobile constraint (`width/max-width/min-width`) scoped to this portfolio editor.
   - In a narrow 2-column card, intrinsic input size and label content can push layout outside comfortable bounds.

3. **Grid-in-grid compression without dedicated mobile pattern**
   - Card grid + field grid combination has no dedicated “mobile tabular” pattern for this new editor, so spacing and track behavior are brittle under constrained widths.

## Minimal override plan (no patch yet)
Scope: **CRM-only, feature-only**, in `crm.css` under a mobile media query. Prefer selectors like:
- `body.crm-route [data-crm-portfolio-row] ...` or `.crm-page--client-view [data-crm-portfolio-row] ...`
- `[data-crm-portfolio-fields] ...`

Plan:
1. Fix parent card width on phone
   - Ensure linked property cards are single-column on true mobile within client view (<=767 or <=640 depending existing convention for this page).
   - Keep desktop/tablet behavior unchanged.

2. Add portfolio-editor mobile-safe sizing
   - On `[data-crm-portfolio-fields]` and its immediate children: enforce `min-width: 0`.
   - For inputs in this block: `width: 100%; max-width: 100%; min-width: 0;`.
   - For labels in this block: block/grid item behavior that does not impose intrinsic/fixed width.

3. Keep actions accessible
   - Ensure save row (`.crm-inline-form` under `[data-crm-portfolio-row]`) wraps cleanly and does not push unlink control out of card.

### Proposed “mobile tabular” layout
**Best fit with existing CRM styles: Option B (compact 2-up grid).**
- Use `[data-crm-portfolio-fields]` as a compact 2-column grid for field groups on mobile, with the last odd field spanning full width if needed.
- Each field group remains `label` above `input` (existing pattern), preserving CRM form semantics and avoiding table markup.
- This matches current CRM grid/form vocabulary better than introducing a literal table-like label/value two-track row for each field.

(Alternative Option A is possible: per-field row with `label | input` two-track grid, table-like. But this would be a bigger pattern shift vs current form style.)

## Test checklist
- Android Chrome viewport <= 480px.
- iPhone Safari viewport (small and standard width).
- Confirm no horizontal page/card scroll in client portfolio section.
- Confirm `Save` button and unlink icon remain visible and tappable.
