# PeraCRM UI System Definition

## 1. Purpose and Design Goals
PeraCRM is an internal operational CRM for coordinators, sales advisors, managers, and support staff who spend long stretches of the day triaging leads, updating statuses, managing reminders, checking activity, reviewing pipeline stage movement, and editing record details. It is not a promotional surface and should not inherit the interaction model of the public-facing website shell. The existing audit shows that the current shared shell still behaves like a branded marketing extension, with a large hero, plush cards, pill-heavy controls, and inconsistent density. This definition converts that audit into explicit standards for the plugin-based CRM experience.

The UI system must support users who repeatedly:
- review new leads and assign next actions;
- scan overdue and due-today tasks;
- search, filter, and sort clients rapidly;
- inspect a client record, update status, and capture notes/reminders;
- move through pipeline stages with clear stage-specific context;
- create or edit records without excessive page depth or decoration.

The UI should optimize for:
- fast scanning of names, statuses, dates, owners, and next actions;
- stable layouts that behave predictably across overview, lists, details, and pipeline;
- a clear hierarchy between one primary action and all other controls;
- calm presentation with minimal decorative weight;
- dense but readable desktop workflows;
- responsive simplification that preserves task completion on smaller screens.

The UI should avoid:
- hero-first page models that delay working content;
- marketing-style visual excess such as oversized radii, deep shadows, and CTA-like toggle buttons;
- multiple competing list patterns for the same entity without a canonical default;
- masonry-like detail layouts with unstable reading order;
- pill treatment for every piece of metadata;
- mobile behavior that merely stacks desktop panels without reducing complexity.

A marketing page model is optimized for persuasion, branded storytelling, and depth of presentation. It expects large headers, emotional emphasis, broad spacing, and relatively few decisions per screen. A CRM app-shell model is optimized for repeated action, recall, and throughput. It expects persistent navigation, compact headers, dense structured data, explicit workflow regions, and consistent placement of actions and metadata. PeraCRM must use the CRM app-shell model as its default for all operational screens.

## 2. Core Design Principles
1. **Action-first**
   - **Rule:** Every screen must expose one dominant next action within the first scan pass.
   - **Why it matters in PeraCRM specifically:** Users are managing leads, reminders, and follow-up tasks under time pressure; hesitation caused by equal-weight buttons directly slows operational throughput.

2. **Dense but readable**
   - **Rule:** Desktop views should favor compact spacing, short rows, and structured data blocks, but never collapse labels and values into unreadable noise.
   - **Why it matters in PeraCRM specifically:** Staff need to review many records per session, so information-per-screen matters more than “spacious” marketing aesthetics.

3. **Stable scan paths**
   - **Rule:** The order of title, filters, key status, primary content, and secondary context must stay predictable across screens.
   - **Why it matters in PeraCRM specifically:** The current client detail layout competes across columns; stable scan order reduces cognitive overhead during frequent context switching.

4. **One dominant primary action**
   - **Rule:** Each page or section may have one visually dominant primary button; all other actions must step down clearly.
   - **Why it matters in PeraCRM specifically:** “Create lead,” “Save changes,” and “Mark done” should not visually compete with view toggles, clear filters, or row utilities.

5. **Metadata should recede**
   - **Rule:** Names, statuses, due dates, and next actions must be more prominent than timestamps, sources, owners, and passive descriptors.
   - **Why it matters in PeraCRM specifically:** Much of the current UI turns source, advisor, and contextual data into equally loud pills, making triage slower.

6. **Status color is semantic, not decorative**
   - **Rule:** Use strong color fills only for meaningful states such as overdue, success, warning, blocked, or selected status.
   - **Why it matters in PeraCRM specifically:** Overdue tasks and urgent exceptions already need color significance; decorative color elsewhere weakens those signals.

7. **Responsive simplification, not responsive stacking**
   - **Rule:** Smaller breakpoints must reduce, regroup, or defer controls instead of merely stacking every desktop region vertically.
   - **Why it matters in PeraCRM specifically:** Tablet and mobile use will fail if the current dense detail and pipeline patterns are simply pushed into long scrolls.

8. **Structure before decoration**
   - **Rule:** Use layout, grouping, and typography to define hierarchy before adding borders, fills, chips, or shadows.
   - **Why it matters in PeraCRM specifically:** The audit shows too much reliance on card shells and pills to express importance, which adds noise without improving comprehension.

9. **Canonical patterns over local improvisation**
   - **Rule:** Each recurring data type should have one default presentation pattern per breakpoint context.
   - **Why it matters in PeraCRM specifically:** Clients/leads currently swing between cards and tables, and `.crm-list` is used for incompatible list anatomies.

10. **Desktop-primary, mobile-adapted**
   - **Rule:** Design from the desktop workflow outward, then intentionally adapt for tablet and mobile with task-preserving reductions.
   - **Why it matters in PeraCRM specifically:** Most use will happen on desktop inside the CRM workspace, but the plugin still needs credible field access on smaller devices.

11. **Explicit containment**
   - **Rule:** Each area should have one clear containment strategy: panel, row dividers, or standalone objects; never all three layered together.
   - **Why it matters in PeraCRM specifically:** Nested bordered pills inside bordered cards inside bordered panels currently create unnecessary visual friction.

12. **Forms follow decision groups**
   - **Rule:** Forms must be organized around operational decisions, not arbitrary field order.
   - **Why it matters in PeraCRM specifically:** Client and lead editing often mixes status, contact, source, and note-taking; grouped form sections improve completion speed and reduce errors.

## 3. Canonical Page Models

### 3.1 App shell
- **Purpose:** Establish the standard operational workspace for all CRM routes.
- **Required regions:** persistent left navigation on desktop; compact page header; main content region; optional utility affordance for global/mobile nav.
- **Optional regions:** right contextual rail on record-detail pages; inline notices; drawer-based mobile navigation.
- **Expected scan order:** navigation landmark -> page title/context -> page actions -> toolbar/filter row if present -> primary content -> secondary context.
- **Primary action location:** page header right side, aligned with title band.
- **Secondary action location:** toolbar row, action group, or contextual row menus.
- **What should not appear:** tall branded hero, large marketing copy block, decorative headline band, duplicated navigation zones.
- **Desktop behavior:** left nav remains persistent; page header stays compact; content gets the majority of horizontal space.
- **Tablet behavior:** nav collapses to a drawer or rail; page header remains one compact unit; content becomes single-column or controlled two-column depending on page type.
- **Mobile behavior:** nav becomes a drawer; page header compresses into title + overflow actions; only essential context remains above the fold.

### 3.2 Dashboard / overview page
- **Purpose:** Surface urgent workload and recent lead intake in a command-center sequence.
- **Required regions:** compact page header; KPI strip; urgent work band; new leads queue; recent activity block.
- **Optional regions:** notices; passive metrics; notifications; diagnostic/admin utilities.
- **Expected scan order:** title and primary CTA -> KPI/queue strip -> overdue tasks -> due today -> new leads -> recent activity -> passive metrics.
- **Primary action location:** page header right side, usually “Create lead.”
- **Secondary action location:** within each queue header as text links or compact secondary buttons.
- **What should not appear:** equal-weight stacks of unrelated card sections, oversized intro copy, decorative cards for passive data before urgent work.
- **Desktop behavior:** use bands or a 2-column command-center layout with urgent queues dominant and metrics secondary.
- **Tablet behavior:** shift to one primary column with compact KPI strip and collapsible lower-priority sections.
- **Mobile behavior:** prioritize overdue, due today, and new leads; defer passive activity/metrics lower; use row lists instead of card grids.

### 3.3 List page
- **Purpose:** Support rapid triage, sorting, filtering, and selection of many records.
- **Required regions:** compact page header; toolbar with search, filters, saved view/type control, and primary CTA; canonical table or structured row list.
- **Optional regions:** batch action bar; empty-state panel; quick summary counts.
- **Expected scan order:** title/count -> filters/search -> list header -> rows -> row actions.
- **Primary action location:** page header or toolbar right side.
- **Secondary action location:** toolbar left/center, row action menus, table row trailing cells.
- **What should not appear:** hero filters separate from toolbar, both dense table and decorative cards as equal defaults, large cards for core triage on desktop.
- **Desktop behavior:** full-width table is default for leads/clients and tasks; row actions stay fixed in predictable columns.
- **Tablet behavior:** structured row list replaces full table when columns would become unreadable; filters may collapse into a drawer.
- **Mobile behavior:** use structured row list with title, status, due date, owner, and one primary row action; secondary row actions move into overflow.

### 3.4 Record detail page
- **Purpose:** Present the record identity, key status, next actions, and grouped operational detail in a stable order.
- **Required regions:** summary header; primary action cluster; key KPI strip or summary facts; ordered sections for profile, reminders/tasks, notes, activity, related items.
- **Optional regions:** right rail for compact facts or quick actions; notices; related deals/properties.
- **Expected scan order:** record identity -> status/owner/health -> next actions -> imminent reminders/tasks -> editable profile -> notes -> activity -> related records.
- **Primary action location:** summary header right side.
- **Secondary action location:** section headers, inline row actions, right rail quick links.
- **What should not appear:** masonry layout, unordered panel clouds, excessive metadata pills, repeated full-card shells for every subsection.
- **Desktop behavior:** fixed two-column detail grid only when the second column has a clear supporting purpose; otherwise one main column with optional right rail.
- **Tablet behavior:** collapse to one main column plus optional accordions for lower-priority sections.
- **Mobile behavior:** summary header compresses; sections become stacked with accordions where appropriate; only current status and next action stay at the top.

### 3.5 Pipeline page
- **Purpose:** Show stage distribution and support scanning movement opportunities across stages.
- **Required regions:** compact page header; pipeline board or stage list; per-column counts; concise card anatomy.
- **Optional regions:** filters, owner filter, compact pipeline metrics, stage-specific totals.
- **Expected scan order:** title/filter context -> stage counts -> stage columns -> cards within highest-priority stages.
- **Primary action location:** page header right side, usually “Create lead” or stage-specific add action if later required.
- **Secondary action location:** toolbar filters, card links, overflow actions on cards.
- **What should not appear:** metadata rendered entirely as pills, wide fluffy cards, deeply nested card-inside-card containment, equal emphasis on all metadata lines.
- **Desktop behavior:** horizontal stage board with compact columns and scroll when necessary.
- **Tablet behavior:** fewer visible columns at once with horizontal scroll or stage tabs; keep counts visible.
- **Mobile behavior:** convert to stage-by-stage stacked sections or tabs with row-card hybrids; never attempt a six-column miniaturized board.

### 3.6 Create / edit form page
- **Purpose:** Capture or update data efficiently with clear grouping and expected save actions.
- **Required regions:** compact page header; grouped form sections; persistent or clearly anchored submit area; inline validation.
- **Optional regions:** helper content, contextual quick actions, duplicate warnings.
- **Expected scan order:** title/context -> critical required fields -> grouped secondary fields -> notes/details -> save area.
- **Primary action location:** sticky footer action bar or page header/right footer depending on form length.
- **Secondary action location:** adjacent secondary button such as cancel/back, plus section-level utilities if needed.
- **What should not appear:** isolated single fields floating in separate cards, pill-styled control groups that resemble status tags, over-decorated form shells.
- **Desktop behavior:** one main form column with moderate max width, or 2-column field grid only for short related fields.
- **Tablet behavior:** single form column with compact sections; keep save action persistent when forms exceed one screen.
- **Mobile behavior:** one field column only; section accordions are acceptable for long forms if required fields remain immediately visible.

## 4. Breakpoint Strategy
PeraCRM needs breakpoint behavior built around operational readability rather than generic responsive stacking. The plugin already has drawer logic in `crm.js` and a desktop-to-mobile navigation shift around 1024px, which is a useful implementation anchor. The system should formalize five breakpoint ranges:

- **Large desktop:** `>= 1440px`
- **Standard desktop / laptop:** `1200px - 1439px`
- **Tablet landscape / compact desktop:** `992px - 1199px`
- **Tablet portrait / small laptop:** `768px - 991px`
- **Mobile:** `< 768px`

These values suit PeraCRM because the app contains side navigation, wide list pages, and a pipeline board that all benefit from distinct behavior near 1200px, 992px, and 768px. They also align closely with the existing nav drawer behavior and current CSS breakpoints while introducing clearer CRM-specific decisions.

What should stay consistent across breakpoints:
- page title location and semantics;
- one dominant primary action per screen;
- the canonical order of urgent work before passive information;
- status semantics and color meaning;
- canonical component anatomy, even when the layout container changes.

What should change across breakpoints:
- navigation persistence;
- number of columns in detail and dashboard layouts;
- whether filters remain inline or collapse into drawers;
- whether tables remain intact or become structured row lists;
- whether the pipeline is a horizontal board, stage tabs, or stacked stage sections.

Where card grids are acceptable:
- KPI strips;
- summary cards on overview only when used for top-level counts;
- small optional utility modules;
- mobile-only condensed queue cards if rows cannot express the content.

Where tables/rows must stay dominant:
- leads/clients on desktop;
- tasks on desktop;
- related items in client detail where comparison matters;
- dashboard queues when the user is scanning action dates and statuses rather than marketing-like summaries.

### Large desktop (`>= 1440px`)
- **Layout model:** persistent side nav + wide main workspace; optional right rail allowed on detail pages; tables can use full width; dashboard can use 2 dominant columns plus compact side rail.
- **Navigation behavior:** fixed sidebar; no top-level nav duplication in the page header.
- **Page header behavior:** one compact row with title, optional context/count, and action group.
- **Toolbar/filter behavior:** full inline toolbar; search, filters, saved view controls, and density/view controls stay visible when relevant.
- **Detail page behavior:** two-column detail model allowed only when left column is primary workflow and right column is compact supporting context; no masonry.
- **Pipeline behavior:** horizontal board with visible 4-6 compact columns; horizontal overflow is acceptable.
- **Table/list behavior:** dense table is default; sticky table header recommended when feasible in later implementation.
- **Risks / anti-patterns to avoid:** wasting extra width on oversized cards or increasing decorative spacing; expanding headers vertically because space is available.

### Standard desktop/laptop (`1200px - 1439px`)
- **Layout model:** persistent side nav + primary content column; right rail only if narrow and useful.
- **Navigation behavior:** fixed sidebar remains the app landmark.
- **Page header behavior:** title and primary actions remain in one band; counts/subtitle below only when necessary.
- **Toolbar/filter behavior:** inline toolbar, but low-priority controls may collapse into “More filters.”
- **Detail page behavior:** one dominant main column plus constrained secondary rail or stacked lower sections.
- **Pipeline behavior:** horizontal board remains primary, with narrower columns and clear stage counts.
- **Table/list behavior:** tables remain default for leads/clients and tasks; column set must be curated rather than allowing too many weak columns.
- **Risks / anti-patterns to avoid:** squeezing too many columns into unreadable tables; preserving decorative header depth from the current hero model.

### Tablet landscape (`992px - 1199px`)
- **Layout model:** navigation shifts from persistent sidebar to collapsible drawer or narrow rail; content becomes primarily single-column with selective split sections.
- **Navigation behavior:** menu toggle opens drawer; current page identity remains in the header.
- **Page header behavior:** title + primary action in one row if space allows, otherwise action drops below title.
- **Toolbar/filter behavior:** search may stay inline; secondary filters collapse into a drawer or expandable filter row.
- **Detail page behavior:** summary header stays compact; supporting facts move below the main summary rather than remaining in a side rail.
- **Pipeline behavior:** board remains possible with horizontal scroll, but stage tabs become acceptable if readability suffers.
- **Table/list behavior:** dense tables allowed only for a curated set of 4-5 columns; otherwise use structured row list.
- **Risks / anti-patterns to avoid:** half-preserved desktop two-column grids that create cramped reading zones; horizontal scrolling tables without frozen identity/status columns.

### Tablet portrait / small laptop (`768px - 991px`)
- **Layout model:** single primary content column; side nav drawer only; sections stack intentionally.
- **Navigation behavior:** drawer nav with clear current section state.
- **Page header behavior:** compact two-row header is acceptable; title first, actions second.
- **Toolbar/filter behavior:** search visible by default; filters become modal/drawer or expandable panel; saved view toggles may become segmented controls.
- **Detail page behavior:** summary header followed by a small facts strip; subsequent content becomes ordered stacked sections; accordions allowed for secondary information.
- **Pipeline behavior:** stage tabs or accordions are preferred over a miniature board.
- **Table/list behavior:** structured row list becomes primary for leads/clients and tasks.
- **Risks / anti-patterns to avoid:** stacking every desktop panel unchanged; preserving multiple equal-weight header rows; keeping desktop card grids that produce two cramped columns.

### Mobile (`< 768px`)
- **Layout model:** single-column task-first flow.
- **Navigation behavior:** drawer nav only; topbar may include menu, page title, and overflow action.
- **Page header behavior:** concise title line; a single primary action may sit beneath or inside the overflow if space is constrained; no descriptive hero copy unless critical.
- **Toolbar/filter behavior:** search stays visible on list screens; filters move into a bottom sheet, drawer, or expandable panel; secondary sort/view controls go to overflow or segmented control.
- **Detail page behavior:** summary header compresses to identity, status, owner, and one primary action; sections become stacked blocks or accordions; activity and related items move lower.
- **Pipeline behavior:** stage list, tabs, or accordion sections with compact cards; never a horizontally miniaturized kanban.
- **Table/list behavior:** structured row list only; each row shows name/title, status, due date or stage, and a single quick action; metadata drops below the primary line or into overflow.
- **Risks / anti-patterns to avoid:** full desktop tables on narrow screens, filter bars wrapped into 3-4 lines, equal emphasis on all metadata, long unprioritized detail pages.

## 5. Layout System

### 5.1 Grid philosophy
Appearance rule: surfaces should look calm and compact. Layout rule: the grid must create stable scan paths and enforce a small set of page structures. PeraCRM should use a restrained app grid with a persistent nav column where space allows and a primary content region that expands to maximize working width. Masonry layouts are not allowed. Auto-fit card grids are limited to KPI strips and clearly secondary utility modules. Operational content should use either full-width sections, structured row lists, or fixed two-column detail layouts.

### 5.2 Content widths
- App shell main content should target a comfortable operational width of roughly `1120px - 1280px` inside the shell, depending on nav persistence.
- Full-width tables are preferred on desktop and may extend wider than card-based content when the shell permits.
- Create/edit forms should use a narrower content width of roughly `720px - 880px` for readability.
- Detail pages may use a main column of roughly 60-70% plus a right rail of 30-40% only when the rail contains compact supporting content.
- Large desktop should use extra width to show more rows or better table columns, not more decoration.

### 5.3 Section spacing
- Base vertical rhythm: 8px increments.
- Typical section padding inside panels: 16px desktop, 12-16px tablet, 12px mobile.
- Gap between major stacked sections: 24px desktop, 20px tablet, 16px mobile.
- Gap between related controls and metadata rows: 8px or 12px.
- Avoid bespoke 10/18/22/30px rhythms. Standardize on 8/12/16/24/32.

### 5.4 Toolbar/header spacing
- Page headers should consume minimal vertical depth: ideally 56-80px total for title, count/context, and actions on desktop.
- Toolbar rows should be 44-52px high for control alignment, with 8-12px internal gaps.
- Filters should live directly under or inline with the page header, not in a separate hero band.
- Desktop should not spend more than one compact band on page identity and one compact band on controls before the main content begins.

### 5.5 Detail page column rules
- Use **one column** when the page is primarily form-heavy, note-heavy, or linear in workflow.
- Use **two columns** only when there is a clear difference between primary operational work and secondary supporting context.
- The left/main column should contain editable profile, reminders/tasks, notes, and activity in order.
- The right rail is appropriate for compact facts, quick actions, KPI counts, or related links; it is not appropriate for equally important panels that destroy scan order.
- No masonry or auto-placement columns based on content height.
- On tablet and below, detail pages collapse to one column in deliberate order.

### 5.6 Pipeline column rules
- Desktop pipeline columns should be compact and uniform, optimized for 3-6 cards visible at a glance.
- Card width should stay narrow enough to support stage comparison and quick scanning; avoid broad cards with multiple pill rows.
- Horizontal scrolling is acceptable on desktop and tablet landscape, but cards must remain concise.
- Tablet portrait and mobile should switch from board thinking to stage-group thinking using tabs, segmented controls, or accordion sections.
- Stage counts must remain visible in every mode.

## 6. Typography System
Appearance rule: typography defines information hierarchy without decorative styling. Layout rule: the type ladder must fit dense desktop views and survive breakpoint compression without changing meaning.

- **Page title**
  - **Font size:** 24px
  - **Line height:** 32px
  - **Weight:** 600
  - **Use case:** page-level identity in the compact header.
  - **Usage notes:** use one line when possible; allow wrapping at tablet/mobile without increasing emphasis elsewhere.

- **Section title**
  - **Font size:** 18px
  - **Line height:** 26px
  - **Weight:** 600
  - **Use case:** titles for dashboard bands, major detail sections, list modules.
  - **Usage notes:** avoid pairing section titles with decorative pills unless the pill is a true status/count.

- **Card/record title**
  - **Font size:** 15px
  - **Line height:** 22px
  - **Weight:** 600
  - **Use case:** client names, lead names, pipeline card titles, summary row identity.
  - **Usage notes:** should be visually stronger than all supporting metadata in the same object.

- **Table primary text**
  - **Font size:** 14px
  - **Line height:** 20px
  - **Weight:** 500 or 600 for the primary identity column
  - **Use case:** row identity in tables and structured lists.
  - **Usage notes:** use 400 for secondary cells; primary column gets modest emphasis only.

- **Body text**
  - **Font size:** 14px
  - **Line height:** 20px
  - **Weight:** 400
  - **Use case:** paragraph text, note text, normal field values.
  - **Usage notes:** default reading size across the CRM.

- **Metadata text**
  - **Font size:** 12px
  - **Line height:** 16px
  - **Weight:** 400 or 500
  - **Use case:** timestamps, sources, owner labels, secondary qualifiers.
  - **Usage notes:** metadata should recede through color and weight, not only size.

- **Form labels**
  - **Font size:** 12px
  - **Line height:** 16px
  - **Weight:** 600
  - **Use case:** field labels.
  - **Usage notes:** sentence case by default; visually quieter than entered values but stronger than helper text.

- **Helper/error text**
  - **Font size:** 12px
  - **Line height:** 16px
  - **Weight:** 400
  - **Use case:** field help, validation, inline notices.
  - **Usage notes:** errors may use semantic color; helper text should remain subdued.

- **KPI/value text**
  - **Font size:** 24px desktop, 22px tablet/mobile
  - **Line height:** 28px
  - **Weight:** 600
  - **Use case:** count tiles, key summary values.
  - **Usage notes:** reserved for truly summary values, not everyday row data.

- **Button text**
  - **Font size:** 14px
  - **Line height:** 20px
  - **Weight:** 600
  - **Use case:** primary, secondary, tertiary, segmented controls.
  - **Usage notes:** keep labels short and explicit; avoid all-caps.

- **Chip text**
  - **Font size:** 12px
  - **Line height:** 16px
  - **Weight:** 600
  - **Use case:** status chips, concise tags.
  - **Usage notes:** use sentence case or short title case; uppercase is not the default.

Additional rules:
- Sentence case is the default for page titles, section titles, labels, buttons, chips, and table headers.
- Uppercase is reserved only for rare micro-eyebrows or utility overlines, not routine form labels or status chips.
- Labels should be visually quieter than values when paired together in summary rows, detail pairs, and forms.
- Typography anti-patterns to avoid: all-caps field labels across long forms, KPI values used in regular cards, multiple heading sizes doing the same job, and metadata matching record-title weight.

## 7. Surface, Border, and Elevation System
Appearance rule: surfaces should feel controlled and low-ornament. Layout rule: containment should reinforce section boundaries without building nested card stacks.

- **Panel surfaces:** default operational container; neutral/white background with subtle border.
- **Cards:** reserved for summary objects, pipeline cards, KPI tiles, and isolated utility modules.
- **Grouped sections:** preferred inside detail pages and forms; use simple headers with internal dividers instead of separate floating cards where possible.
- **Table containers:** flat surface with clear outer border and row dividers.
- **Inline rows:** rely on spacing and dividers more than independent card shells.
- **Notices:** semantic container with distinct tone and left accent or subtle tinted background.
- **Empty states:** bounded but lightweight block with title, explanation, and suggested next action.
- **Overlays/drawers/modals:** stronger elevation than page surfaces, but still restrained and crisp.

Specific standards:
- **Radius strategy:** 10-12px for most panels and cards; 8px for dense controls/inputs; 16px maximum for special overlay shells. Current 20-32px radii are too soft for CRM density.
- **Border strategy:** 1px neutral borders are the default containment method. Use row dividers inside dense lists and sections. Borders should do more work than shadows.
- **Shadow/elevation strategy:** minimal shadows on overlays and, optionally, select summary cards. Most panels should be flat or nearly flat.
- **Containment rules:** one panel can contain multiple rows with dividers; do not place many bordered pills inside a bordered row inside a bordered card unless those pills are true statuses.

Be explicit:
- Use borders instead of shadows for page panels, tables, grouped sections, and dense row lists.
- Avoid nested bordered objects when a section-level boundary already exists.
- Use a full card shell only when the object is independently actionable or needs clear standalone identity, such as a pipeline card, KPI tile, or compact summary card.
- Use row dividers rather than full card shells for activity feeds, notes lists, tasks lists within detail pages, and related-record lists.

## 8. Button, Control, and Interaction Hierarchy
Appearance rule: controls must convey hierarchy and intent. Layout rule: action placement must stay consistent enough that users know where to look before reading labels.

### 8.1 Primary button
- **Purpose:** main page or section completion action.
- **Visual role:** filled button with strongest contrast.
- **Where it should be used:** create lead, save changes, confirm stage-changing workflow when it is the main action.
- **Where it should not be used:** nav links, row utilities, view switches, filter clear actions, destructive actions.
- **Mobile considerations:** keep only one visible primary button in the header area; others move to footer or overflow.
- **Density considerations:** medium height, compact padding, short label.

### 8.2 Secondary button
- **Purpose:** supporting but important action.
- **Visual role:** outlined or low-emphasis filled button.
- **Where it should be used:** clear filters, add note, export, cancel, optional follow-up actions.
- **Where it should not be used:** as the only visually dominant CTA on a page that has a true primary action.
- **Mobile considerations:** may sit beside primary only when labels are short; otherwise stack.
- **Density considerations:** same height as primary for alignment.

### 8.3 Tertiary/text button
- **Purpose:** low-friction supporting actions.
- **Visual role:** text-only or minimally framed link-button.
- **Where it should be used:** “See all,” “Clear,” “View details,” inline secondary actions.
- **Where it should not be used:** high-risk confirmations or key completion actions.
- **Mobile considerations:** good for inline actions inside dense sections.
- **Density considerations:** ideal for reducing chrome in crowded toolbars.

### 8.4 Utility/icon button
- **Purpose:** compact utility actions such as more options, close, or refresh.
- **Visual role:** square or circular low-emphasis icon control.
- **Where it should be used:** row action menus, dismiss buttons, shell controls.
- **Where it should not be used:** as the only affordance for critical business actions without labels.
- **Mobile considerations:** useful for overflow menus; ensure touch target remains sufficient.
- **Density considerations:** avoids long button groups in tables and row lists.

### 8.5 Segmented control
- **Purpose:** switch modes or views within the same data set.
- **Visual role:** shared segmented container with one selected state.
- **Where it should be used:** list/table mode, lead/client/inactive type toggle, stage subsets where appropriate.
- **Where it should not be used:** form submission, destructive actions, unrelated shortcuts.
- **Mobile considerations:** may scroll horizontally or reduce to 2-3 key segments.
- **Density considerations:** lower visual weight than primary CTA.

### 8.6 Chips/tags/status pills
- **Purpose:** show concise state, category, or filter tokens.
- **Visual role:** small contained label, mostly subtle unless semantic urgency applies.
- **Where it should be used:** record status, overdue indicator, stage, source tag when genuinely useful, selected filters.
- **Where it should not be used:** generic metadata, action links, row buttons, long descriptive text.
- **Mobile considerations:** limit quantity aggressively; overflow extra metadata into text rows.
- **Density considerations:** short labels only.

### 8.7 Form controls
- **Purpose:** capture or edit values.
- **Visual role:** rectangular fields with modest radius, clear borders, focused state distinct from button styles.
- **Where it should be used:** all data entry contexts.
- **Where it should not be used:** status-display contexts or static summaries.
- **Mobile considerations:** single-column fields; labels remain visible.
- **Density considerations:** controls should be compact enough for dense forms but not cramped.

### 8.8 Row actions
- **Purpose:** act on one record from a list or row.
- **Visual role:** text links, icon buttons, or overflow menu; never a cluster of large pills.
- **Where it should be used:** list pages, related records, task queues.
- **Where it should not be used:** as a page-level primary action substitute.
- **Mobile considerations:** show one quick action and move the rest into overflow.
- **Density considerations:** must preserve row readability.

### 8.9 Destructive actions
- **Purpose:** delete, archive, remove, or irreversible reset.
- **Visual role:** red text or outlined treatment, with separation from neutral and confirmatory actions.
- **Where it should be used:** only where destructive intent is real.
- **Where it should not be used:** for overdue state, warnings, or ordinary “mark done” operations.
- **Mobile considerations:** keep away from thumb-adjacent primary actions and require clear confirmation flows later.
- **Density considerations:** destructive actions should not dominate dense toolbars.

Explicit rule: toggles and mode switches must not look like submit actions. The current button treatment in `crm.css` and `crm.js` should later separate segmented controls from solid/ghost command buttons instead of reusing the same CTA language.

## 9. Data Display Patterns
Appearance rule: repeated data must look consistent. Layout rule: each data type must have a default rendering pattern appropriate to the screen and breakpoint.

### 9.1 Dense table
- **What it is for:** high-volume desktop triage and comparison.
- **Ideal information density:** high; multiple comparable columns in compact rows.
- **Required fields:** primary identity, status/stage, owner/advisor if relevant, date or due date, one action cell.
- **Optional fields:** source, health, last activity, linked counts.
- **Whether it is desktop-primary or mobile-primary:** desktop-primary.
- **When it replaces another pattern:** replaces cards for leads/clients and tasks on desktop.
- **Anti-patterns:** too many weak columns, multiline row bloat, row actions as large pills.

### 9.2 Structured row list
- **What it is for:** medium-density list when full tables do not fit.
- **Ideal information density:** medium-high.
- **Required fields:** title/name, primary status/date, one supporting metadata row, one quick action.
- **Optional fields:** owner, source, last activity.
- **Whether it is desktop-primary or mobile-primary:** tablet/mobile-primary; desktop-secondary.
- **When it replaces another pattern:** replaces dense tables below tablet landscape or in contextual lists inside detail pages.
- **Anti-patterns:** turning each row into a fluffy card with multiple pill clusters.

### 9.3 Summary card
- **What it is for:** small standalone summary object, usually on overview or side rail.
- **Ideal information density:** medium.
- **Required fields:** title, one main value or summary line.
- **Optional fields:** secondary meta, tiny action.
- **Whether it is desktop-primary or mobile-primary:** neither; supplemental.
- **When it replaces another pattern:** only when a summary is more important than a detailed list.
- **Anti-patterns:** using summary cards for core lead/task queues on desktop.

### 9.4 KPI strip
- **What it is for:** quick top-level counts with jump context.
- **Ideal information density:** medium-high across multiple small tiles.
- **Required fields:** label and value.
- **Optional fields:** tiny delta or helper text.
- **Whether it is desktop-primary or mobile-primary:** cross-breakpoint pattern.
- **When it replaces another pattern:** replaces large dashboard cards for top-line metrics.
- **Anti-patterns:** overly tall tiles, bright decorative colors on every KPI.

### 9.5 Detail summary header
- **What it is for:** establish record identity and primary actions.
- **Ideal information density:** medium, front-loaded.
- **Required fields:** record title, lifecycle/status, owner/advisor, primary action group.
- **Optional fields:** health, source, type, key counts.
- **Whether it is desktop-primary or mobile-primary:** desktop-primary and mobile-adapted.
- **When it replaces another pattern:** replaces hero headers and pill clouds on detail pages.
- **Anti-patterns:** many equal-weight chips, long intro copy, separated action rows without hierarchy.

### 9.6 Activity/timeline block
- **What it is for:** chronological history of actions and events.
- **Ideal information density:** medium-high using rows with dividers.
- **Required fields:** timestamp, event type, summary.
- **Optional fields:** actor, linked object, inline action.
- **Whether it is desktop-primary or mobile-primary:** cross-breakpoint.
- **When it replaces another pattern:** replaces generic unordered pill-heavy lists.
- **Anti-patterns:** every event in a fully bordered card, or event type displayed as loud decorative pill unless state significance exists.

### 9.7 Notes/reminders block
- **What it is for:** actionable note-taking and reminder tracking in detail pages.
- **Ideal information density:** medium-high.
- **Required fields:** note/reminder text, due date or timestamp, status, owner if relevant.
- **Optional fields:** quick complete/edit actions.
- **Whether it is desktop-primary or mobile-primary:** cross-breakpoint.
- **When it replaces another pattern:** replaces oversized task cards and loosely structured note paragraphs.
- **Anti-patterns:** isolated reminder cards with repeated labels and too much whitespace.

### 9.8 Empty states
- **What it is for:** explain absence of data and suggest the next step.
- **Ideal information density:** low but precise.
- **Required fields:** title, explanation.
- **Optional fields:** primary or tertiary action, filter-reset hint.
- **Whether it is desktop-primary or mobile-primary:** cross-breakpoint.
- **When it replaces another pattern:** every time a list or board is empty.
- **Anti-patterns:** “No items found” with no context.

### 9.9 Status presentation
- **What it is for:** communicate stage, health, urgency, completion, or blocking state.
- **Ideal information density:** concise.
- **Required fields:** short label.
- **Optional fields:** icon or subtle semantic tint.
- **Whether it is desktop-primary or mobile-primary:** cross-breakpoint.
- **When it replaces another pattern:** replaces verbose metadata strings where a standard status token exists.
- **Anti-patterns:** using chips for everything, multiple chip colors with no semantic system.

### 9.10 Pipeline card anatomy
- **What it is for:** compact representation of a lead/client within a stage.
- **Ideal information density:** medium; enough for movement decisions, not full detail.
- **Required fields:** title/name, owner or advisor, last activity or next action date, one secondary qualifier such as source or budget, click target.
- **Optional fields:** overdue indicator, health, key badge.
- **Whether it is desktop-primary or mobile-primary:** desktop-primary inside pipeline; mobile-adapted into list-like cards.
- **When it replaces another pattern:** always inside the pipeline.
- **Anti-patterns:** multiple pill rows, wide narrative descriptions, large button groups inside every card.

Default desktop patterns:
- **Clients/leads:** dense table.
- **Tasks:** dense table on dedicated task list; structured row list inside dashboard/detail contexts.
- **Dashboard queues:** structured row list, not decorative cards, except compact KPI strip at the top.
- **Pipeline:** pipeline card anatomy inside stage columns.
- **Client detail related items:** structured row list or compact table depending on comparison needs.

## 10. Navigation and Information Architecture Rules
Appearance rule: navigation should feel stable and quiet. Layout rule: navigation occupies persistent structure on desktop and becomes explicit access on smaller breakpoints.

- Persistent navigation should contain enduring destinations only: Overview, Clients, Tasks, Pipeline, Logs or utilities that are true destinations.
- Page-level actions should contain creation or workflow actions such as “Create lead,” “Save changes,” or “Add note.”
- Contextual actions inside records should contain row-level or record-level operations such as call, WhatsApp, mark done, edit, archive, or delete.
- “Create lead” does **not** belong in persistent navigation. It is an action, not a destination, and should live in the page header action area.
- The sidebar should identify the workspace and primary destinations. It should not duplicate page identity already expressed by the page header.
- A topbar is needed only as a compact shell utility on tablet/mobile for the nav toggle, current page title, and possibly one overflow action. Desktop does not need a large topbar if the left nav is persistent.

Breakpoint behavior:
- **Desktop:** persistent sidebar, compact page headers, no hero-brand duplication.
- **Tablet:** nav drawer from a compact top shell; page title remains the local workspace anchor.
- **Mobile:** drawer nav only, with one compact top row; keep navigation discovery simple and never mix it with large promotional copy.

## 11. Responsive Behavior by Major Screen

### 11.1 Overview/dashboard
- **Desktop layout:** KPI strip across the top, urgent work rows first, new leads second, activity/secondary metrics lower in a 2-column command-center arrangement.
- **Tablet layout:** single dominant column; KPI strip may become horizontally scrollable; urgent queues remain first.
- **Mobile layout:** overdue and due today first, then new leads, then activity; metrics compress into small tiles or stacked strip.
- **Controls that stay visible:** primary CTA, queue-specific “See all,” top summary counts.
- **Controls that collapse:** passive filters or utility controls, notification extras.
- **Content that must remain above the fold:** overdue tasks, due today tasks, top-level counts.
- **Content that can move lower:** passive activity, notifications, secondary diagnostics.
- **What should become tabs, accordions, drawers, or stacked sections:** lower-priority metrics and utilities may become accordions on tablet/mobile.

### 11.2 Leads/clients list
- **Desktop layout:** compact header + toolbar + full-width dense table.
- **Tablet layout:** header remains compact; search visible; filters in collapsible panel; structured row list if table width fails.
- **Mobile layout:** structured row list with primary metadata line and overflow menu.
- **Controls that stay visible:** search, current view/type selection, primary CTA.
- **Controls that collapse:** secondary filters, density/sort options beyond the essential default.
- **Content that must remain above the fold:** search/filter access, list identity/count, first rows.
- **Content that can move lower:** explanatory copy, secondary view controls.
- **What should become tabs, accordions, drawers, or stacked sections:** type toggles can remain segmented; filters should become drawer/panel on smaller screens.

### 11.3 Tasks
- **Desktop layout:** dense table by default, emphasizing due date, status, related client, and quick complete action.
- **Tablet layout:** compact row list if table becomes too wide; overdue and today filters are prominent.
- **Mobile layout:** row list with task text, due date, related client, and one quick action.
- **Controls that stay visible:** key task filters, search if present, primary quick action for completion where appropriate.
- **Controls that collapse:** low-priority sort options and batch tools.
- **Content that must remain above the fold:** overdue/today task visibility and quick completion path.
- **Content that can move lower:** passive notes preview, secondary metadata.
- **What should become tabs, accordions, drawers, or stacked sections:** overdue/today/upcoming can become tabs or segmented views on smaller screens.

### 11.4 Client detail
- **Desktop layout:** summary header at top; main workflow column for profile/reminders/notes/activity; optional right rail for compact facts/KPIs.
- **Tablet layout:** summary header followed by KPI/facts strip; all major sections stacked in fixed order.
- **Mobile layout:** identity and status first; primary action next; profile/actions/reminders in stacked sections; activity lower; less-used related items in accordions.
- **Controls that stay visible:** status, owner/advisor, primary record action, next reminder or overdue count.
- **Controls that collapse:** secondary quick actions, lower-priority related records, historical activity details.
- **Content that must remain above the fold:** record identity, current status/health, primary action, next critical reminder/task.
- **Content that can move lower:** long activity history, linked properties, older notes, historical deals.
- **What should become tabs, accordions, drawers, or stacked sections:** on smaller screens, related items and historical blocks may become accordions; the core summary should never become a hidden tab.

### 11.5 Pipeline
- **Desktop layout:** horizontal board with compact columns and concise cards.
- **Tablet layout:** board with horizontal scroll or stage tabs depending on readable card width.
- **Mobile layout:** stage tabs or accordion sections with compact list-like cards.
- **Controls that stay visible:** stage counts, stage selector, primary CTA.
- **Controls that collapse:** secondary filters and diagnostics.
- **Content that must remain above the fold:** current stage summary and first visible cards.
- **Content that can move lower:** secondary qualifiers like budget details when not crucial.
- **What should become tabs, accordions, drawers, or stacked sections:** stage groups should become tabs or accordions below 992px.

### 11.6 Create/edit screens
- **Desktop layout:** narrow main form column with grouped sections and clear save area.
- **Tablet layout:** same structure, full-width single column; save action remains anchored.
- **Mobile layout:** single field column with short section blocks; helper text minimized.
- **Controls that stay visible:** save/create primary action, cancel/back secondary action, required-field context.
- **Controls that collapse:** nonessential helper copy and secondary utilities.
- **Content that must remain above the fold:** form title, key required fields, primary save path.
- **Content that can move lower:** notes, secondary optional fields, contextual references.
- **What should become tabs, accordions, drawers, or stacked sections:** long optional sections may become accordions on mobile if the primary completion path remains visible.

## 12. Component Inventory and Reusable Primitives

- **crm-page-header**
  - **Purpose:** compact page identity + action region.
  - **Likely reuse scope:** all CRM pages.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** `crm-header.php`, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-toolbar**
  - **Purpose:** search, filters, segmented controls, secondary actions.
  - **Likely reuse scope:** list pages, pipeline, task views.
  - **Whether it should be global or page-specific:** global pattern with page-level variants.
  - **Likely current files involved:** `crm-header.php`, `crm-overview.php`, `crm.css`, `crm.js`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-section**
  - **Purpose:** standardized content section with header, body, and optional actions.
  - **Likely reuse scope:** overview, detail, forms.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** all page templates, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-summary-header**
  - **Purpose:** record detail summary region.
  - **Likely reuse scope:** client detail and future detailed entities.
  - **Whether it should be global or page-specific:** global detail-page primitive.
  - **Likely current files involved:** `crm-client.php`, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-table**
  - **Purpose:** canonical dense desktop list surface.
  - **Likely reuse scope:** clients, tasks, related items.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** `crm-overview.php`, `crm.css`, `crm.js`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-row-list**
  - **Purpose:** canonical structured row list for tablet/mobile and contextual modules.
  - **Likely reuse scope:** overview queues, client detail related items, responsive list fallback.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** `crm-overview.php`, `crm-client.php`, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-summary-card**
  - **Purpose:** compact standalone summary object.
  - **Likely reuse scope:** KPI/summary areas and optional side rails.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** `crm-overview.php`, `crm-client.php`, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-kpi-strip**
  - **Purpose:** standard top-level KPI band.
  - **Likely reuse scope:** overview and detail summaries.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** `crm-overview.php`, `crm-client.php`, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-chip**
  - **Purpose:** generic subtle tag or selected-filter token.
  - **Likely reuse scope:** statuses, filter tokens, small labels.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** `crm.css`, page templates using `.pill`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-status**
  - **Purpose:** semantically controlled status presentation distinct from generic chips.
  - **Likely reuse scope:** tables, summary headers, tasks, pipeline.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** `crm.css`, `crm-overview.php`, `crm-client.php`, `crm-pipeline.php`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-empty-state**
  - **Purpose:** standard no-data state with explanation and action.
  - **Likely reuse scope:** all lists, sections, and board columns.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** all page templates, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-side-nav**
  - **Purpose:** persistent/drawer navigation shell.
  - **Likely reuse scope:** all CRM routes.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** `crm-side-nav.php`, `crm.js`, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-action-group**
  - **Purpose:** standardized alignment and priority of primary/secondary/utility actions.
  - **Likely reuse scope:** page headers, section headers, summary headers, forms.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** page templates, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-form-section**
  - **Purpose:** grouped form subsection with title, fields, and helper copy.
  - **Likely reuse scope:** create/edit forms and detail edit modules.
  - **Whether it should be global or page-specific:** global.
  - **Likely current files involved:** `crm-new.php`, `crm-client.php`, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

- **crm-pipeline-card**
  - **Purpose:** standard compact pipeline record object.
  - **Likely reuse scope:** pipeline screen only, but designed as a formal primitive.
  - **Whether it should be global or page-specific:** page-specific primitive.
  - **Likely current files involved:** `crm-pipeline.php`, `crm.css`.
  - **Whether it is mostly CSS, markup, or both:** both.

## 13. Mapping from Current UI to Target UI

| Current pattern | Problem | Target pattern | Why the replacement is better | Likely affected files |
|---|---|---|---|---|
| Hero header | Consumes vertical space and frames CRM pages like marketing pages | Compact `crm-page-header` | Brings task content above the fold and clarifies page purpose faster | `crm-header.php`, `crm.css`, page templates |
| Hero-embedded filters | Splits page identity from working controls | `crm-toolbar` below/in header band | Keeps search and filter activity in the operational control zone | `crm-header.php`, `crm-overview.php`, `crm.css` |
| Pill buttons for many control roles | Flattens hierarchy between submit, toggle, and utility actions | Primary/secondary/text/segmented system | Makes intent clearer and restores one dominant CTA | `crm.css`, `crm.js`, page templates |
| “Create lead” in side nav | Mixes action with destination IA | Header primary action | Aligns with CRM navigation standards and reduces nav ambiguity | `crm-side-nav.php`, page templates |
| Plush `card-shell` everywhere | Adds visual noise and lowers density | Flat bordered sections and selective cards | Keeps chrome lighter and lets hierarchy come from structure | `crm.css`, all CRM templates |
| KPI cards with pill labels | Overuses chips and weakens value hierarchy | `crm-kpi-strip` with label/value pair | Improves at-a-glance reading and reduces decoration | `crm-overview.php`, `crm-client.php`, `crm.css` |
| Mixed list/card behavior for clients | Default presentation is inconsistent and cards are too sparse | Desktop `crm-table`, responsive `crm-row-list` | Creates one canonical triage model per breakpoint | `crm-overview.php`, `crm.js`, `crm.css` |
| `.crm-list` used for multiple anatomies | Density and semantics vary unpredictably | Separate row-list, activity-list, and utility-list primitives | Standardizes rendering expectations | `crm.css`, `crm-overview.php`, `crm-client.php` |
| Client header strip with many pills | Identity, status, owner, and metadata blur together | `crm-summary-header` | Establishes clear lanes for identity, status, and actions | `crm-client.php`, `crm.css` |
| Masonry-like client detail composition | Scan order depends on viewport shape | Fixed ordered detail grid | Supports predictable workflow scanning | `crm-client.php`, `crm.css` |
| Pipeline card meta rendered as pill clouds | Metadata is too loud and cards are too soft | Compact `crm-pipeline-card` with plain meta rows | Improves stage scanning and reduces visual clutter | `crm-pipeline.php`, `crm.css` |
| Card grids for dashboard queues | Too much vertical space for small amounts of data | Structured row lists for queues | More records visible at once and urgency is clearer | `crm-overview.php`, `crm.css` |
| Plain empty messages | Do not guide the next step | `crm-empty-state` | Helps users recover from no-results and filtered states | page templates, `crm.css` |

## 14. Priority Refactor Roadmap

### 14.1 Phase 1 — Structural foundations
- **Objective:** replace the marketing-style shell assumptions with a CRM app-shell model.
- **Scope:** page header model, nav IA cleanup, global layout widths, breakpoint rules, section containment.
- **Expected impact:** highest improvement in scan speed and perceived seriousness of the CRM.
- **Implementation risk:** medium, because header/nav changes affect all CRM pages.
- **Dependency notes:** should happen before local component restyling; moving “Create lead” out of nav is part of this phase.

### 14.2 Phase 2 — Reusable system primitives
- **Objective:** establish canonical components and tokens for repeated CRM structures.
- **Scope:** page header, toolbar, section, summary header, KPI strip, table, row list, chip/status, empty state, action groups.
- **Expected impact:** reduces visual inconsistency and accelerates page-by-page cleanup.
- **Implementation risk:** medium; broad CSS impact but conceptually straightforward.
- **Dependency notes:** depends on Phase 1 layout decisions so primitives fit the final shell.

### 14.3 Phase 3 — Screen-by-screen refactor
- **Objective:** apply the system to high-value CRM screens in priority order.
- **Scope:** overview/dashboard first, then leads/clients list, tasks, client detail, pipeline, create/edit screens.
- **Expected impact:** visible workflow improvement where users spend most time.
- **Implementation risk:** medium-high, especially for client detail due to mixed content and legacy markup.
- **Dependency notes:** should reuse Phase 2 primitives rather than introducing page-local styles.

### 14.4 Phase 4 — Responsive refinement and polish
- **Objective:** enforce task-preserving responsive behavior rather than simple stacking.
- **Scope:** tablet/mobile toolbar strategy, table-to-row-list transitions, pipeline stage adaptation, detail accordions, overflow actions.
- **Expected impact:** makes the CRM credible on smaller screens and prevents desktop problems from being inherited.
- **Implementation risk:** medium, with some JS interaction updates likely required.
- **Dependency notes:** depends on canonical desktop patterns being stable first.

## 15. File-Level Impact Map
- **`wp-content/plugins/peracrm/assets/frontend/crm.css`**
  - **What likely needs to change later:** design tokens, spacing rhythm, typography ladder, button hierarchy, panel/card treatment, toolbar/list/detail/pipeline primitives, breakpoint rules.
  - **Whether the changes are structural, stylistic, behavioral, or mixed:** mixed, with strong stylistic and layout-system impact.

- **`wp-content/plugins/peracrm/assets/frontend/crm.js`**
  - **What likely needs to change later:** segmented control handling, list-mode logic, responsive nav behavior, filter drawers, possible accordion or stage-tab interactions.
  - **Whether the changes are structural, stylistic, behavioral, or mixed:** behavioral.

- **`wp-content/plugins/peracrm/inc/views/partials/crm-header.php`**
  - **What likely needs to change later:** convert hero header into compact page header and toolbar pattern; relocate filters and actions.
  - **Whether the changes are structural, stylistic, behavioral, or mixed:** structural.

- **`wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`**
  - **What likely needs to change later:** remove action-style items from persistent nav, tune destination grouping, support cleaner desktop/mobile nav behavior.
  - **Whether the changes are structural, stylistic, behavioral, or mixed:** structural and behavioral.

- **`wp-content/plugins/peracrm/inc/views/pages/crm-overview.php`**
  - **What likely needs to change later:** reorder dashboard by urgency, replace card-heavy queues with row-list structures, standardize KPI and empty states, clarify list page treatment for leads/tasks variants.
  - **Whether the changes are structural, stylistic, behavioral, or mixed:** mixed.

- **`wp-content/plugins/peracrm/inc/views/pages/crm-client.php`**
  - **What likely needs to change later:** replace pill-heavy summary strip, impose fixed detail order, formalize section primitives, reduce nested card shells, separate primary and contextual actions.
  - **Whether the changes are structural, stylistic, behavioral, or mixed:** mixed, with major structural impact.

- **`wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php`**
  - **What likely needs to change later:** tighten column/card anatomy, reduce pill usage, add responsive stage behavior, improve stage-level control area.
  - **Whether the changes are structural, stylistic, behavioral, or mixed:** mixed.

- **`wp-content/plugins/peracrm/inc/views/pages/crm-new.php`**
  - **What likely needs to change later:** shift from hero-led single card form to grouped form sections with tighter labels, clearer action anchoring, and improved responsive form flow.
  - **Whether the changes are structural, stylistic, behavioral, or mixed:** mixed.

## 16. Final Recommended House Style for PeraCRM
PeraCRM should use a compact app-shell page model with persistent destination navigation on desktop, a calm page header, and immediate access to working content. The default list model on desktop should be a dense table for leads/clients and tasks, with structured row lists as the canonical adaptive pattern on tablet and mobile. The default detail model should be a summary header followed by a fixed ordered workflow layout, never a masonry panel cloud. Action hierarchy should be explicit: one primary button, supporting secondary actions, tertiary text actions, utility icon actions, and segmented controls for mode switching. The type ladder should be restrained and stable, centered on 24/32 page titles, 18/26 section titles, 15/22 record titles, 14/20 body text, and 12/16 metadata and labels. The spacing rhythm should standardize on 8/12/16/24/32 increments. Borders should be the primary containment method, with minimal shadows and moderate radii. Chips should be reserved for true statuses, tags, and selected filters, not for every fragment of metadata. Responsive strategy should simplify deliberately: preserve essential actions and statuses, collapse secondary filters into drawers, convert tables to row lists, and convert pipeline boards into stage groups rather than shrinking the same desktop structure. When finished, PeraCRM should feel focused, durable, and operational: calm instead of promotional, dense instead of fluffy, and predictable enough that experienced staff can move through daily workflows with minimal visual friction.
