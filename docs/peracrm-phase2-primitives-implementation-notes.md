# PeraCRM Phase 2 Primitives Implementation Notes

## Summary of what changed
Phase 2 introduced the shared data-display primitives needed for later CRM refactor phases without starting any screen-level layout rewrites.

New shared primitives added:
- `crm-section`
  - Reusable titled content block with header, optional action area, body, and flush variant.
  - Designed to replace ad hoc combinations of `.section`, `.card-shell`, and one-off wrappers in a gradual migration.
- `crm-table`
  - Canonical dense table styling for operational CRM data.
  - Adds clearer header treatment, tighter row density, better primary-cell emphasis, and reusable empty-state hooks.
- `crm-row-list`
  - Structured row-based list for medium-density data and smaller-width fallbacks.
  - Supports row title, metadata lines, semantic chip use, and trailing actions.
- `crm-chip`
  - Controlled semantic chip primitive with explicit variants for status, urgency, selected state, and neutral compact tags.
  - Intended to stop the previous pill-overuse pattern for generic metadata.
- List anatomy split foundations
  - Added `crm-activity-list` and `crm-utility-list` hooks so `.crm-list` no longer needs to keep expanding as a generic catch-all pattern.

## Files changed
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-logs.php`
- `docs/peracrm-phase2-primitives-implementation-notes.md`

## Existing areas that adopted the new primitives
Light, safe adoption only:
- `crm-overview.php`
  - **New Leads** now uses `crm-section` + `crm-row-list`.
  - **Latest Activity** now uses `crm-section` + `crm-activity-list`.
  - Leads/tasks desktop tables now opt into the `crm-table` primitive styling and use `crm-chip` for semantic status rendering.
- `crm-logs.php`
  - Email logs table now opts into the shared `crm-table` styling.
  - Status column now uses semantic `crm-chip` rendering.
- `crm-pipeline.php`
  - Reduced some metadata pill overuse by switching obvious generic metadata from pills to calmer text/meta rows.
  - Stage count now uses the shared chip primitive in a restrained neutral form.

## What was intentionally deferred
The following were **not** started in Phase 2 and remain deferred for later phases:
- client detail page restructure
- dashboard composition reorder
- full list-page redesign
- pipeline board redesign
- form grouping overhaul
- broad conversion of all legacy `.card-shell`, `.pill`, or `.crm-list` usages
- mobile/tablet-specific page-level layout rewrites beyond primitive-level responsive support

## `crm.js` behavior touched
- No JavaScript behavior changes were required for this phase.
- Existing shell header, nav drawer, view-toggle, and sort behavior were intentionally preserved unchanged.

## Risks / migration notes for later phases
- Legacy `.pill` and `.crm-list` patterns still exist in many templates; this phase establishes the replacement layer but does not remove all legacy usage.
- `crm-section` and `crm-table` currently coexist with `.card-shell` and older wrappers. Later phases should continue migrating page bodies while avoiding nested decorative containment.
- The pipeline received only metadata normalization, not structural redesign; a later dedicated phase should apply the new primitives more comprehensively.
- Some overview/task card surfaces still use older card anatomy because full dashboard and task-list restructuring are out of scope for Phase 2.

## Manual QA still required
Yes. Manual QA is still required on these screens:
- CRM overview
- leads/clients list
- tasks list
- pipeline
- email logs
- shell header + nav drawer behavior across desktop, tablet, and mobile widths

Recommended QA focus:
- confirm Phase 1 compact header remains intact
- confirm nav drawer still opens/closes correctly on smaller breakpoints
- confirm table view toggles still switch properly on leads/clients and tasks
- confirm new row-list and table primitives remain readable on desktop, tablet, and mobile
- confirm chips stay compact and do not expand line height excessively
- confirm no accidental screen-level restructure has started
