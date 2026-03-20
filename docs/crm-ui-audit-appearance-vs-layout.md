# CRM UI Audit

## 1. Executive Summary
- **Appearance — High:** The UI still reads more like a marketing-site extension than a dense operational CRM because the shared visual system relies on rounded hero cards, pill buttons, strong shadows, and oversized corner radii that reduce information density and make screens feel less workmanlike under heavy daily use.
- **Appearance — High:** Typography is only partially normalized; body copy is reasonably controlled, but headings, labels, KPI values, table headers, pills, and section titles do not form a disciplined desktop CRM type ladder, so information importance shifts from page to page instead of being immediately legible.
- **Appearance — High:** Button hierarchy is weak. Primary actions, row actions, view toggles, status changes, and navigation-like controls are all rendered through the same pill-button language, which flattens action priority and slows next-step recognition.
- **Appearance — Medium:** Tables, cards, and list rows use inconsistent density rules. Some screens are compact, while overview cards, lead cards, and task cards consume too much space for the amount of information shown.
- **Appearance — Medium:** Borders and separators are inconsistent: some modules use full card shells with shadows, some use list dividers, some use bordered chips inside bordered cards, creating layered visual noise instead of clear containment.
- **Layout — High:** The global page model is inconsistent. Overview, list, detail, and pipeline pages all share the same hero-first shell, but operational CRM screens usually need a compact title/action/filter bar rather than a large branded header that pushes working content down.
- **Layout — High:** The client detail page is structurally overloaded. Important workflow areas such as status, reminders, notes, and linked properties compete in a masonry-like column flow, which hurts predictability and makes scan order dependent on viewport height instead of workflow priority.
- **Layout — High:** The leads/clients list page forces an unstable content model by offering both sparse cards and dense tables for the same records, while the card version omits too much useful triage information and the table version lacks a stronger fixed action pattern.
- **Layout — Medium:** The dashboard favors stacked sections of cards over a tighter command-center layout; new leads, tasks, KPI snapshots, activity, and notifications do not resolve into a clear “what needs action first” order.
- **Layout — Medium:** Responsive handling is mostly protective rather than strategic. Components collapse safely, but some desktop patterns already feel inefficient, so the mobile changes often inherit the same structural weaknesses rather than solving them.

## 2. Benchmark Principles from Popular CRMs
Strong CRM products such as HubSpot, Pipedrive, and Salesforce generally converge on the same practical patterns even when their brand styling differs. The useful benchmark is not “modern-looking” in a generic sense; it is whether the UI helps a rep or operations user process a lot of records quickly without visual drag.

### What good appearance usually looks like
- **A restrained type ladder:** one compact body size, one small metadata size, one row/title size, and one page-heading size. Labels are readable but not louder than values.
- **Low-ornament surfaces:** most work areas use flat or lightly elevated white/neutral surfaces with subtle borders, not deep shadows and large radii.
- **Clear action hierarchy:** one visually dominant primary button, one secondary button style, one tertiary/link style, and icon-only utilities. Toggles should not look like submit actions.
- **Compact but comfortable density:** cards, rows, and form fields are optimized for scan speed, usually with 8/12/16/24 spacing rhythms rather than many near-duplicate spacings.
- **Metadata that recedes:** timestamps, source labels, advisor names, and status metadata are visible but quieter than names, stage, task status, and next actions.
- **State colors used sparingly:** red, green, warning, and success colors signal status, not general decoration.

### What good layout usually looks like
- **Compact page headers:** a title, key count/context, and primary actions in one horizontal band near the top of content.
- **Stable left-to-right scan order:** navigation on the side, content in the center, optional secondary context on the right only when needed.
- **Dashboard by urgency, not by content type:** overdue work and due-today work come before passive metrics.
- **List pages optimized for triage:** filters, saved views, sort, and density controls above one canonical list pattern, usually a table or highly structured rows.
- **Record pages optimized for action:** a summary header, sticky or obvious key actions, then grouped sections in a predictable order such as status, tasks, notes, activity, related records.
- **Pipelines that prioritize stage movement:** narrower cards, concise metadata, visible owners, activity freshness, and clear affordances for next action.
- **Forms grouped by decision task:** related fields appear together, long forms are chunked into clear sections, and submit actions anchor at expected positions.

### Practical evaluation criteria used in this audit
- Can a user identify the page purpose and primary action within 2-3 seconds?
- Does the visual system distinguish page title, record title, section title, field label, metadata, and status cleanly?
- Is the default density efficient enough for all-day desktop use?
- Do navigation controls, toggles, and submit actions look meaningfully different?
- Is the default scan path stable across overview, lists, detail pages, and pipeline?
- Are urgent items surfaced before informational items?
- Are repeated CRM entities rendered with consistent row/card anatomy?
- Are spacing, radii, borders, and shadows systematic rather than component-by-component?

## 3. Appearance Audit

### 3.1 Typography
1. **Location/page/component:** Global CRM typography tokens and most cards/forms.
   - **Current behavior:** Body copy is normalized to `1rem`, small text to `0.9rem`, metadata to `0.8rem`, but headings and labels are left relatively ad hoc. Labels are uppercase at `0.8rem`, KPI values vary by component, and record names often share similar visual weight with surrounding metadata.
   - **Why it feels weaker than a strong CRM pattern:** Strong CRMs use a predictable ladder so users can instantly separate record identity, section structure, metadata, and controls. Here, the label style is often louder than it needs to be while titles are not consistently assertive.
   - **Severity:** High.
   - **Recommended direction:** Define an explicit CRM ladder such as page title 24/32, section title 18/26, card title 15/22, body 14/20, meta 12/16, label 12/16 semibold without forced uppercase by default.
   - **System type:** Token/CSS-system issue.

2. **Location/page/component:** Client detail KPIs, pipeline cards, overview cards, list cards.
   - **Current behavior:** KPI numbers, card titles, pill metadata, and supporting text sit too close together in size and weight, creating a flattened reading rhythm.
   - **Why it feels weaker than a strong CRM pattern:** Operational screens benefit from sharp contrast between “what this thing is” and “supporting context.” The current hierarchy makes users read line by line instead of scanning anchors first.
   - **Severity:** Medium.
   - **Recommended direction:** Increase contrast between title/value text and metadata; reduce meta prominence; keep one clear semantic style for record names across all cards.
   - **System type:** Token/CSS-system issue.

3. **Location/page/component:** Form labels across create-lead and client-profile/status forms.
   - **Current behavior:** Labels are uppercase, tight, and visually assertive even on routine fields.
   - **Why it feels weaker than a strong CRM pattern:** Uppercase micro-labels can work for dashboards, but across large forms they create visual noise and decrease readability, especially when many fields are stacked.
   - **Severity:** Medium.
   - **Recommended direction:** Use sentence case for most field labels, reserve uppercase eyebrow styling for section-level micro-headings only.
   - **System type:** Token/CSS-system issue.

### 3.2 Spacing and density
1. **Location/page/component:** Global cards, sections, overview modules, list cards.
   - **Current behavior:** Spacing tokens are close together (`10`, `16`, `20`, `32`), but actual components use many custom paddings and margins. Cards with limited content still receive large rounded shells and generous white space.
   - **Why it feels weaker than a strong CRM pattern:** Strong CRMs are dense by default on desktop. Here, the spacing feels more editorial than operational, which lowers records-per-screen and slows scanning.
   - **Severity:** High.
   - **Recommended direction:** Normalize around an 8px rhythm, shrink default card padding, reduce inter-section whitespace, and make overview/list cards visibly denser.
   - **System type:** Token/CSS-system issue.

2. **Location/page/component:** Overview new leads, overview tasks, task cards, lead cards.
   - **Current behavior:** Cards often spend vertical space on repeated label/value paragraphs and button wrappers instead of structured rows.
   - **Why it feels weaker than a strong CRM pattern:** Reps should be able to scan 8-12 items quickly; these cards expose too little data for the amount of area they consume.
   - **Severity:** High.
   - **Recommended direction:** Convert repetitive stacked paragraphs into compact meta rows or definition-grid patterns and reduce card padding/gap values.
   - **System type:** Local component issue.

3. **Location/page/component:** Hero and content transition.
   - **Current behavior:** The hero/header region adds significant vertical depth before content begins.
   - **Why it feels weaker than a strong CRM pattern:** For repeated daily use, this makes the first useful content arrive later than it should.
   - **Severity:** Medium.
   - **Recommended direction:** Compress the header block substantially on operational pages and reserve large hero treatment for non-operational or onboarding screens only.
   - **System type:** Structural/layout issue with appearance impact.

### 3.3 Cards, borders, surfaces
1. **Location/page/component:** `.card-shell` globally.
   - **Current behavior:** Cards use 20px radii, visible borders, and soft shadows, while major containers use 32px radii and heavier shadows.
   - **Why it feels weaker than a strong CRM pattern:** This is visually plush but not especially CRM-like. Strong CRMs tend to favor flatter panels with tighter radii so information, not chrome, dominates.
   - **Severity:** High.
   - **Recommended direction:** Reduce outer panel radius materially, reduce card radius to 8-12px, lighten or remove most shadows, and rely more on border contrast plus spacing.
   - **System type:** Token/CSS-system issue.

2. **Location/page/component:** Lists within cards, pills inside bordered cards, linked property items inside cards.
   - **Current behavior:** Bordered items often sit inside bordered cards inside bordered content panels.
   - **Why it feels weaker than a strong CRM pattern:** Layered containment creates unnecessary noise and makes screens feel busy without adding structure.
   - **Severity:** Medium.
   - **Recommended direction:** Pick a primary containment method per area: either the section is a panel with internal dividers, or each row/item is a bordered object, but not both repeatedly.
   - **System type:** Local component issue.

3. **Location/page/component:** Notices and empty states.
   - **Current behavior:** Notices are visually similar to regular cards or pills and empty states often render as plain paragraphs.
   - **Why it feels weaker than a strong CRM pattern:** Users need clearer differentiation between error, informational, and no-data states.
   - **Severity:** Medium.
   - **Recommended direction:** Create a small empty-state pattern with title, explanation, and suggested next action; reserve notice styling for exceptional states.
   - **System type:** Local component issue.

### 3.4 Buttons and controls
1. **Location/page/component:** Global button system.
   - **Current behavior:** Most buttons are rounded pills regardless of role; primary, secondary, toggle, and record-link actions are visually too similar.
   - **Why it feels weaker than a strong CRM pattern:** Strong CRMs make one action obviously primary and let low-emphasis actions recede. Here, pills make many controls feel equally actionable.
   - **Severity:** High.
   - **Recommended direction:** Separate button families into primary solid, secondary outline, tertiary text/link, and segmented toggles. Reserve pill styling for status chips, not primary task execution.
   - **System type:** Token/CSS-system issue.

2. **Location/page/component:** View toggles and list type toggles.
   - **Current behavior:** Toggles are implemented with standard buttons that look like submit CTAs.
   - **Why it feels weaker than a strong CRM pattern:** Mode-switching controls should read as segmented options, not command buttons.
   - **Severity:** Medium.
   - **Recommended direction:** Use a segmented control appearance with a shared capsule container and lower visual weight than the page primary CTA.
   - **System type:** Local component issue.

3. **Location/page/component:** Client quick actions, mark-done actions, delete note actions.
   - **Current behavior:** Destructive, confirmatory, and utility actions all occupy similar pill-button territory.
   - **Why it feels weaker than a strong CRM pattern:** Users cannot prioritize intent quickly, and destructive actions do not feel sufficiently separated.
   - **Severity:** Medium.
   - **Recommended direction:** Introduce utility icon buttons or text buttons for secondary actions; reserve red/destructive treatment only for dangerous actions.
   - **System type:** Token/CSS-system issue.

### 3.5 Forms and inputs
1. **Location/page/component:** Create lead form and client profile/status forms.
   - **Current behavior:** Inputs are acceptable but generic, with soft rounded corners and moderate padding. Field groups often feel visually isolated rather than part of a stronger form section model.
   - **Why it feels weaker than a strong CRM pattern:** Strong CRM forms group related decisions into clear modules with crisp labels, supporting text, and predictable save areas.
   - **Severity:** Medium.
   - **Recommended direction:** Add section grouping, reduce decorative rounding, tighten vertical spacing, and distinguish editable field groups from read-only quick actions.
   - **System type:** Combination of token/CSS-system and local component issues.

2. **Location/page/component:** Checkbox pills for preferred contact.
   - **Current behavior:** Checkbox options use pill containers similar to status chips.
   - **Why it feels weaker than a strong CRM pattern:** It blurs the line between field controls and status display tokens.
   - **Severity:** Low.
   - **Recommended direction:** Style multi-select options as checkable chips with clearer selected/unselected states and slightly less decorative treatment.
   - **System type:** Local component issue.

3. **Location/page/component:** Hero filters on client list.
   - **Current behavior:** Search and filter inputs live inside the hero region and compete with the page title visually.
   - **Why it feels weaker than a strong CRM pattern:** In strong CRMs, filters are typically part of the working header, not embedded in a branded hero treatment.
   - **Severity:** Medium.
   - **Recommended direction:** Move filters into a compact toolbar below the title or inline with title/actions.
   - **System type:** Structural/layout issue with appearance impact.

### 3.6 Tables and list rows
1. **Location/page/component:** Leads/clients and tasks tables.
   - **Current behavior:** Tables are the densest and most CRM-like surfaces in the product, but row styling is still fairly plain, with limited hierarchy, modest header contrast, and no sticky context.
   - **Why it feels weaker than a strong CRM pattern:** Strong CRM tables usually emphasize name/status/owner/date hierarchy more clearly and give bulk triage confidence.
   - **Severity:** Medium.
   - **Recommended direction:** Strengthen header row styling, tighten row height slightly, make primary text heavier, and add clearer treatment for status and overdue state.
   - **System type:** Local component issue.

2. **Location/page/component:** Task lists rendered as card-like list items.
   - **Current behavior:** `.crm-list` becomes a card grid in some views and a simple divider list in others.
   - **Why it feels weaker than a strong CRM pattern:** Reusing one class for several anatomies introduces inconsistency and makes density unpredictable across pages.
   - **Severity:** High.
   - **Recommended direction:** Split list primitives into distinct patterns: table, structured row list, and card grid, each with its own spacing and semantics.
   - **System type:** Token/CSS-system issue.

3. **Location/page/component:** Empty table/list states.
   - **Current behavior:** Empty rows often say only “No X found.”
   - **Why it feels weaker than a strong CRM pattern:** Good CRM empty states usually explain whether filters caused the result and what action to take next.
   - **Severity:** Low.
   - **Recommended direction:** Add contextual empty-state messaging with actions like clear filters, create lead, or widen scope.
   - **System type:** Local component issue.

### 3.7 Badges, labels, status indicators
1. **Location/page/component:** Pills across headers, tables, timeline, pipeline, KPI cards.
   - **Current behavior:** Pills are heavily reused for status, source, counts, timeline types, advisor labels, budget metadata, and even “See more” controls.
   - **Why it feels weaker than a strong CRM pattern:** When every bit of metadata becomes a pill, the pattern loses meaning and visual hierarchy collapses.
   - **Severity:** High.
   - **Recommended direction:** Reserve pills for true statuses/tags only. Render secondary metadata as small plain text rows or icon-label pairs.
   - **System type:** Token/CSS-system issue.

2. **Location/page/component:** Status vs urgency signaling.
   - **Current behavior:** Red pills do communicate overdue state, but many non-urgent items also use pills with similar visual prominence.
   - **Why it feels weaker than a strong CRM pattern:** Color significance gets diluted.
   - **Severity:** Medium.
   - **Recommended direction:** Limit strong color fills to exception states; default metadata chips should be subtle gray or text-only.
   - **System type:** Token/CSS-system issue.

### 3.8 Visual hierarchy and emphasis
1. **Location/page/component:** Overview and client detail pages.
   - **Current behavior:** Many sections have comparable weight because they all use similar `card-shell` containers, pill labels, and heading sizes.
   - **Why it feels weaker than a strong CRM pattern:** It is difficult to tell what deserves attention first versus what is contextual.
   - **Severity:** High.
   - **Recommended direction:** Promote one primary work area per page, reduce chrome on secondary sections, and rely on section ordering plus heading contrast instead of repeating identical card framing.
   - **System type:** Combination of token/CSS-system and structural/layout issues.

2. **Location/page/component:** Client header strip.
   - **Current behavior:** The client name appears, but much of the surrounding status context is delivered as a cloud of same-style pills.
   - **Why it feels weaker than a strong CRM pattern:** Strong record headers usually separate identity, ownership, lifecycle status, and action buttons into clearer lanes.
   - **Severity:** High.
   - **Recommended direction:** Rebuild the header into a summary block with title/subtitle on the left and key status/action group on the right; reduce pill count.
   - **System type:** Structural/layout issue with appearance impact.

## 4. Layout Audit

### 4.1 Global shell
1. **Location/page/component:** CRM global shell.
   - **Current behavior:** All major CRM pages sit under a large branded hero and then a rounded content panel.
   - **Why it harms scan speed / workflow / action clarity:** It delays operational content, makes every page feel equally “presentational,” and uses vertical space that should belong to tasks, records, and filters.
   - **Severity:** High.
   - **Recommended direction:** Adopt a compact app-shell header for CRM pages: small title row, optional breadcrumb/context line, then content immediately.
   - **Type:** Structural/layout.

2. **Location/page/component:** Main content plus side navigation grid.
   - **Current behavior:** Desktop layout uses a two-column content-plus-sidebar model, but the main content patterns do not always take advantage of the fixed right rail.
   - **Why it harms scan speed / workflow / action clarity:** The nav occupies meaningful width while high-value contextual content is not using a complementary secondary column. The result is a somewhat narrow main workspace without a clear right-rail strategy.
   - **Severity:** Medium.
   - **Recommended direction:** Either keep a compact left nav and maximize main content width, or formally introduce a right-side contextual rail on detail pages.
   - **Type:** Structural/layout.

### 4.2 Sidebar / top navigation
1. **Location/page/component:** Desktop side nav plus hero header.
   - **Current behavior:** Navigation exists in a sticky sidebar, while page identity lives in a large header area above content.
   - **Why it harms scan speed / workflow / action clarity:** Two large navigation/context zones compete for attention. Strong CRMs tend to simplify: nav identifies the app, while the page header identifies the workspace.
   - **Severity:** Medium.
   - **Recommended direction:** Reduce header branding and make the nav the stable app landmark; keep page headers compact and task-oriented.
   - **Type:** Structural/layout.

2. **Location/page/component:** Side nav information architecture.
   - **Current behavior:** Navigation includes overview, clients, tasks, pipeline, create lead, and logs in one list.
   - **Why it harms scan speed / workflow / action clarity:** “Create lead” is an action, not a section, so mixing it into primary navigation weakens IA clarity.
   - **Severity:** Medium.
   - **Recommended direction:** Move “Create lead” into page/header action space and keep sidebar items focused on persistent destinations.
   - **Type:** Structural/layout.

### 4.3 Dashboard layout
1. **Location/page/component:** CRM overview/dashboard.
   - **Current behavior:** Notices, new leads, today’s tasks, overdue tasks, activity, KPI snapshot, pipeline counts, and notifications appear as separate stacked sections.
   - **Why it harms scan speed / workflow / action clarity:** The page reads as a content report instead of an action console. Users must scroll and interpret multiple equally weighted blocks before knowing what matters first.
   - **Severity:** High.
   - **Recommended direction:** Recompose the dashboard into priority bands: overdue/today work first, fresh leads second, then metrics/activity, with passive diagnostic utilities last.
   - **Type:** Structural/layout.

2. **Location/page/component:** Dashboard section anatomy.
   - **Current behavior:** Each section is self-contained, but there is little cross-linking or summarized action state.
   - **Why it harms scan speed / workflow / action clarity:** Users cannot get a quick “what requires me now” view from one glance.
   - **Severity:** Medium.
   - **Recommended direction:** Add a compact top summary strip for overdue tasks, due today, unassigned/new leads, and recent changes, each with obvious jump actions.
   - **Type:** Structural/layout.

### 4.4 Client list / record list layout
1. **Location/page/component:** Leads/clients page.
   - **Current behavior:** A hero contains filters, then a toolbar contains type toggle, create CTA, and view toggle, then either cards or a table.
   - **Why it harms scan speed / workflow / action clarity:** The filtering and view controls are split across multiple vertical bands, so the user’s control surface is fragmented.
   - **Severity:** High.
   - **Recommended direction:** Consolidate title, counts, filters, saved scope/type selector, and primary action into one compact list-page header above the canonical list.
   - **Type:** Structural/layout.

2. **Location/page/component:** Card view on leads/clients page.
   - **Current behavior:** Cards expose limited record context and require a “View Lead/Client” click for most deeper action.
   - **Why it harms scan speed / workflow / action clarity:** For a CRM, list pages should support triage directly. The current cards are too shallow and too spacious.
   - **Severity:** High.
   - **Recommended direction:** Make table/structured-row view the primary default for desktop and treat cards as a secondary compact visual summary, if kept at all.
   - **Type:** Structural/layout.

3. **Location/page/component:** Tasks page.
   - **Current behavior:** Tasks are split into three card/list sections plus an alternative table view.
   - **Why it harms scan speed / workflow / action clarity:** Users may need one sortable all-task view first, with buckets as filters or tabs rather than separate long sections.
   - **Severity:** Medium.
   - **Recommended direction:** Default to one table or one dense row list with tabs/filters for Today, Overdue, Upcoming, All.
   - **Type:** Structural/layout.

### 4.5 Client detail / record detail layout
1. **Location/page/component:** Client detail page overall.
   - **Current behavior:** After the header strip and KPI cards, panels flow in a CSS multi-column layout on desktop.
   - **Why it harms scan speed / workflow / action clarity:** Masonry-like flow breaks predictable vertical scan order. A user cannot rely on stable left-to-right progression because section heights alter placement.
   - **Severity:** High.
   - **Recommended direction:** Replace column flow with a deliberate grid template, such as main column for status/tasks/notes/timeline and secondary column for profile/ownership/quick actions.
   - **Type:** Structural/layout.

2. **Location/page/component:** Client header strip.
   - **Current behavior:** Header metadata is delivered as many pills without adjacent action grouping.
   - **Why it harms scan speed / workflow / action clarity:** The top of a record should answer “Who is this, what state are they in, and what do I do next?” more directly.
   - **Severity:** High.
   - **Recommended direction:** Use a two-row summary header with identity, owner, lifecycle, primary next actions, and maybe one compact health/status cluster.
   - **Type:** Structural/layout.

3. **Location/page/component:** Notes, reminders, timeline, related properties, deals.
   - **Current behavior:** Multiple high-value workflow modules appear as equal panels.
   - **Why it harms scan speed / workflow / action clarity:** Reminders and status management deserve stronger precedence than long-form context like timeline or property blocks.
   - **Severity:** High.
   - **Recommended direction:** Order sections by decision priority: status + next actions, reminders, notes, activity/timeline, related records, extended admin areas.
   - **Type:** Structural/layout.

### 4.6 Pipeline layout
1. **Location/page/component:** Pipeline board.
   - **Current behavior:** The board is horizontally scrollable with fairly wide columns and cards that contain several pill rows.
   - **Why it harms scan speed / workflow / action clarity:** Wider cards plus pill-heavy metadata reduce the number of visible columns and records at once, which undercuts the main value of pipeline view.
   - **Severity:** High.
   - **Recommended direction:** Narrow columns and cards, compress metadata into 2-3 high-value lines, and elevate stage counts plus freshness indicators.
   - **Type:** Structural/layout with appearance impact.

2. **Location/page/component:** Pipeline card content model.
   - **Current behavior:** Cards emphasize source, budget, advisor, and last activity equally.
   - **Why it harms scan speed / workflow / action clarity:** Not all metadata is equally useful during stage review. The board needs a primary line, one secondary line, and one action/freshness line.
   - **Severity:** Medium.
   - **Recommended direction:** Standardize a record card anatomy: name, urgency/status hint, owner, latest activity age, and maybe one key qualification field.
   - **Type:** Structural/layout.

### 4.7 Forms / edit screens
1. **Location/page/component:** Create lead page.
   - **Current behavior:** The form sits in a single card under the same hero shell used elsewhere.
   - **Why it harms scan speed / workflow / action clarity:** The creation workflow itself is simple, but the shell adds weight that makes the page feel slower than it is.
   - **Severity:** Low.
   - **Recommended direction:** Use a compact create-record template with concise title, optional guidance, then the form immediately.
   - **Type:** Structural/layout.

2. **Location/page/component:** Client profile and status edit areas.
   - **Current behavior:** Edit forms are embedded as full panels among many other panels.
   - **Why it harms scan speed / workflow / action clarity:** Important save interactions are diluted inside a long detail page rather than anchored in a clearly editable top section.
   - **Severity:** Medium.
   - **Recommended direction:** Consolidate editable summary fields into a stronger top-of-record edit module or a stable right rail.
   - **Type:** Structural/layout.

### 4.8 Responsive and constrained-width behavior
1. **Location/page/component:** Client detail desktop-to-tablet transition.
   - **Current behavior:** The layout is resilient, but the multi-column card flow and pill-heavy sections remain conceptually busy as width narrows.
   - **Why it harms scan speed / workflow / action clarity:** The UI protects against overflow, but the information architecture is still noisy, so narrower widths amplify scanning cost.
   - **Severity:** Medium.
   - **Recommended direction:** Solve desktop structure first; responsive states should inherit a simpler content model rather than merely stacking the same modules.
   - **Type:** Structural/layout.

2. **Location/page/component:** Mobile/task and overview cards.
   - **Current behavior:** Grids collapse cleanly to one column.
   - **Why it harms scan speed / workflow / action clarity:** This is functionally safe, but because the card model is already spacious, mobile becomes long quickly.
   - **Severity:** Low.
   - **Recommended direction:** Reduce card verbosity and favor collapsible metadata or denser row patterns when small screens are unavoidable.
   - **Type:** Structural/layout with appearance impact.

## 5. Priority Fix List

### 5.1 Appearance quick wins
1. **Replace the pill-heavy action language with a stricter button hierarchy.**
   - **Impact:** High.
   - **Implementation complexity:** Medium.
   - **Likely files/components involved:** `wp-content/plugins/peracrm/assets/frontend/crm.css`, list/detail templates, toolbar controls, quick actions, task actions.

2. **Reduce radii and shadows across `content-panel-box`, `card-shell`, pipeline cards, and list cards.**
   - **Impact:** High.
   - **Implementation complexity:** Low.
   - **Likely files/components involved:** `wp-content/plugins/peracrm/assets/frontend/crm.css` and any card-specific classes.

3. **Install a disciplined CRM type ladder for page titles, section titles, card titles, meta text, and labels.**
   - **Impact:** High.
   - **Implementation complexity:** Medium.
   - **Likely files/components involved:** `wp-content/plugins/peracrm/assets/frontend/crm.css`, page templates, partials.

4. **Reduce desktop spacing and padding on overview/task/lead cards.**
   - **Impact:** Medium.
   - **Implementation complexity:** Low.
   - **Likely files/components involved:** `crm.css`, overview/task/list templates.

5. **Limit pills to actual statuses/tags and convert secondary metadata to plain text rows.**
   - **Impact:** Medium.
   - **Implementation complexity:** Medium.
   - **Likely files/components involved:** pipeline cards, client header, timeline, KPI cards, task cards, list rows.

### 5.2 Layout quick wins
1. **Collapse the hero treatment into a compact CRM page header on overview, list, detail, pipeline, and create pages.**
   - **Impact:** High.
   - **Implementation complexity:** Medium.
   - **Likely files/components involved:** `inc/views/partials/crm-header.php`, `assets/frontend/crm.css`, all page templates.

2. **Rebuild client detail from masonry-style columns into a fixed record template.**
   - **Impact:** High.
   - **Implementation complexity:** High.
   - **Likely files/components involved:** `inc/views/pages/crm-client.php`, related partial rendering, `crm.css`.

3. **Make the leads/clients desktop experience table-first and consolidate filters/actions into one toolbar.**
   - **Impact:** High.
   - **Implementation complexity:** Medium.
   - **Likely files/components involved:** `inc/views/pages/crm-overview.php`, `inc/views/partials/crm-header.php`, `assets/frontend/crm.js`, `crm.css`.

4. **Recompose the dashboard so overdue/today/new-lead work appears before passive KPI and activity blocks.**
   - **Impact:** High.
   - **Implementation complexity:** Medium.
   - **Likely files/components involved:** `inc/views/pages/crm-overview.php`, `crm.css`.

5. **Compress pipeline columns and standardize record-card anatomy.**
   - **Impact:** Medium.
   - **Implementation complexity:** Medium.
   - **Likely files/components involved:** `inc/views/pages/crm-pipeline.php`, `crm.css`.

## 6. Design Recommendations

### 6.1 Appearance system recommendations
- **Font-size ladder:**
  - Page title: `24px / 32px`, semibold.
  - Section title: `18px / 26px`, semibold.
  - Record/card title: `15px / 22px`, semibold.
  - Body text: `14px / 20px`, regular.
  - Meta text: `12px / 16px`, regular or medium.
  - Field labels: `12px / 16px`, medium, sentence case by default.
- **Spacing rhythm:** Use `4 / 8 / 12 / 16 / 24 / 32`. Avoid many one-off values between 10 and 20 unless a component truly needs them.
- **Border strategy:** Prefer 1px neutral borders with subtle contrast. Use elevation sparingly and primarily for overlays, drawers, or a small number of summary cards.
- **Button hierarchy:**
  - Primary: filled rectangle with 8-10px radius.
  - Secondary: subtle outline or tonal fill.
  - Tertiary: text/link button.
  - Utility: icon button.
  - Status chips: separate chip component, not interchangeable with buttons.
- **Card/table density:**
  - Desktop card padding should usually land around 12-16px.
  - Tables should target a compact operational row height with distinct primary text and quieter secondary metadata.
- **Pill strategy:** Keep chips for lifecycle status, overdue state, or true categorical tags. Remove pills from routine metadata like “Advisor:” and “Budget:” unless the board specifically depends on them.

### 6.2 Layout system recommendations
- **Page shell model:** Replace the large hero with an app-style page header: title, subtitle/count, actions, and optional filters in one compact top band.
- **Record detail template model:**
  - Top summary: record identity, owner, lifecycle, primary actions.
  - Main column: tasks/reminders, notes, timeline/activity.
  - Secondary column: profile, quick contact actions, linked metadata, administrative tools.
- **Dashboard composition model:**
  - Row 1: action summary cards (overdue, due today, new/unassigned leads).
  - Row 2: actionable queues.
  - Row 3: passive analytics and activity.
  - Row 4: diagnostics/utilities like push notification setup.
- **List page model:** Title and counts plus filters and saved scope in a single header; one default desktop list pattern; secondary view options de-emphasized.
- **Pipeline model:** Narrower stage columns, concise cards, stage count at top, clear urgency/freshness indicators, optional quick actions on hover/focus.
- **Form/edit model:** Group fields by task, keep submit actions anchored, and avoid mixing too many unrelated editable blocks in the same visual plane.

## 7. Implementation Notes
- **Global visual tokens and component primitives:** Start in `wp-content/plugins/peracrm/assets/frontend/crm.css`. This file currently owns most card, button, pill, nav, form, list, table, and layout behavior, so it should be the first place to split system tokens from component-specific rules.
- **Shared header shell:** `wp-content/plugins/peracrm/inc/views/partials/crm-header.php` should be refactored if the team adopts a compact CRM app-header rather than the current hero model.
- **Shared navigation:** `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php` likely needs IA cleanup if “Create lead” moves from navigation into primary action space.
- **Dashboard and list page restructuring:** `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php` controls overview, tasks, and leads/clients views, so many of the dashboard and list-layout improvements map here.
- **Record/detail template restructuring:** `wp-content/plugins/peracrm/inc/views/pages/crm-client.php` is the main file for header, KPIs, forms, notes, reminders, timeline, deals, and related properties. The biggest workflow/layout improvements will land here.
- **Pipeline compression:** `wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php` should own the revised column/card anatomy for a denser pipeline board.
- **Create-record simplification:** `wp-content/plugins/peracrm/inc/views/pages/crm-new.php` should adopt the same compact shell and form-grouping rules as the rest of the CRM once the page model is updated.
- **Behavioral support for list-mode decisions:** `wp-content/plugins/peracrm/assets/frontend/crm.js` contains the current view-toggle and table-sorting logic. If the team moves toward a table-first desktop pattern, this file will likely need corresponding simplification.
- **Potential reusable component extraction:** Over time, consider separating reusable primitives for page header, toolbar, status chip, structured row list, dense KPI strip, record summary header, and right-rail summary cards rather than continuing to layer more rules onto `.card-shell`, `.crm-list`, and `.pill`.
