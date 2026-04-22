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
