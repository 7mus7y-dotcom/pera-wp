# PeraCRM Phase 3.1 Client Detail Refinement Notes

## What Phase 3.1 refined relative to Phase 3
Phase 3 established the first non-masonry client detail layout and introduced the shared `crm-summary-header`, `crm-section`, and row-list usage on the record page.

Phase 3.1 is a focused refinement pass on that same client detail route. It does **not** rewrite the page or broaden the refactor to other CRM screens. The work in this pass specifically:
- consolidates the top-of-page record summary into a tighter workflow-led summary header;
- makes the reading order more explicit so reminders and next actions sit clearly ahead of profile, notes, timeline, and related modules;
- increases operational visibility for overdue / due-today / next-step reminder state;
- demotes timeline/history and related records so they read as support context rather than competing primary work surfaces;
- reduces the remaining “stack of generic card sections” feeling by tightening spacing and adjusting hierarchy within the existing client detail structure.

## Final client detail section order
The final intended order on the client detail page is:
1. shared CRM page header;
2. client summary header;
3. next actions and reminders;
4. profile and key facts;
5. advisor notes;
6. activity and timeline;
7. related records and secondary modules.

## Summary header changes
The summary header was refined so it now behaves like a true compact record summary instead of a fragmented strip plus follow-on stat boxes.

It now groups:
- client identity and lifecycle state;
- advisor / ownership / record context metadata;
- key client/source/contact context;
- a visible next-step signal;
- compact operational counts;
- primary actions for reminder creation, note capture, and direct contact.

The header intentionally stays compact and avoids a hero/banner treatment. It uses one ranked summary surface instead of several competing mini summary regions.

## Reminders / workflow emphasis
The reminders area remains directly beneath the summary and is now the clearest operational block on the page.

Refinements include:
- an explicit “Next actions and reminders” heading;
- an at-a-glance operational strip for overdue count, due-today count, and next step;
- task groups ordered to emphasize overdue work before due today and upcoming work;
- preserved existing reminder forms/actions so workflow logic and handlers remain unchanged.

## Activity/timeline treatment
The activity/timeline section remains on the page but is intentionally calmer than the summary and reminders.

It is still chronological, but its surface and item treatment are quieter so it reads as history rather than the primary operational area.

## Support rail usage
Yes. A secondary support rail is still used on desktop, but it remains clearly secondary.

In this pass it continues to hold the danger-zone area only. Primary workflow content stays in the main column and related records remain below notes/timeline rather than moving back toward the top.

## Intentionally deferred
Still intentionally deferred after Phase 3.1:
- overview/dashboard reorder work;
- list-page redesign;
- tasks-page redesign;
- pipeline redesign;
- broad form-system overhaul outside the existing client detail scope;
- backend/business-logic rewrites;
- unrelated CRM page refactors.

## `crm.js` changed?
No. `wp-content/plugins/peracrm/assets/frontend/crm.js` was intentionally left unchanged in this Phase 3.1 pass.

## Manual QA checklist
### Desktop
- Confirm the client detail page reads in the intended order from summary -> reminders -> profile -> notes -> timeline -> related.
- Confirm the summary header shows identity, lifecycle state, advisor context, source/contact context, counts, and actions without feeling hero-like.
- Confirm overdue / due-today / next-step information is visible without scrolling into notes or timeline.
- Confirm timeline feels quieter than reminders and profile.
- Confirm related records and portfolio/deal modules feel secondary and do not visually compete with the top workflow sections.
- Confirm the support rail remains secondary and does not disrupt reading order.

### Tablet
- Confirm the layout collapses into a coherent single reading column without masonry-like jumps.
- Confirm summary content and action buttons wrap cleanly.
- Confirm reminder group ordering remains overdue -> due today -> upcoming.
- Confirm related records still stay below notes/timeline.

### Mobile
- Confirm the summary header remains compact and readable.
- Confirm action buttons remain usable without causing overflow.
- Confirm reminder counts and next-step content remain visible near the top.
- Confirm notes, timeline, and related modules stack cleanly in the intended order.
- Confirm linked property, deal, reminder, and note actions remain accessible.

### Workflow / regression checks
- Confirm reminder add / mark-done actions still submit correctly.
- Confirm note add / delete actions still submit correctly.
- Confirm profile/status/advisor forms still submit correctly.
- Confirm deal create/update/delete flows still work.
- Confirm property link/unlink and portfolio actions still render correctly.
- Confirm there are no regressions to the Phase 1 shell/page-header or Phase 2 section/list/chip primitives.
- Confirm no unrelated CRM screens were changed as part of this pass.

## Phase 3.2 polish pass
Phase 3.2 is a minor refinement pass on the already-refactored client detail page. It does **not** restructure the route, reorder sections, expand into other CRM screens, or introduce a new UI system.

### What was polished
- flattened the client summary header surface so it reads as a quieter primary CRM container rather than a decorative banner;
- tightened vertical spacing across the summary title row, meta row, fact chips, KPI strip, and adjacent section stack so the top of the page reads faster;
- adjusted summary meta wrapping so tablet/mobile can break onto cleaner multi-line rows instead of cramped inline overflow;
- shortened the summary/header and next-actions “Next step” copy to workflow-first reminder labels, with reminder notes trimmed to a short supporting form when present;
- slightly compressed KPI card density so the operational counts support the summary instead of visually outweighing it;
- softened remaining legacy green pill/toggle styling so those controls sit closer to the calmer CRM system language without changing behavior;
- kept the right support rail visually secondary and avoided adding any new emphasis there.

### What was intentionally not changed
- no section reordering;
- no new primitives, components, or interaction systems;
- no dashboard, list/table, pipeline, or other CRM screen changes;
- no backend/business-logic changes;
- no `crm.js` changes.

### Structural confirmation
The client detail page keeps the exact same Phase 3.1 structure and workflow order:
1. shared CRM page header;
2. client summary header;
3. next actions and reminders;
4. profile and key facts;
5. advisor notes;
6. activity and timeline;
7. related records and secondary modules.

This pass only polishes density, wrapping, and visual calm within that existing structure.
