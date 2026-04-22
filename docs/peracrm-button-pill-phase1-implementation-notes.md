# PeraCRM button/pill/chip simplification — Phase 1 implementation notes

## What was consolidated

- **Core action button contract**
  - Normalized shared button geometry/type traits behind CRM-scoped custom properties (`--crm-button-core-*`) and pointed the existing `.btn` primitive selectors to those values.
  - Preserved existing `.btn`, `.btn--solid`, `.btn--ghost`, `.btn--blue`, `.btn--green`, `.btn--red`, and `.btn--white` selector behavior.

- **Compact operational button contract**
  - Consolidated compact sizing (`padding` + operational font size) into one shared selector group used by existing operational zones:
    - toolbar actions
    - section/row/table actions
    - task/action groups
    - view/type toggle button sets
    - archive hero toggle
    - CRM logs utility + WP secondary bridge buttons

- **Icon-only control contract**
  - Centralized fixed-size icon control geometry into a single shared contract (`--crm-button-icon-size`, `--crm-button-icon-radius`) while keeping all existing selectors intact, including:
    - `.crm-icon-btn`
    - `.peracrm-linked-property-unlink-btn`
    - portfolio copy/open controls (`[data-crm-theme-portfolio-open-link]`, `[data-crm-theme-portfolio-copy]`)

- **Chip / pill alias layer**
  - Added a small shared inline-flex baseline for `.crm-chip` and `.pill` to reduce geometry drift.
  - Marked `.pill` explicitly as a legacy compatibility alias and documented `.crm-chip` as canonical semantic direction.

- **WP bridge layer cleanup**
  - Kept CRM-scoped WP bridge selectors (`.button.button-secondary`, `button.button-secondary`) intact.
  - Moved compact sizing ownership for these bridge buttons into the shared compact contract to avoid drift with CRM-native compact actions.

## Intentionally left untouched in Phase 1

- Markup/templates (no class removals or selector renames).
- `crm.js` behavior contracts and all data-hook/state-driven areas.
- Segmented control structure and view-toggle behavior coupling (only documented via comments).
- Color-role hierarchy/design-system redesign concerns (deferred to later phases).

## Follow-up candidates for later phases

- Markup simplification to reduce alias debt (`.pill` usage migration toward `.crm-chip` where semantically correct).
- Further segmented-control separation from command-button visual language.
- Cleanup of historical duplicate blocks that are still harmless but structurally redundant outside this safe Phase 1 scope.

## Phase 1.5 refinements

- **Removed redundant button geometry where core contracts already apply**
  - Dropped duplicate `.btn` geometry/type declarations from `.crm-reminder-toast__undo.btn` so it inherits the shared core button contract while preserving its explicit color/border behavior.
  - Removed an empty historical `.crm-action-group__item {}` block.

- **Completed compact operational contract usage notes**
  - Kept compact sizing ownership in the shared compact selector contract and documented that it explicitly covers archive toggle + CRM-scoped WP secondary bridge buttons used in logs utilities.

- **Tightened icon-button contract ownership**
  - Removed legacy geometry declarations from `.peracrm-linked-property-unlink-btn` now that width/min-width/padding/radius/display alignment are centralized in the icon-button contract group.
  - Removed a no-op duplicate icon-button selector block that only carried a comment and no declarations.

- **Pill/chip system clarity**
  - Added a dedicated chip/pill alias section header near the shared inline-flex baseline to make canonical (`.crm-chip`) vs compatibility alias (`.pill`) intent clearer without changing selectors or behavior.

- **Known exceptions intentionally retained**
  - Segmented toggle controls (`.crm-view-toggle` / `.crm-type-toggle`) retain their behavior-coupled styling and selector contracts.
  - `.pill` remains a compatibility alias and is not force-migrated to `.crm-chip` in Phase 1.5.

- **Deferred to Phase 2**
  - Markup-level selector simplification/migration work.
  - Any restructuring that would alter cascade order for behavior-coupled zones.

## Phase 2 — compact height, hover restoration, dark mode repair

- **Compact height contract (32px ceiling for compact controls)**
  - Introduced explicit compact-control tokens to keep compact interactive controls within a consistent visual height ceiling:
    - `--crm-control-compact-max-height: 32px`
    - `--crm-control-compact-font-size`
    - `--crm-control-compact-line-height`
    - `--crm-control-compact-padding-y/x`
    - `--crm-control-icon-size: 32px`
  - Wired compact operational selectors (toolbars, row/table/section actions, segmented toggle buttons, archive toggle, logs utility bridges) to use shared compact height + type settings.
  - Set icon controls covered by the canonical icon-button contract to a 32px box (`width/min-width/height/min-height/max-height`) while preserving existing selectors and behavior contracts.
  - Kept `.pill` compatibility alias and canonical `.crm-chip` aligned to compact line-height/padding with the same max-height ceiling.
  - Applied the compact max-height ceiling to compact date/meta badge controls and reminder toast undo button.

- **Hover restoration pass**
  - Restored explicit hover/focus-visible visual feedback for segmented-toggle ghost states in light mode to prevent neutralized/inconsistent hover outcomes.
  - Restored/standardized hover feedback for:
    - `.pill--outline`
    - `.crm-chip` (non-selected)
    - icon utility buttons under CRM icon-button selectors
  - Kept selected/active states authoritative (e.g., `.crm-chip--selected` is excluded from neutral hover treatment).

- **Dark mode repair pass**
  - Added/normalized dark-mode pill surfaces for base `.pill` and `.pill--outline`, including visible hover states (no light-mode leftovers).
  - Added dark-mode hover treatment for non-selected chips so hover remains visible against dark surfaces.
  - Repaired reminder toast undo in dark mode with explicit border/fill/hover/focus contrast.
  - Added dark-mode icon-button hover ring treatment for CRM icon utility controls.
  - Preserved behavior-coupled segmented controls (`.crm-view-toggle`, `.crm-type-toggle`) while refining visual consistency only.

- **Intentional exceptions / notes**
  - Side-nav/header icon toggle controls keep their independent tap-target contract and were not folded into the compact CRM icon-button contract.
  - No markup, JS, data-hook, state-class, or selector-identity changes were made in this phase.

- **Still deferred**
  - Any markup simplification/class migration (including `.pill` alias debt retirement) remains deferred to later phases.
  - Any JS-coupled structural refactor remains deferred.

### Phase 2 correction — height enforcement strategy update

- Revised compact height enforcement to avoid hard clipping on text-based controls.
- Removed blanket `min-height/max-height: 32px` usage from compact text-control groups and the mixed pill/chip/date/reminder grouping.
- Text controls now rely on tokenized compact spacing (`padding`, `font-size`, `line-height`) so they naturally land at/under the 32px visual target.
- Icon-only controls remain explicit fixed-size exceptions at 32px square via the canonical icon-button contract.

## Phase 3 — safe markup simplification

- **What was simplified**
  - Converted two purely visual reminder group labels in the client view from legacy `.pill` markup to canonical chip markup:
    - `Overdue`: `<p class="pill">…</p>` → `<span class="crm-chip crm-chip--urgent">…</span>`
    - `Upcoming`: `<p class="pill">…</p>` → `<span class="crm-chip crm-chip--status">…</span>`
  - Kept behavior and structure intact (same parent sections, no form/event target changes).

- **Compatibility styling follow-up**
  - Updated the reminder-group heading selector to target both aliases during transition:
    - `.crm-client-reminders-grid > section > :is(.pill, .crm-chip)`
  - This preserves layout/spacing while allowing safe incremental `.pill` retirement.

- **What was explicitly skipped (risk control)**
  - No changes to JS-coupled controls, including any element with `data-crm-*`/`data-peracrm-*` hooks.
  - No changes to toggle/segmented controls (`.crm-view-toggle`, `.crm-type-toggle`).
  - No changes to unlink/icon action controls (`.crm-icon-btn`, `.peracrm-linked-property-unlink-btn`).
  - No wrapper removals inside forms, reminder action rows, or AJAX-bound components.
  - Left clickable note trigger aliases (e.g., `.pill.crm-task-note-trigger`) unchanged for now due to behavior-adjacent risk.

- **Ambiguous areas left for later phases**
  - Broader `.pill`→`.crm-chip` migration in mixed interactive/list-filter regions.
  - Button-stack simplification where class membership may still provide layout hooks outside visual styling.
  - Any wrapper pruning where spacing responsibility is not provably redundant from local context alone.

## Phase 2.1 — compact button coverage audit and fixes

- **Missing compact-coverage zones identified**
  - Client-view summary action stack (`.crm-summary-header__actions .btn`).
  - Client-view quick-contact actions (`.crm-client-quick-actions .btn`).
  - CRM status utility actions (`.crm-status-actions .btn`, including inline-form button usage).
  - Client-view utility rows/dialog actions:
    - `.crm-client-toolbar .crm-client-toolbar__back.btn`
    - `.crm-danger-dialog__actions .btn`
    - `.crm-linked-workspace__toolbar .btn`
    - `.crm-linked-workspace__portfolio-actions .btn`
  - Existing compact zones (toolbars, action groups, row/table actions, view/type toggles, reminder task actions, archive toggles) were left intact.

- **Selector coverage expansion applied**
  - Expanded the shared compact operational selector contract in `crm.css` to include the missing utility/control zones above.
  - Kept icon-only controls excluded via existing guard clauses:
    - `:not(.crm-icon-btn):not(.peracrm-linked-property-unlink-btn)`
  - Added `.crm-inline-form .btn` to compact coverage for behavior-equivalent inline utility controls.

- **Mobile-specific compact inflation fix (client view)**
  - Added a tightly scoped mobile (`max-width: 575px`) override for compact utility zones to prevent the broad client-view mobile button floor from inflating compact controls.
  - Applied compact `min-height` and compact vertical padding only to the audited compact zones (summary/quick-actions/status/dialog/linked-workspace/reminder-task-action controls).

- **Intentionally kept standard-size**
  - Primary form submit CTAs in regular form flows (e.g., profile/status save, add note/reminder, create/update flows) were not globally downscaled.
  - No global `.btn` shrink was introduced.

- **Safety constraints preserved**
  - CSS-only pass; no JS changes.
  - No selector removals/renames.
  - No data-hook, nonce, hidden input, or behavior-contract changes.


## Phase 3.1 — static pill migration

- **Audit scope executed**
  - Reviewed `wp-content/plugins/peracrm/inc/views/pages/*.php` and `wp-content/plugins/peracrm/inc/views/partials/*.php` for remaining `.pill` usage eligible for static migration.
  - Cross-checked behavior coupling constraints against `wp-content/plugins/peracrm/assets/frontend/crm.js` (inspection only; no JS edits).

- **Conversions made (safe/static only)**
  - `0` additional `.pill` usages converted in this pass.
  - Reason: the only remaining `.pill` occurrence in audited template scope is behavior-coupled and interactive (`.pill.crm-task-note-trigger` on a `<button>` with `data-crm-note-trigger`), so it is intentionally protected.

- **Examples of safe conversion pattern (reference only)**
  - Canonical static-label migration remains: `<p class="pill">…</p>` → `<span class="crm-chip crm-chip--status|urgent">…</span>`.
  - Existing Phase 3 reminder-group conversions (`Overdue` / `Upcoming`) continue to represent the approved low-risk pattern.

- **Explicitly skipped categories (risk control)**
  - Interactive/clickable pills and anything inside `<button>`/`<a>`.
  - JS/data-hook coupled elements (`[data-crm-*]`, `[data-peracrm-*]`), including note-trigger/popover flows.
  - Toggles/segmented controls (`.crm-view-toggle`, `.crm-type-toggle`).
  - Behavioral classes such as `.crm-task-note-trigger`, icon action controls, and items inside forms/AJAX flows (`.crm-task-action`, `data-crm-reminder-action-form`).

- **Validation summary**
  - Confirmed no new `.pill`→`.crm-chip` template edits were made where behavior might change.
  - Confirmed no layout/CSS compatibility changes were required in this phase because no additional static template conversions occurred.

## Phase 3.2 — hover inversion and interactive pill audit

- **Button hover behavior corrected (solid/inverse now authoritative)**
  - Normalized both `.btn--solid` and legacy `.btn--ghost` to use the same intentional control language:
    - default: filled with assigned variant color (`--btn-color`)
    - hover/focus-visible: inverse (light surface + colored text/border)
    - active: inverse/pressed variant (stronger light pressed background)
  - Removed the previous weak ghost hover treatment based on `filter: brightness(...)` in CRM button scope.
  - Kept focus-visible ring behavior intact.

- **Zones audited and aligned**
  - Client summary/header action controls and quick actions.
  - Status and reminder action buttons (including reminder toast Undo treatment).
  - Danger dialog action rows.
  - Linked workspace toolbar/portfolio action controls (including theme portfolio toggle region).
  - Archive “See more/See less” button-like toggles.
  - View/type toggle groups in compact operational zones.
  - Logs utility actions in dark mode.

- **Dark mode contrast corrections**
  - Updated dark-mode button behavior to keep the same filled-default + inverse-hover logic rather than dim/transparent-only hover behavior.
  - Ensured hover/active states remain visible and readable (no dark-on-dark text/background collisions in audited button zones).

- **Interactive `.pill` decision**
  - Remaining template-scope `.pill` usage is still `.pill.crm-task-note-trigger` (interactive `<button>` with `data-crm-note-trigger`).
  - Cross-check against `crm.js` confirms this trigger is behavior-coupled to note-popover open/close state (`[data-crm-note-wrap]`, `[data-crm-note-trigger]`, `[data-crm-note-popover]`).
  - Classification: **safe to restyle only**.
  - Action taken: retained markup and JS/data hooks unchanged, but normalized trigger visuals through CSS to match filled/inverse interaction language.

- **Intentionally deferred (risk control)**
  - No structural migration of `.crm-task-note-trigger` markup/class stack.
  - No JS edits and no changes to selector/data-hook/event contracts.

### Phase 3.2 correction — explicit hover/active border preservation

- Corrected hover/active border preservation so normalized inverse states now explicitly keep a **1px solid border** in each control’s own color family (`--btn-color`/brand variants) instead of relying on inherited border declarations.
- Applied parity updates across:
  - core `.btn--solid` + compatibility `.btn--ghost` states (light + dark mode),
  - normalized button-like controls (`.archive-hero-desc__toggle`, `.crm-reminder-toast__undo.btn`, `.crm-task-note-trigger`),
  - logs utility buttons sharing the same interaction language.
- Scope remained CSS-only with no selector renames/removals, markup changes, or JS/data-hook changes.
