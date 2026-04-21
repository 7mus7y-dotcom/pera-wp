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
