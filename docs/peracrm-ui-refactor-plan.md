# PeraCRM UI Refactor Plan

## 1. Objective
PeraCRM’s front-end CRM workspace needs a structural refactor across the `peracrm` plugin’s frontend shell, shared partials, route templates, and CRM asset bundle so the product behaves like an operational app instead of a marketing-style extension. The current implementation still centers the experience around a hero header, plush panel shells, pill-heavy metadata, and page-specific patterns spread across the shared header partial, side navigation partial, route templates, and shared `crm.css` / `crm.js` assets. The refactor therefore targets the plugin’s existing CRM rendering path: `inc/frontend/view-loader.php`, the shared partials in `inc/views/partials/`, page templates in `inc/views/pages/`, and the shared frontend assets in `assets/frontend/`.

This refactor is necessary because the audit identifies high-severity layout problems before purely visual issues: the global page model pushes working content too far down, detail pages use unstable multi-panel reading order, lists lack a single canonical triage model, and responsive behavior currently protects the existing layout instead of simplifying it. The UI system definition further makes clear that PeraCRM should operate as a desktop-primary CRM app shell with compact headers, predictable scan paths, explicit action hierarchy, and intentional mobile reductions.

Success means the plugin delivers one coherent CRM shell and a small set of reusable primitives that can be rolled out page by page without a rewrite. In practice, that means: compact app-level headers replace hero headers; list pages converge on one default desktop pattern and one mobile/tablet fallback pattern; record detail layouts become ordered and non-masonry; pipeline cards become denser and more semantic; forms are grouped by decision task; and every phase includes responsive behavior as part of the implementation definition rather than as a post-refactor cleanup.

---

## 2. Refactor Strategy
The refactor will follow a strict structure-first, primitive-first, rollout-safe approach anchored to the existing `peracrm` plugin architecture.

1. **Structure before styling**
   - The first work is not color, radius, or polish.
   - The first work is replacing the page model: hero header to compact app header, unstable section arrangements to ordered layouts, and inconsistent list anatomy to canonical structures.
   - Visual polish is deferred until the hierarchy, containment, and responsive behavior are correct.

2. **Reusable primitives before page rewrites**
   - Shared primitives must be introduced in `assets/frontend/crm.css`, with only the minimum supporting behavior in `assets/frontend/crm.js`, before heavily rewriting page-specific templates.
   - Page templates should consume the same primitive classes and markup conventions instead of adding new one-off section wrappers.
   - If a layout need appears on more than one screen, it becomes a primitive first.

3. **Incremental rollout, not full rewrite**
   - The refactor should preserve existing data-loading and routing paths.
   - Each phase should ship against the current templates and assets, replacing isolated structural zones one by one.
   - Shared layout changes should land first where they unlock several pages at once, then individual screens can be migrated behind those primitives.
   - No phase should require simultaneous replacement of overview, lists, detail, pipeline, and forms.

4. **Regression avoidance**
   - Keep PHP route and data-provider logic intact wherever possible; prefer markup-layer and shared-style refactors over business-logic churn.
   - Introduce new CSS primitives in parallel with existing classes, then migrate templates incrementally.
   - Treat `crm.js` behavior as a contract: existing drawer navigation, view-toggle behavior, and any DOM-dependent scripts must be checked before changing markup.
   - Validate each phase at desktop, tablet, and mobile before moving on.
   - Use page-by-page adoption so regressions are contained to one route family at a time.

5. **Plugin-aware implementation approach**
   - Shared shell concerns should be handled in `crm-header.php`, `crm-side-nav.php`, and `crm.css`.
   - Screen-specific markup should be handled in `crm-overview.php`, `crm-client.php`, `crm-pipeline.php`, `crm-new.php`, and the leads/tasks list paths that currently live in the overview route template.
   - Shared behavior adjustments should be isolated in `crm.js` only when a new primitive truly needs it.

---

## 3. Non-Negotiable Rules
1. **No hero-style headers in CRM.**
   - Operational CRM routes must use compact page headers only.
   - The shared CRM header partial cannot continue to render a tall branded hero for overview, lists, detail, pipeline, or forms.

2. **No masonry layouts for record detail.**
   - Client detail must have a stable, explicit reading order.
   - Desktop may use a main column plus a purpose-built secondary rail, but never a free-flowing card cloud.

3. **No pill overuse for metadata.**
   - Pills are restricted to meaningful statuses, selected states, or narrow tag cases.
   - Source, advisor, timestamps, derived type, and similar metadata should usually render as text rows, keyed facts, or compact summary items.

4. **No page-specific one-off UI systems when a reusable primitive should exist.**
   - If a page needs a titled section, summary header, action cluster, table wrapper, row list, or toolbar, it must use or extend a shared primitive.
   - New ad hoc wrappers should be treated as a refactor failure unless the need is truly unique.

5. **Responsive behavior must be designed inside each phase, not after it.**
   - Every primitive and every page migration must define desktop, tablet, and mobile behavior before implementation is considered complete.
   - “We will fix mobile later” is not acceptable for any phase.

6. **Action hierarchy must stay explicit.**
   - One dominant primary action per page or section.
   - Toggles, view switches, and utility actions must not look like submit buttons.

7. **Canonical patterns beat local convenience.**
   - Leads/clients should not continue to have equally supported card and table paradigms on desktop.
   - `.crm-list` cannot keep serving as a generic catch-all for incompatible list anatomies.

---

## 4. Phase Plan

### Phase 1 — App shell and page-header foundation
- **Goal**
  - Replace the CRM-wide hero-first page model with a compact app shell and page-header system that works across overview, list, detail, pipeline, and form routes.
- **Exact UI problems being solved**
  - Tall hero pushing content below the fold.
  - Duplicate context between sidebar and hero.
  - Filters embedded in hero treatment.
  - Inconsistent placement of title, primary action, and working controls.
- **Affected pages**
  - Overview.
  - Leads/clients list.
  - Tasks.
  - Client detail.
  - Pipeline.
  - Create/edit screens.
- **Likely files involved**
  - `wp-content/plugins/peracrm/inc/views/partials/crm-header.php`
  - `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`
  - `wp-content/plugins/peracrm/assets/frontend/crm.css`
  - `wp-content/plugins/peracrm/assets/frontend/crm.js`
  - Route templates that pass header args.
- **Type of work**
  - Mixed: markup + CSS + light JS.
- **Dependencies**
  - None; this is the foundation phase.
- **Risk level**
  - Medium, because the shared header affects all CRM routes.
- **Expected UX impact**
  - Immediate gain in scan speed and above-the-fold usability.
  - Establishes a consistent title/action region for all later phases.
- **Validation criteria before moving to next phase**
  - All CRM routes render with a compact header instead of a hero.
  - Desktop keeps persistent nav; tablet/mobile use drawer behavior cleanly.
  - Primary page action is visible in the first scan pass.
  - Leads/clients filters move into a toolbar-capable region rather than hero content.
  - No broken navigation toggle behavior in `crm.js`.

### Phase 2 — Core data display primitives
- **Goal**
  - Introduce the shared primitives needed to replace ad hoc shells, pills, lists, and section wrappers before screen-level rewrites accelerate.
- **Exact UI problems being solved**
  - Overuse of `.card-shell` for everything.
  - Overloaded `.crm-list` across incompatible use cases.
  - No standardized toolbar, section, summary header, or action group primitives.
  - Weak containment and inconsistent density.
- **Affected pages**
  - All CRM routes, because the primitives are shared.
- **Likely files involved**
  - `wp-content/plugins/peracrm/assets/frontend/crm.css`
  - `wp-content/plugins/peracrm/assets/frontend/crm.js`
  - Partial/template files updated only where needed for early adoption.
- **Type of work**
  - Mixed, mostly CSS and markup conventions with selective JS support.
- **Dependencies**
  - Phase 1 page-header foundation.
- **Risk level**
  - Medium-low if introduced alongside existing classes.
- **Expected UX impact**
  - Improves consistency immediately, even before all pages are fully rewritten.
  - Creates a controlled language for later phases.
- **Validation criteria before moving to next phase**
  - Shared primitives exist and are documented in the code comments / implementation notes.
  - At least one page uses each foundational primitive successfully.
  - Primitive styles work at desktop, tablet, and mobile without page-specific overrides.

### Phase 3 — Record detail refactor (client view)
- **Goal**
  - Rebuild the client detail page around a stable summary-first structure with explicit workflow ordering.
- **Exact UI problems being solved**
  - Header strip overloaded with pills.
  - Unstable detail scan order.
  - Too many equal-weight card shells.
  - Notes, reminders, profile, linked properties, and activity competing visually.
- **Affected pages**
  - Client detail route.
- **Likely files involved**
  - `wp-content/plugins/peracrm/inc/views/pages/crm-client.php`
  - `wp-content/plugins/peracrm/assets/frontend/crm.css`
  - `wp-content/plugins/peracrm/assets/frontend/crm.js`
- **Type of work**
  - Mixed.
- **Dependencies**
  - Phase 1 header foundation.
  - Phase 2 primitives: `crm-summary-header`, `crm-section`, `crm-action-group`, `crm-chip`, `crm-row-list`, potentially `crm-table`.
- **Risk level**
  - High, because `crm-client.php` is dense and likely interwoven with existing form and interaction states.
- **Expected UX impact**
  - High; this is the most important structural correction after the shell.
- **Validation criteria before moving to next phase**
  - Record identity, status, owner, and next actions appear in one summary block.
  - Reminders/tasks, profile, notes, activity, and related items follow a predictable order.
  - No masonry-like cross-column reading dependency remains.
  - Tablet collapses to one intentional column; mobile uses stacked sections/accordions where appropriate.
  - Existing submission and inline action flows still work.

### Phase 4 — Dashboard / overview refactor
- **Goal**
  - Turn the overview page into a command-center layout that prioritizes urgency over passive metrics.
- **Exact UI problems being solved**
  - Equal-weight stacks of cards.
  - New leads and tasks consuming too much space for too little data.
  - KPI tiles and activity competing with urgent work.
- **Affected pages**
  - Overview/dashboard route.
- **Likely files involved**
  - `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php`
  - `wp-content/plugins/peracrm/assets/frontend/crm.css`
- **Type of work**
  - Mixed, mostly markup + CSS.
- **Dependencies**
  - Phase 1 and Phase 2.
  - Helpful reuse from Phase 3 if summary/header conventions are already proven.
- **Risk level**
  - Medium.
- **Expected UX impact**
  - High on daily efficiency: overdue and due-today work becomes easier to act on.
- **Validation criteria before moving to next phase**
  - Page order is title/action -> KPI strip -> overdue -> due today -> new leads -> activity/passive metrics.
  - New leads and tasks use denser structured rows or compact cards, not fluffy marketing cards.
  - Mobile deprioritizes passive sections without losing core actions.

### Phase 5 — List pages and tasks refactor
- **Goal**
  - Standardize leads/clients and tasks into a canonical list-page model with a shared toolbar and structured row fallback.
- **Exact UI problems being solved**
  - Unstable duality between cards and tables.
  - Filters living in the wrong region.
  - Weak row action hierarchy.
  - Table density and hierarchy not yet strong enough.
- **Affected pages**
  - Leads list.
  - Clients list.
  - Tasks list.
- **Likely files involved**
  - `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php` if list rendering remains there.
  - Any additional list-specific page templates present in the plugin routing layer.
  - `wp-content/plugins/peracrm/assets/frontend/crm.css`
  - `wp-content/plugins/peracrm/assets/frontend/crm.js`
- **Type of work**
  - Mixed.
- **Dependencies**
  - Phase 1 header + toolbar foundation.
  - Phase 2 `crm-toolbar`, `crm-table`, `crm-row-list`, `crm-action-group`.
- **Risk level**
  - Medium-high because list pages may share view-toggle behavior already wired in `crm.js`.
- **Expected UX impact**
  - High for triage efficiency.
- **Validation criteria before moving to next phase**
  - Desktop has one clear canonical list pattern per page.
  - Tablet/mobile convert to structured row lists at defined breakpoints.
  - Search, filters, saved-view/type controls, and primary CTA occupy the same predictable toolbar zone.
  - Row actions remain discoverable and non-dominant.

### Phase 6 — Pipeline refactor
- **Goal**
  - Refactor the pipeline into a compact, stage-first board with a controlled card anatomy and responsive stage navigation.
- **Exact UI problems being solved**
  - Pipeline cards too fluffy and pill-heavy.
  - Metadata hierarchy is weak.
  - Horizontal board does not yet have a strong responsive fallback model.
- **Affected pages**
  - Pipeline route.
- **Likely files involved**
  - `wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php`
  - `wp-content/plugins/peracrm/assets/frontend/crm.css`
  - `wp-content/plugins/peracrm/assets/frontend/crm.js`
- **Type of work**
  - Mixed.
- **Dependencies**
  - Phase 1 shell.
  - Phase 2 primitives, especially `crm-toolbar`, `crm-chip`, `crm-action-group`, `crm-pipeline-card`.
- **Risk level**
  - Medium.
- **Expected UX impact**
  - Medium-high; especially valuable for advisors and managers scanning movement opportunities.
- **Validation criteria before moving to next phase**
  - Desktop stage board is compact and readable.
  - Tablet uses horizontal stage scroll or tabs intentionally.
  - Mobile uses stage sections/tabs rather than miniaturized kanban columns.
  - Card metadata is mostly text hierarchy, not pill clutter.

### Phase 7 — Create / edit screens
- **Goal**
  - Rework create/edit views into grouped form sections with predictable save actions and calmer control styling.
- **Exact UI problems being solved**
  - Field groups feel isolated.
  - Labels are too shouty.
  - Some control treatments resemble status pills instead of form controls.
  - Save/cancel placement may be inconsistent.
- **Affected pages**
  - Create lead.
  - Edit/update sections on client detail where forms are embedded.
- **Likely files involved**
  - `wp-content/plugins/peracrm/inc/views/pages/crm-new.php`
  - `wp-content/plugins/peracrm/inc/views/pages/crm-client.php`
  - `wp-content/plugins/peracrm/assets/frontend/crm.css`
  - `wp-content/plugins/peracrm/assets/frontend/crm.js`
- **Type of work**
  - Mixed.
- **Dependencies**
  - Phase 1 shell.
  - Phase 2 `crm-form-section`, `crm-action-group`, `crm-chip` restricted for selectable states only.
- **Risk level**
  - Medium-high because forms carry submission and validation risk.
- **Expected UX impact**
  - Medium-high; improves completion speed and lowers form fatigue.
- **Validation criteria before moving to next phase**
  - Forms are grouped by operational decisions.
  - Required fields and primary save action remain obvious above the fold.
  - Long forms have a clear submit anchor or sticky action area.
  - Mobile collapses complexity without hiding critical fields.

### Phase 8 — Responsive hardening and polish
- **Goal**
  - Normalize responsive edge cases and apply restrained visual polish only after structural consistency is proven.
- **Exact UI problems being solved**
  - Breakpoint-specific gaps, awkward wraps, noisy metadata remnants, and inconsistent density.
  - Remaining decorative debt from legacy classes.
- **Affected pages**
  - All CRM routes.
- **Likely files involved**
  - `wp-content/plugins/peracrm/assets/frontend/crm.css`
  - `wp-content/plugins/peracrm/assets/frontend/crm.js`
  - Any page templates still carrying legacy markup wrappers.
- **Type of work**
  - Mixed, mostly CSS with selective markup cleanup.
- **Dependencies**
  - Phases 1-7 complete or substantially landed.
- **Risk level**
  - Low-medium if structural work is already stable.
- **Expected UX impact**
  - Medium; this phase makes the system feel complete and intentional.
- **Validation criteria before moving to next phase**
  - Responsive behavior is consistent across all route types.
  - Legacy pill/card-shell usage is reduced to approved cases.
  - Typography, spacing, borders, radii, and action hierarchy match the defined system across pages.
  - No regressions remain in navigation, forms, list actions, or route-specific JS.

---

## 5. Screen-by-Screen Work Breakdown

### Overview / dashboard
- **Current issues (based on audit)**
  - Urgent work is not consistently first.
  - New leads and task cards waste space.
  - KPI, activity, notices, and queues all use similar shell weight.
  - Visual order feels like stacked content modules rather than a command center.
- **Target state (based on UI system definition)**
  - Compact page header with primary CTA.
  - KPI strip as a quick top summary.
  - Overdue tasks first, then due today, then new leads, then recent activity and passive metrics.
  - Dense structured queue presentation with stable headers and clear next actions.
- **Required primitives/components**
  - `crm-page-header`
  - `crm-kpi-strip`
  - `crm-section`
  - `crm-row-list`
  - `crm-action-group`
  - Potential `crm-chip` for overdue state only.
- **Likely markup changes**
  - Reorder sections in `crm-overview.php`.
  - Replace nested card grids with queue rows or denser list items.
  - Introduce explicit section headers with trailing actions.
- **Likely CSS changes**
  - KPI strip layout.
  - Queue row density.
  - Reduced decorative framing on lower-priority sections.
- **Likely JS changes**
  - Minimal; maybe only for collapsible lower-priority sections on tablet/mobile.
- **Breakpoint considerations (desktop/tablet/mobile)**
  - Desktop: 2-column command center allowed, but urgent queue stays dominant.
  - Tablet: single primary column with KPI strip retained.
  - Mobile: overdue, due today, and new leads remain above the fold; activity moves lower.
- **Rollout notes**
  - Good candidate after client detail because it is structurally simpler and highly visible.

### Clients / leads list
- **Current issues (based on audit)**
  - Hero filters are in the wrong place.
  - Cards and tables compete as equal patterns.
  - Card view omits useful triage information.
  - Table hierarchy and action placement need strengthening.
- **Target state (based on UI system definition)**
  - Compact header plus toolbar.
  - Desktop default is table.
  - Tablet/mobile convert to structured row list.
  - Search, filters, type controls, and CTA live in one predictable toolbar zone.
- **Required primitives/components**
  - `crm-page-header`
  - `crm-toolbar`
  - `crm-table`
  - `crm-row-list`
  - `crm-action-group`
  - Restricted `crm-chip` for real status/stage markers.
- **Likely markup changes**
  - Remove hero filter form usage from `crm-header.php` for list pages.
  - Move filter/search controls into list page toolbar markup.
  - Normalize row anatomy so identity, status, owner, and timestamps appear in a stable structure.
- **Likely CSS changes**
  - Table header and row hierarchy.
  - Row-list fallback styles.
  - Segmented/toggle control styling if type selectors remain.
- **Likely JS changes**
  - Revisit existing view-toggle behavior.
  - Keep only behaviors that still match the canonical desktop/tablet/mobile patterns.
- **Breakpoint considerations (desktop/tablet/mobile)**
  - Desktop: full table.
  - Tablet: curated narrow table only if readable; otherwise structured row list.
  - Mobile: row list with one quick action and overflow for secondary actions.
- **Rollout notes**
  - Avoid keeping decorative card mode as a first-class desktop option after refactor.

### Tasks
- **Current issues (based on audit)**
  - Tasks appear both as overview cards and list/table-like structures with inconsistent density.
  - Overdue importance can be diluted by similar styling elsewhere.
  - Action hierarchy around “Mark done” is not always clean.
- **Target state (based on UI system definition)**
  - Tasks become a triage-first list page with obvious due state, owner/client context, and next action.
  - Desktop uses table or highly structured rows; smaller screens use row list only.
- **Required primitives/components**
  - `crm-page-header`
  - `crm-toolbar`
  - `crm-table`
  - `crm-row-list`
  - `crm-action-group`
  - Restricted `crm-chip` for overdue or status state.
- **Likely markup changes**
  - Normalize task row anatomy across overview and task list.
  - Surface due date, note summary, related client, and primary completion action consistently.
- **Likely CSS changes**
  - Overdue styling.
  - Compact row spacing and action alignment.
- **Likely JS changes**
  - Likely minimal unless task filtering or responsive collapse needs simple toggles.
- **Breakpoint considerations (desktop/tablet/mobile)**
  - Desktop: dense rows.
  - Tablet: narrower row list with metadata wrapping rules.
  - Mobile: one-line identity plus one metadata line plus quick action.
- **Rollout notes**
  - Align this work with list-page refactor so task and client lists share the same primitive system.

### Client detail
- **Current issues (based on audit)**
  - Header strip is pill-heavy and visually noisy.
  - Sections compete with equal-weight card shells.
  - Workflow order is unstable.
  - Responsive behavior risks turning a weak desktop layout into an even longer mobile scroll.
- **Target state (based on UI system definition)**
  - Summary header with identity, status, owner, and action group.
  - Ordered sections: reminders/tasks, profile, notes, activity, related records.
  - Optional supporting rail only when content is clearly secondary and compact.
- **Required primitives/components**
  - `crm-page-header`
  - `crm-summary-header`
  - `crm-section`
  - `crm-kpi-strip` or compact facts strip
  - `crm-action-group`
  - `crm-row-list`
  - `crm-table`
  - Restricted `crm-chip`
  - `crm-form-section`
- **Likely markup changes**
  - Replace header pill cloud with summary lanes.
  - Reorder section markup in `crm-client.php`.
  - Convert repeated cards to section blocks with internal dividers.
  - Introduce accordions only for lower-priority content on smaller breakpoints.
- **Likely CSS changes**
  - Summary header layout.
  - Section containment and spacing.
  - Metadata de-emphasis and action alignment.
- **Likely JS changes**
  - Accordion/drawer behavior for mobile sections if introduced.
  - Careful handling of any existing DOM-coupled interactions.
- **Breakpoint considerations (desktop/tablet/mobile)**
  - Desktop: main column plus optional compact supporting rail.
  - Tablet: single dominant column, supporting content shifted downward or collapsible.
  - Mobile: compressed summary, top tasks/status retained above the fold, lower sections stack or collapse.
- **Rollout notes**
  - Highest-value page-specific refactor; safest after primitive foundation is proven.

### Pipeline
- **Current issues (based on audit)**
  - Cards overuse pills and decorative containment.
  - Metadata hierarchy is flattened.
  - Responsive path is underdefined.
- **Target state (based on UI system definition)**
  - Compact desktop board with stage counts and denser card anatomy.
  - Tablet uses controlled horizontal scroll or stage tabs.
  - Mobile uses stage-by-stage sections or tabs.
- **Required primitives/components**
  - `crm-page-header`
  - `crm-toolbar`
  - `crm-pipeline-card`
  - `crm-action-group`
  - Restricted `crm-chip`
  - `crm-section` for mobile stage groups.
- **Likely markup changes**
  - Simplify card internals.
  - Tighten stage header/count markup.
  - Prepare mobile alternate stage presentation without changing underlying data model.
- **Likely CSS changes**
  - Column density.
  - Card spacing and metadata hierarchy.
  - Responsive stage container behavior.
- **Likely JS changes**
  - Tabs or stage navigation support on tablet/mobile if introduced.
- **Breakpoint considerations (desktop/tablet/mobile)**
  - Desktop: board with compact columns.
  - Tablet: board with constrained visible columns or stage tabs.
  - Mobile: one stage at a time or stacked sections; no mini board.
- **Rollout notes**
  - Refactor after list/detail patterns are stable so pipeline can reuse shared action and chip rules.

### Create / edit screens
- **Current issues (based on audit)**
  - Form sections feel generic and isolated.
  - Labels are too forceful.
  - Some control patterns visually resemble chips/status tokens.
  - Long-form hierarchy is weak.
- **Target state (based on UI system definition)**
  - Compact page header.
  - Decision-grouped form sections.
  - Clear save/cancel area.
  - Sentence-case labels and calmer inputs.
- **Required primitives/components**
  - `crm-page-header`
  - `crm-form-section`
  - `crm-action-group`
  - Restricted `crm-chip` only where selectable chips are truly appropriate.
  - `crm-section`.
- **Likely markup changes**
  - Group fields into meaningful sections in `crm-new.php` and embedded edit regions.
  - Consolidate action areas.
- **Likely CSS changes**
  - Form section spacing.
  - Label styles.
  - Input density and grouped actions.
- **Likely JS changes**
  - Minimal unless sticky save behavior or collapsible sections are introduced.
- **Breakpoint considerations (desktop/tablet/mobile)**
  - Desktop: single main column or narrow two-column grid only for related short fields.
  - Tablet: one column with persistent save access.
  - Mobile: one column only; accordions acceptable for long optional sections.
- **Rollout notes**
  - This should come after shared section/action primitives are mature, because forms punish inconsistent patterns quickly.

---

## 6. Reusable Primitive Build Order

1. **`crm-page-header`**
   - **Why it is needed at that stage**
     - It is the replacement for the CRM hero and unlocks every route.
   - **What it enables**
     - Compact title/action layout, inline counts/context, toolbar adjacency, and responsive header compression.
   - **Which phases depend on it**
     - Phases 1-8.

2. **`crm-action-group`**
   - **Why it is needed at that stage**
     - Action hierarchy is currently muddled; this primitive defines primary vs secondary vs utility grouping early.
   - **What it enables**
     - Consistent button clusters in headers, sections, rows, and forms.
   - **Which phases depend on it**
     - Phases 1, 3, 4, 5, 6, 7, 8.

3. **`crm-toolbar`**
   - **Why it is needed at that stage**
     - List and pipeline screens need a canonical working-controls region as soon as the hero is removed.
   - **What it enables**
     - Search, filters, segmented view/type controls, counts, and secondary actions in one horizontal band.
   - **Which phases depend on it**
     - Phases 1, 5, 6, 8.

4. **`crm-section`**
   - **Why it is needed at that stage**
     - The current UI relies too heavily on generic card shells.
   - **What it enables**
     - Consistent titled sections with optional header actions and one containment strategy.
   - **Which phases depend on it**
     - Phases 2, 3, 4, 6, 7, 8.

5. **`crm-chip` (restricted use)**
   - **Why it is needed at that stage**
     - The system needs a sanctioned metadata token primitive precisely so pill use can be limited and semantically controlled.
   - **What it enables**
     - Consistent status, urgency, and selected-state markers without allowing chip sprawl.
   - **Which phases depend on it**
     - Phases 2, 3, 5, 6, 7, 8.

6. **`crm-kpi-strip`**
   - **Why it is needed at that stage**
     - Overview and detail pages both need compact summary facts that do not become oversized cards.
   - **What it enables**
     - Dense KPI rows, top-of-page metrics, and compact summary stats.
   - **Which phases depend on it**
     - Phases 3, 4, 8.

7. **`crm-table`**
   - **Why it is needed at that stage**
     - List-page refactor cannot be done well without a canonical desktop table pattern.
   - **What it enables**
     - Stronger headers, denser rows, predictable action columns, and consistent status styling.
   - **Which phases depend on it**
     - Phases 5, 3, 8.

8. **`crm-row-list`**
   - **Why it is needed at that stage**
     - Responsive fallback must be built at the same time as table/list work.
   - **What it enables**
     - Tablet/mobile structured rows for leads, tasks, and related records.
   - **Which phases depend on it**
     - Phases 4, 5, 3, 6, 8.

9. **`crm-summary-header`**
   - **Why it is needed at that stage**
     - Client detail requires a purpose-built record summary primitive, not another section shell.
   - **What it enables**
     - Identity lane, status/facts lane, and action lane for record detail screens.
   - **Which phases depend on it**
     - Phases 3, 8.

10. **`crm-form-section`**
    - **Why it is needed at that stage**
      - Form cleanup should happen after the structural primitives are stable.
    - **What it enables**
      - Grouped fields, calmer section headings, and reusable save-action placement.
    - **Which phases depend on it**
      - Phases 7, 3, 8.

11. **`crm-pipeline-card`**
    - **Why it is needed at that stage**
      - Pipeline needs its own card anatomy once the shared list/action/chip system is established.
    - **What it enables**
      - Dense stage cards with controlled metadata lines and action affordances.
    - **Which phases depend on it**
      - Phases 6, 8.

---

## 7. Breakpoint and Responsive Plan
This refactor must follow the CRM-specific breakpoint model from the UI system definition and make responsiveness a phase-level requirement rather than a polish pass.

### Global responsive rules
- **Desktop**
  - Primary design target.
  - Persistent left navigation remains visible.
  - Page header stays compact and horizontally organized.
  - Dense tables and stage boards are allowed where comparison matters.
- **Tablet**
  - Navigation becomes drawer/rail.
  - Page headers may become two-row units, but title and primary action must still be visually linked.
  - Secondary controls collapse before core identity or primary action does.
- **Mobile**
  - Single-column task-first layout.
  - Search remains visible on list screens; secondary filters collapse.
  - Only top-priority summary and one primary action stay above the fold.
  - Long desktop structures must convert, not just stack.

### What compresses vs what reflows vs what collapses
- **Compresses**
  - Page-header spacing, section padding, KPI strip gutters, action-group spacing, table row height, and metadata text size.
- **Reflows**
  - Header actions can move below the title.
  - KPI strips can wrap into two rows on tablet.
  - Summary facts can shift from horizontal strips to stacked key/value rows.
- **Collapses**
  - Filter sets beyond search.
  - Secondary section utilities.
  - Lower-priority detail sections.
  - Stage navigation for pipeline.
  - Overflow row actions.

### When tables convert to row lists
- Desktop (`>= 1200px`): tables remain default for leads/clients and tasks.
- Compact desktop/tablet landscape (`992px - 1199px`): tables remain only if the column set is explicitly reduced to a readable core; otherwise convert to row list.
- Tablet portrait and below (`< 992px`): clients/leads and tasks should default to `crm-row-list`.
- Mobile (`< 768px`): structured row list only.

### When sections become tabs, accordions, or drawers
- **Tabs**
  - Pipeline stages on tablet/mobile when horizontal board density breaks down.
- **Accordions**
  - Client detail lower-priority sections on tablet/mobile.
  - Long optional form sections on mobile.
- **Drawers / sheets**
  - Navigation on tablet/mobile.
  - Non-primary filters on list pages and pipeline.

### Which elements must remain above the fold
- **Overview**
  - Page title, create-lead action, KPI strip, overdue work.
- **List pages**
  - Page title/count, search, one primary action, and first rows of records.
- **Client detail**
  - Record identity, current status/owner, and primary next actions.
- **Pipeline**
  - Page title, filter context, visible stage labels/counts, and first cards in priority stages.
- **Create/edit**
  - Page title, required opening fields, and the primary save path.

### Where mobile should diverge from desktop patterns
- Lists should not preserve desktop tables.
- Pipeline should stop behaving like a board and become stage sections/tabs.
- Client detail should collapse lower-priority sections instead of showing every panel in full sequence.
- Toolbar controls should prioritize search and the dominant action, with filters moved to an expandable control.
- Form pages may use accordions for optional sections, which are not necessary on desktop.

### Desktop behavior
- Persistent sidebar remains the app landmark.
- Page-header and toolbar remain separate but adjacent layers.
- Overview may use a command-center split layout, but urgent work must dominate width and order.
- Detail pages may use a main column plus compact support rail only when the rail has a clear function.
- Tables and pipeline boards are preferred where comparison/scanning matters.

### Tablet behavior
- Sidebar becomes a drawer.
- Page header may wrap into two rows, but primary action stays near the title.
- Toolbars keep search visible while secondary filters collapse.
- Dashboard becomes mostly single-column.
- Detail pages stack sections intentionally, with accordions allowed for lower-priority areas.
- Pipeline uses horizontal scroll or stage tabs depending on readability.

### Mobile behavior
- Single-column flow.
- Header is reduced to title + essential action.
- Search remains visible; filters go to expandable panel or drawer.
- Lists use row-list anatomy with one quick action.
- Detail pages keep summary and next action at top, then stack/collapse the rest.
- Pipeline uses stage tabs or stacked sections only.
- Forms are one column with obvious save controls and reduced decorative treatment.

### Per-screen responsive behavior
- **Overview / dashboard**
  - Desktop: KPI strip + urgent queues dominant.
  - Tablet: one column, KPI strip wraps, lower-priority activity collapsible if needed.
  - Mobile: overdue -> due today -> new leads remain first; activity and passive metrics move lower.
- **Clients / leads list**
  - Desktop: table with curated columns.
  - Tablet: table only if readable; otherwise row list.
  - Mobile: row list with status, owner/stage, last activity/due date, and overflow actions.
- **Tasks**
  - Desktop: dense rows or table.
  - Tablet: structured row list with clear due-state line.
  - Mobile: one quick action, status/due date visible without expansion.
- **Client detail**
  - Desktop: summary header + ordered sections; optional support rail.
  - Tablet: single column, support facts moved below summary, some sections collapsible.
  - Mobile: compressed summary, top reminders/actions first, lower sections as accordions where useful.
- **Pipeline**
  - Desktop: compact board.
  - Tablet: horizontal scroll or stage tabs.
  - Mobile: stage sections/tabs with compact cards.
- **Create / edit screens**
  - Desktop: narrow readable form width.
  - Tablet: single column, save action remains easy to reach.
  - Mobile: one field column, optional accordions for low-priority sections.

---

## 8. Technical Risk Notes
1. **Shared templates control multiple layouts**
   - `crm-header.php` currently drives the hero model across multiple page types.
   - `crm-overview.php` appears to handle overview plus list-related views, which increases blast radius for markup changes.
   - Refactoring these files incrementally is safer than replacing route structures outright.

2. **Tight coupling between markup and styling**
   - `crm.css` contains broad global CRM selectors tied to classes like `.card-shell`, `.pill`, `.section`, hero wrappers, and content panel shells.
   - This means layout changes can easily create visual regressions unless new primitives are introduced alongside legacy classes first.

3. **`crm.js` dependencies on layout structure**
   - Navigation drawer behavior depends on current header/nav toggle attributes and breakpoint assumptions.
   - Existing view-toggle logic and any DOM queries tied to current list markup can break if table/card structure changes abruptly.
   - Any new accordion, tabs, or filter-collapse behaviors should be additive and scoped.

4. **Overuse of global classes like `.card-shell` and `.pill`**
   - These classes are acting as de facto design primitives for unrelated components.
   - Full replacement in one pass is risky because the classes appear across overview, detail, and supporting components.
   - Safer approach: restrict future use, introduce new primitives, then migrate templates gradually.

5. **Incremental refactor is safer than full replacement in high-density templates**
   - `crm-client.php` likely carries the highest structural and interaction complexity.
   - Large screen-specific files should be refactored region by region: header, summary facts, task/reminder areas, notes/activity, related records, embedded forms.
   - Big-bang replacement would create markup, CSS, and behavior regression risk simultaneously.

6. **Legacy shell assumptions may linger outside the plugin**
   - Even though the active CRM assets exist in the plugin, theme-era CRM assets and past coupling history suggest care is needed before assuming styles or shell behavior are isolated.
   - Shared shell behavior should be verified against the actual frontend rendering path before deleting legacy-compatible wrappers.

7. **Responsive regressions are likely if desktop-only fixes land first**
   - The current code already has breakpoint handling, but it is tied to the hero/shell model.
   - Replacing header and layout structures without redefining responsive behavior at the same time will create broken wraps, hidden actions, or overly long pages on smaller screens.

---

## 9. Validation Checklist

### Phase 1 — App shell and page-header foundation
- Layout correctness
  - Compact page header replaces hero on all CRM routes.
  - Sidebar, header, and content hierarchy is consistent.
- Visual hierarchy
  - Title and primary action are obvious within one scan pass.
- Action clarity
  - “Create lead” and other dominant actions are not mixed into navigation-like controls.
- Consistency across pages
  - Overview, list, detail, pipeline, and forms use the same header logic.
- Responsiveness
  - Header compresses correctly on tablet/mobile.
  - Drawer nav still works at the correct breakpoint.
- Absence of regressions
  - No broken route rendering, nav focus traps, or hidden key actions.

### Phase 2 — Core data display primitives
- Layout correctness
  - New primitives render consistently without relying on legacy hero/card assumptions.
- Visual hierarchy
  - Primitives reinforce title/metadata/action differences.
- Action clarity
  - Action groups distinguish dominant vs secondary actions.
- Consistency across pages
  - Shared section/table/row-list primitives appear identical in equivalent contexts.
- Responsiveness
  - Every primitive has desktop/tablet/mobile behavior defined and working.
- Absence of regressions
  - Legacy classes still render acceptably where migration is incomplete.

### Phase 3 — Record detail refactor
- Layout correctness
  - Client detail has a stable top-to-bottom order.
  - No masonry or unordered card cloud remains.
- Visual hierarchy
  - Record name, current status, and next action dominate secondary metadata.
- Action clarity
  - Primary actions are grouped and distinct from utilities/destructive actions.
- Consistency across pages
  - Summary header follows the same system language as page headers and sections.
- Responsiveness
  - Tablet and mobile preserve the same priority order with intentional collapse patterns.
- Absence of regressions
  - Existing forms, reminders, notes, and inline actions still submit and display correctly.

### Phase 4 — Dashboard / overview refactor
- Layout correctness
  - Urgent queues appear before passive metrics.
- Visual hierarchy
  - Overdue and due-today items are more prominent than activity and KPI decoration.
- Action clarity
  - Queue actions and “Create lead” are immediately visible.
- Consistency across pages
  - Overview sections use shared section and row/list primitives.
- Responsiveness
  - Mobile keeps urgent work above passive sections.
- Absence of regressions
  - Dashboard data still renders for notices, tasks, leads, activity, and KPIs.

### Phase 5 — List pages and tasks refactor
- Layout correctness
  - Lists use one canonical desktop pattern and one responsive fallback pattern.
- Visual hierarchy
  - Row identity, status, due date/stage, and owner are visually ordered.
- Action clarity
  - Primary row actions are easy to find; secondary actions recede.
- Consistency across pages
  - Leads/clients and tasks share toolbar and row/table conventions.
- Responsiveness
  - Table-to-row-list conversion occurs at defined breakpoints.
- Absence of regressions
  - Sorting, filters, search, and any view-mode behavior continue to work or are intentionally replaced.

### Phase 6 — Pipeline refactor
- Layout correctness
  - Stage counts, columns, and card anatomy are stable and readable.
- Visual hierarchy
  - Stage, record identity, and next-step context dominate low-value metadata.
- Action clarity
  - Pipeline actions are visible without competing with status display.
- Consistency across pages
  - Card metadata styling matches the broader CRM system.
- Responsiveness
  - Board becomes tabs/sections on smaller screens instead of shrinking indefinitely.
- Absence of regressions
  - Filtering and stage navigation remain functional.

### Phase 7 — Create / edit screens
- Layout correctness
  - Fields are grouped by decision task, not arbitrary order.
- Visual hierarchy
  - Required fields and section headings are obvious without excessive label noise.
- Action clarity
  - Save/cancel actions are consistently placed and readable.
- Consistency across pages
  - Form sections match section and action-group conventions used elsewhere.
- Responsiveness
  - Forms remain fillable and scannable on tablet/mobile without awkward multi-column remnants.
- Absence of regressions
  - Validation, submission, and embedded edit flows continue to function.

### Phase 8 — Responsive hardening and polish
- Layout correctness
  - No awkward wraps, clipped actions, or spacing anomalies remain.
- Visual hierarchy
  - Type, spacing, and containment feel consistent across all routes.
- Action clarity
  - Primary/secondary/utility distinctions remain intact across breakpoints.
- Consistency across pages
  - Shared primitives are used consistently; legacy exceptions are minimized.
- Responsiveness
  - Every route is validated at desktop, tablet, and mobile.
- Absence of regressions
  - Final QA finds no new shell, navigation, list, detail, pipeline, or form regressions.

---

## 10. File-Level Task Map

### `crm.css`
- **What will likely change**
  - Largest change surface.
  - Introduce new shared primitives, retire hero-first assumptions, reduce dependence on `.card-shell` / `.pill`, define density/hierarchy tokens, and add explicit breakpoint behaviors.
- **Type of change**
  - Structure + style.
- **Relative complexity**
  - High.

### `crm.js`
- **What will likely change**
  - Preserve nav drawer behavior, update selectors if header/nav structure changes, remove or revise view-toggle logic if canonical list behavior changes, and add any accordion/tab/filter-collapse support required by new primitives.
- **Type of change**
  - Behavior.
- **Relative complexity**
  - Medium.

### `crm-header.php`
- **What will likely change**
  - Major structural rewrite from hero header to compact `crm-page-header` with optional subtitle, count/context, primary action slot, and optional toolbar adjacency.
  - Client list filters should no longer be rendered as hero content.
- **Type of change**
  - Structure.
- **Relative complexity**
  - High.

### `crm-side-nav.php`
- **What will likely change**
  - Smaller IA and presentation adjustments so nav remains the app landmark while “Create lead” shifts out of primary navigation and into action space where appropriate.
  - Drawer/mobile nav may need alignment with the new page-header controls.
- **Type of change**
  - Structure + style semantics.
- **Relative complexity**
  - Medium.

### `crm-overview.php`
- **What will likely change**
  - Reorder dashboard sections, convert fluffy cards into denser queue/list primitives, separate overview structure from list-page toolbar/list concerns more clearly, and adopt new shared primitives.
- **Type of change**
  - Structure + style hookup.
- **Relative complexity**
  - High.

### `crm-client.php`
- **What will likely change**
  - Major record-detail restructuring, including summary header, section order, reduced pill usage, explicit action grouping, compact fact strips, and responsive section behavior.
- **Type of change**
  - Structure + style + behavior touchpoints.
- **Relative complexity**
  - Very high.

### `crm-pipeline.php`
- **What will likely change**
  - Stage-board markup cleanup, compact column/card anatomy, responsive stage navigation, and metadata de-emphasis.
- **Type of change**
  - Structure + style + possible behavior.
- **Relative complexity**
  - Medium-high.

### `crm-new.php`
- **What will likely change**
  - Group fields into reusable form sections, normalize action placement, reduce decorative wrappers, and improve responsive form flow.
- **Type of change**
  - Structure + style.
- **Relative complexity**
  - Medium.

---

## 11. Recommended First Implementation Slice
The best first implementation slice is the **global replacement of the CRM hero with a reusable `crm-page-header` app-shell pattern**.

### Why this slice gives high impact with low risk
- It fixes the highest-leverage structural issue identified by both the audit and UI system definition: the hero-first page model.
- It improves every screen at once without requiring data-model or route rewrites.
- It can be implemented primarily through shared partial and CSS changes, with only light `crm.js` alignment if needed.
- It creates the placement rules for title, count/context, primary action, and toolbar controls that every later phase depends on.

### What parts of the UI it affects
- Overview.
- Leads/clients list.
- Tasks.
- Client detail.
- Pipeline.
- Create/edit screens.
- Potentially mobile drawer/header interaction because the shell hierarchy changes.

### What primitives it introduces
- `crm-page-header`
- `crm-action-group`
- Early `crm-toolbar` scaffolding for routes that need inline controls immediately after the header.

### How it validates the system direction
- It proves that PeraCRM can behave like an operational app shell inside the existing plugin architecture.
- It forces clear decisions about where actions belong, where filters belong, and how responsive header compression works.
- It creates a measurable before/after improvement without a risky big-bang rewrite.
- If this slice lands cleanly, the rest of the roadmap becomes a sequence of page migrations onto an already-correct shell rather than a series of isolated cosmetic patches.
