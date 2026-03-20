# PeraCRM Phase 5 Lists and Tasks Implementation Notes

## Summary of what changed
Phase 5 refactors only the existing leads / clients list branch and tasks branch inside `crm-overview.php` so both pages read as operational list workspaces instead of equal-weight card/table screens.

The work specifically:
- promotes the desktop table as the default workspace for both leads / clients and tasks;
- demotes the alternate view into a secondary structured row-list fallback rather than a decorative card wall;
- tightens the in-content toolbar hierarchy so workspace summary and scope controls outrank the view toggle;
- keeps existing sorting, filters, query args, row clickthrough, links, pagination, and mark-done actions intact;
- reuses the Phase 2 primitives (`crm-toolbar`, `crm-table`, `crm-row-list`, `crm-chip`, `crm-section`, `crm-meta-line`, `crm-action-group`) instead of adding a page-specific system.

## Files changed
- `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php`
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `wp-content/plugins/peracrm/assets/frontend/crm.js`
- `docs/peracrm-phase5-lists-and-tasks-implementation-notes.md`

## Final control hierarchy for leads / clients
1. shared CRM page header;
2. existing header-level filter toolbar from Phase 1 shell (`search`, `stage`, `advisor`, apply / clear);
3. in-content workspace toolbar;
   - workspace summary / result count / active filter summary;
   - type scope toggle (`Leads`, `Clients`, `Inactive`);
   - secondary view toggle (`List view`, `Table view`), visually demoted;
4. primary table-first record list;
5. secondary structured row-list fallback.

## Final control hierarchy for tasks
1. shared CRM page header;
2. in-content workspace toolbar;
   - workspace summary;
   - scope/status summary chips (`overdue`, `due today`);
   - secondary view toggle (`List view`, `Table view`), visually demoted;
3. primary table-first task queue;
4. grouped row-list fallback sections (`Overdue tasks`, `Today's tasks`, `Upcoming tasks`).

## Desktop table preference
Yes. Desktop now defaults to **table mode** for both the leads / clients page and the tasks page when no saved preference already exists.

If a user already has a saved view in local storage, that saved preference is still respected so existing behavior is not broken.

## Responsive fallback handling
- Desktop keeps the table as the primary default workspace.
- Tablet keeps the same hierarchy but allows controls to wrap cleanly.
- Mobile defaults to the alternate structured row-list view when no saved preference exists.
- The alternate view is now a compact `crm-row-list` fallback instead of a decorative card gallery.
- Toolbar controls stack to full width on smaller breakpoints to avoid awkward overflow.

## Did `crm.js` change?
Yes, minimally.

`crm.js` was only updated so view toggles can honor per-page default preferences:
- desktop fallback default = table;
- mobile fallback default = row-list / alternate view;
- saved local-storage preference still wins when present.

No new interaction system was introduced.

## What was intentionally deferred
Still intentionally deferred after Phase 5:
- overview/dashboard changes beyond the existing Phase 4 work;
- client detail changes beyond the existing Phase 3.x work;
- pipeline redesign (Phase 6);
- create / edit screen refactors;
- unrelated CRM routes or backend business logic changes;
- a broader rewrite of the shared header partial.

## Manual QA checklist
### Desktop
- Confirm leads / clients opens into table view by default when no saved preference exists.
- Confirm tasks opens into table view by default when no saved preference exists.
- Confirm the leads / clients toolbar reads as summary -> scope toggle -> view toggle.
- Confirm the tasks toolbar reads as summary -> task scope chips -> view toggle.
- Confirm table sorting still works on both pages.
- Confirm row clickthrough still opens the correct client record.
- Confirm task mark-done still submits correctly from table and row-list views.
- Confirm card-wall styling is no longer the dominant desktop experience.

### Tablet
- Confirm toolbar controls wrap cleanly without overlap.
- Confirm tables remain readable without awkward control duplication.
- Confirm switching to the row-list fallback still works.
- Confirm leads / clients and tasks keep a clear scan path.

### Mobile
- Confirm pages default to the structured row-list fallback when no saved preference exists.
- Confirm no horizontal overflow appears in the toolbar or row actions.
- Confirm row-list actions remain tappable and readable.
- Confirm tasks still separate overdue, today, and upcoming work clearly.

### Regression / scope checks
- Confirm Phase 1 compact shell header remains intact.
- Confirm Phase 2 primitives remain intact and are reused rather than bypassed.
- Confirm leads / clients pagination and `type` query arg still work.
- Confirm header-level leads filters still submit and clear correctly.
- Confirm no Phase 6+ pipeline or create/edit work was started.
