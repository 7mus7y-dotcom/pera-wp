# PeraCRM Phase 4 Overview Implementation Notes

## Summary of what changed
Phase 4 refactors only the CRM overview/dashboard route into an action-first workspace.

The overview no longer opens with a stack of equal-weight cards. It now:
- starts with a ranked **Priority work** band that surfaces overdue tasks first and due-today tasks second;
- keeps **New leads queue** directly below that operational band so fresh enquiries remain an obvious next click;
- retains a lighter **Task focus** section that points users into the full task workspace without competing with the urgent queue;
- keeps **Latest Activity** on the page but with visibly calmer treatment;
- demotes **KPI Snapshot** and **Pipeline Overview** below operational content;
- leaves lower-priority notices / notifications at the bottom of the overview stack.

The implementation intentionally reuses the shared Phase 2 primitives rather than creating a dashboard-only system. The overview now leans on:
- `crm-section`
- `crm-row-list`
- `crm-activity-list`
- `crm-chip`
- `crm-meta-line`
- existing `crm-action-group` / button group patterns where needed

## Files changed
- `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php`
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `docs/peracrm-phase4-overview-implementation-notes.md`

## Final overview section order
1. shared CRM page header;
2. **Priority work** band;
   - overdue tasks;
   - today’s tasks;
3. **New leads queue**;
4. **Task focus** summary / handoff into the tasks workspace;
5. **Latest Activity**;
6. **KPI Snapshot**;
7. **Pipeline Overview**;
8. **Workspace notices** (if present);
9. **Reminder Push Notifications** (if present for logged-in users).

## Top-priority vs secondary areas
### Top-priority
- Priority work / overdue tasks
- Priority work / today’s tasks
- New leads queue

### Secondary but still workflow-relevant
- Task focus handoff section

### Informational / calmer
- Latest Activity

### Demoted passive overview content
- KPI Snapshot
- Pipeline Overview
- Workspace notices
- Push notification diagnostics

## `crm.js` changed?
No. `wp-content/plugins/peracrm/assets/frontend/crm.js` was intentionally left unchanged in Phase 4.

## What was intentionally deferred
Still intentionally deferred in this phase:
- leads / clients list redesign;
- tasks page redesign;
- pipeline board redesign;
- create / edit screen overhaul;
- client detail refactor changes beyond existing Phase 3 work;
- backend/business-logic rewrites;
- routing changes;
- broader conversion of unrelated CRM pages.

## Manual QA checklist
### Desktop
- Confirm the overview reads: header -> priority work -> new leads -> task focus -> activity -> KPI snapshot -> pipeline -> lower-priority modules.
- Confirm overdue tasks are more visually prominent than due-today work.
- Confirm new leads feel like an operational queue, not a decorative card grid.
- Confirm Latest Activity is visibly calmer than the queues above it.
- Confirm KPI Snapshot and Pipeline Overview feel demoted and do not dominate the first screen.
- Confirm notices and push diagnostics stay below the main workspace content.

### Tablet
- Confirm priority work collapses into a clean single-column sequence without losing the overdue-before-today order.
- Confirm new leads rows wrap cleanly and actions stay accessible.
- Confirm no awkward two-column overflow appears in the priority or queue sections.
- Confirm activity and KPI sections remain readable after the collapse.

### Mobile
- Confirm the same operational order is preserved.
- Confirm overdue tasks still appear first near the top.
- Confirm row-list actions stack cleanly without horizontal overflow.
- Confirm KPI/pipeline sections remain lower in the scroll and visually calmer.
- Confirm notices / notifications remain secondary and do not interrupt the action-first start of the page.

### Regression / scope checks
- Confirm Phase 1 page shell and compact CRM header remain intact.
- Confirm Phase 2 primitives remain intact and reusable.
- Confirm reminder mark-done and link actions still submit correctly from overview rows.
- Confirm no unrelated CRM routes were refactored as part of this phase.
- Confirm Phase 5+ work was not started.
