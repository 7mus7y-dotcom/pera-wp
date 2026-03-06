# Live CSS Baseline Consistency Audit

Date: 2026-03-06  
Scope: Active baseline CSS used by the child theme front-end.

---

## Executive summary

This audit reviewed the active baseline CSS for consistency across typography, spacing, colors, buttons, cards, forms, containers, radii, shadows, interaction states, breakpoints, utilities, naming patterns, repeated rules, and page-specific drift.

### High-level findings
- The active baseline is **`main.css` + conditional `slider.css` + conditional `property.css`** (not `(2)`-suffixed files in this repo snapshot).
- `main.css` provides a good token and primitive foundation (colors, spacing, radii, pills, buttons, utilities), but downstream files contain substantial hard-coded values and one-off overrides.
- Biggest risks are:
  - selector duplication and layered overrides,
  - breakpoint fragmentation,
  - inconsistent interaction states (especially form focus and context-specific button sizing),
  - ad hoc shadow/radius/spacing values drifting from token intent.

---

## A. CSS loading / source-of-truth audit

## 1) Exact file paths
- `wp-content/themes/hello-elementor-child/css/main.css`
- `wp-content/themes/hello-elementor-child/css/property.css`
- `wp-content/themes/hello-elementor-child/css/slider.css`

## 2) Enqueue handles + conditions
- **Always loaded**
  - handle: `pera-main-css` → `/css/main.css`
- **Conditionally loaded**
  - handle: `pera-slider-css` → `/css/slider.css`
    - loaded on home, single property, single bodrum property, single post, contact template, about template.
  - handle: `pera-property-css` → `/css/property.css`
    - loaded for property archive contexts and also on home/single property variants.

## 3) Are these active or superseded?
- Parent Hello Elementor front-end styles are explicitly disabled/dequeued, which reinforces these child theme styles as source of truth.
- No compiled replacement bundle was found superseding these three globally in this snapshot.
- A test-template asset loader (`home-page-test-assets.php`) enqueues the same CSS family; this does not indicate supersession.

## 4) About `main (2).css` and `property (2).css`
- Exact files named `main (2).css` / `property (2).css` were not present in this repo snapshot.
- The active enqueues target `main.css` / `property.css`.

## 5) Duplicated CSS across files
- Low exact selector collision across files due scoping.
- High **behavioral duplication/drift** and intra-file duplication (notably in `slider.css` and `property.css`).

---

## B. Design token consistency

## 1) Colors
- `main.css` defines tokenized base colors (`--text`, `--bg`, `--brand`, etc.).
- Real usage still mixes many literals:
  - `#fff` and `#ffffff`
  - `#000` and `#000000`
  - many one-off RGBA values for shadows/surfaces/focus states.
- Dark mode is handled through multiple pathways (`prefers-color-scheme`, `[data-theme="dark"]`, `.dark`), which increases maintenance overhead.

## 2) Font sizes / weights
- Typographic foundation exists, but contextual overrides add drift:
  - base button size differs from slider-mobile button override;
  - filter/pill contexts add several nearby but not unified sizes.

## 3) Spacing
- Global spacing tokens exist (`--space-*`), but large portions of component CSS use hard-coded spacing (`10px`, `14px`, `18px`, `24px`, etc.).
- This creates a mixed token/non-token system and makes spacing audits harder.

## 4) Radius
- Radius tokens exist and are sensible.
- Many components still use literal radius values (`10px`, `12px`, `14px`, `16px`, `20px`, `999px`) with no explicit semantic mapping.

## 5) Shadows
- Multiple shadow recipes appear across components with similar but not standardized depth semantics.
- Candidate for consolidation into shadow tiers (e.g., subtle / raised / overlay).

## 6) Transition values
- Interactions use mixed durations (`160ms`, `0.2s`, `0.15s`, `0.12s`) for similar interaction categories.
- A transition token set would improve consistency.

## 7) z-index patterns
- High z-index values (e.g., `9999`) are repeated in multiple components.
- No centralized z-index scale token is apparent.

## 8) Breakpoints
- Token breakpoints are defined in `main.css`, but raw px breakpoints remain widespread (`767`, `720`, `800`, `900`, `1100`, etc.).
- This is a clear fragmentation source.

## 9) Custom properties in use
- `main.css` is the primary token source (color, spacing, radius, glass, breakpoints).
- `property.css` and `slider.css` consume some tokens but still rely heavily on literals.

## 10) Near-duplicate values to consolidate
- Color aliases: `#fff` / `#ffffff`, `#000` / `#000000`.
- Radius families: 12/14/16/20 and pill 999.
- Shadows: repeated variants around `0 4px 10px`, `0 6px 16px`, `0 8px 20px`, `0 10px 30px`, `0 12px 24px`.
- Breakpoint near-duplicates: `640/720/768/800`, `1024/1100/1200`.

---

## C. Component consistency audit

## 1) Buttons
- Strong base button system exists in `main.css`.
- `slider.css` changes button sizing in slider cards for mobile, causing context drift.
- `property.css` applies layout-specific `.btn` behavior in filter actions.

## 2) Property cards
- Generic `card-shell` in `main.css` is reusable and token-aware.
- Property contexts include forced per-card overrides (`!important` cases), which may bypass baseline card rhythm.

## 3) Sliders / carousels
- Slider utility architecture is good (base + variants).
- Property gallery section includes repeated selector blocks and layered restyling inside `slider.css`, which increases conflict risk.

## 4) Forms / inputs / selects
- Property filter controls have initial base styles, then follow-up overrides and dark-mode-specific focus behavior.
- Focus consistency appears stronger in dark mode than default mode for some controls.

## 5) Archive / listing layouts
- Main provides baseline section/container patterns.
- Property archive hero uses fixed large paddings and negative margins (patch-like feel rather than systemic spacing).

## 6) Hero sections
- Base hero language is coherent in `main.css`.
- Property-specific hero offsets suggest template-specific compensation rather than normalized shell variables.

## 7) Badges / pills / labels
- Pill system in `main.css` is fairly mature and tokenized.
- Property filters add additional active-state patterns (`.filter-pill--active`, `.pill--radio:has(...)`) that may diverge from core pill behavior.

## 8) Tables
- Table support appears present in property page range section and mostly scoped.
- Uses literals that could be normalized.

## 9) Section wrappers / surface panels
- `.section`, `.container`, `.content-panel-box` patterns exist but are overridden in property contexts with force rules and one-off sizing.

---

## D. Conflict / drift audit

## 1) Selectors repeated within files
- `slider.css`: repeated blocks for
  - `.property-gallery-strip`
  - `.property-gallery-strip__row`
  - `.property-gallery-strip__item`
  - `.property-gallery-strip__item img`
- `property.css`: repeated and layered declarations for
  - filter dialog panel/content,
  - filter input groups,
  - mobile stack/layout behavior,
  - dark mode form controls.

## 2) Overrides likely accidental or force-based
- Multiple `!important` usages used to enforce outcomes (e.g., centering, hiding, box-shadow removal), suggesting specificity contention.

## 3) Context divergence
- Archive hero toggle behavior exists in main but receives map-card-specific restyling in property.
- Buttons and pills are generally shared, but context-specific deltas are implemented via nested selectors rather than explicit variant classes.

## 4) Property / slider divergence from main language
- Main tokenizes; property/slider often literalize.
- Main has standardized button/pill primitives; property/slider sometimes override by context and media block.

---

## E. Responsiveness audit

## 1) Breakpoint consistency
- Core tokens exist but are inconsistently used.
- Raw breakpoints across files indicate mixed generations of responsive logic.

## 2) Repeated mobile overrides
- Property filter panel and archive hero contain multiple layered mobile overrides that appear iterative.
- Slider introduces additional mobile-specific button and card-basis changes, which may complicate consistency.

## 3) Signs of patched spacing on desktop/mobile
- Negative margins and large fixed paddings in archive hero (`hero-content` and intro panel) indicate layout patching.

## 4) Fragility indicators
- Repeated selector blocks for gallery/filters + hard-coded values + `!important` usages are the strongest fragility signals.

---

## F. Risk-ranked recommendations

## 1) Safe quick wins
1. Document source-of-truth naming (`main.css`/`property.css` active equivalents).
2. Consolidate exact duplicate selector blocks in `slider.css` without changing computed outputs.
3. Introduce alias tokens for most-used shadow/radius literals and replace exact matches only.
4. Normalize obvious color aliases (`#fff` vs `#ffffff`, etc.).
5. Reduce non-essential `!important` cases where selector scope already guarantees order.

## 2) Medium-risk normalization tasks
1. Convert context-specific button sizing into explicit button variants.
2. Normalize form focus states across default/dark contexts.
3. Consolidate breakpoint usage onto `--bp-*` tokens.
4. Introduce z-index scale and migrate overlay/dialog/nav stacks.

## 3) Higher-risk refactor items
1. Rework archive hero vertical spacing to remove negative-margin coupling.
2. Unify dark mode strategy (single source: theme class or media strategy with clear precedence).
3. Move repeated component state logic (toggle/pill/card interactions) into shared source and reduce context overrides.

---

## G. Proposed standardization plan (no implementation)

1. Keep existing file split (`main.css`, `property.css`, `slider.css`) to minimize immediate risk.
2. Formalize a token layer in `main.css`:
   - colors, spacing, radii, shadows, transitions, z-index, breakpoints.
3. Perform a no-visual-change normalization pass:
   - duplicate block consolidation,
   - literal-to-token substitutions only where exact equivalence exists.
4. Move shared interaction states into main component primitives:
   - buttons, pills, toggles, focus rings.
5. Replace context-heavy overrides with explicit variant classes where feasible.
6. Defer layout shell refactors (hero/archive) to a final, regression-tested phase.

---

## Key inconsistencies by category

- **Typography:** nearby but inconsistent component sizes; context overrides for button sizing.
- **Spacing:** token scale exists but mixed with heavy literal usage.
- **Colors:** tokenized base with frequent literal drift.
- **Buttons:** strong base system, but local overrides cause inconsistent sizing/behavior.
- **Cards:** reusable shell exists; property overrides can bypass defaults.
- **Forms:** layered overrides and mode-specific focus inconsistencies.
- **Containers/shells:** good primitives, but archive contexts patch around template constraints.
- **Radius/shadows:** many near-duplicate recipes lacking semantic tiers.
- **States:** hover/focus treatment not uniformly centralized across all component contexts.
- **Responsive:** breakpoint fragmentation and repeated patch-like mobile overrides.

---

## Top 10 highest-value fixes (future work backlog)

1. Consolidate duplicated property-gallery blocks in `slider.css`.
2. Normalize button sizing through variants rather than context selectors.
3. Create shared focus-ring tokens for form controls and apply in all modes.
4. Consolidate repeated shadow recipes into 3–4 tiers.
5. Normalize border-radius values to token tiers.
6. Reduce `!important` in property archive alignment/visibility rules.
7. Standardize breakpoints to `--bp-*` set.
8. Normalize color literals to tokens.
9. Rationalize z-index usage via named layers.
10. Unify dark-mode strategy to reduce duplicated rule pathways.

---

## Risk notes

- Highest regression risk areas:
  - archive hero spacing and panel overlap,
  - property filter dialog behavior across breakpoints,
  - gallery strip/nav behavior due repeated selector blocks,
  - force rules currently stabilizing layout (`!important`).

---

## Suggested implementation order

1. Source-of-truth documentation + token inventory freeze.
2. Duplicate selector consolidation (no visual intent changes).
3. Literal-to-token normalization for exact equivalent values.
4. Button/pill/toggle/focus state harmonization.
5. Breakpoint normalization.
6. Layout shell refactors (hero/archive) last, with visual QA gates.

---

## Appendix

## 1) Repeated spacing values (observed frequently)
- `8px`, `10px`, `12px`, `16px`, `20px`, `24px`, `30px`.

## 2) Repeated radius values
- `999px`, `12px`, `14px`, `16px`, `20px`.

## 3) Repeated shadow recipes
- `0 4px 10px rgba(...)`
- `0 6px 16px rgba(...)`
- `0 8px 20px rgba(...)`
- `0 10px 30px rgba(...)`
- `0 12px 24px rgba(...)`

## 4) Repeated breakpoints
- Common: `640`, `768`, `1024`.
- Fragmenting extras: `720`, `767`, `800`, `900`, `1100`, `1200`.

## 5) Top specificity hotspots (examples)
- `.property-map-card .archive-hero-desc[data-collapsed="false"] .archive-hero-desc__toggle`
- `.portfolio-token-page .portfolio-view-toggle .btn.is-active`
- `[data-theme="dark"] .property-gallery .property-gallery-nav svg`

## 6) `!important` occurrence summary
- Present in all three baseline files.
- Most concentrated in `main.css`, followed by `property.css`, then `slider.css`.

---

## Evidence and method summary

This report is derived from repository inspection and CSS enqueue tracing, including:
- enqueue references in theme PHP modules,
- direct review of all three CSS files,
- targeted searches for breakpoints, shadows, radii, transitions, specificity pressure, and `!important` usage.

No CSS changes were implemented in this task.
