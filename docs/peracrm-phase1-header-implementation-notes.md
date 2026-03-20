# PeraCRM Phase 1 Header Implementation Notes

## Summary of what changed
- Replaced the shared CRM hero partial with a compact `crm-page-header` structure built around title, meta/subtitle, and right-aligned action groups.
- Moved the lead/client filters out of the hero concept and into a compact toolbar region below the page header.
- Added early `crm-toolbar` and `crm-action-group` foundations in shared CSS and adopted them in overview list/task views.
- Updated page-level header arguments so overview, leads/clients, tasks, pipeline, logs, create-new, and client detail all render through the compact header model.
- Removed `Create lead` from the persistent side-nav IA and kept it available as a page action / quick-access utility link.
- Tightened shell styling so the header/app shell reads as an operational workspace instead of a branded hero-first surface.

## Files changed
- `wp-content/plugins/peracrm/inc/views/partials/crm-header.php`
- `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-client.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-new.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-logs.php`
- `wp-content/plugins/peracrm/assets/frontend/crm.css`

## Assumptions made
- Phase 1 should update the shared shell/header markup and any obvious page-level action placement needed to stop the CRM feeling hero-led, while keeping deeper page body structures intact.
- Existing card/table/pipeline body layouts are intentionally left mostly as-is unless they needed toolbar/header separation for Phase 1.
- Log pages use the same shared header shell even though they were not called out as a primary target, because they consume the same partial and benefit from the shared app-shell treatment.

## Deferred items for later phases
- Full client detail restructuring remains deferred; Phase 1 only upgrades the top shell/header behavior.
- Dashboard section order and body-level density changes remain deferred.
- Full canonical list/table refactors remain deferred beyond the new toolbar scaffolding and action placement cleanup.
- Pipeline card anatomy and metadata cleanup remain deferred.
- Form grouping and large-scale input layout improvements remain deferred.

## crm.js behavior touched
- No functional JavaScript changes were required in this phase.
- Existing drawer toggle and view-toggle behavior were audited against the new markup usage and preserved unchanged.

## Risks / follow-up notes
- Some older page body regions still use legacy card/pill patterns, so Phase 1 will look notably improved in the shell/header first, with deeper consistency to follow in later phases.
- The client detail page now has two summary zones (shared page header plus existing local summary strip); this is intentional for Phase 1 safety and should be consolidated in the client-detail refactor phase.
- View toggles still rely on existing button classes and localStorage behavior; they are visually normalized for Phase 1 but not yet converted into a fully shared segmented-control system.
